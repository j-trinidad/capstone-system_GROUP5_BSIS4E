<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';
require '../../includes/activity_logger.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get admin info for logging
$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminId = $_SESSION['user_id'];

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!empty($_POST['selected_services'])) {
        $ids = array_map('intval', $_POST['selected_services']);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        // Get service names before deletion for logging
        $serviceStmt = $pdo->prepare("SELECT id, name FROM services WHERE id IN ($placeholders)");
        $serviceStmt->execute($ids);
        $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete services
        $stmt = $pdo->prepare("DELETE FROM services WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        // Log each deletion
        foreach ($services as $service) {
            logActivity($pdo, $adminId, $adminName, 'service_delete', "Deleted service: {$service['name']} (ID: {$service['id']})");
        }
        
        header("Location: manage_services.php?msg=" . count($ids) . " services deleted successfully!&type=success");
        exit;
    }
}

// Fetch all services
$stmt = $pdo->query("SELECT * FROM services ORDER BY name");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hardcoded brands for different service types (keyword-based)
$brandsByService = [
    'oil' => ['Shell Advance', 'Motul', 'Castrol', 'Yamalube', 'Honda Genuine Oil', 'Suzuki Oil', 'Mobil 1', 'Motul 7100', 'Castrol Power1', 'Shell Helix'],
    'brake' => ['Nissin', 'Tokico', 'Brembo', 'FDR', 'EBC', 'Ferodo', 'Yamaha Genuine', 'Honda Genuine', 'TRW', 'Vesrah'],
    'tire' => ['Pirelli', 'Michelin', 'Dunlop', 'Bridgestone', 'Metzeler', 'Continental', 'IRC', 'Maxxis', 'FDR', 'Corsa', 'Aspira', 'Zeneos'],
    'battery' => ['Motolite', 'GS Battery', 'Yuasa', 'Furukawa', 'ACDelco', 'Panasonic', 'Bosch'],
    'spark' => ['NGK', 'Denso', 'Bosch', 'Champion', 'NGK Iridium', 'Denso Iridium'],
    'filter' => ['K&N', 'BMC', 'Honda Genuine', 'Yamaha Genuine', 'Ferrox', 'Sprint Filter', 'DNA'],
    'chain' => ['DID', 'RK', 'EK', 'SSS', 'JT Sprocket', 'AFAM', 'Regina'],
    'sprocket' => ['DID', 'RK', 'EK', 'SSS', 'JT Sprocket', 'AFAM', 'Regina'],
    'clutch' => ['Ferodo', 'Vesrah', 'EBC', 'Honda Genuine', 'Yamaha Genuine', 'FCC'],
    'fluid' => ['Motul RBF600', 'Castrol DOT 4', 'Brembo DOT 4', 'Shell DOT 3', 'Wurth DOT 4', 'ATE DOT 4']
];

// Common tire sizes for motorcycles
$tireSizes = [
    'Front' => ['60/90-14', '70/90-14', '80/90-14', '90/90-14', '100/80-14', '110/70-14', '120/70-14'],
    'Rear' => ['70/90-14', '80/90-14', '90/90-14', '100/90-14', '110/80-14', '120/70-14', '130/70-14', '140/70-14'],
    'Scooter' => ['90/90-12', '100/90-12', '110/70-12', '120/70-12', '130/70-12'],
    'Big Bike' => ['110/70-17', '120/70-17', '150/60-17', '160/60-17', '180/55-17', '190/50-17', '190/55-17']
];

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
    $id          = $_POST['id'] ?? null;
    $service_key = strtolower(trim($_POST['service_key']));
    $name        = trim($_POST['name']);
    $base_price  = (float) $_POST['base_price'];
    $slug        = strtolower(str_replace(' ', '-', $name));

    if ($service_key === '' || $name === '' || $base_price <= 0) {
        $message = "All fields are required and price must be positive.";
        $messageType = 'error';
    } elseif (!$id) {
        // ========== CREATE NEW SERVICE ==========
        $check = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_key = ?");
        $check->execute([$service_key]);

        if ($check->fetchColumn() > 0) {
            $message = "Service Key already exists.";
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (service_key, name, slug, base_price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$service_key, $name, $slug, $base_price]);
            $id = $pdo->lastInsertId();
            $message = "Service added successfully!";
            $messageType = 'success';
            
            // Log service creation
            logActivity($pdo, $adminId, $adminName, 'service_create', "Created new service: $name (Key: $service_key, Price: ₱$base_price)");
        }
    } else {
        // ========== UPDATE EXISTING SERVICE ==========
        // Get old data for comparison
        $oldStmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $oldStmt->execute([$id]);
        $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if service_key already exists (on other services)
        $checkKeyStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_key = ? AND id != ?");
        $checkKeyStmt->execute([$service_key, $id]);
        
        if ($checkKeyStmt->fetchColumn() > 0) {
            $message = "Service Key already exists.";
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE services SET service_key = ?, name = ?, slug = ?, base_price = ? WHERE id = ?");
            $stmt->execute([$service_key, $name, $slug, $base_price, $id]);
            $message = "Service updated successfully!";
            $messageType = 'success';
            
            // Log what changed
            $changes = [];
            if ($oldData['service_key'] !== $service_key) $changes[] = "Service Key: {$oldData['service_key']} → $service_key";
            if ($oldData['name'] !== $name) $changes[] = "Name: {$oldData['name']} → $name";
            if ($oldData['base_price'] !== $base_price) $changes[] = "Price: ₱{$oldData['base_price']} → ₱$base_price";
            
            if (!empty($changes)) {
                $changeDetail = implode(', ', $changes);
                logActivity($pdo, $adminId, $adminName, 'service_update', "Updated service: $name. Changes: $changeDetail");
            }
        }
    }

    // ========== HANDLE BRANDS (for both create and update) ==========
    if ($id && isset($_POST['brand_inputs']) && $messageType === 'success') {
        $brandInputs = $_POST['brand_inputs'];
        $brandPrices = $_POST['brand_prices'] ?? [];
        $brandCoverages = $_POST['brand_coverages'] ?? [];
        $brandSizes = $_POST['brand_sizes'] ?? [];
        
        // Get old brands before deletion for logging updates
        $oldBrandsStmt = $pdo->prepare("SELECT * FROM brands WHERE service_id = ?");
        $oldBrandsStmt->execute([$id]);
        $oldBrands = $oldBrandsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pdo->prepare("DELETE FROM brands WHERE service_id = ?")->execute([$id]);

        $isMaintenanceType = ($service_key === 'maintenance' || 
                             $service_key === 'tuneup' || 
                             $service_key === 'tune_up' || 
                             $service_key === 'engine_tuneup' ||
                             $service_key === 'engine_tune_up' ||
                             $service_key === 'general_maintenance' ||
                             strpos($service_key, 'maintenance') !== false ||
                             strpos($service_key, 'tuneup') !== false ||
                             strpos($service_key, 'tune_up') !== false);
        
        $count = count($brandInputs);
        $brandCount = 0;
        
        for ($i = 0; $i < $count; $i++) {
            $brandInput = trim($brandInputs[$i] ?? '');
            $brandPrice = $brandPrices[$i] ?? 0;
            
            if ($brandInput === '' || !is_numeric($brandPrice)) {
                continue;
            }
            
            $brandName = $brandInput;
            $coverage = null;
            
            if ($service_key === 'tire' && isset($brandSizes[$i])) {
                $sizes = array_filter(array_map('trim', explode(",", $brandSizes[$i])));
                if (!empty($sizes)) {
                    $coverage = json_encode($sizes);
                }
            } elseif ($isMaintenanceType && isset($brandCoverages[$i]) && trim($brandCoverages[$i]) !== '') {
                $coverageItems = array_filter(array_map('trim', explode("\n", $brandCoverages[$i])));
                if (!empty($coverageItems)) {
                    $coverage = json_encode($coverageItems);
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO brands (service_id, name, price, coverage) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $brandName, (float)$brandPrice, $coverage]);
            $brandCount++;
        }
        
        // Log brand additions/updates
        if (!isset($oldData)) {
            // New service - log brand additions
            if ($brandCount > 0) {
                logActivity($pdo, $adminId, $adminName, 'service_brand_add', "Added $brandCount brand options to service: $name");
            }
        } else {
            // Updated service - log brand changes
            $oldBrandCount = count($oldBrands);
            if ($brandCount !== $oldBrandCount) {
                logActivity($pdo, $adminId, $adminName, 'service_brand_update', "Updated brands for service: $name (Changed from $oldBrandCount to $brandCount brand options)");
            } elseif ($brandCount > 0) {
                logActivity($pdo, $adminId, $adminName, 'service_brand_update', "Updated $brandCount brand options for service: $name");
            }
        }
    }

    // ALWAYS redirect to show message (success or error)
    if ($messageType === 'success' || $messageType === 'error') {
        header("Location: manage_services.php?msg=" . urlencode($message) . "&type=" . $messageType);
        exit;
    }
}

// Handle single delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    
    // Get service info before deletion
    $serviceStmt = $pdo->prepare("SELECT id, name FROM services WHERE id = ?");
    $serviceStmt->execute([$id]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
    
    // Log deletion
    if ($service) {
        logActivity($pdo, $adminId, $adminName, 'service_delete', "Deleted service: {$service['name']} (ID: {$service['id']})");
    }
    
    header("Location: manage_services.php?msg=Service deleted successfully!&type=success");
    exit;
}

// Fetch related data for editing
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editData) {
        $brandStmt = $pdo->prepare("SELECT * FROM brands WHERE service_id = ?");
        $brandStmt->execute([$id]);
        $editData['brands'] = $brandStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Services - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary: #ff8c00;
    --secondary: #e52e71;
    --dark-bg: #0a0e27;
    --card-bg: #1a1f3a;
    --border: rgba(255, 140, 0, 0.2);
    --text-primary: #fff;
    --text-secondary: #b0b8d4;
    --success: #00d084;
    --error: #ff4757;
    --warning: #ffa502;
}

body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
    min-height: 100vh;
}

.header {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    border-bottom: 1px solid var(--border);
    padding: 2rem 2.5rem;
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(10px);
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1400px;
    margin: 0 auto;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.back-btn {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
    width: 44px;
    height: 44px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 18px;
}

.back-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    transform: translateX(-4px);
}

.header h1 {
    font-size: 28px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.header h1 i {
    font-size: 32px;
}

.main-content {
    padding: 2.5rem;
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
}

.message {
    padding: 1.2rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.success {
    background: rgba(0, 208, 132, 0.15);
    border: 1px solid var(--success);
    color: var(--success);
}

.message.error {
    background: rgba(255, 71, 87, 0.15);
    border: 1px solid var(--error);
    color: var(--error);
}

.message i {
    font-size: 18px;
    flex-shrink: 0;
}

.controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.btn {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(255, 140, 0, 0.4);
}

.btn-danger {
    background: var(--error);
    color: #fff;
    box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(255, 71, 87, 0.4);
}

.btn-edit {
    background: #3b82f6;
    color: #fff;
    padding: 0.6rem 1rem;
    font-size: 13px;
}

.btn-edit:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

.btn-delete {
    background: var(--error);
    color: #fff;
    padding: 0.6rem 1rem;
    font-size: 13px;
}

.btn-delete:hover {
    background: #ff3838;
    transform: translateY(-2px);
}

.btn-add-item {
    background: var(--success);
    color: #fff;
    padding: 0.6rem 1rem;
    font-size: 13px;
    margin-top: 1rem;
}

.btn-add-item:hover {
    background: #00b86d;
}

.btn-remove-item {
    background: var(--error);
    color: #fff;
    padding: 0.5rem 0.8rem;
    font-size: 12px;
}

.btn-remove-item:hover {
    background: #ff3838;
}

.table-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: linear-gradient(90deg, rgba(255, 140, 0, 0.2), rgba(229, 46, 113, 0.2));
    border-bottom: 2px solid var(--border);
}

th {
    padding: 1.2rem;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

th:last-child {
    text-align: center;
}

td {
    padding: 1.2rem;
    border-bottom: 1px solid var(--border);
}

td:last-child {
    text-align: center;
}

tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background: rgba(255, 140, 0, 0.05);
}

tbody tr:last-child td {
    border-bottom: none;
}

.service-key-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Space Mono', monospace;
}

.price-text {
    color: var(--success);
    font-weight: 700;
    font-family: 'Space Mono', monospace;
}

.actions-cell {
    display: flex;
    gap: 0.6rem;
    justify-content: center;
}

.checkbox-cell {
    width: 45px;
    text-align: center;
}

.checkbox-cell input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
    overflow-y: auto;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 2rem;
    width: 90%;
    max-width: 700px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    animation: slideUp 0.4s ease;
    margin: 2rem auto;
    max-height: 90vh;
    overflow-y: auto;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.8rem;
}

.modal-header h2 {
    font-size: 22px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.modal-close {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 24px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--error);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.6rem;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.9rem 1.2rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-family: inherit;
    transition: all 0.3s ease;
}

.form-group select option {
    background: var(--card-bg);
    color: var(--text-primary);
}

/* FIX: Tire size selector - make text visible */
.tire-size-selector {
    background: rgba(255, 255, 255, 0.08) !important;
    color: var(--text-primary) !important;
}

.tire-size-selector option {
    background: var(--card-bg) !important;
    color: var(--text-primary) !important;
    padding: 0.5rem !important;
}

.tire-size-selector optgroup {
    background: rgba(255, 140, 0, 0.15) !important;
    color: var(--primary) !important;
    font-weight: 700;
    font-size: 13px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
    box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
}

.section {
    display: none;
    margin-top: 2rem;
    padding: 1.5rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255, 140, 0, 0.05);
}

.section.active {
    display: block;
}

.section h3 {
    color: var(--primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.section-desc {
    color: var(--text-secondary);
    font-size: 12px;
    margin-bottom: 1rem;
}

.item-group {
    margin-bottom: 1.2rem;
    padding: 1.2rem;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    border: 1px solid rgba(255, 140, 0, 0.15);
}

.item-group input,
.item-group select,
.item-group textarea {
    margin-bottom: 0.8rem;
}

.input-mode-selector {
    display: flex;
    gap: 0.8rem;
    margin-bottom: 1rem;
    padding: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
}

.mode-btn {
    flex: 1;
    padding: 0.8rem 1rem;
    border: 2px solid var(--border);
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-secondary);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.mode-btn i {
    font-size: 14px;
}

.mode-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(255, 140, 0, 0.3);
    transform: translateY(-2px);
}

.mode-btn:hover:not(.active) {
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
    color: var(--primary);
}

.input-wrapper {
    display: none;
}

.input-wrapper.active {
    display: block;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.form-actions .btn {
    flex: 1;
    justify-content: center;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .main-content {
        padding: 1.5rem;
    }

    .header {
        padding: 1.5rem;
    }

    .header-content {
        flex-direction: column;
        gap: 1rem;
    }

    .header h1 {
        font-size: 20px;
    }

    .controls-bar {
        flex-direction: column;
    }

    .btn-primary {
        width: 100%;
        justify-content: center;
    }

    table {
        font-size: 13px;
    }

    th, td {
        padding: 0.8rem;
    }

    .actions-cell {
        flex-direction: column;
    }

    .modal-content {
        width: 95%;
        padding: 1.5rem;
    }

    .input-mode-selector {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="header-left">
            <button class="back-btn" onclick="location.href='admin_dashboard.php'" title="Back to Dashboard">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1><i class="fas fa-tools"></i> Manage Services</h1>
        </div>
    </div>
</div>

<div class="main-content">
    <?php if (isset($_GET['msg'])): ?>
        <div class="message <?= htmlspecialchars($_GET['type'] ?? 'success') ?>">
            <i class="fas fa-<?= ($_GET['type'] ?? 'success') === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= htmlspecialchars($_GET['msg']) ?></span>
        </div>
    <?php elseif ($message): ?>
        <div class="message <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <div class="controls-bar">
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> Add New Service
        </button>
        <button class="btn btn-danger" onclick="bulkDelete()" id="bulkDeleteBtn" style="display:none;">
            <i class="fas fa-trash-alt"></i> Delete Selected
        </button>
    </div>

    <?php if (!empty($services)): ?>
        <form method="POST" id="bulkDeleteForm">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th><i class="fas fa-key"></i> Service Key</th>
                            <th><i class="fas fa-tag"></i> Name</th>
                            <th><i class="fas fa-wrench"></i> Labor Price</th>
                            <th><i class="fas fa-cogs"></i> Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td class="checkbox-cell"><input type="checkbox" name="selected_services[]" value="<?= $s['id'] ?>" class="service-checkbox" onchange="toggleBulkDelete()"></td>
                                <td><span class="service-key-badge"><?= htmlspecialchars($s['service_key'] ?? $s['name']) ?></span></td>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td class="price-text">₱<?= number_format($s['base_price'] ?? $s['price'], 2) ?></td>
                                <td>
                                    <div class="actions-cell">
                                        <button type="button" class="btn btn-edit" onclick="editService(<?= $s['id'] ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?delete=<?= $s['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this service?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php else: ?>
        <div class="table-container">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No services found. Create your first service to get started.</p>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Service
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal" id="serviceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-plus"></i> Add Service</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="serviceForm">
            <input type="hidden" name="id" id="serviceId">
            
            <div class="form-group">
                <label for="serviceKey"><i class="fas fa-key"></i> Service Key</label>
                <input type="text" id="serviceKey" name="service_key" required placeholder="e.g., oil, tire, brake, spark, battery" onchange="toggleSections()" onkeyup="toggleSections()">
                <small style="color: var(--text-secondary); font-size: 11px; margin-top: 0.3rem; display: block;">Just type the keyword (oil, brake, tire, etc.) - brands will auto-load!</small>
            </div>

            <div class="form-group">
                <label for="serviceName"><i class="fas fa-tag"></i> Service Name</label>
                <input type="text" id="serviceName" name="name" required placeholder="Enter service name">
            </div>

            <div class="form-group">
                <label for="servicePrice"><i class="fas fa-wrench"></i> Labor Price (₱)</label>
                <input type="number" id="servicePrice" name="base_price" step="0.01" min="0" required placeholder="0.00">
                <small style="color: var(--text-secondary); font-size: 11px; margin-top: 0.3rem; display: block;">Labor/service fee only (parts price added separately)</small>
            </div>

            <!-- Brands Section - SHOWN BY DEFAULT -->
            <div class="section active" id="brandsSection">
                <h3><i class="fas fa-box-open"></i> Parts Brands & Pricing</h3>
                <p class="section-desc">Add brand options for this service (e.g., oil brands for oil change service).</p>
                <div id="brandsList"></div>
                <button type="button" class="btn btn-add-item" onclick="addBrand()">
                    <i class="fas fa-plus"></i> Add Brand Option
                </button>
            </div>

            <!-- Packages Section -->
            <div class="section" id="packagesSection">
                <h3><i class="fas fa-box"></i> Service Packages</h3>
                <p class="section-desc">Add maintenance packages with coverage items.</p>
                <div id="packagesList"></div>
                <button type="button" class="btn btn-add-item" onclick="addPackage()">
                    <i class="fas fa-plus"></i> Add Package
                </button>
            </div>

            <!-- Tire Sizes Section -->
            <div class="section" id="sizesSection">
                <h3><i class="fas fa-compact-disc"></i> Tire Options</h3>
                <p class="section-desc">Select tire brands with available sizes.</p>
                <div id="sizesList"></div>
                <button type="button" class="btn btn-add-item" onclick="addSize()">
                    <i class="fas fa-plus"></i> Add Tire Option
                </button>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_service" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Service
                </button>
                <button type="button" class="btn" onclick="closeModal()" style="background: rgba(255,255,255,0.1); border: 1px solid var(--border);">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let brandCounter = 0;
const brandsByService = <?= json_encode($brandsByService) ?>;
const tireSizes = <?= json_encode($tireSizes) ?>;

// Function to detect keyword in service key and return matching brands
function getBrandsForServiceKey(serviceKey) {
    serviceKey = serviceKey.toLowerCase().trim();
    
    console.log('🔍 Searching brands for service key:', serviceKey);
    
    // Check if service key contains any of our keywords
    for (const [keyword, brands] of Object.entries(brandsByService)) {
        if (serviceKey.includes(keyword)) {
            console.log('✅ Found matching keyword:', keyword, '- Brands:', brands);
            return brands;
        }
    }
    
    console.log('❌ No matching brands found for:', serviceKey);
    return [];
}

// Function to check if service is tire-related
function isTireService(serviceKey) {
    return serviceKey.toLowerCase().includes('tire');
}

// Function to check if service is maintenance-related
function isMaintenanceService(serviceKey) {
    const key = serviceKey.toLowerCase();
    return key.includes('maintenance') || key.includes('tuneup') || key.includes('tune_up');
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.service-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    toggleBulkDelete();
}

function toggleBulkDelete() {
    const checked = document.querySelectorAll('.service-checkbox:checked').length;
    document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? 'inline-flex' : 'none';
}

function bulkDelete() {
    const checked = document.querySelectorAll('.service-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one service to delete.');
        return;
    }
    if (confirm(`Are you sure you want to delete ${checked} service(s)?`)) {
        const form = document.getElementById('bulkDeleteForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_delete';
        input.value = '1';
        form.appendChild(input);
        form.submit();
    }
}

function openModal() {
    document.getElementById('serviceModal').classList.add('active');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Service';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceId').value = '';
    document.getElementById('brandsList').innerHTML = '';
    document.getElementById('packagesList').innerHTML = '';
    document.getElementById('sizesList').innerHTML = '';
    brandCounter = 0;
    
    // CHANGED: Show brands section by default when opening modal
    document.getElementById('brandsSection').classList.add('active');
    document.getElementById('packagesSection').classList.remove('active');
    document.getElementById('sizesSection').classList.remove('active');
}

function editService(id) {
    window.location.href = '?edit=' + id;
}

function toggleSections() {
    const key = document.getElementById('serviceKey').value.toLowerCase().trim();
    
    const isTire = isTireService(key);
    const isMaintenance = isMaintenanceService(key);
    const hasBrands = getBrandsForServiceKey(key).length > 0;
    
    const brandsSection = document.getElementById('brandsSection');
    const packagesSection = document.getElementById('packagesSection');
    const sizesSection = document.getElementById('sizesSection');
    
    // CHANGED: Always show brands section by default, unless it's tire or maintenance
    if (isTire) {
        brandsSection.classList.remove('active');
        packagesSection.classList.remove('active');
        sizesSection.classList.add('active');
    } else if (isMaintenance) {
        brandsSection.classList.remove('active');
        packagesSection.classList.add('active');
        sizesSection.classList.remove('active');
    } else {
        // Default: show brands section
        brandsSection.classList.add('active');
        packagesSection.classList.remove('active');
        sizesSection.classList.remove('active');
    }
    
    // Update brand options when service key changes
    updateBrandOptions();
}

function addBrand() {
    const list = document.getElementById('brandsList');
    const serviceKey = document.getElementById('serviceKey').value.toLowerCase().trim();
    const brands = getBrandsForServiceKey(serviceKey);
    
    const item = document.createElement('div');
    item.className = 'item-group';
    item.dataset.index = brandCounter;
    
    let brandOptions = '<option value="">-- Choose Brand --</option>';
    if (brands.length > 0) {
        brandOptions += brands.map(brand => `<option value="${brand}">${brand}</option>`).join('');
    } else {
        brandOptions += '<option value="">No brands available - use manual input</option>';
    }
    
    item.innerHTML = `
        <div class="input-mode-selector">
            <button type="button" class="mode-btn active" onclick="toggleInputMode(this, 'select', ${brandCounter})">
                <i class="fas fa-list-ul"></i> Select Brand
            </button>
            <button type="button" class="mode-btn" onclick="toggleInputMode(this, 'manual', ${brandCounter})">
                <i class="fas fa-keyboard"></i> Manual Input
            </button>
        </div>
        <div class="input-wrapper active" id="select-${brandCounter}">
            <select name="brand_inputs[]" class="brand-select" data-service-key="${serviceKey}">
                ${brandOptions}
            </select>
        </div>
        <div class="input-wrapper" id="manual-${brandCounter}">
            <input type="text" name="brand_inputs[]" placeholder="Enter brand name manually" class="brand-manual" disabled>
        </div>
        <input type="number" step="0.01" name="brand_prices[]" placeholder="Parts Price (₱)">
        <button type="button" class="btn btn-remove-item" onclick="removeItem(this)">
            <i class="fas fa-trash"></i> Remove
        </button>
    `;
    list.appendChild(item);
    brandCounter++;
}

// Update brand options when service key changes
function updateBrandOptions() {
    const serviceKey = document.getElementById('serviceKey').value.toLowerCase().trim();
    const brands = getBrandsForServiceKey(serviceKey);
    
    // Update all existing brand selects
    document.querySelectorAll('#brandsList .brand-select').forEach(select => {
        const currentValue = select.value;
        let brandOptions = '<option value="">-- Choose Brand --</option>';
        
        if (brands.length > 0) {
            brandOptions += brands.map(brand => `<option value="${brand}">${brand}</option>`).join('');
        } else {
            brandOptions += '<option value="">No brands available - use manual input</option>';
        }
        
        select.innerHTML = brandOptions;
        
        // Restore previous value if it exists in new options
        if (currentValue && brands.includes(currentValue)) {
            select.value = currentValue;
        }
        
        // Update the data attribute
        select.setAttribute('data-service-key', serviceKey);
    });
    
    // Also update tire brand selects
    document.querySelectorAll('#sizesList .brand-select').forEach(select => {
        const tireBrands = brandsByService['tire'] || [];
        const currentValue = select.value;
        
        let brandOptions = '<option value="">-- Choose Tire Brand --</option>';
        if (tireBrands.length > 0) {
            brandOptions += tireBrands.map(brand => `<option value="${brand}">${brand}</option>`).join('');
        } else {
            brandOptions += '<option value="">No tire brands available</option>';
        }
        
        select.innerHTML = brandOptions;
        
        // Restore previous value if it exists in new options
        if (currentValue && tireBrands.includes(currentValue)) {
            select.value = currentValue;
        }
    });
}

function addPackage() {
    const list = document.getElementById('packagesList');
    const item = document.createElement('div');
    item.className = 'item-group';
    item.dataset.index = brandCounter;
    item.innerHTML = `
        <input type="text" name="brand_inputs[]" placeholder="Package Name (e.g., Basic Maintenance)" required>
        <input type="number" step="0.01" name="brand_prices[]" placeholder="Package Price (₱)" required>
        <textarea name="brand_coverages[]" placeholder="Coverage items (one per line)
Example:
• Engine Oil Change
• Brake Inspection
• Tire Pressure Check" rows="5"></textarea>
        <button type="button" class="btn btn-remove-item" onclick="removeItem(this)">
            <i class="fas fa-trash"></i> Remove
        </button>
    `;
    list.appendChild(item);
    brandCounter++;
}

function addSize() {
    const list = document.getElementById('sizesList');
    const tireBrands = brandsByService['tire'] || [];
    
    const item = document.createElement('div');
    item.className = 'item-group';
    item.dataset.index = brandCounter;
    
    let brandOptions = '<option value="">-- Choose Tire Brand --</option>';
    if (tireBrands.length > 0) {
        brandOptions += tireBrands.map(brand => `<option value="${brand}">${brand}</option>`).join('');
    } else {
        brandOptions += '<option value="" disabled>No tire brands available</option>';
    }
    
    // Build size options with categories - FIXED STYLING
    let sizeOptions = '<option value="">-- Select Tire Size --</option>';
    for (const [category, sizes] of Object.entries(tireSizes)) {
        sizeOptions += `<optgroup label="${category}">`;
        sizeOptions += sizes.map(size => `<option value="${size}">${size}</option>`).join('');
        sizeOptions += '</optgroup>';
    }
    
    item.innerHTML = `
        <div class="input-mode-selector">
            <button type="button" class="mode-btn active" onclick="toggleInputMode(this, 'select', ${brandCounter})">
                <i class="fas fa-list-ul"></i> Select Brand
            </button>
            <button type="button" class="mode-btn" onclick="toggleInputMode(this, 'manual', ${brandCounter})">
                <i class="fas fa-keyboard"></i> Manual Input
            </button>
        </div>
        <div class="input-wrapper active" id="select-${brandCounter}">
            <select name="brand_inputs[]" class="brand-select">
                ${brandOptions}
            </select>
        </div>
        <div class="input-wrapper" id="manual-${brandCounter}">
            <input type="text" name="brand_inputs[]" placeholder="Enter tire brand manually" class="brand-manual" disabled>
        </div>
        <input type="number" step="0.01" name="brand_prices[]" placeholder="Price per Tire (₱)" required>
        <div style="margin-bottom: 0.8rem;">
            <label style="display: block; margin-bottom: 0.4rem; font-size: 12px; color: var(--text-secondary);">
                <i class="fas fa-ruler"></i> Available Sizes (select or type custom)
            </label>
            <select class="tire-size-selector" onchange="addTireSize(this)">
                ${sizeOptions}
            </select>
            <input type="text" name="brand_sizes[]" placeholder="Selected sizes (or type custom, comma-separated)" class="selected-sizes" style="width: 100%; padding: 0.7rem 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); margin-top: 0.5rem;">
        </div>
        <button type="button" class="btn btn-remove-item" onclick="removeItem(this)">
            <i class="fas fa-trash"></i> Remove
        </button>
    `;
    list.appendChild(item);
    brandCounter++;
}

// Function to add selected tire size to the input
function addTireSize(selectElement) {
    const selectedSize = selectElement.value;
    if (!selectedSize) return;
    
    const itemGroup = selectElement.closest('.item-group');
    const sizesInput = itemGroup.querySelector('.selected-sizes');
    
    // Get current sizes
    let currentSizes = sizesInput.value.trim();
    
    // Split into array and check if size already exists
    let sizesArray = currentSizes ? currentSizes.split(',').map(s => s.trim()) : [];
    
    if (!sizesArray.includes(selectedSize)) {
        sizesArray.push(selectedSize);
        sizesInput.value = sizesArray.join(', ');
    }
    
    // Reset selector
    selectElement.value = '';
}

function toggleInputMode(btn, mode, index) {
    const itemGroup = btn.closest('.item-group');
    
    // Update button states
    itemGroup.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Update input visibility and enabled state
    itemGroup.querySelectorAll('.input-wrapper').forEach(w => w.classList.remove('active'));
    itemGroup.querySelector(`#${mode}-${index}`).classList.add('active');
    
    // Enable the active input and disable the inactive one
    if (mode === 'select') {
        const manualInput = itemGroup.querySelector('.brand-manual');
        const selectInput = itemGroup.querySelector('.brand-select');
        if (manualInput) {
            manualInput.value = '';
            manualInput.disabled = true;
        }
        if (selectInput) {
            selectInput.disabled = false;
        }
    } else {
        const selectInput = itemGroup.querySelector('.brand-select');
        const manualInput = itemGroup.querySelector('.brand-manual');
        if (selectInput) {
            selectInput.value = '';
            selectInput.disabled = true;
        }
        if (manualInput) {
            manualInput.disabled = false;
        }
    }
}

function removeItem(btn) {
    btn.parentElement.remove();
}

function closeModal() {
    document.getElementById('serviceModal').classList.remove('active');
}

document.getElementById('serviceModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Form submission handler
document.getElementById('serviceForm').addEventListener('submit', function(e) {
    // Debug: Log form data
    const formData = new FormData(this);
    console.log('Form Data:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Check if service key, name, and price are filled
    const serviceKey = document.getElementById('serviceKey').value.trim();
    const serviceName = document.getElementById('serviceName').value.trim();
    const servicePrice = document.getElementById('servicePrice').value;
    
    if (!serviceKey || !serviceName || !servicePrice || parseFloat(servicePrice) <= 0) {
        e.preventDefault();
        alert('Please fill in all required fields (Service Key, Name, and Labor Price must be greater than 0)');
        return false;
    }
    
    // Allow form to submit normally
    return true;
});

<?php if ($editData): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('serviceModal').classList.add('active');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Service';
    document.getElementById('serviceId').value = '<?= $editData['id'] ?>';
    document.getElementById('serviceKey').value = '<?= htmlspecialchars($editData['service_key'] ?? $editData['name']) ?>';
    document.getElementById('serviceName').value = '<?= htmlspecialchars($editData['name']) ?>';
    document.getElementById('servicePrice').value = '<?= $editData['base_price'] ?? $editData['price'] ?>';
    
    toggleSections();
    
    const brands = <?= json_encode($editData['brands'] ?? []) ?>;
    
    brands.forEach((brand) => {
        const serviceKey = '<?= strtolower($editData['service_key'] ?? '') ?>';
        
        if (isTireService(serviceKey)) {
            addSize();
            const items = document.querySelectorAll('#sizesList .item-group');
            const last = items[items.length - 1];
            const currentIndex = last.dataset.index;
            
            const tireBrands = brandsByService['tire'] || [];
            if (tireBrands.includes(brand.name)) {
                last.querySelector('.brand-select').value = brand.name;
            } else {
                const manualBtn = last.querySelectorAll('.mode-btn')[1];
                toggleInputMode(manualBtn, 'manual', currentIndex);
                last.querySelector('.brand-manual').value = brand.name;
            }
            
            last.querySelector('input[name="brand_prices[]"]').value = brand.price;
            if (brand.coverage) {
                try {
                    const coverage = JSON.parse(brand.coverage);
                    last.querySelector('.selected-sizes').value = coverage.join(', ');
                } catch(e) {}
            }
        } else if (isMaintenanceService(serviceKey)) {
            addPackage();
            const items = document.querySelectorAll('#packagesList .item-group');
            const last = items[items.length - 1];
            last.querySelector('input[name="brand_inputs[]"]').value = brand.name;
            last.querySelector('input[name="brand_prices[]"]').value = brand.price;
            if (brand.coverage) {
                try {
                    const coverage = JSON.parse(brand.coverage);
                    last.querySelector('textarea[name="brand_coverages[]"]').value = coverage.join('\n');
                } catch(e) {}
            }
        } else {
            addBrand();
            const items = document.querySelectorAll('#brandsList .item-group');
            const last = items[items.length - 1];
            const currentIndex = last.dataset.index;
            
            const serviceBrands = getBrandsForServiceKey(serviceKey);
            if (serviceBrands.includes(brand.name)) {
                last.querySelector('.brand-select').value = brand.name;
            } else {
                const manualBtn = last.querySelectorAll('.mode-btn')[1];
                toggleInputMode(manualBtn, 'manual', currentIndex);
                last.querySelector('.brand-manual').value = brand.name;
            }
            
            last.querySelector('input[name="brand_prices[]"]').value = brand.price;
        }
    });
});
<?php endif; ?>
</script>

</body>
</html>