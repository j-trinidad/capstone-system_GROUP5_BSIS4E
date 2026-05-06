<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';
require '../../includes/activity_logger.php';

// Get admin info for logging
$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminId = $_SESSION['user_id'];

// Fetch all parts
$stmt = $pdo->query("SELECT * FROM parts ORDER BY category, name");
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add/edit
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $price = (float) $_POST['price'];
    $category = trim($_POST['category']);

    if (empty($name) || empty($category) || $price < 0) {
        $message = "All fields are required!";
        $messageType = 'error';
    } else {
        if ($id) {
            // UPDATE PART
            // Get old data for comparison
            $oldStmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
            $oldStmt->execute([$id]);
            $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE parts SET name = ?, price = ?, category = ? WHERE id = ?");
            $stmt->execute([$name, $price, $category, $id]);
            $message = "Part updated successfully!";
            $messageType = 'success';
            
            // Log what changed
            $changes = [];
            if ($oldData['name'] !== $name) $changes[] = "Name: {$oldData['name']} → $name";
            if ($oldData['price'] != $price) $changes[] = "Price: ₱{$oldData['price']} → ₱$price";
            if ($oldData['category'] !== $category) $changes[] = "Category: {$oldData['category']} → $category";
            
            if (!empty($changes)) {
                $changeDetail = implode(', ', $changes);
                logActivity($pdo, $adminId, $adminName, 'part_update', "Updated part: $name. Changes: $changeDetail");
            }
        } else {
            // CREATE NEW PART
            $stmt = $pdo->prepare("INSERT INTO parts (name, price, category) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $category]);
            $id = $pdo->lastInsertId();
            $message = "Part added successfully!";
            $messageType = 'success';
            
            // Log part creation
            logActivity($pdo, $adminId, $adminName, 'part_create', "Created new part: $name (Category: $category, Price: ₱$price)");
        }
        header("Location: manage_parts.php?msg=" . urlencode($message) . "&type=" . $messageType);
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    
    // Get part info before deletion
    $partStmt = $pdo->prepare("SELECT id, name, category, price FROM parts WHERE id = ?");
    $partStmt->execute([$id]);
    $part = $partStmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->prepare("DELETE FROM parts WHERE id = ?")->execute([$id]);
    
    // Log deletion
    if ($part) {
        logActivity($pdo, $adminId, $adminName, 'part_delete', "Deleted part: {$part['name']} (Category: {$part['category']}, Price: ₱{$part['price']}) - ID: {$part['id']}");
    }
    
    header("Location: manage_parts.php?msg=" . urlencode("Part deleted successfully!") . "&type=success");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Parts - Admin</title>
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
    overflow-x: hidden;
}

.page-wrapper {
    display: flex;
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
    flex: 1;
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
}

.btn-danger:hover {
    background: #ff3838;
    transform: translateY(-2px);
}

.btn-edit {
    background: #3b82f6;
    color: #fff;
    padding: 0.6rem 1rem;
    font-size: 13px;
}

.btn-edit:hover {
    background: #2563eb;
}

.btn-delete {
    background: var(--error);
    color: #fff;
    padding: 0.6rem 1rem;
    font-size: 13px;
}

.btn-delete:hover {
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

td {
    padding: 1.2rem;
    border-bottom: 1px solid var(--border);
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

.category-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    background: rgba(255, 140, 0, 0.2);
    color: var(--primary);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.price-text {
    color: var(--success);
    font-weight: 700;
    font-family: 'Space Mono', monospace;
}

.actions-cell {
    display: flex;
    gap: 0.6rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease;
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
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    animation: slideUp 0.4s ease;
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
.form-group select {
    width: 100%;
    padding: 0.9rem 1.2rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group select option {
    background: var(--card-bg);
    color: var(--text-primary);
    padding: 0.5rem;
}

.form-group select option:checked {
    background: var(--primary);
    color: #1a1f3a;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
    box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
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
        gap: 1rem;
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
            <h1><i class="fas fa-boxes"></i> Manage Parts</h1>
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
            <i class="fas fa-plus"></i> Add New Part
        </button>
    </div>

    <?php if (!empty($parts)): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-tag"></i> Part Name</th>
                        <th><i class="fas fa-folder"></i> Category</th>
                        <th><i class="fas fa-dollar-sign"></i> Price</th>
                        <th style="text-align: right;"><i class="fas fa-cogs"></i> Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td>
                                <span class="category-badge"><?= htmlspecialchars($p['category']) ?></span>
                            </td>
                            <td class="price-text">₱<?= number_format($p['price'], 2) ?></td>
                            <td style="text-align: right;">
                                <div class="actions-cell">
                                    <button class="btn btn-edit" onclick="editPart(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>, '<?= htmlspecialchars($p['category']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this part?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="table-container">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No parts found. Create your first part to get started.</p>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Part
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal" id="partModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-plus"></i> Add Part</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="partForm">
            <input type="hidden" name="id" id="part-id">
            
            <div class="form-group">
                <label for="partName"><i class="fas fa-tag"></i> Part Name</label>
                <input type="text" id="partName" name="name" required placeholder="e.g., Synthetic Oil 5W-30">
            </div>

            <div class="form-group">
                <label for="partCategory"><i class="fas fa-folder"></i> Category</label>
                <select id="partCategory" name="category" required>
                    <option value="">Select a category</option>
                    <option value="Oil">Oil</option>
                    <option value="Tire">Tire</option>
                    <option value="Battery">Battery</option>
                    <option value="Chain">Chain</option>
                    <option value="Filter">Filter</option>
                    <option value="Brake">Brake</option>
                    <option value="Spark Plug">Spark Plug</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="partPrice"><i class="fas fa-dollar-sign"></i> Price (₱)</label>
                <input type="number" id="partPrice" name="price" step="0.01" min="0" required placeholder="0.00">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Part
                </button>
                <button type="button" class="btn" onclick="closeModal()" style="background: rgba(255,255,255,0.1); border: 1px solid var(--border); color: var(--text-primary);">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('partModal').classList.add('active');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Part';
    document.getElementById('partForm').reset();
    document.getElementById('part-id').value = '';
}

function editPart(id, name, price, category) {
    document.getElementById('partModal').classList.add('active');
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Part';
    document.getElementById('part-id').value = id;
    document.getElementById('partName').value = name;
    document.getElementById('partPrice').value = price;
    document.getElementById('partCategory').value = category;
    document.getElementById('partName').focus();
}

function closeModal() {
    document.getElementById('partModal').classList.remove('active');
}

document.getElementById('partModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>