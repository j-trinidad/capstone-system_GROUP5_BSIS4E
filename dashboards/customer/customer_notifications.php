<?php
session_start();
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$customer_id = $_SESSION['user_id'];

// Get counts for badges
$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$notifStmt->execute([$customer_id]);
$unreadNotifications = $notifStmt->fetchColumn();

$msgStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$msgStmt->execute([$customer_id]);
$messages = $msgStmt->fetchColumn();

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE customer_notifications SET is_read = 1, read_at = NOW() WHERE customer_id = ? AND is_read = 0");
    $stmt->execute([$customer_id]);
    $_SESSION['success'] = 'All notifications marked as read!';
    header('Location: customer_notifications.php');
    exit;
}

// Fetch all notifications
$stmt = $pdo->prepare("
    SELECT * FROM customer_notifications 
    WHERE customer_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$customer_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$unreadStmt->execute([$customer_id]);
$unreadCount = $unreadStmt->fetchColumn();

function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y h:i A', $time);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Notifications - MotorService</title>
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

/* MAIN CONTENT */
.main {
    width: 100%;
    height: 100vh;
    padding: 40px;
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

.header-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.btn {
    padding: 12px 25px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(26, 86, 219, 0.1);
    font-family: 'Outfit', sans-serif;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(26, 86, 219, 0.25);
}

.btn-secondary {
    background: rgba(26, 86, 219, 0.06);
    color: var(--primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateY(-2px);
}

.back-btn {
    padding: 12px 25px;
    border-radius: 8px;
    border: 1px solid var(--border);
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(26, 86, 219, 0.06);
    color: var(--primary);
}

.back-btn:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 86, 219, 0.15);
}

.stats-bar {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.08);
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
}

.stat-label {
    color: var(--text-secondary);
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 600;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.06);
}

.notification-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(26, 86, 219, 0.15);
    border-color: rgba(26, 86, 219, 0.35);
}

.notification-card.unread {
    border-left: 4px solid var(--primary);
    background: linear-gradient(135deg, rgba(26, 86, 219, 0.06), rgba(26, 86, 219, 0.02));
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 15px;
}

.notification-title {
    color: var(--primary);
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.new-badge {
    background: #ff0000;
    color: #fff;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.notification-time {
    color: var(--text-secondary);
    font-size: 12px;
    white-space: nowrap;
}

.notification-message {
    color: var(--text-secondary);
    line-height: 1.5;
    font-size: 13px;
    margin-bottom: 12px;
}

.notification-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid var(--border);
    gap: 10px;
}

.notification-type {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}

.type-booking_accepted {
    background: rgba(5, 150, 105, 0.1);
    color: #047857;
    border: 1px solid rgba(5, 150, 105, 0.35);
}

.type-booking_declined {
    background: rgba(220, 38, 38, 0.08);
    color: var(--error);
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.type-status_change {
    background: rgba(26, 86, 219, 0.08);
    color: var(--primary);
    border: 1px solid rgba(26, 86, 219, 0.25);
}

.type-info {
    background: rgba(217, 119, 6, 0.08);
    color: var(--warning);
    border: 1px solid rgba(217, 119, 6, 0.25);
}

.view-booking-btn {
    padding: 8px 16px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 11px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(26, 86, 219, 0.2);
    font-family: 'Outfit', sans-serif;
}

.view-booking-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(26, 86, 219, 0.3);
}

.alert {
    background: rgba(5, 150, 105, 0.08);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: var(--success);
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 80px;
    color: var(--border);
    margin-bottom: 20px;
    display: block;
}

.empty-state h3 {
    color: var(--primary);
    font-size: 22px;
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 25px;
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .main {
        padding: 20px;
    }

    .header {
        font-size: 20px;
        margin-bottom: 25px;
    }

    .header-actions {
        width: 100%;
    }

    .btn, .back-btn {
        flex: 1;
        justify-content: center;
        padding: 10px 16px;
        font-size: 11px;
    }

    .notification-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .notification-time {
        white-space: normal;
    }

    .notification-footer {
        flex-direction: column;
        align-items: flex-start;
    }

    .view-booking-btn {
        width: 100%;
        justify-content: center;
    }

    .stats-bar {
        flex-direction: column;
        gap: 20px;
    }

    .stat-item {
        width: 100%;
    }

    .notification-card {
        padding: 15px;
    }

    .notification-title {
        font-size: 14px;
    }

    .notification-message {
        font-size: 12px;
    }
}
</style>
</head>
<body>

<main class="main">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
        <div class="header">
            <i class="fas fa-bell"></i> Notifications
        </div>
        <a href="customer_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="header-actions">
        <?php if ($unreadCount > 0): ?>
            <a href="?mark_all_read=1" class="btn btn-primary">
                <i class="fas fa-check-double"></i> Mark All Read
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($_SESSION['success']) ?></span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?= count($notifications) ?></div>
            <div class="stat-label">Total Notifications</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" style="color: var(--primary);"><?= $unreadCount ?></div>
            <div class="stat-label">Unread</div>
        </div>
        <div class="stat-item">
            <div class="stat-number" style="color: var(--success);"><?= count($notifications) - $unreadCount ?></div>
            <div class="stat-label">Read</div>
        </div>
    </div>

    <div class="notification-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No Notifications Yet</h3>
                <p>You don't have any notifications at the moment.</p>
                <a href="customer_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-card <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" 
                     onclick="viewBooking(<?= $notif['id'] ?>, <?= $notif['booking_id'] ?>)">
                    <div class="notification-header">
                        <div class="notification-title">
                            <?= $notif['is_read'] == 0 ? '<span class="new-badge">NEW</span>' : '' ?>
                            <?= htmlspecialchars($notif['title']) ?>
                        </div>
                        <div class="notification-time">
                            <i class="fas fa-clock"></i> <?= timeAgo($notif['created_at']) ?>
                        </div>
                    </div>
                    
                    <div class="notification-message">
                        <?= htmlspecialchars($notif['message']) ?>
                    </div>
                    
                    <div class="notification-footer">
                        <span class="notification-type type-<?= htmlspecialchars($notif['type']) ?>">
                            <i class="fas fa-info-circle"></i> <?= ucfirst(str_replace('_', ' ', $notif['type'])) ?>
                        </span>
                        <button class="view-booking-btn" onclick="event.stopPropagation(); viewBooking(<?= $notif['id'] ?>, <?= $notif['booking_id'] ?>)">
                            <i class="fas fa-eye"></i> View Booking
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function viewBooking(notifId, bookingId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ notification_id: notifId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'customer_my_bookings.php?id=' + bookingId;
        }
    })
    .catch(err => console.error('Error:', err));
}
</script>

</body>
</html>