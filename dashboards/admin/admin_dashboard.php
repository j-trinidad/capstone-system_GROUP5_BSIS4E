<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

// --- Real-time stats from DB ---

// Total users (ONLY customers)
$totalUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalUsersStmt->execute();
$totalUsers = (int) $totalUsersStmt->fetchColumn();

// Active mechanics (role = mechanic and not disabled)
$activeMechStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'mechanic' AND (is_disabled = 0 OR is_disabled IS NULL)");
$activeMechStmt->execute();
$activeMechanics = (int) $activeMechStmt->fetchColumn();

// Total bookings completed
$totalBookingsStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'completed'");
$totalBookingsStmt->execute();
$totalBookings = (int) $totalBookingsStmt->fetchColumn();

// Total revenue (sum of all completed bookings)
$revenueStmt = $pdo->prepare("
    SELECT COALESCE(SUM(labor_fee + service_fee + parts_total + 
        CASE WHEN service_location = 'home' THEN 150 ELSE 0 END), 0) as total
    FROM bookings WHERE status = 'completed'
");
$revenueStmt->execute();
$totalRevenue = (float) $revenueStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent registrations
$recentStmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, created_at 
    FROM users 
    WHERE role = 'customer' 
    ORDER BY created_at DESC
    LIMIT 6
");
$recentStmt->execute();
$recentRegistrations = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// For active link detection
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard - MotorService</title>
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

.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

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

.logo i { font-size: 24px; }

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

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: var(--text-primary) !important;
    margin-top: auto !important;
    justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }

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

.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-track { background: transparent; }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

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

h1 i { font-size: 36px; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(229, 46, 113, 0.1));
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(255, 140, 0, 0.2);
}

.stat-card .icon { font-size: 32px; color: var(--primary); margin-bottom: 15px; }
.stat-card .title { color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 10px; }
.stat-card .number { font-size: 32px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px; }
.stat-card .subtitle { font-size: 12px; color: var(--text-secondary); }

.grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
}

.panel {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.panel-header h3 {
    font-size: 18px;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.panel-header h3 i { color: var(--primary); font-size: 20px; }

.show-all-link {
    padding: 8px 16px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.show-all-link:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 140, 0, 0.3); }

.list { display: flex; flex-direction: column; gap: 15px; }

.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: rgba(255, 140, 0, 0.05);
    border: 1px solid var(--border);
    border-radius: 10px;
    transition: all 0.3s ease;
}

.list-item:hover { background: rgba(255, 140, 0, 0.1); border-color: var(--primary); }

.list-item-left { display: flex; align-items: center; gap: 15px; }

.avatar {
    width: 50px; height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #1a1f3a; font-weight: 700; font-size: 18px;
    flex-shrink: 0;
}

.list-item-info h4 { margin: 0 0 5px 0; font-size: 14px; color: var(--text-primary); font-weight: 600; }
.list-item-info p { margin: 0; font-size: 12px; color: var(--text-secondary); }
.list-item-right { text-align: right; flex-shrink: 0; }
.list-item-time { font-size: 12px; color: var(--text-secondary); }

.quick-actions { display: flex; flex-direction: column; gap: 12px; }

.btn {
    padding: 12px 20px;
    border: none; border-radius: 10px;
    cursor: pointer; font-weight: 600;
    transition: all 0.3s ease; font-size: 14px;
    display: flex; align-items: center; justify-content: center;
    gap: 10px; text-transform: uppercase; letter-spacing: 0.5px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4); }

.btn-secondary {
    background: rgba(255, 140, 0, 0.1);
    color: var(--primary);
    border: 2px solid var(--primary);
}
.btn-secondary:hover { background: rgba(255, 140, 0, 0.2); transform: translateY(-2px); }

.btn i { font-size: 16px; }

.empty-state { text-align: center; padding: 30px 20px; color: var(--text-secondary); }
.empty-state i { font-size: 40px; margin-bottom: 15px; opacity: 0.5; }
.empty-state p { margin: 10px 0; font-size: 14px; }

/* ── RESPONSIVE ── */
@media (max-width: 1024px) {
    .sidebar { width: 220px; padding: 20px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    h1 { font-size: 24px; }
    .grid-2 { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    /* Show hamburger, hide sidebar by default */
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }

    /* Main area fills full screen */
    .main {
        margin-left: 0;
        width: 100%;
        padding: 80px 20px 20px 20px;
    }

    h1 { font-size: 20px; margin-top: 10px; }

    /* Stats: 2-column grid on mobile */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 25px;
    }

    .stat-card { padding: 16px; }
    .stat-card .icon { font-size: 24px; margin-bottom: 10px; }
    .stat-card .title { font-size: 11px; letter-spacing: 0; margin-bottom: 6px; }
    .stat-card .number { font-size: 22px; }
    .stat-card .subtitle { font-size: 11px; }

    .grid-2 { grid-template-columns: 1fr; }

    .panel { padding: 20px; }
    .panel-header { flex-wrap: wrap; gap: 10px; }
    .panel-header h3 { font-size: 15px; }

    /* List items: stack on very small screens */
    .list-item { flex-wrap: wrap; gap: 8px; }
    .list-item-left { gap: 10px; }
    .avatar { width: 40px; height: 40px; font-size: 15px; border-radius: 8px; }
    .list-item-info h4 { font-size: 13px; }
    .list-item-info p { font-size: 11px; word-break: break-all; }
    .list-item-time { font-size: 11px; }

    .btn { padding: 12px 15px; font-size: 13px; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .main { padding: 75px 15px 15px 15px; }
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
    <h1><i class="fas fa-gauge-high"></i> Admin Dashboard</h1>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="title">Total Customers</div>
            <div class="number"><?= number_format($totalUsers) ?></div>
            <div class="subtitle">Registered users</div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="fas fa-wrench"></i></div>
            <div class="title">Active Mechanics</div>
            <div class="number"><?= number_format($activeMechanics) ?></div>
            <div class="subtitle">Available for service</div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="title">Completed Bookings</div>
            <div class="number"><?= number_format($totalBookings) ?></div>
            <div class="subtitle">Total services</div>
        </div>

        <div class="stat-card">
            <div class="icon"><i class="fas fa-peso-sign"></i></div>
            <div class="title">Total Revenue</div>
            <div class="number">₱<?= number_format($totalRevenue, 0) ?></div>
            <div class="subtitle">From completed bookings</div>
        </div>
    </div>

    <!-- PANELS -->
    <div class="grid-2">
        <section class="panel">
            <div class="panel-header">
                <h3><i class="fas fa-users"></i> Recent Registrations</h3>
                <a href="manage_users.php?filter=customers" class="show-all-link">View All</a>
            </div>

            <div class="list">
                <?php if (!empty($recentRegistrations)): ?>
                    <?php foreach ($recentRegistrations as $user): 
                        $fn = htmlspecialchars($user['first_name'] ?? '');
                        $ln = htmlspecialchars($user['last_name'] ?? '');
                        $initials = strtoupper(($user['first_name'][0] ?? '') . ($user['last_name'][0] ?? ''));
                        if ($initials == "") $initials = "U";

                        $created = strtotime($user['created_at']);
                        $diff = time() - $created;

                        if ($diff < 60) $ago = 'just now';
                        elseif ($diff < 3600) $ago = floor($diff/60).'m ago';
                        elseif ($diff < 86400) $ago = floor($diff/3600).'h ago';
                        else $ago = floor($diff/86400).'d ago';
                    ?>
                        <div class="list-item">
                            <div class="list-item-left">
                                <div class="avatar"><?= $initials ?></div>
                                <div class="list-item-info">
                                    <h4><?= $fn . " " . $ln ?></h4>
                                    <p><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                            </div>
                            <div class="list-item-right">
                                <div class="list-item-time"><?= $ago ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No recent registrations</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h3><i class="fas fa-sliders-h"></i> Manage Services & Users</h3>
            </div>

            <div class="quick-actions">
                <a href="mechanic_tracker.php" class="btn btn-primary">
                    <i class="fas fa-users-cog"></i> Mechanic Status Tracker
                </a>
                <a href="add_mechanic.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add Mechanic Account
                </a>
                <a href="manage_users.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="manage_services.php" class="btn btn-secondary">
                    <i class="fas fa-tools"></i> Manage Services
                </a>
                <a href="manage_parts.php" class="btn btn-secondary">
                    <i class="fas fa-boxes"></i> Manage Parts
                </a>
            </div>
        </section>
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