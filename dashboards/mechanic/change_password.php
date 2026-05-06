<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$message = "";
$success = false;

// Fetch current user data
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Messages count (unread)
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$user_id]);
$messages = $messagesStmt->fetchColumn();

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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Change Password - MotorService</title>
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
    -webkit-overflow-scrolling: touch;
}

.sidebar::-webkit-scrollbar { width: 6px; }
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
    display: flex;
    align-items: center;
    justify-content: center;
    -webkit-overflow-scrolling: touch;
}

.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-track { background: rgba(26, 86, 219, 0.04); }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 5px; }
.main::-webkit-scrollbar-thumb:hover { background: var(--primary); }

.content-wrapper {
    max-width: 550px;
    width: 100%;
}

.change-password-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 15px 40px rgba(26, 86, 219, 0.10), 0 0 30px rgba(26, 86, 219, 0.06);
    text-align: center;
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
    color: #ffffff;
    box-shadow: 0 8px 25px rgba(26, 86, 219, 0.3);
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
    background: rgba(217, 119, 6, 0.08);
    border: 1px solid rgba(217, 119, 6, 0.3);
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
    background: rgba(220, 38, 38, 0.08);
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
    to   { opacity: 1; transform: translateY(0); }
}

.success {
    background: rgba(5, 150, 105, 0.1);
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
    background: #f8faff;
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
    box-shadow: 0 0 10px rgba(26, 86, 219, 0.15);
    background: #eef2ff;
}

input::placeholder {
    color: #94a3b8;
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

.btn:active {
    transform: scale(0.98);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.45);
}

.btn-secondary {
    background: rgba(26, 86, 219, 0.06);
    color: var(--primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(26, 86, 219, 0.12);
    border-color: var(--primary);
    transform: translateY(-3px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #10b981);
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
    animation: pulse 2s infinite;
}

.btn-success:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
    animation: none;
}

.success-container {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.9); }
    to   { opacity: 1; transform: scale(1); }
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, var(--success), #10b981);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: #ffffff;
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
    }

    .sidebar.active { transform: translateX(0); }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 80px 20px 100px;
        align-items: flex-start;
        overflow-y: auto;
    }

    .content-wrapper {
        max-width: 100%;
        margin-bottom: 40px;
    }

    .change-password-card {
        padding: 25px;
        margin-bottom: 30px;
    }

    .card-title { font-size: 22px; }

    .card-icon {
        width: 80px;
        height: 80px;
        font-size: 38px;
    }

    .button-group {
        flex-direction: column;
        margin-bottom: 60px;
    }

    .btn {
        width: 100%;
        padding: 16px 30px;
    }

    .warning-notice ul {
        font-size: 12px;
        margin-left: 15px;
    }
}

@media (max-width: 480px) {
    .main { padding: 70px 15px 120px; }

    .change-password-card {
        padding: 20px 15px;
        margin-bottom: 40px;
    }

    .card-title { font-size: 20px; }

    .card-icon {
        width: 70px;
        height: 70px;
        font-size: 35px;
    }

    .button-group { margin-bottom: 80px; }

    .btn { padding: 18px 30px; font-size: 13px; }

    .card-description { font-size: 13px; }

    .form-group label { font-size: 13px; }

    input[type="password"] {
        font-size: 14px;
        padding: 14px;
    }

    .success-icon {
        width: 70px;
        height: 70px;
        font-size: 35px;
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
                       You are about to change your password. Please make sure to remember your new password for future logins.
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
                    <a href="mechanic_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>

            <?php elseif ($showForm && !$success): ?>
                <!-- PASSWORD CHANGE FORM -->
                <div class="card-header">
                    <img src="../../assets/img/logo.png" alt="Logo">
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
                            <i class="fas fa-key"></i> Current Password
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
                        <a href="change_password.php" class="btn btn-secondary">
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
                    <p class="card-description">Your password has been updated. You can now continue using the app securely.</p>
                    <div class="button-group">
                        <a href="mechanic_dashboard.php" class="btn btn-success">
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

let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) event.preventDefault();
    lastTouchEnd = now;
}, false);

console.log('✅ Change Password page initialized');
</script>

</body>
</html>