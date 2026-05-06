<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

// Filter logic
$filterBy = $_GET['filter_by'] ?? 'all';
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Build WHERE clause
$where = "WHERE 1=1";
$params = [];

if ($filterBy !== 'all') {
    $where .= " AND action_type = ?";
    $params[] = $filterBy;
}

if ($search) {
    $where .= " AND (details LIKE ? OR admin_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($startDate && $endDate) {
    $where .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// Get activity logs
$stmt = $pdo->prepare("
    SELECT * FROM activity_logs 
    $where
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$totalLogs = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$todayLogs = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$weekLogs = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Log - MotorService Admin</title>
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
    --info: #3b82f6;
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

.filters {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
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

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(255, 140, 0, 0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(255, 140, 0, 0.1);
}

.timeline {
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.timeline-item {
    position: relative;
    padding: 20px 20px 20px 60px;
    margin-bottom: 15px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.timeline-item:hover {
    background: rgba(255, 140, 0, 0.05);
    border-color: var(--primary);
    transform: translateX(5px);
}

.timeline-icon {
    position: absolute;
    left: 10px;
    top: 20px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #1a1f3a;
    z-index: 1;
}

.icon-user { background: var(--info); }
.icon-service { background: var(--success); }
.icon-parts { background: var(--warning); }
.icon-mechanic { background: var(--primary); }
.icon-delete { background: var(--error); }
.icon-update { background: #8b5cf6; }

.timeline-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.timeline-details {
    flex: 1;
}

.timeline-action {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    font-size: 14px;
}

.timeline-description {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 8px;
}

.timeline-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: var(--text-secondary);
}

.timeline-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.timeline-time {
    text-align: right;
    color: var(--text-secondary);
    font-size: 12px;
}

.timeline-date {
    font-weight: 600;
    margin-bottom: 3px;
}

.action-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}

.badge-create { background: rgba(0, 208, 132, 0.2); color: var(--success); }
.badge-update { background: rgba(59, 130, 246, 0.2); color: var(--info); }
.badge-delete { background: rgba(255, 71, 87, 0.2); color: var(--error); }
.badge-disable { background: rgba(255, 165, 2, 0.2); color: var(--warning); }
.badge-enable { background: rgba(0, 208, 132, 0.2); color: var(--success); }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: var(--text-primary);
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

    h1 {
        font-size: 24px;
    }
}

@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 80px 20px 20px 20px;
    }

    h1 { font-size: 20px; margin-top: 10px; }

    .stats-mini { grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .stat-mini { padding: 12px; }
    .stat-mini .number { font-size: 20px; }
    .stat-mini .label { font-size: 10px; }

    .filter-row { grid-template-columns: 1fr; }
    .filter-actions { flex-wrap: wrap; }
    .filter-actions .btn { flex: 1; justify-content: center; }

    .timeline::before { left: 15px; }
    .timeline-item { padding: 15px 15px 15px 50px; }
    .timeline-icon { left: 5px; width: 20px; height: 20px; font-size: 10px; }
    .timeline-content { flex-direction: column; }
    .timeline-time { text-align: left; }
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
    <h1><i class="fas fa-clipboard-list"></i> Activity Log</h1>

    <!-- Stats -->
    <div class="stats-mini">
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-list"></i></div>
            <div class="number"><?= number_format($totalLogs) ?></div>
            <div class="label">Total Activities</div>
        </div>
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
            <div class="number"><?= number_format($todayLogs) ?></div>
            <div class="label">Today</div>
        </div>
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-calendar-week"></i></div>
            <div class="number"><?= number_format($weekLogs) ?></div>
            <div class="label">This Week</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <form method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Filter By Action</label>
                    <select name="filter_by">
                        <option value="all" <?= $filterBy === 'all' ? 'selected' : '' ?>>All Actions</option>
                        <option value="user_create" <?= $filterBy === 'user_create' ? 'selected' : '' ?>>User Created</option>
                        <option value="user_disable" <?= $filterBy === 'user_disable' ? 'selected' : '' ?>>User Disabled</option>
                        <option value="user_enable" <?= $filterBy === 'user_enable' ? 'selected' : '' ?>>User Enabled</option>
                        <option value="user_delete" <?= $filterBy === 'user_delete' ? 'selected' : '' ?>>User Deleted</option>
                        <option value="service_create" <?= $filterBy === 'service_create' ? 'selected' : '' ?>>Service Created</option>
                        <option value="service_update" <?= $filterBy === 'service_update' ? 'selected' : '' ?>>Service Updated</option>
                        <option value="service_delete" <?= $filterBy === 'service_delete' ? 'selected' : '' ?>>Service Deleted</option>
                        <option value="parts_create" <?= $filterBy === 'parts_create' ? 'selected' : '' ?>>Parts Created</option>
                        <option value="parts_update" <?= $filterBy === 'parts_update' ? 'selected' : '' ?>>Parts Updated</option>
                        <option value="parts_delete" <?= $filterBy === 'parts_delete' ? 'selected' : '' ?>>Parts Deleted</option>
                        <option value="mechanic_create" <?= $filterBy === 'mechanic_create' ? 'selected' : '' ?>>Mechanic Added</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>">
                </div>

                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>">
                </div>

                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search details..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="activity_log.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Timeline -->
    <div class="timeline">
        <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): 
                // Determine icon class
                $iconClass = 'icon-update';
                if (strpos($log['action_type'], 'user') !== false) $iconClass = 'icon-user';
                elseif (strpos($log['action_type'], 'service') !== false) $iconClass = 'icon-service';
                elseif (strpos($log['action_type'], 'parts') !== false) $iconClass = 'icon-parts';
                elseif (strpos($log['action_type'], 'mechanic') !== false) $iconClass = 'icon-mechanic';
                elseif (strpos($log['action_type'], 'delete') !== false) $iconClass = 'icon-delete';

                // Determine badge class
                $badgeClass = 'badge-update';
                if (strpos($log['action_type'], 'create') !== false) $badgeClass = 'badge-create';
                elseif (strpos($log['action_type'], 'delete') !== false) $badgeClass = 'badge-delete';
                elseif (strpos($log['action_type'], 'disable') !== false) $badgeClass = 'badge-disable';
                elseif (strpos($log['action_type'], 'enable') !== false) $badgeClass = 'badge-enable';

                // Format action name
                $actionName = ucwords(str_replace('_', ' ', $log['action_type']));
            ?>
                <div class="timeline-item">
                    <div class="timeline-icon <?= $iconClass ?>">
                        <i class="fas fa-<?= 
                            strpos($log['action_type'], 'user') !== false ? 'user' : 
                            (strpos($log['action_type'], 'service') !== false ? 'tools' : 
                            (strpos($log['action_type'], 'parts') !== false ? 'boxes' : 
                            (strpos($log['action_type'], 'mechanic') !== false ? 'wrench' : 'edit')))
                        ?>"></i>
                    </div>

                    <div class="timeline-content">
                        <div class="timeline-details">
                            <div class="timeline-action"><?= htmlspecialchars($actionName) ?></div>
                            <div class="timeline-description"><?= htmlspecialchars($log['details']) ?></div>
                            <div class="timeline-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($log['admin_name']) ?></span>
                                <?php if ($log['ip_address']): ?>
                                    <span><i class="fas fa-network-wired"></i> <?= htmlspecialchars($log['ip_address']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="action-badge <?= $badgeClass ?>"><?= htmlspecialchars($actionName) ?></span>
                        </div>

                        <div class="timeline-time">
                            <div class="timeline-date"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                            <div><?= date('h:i A', strtotime($log['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No Activity Found</h3>
                <p>No activities match your current filters</p>
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
</script>

</body>
</html>