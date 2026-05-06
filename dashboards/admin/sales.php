<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

// Get date range from filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$filterBy = $_GET['filter_by'] ?? 'all';

// Validate dates
$startDate = date('Y-m-d', strtotime($startDate));
$endDate = date('Y-m-d', strtotime($endDate));

// Build WHERE clause
$whereClause = "WHERE b.status = 'completed' AND DATE(b.completed_at) BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($filterBy === 'mechanic' && isset($_GET['mechanic_id'])) {
    $whereClause .= " AND b.mechanic_id = ?";
    $params[] = (int)$_GET['mechanic_id'];
} elseif ($filterBy === 'service' && isset($_GET['service_type'])) {
    $whereClause .= " AND b.service_type = ?";
    $params[] = $_GET['service_type'];
}

// Get sales data
$stmt = $pdo->prepare("
    SELECT 
        b.id,
        b.brand,
        b.vehicle_type,
        b.service_type,
        b.schedule,
        b.service_location,
        b.labor_fee,
        b.service_fee,
        b.parts_total,
        b.total_price,
        b.completed_at,
        u.first_name,
        u.last_name,
        c.first_name as customer_first,
        c.last_name as customer_last
    FROM bookings b
    JOIN users u ON b.mechanic_id = u.id
    JOIN users c ON b.customer_id = c.id
    $whereClause
    ORDER BY b.completed_at DESC
");
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalRevenue = 0;
$totalBookings = count($sales);
$totalLabor = 0;
$totalService = 0;
$totalParts = 0;

foreach ($sales as $sale) {
    $totalRevenue += $sale['total_price'];
    $totalLabor += $sale['labor_fee'];
    $totalService += $sale['service_fee'];
    $totalParts += $sale['parts_total'];
}

// ✅ Get CURRENT MONTH stats (for stat cards display)
// This shows ALL completed bookings in the current month, regardless of their schedule date
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');

$monthlyStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(SUM(labor_fee), 0) as total_labor,
        COALESCE(SUM(service_fee), 0) as total_service,
        COALESCE(SUM(parts_total), 0) as total_parts
    FROM bookings 
    WHERE status = 'completed' 
    AND DATE(completed_at) BETWEEN ? AND ?
");
$monthlyStmt->execute([$currentMonthStart, $currentMonthEnd]);
$monthlyStats = $monthlyStmt->fetch(PDO::FETCH_ASSOC);

// ✅ Get ALL-TIME stats (for modals)
$allTimeStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_bookings,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(SUM(labor_fee), 0) as total_labor,
        COALESCE(SUM(service_fee), 0) as total_service,
        COALESCE(SUM(parts_total), 0) as total_parts
    FROM bookings 
    WHERE status = 'completed'
");
$allTimeStats = $allTimeStmt->fetch(PDO::FETCH_ASSOC);

// ✅ Get MONTHLY mechanic labor breakdown
$monthlyMechanicStmt = $pdo->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        COUNT(b.id) as total_jobs,
        COALESCE(SUM(b.labor_fee), 0) as total_labor
    FROM users u
    LEFT JOIN bookings b ON u.id = b.mechanic_id 
        AND b.status = 'completed'
        AND DATE(b.completed_at) BETWEEN ? AND ?
    WHERE u.role = 'mechanic'
    GROUP BY u.id, u.first_name, u.last_name
    ORDER BY total_labor DESC
");
$monthlyMechanicStmt->execute([$currentMonthStart, $currentMonthEnd]);
$monthlyMechanicLabor = $monthlyMechanicStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get ALL-TIME mechanic labor breakdown
$allTimeMechanicStmt = $pdo->query("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        COUNT(b.id) as total_jobs,
        COALESCE(SUM(b.labor_fee), 0) as total_labor
    FROM users u
    LEFT JOIN bookings b ON u.id = b.mechanic_id AND b.status = 'completed'
    WHERE u.role = 'mechanic'
    GROUP BY u.id, u.first_name, u.last_name
    ORDER BY total_labor DESC
");
$allTimeMechanicLabor = $allTimeMechanicStmt->fetchAll(PDO::FETCH_ASSOC);

// Get mechanics list for filter
$mechanicsStmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'mechanic' ORDER BY first_name");
$mechanics = $mechanicsStmt->fetchAll(PDO::FETCH_ASSOC);

// Service type options
$serviceTypes = [
    'general_maintenance' => 'General Maintenance',
    'oil_change' => 'Oil Change',
    'brake_inspection' => 'Brake Inspection',
    'tire_replacement' => 'Tire Replacement',
    'battery_replacement' => 'Battery Replacement',
    'engine_diagnostic' => 'Engine Diagnostic',
    'chain_replacement' => 'Chain Replacement',
    'suspension_repair' => 'Suspension Repair',
    'electrical_repair' => 'Electrical Repair'
];

$currentPage = basename($_SERVER['PHP_SELF']);
$currentMonthName = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sales Report - MotorService Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
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
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
    overflow: hidden;
}

/* HAMBURGER BUTTON */
.hamburger-btn {
    display: none;
    position: fixed; top: 20px; left: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 12px;
    cursor: pointer; box-shadow: 0 4px 15px rgba(255,140,0,0.4);
    transition: all 0.3s ease;
}
.hamburger-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(255,140,0,0.6); }
.hamburger-btn span {
    display: block; width: 25px; height: 3px;
    background: #1a1f3a; margin: 5px auto;
    border-radius: 2px; transition: all 0.3s ease;
}
.hamburger-btn.active span:nth-child(1) { transform: rotate(45deg) translate(8px,8px); }
.hamburger-btn.active span:nth-child(2) { opacity: 0; }
.hamburger-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(7px,-7px); }

/* SIDEBAR OVERLAY */
.sidebar-overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.7); z-index: 999;
    opacity: 0; transition: opacity 0.3s ease;
}
.sidebar-overlay.active { display: block; opacity: 1; }

a {
    color: inherit;
    text-decoration: none;
}

/* FIXED SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(180deg, #0f1419 0%, #1a1f3a 100%);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 25px 20px;
    z-index: 1000;
    transition: transform 0.3s ease;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.logo {
    font-size: 1.6rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 35px;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    color: var(--text-secondary);
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    transform: translateX(5px);
}

.sidebar nav a i {
    width: 20px;
    text-align: center;
}

.sidebar .footer {
    margin-top: auto;
    font-size: 12px;
    color: var(--text-secondary);
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

/* MAIN CONTENT */
.main {
    margin-left: 260px;
    padding: 40px;
    width: calc(100% - 260px);
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
}

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 8px 24px rgba(255, 140, 0, 0.2);
}

.stat-card .icon {
    font-size: 32px;
    color: var(--primary);
    margin-bottom: 12px;
}

.stat-card .title {
    color: var(--text-secondary);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 10px;
}

.stat-card .number {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.stat-card .subtitle {
    font-size: 12px;
    color: var(--text-secondary);
}

.filters {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

.filter-group input,
.filter-group select {
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 13px;
    font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
}

.filter-group select option {
    background: var(--card-bg);
    color: var(--text-primary);
}

.filter-group select option:checked {
    background: var(--primary);
    color: #1a1f3a;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.btn {
    padding: 10px 20px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 140, 0, 0.3);
}

.btn-reset {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
}

.btn-reset:hover {
    background: rgba(255, 140, 0, 0.1);
    border-color: var(--primary);
}

.table-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
    padding: 16px;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
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

.amount {
    color: var(--success);
    font-weight: 700;
    font-family: 'Space Mono', monospace;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-home {
    background: rgba(0, 208, 132, 0.2);
    color: var(--success);
}

.badge-shop {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.summary-box {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-top: 30px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.summary-item {
    text-align: center;
}

.summary-item-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

.summary-item-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

/* Modal Styles */
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
    z-index: 9999;
    animation: fadeIn 0.3s ease;
    overflow-y: auto;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.show {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 30px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
    animation: slideUp 0.4s ease;
    margin: 20px;
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

.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.modal-header h2 {
    font-size: 24px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.modal-close {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 28px;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

.modal-close:hover {
    color: var(--error);
    background: rgba(255, 71, 87, 0.1);
}

.all-time-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.all-time-card {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.all-time-card .icon {
    font-size: 28px;
    color: var(--primary);
    margin-bottom: 10px;
}

.all-time-card .label {
    font-size: 11px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

.all-time-card .value {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
}

.all-time-card.featured {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.all-time-card.featured .icon {
    color: #fff;
    font-size: 36px;
}

.all-time-card.featured .label {
    color: rgba(255, 255, 255, 0.8);
}

.all-time-card.featured .value {
    color: #fff;
    font-size: 32px;
}

.clickable-card {
    cursor: pointer;
    position: relative;
}

.clickable-card::after {
    content: '\f06e';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 18px;
    color: rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.clickable-card:hover::after {
    color: rgba(255, 255, 255, 0.8);
    transform: scale(1.2);
}

/* ✅ Mechanic breakdown table styles */
.mechanic-table {
    width: 100%;
    margin-top: 20px;
}

.mechanic-table thead {
    background: linear-gradient(90deg, rgba(255, 140, 0, 0.15), rgba(229, 46, 113, 0.15));
}

.mechanic-table th {
    padding: 12px;
    font-size: 11px;
    color: var(--text-secondary);
}

.mechanic-table td {
    padding: 12px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
}

.mechanic-table tbody tr:hover {
    background: rgba(255, 140, 0, 0.05);
}

.mechanic-name {
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.mechanic-name i {
    color: var(--primary);
    font-size: 14px;
}

.rank-badge {
    display: inline-block;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff;
    font-weight: 700;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rank-badge.top-1 {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #1a1f3a;
}

.rank-badge.top-2 {
    background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
    color: #1a1f3a;
}

.rank-badge.top-3 {
    background: linear-gradient(135deg, #cd7f32, #d4af37);
    color: #1a1f3a;
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    h1 { font-size: 24px; }
    .filters { grid-template-columns: 1fr; }
    .all-time-stats { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }

    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }

    h1 { font-size: 20px; margin-top: 10px; }

    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 25px; }
    .stat-card { padding: 16px; }
    .stat-card .icon { font-size: 24px; margin-bottom: 8px; }
    .stat-card .number { font-size: 20px; }

    .filters { grid-template-columns: 1fr; }
    .filter-actions { flex-wrap: wrap; }
    .filter-actions .btn { flex: 1; text-align: center; }

    /* Scrollable table on mobile */
    .table-container { overflow-x: auto; }
    table { font-size: 12px; min-width: 700px; }
    th, td { padding: 10px 8px; }

    .summary-box { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .summary-item-value { font-size: 18px; }

    .page-header-row { flex-direction: column; align-items: flex-start; }
    .header-btns { width: 100%; }
    .btn-outline-receipts, .btn-print-pdf { flex: 1; justify-content: center; }

    .mechanic-table th, .mechanic-table td { padding: 8px; }
}

@media (max-width: 480px) {
    .main { padding: 75px 15px 15px 15px; }
    .stats-grid { grid-template-columns: 1fr; }
    .summary-box { grid-template-columns: 1fr; }
    .all-time-stats { grid-template-columns: 1fr; }
}
/* ── Page header row ── */
.page-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}
.page-header-row h1 { margin-bottom: 0; }

.header-btns {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-outline-receipts {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 20px;
    background: rgba(255, 140, 0, 0.1);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--primary);
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}
.btn-outline-receipts:hover {
    background: rgba(255, 140, 0, 0.2);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.btn-print-pdf {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 20px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    font-family: 'Outfit', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.35);
    transition: all 0.3s ease;
    white-space: nowrap;
}
.btn-print-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 140, 0, 0.5);
}

/* ── View receipt button inside table ── */
.tbl-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 13px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    text-decoration: none;
    transition: all 0.3s ease;
    white-space: nowrap;
}
.tbl-view-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(255, 140, 0, 0.4);
}

/* ── Print-only header ── */
.print-header { display: none; }

/* ── PRINT / SAVE AS PDF ── */
@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    body { background: #fff !important; color: #111 !important; display: block !important; }
    .sidebar,
    .filters,
    .stats-grid,
    .modal,
    .header-btns,
    .tbl-view-btn { display: none !important; }
    .main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 20px !important;
        overflow: visible !important;
        background: #fff !important;
        min-height: unset !important;
    }
    .page-header-row { margin-bottom: 6px !important; }
    h1 {
        -webkit-text-fill-color: #111 !important;
        background: none !important;
        color: #111 !important;
        font-size: 20px !important;
        margin-bottom: 0 !important;
    }
    .print-header {
        display: block !important;
        border-bottom: 3px solid #ff8c00;
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    .print-header-company { font-size: 19px; font-weight: 700; color: #ff8c00; }
    .print-header-meta { font-size: 11px; color: #555; margin-top: 4px; line-height: 1.6; }
    .table-container { border: none !important; box-shadow: none !important; background: transparent !important; }
    table { font-size: 11px !important; }
    thead { background: #ff8c00 !important; }
    th { background: #ff8c00 !important; color: #fff !important; padding: 7px 9px !important; font-size: 10px !important; }
    td { padding: 6px 9px !important; border-bottom: 1px solid #ddd !important; color: #111 !important; font-size: 11px !important; }
    tbody tr:nth-child(even) { background: #fff8f0 !important; }
    tbody tr:last-child td { border-bottom: 2px solid #ff8c00 !important; }
    .amount { color: #157a15 !important; font-weight: 700; }
    .badge { border: 1px solid #ccc !important; padding: 2px 7px !important; font-size: 10px !important; }
    .badge-home { color: #155724 !important; background: #d4edda !important; }
    .badge-shop  { color: #004085 !important; background: #cce5ff !important; }
    .summary-box {
        border: 1px solid #ffd080 !important;
        background: #fff8ee !important;
        margin-top: 14px !important;
        page-break-inside: avoid;
    }
    .summary-item-value { color: #ff8c00 !important; }
    .summary-item-label { color: #555 !important; }
}

</style>
</head>
<body>

<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-tools"></i>
        <span>MotorService</span>
    </div>

    <nav>
        <a href="admin_profile.php" class="<?= $currentPage == 'admin_profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="admin_dashboard.php" class="<?= $currentPage == 'admin_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="activity_log.php" class="<?= $currentPage == 'activity_log.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Activity Log
        </a>
        <a href="messages.php" class="<?= $currentPage == 'messages.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Messages
        </a>
        <a href="sales.php" class="<?= $currentPage == 'sales.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Sales
        </a>
        <a href="admin_receipts.php" class="<?= $currentPage == 'admin_receipts.php' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> All Receipts
        </a>
        <a href="admin_change_password.php" class="<?= $currentPage == 'admin_change_password.php' ? 'active' : '' ?>">
            <i class="fas fa-key"></i> Change Password
        </a>
        <a href="../../logout.php" style="margin-top: auto;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<main class="main">

    <!-- Print-only header (hidden on screen) -->
    <div class="print-header">
        <div class="print-header-company">🔧 MotorService — Sales Report</div>
        <div class="print-header-meta">
            Period: <?= date('M d, Y', strtotime($startDate)) ?> – <?= date('M d, Y', strtotime($endDate)) ?>
            &nbsp;|&nbsp; Generated: <?= date('F d, Y h:i A') ?>
            &nbsp;|&nbsp; Total Completed: <?= $totalBookings ?>
        </div>
    </div>

    <div class="page-header-row">
        <h1><i class="fas fa-chart-bar"></i> Sales Report</h1>
        <div class="header-btns">
            <a href="admin_receipts.php" class="btn-outline-receipts">
                <i class="fas fa-receipt"></i> All Receipts
            </a>
            <button class="btn-print-pdf" onclick="window.print()">
                <i class="fas fa-print"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- ✅ STATS - SHOWING CURRENT MONTH DATA (ALL COMPLETED BOOKINGS THIS MONTH) -->
    <div class="stats-grid">
        <div class="stat-card clickable-card" onclick="showAllTimeRevenue()">
            <div class="icon"><i class="fas fa-peso-sign"></i></div>
            <div class="title">Total Revenue</div>
            <div class="number">₱<?= number_format($monthlyStats['total_revenue'], 2) ?></div>
            <div class="subtitle">Completed in <?= $currentMonthName ?></div>
        </div>

        <div class="stat-card clickable-card" onclick="showAllTimeBookings()">
            <div class="icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="title">Total Bookings</div>
            <div class="number"><?= number_format($monthlyStats['total_bookings']) ?></div>
            <div class="subtitle">Completed in <?= $currentMonthName ?></div>
        </div>

        <div class="stat-card clickable-card" onclick="showAllTimeLabor()">
            <div class="icon"><i class="fas fa-wrench"></i></div>
            <div class="title">Labor Fees</div>
            <div class="number">₱<?= number_format($monthlyStats['total_labor'], 2) ?></div>
            <div class="subtitle">Completed in <?= $currentMonthName ?></div>
        </div>

        <div class="stat-card clickable-card" onclick="showAllTimeParts()">
            <div class="icon"><i class="fas fa-boxes"></i></div>
            <div class="title">Parts Total</div>
            <div class="number">₱<?= number_format($monthlyStats['total_parts'], 2) ?></div>
            <div class="subtitle">Completed in <?= $currentMonthName ?></div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
        <form method="GET" style="display: contents;">
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= $startDate ?>" required>
            </div>

            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= $endDate ?>" required>
            </div>

            <div class="filter-group">
                <label>Filter By</label>
                <select name="filter_by" onchange="document.querySelector('.mechanic-filter').style.display = this.value === 'mechanic' ? 'block' : 'none'; document.querySelector('.service-filter').style.display = this.value === 'service' ? 'block' : 'none';">
                    <option value="all" <?= $filterBy === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="mechanic" <?= $filterBy === 'mechanic' ? 'selected' : '' ?>>By Mechanic</option>
                    <option value="service" <?= $filterBy === 'service' ? 'selected' : '' ?>>By Service</option>
                </select>
            </div>

            <div class="filter-group mechanic-filter" style="display: <?= $filterBy === 'mechanic' ? 'block' : 'none' ?>;">
                <label>Mechanic</label>
                <select name="mechanic_id">
                    <option value="">Select Mechanic</option>
                    <?php foreach ($mechanics as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= isset($_GET['mechanic_id']) && $_GET['mechanic_id'] == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group service-filter" style="display: <?= $filterBy === 'service' ? 'block' : 'none' ?>;">
                <label>Service Type</label>
                <select name="service_type">
                    <option value="">Select Service</option>
                    <?php foreach ($serviceTypes as $key => $label): ?>
                        <option value="<?= $key ?>" <?= isset($_GET['service_type']) && $_GET['service_type'] == $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="sales.php" class="btn btn-reset"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="table-container">
        <?php if (!empty($sales)): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-calendar"></i> Completed</th>
                        <th><i class="fas fa-user"></i> Mechanic</th>
                        <th><i class="fas fa-user-check"></i> Customer</th>
                        <th><i class="fas fa-cogs"></i> Service</th>
                        <th><i class="fas fa-motorcycle"></i> Vehicle</th>
                        <th><i class="fas fa-map-marker"></i> Location</th>
                        <th><i class="fas fa-hammer"></i> Labor</th>
                        <th><i class="fas fa-tools"></i> Service</th>
                        <th><i class="fas fa-boxes"></i> Parts</th>
                        <th style="text-align: right;"><i class="fas fa-dollar-sign"></i> Total</th>
                        <th style="text-align: center;">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): 
                        $serviceLabel = $serviceTypes[$sale['service_type']] ?? ucfirst(str_replace('_', ' ', $sale['service_type']));
                    ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($sale['completed_at'])) ?><br>
                                <small style="color: var(--text-secondary);"><?= date('h:i A', strtotime($sale['completed_at'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']) ?></td>
                            <td><?= htmlspecialchars($sale['customer_first'] . ' ' . $sale['customer_last']) ?></td>
                            <td><?= htmlspecialchars($serviceLabel) ?></td>
                            <td><?= htmlspecialchars($sale['brand']) ?> <?= htmlspecialchars($sale['vehicle_type']) ?></td>
                            <td>
                                <span class="badge <?= $sale['service_location'] === 'home' ? 'badge-home' : 'badge-shop' ?>">
                                    <?= $sale['service_location'] === 'home' ? 'Home' : 'Shop' ?>
                                </span>
                            </td>
                            <td class="amount">₱<?= number_format($sale['labor_fee'], 2) ?></td>
                            <td class="amount">₱<?= number_format($sale['service_fee'], 2) ?></td>
                            <td class="amount">₱<?= number_format($sale['parts_total'], 2) ?></td>
                            <td class="amount" style="text-align: right;">₱<?= number_format($sale['total_price'], 2) ?></td>
                            <td style="text-align: center;">
                                <a href="admin_receipt_view.php?id=<?= $sale['id'] ?>" target="_blank" class="tbl-view-btn">
                                    <i class="fas fa-receipt"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No sales data found for the selected period</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- SUMMARY - SHOWING FILTERED DATA -->
    <?php if (!empty($sales)): ?>
    <div class="summary-box">
        <div class="summary-item">
            <div class="summary-item-label">Total Bookings</div>
            <div class="summary-item-value"><?= $totalBookings ?></div>
            <div class="subtitle" style="font-size: 11px; color: var(--text-secondary); margin-top: 5px;">
                <?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
            </div>
        </div>
        <div class="summary-item">
            <div class="summary-item-label">Total Labor</div>
            <div class="summary-item-value">₱<?= number_format($totalLabor, 2) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-item-label">Total Service</div>
            <div class="summary-item-value">₱<?= number_format($totalService, 2) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-item-label">Total Parts</div>
            <div class="summary-item-value">₱<?= number_format($totalParts, 2) ?></div>
        </div>
        <div class="summary-item">
            <div class="summary-item-label">Total Revenue</div>
            <div class="summary-item-value">₱<?= number_format($totalRevenue, 2) ?></div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- All-Time Revenue Modal -->
<div class="modal" id="revenueModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-chart-line"></i> All-Time Revenue Statistics</h2>
            <button class="modal-close" onclick="closeModal('revenueModal')">&times;</button>
        </div>

        <div class="all-time-stats">
            <div class="all-time-card featured">
                <div class="icon"><i class="fas fa-trophy"></i></div>
                <div class="label">Total All-Time Revenue</div>
                <div class="value">₱<?= number_format($allTimeStats['total_revenue'], 2) ?></div>
            </div>

            <div class="all-time-card">
                <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                <div class="label">Total Bookings</div>
                <div class="value"><?= number_format($allTimeStats['total_bookings']) ?></div>
            </div>

            <div class="all-time-card">
                <div class="icon"><i class="fas fa-calculator"></i></div>
                <div class="label">Average per Booking</div>
                <div class="value">₱<?= $allTimeStats['total_bookings'] > 0 ? number_format($allTimeStats['total_revenue'] / $allTimeStats['total_bookings'], 2) : '0.00' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- All-Time Bookings Modal -->
<div class="modal" id="bookingsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-clipboard-list"></i> All-Time Bookings Statistics</h2>
            <button class="modal-close" onclick="closeModal('bookingsModal')">&times;</button>
        </div>

        <div class="all-time-stats">
            <div class="all-time-card featured">
                <div class="icon"><i class="fas fa-trophy"></i></div>
                <div class="label">Total Completed Bookings</div>
                <div class="value"><?= number_format($allTimeStats['total_bookings']) ?></div>
            </div>

            <div class="all-time-card">
                <div class="icon"><i class="fas fa-peso-sign"></i></div>
                <div class="label">Total Revenue</div>
                <div class="value">₱<?= number_format($allTimeStats['total_revenue'], 2) ?></div>
            </div>

            <div class="all-time-card">
                <div class="icon"><i class="fas fa-calculator"></i></div>
                <div class="label">Average per Booking</div>
                <div class="value">₱<?= $allTimeStats['total_bookings'] > 0 ? number_format($allTimeStats['total_revenue'] / $allTimeStats['total_bookings'], 2) : '0.00' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ All-Time Labor Modal with Mechanic Breakdown -->
<div class="modal" id="laborModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-wrench"></i> Labor Statistics by Mechanic</h2>
            <button class="modal-close" onclick="closeModal('laborModal')">&times;</button>
        </div>

        <div class="all-time-stats">
            <div class="all-time-card featured">
                <div class="icon"><i class="fas fa-trophy"></i></div>
                <div class="label">Total Labor Fees (All-Time)</div>
                <div class="value">₱<?= number_format($allTimeStats['total_labor'], 2) ?></div>
            </div>

            <div class="all-time-card">
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="label">This Month (<?= date('M Y') ?>)</div>
                <div class="value">₱<?= number_format($monthlyStats['total_labor'], 2) ?></div>
            </div>
        </div>

        <!-- ✅ THIS MONTH Mechanic Breakdown -->
        <div style="margin-top: 30px;">
            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-calendar-check"></i> <?= $currentMonthName ?> Performance
            </h3>

            <table class="mechanic-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Mechanic</th>
                        <th style="text-align: center;">Jobs</th>
                        <th style="text-align: right;">Labor Fees</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyMechanicLabor as $index => $mechanic): 
                        $rank = $index + 1;
                        $rankClass = '';
                        if ($rank === 1) $rankClass = 'top-1';
                        elseif ($rank === 2) $rankClass = 'top-2';
                        elseif ($rank === 3) $rankClass = 'top-3';
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span>
                            </td>
                            <td>
                                <div class="mechanic-name">
                                    <i class="fas fa-user-cog"></i>
                                    <?= htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']) ?>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?= number_format($mechanic['total_jobs']) ?>
                            </td>
                            <td class="amount" style="text-align: right; font-size: 14px;">
                                ₱<?= number_format($mechanic['total_labor'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ✅ ALL-TIME Mechanic Breakdown -->
        <div style="margin-top: 30px;">
            <h3 style="font-size: 16px; margin-bottom: 15px; color: var(--secondary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-trophy"></i> All-Time Performance
            </h3>

            <table class="mechanic-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Mechanic</th>
                        <th style="text-align: center;">Jobs</th>
                        <th style="text-align: right;">Labor Fees</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTimeMechanicLabor as $index => $mechanic): 
                        $rank = $index + 1;
                        $rankClass = '';
                        if ($rank === 1) $rankClass = 'top-1';
                        elseif ($rank === 2) $rankClass = 'top-2';
                        elseif ($rank === 3) $rankClass = 'top-3';
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span>
                            </td>
                            <td>
                                <div class="mechanic-name">
                                    <i class="fas fa-user-cog"></i>
                                    <?= htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']) ?>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?= number_format($mechanic['total_jobs']) ?>
                            </td>
                            <td class="amount" style="text-align: right; font-size: 14px;">
                                ₱<?= number_format($mechanic['total_labor'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- All-Time Parts Modal -->
<div class="modal" id="partsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-boxes"></i> All-Time Parts Statistics</h2>
            <button class="modal-close" onclick="closeModal('partsModal')">&times;</button>
        </div>

        <div class="all-time-stats">
            <div class="all-time-card featured">
                <div class="icon"><i class="fas fa-trophy"></i></div>
                <div class="label">Total Parts Sales (All-Time)</div>
                <div class="value">₱<?= number_format($allTimeStats['total_parts'], 2) ?></div>
            </div>

            <div class="all-time-card">
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="label">This Month (<?= date('M Y') ?>)</div>
                <div class="value">₱<?= number_format($monthlyStats['total_parts'], 2) ?></div>
            </div>
        </div>
    </div>
</div>

<script>
// Hamburger sidebar toggle
const hamburgerBtn   = document.getElementById('hamburger-btn');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
hamburgerBtn.addEventListener('click', () => {
    hamburgerBtn.classList.toggle('active');
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
});
sidebarOverlay.addEventListener('click', () => {
    hamburgerBtn.classList.remove('active');
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
});
sidebar.querySelectorAll('nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
    });
});

// Modal functions
function showAllTimeRevenue() {
    document.getElementById('revenueModal').classList.add('show');
}

function showAllTimeBookings() {
    document.getElementById('bookingsModal').classList.add('show');
}

function showAllTimeLabor() {
    document.getElementById('laborModal').classList.add('show');
}

function showAllTimeParts() {
    document.getElementById('partsModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal(this.id);
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(modal => {
            closeModal(modal.id);
        });
    }
});

console.log('✅ Sales page initialized - Shows all completed bookings in current month regardless of schedule date');
</script>

</body>
</html>