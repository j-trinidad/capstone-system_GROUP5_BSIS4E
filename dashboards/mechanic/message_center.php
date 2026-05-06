<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    header("Location: ../../login.php");
    exit();
}

require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];

// Get unread message counts
$customerMsgStmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE receiver_id = ? AND is_read = 0 
    AND sender_id IN (SELECT id FROM users WHERE role = 'customer')
");
$customerMsgStmt->execute([$mechanic_id]);
$unreadCustomerMessages = $customerMsgStmt->fetchColumn();

$adminMsgStmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE receiver_id = ? AND is_read = 0 
    AND sender_id IN (SELECT id FROM users WHERE role = 'admin')
");
$adminMsgStmt->execute([$mechanic_id]);
$unreadAdminMessages = $adminMsgStmt->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Message Center - MotorService</title>
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
    --info: #0284c7;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
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
    -webkit-overflow-scrolling: touch;
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
    overflow-x: hidden;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    -webkit-overflow-scrolling: touch;
}

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-track {
    background: rgba(26, 86, 219, 0.04);
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 5px;
}

.main::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}

.content-wrapper {
    max-width: 900px;
    width: 100%;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 40px;
    color: var(--primary);
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 10px 20px;
    background: rgba(26, 86, 219, 0.06);
    border-radius: 10px;
    border: 1px solid var(--border);
}

.back-link:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateX(-5px);
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.15);
}

.page-title {
    font-size: 32px;
    text-align: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 50px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.option {
    background: var(--card-bg);
    border: 2px solid var(--border);
    border-radius: 15px;
    padding: 50px 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
    position: relative;
    overflow: hidden;
}

.option::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(135deg, transparent, rgba(26, 86, 219, 0.06), transparent);
    transform: rotate(45deg);
    transition: 0.5s;
}

.option:hover::before {
    left: 100%;
}

.option:hover {
    border-color: var(--primary);
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(26, 86, 219, 0.2);
    background: linear-gradient(135deg, rgba(26, 86, 219, 0.03), rgba(30, 64, 175, 0.03));
}

.option i {
    font-size: 60px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 20px;
    display: block;
    transition: 0.3s;
}

.option:hover i {
    transform: scale(1.1);
}

.option h3 {
    color: var(--text-primary);
    font-size: 20px;
    font-weight: 700;
}

.option .badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #ff0000, #c82333);
    color: #fff;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(255, 0, 0, 0.4);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.1); }
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
        padding: 90px 20px 40px;
        height: 100vh;
        justify-content: flex-start;
    }

    .page-title {
        font-size: 24px;
        margin-bottom: 30px;
    }

    .options {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .option {
        padding: 40px 30px;
    }

    .option i {
        font-size: 50px;
    }

    .option h3 {
        font-size: 18px;
    }

    .back-link {
        margin-bottom: 25px;
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
            <?php if (($unreadCustomerMessages + $unreadAdminMessages) > 0): ?>
                <span class="notification-badge"><?= ($unreadCustomerMessages + $unreadAdminMessages) ?></span>
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
    <div class="content-wrapper">
        <a href="mechanic_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Go to Dashboard
        </a>

        <h1 class="page-title">
            <i class="fas fa-envelope"></i> Message Center
        </h1>

        <div class="options">
            <a href="select_customer.php" class="option">
                <?php if ($unreadCustomerMessages > 0): ?>
                    <span class="badge"><?= $unreadCustomerMessages ?></span>
                <?php endif; ?>
                <i class="fas fa-users"></i>
                <h3>Message Customer</h3>
            </a>

            <a href="message_admin.php" class="option">
                <?php if ($unreadAdminMessages > 0): ?>
                    <span class="badge"><?= $unreadAdminMessages ?></span>
                <?php endif; ?>
                <i class="fas fa-user-tie"></i>
                <h3>Message Admin</h3>
            </a>
        </div>
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

console.log('✅ Message Center initialized');
</script>

</body>
</html>