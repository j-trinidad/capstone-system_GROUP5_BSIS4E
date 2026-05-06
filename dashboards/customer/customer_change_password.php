<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// Get counts for badges
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$notifStmt->execute([$user_id]);
$unreadNotifications = $notifStmt->fetchColumn();

$msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$msgStmt->execute([$user_id]);
$messages = $msgStmt->fetchColumn();

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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:        #1a56db;
    --secondary:      #1e40af;
    --dark-bg:        #f0f4ff;
    --card-bg:        #ffffff;
    --border:         rgba(26,86,219,0.2);
    --text-primary:   #1e293b;
    --text-secondary: #475569;
    --success:        #059669;
    --error:          #dc2626;
    --warning:        #d97706;
}
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    height:100%; font-family:'Outfit',sans-serif;
    background:linear-gradient(135deg,#f0f4ff,#e8eeff);
    color:var(--text-primary); overflow:hidden;
}
a { color:inherit; text-decoration:none; }

/* ── Hamburger ── */
.hamburger-btn {
    display:none; position:fixed; top:20px; left:20px; z-index:1001;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    border:none; width:50px; height:50px; border-radius:12px;
    cursor:pointer; box-shadow:0 4px 15px rgba(26,86,219,0.4); transition:all .3s;
}
.hamburger-btn:hover { transform:scale(1.05); box-shadow:0 6px 20px rgba(26,86,219,0.6); }
.hamburger-btn span {
    display:block; width:25px; height:3px; background:#ffffff;
    margin:5px auto; border-radius:2px; transition:all .3s;
}
.hamburger-btn.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
.hamburger-btn.active span:nth-child(2){opacity:0}
.hamburger-btn.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}

/* ── Notification Bell ── */
.notification-bell {
    position:fixed; top:20px; right:20px; z-index:1001;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    border:none; width:50px; height:50px; border-radius:50%;
    cursor:pointer; box-shadow:0 4px 15px rgba(26,86,219,0.4); transition:all .3s;
    display:flex; align-items:center; justify-content:center;
    font-size:20px; color:#ffffff;
}
.notification-bell:hover { transform:scale(1.1); box-shadow:0 6px 20px rgba(26,86,219,0.6); }
.notification-bell .bell-badge {
    position:absolute; top:-5px; right:-5px;
    background:#ff0000; color:#fff; width:22px; height:22px; border-radius:50%;
    font-size:11px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 0 10px rgba(255,0,0,0.8); animation:pulse 2s infinite;
}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}

/* ── Sidebar Overlay ── */
.sidebar-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(26,86,219,0.1); z-index:999; opacity:0; transition:opacity .3s;
}
.sidebar-overlay.active{display:block;opacity:1}

/* ── Sidebar ── */
.sidebar {
    position:fixed; top:0; left:0; width:260px; height:100vh; overflow-y:auto;
    background:linear-gradient(180deg,#1e3a8a,#1a56db);
    border-right:1px solid rgba(255,255,255,0.1);
    display:flex; flex-direction:column; padding:25px 20px;
    z-index:1000; transition:transform .3s; max-height:100vh;
}
.sidebar::-webkit-scrollbar{width:6px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:3px}
.logo {
    font-size:1.6rem; font-weight:700; color:#ffffff;
    margin-bottom:35px; display:flex; align-items:center; gap:10px; letter-spacing:.5px;
}
.sidebar nav { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.sidebar nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 10px;
    color: rgba(255,255,255,0.75); font-weight: 600;
    transition: all 0.3s ease; font-size: 14px; position: relative;
}
.sidebar nav a:hover,
.sidebar nav a.active {
    background: rgba(255,255,255,0.2);
    color: #ffffff; transform: translateX(5px);
}
.sidebar nav a i { width: 20px; text-align: center; }
.notification-badge {
    display:inline-flex; align-items:center; justify-content:center;
    background:#ff0000; color:#fff; width:18px; height:18px; min-width:18px;
    font-size:10px; font-weight:700; border-radius:50%; margin-left:auto;
    box-shadow:0 0 8px rgba(255,0,0,0.7);
}
.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: var(--text-primary) !important;
    margin-top: auto !important; justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }
.sidebar .footer {
    margin-top:auto; font-size:12px; color:rgba(255,255,255,0.5);
    text-align:center; padding-top:20px; border-top:1px solid rgba(255,255,255,0.15);
}

/* ── Main ── */
.main {
    margin-left:260px; padding:40px; width:calc(100% - 260px);
    height:100vh; overflow-y:auto;
    background:linear-gradient(135deg,#f0f4ff,#e8eeff);
    display:flex; flex-direction:column; align-items:center; justify-content:center;
}
.main::-webkit-scrollbar{width:8px}
.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

/* ── Container ── */
.container {
    background:var(--card-bg); border:1px solid var(--border); border-radius:16px;
    padding:40px; width:100%; max-width:550px;
    box-shadow:0 4px 15px rgba(26,86,219,0.08); text-align:center;
    transition:all .3s;
}
.container:hover {
    border-color:var(--primary);
    box-shadow:0 12px 40px rgba(26,86,219,0.15);
}

/* ── Card Header ── */
.card-header { margin-bottom:30px; }

.card-icon {
    width:100px; height:100px; margin:0 auto 25px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:45px; color:#ffffff;
    box-shadow:0 8px 25px rgba(26,86,219,0.3); animation:pulse 2s infinite;
}

.card-title {
    font-size:26px; font-weight:700; margin-bottom:10px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}

.card-description {
    color:var(--text-secondary); font-size:14px; margin-bottom:25px; line-height:1.6;
}

/* ── Warning Notice ── */
.warning-notice {
    background:rgba(217,119,6,0.08); border:1px solid rgba(217,119,6,0.25);
    border-radius:10px; padding:16px; margin-bottom:25px; text-align:left;
}
.warning-notice ul {
    margin:10px 0 0 20px; color:var(--text-secondary); font-size:13px; line-height:1.8;
}
.warning-notice ul li { margin-bottom:5px; }

/* ── Form ── */
.form-group { margin-bottom:20px; text-align:left; }
.form-group label {
    display:flex; align-items:center; gap:8px;
    font-size:11px; color:var(--primary); font-weight:700;
    text-transform:uppercase; letter-spacing:.6px; margin-bottom:8px;
}
input[type="password"] {
    width:100%; padding:11px 14px; border-radius:9px;
    border:1px solid var(--border); background:#f8faff;
    color:var(--text-primary); font-size:13px; transition:all .3s;
    font-family:'Outfit',sans-serif;
}
input[type="password"]:focus {
    border-color:var(--primary); outline:none;
    box-shadow:0 0 0 3px rgba(26,86,219,0.12); background:#ffffff;
}

/* ── Alerts ── */
.error {
    background:rgba(220,38,38,0.08); color:var(--error);
    padding:12px 15px; border-radius:10px; font-size:13px;
    margin-bottom:15px; border-left:4px solid var(--error);
    display:flex; align-items:center; gap:10px; text-align:left;
    animation:slideInDown .3s ease;
}
@keyframes slideInDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

.success-msg {
    background:rgba(5,150,105,0.08); color:var(--success);
    padding:15px 20px; border-radius:10px; font-size:14px;
    margin-bottom:20px; border-left:4px solid var(--success);
    display:flex; align-items:center; gap:10px; text-align:left;
    animation:bounceIn .6s ease;
}
@keyframes bounceIn{
    0%{opacity:0;transform:scale(0.3)}
    50%{opacity:1;transform:scale(1.05)}
    70%{transform:scale(0.9)}
    100%{opacity:1;transform:scale(1)}
}

/* ── Buttons ── */
.button-group {
    display:flex; gap:12px; justify-content:center; margin-top:30px; flex-wrap:wrap;
}
.btn {
    padding:11px 26px; border-radius:10px; border:none; font-weight:700; cursor:pointer;
    transition:all .3s; font-size:13px; text-transform:uppercase; letter-spacing:.5px;
    display:inline-flex; align-items:center; justify-content:center; gap:10px;
}
.btn-primary {
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#ffffff; box-shadow:0 4px 12px rgba(26,86,219,0.25);
}
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(26,86,219,0.35); }

.btn-secondary {
    background:transparent; color:var(--text-secondary);
    border:1px solid var(--border);
}
.btn-secondary:hover { background:rgba(26,86,219,0.06); color:var(--text-primary); transform:translateY(-2px); }

.btn-success {
    background:linear-gradient(135deg,var(--success),#10b981);
    color:#ffffff; box-shadow:0 4px 12px rgba(5,150,105,0.25);
}
.btn-success:hover { transform:translateY(-2px) scale(1.04); box-shadow:0 6px 18px rgba(5,150,105,0.35); }

/* ── Success Container ── */
.success-container { animation:fadeIn .5s ease; }
@keyframes fadeIn{from{opacity:0;transform:scale(0.9)}to{opacity:1;transform:scale(1)}}

.success-icon {
    width:80px; height:80px; margin:0 auto 20px;
    background:linear-gradient(135deg,var(--success),#10b981);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-size:40px; color:#ffffff; animation:scaleIn .6s ease;
}
@keyframes scaleIn{
    0%{transform:scale(0)} 50%{transform:scale(1.2)} 100%{transform:scale(1)}
}

/* ── Responsive ── */
@media(max-width:768px){
    .hamburger-btn{display:flex;flex-direction:column;align-items:center;justify-content:center}
    .sidebar{transform:translateX(-100%)}
    .sidebar.active{transform:translateX(0)}
    .main{margin-left:0;width:100%;padding:80px 20px 20px}
    .container{padding:25px;max-width:100%}
    .card-title{font-size:22px}
    .button-group{flex-direction:column}
    .btn{width:100%}
}
</style>
</head>
<body>

<!-- HAMBURGER BUTTON -->
<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<!-- NOTIFICATION BELL -->
<button class="notification-bell" id="notification-bell">
    <i class="fas fa-bell"></i>
    <?php if($unreadNotifications > 0): ?>
        <span class="bell-badge"><?= $unreadNotifications ?></span>
    <?php endif; ?>
</button>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-tools"></i><span>MotorService</span></div>
    <nav>
        <a href="customer_dashboard.php"       <?= $currentPage==='customer_dashboard.php'      ?'class="active"':'' ?>><i class="fas fa-home"></i> Home</a>
        <a href="customer_my_bookings.php"      <?= $currentPage==='customer_my_bookings.php'     ?'class="active"':'' ?>><i class="fas fa-calendar-alt"></i> My Bookings</a>
        <a href="customer_history.php"          <?= $currentPage==='customer_history.php'         ?'class="active"':'' ?>><i class="fas fa-history"></i> History</a>
        <a href="customer_messages.php"         <?= $currentPage==='customer_messages.php'        ?'class="active"':'' ?>>
            <i class="fas fa-envelope"></i> Messages
            <?php if($messages > 0): ?><span class="notification-badge"><?= $messages ?></span><?php endif; ?>
        </a>
        <a href="customer_my_receipts.php"      <?= $currentPage==='customer_my_receipts.php'     ?'class="active"':'' ?>><i class="fas fa-receipt"></i> My Receipts</a>
        <a href="customer_address.php"          <?= $currentPage==='customer_address.php'         ?'class="active"':'' ?>><i class="fas fa-map-marker-alt"></i> Address</a>
        <a href="customer_profile.php"          <?= $currentPage==='customer_profile.php'         ?'class="active"':'' ?>><i class="fas fa-user-circle"></i> Profile</a>
        <a href="customer_change_password.php"  <?= $currentPage==='customer_change_password.php' ?'class="active"':'' ?>><i class="fas fa-lock"></i> Change Password</a>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="container">
        <?php if (!$showForm && !$success): ?>
            <!-- CONFIRMATION SCREEN -->
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2 class="card-title">Change Password</h2>
                <p class="card-description">
                    You are about to change your password.
                    Please make sure to remember your new password for future logins.
                </p>
            </div>

            <div class="warning-notice">
                <p style="color:var(--warning);font-weight:600;margin-bottom:10px;">
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
                <a href="customer_dashboard.php" class="btn btn-secondary">
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
                    <a href="customer_change_password.php" class="btn btn-secondary">
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
                <div class="success-msg">
                    <i class="fas fa-thumbs-up"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <p class="card-description">Your password has been updated. You can now continue using the app securely.</p>
                <div class="button-group">
                    <a href="customer_dashboard.php" class="btn btn-success">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </a>
                </div>
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

document.getElementById('notification-bell').addEventListener('click', () => {
    window.location.href = 'customer_notifications.php';
});

console.log('✅ Customer Change Password page initialized');
</script>

</body>
</html>