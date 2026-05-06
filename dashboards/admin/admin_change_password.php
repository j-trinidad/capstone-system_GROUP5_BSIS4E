<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$message = "";
$success = false;

// Fetch current user data
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user confirmed they want to change password
$showForm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    } elseif (!password_verify($current_password, $user['password_hash'])) {
        $errors[] = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        $message = "Password changed successfully!";
        $success = true;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password - Admin</title>
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
    display: flex;
    align-items: center;
    justify-content: center;
}

.main::-webkit-scrollbar {
    width: 8px;
}

.main::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

.content-wrapper {
    max-width: 550px;
    width: 100%;
}

.change-password-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 40px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
    text-align: center;
}

.change-password-card:hover {
    border-color: var(--primary);
    box-shadow: 0 12px 40px rgba(255, 140, 0, 0.3);
}

.card-header {
    margin-bottom: 30px;
}

.card-header img {
    width: 80px;
    margin-bottom: 20px;
}

.card-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 25px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 45px;
    color: #1a1f3a;
    box-shadow: 0 8px 25px rgba(255, 140, 0, 0.4);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.card-title {
    font-size: 28px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-weight: 700;
}

.card-description {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 25px;
    line-height: 1.6;
}

.warning-notice {
    background: rgba(255, 168, 0, 0.1);
    border: 1px solid rgba(255, 168, 0, 0.3);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 25px;
    text-align: left;
}

.warning-notice ul {
    margin: 10px 0 0 20px;
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.8;
}

.warning-notice ul li {
    margin-bottom: 5px;
}

.error {
    background: rgba(255, 71, 87, 0.2);
    color: var(--error);
    padding: 12px 15px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 15px;
    border-left: 4px solid var(--error);
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: left;
    animation: slideInDown 0.3s ease;
}

@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.success {
    background: rgba(0, 208, 132, 0.2);
    color: var(--success);
    padding: 15px 20px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    border-left: 4px solid var(--success);
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: left;
    animation: bounceIn 0.6s ease;
}

@keyframes bounceIn {
    0% { opacity: 0; transform: scale(0.3); }
    50% { opacity: 1; transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1); }
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 8px;
}

input[type="password"] {
    width: 100%;
    background: rgba(255, 255, 255, 0.08);
    border: 2px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    padding: 12px 15px;
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
    transition: all 0.3s ease;
}

input[type="password"]:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 10px rgba(255, 140, 0, 0.3);
    background: rgba(255, 255, 255, 0.12);
}

.button-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 14px 30px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
    box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.5);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(255, 140, 0, 0.2);
    border-color: var(--primary);
    transform: translateY(-3px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #20c997);
    color: #1a1f3a;
    box-shadow: 0 4px 15px rgba(0, 208, 132, 0.3);
    animation: pulse 2s infinite;
}

.btn-success:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 20px rgba(0, 208, 132, 0.5);
    animation: none;
}

.success-container {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, var(--success), #20c997);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: #1a1f3a;
    animation: scaleIn 0.6s ease;
}

@keyframes scaleIn {
    0% { transform: scale(0); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
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
        align-items: flex-start;
    }

    .content-wrapper { max-width: 100%; }
    .change-password-card { padding: 25px; }
    .card-title { font-size: 22px; }
    .button-group { flex-direction: column; }
    .btn { width: 100%; }
}

@media (max-width: 480px) {
    .main { padding: 75px 15px 15px 15px; }
    .card-icon { width: 80px; height: 80px; font-size: 35px; }
}
</style>
</head>
<body>

<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
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

<!-- MAIN CONTENT -->
<main class="main">
    <div class="content-wrapper">
        <div class="change-password-card">
            <?php if (!$showForm && !$success): ?>
                <!-- CONFIRMATION SCREEN -->
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2 class="card-title">
                        Change Password
                    </h2>
                    <p class="card-description">
                       You are about to change your password.
Please make sure to remember your new password for future logins.
                    </p>
                </div>

                <div class="warning-notice">
                    <p style="color: var(--warning); font-weight: 600; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> Important Reminders:
                    </p>
                    <ul>
                        <li>Make sure you remember your current password</li>
                        <li>Choose a strong new password (minimum 6 characters)</li>
                        <li>Don't share your password with anyone</li>
                        <li>You'll need to enter your current password to verify it's you</li>
                    </ul>
                </div>

                <div class="button-group">
                    <a href="?confirm=1" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Proceed to Change Password
                    </a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>

            <?php elseif ($showForm && !$success): ?>
                <!-- PASSWORD CHANGE FORM -->
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-lock"></i> Change Password
                    </h2>
                    <p class="card-description">Enter your current password and set a new one.</p>
                </div>

                <?php foreach ($errors as $e): ?>
                    <div class="error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?= htmlspecialchars($e) ?></span>
                    </div>
                <?php endforeach; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">
                            <i class="fas fa-lock"></i> Current Password
                        </label>
                        <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-unlock"></i> New Password
                        </label>
                        <input type="password" name="new_password" id="new_password" placeholder="Enter new password (min. 6 characters)" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-check-circle"></i> Confirm New Password
                        </label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                        <a href="admin_change_password.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <!-- SUCCESS SCREEN -->
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="card-title">
                        <i class="fas fa-check-circle"></i> Password Changed!
                    </h2>
                    <div class="success">
                        <i class="fas fa-thumbs-up"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                    <p class="card-description">Your password has been updated. You can now continue using the admin panel securely.</p>
                    <div class="button-group">
                        <a href="admin_dashboard.php" class="btn btn-success">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
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
console.log('✅ Admin Change Password page initialized');
</script>

</body>
</html>