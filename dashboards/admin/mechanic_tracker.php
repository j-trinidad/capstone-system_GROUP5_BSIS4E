<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

// Get all mechanics with their current status
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.is_disabled,
        u.is_available,
        COUNT(DISTINCT CASE WHEN b.status IN ('pending', 'preparing', 'in_progress') THEN b.id END) as active_jobs,
        (SELECT b2.id FROM bookings b2 
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as current_job_id,
        (SELECT b2.service_type FROM bookings b2 
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as current_service,
        (SELECT b2.status FROM bookings b2 
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as current_status,
        (SELECT b2.started_at FROM bookings b2 
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as started_at,
        (SELECT b2.schedule FROM bookings b2 
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as schedule,
        (SELECT c2.first_name FROM bookings b2 
         LEFT JOIN users c2 ON b2.customer_id = c2.id
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as customer_fname,
        (SELECT c2.last_name FROM bookings b2 
         LEFT JOIN users c2 ON b2.customer_id = c2.id
         WHERE b2.mechanic_id = u.id 
         AND b2.status IN ('preparing', 'in_progress')
         ORDER BY 
            CASE b2.status 
                WHEN 'in_progress' THEN 1
                WHEN 'preparing' THEN 2
            END,
            b2.started_at DESC,
            b2.schedule ASC
         LIMIT 1) as customer_lname
    FROM users u
    LEFT JOIN bookings b ON u.id = b.mechanic_id
    WHERE u.role = 'mechanic'
    GROUP BY u.id
    ORDER BY u.first_name ASC
");
$stmt->execute();
$mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Service type labels
$serviceTypeLabels = [
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mechanic Work Status - MotorService</title>
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

a {
    color: inherit;
    text-decoration: none;
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

/* SIDEBAR */
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

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

h1 {
    font-size: 32px;
    font-weight: 700;
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

.refresh-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.refresh-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
}

/* STATUS TABLE */
.status-table {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.table-header {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    padding: 20px 30px;
    display: grid;
    grid-template-columns: 2.5fr 1.5fr 1.5fr 3fr;
    gap: 20px;
    font-weight: 700;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--primary);
}

.mechanic-row {
    padding: 25px 30px;
    display: grid;
    grid-template-columns: 2.5fr 1.5fr 1.5fr 3fr;
    gap: 20px;
    align-items: center;
    border-top: 1px solid var(--border);
    transition: all 0.3s ease;
}

.mechanic-row:hover {
    background: rgba(255, 140, 0, 0.05);
}

.mechanic-row.offline-row {
    opacity: 0.6;
}

.mechanic-name {
    display: flex;
    align-items: center;
    gap: 15px;
}

.mechanic-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1a1f3a;
    font-weight: 700;
    font-size: 20px;
    flex-shrink: 0;
}

.name-info h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: var(--text-primary);
}

.name-info p {
    margin: 0;
    font-size: 12px;
    color: var(--text-secondary);
}

/* WORK STATUS */
.work-status {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    flex-shrink: 0;
    animation: pulse 2s infinite;
}

.status-dot.preparing {
    background: var(--info);
    box-shadow: 0 0 15px var(--info);
}

.status-dot.working {
    background: var(--success);
    box-shadow: 0 0 15px var(--success);
}

.status-dot.idle {
    background: var(--text-secondary);
    box-shadow: 0 0 10px var(--text-secondary);
    opacity: 0.3;
}

.status-dot.offline {
    background: var(--error);
    box-shadow: 0 0 10px var(--error);
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.status-label {
    font-weight: 700;
    font-size: 16px;
}

.status-label.preparing {
    color: var(--info);
}

.status-label.working {
    color: var(--success);
}

.status-label.idle {
    color: var(--text-secondary);
}

.status-label.offline {
    color: var(--error);
}

/* ONLINE/OFFLINE STATUS */
.online-status {
    display: flex;
    align-items: center;
    gap: 10px;
}

.online-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 25px;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.online-badge.online {
    background: rgba(0, 208, 132, 0.2);
    color: var(--success);
    border: 2px solid var(--success);
}

.online-badge.offline {
    background: rgba(255, 71, 87, 0.2);
    color: var(--error);
    border: 2px solid var(--error);
}

.online-badge .badge-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.online-badge.online .badge-dot {
    background: var(--success);
    box-shadow: 0 0 10px var(--success);
}

.online-badge.offline .badge-dot {
    background: var(--error);
    box-shadow: 0 0 10px var(--error);
}

/* JOB DETAILS */
.job-details {
    color: var(--text-secondary);
    font-size: 13px;
}

.job-details.working {
    background: rgba(0, 208, 132, 0.1);
    border: 1px solid rgba(0, 208, 132, 0.3);
    border-radius: 10px;
    padding: 15px;
}

.job-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.job-info-row:last-child {
    margin-bottom: 0;
}

.job-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.job-value {
    color: var(--text-primary);
    font-weight: 600;
}

.work-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 8px;
}

.work-badge.preparing {
    background: rgba(59, 130, 246, 0.2);
    color: var(--info);
    border: 1px solid var(--info);
}

.work-badge.in_progress {
    background: rgba(0, 208, 132, 0.2);
    color: var(--success);
    border: 1px solid var(--success);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 60px;
    margin-bottom: 20px;
    opacity: 0.3;
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; padding: 20px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    h1 { font-size: 24px; }
    .table-header, .mechanic-row { grid-template-columns: 2fr 1fr 1fr 2fr; }
}

@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }

    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }

    h1 { font-size: 20px; margin-top: 10px; }
    .header { margin-bottom: 20px; }

    .table-header { display: none; }
    .mechanic-row { grid-template-columns: 1fr; gap: 15px; padding: 20px; }
    .work-status { justify-content: flex-start; }
    .online-status { justify-content: flex-start; }
}

@media (max-width: 480px) {
    .main { padding: 75px 15px 15px 15px; }
    .mechanic-avatar { width: 40px; height: 40px; font-size: 16px; }
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
    <div class="header">
        <h1><i class="fas fa-hard-hat"></i> Mechanic Work Status</h1>
        <button class="refresh-btn" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <div class="status-table">
        <div class="table-header">
            <div>MECHANIC</div>
            <div>STATUS</div>
            <div>WORK STATUS</div>
            <div>CURRENT JOB</div>
        </div>

        <?php if (!empty($mechanics)): ?>
            <?php foreach ($mechanics as $mechanic): 
                $initials = strtoupper(
                    (isset($mechanic['first_name'][0]) ? $mechanic['first_name'][0] : '') . 
                    (isset($mechanic['last_name'][0]) ? $mechanic['last_name'][0] : '')
                );
                if ($initials == "") $initials = "M";
                
                // Determine work status
                $isDisabled = (bool)$mechanic['is_disabled'];
                $isAvailable = (bool)($mechanic['is_available'] ?? 1);
                $currentStatus = $mechanic['current_status'];
                
                $isWorking = false;
                $workStatusLabel = 'No Work';
                $workStatusClass = 'idle';
                
                $onlineStatus = 'online';
                $onlineLabel = 'ONLINE';
                
                if ($isDisabled || !$isAvailable) {
                    $workStatusLabel = 'Offline';
                    $workStatusClass = 'offline';
                    $onlineStatus = 'offline';
                    $onlineLabel = 'OFFLINE';
                } elseif ($currentStatus == 'preparing') {
                    $isWorking = true;
                    $workStatusLabel = 'Preparing';
                    $workStatusClass = 'preparing';
                } elseif ($currentStatus == 'in_progress') {
                    $isWorking = true;
                    $workStatusLabel = 'Working';
                    $workStatusClass = 'working';
                }
            ?>
                <div class="mechanic-row <?= $onlineStatus == 'offline' ? 'offline-row' : '' ?>">
                    <!-- Mechanic Name -->
                    <div class="mechanic-name">
                        <div class="mechanic-avatar"><?= $initials ?></div>
                        <div class="name-info">
                            <h3><?= htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']) ?></h3>
                            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($mechanic['email']) ?></p>
                        </div>
                    </div>

                    <!-- Online/Offline Status -->
                    <div class="online-status">
                        <div class="online-badge <?= $onlineStatus ?>">
                            <span class="badge-dot"></span>
                            <?= $onlineLabel ?>
                        </div>
                    </div>

                    <!-- Work Status -->
                    <div class="work-status">
                        <div class="status-dot <?= $workStatusClass ?>"></div>
                        <div class="status-label <?= $workStatusClass ?>">
                            <?= $workStatusLabel ?>
                        </div>
                    </div>

                    <!-- Current Job Details -->
                    <div class="job-details <?= $isWorking ? 'working' : '' ?>">
                        <?php if ($isWorking): 
                            $serviceLabel = $serviceTypeLabels[$mechanic['current_service']] ?? ucfirst(str_replace('_', ' ', $mechanic['current_service']));
                            $customerName = htmlspecialchars($mechanic['customer_fname'] . ' ' . $mechanic['customer_lname']);
                        ?>
                            <div class="job-info-row">
                                <span class="job-label">Booking ID:</span>
                                <span class="job-value">#<?= $mechanic['current_job_id'] ?></span>
                            </div>
                            <div class="job-info-row">
                                <span class="job-label">Service:</span>
                                <span class="job-value"><?= $serviceLabel ?></span>
                            </div>
                            <div class="job-info-row">
                                <span class="job-label">Customer:</span>
                                <span class="job-value"><?= $customerName ?></span>
                            </div>
                            <?php if ($currentStatus == 'in_progress' && $mechanic['started_at']): ?>
                            <div class="job-info-row">
                                <span class="job-label">Started:</span>
                                <span class="job-value"><?= date('M d, h:i A', strtotime($mechanic['started_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <span class="work-badge <?= str_replace('_', '_', $currentStatus) ?>">
                                <?= $currentStatus == 'preparing' ? '🔧 PREPARING' : '⚙️ WORKING NOW' ?>
                            </span>
                            <?php if ($mechanic['active_jobs'] > 1): ?>
                                <div style="margin-top: 8px; font-size: 12px; color: var(--warning);">
                                    <i class="fas fa-clock"></i> +<?= $mechanic['active_jobs'] - 1 ?> more jobs waiting
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($isDisabled || !$isAvailable): ?>
                                <em>Not available</em>
                            <?php else: ?>
                                <em>No current job</em>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users-cog"></i>
                <h3>No Mechanics Found</h3>
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