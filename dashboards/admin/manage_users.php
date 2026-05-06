<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';
require '../../includes/activity_logger.php';

// Get admin info for logging
$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminId = $_SESSION['user_id'];

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $user_id = (int) $_POST['user_id'];

        // Get user info for logging
        $userStmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = $targetUser['first_name'] . ' ' . $targetUser['last_name'];
        $userRole = $targetUser['role'];

        if ($action === 'toggle_disable') {
            $stmt = $pdo->prepare("SELECT is_disabled FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current = $stmt->fetchColumn();
            $new_status = ($current == 1) ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_disabled = ? WHERE id = ?")->execute([$new_status, $user_id]);
            
            if ($new_status == 1) {
                $message = "User disabled successfully! They will be logged out on next login attempt.";
                $messageType = "success";
                logActivity($pdo, $adminId, $adminName, 'user_disable', "Disabled user: $userName ($userRole)");
                
                // Optional: Force logout by deleting sessions (if you have a sessions table)
                // For now, just log that they're disabled
            } else {
                $message = "User enabled successfully!";
                $messageType = "success";
                logActivity($pdo, $adminId, $adminName, 'user_enable', "Enabled user: $userName ($userRole)");
            }
        } elseif ($action === 'delete') {
            // Start transaction to ensure atomicity
            $pdo->beginTransaction();
            try {
                // Delete related records first
                $pdo->prepare("DELETE FROM customer_addresses WHERE customer_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
                $pdo->prepare("DELETE FROM bookings WHERE customer_id = ? OR mechanic_id = ?")->execute([$user_id, $user_id]);
                $pdo->prepare("DELETE FROM customer_notifications WHERE customer_id = ?")->execute([$user_id]);

                // Now delete the user
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                
                $pdo->commit();
                $message = "User deleted successfully! All related data has been removed.";
                $messageType = "success";
                logActivity($pdo, $adminId, $adminName, 'user_delete', "Deleted user: $userName ($userRole)");
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Error deleting user: " . $e->getMessage();
                $messageType = "error";
            }
        } elseif ($action === 'reset_password') {
            $newPassword = bin2hex(random_bytes(6));
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $user_id]);
            $message = "Password reset. New password: <strong>" . $newPassword . "</strong> (Share securely with user)";
            $messageType = "success";
            logActivity($pdo, $adminId, $adminName, 'user_password_reset', "Reset password for user: $userName");
        }
    }
}

// Filter logic
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where = "role != 'admin'";
$params = [];

if ($filter === 'customers') {
    $where .= " AND role = 'customer'";
} elseif ($filter === 'mechanics') {
    $where .= " AND role = 'mechanic'";
} elseif ($filter === 'active') {
    $where .= " AND (is_disabled = 0 OR is_disabled IS NULL)";
} elseif ($filter === 'disabled') {
    $where .= " AND is_disabled = 1";
}

if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalMechanics = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mechanic' AND (is_disabled = 0 OR is_disabled IS NULL)")->fetchColumn();
$disabledUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_disabled = 1")->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Users - MotorService Admin</title>
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
    --warning: #ffa502;
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

a { color: inherit; text-decoration: none; }

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

/* FIXED SIDEBAR */
.sidebar {
    position: fixed; top: 0; left: 0;
    width: 260px; height: 100vh; overflow-y: auto;
    background: linear-gradient(180deg, #0f1419 0%, #1a1f3a 100%);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    padding: 25px 20px; z-index: 1000;
    transition: transform 0.3s ease;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
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

.logo i {
    font-size: 24px;
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
    font-size: 16px;
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

.main::-webkit-scrollbar-track {
    background: transparent;
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

h1 i {
    font-size: 36px;
}

.stats-mini {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-mini {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
}

.stat-mini .icon {
    font-size: 24px;
    color: var(--primary);
    margin-bottom: 8px;
}

.stat-mini .number {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 3px;
}

.stat-mini .label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.controls {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 200px;
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.filter-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-filter {
    padding: 10px 16px;
    background: rgba(255, 140, 0, 0.1);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 13px;
}

.btn-filter:hover,
.btn-filter.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
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
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
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

tbody tr.disabled {
    opacity: 0.7;
}

tbody tr.disabled td {
    color: var(--text-secondary);
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
}

.role-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-customer {
    background: rgba(0, 208, 132, 0.2);
    color: var(--success);
}

.role-mechanic {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.status-active {
    background: rgba(0, 208, 132, 0.2);
    color: var(--success);
}

.status-disabled {
    background: rgba(255, 71, 87, 0.2);
    color: var(--error);
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-disable {
    background: rgba(255, 140, 0, 0.2);
    color: var(--warning);
}

.btn-disable:hover {
    background: rgba(255, 140, 0, 0.4);
}

.btn-delete {
    background: rgba(255, 71, 87, 0.2);
    color: var(--error);
}

.btn-delete:hover {
    background: rgba(255, 71, 87, 0.4);
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

.empty-state p {
    margin-bottom: 16px;
}

.message {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
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
    font-size: 16px;
}

.message strong {
    color: #fff;
    font-weight: 700;
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; padding: 20px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    h1 { font-size: 24px; }
    .controls { flex-direction: column; }
    .search-box { min-width: 100%; }
}

@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }

    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }
    h1 { font-size: 20px; margin-top: 10px; }

    .stats-mini { grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
    .controls { flex-direction: column; gap: 10px; }
    .filter-buttons { gap: 5px; }
    .btn-filter { padding: 8px 12px; font-size: 12px; }

    .table-container { overflow-x: auto; }
    table { min-width: 600px; }
    th, td { padding: 10px; font-size: 12px; }

    .actions { flex-direction: column; gap: 5px; }
    .btn-action { padding: 5px 8px; font-size: 11px; }
}

@media (max-width: 480px) {
    .main { padding: 75px 15px 15px 15px; }
    .stats-mini { grid-template-columns: 1fr; }
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
    <h1><i class="fas fa-users"></i> Manage Users</h1>

    <?php if (isset($message)): ?>
        <div class="message <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-mini">
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?= number_format($totalUsers) ?></div>
            <div class="label">Customers</div>
        </div>
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-wrench"></i></div>
            <div class="number"><?= number_format($totalMechanics) ?></div>
            <div class="label">Mechanics</div>
        </div>
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-ban"></i></div>
            <div class="number"><?= number_format($disabledUsers) ?></div>
            <div class="label">Disabled</div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name, phone, or email..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-buttons">
            <button class="btn-filter <?= $filter === 'all' ? 'active' : '' ?>" onclick="filterUsers('all')">All Users</button>
            <button class="btn-filter <?= $filter === 'customers' ? 'active' : '' ?>" onclick="filterUsers('customers')">Customers</button>
            <button class="btn-filter <?= $filter === 'mechanics' ? 'active' : '' ?>" onclick="filterUsers('mechanics')">Mechanics</button>
            <button class="btn-filter <?= $filter === 'active' ? 'active' : '' ?>" onclick="filterUsers('active')">Active</button>
            <button class="btn-filter <?= $filter === 'disabled' ? 'active' : '' ?>" onclick="filterUsers('disabled')">Disabled</button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <?php if (!empty($users)): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-user"></i> Name</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-tag"></i> Role</th>
                        <th><i class="fas fa-check"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Joined</th>
                        <th style="text-align: right;"><i class="fas fa-cogs"></i> Manage </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr <?= $user['is_disabled'] == 1 ? 'class="disabled"' : '' ?>>
                            <td class="user-name">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                <?php if ($user['is_disabled'] == 1): ?>
                                    <i class="fas fa-lock" style="color: var(--error); margin-left: 8px;" title="Account Disabled"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="role-badge role-<?= $user['role'] === 'customer' ? 'customer' : 'mechanic' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= ($user['is_disabled'] == 1) ? 'disabled' : 'active' ?>">
                                    <?= ($user['is_disabled'] == 1) ? 'Disabled' : 'Active' ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td style="text-align: right;">
                                <div class="actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_disable">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn-action btn-disable" onclick="return confirm('<?= $user['is_disabled'] == 1 ? 'Enable' : 'Disable' ?> this user?')">
                                            <i class="fas fa-<?= $user['is_disabled'] == 1 ? 'check' : 'lock' ?>"></i>
                                            <?= $user['is_disabled'] == 1 ? 'Enable' : 'Disable' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this user permanently? This cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No users found</p>
                <small>Try adjusting your search filters</small>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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

function filterUsers(type) {
    const search = document.getElementById('searchInput').value;
    const url = new URL(window.location);
    url.searchParams.set('filter', type);
    if (search) url.searchParams.set('search', search);
    window.location = url.toString();
}

document.getElementById('searchInput')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        filterUsers('<?= $filter ?>');
    }
});
</script>

</body>
</html>