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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Title - MotorService</title>
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

.app {
    display: flex;
    height: 100vh;
}

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
    position: relative;
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
    box-shadow: 0 0 8px rgba(255,0,0,0.7);
}

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: var(--text-primary) !important;
    margin-top: auto !important;
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
}

.header {
    font-size: 28px;
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

.panel {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

@media (max-width: 768px) {
    .app {
        flex-direction: column;
    }

    .sidebar {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: auto;
        border-top: 1px solid var(--border);
        border-right: none;
        flex-direction: row;
        padding: 10px 20px;
        overflow-x: auto;
        overflow-y: hidden;
    }

    .logo {
        display: none;
    }

    .sidebar nav {
        flex-direction: row;
        gap: 5px;
    }

    .sidebar nav a {
        padding: 8px 12px;
        font-size: 12px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .logout-btn {
        display: none;
    }

    .main {
        margin-left: 0;
        width: 100%;
        margin-bottom: 80px;
        padding: 20px;
        height: auto;
    }

    .header {
        font-size: 20px;
    }
}
</style>
</head>
<body>

<div class="app">
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-tools"></i>
            <span>MotorService</span>
        </div>

        <nav>
            <a href="customer_dashboard.php">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="customer_my_bookings.php">
                <i class="fas fa-calendar-alt"></i> My Bookings
            </a>
            <a href="customer_history.php">
                <i class="fas fa-history"></i> History
            </a>
            <a href="customer_notifications.php">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unreadNotifications > 0): ?>
                    <span class="notification-badge"><?= $unreadNotifications ?></span>
                <?php endif; ?>
            </a>
            <a href="customer_messages.php">
                <i class="fas fa-envelope"></i> Messages
                <?php if ($messages > 0): ?>
                    <span class="notification-badge"><?= $messages ?></span>
                <?php endif; ?>
            </a>
            <a href="customer_address.php">
                <i class="fas fa-map-marker-alt"></i> Address
            </a>
            <a href="customer_profile.php">
                <i class="fas fa-user-circle"></i> Profile
            </a>
            <a href="customer_change_password.php">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="../../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main">
        <div class="header">
            <i class="fas fa-icon-here"></i> Page Title
        </div>

        <div class="panel">
            <!-- Your content here -->
            <h3 style="color: var(--primary); margin-bottom: 20px;">Content Section</h3>
            <p style="color: var(--text-secondary);">Add your page content here...</p>
        </div>
    </main>
</div>

</body>
</html>