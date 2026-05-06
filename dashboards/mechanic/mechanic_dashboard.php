<?php
session_start();

require '../../includes/functions.php';
require '../../includes/db_connect.php';

// Ensure logged in as mechanic
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    header("Location: ../../login.php");
    exit();
}

$mechanic_id = $_SESSION['user_id'];

// Fetch mechanic info with availability status
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'mechanic'");
$stmt->execute([$mechanic_id]);
$mechanic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mechanic) {
    die("Mechanic not found. Please log in again.");
}

$is_available = $mechanic['is_available'] ?? 1;
$mechanic_name = $mechanic['first_name'] . ' ' . $mechanic['last_name'];

// ✅ Stats for mechanic - UPDATED WITH NEW STATUS FLOW
$serviceRequestsStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'pending'");
$serviceRequestsStmt->execute([$mechanic_id]);
$serviceRequests = $serviceRequestsStmt->fetchColumn();

$inProgressStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'in_progress'");
$inProgressStmt->execute([$mechanic_id]);
$inProgressJobs = $inProgressStmt->fetchColumn();

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'completed'");
$completedStmt->execute([$mechanic_id]);
$completedJobs = $completedStmt->fetchColumn();

// Messages count (unread)
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$mechanic_id]);
$messages = $messagesStmt->fetchColumn();

// Greeting
$hour = date('H');
$greeting = ($hour < 12) ? 'Good Morning' : (($hour < 18) ? 'Good Afternoon' : 'Good Evening');

// Recent completed jobs (limit 5)
$recentStmt = $pdo->prepare("
    SELECT b.*, CONCAT(c.first_name, ' ', c.last_name) AS customer_name 
    FROM bookings b 
    LEFT JOIN users c ON b.customer_id = c.id 
    WHERE b.mechanic_id = ? AND b.status = 'completed' 
    ORDER BY b.updated_at DESC LIMIT 5
");
$recentStmt->execute([$mechanic_id]);
$recentJobs = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for time ago
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mechanic Dashboard - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #1a56db;
    --secondary: #1e40af;
    --dark-bg: #f0f4ff;
    --card-bg: #ffffff;
    --border: rgba(26, 86, 219, 0.2);
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --success: #059669;
    --error: #dc2626;
    --warning: #d97706;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

/* HAMBURGER MENU BUTTON */
.hamburger-btn {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.4);
    transition: all 0.3s ease;
}

.hamburger-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.6);
}

.hamburger-btn span {
    display: block;
    width: 25px;
    height: 3px;
    background: #ffffff;
    margin: 5px auto;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.hamburger-btn.active span:nth-child(1) {
    transform: rotate(45deg) translate(8px, 8px);
}

.hamburger-btn.active span:nth-child(2) {
    opacity: 0;
}

.hamburger-btn.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
}

/* SIDEBAR OVERLAY */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(26, 86, 219, 0.1);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* FIXED SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(180deg, #1e3a8a 0%, #1a56db 100%);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    padding: 25px 20px;
    z-index: 1000;
    transition: transform 0.3s ease;
    max-height: 100vh;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.logo {
    font-size: 1.6rem;
    font-weight: 700;
    color: #ffffff;
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
    color: rgba(255, 255, 255, 0.75);
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
    position: relative;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    transform: translateX(5px);
}

.sidebar nav a i {
    width: 20px;
    text-align: center;
}

.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #ff0000;
    color: #ffffff;
    width: 18px;
    height: 18px;
    min-width: 18px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    margin-left: auto;
    line-height: 1;
    box-shadow: 0 0 8px rgba(255, 0, 0, 0.7);
}

.sidebar .footer {
    margin-top: auto;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.5);
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: #fff !important;
    margin-top: auto !important;
    justify-content: center !important;
}

.logout-btn:hover {
    background: linear-gradient(135deg, #ff6666, #d44444) !important;
}

/* MAIN CONTENT */
.main {
    margin-left: 260px;
    padding: 40px;
    width: calc(100% - 260px);
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
}

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header {
    font-size: 28px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 15px;
}

/* AVAILABILITY STATUS STYLES */
.availability-container {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 25px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
}

.availability-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: 25px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.availability-status.available {
    background: rgba(5, 150, 105, 0.12);
    border: 2px solid var(--success);
    color: var(--success);
}

.availability-status.unavailable {
    background: rgba(220, 38, 38, 0.1);
    border: 2px solid var(--error);
    color: var(--error);
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.status-dot.available {
    background: var(--success);
    box-shadow: 0 0 8px var(--success);
}

.status-dot.unavailable {
    background: var(--error);
    box-shadow: 0 0 8px var(--error);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
}

.toggle-btn {
    position: relative;
    width: 60px;
    height: 30px;
    background: var(--error);
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
}

.toggle-btn.active {
    background: var(--success);
}

.toggle-btn::before {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.toggle-btn.active::before {
    left: 33px;
}

.toggle-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(26, 86, 219, 0.3);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 12px 40px rgba(26, 86, 219, 0.2);
}

.stat-title {
    color: var(--text-secondary);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.panel {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
}

.panel:hover {
    border-color: var(--primary);
    box-shadow: 0 12px 40px rgba(26, 86, 219, 0.15);
    transform: translateY(-3px);
}

.panel h3 {
    color: var(--primary);
    font-size: 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.panel ul {
    list-style: none;
    padding-left: 0;
}

.panel li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: 0.3s ease;
}

.panel li:last-child {
    border-bottom: none;
}

.panel li:hover {
    color: var(--primary);
    transform: translateX(5px);
}

.btn-action {
    padding: 14px 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.3s ease;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.2);
    margin-top: 15px;
}

.btn-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, var(--error), #c82333);
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.2);
}

.btn-warning:hover {
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.35);
}

.recent-jobs-panel {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
}

.recent-jobs-panel h3 {
    color: var(--primary);
    font-size: 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.job-item {
    background: rgba(26, 86, 219, 0.04);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 12px;
    border-left: 4px solid var(--success);
    transition: all 0.3s ease;
}

.job-item:hover {
    background: rgba(26, 86, 219, 0.08);
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(5, 150, 105, 0.15);
}

.job-item:last-child {
    margin-bottom: 0;
}

.job-title {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 14px;
    margin-bottom: 8px;
}

.job-info {
    color: var(--text-secondary);
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.job-time {
    color: #94a3b8;
    font-size: 12px;
    margin-top: 8px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
    color: var(--primary);
}

.empty-state p {
    margin-bottom: 16px;
}

/* Leave panel override */
.leave-panel {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.08), rgba(220, 38, 38, 0.04)) !important;
    border-color: rgba(220, 38, 38, 0.25) !important;
}

.leave-panel h3 {
    color: var(--error) !important;
}

.leave-panel p {
    color: var(--text-secondary);
    margin: 0 0 15px 0;
}

@media (max-width: 1024px) {
    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;
        width: calc(100% - 220px);
        padding: 30px 20px;
    }

    .header {
        font-size: 24px;
    }

    .availability-container {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .hamburger-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 80%;
        max-width: 300px;
        height: 100vh;
        transform: translateX(-100%);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        flex-direction: column;
        padding: 25px 20px;
        overflow-y: auto;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar nav {
        flex-direction: column;
        gap: 8px;
    }

    .sidebar nav a {
        padding: 12px 16px;
        font-size: 14px;
    }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 80px 20px 20px 20px;
    }

    .header {
        font-size: 20px;
        margin-top: 20px;
    }

    .header-top {
        flex-direction: column;
        align-items: flex-start;
    }

    .availability-container {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
    }

    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }

    .stat-card {
        padding: 12px 8px;
    }

    .stat-number {
        font-size: 22px;
    }

    .stat-title {
        font-size: 10px;
        letter-spacing: 0;
        margin-bottom: 6px;
    }

    .panel {
        padding: 20px;
    }

    .panel h3 {
        font-size: 16px;
    }

    .btn-action {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
        font-size: 12px;
    }

    .job-info {
        flex-direction: column;
        gap: 5px;
    }

    .job-title {
        font-size: 13px;
    }

    .job-info span,
    .job-time {
        font-size: 12px;
    }

    .recent-jobs-panel {
        padding: 20px;
    }

    .recent-jobs-panel h3 {
        font-size: 16px;
    }

    .job-item {
        padding: 12px;
    }
}
</style>
</head>
<body>

<!-- HAMBURGER BUTTON -->
<button class="hamburger-btn" id="hamburger-btn">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-wrench"></i>
        <span>MotorService</span>
    </div>

    <nav>
        <a href="mechanic_dashboard.php" class="<?= $currentPage == 'mechanic_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="service_request.php" class="<?= $currentPage == 'service_request.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Service Requests
        </a>
        <a href="mechanic_history.php" class="<?= $currentPage == 'mechanic_history.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> History
        </a>
        <a href="message_center.php" class="<?= $currentPage == 'message_center.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="change_password.php" class="<?= $currentPage == 'change_password.php' ? 'active' : '' ?>">
            <i class="fas fa-lock"></i> Change Password
        </a>
        <a href="report_absence.php" class="<?= $currentPage == 'report_absence.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-times"></i> File Leave
        </a>
        <a href="../../logout.php" class="logout-btn" style="margin-top: auto;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="header-top">
        <div class="header">
            <i class="fas fa-wave-square"></i>
            <?= $greeting ?>, <?= htmlspecialchars($mechanic_name) ?>
        </div>
        
        <!-- AVAILABILITY TOGGLE -->
        <div class="availability-container">
            <div class="availability-status <?= $is_available ? 'available' : 'unavailable' ?>" id="status-display">
                <span class="status-dot <?= $is_available ? 'available' : 'unavailable' ?>"></span>
                <span id="status-text"><?= $is_available ? 'Available' : 'Unavailable' ?></span>
            </div>
            
            <div class="toggle-btn <?= $is_available ? 'active' : '' ?>" id="availability-toggle" title="Click to toggle availability"></div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='service_request.php?filter=pending'">
            <div class="stat-title">⏳ Service Request</div>
            <div class="stat-number"><?= $serviceRequests ?></div>
        </div>
        
        <div class="stat-card" onclick="location.href='service_request.php?filter=in_progress'">
            <div class="stat-title">⚙️ In Progress</div>
            <div class="stat-number"><?= $inProgressJobs ?></div>
        </div>

        <div class="stat-card" onclick="location.href='mechanic_history.php'">
            <div class="stat-title">✅ Completed</div>
            <div class="stat-number"><?= $completedJobs ?></div>
        </div>
    </div>

    <!-- RECENT COMPLETED JOBS -->
    <?php if (!empty($recentJobs)): ?>
    <div class="recent-jobs-panel">
        <h3><i class="fas fa-check-circle"></i> ✅ Recent Completed Jobs</h3>
        
        <?php foreach ($recentJobs as $job): ?>
            <div class="job-item">
                <div class="job-title">
                    <i class="fas fa-check"></i> <?= htmlspecialchars($job['service_type']) ?> - <?= htmlspecialchars($job['customer_name']) ?>
                </div>
                <div class="job-info">
                    <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($job['schedule'])) ?></span>
                    <span><i class="fas fa-motorcycle"></i> <?= htmlspecialchars($job['brand'] . ' ' . $job['vehicle_type']) ?></span>
                </div>
                <div class="job-time">
                    <i class="fas fa-clock"></i> <?= timeAgo($job['updated_at']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="recent-jobs-panel">
        <h3><i class="fas fa-check-circle"></i> ✅ Recent Completed Jobs</h3>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No completed jobs yet</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- FILE LEAVE PANEL -->
    <div class="panel leave-panel">
        <h3><i class="fas fa-calendar-times"></i> File Leave</h3>
        <p>
            Unable to work for a while? File your leave here. The system will automatically notify customers and update affected bookings.
        </p>
        <a href="report_absence.php" class="btn-action btn-warning">
            <i class="fas fa-file-medical"></i> File Leave
        </a>
    </div>

    <!-- QUICK TIPS PANEL -->
    <div class="panel">
        <h3><i class="fas fa-lightbulb"></i> Mechanic Tips</h3>
        <ul>
            <li><i class="fas fa-check"></i> Always inspect tools before use to ensure safety.</li>
            <li><i class="fas fa-check"></i> Communicate clearly with customers about service progress.</li>
            <li><i class="fas fa-check"></i> Keep your workspace organized to improve efficiency.</li>
            <li><i class="fas fa-check"></i> Stay updated with the latest motorcycle maintenance techniques.</li>
        </ul>
    </div>
</main>

<script>
// Hamburger menu toggle
const hamburgerBtn = document.getElementById('hamburger-btn');
const sidebar = document.getElementById('sidebar');
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

// Close sidebar when clicking a link on mobile
sidebar.querySelectorAll('nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
    });
});

// ✅ AVAILABILITY TOGGLE FUNCTIONALITY
const toggleBtn = document.getElementById('availability-toggle');
const statusDisplay = document.getElementById('status-display');
const statusText = document.getElementById('status-text');
let isAvailable = <?= $is_available ? 'true' : 'false' ?>;

toggleBtn.addEventListener('click', function() {
    toggleBtn.style.pointerEvents = 'none';
    
    fetch('toggle_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        console.log('✅ Toggle response:', data);
        
        if (data.success) {
            isAvailable = data.is_available == 1;
            
            if (isAvailable) {
                toggleBtn.classList.add('active');
            } else {
                toggleBtn.classList.remove('active');
            }
            
            statusDisplay.className = 'availability-status ' + (isAvailable ? 'available' : 'unavailable');
            statusText.textContent = data.status_text;
            
            const statusDot = statusDisplay.querySelector('.status-dot');
            statusDot.className = 'status-dot ' + (isAvailable ? 'available' : 'unavailable');
            
            showNotification(data.message, 'success');
            
            console.log('✅ Status updated to:', data.status_text);
        } else {
            showNotification(data.message || 'Failed to update status', 'error');
        }
        
        toggleBtn.style.pointerEvents = 'auto';
    })
    .catch(err => {
        console.error('❌ Toggle error:', err);
        showNotification('Network error. Please try again.', 'error');
        toggleBtn.style.pointerEvents = 'auto';
    });
});

// ✅ NOTIFICATION FUNCTION
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${type === 'success' ? 'linear-gradient(135deg, #059669, #047857)' : 'linear-gradient(135deg, #dc2626, #b91c1c)'};
        color: #fff;
        border-radius: 12px;
        font-weight: 600;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        font-size: 14px;
        font-family: 'Outfit', sans-serif;
    `;
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;
document.head.appendChild(style);

console.log('✅ Mechanic Dashboard initialized');
</script>

</body>
</html>