<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id   = $_SESSION['user_id'];
$mechanic_name = $_SESSION['user_name'] ?? 'Mechanic';
$today         = date('Y-m-d');
$DAILY_LIMIT   = 4;

// Messages count (unread)
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$mechanic_id]);
$messages = $messagesStmt->fetchColumn();

// Active bookings TODAY
$todayLimStmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE mechanic_id = ? AND DATE(schedule) = ?
    AND status IN ('pending','in_progress')
");
$todayLimStmt->execute([$mechanic_id, $today]);
$confirmedTodayCount = (int)$todayLimStmt->fetchColumn();

// Filter logic
$filter       = $_GET['filter'] ?? 'all';
$where_clause = "b.mechanic_id = ?";

if ($filter === 'pending') {
    $where_clause .= " AND b.status = 'pending'";
} elseif ($filter === 'in_progress') {
    $where_clause .= " AND b.status = 'in_progress'";
} elseif ($filter === 'completed') {
    $where_clause .= " AND b.status = 'completed'";
} else {
    $where_clause .= " AND b.status IN ('pending','in_progress')";
}

// Fetch bookings
$stmt = $pdo->prepare("
    SELECT b.*,
           u.first_name AS customer_first,
           u.last_name  AS customer_last,
           u.email      AS customer_email,
           s.name       AS service_name,
           s.service_key
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    LEFT JOIN services s ON b.service_type = s.service_key
                         OR b.service_type = CAST(s.id AS CHAR)
    WHERE $where_clause
    ORDER BY
        CASE b.status
            WHEN 'pending'     THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed'   THEN 3
            ELSE 5
        END,
        b.created_at DESC
");
$stmt->execute([$mechanic_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service Requests - MotorService</title>
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
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    overflow: hidden;
}
a { color: inherit; text-decoration: none; }

/* HAMBURGER */
.hamburger-btn {
    display: none; position: fixed; top: 20px; left: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 12px;
    cursor: pointer; box-shadow: 0 4px 15px rgba(26,86,219,0.4); transition: all 0.3s ease;
}
.hamburger-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(26,86,219,0.6); }
.hamburger-btn span { display: block; width: 25px; height: 3px; background: #ffffff; margin: 5px auto; border-radius: 2px; transition: all 0.3s ease; }
.hamburger-btn.active span:nth-child(1) { transform: rotate(45deg) translate(8px,8px); }
.hamburger-btn.active span:nth-child(2) { opacity: 0; }
.hamburger-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(7px,-7px); }

/* OVERLAY */
.sidebar-overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%; background: rgba(26,86,219,0.1);
    z-index: 999; opacity: 0; transition: opacity 0.3s ease;
}
.sidebar-overlay.active { display: block; opacity: 1; }

/* SIDEBAR */
.sidebar {
    position: fixed; top: 0; left: 0; width: 260px; height: 100vh; overflow-y: auto;
    background: linear-gradient(180deg, #1e3a8a 0%, #1a56db 100%);
    border-right: 1px solid rgba(255,255,255,0.1);
    display: flex; flex-direction: column; padding: 25px 20px;
    z-index: 1000; transition: transform 0.3s ease; max-height: 100vh;
    -webkit-overflow-scrolling: touch;
}
.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }
.logo {
    font-size: 1.6rem; font-weight: 700; color: #ffffff;
    margin-bottom: 35px; letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 10px;
}
.sidebar nav { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.sidebar nav a {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px;
    color: rgba(255,255,255,0.75); font-weight: 600; transition: all 0.3s ease;
    font-size: 14px; position: relative;
}
.sidebar nav a:hover, .sidebar nav a.active {
    background: rgba(255,255,255,0.2);
    color: #ffffff; transform: translateX(5px);
}
.sidebar nav a i { width: 20px; text-align: center; }
.notification-badge {
    display: inline-flex; align-items: center; justify-content: center;
    background-color: #ff0000; color: #ffffff;
    width: 18px; height: 18px; min-width: 18px;
    font-size: 10px; font-weight: 700; border-radius: 50%;
    margin-left: auto; line-height: 1; box-shadow: 0 0 8px rgba(255,0,0,0.7);
}
.sidebar .footer {
    margin-top: auto; font-size: 12px; color: rgba(255,255,255,0.5);
    text-align: center; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.15);
}
.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: #fff !important; margin-top: auto !important; justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }

/* MAIN */
.main {
    margin-left: 260px; padding: 40px;
    width: calc(100% - 260px); height: 100vh;
    overflow-y: auto; overflow-x: hidden;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    -webkit-overflow-scrolling: touch;
}
.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-track { background: rgba(26,86,219,0.04); }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 5px; }
.main::-webkit-scrollbar-thumb:hover { background: var(--primary); }

.header {
    font-size: 28px; font-weight: 700; margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; display: flex; align-items: center; gap: 15px;
}

/* LIMIT BANNERS */
.limit-banner {
    padding: 14px 20px; border-radius: 10px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 12px;
    font-weight: 600; font-size: 14px; animation: slideDown 0.3s ease;
}
.limit-banner.warn {
    background: rgba(217,119,6,0.08); border: 1px solid rgba(217,119,6,0.35); color: var(--warning);
}
.limit-banner.full {
    background: rgba(220,38,38,0.08); border: 1px solid rgba(220,38,38,0.35); color: var(--error);
    animation: slideDown 0.3s ease, pulseBanner 2s infinite;
}
@keyframes pulseBanner {
    0%,100% { box-shadow: 0 0 0 rgba(220,38,38,0.1); }
    50%      { box-shadow: 0 0 18px rgba(220,38,38,0.2); }
}

/* FILTERS */
.filters { display: flex; gap: 12px; margin-bottom: 30px; flex-wrap: wrap; }
.filter-btn {
    padding: 10px 20px; background: rgba(26,86,219,0.06); border: 1px solid var(--border);
    color: var(--text-secondary); border-radius: 8px; font-weight: 600; cursor: pointer;
    transition: all 0.3s ease; font-size: 13px; display: flex; align-items: center; gap: 6px;
}
.filter-btn:hover, .filter-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; border-color: var(--primary);
}

/* BOOKING CARDS */
.bookings-container { display: block; margin-top: 20px; }
.booking-card {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: 12px; overflow: hidden;
    box-shadow: 0 4px 15px rgba(26,86,219,0.08);
    transition: all 0.3s ease; animation: slideIn 0.4s ease; margin-bottom: 20px;
}
@keyframes slideIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.booking-card:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 12px 30px rgba(26,86,219,0.15); }

.booking-header {
    padding: 20px;
    background: linear-gradient(90deg, rgba(26,86,219,0.06), rgba(30,64,175,0.04));
    border-bottom: 1px solid var(--border);
    cursor: pointer; display: flex; justify-content: space-between; align-items: center;
    transition: all 0.3s ease;
}
.booking-header:hover { background: linear-gradient(90deg, rgba(26,86,219,0.1), rgba(30,64,175,0.08)); }
.booking-header-left { display: flex; align-items: center; gap: 15px; flex: 1; }
.booking-icon { font-size: 28px; color: var(--primary); }
.booking-info h4 { color: var(--text-primary); font-size: 16px; font-weight: 700; margin-bottom: 5px; }
.booking-info p { color: var(--text-secondary); font-size: 13px; margin: 0; display: flex; align-items: center; gap: 6px; }
.booking-header-right { text-align: right; min-width: 150px; }
.booking-price {
    font-size: 20px; font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; margin-bottom: 8px;
}

/* STATUS BADGES */
.status-badge {
    display: inline-block; padding: 6px 12px; border-radius: 6px;
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.status-badge.pending     { background: rgba(217,119,6,0.12);  color: var(--warning); border: 1px solid rgba(217,119,6,0.3); }
.status-badge.in_progress { background: rgba(26,86,219,0.12);  color: var(--primary); border: 1px solid rgba(26,86,219,0.3); }
.status-badge.completed   { background: rgba(5,150,105,0.12);  color: var(--success); border: 1px solid rgba(5,150,105,0.3); }

/* BOOKING DETAILS */
.booking-details { display: none; padding: 20px; background: rgba(26,86,219,0.02); }
.booking-details.show { display: block; }
.details-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px; margin-bottom: 20px;
}
.detail-item {
    background: #f8faff; padding: 12px; border-radius: 8px;
    border: 1px solid var(--border);
}
.detail-item label { color: var(--primary); font-weight: 700; font-size: 12px; display: block; margin-bottom: 6px; }
.detail-item p { color: var(--text-primary); font-size: 13px; margin: 0; word-break: break-word; }

/* ACTION BUTTONS */
.booking-actions {
    display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;
    margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);
}
.btn-action {
    padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
    font-weight: 700; transition: all 0.3s ease; font-size: 12px;
    text-transform: uppercase; letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 6px;
    box-shadow: 0 4px 12px rgba(26,86,219,0.1);
    font-family: 'Outfit', sans-serif;
}
.btn-action:active { transform: scale(0.98); }
.btn-acknowledge { background: linear-gradient(135deg, #1a56db, #1e40af); color: #fff; }
.btn-acknowledge:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(26,86,219,0.35); }
.btn-acknowledged-done {
    background: rgba(0,0,0,0.04) !important;
    color: rgba(0,0,0,0.25) !important;
    cursor: not-allowed !important;
    box-shadow: none !important;
    transform: none !important;
    border: 1px solid rgba(0,0,0,0.1);
}
.btn-accept { background: linear-gradient(135deg, var(--success), #047857); color: #fff; }
.btn-accept:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(5,150,105,0.35); }
.btn-accept:disabled,
.btn-accept.btn-disabled {
    background: rgba(0,0,0,0.05) !important; color: rgba(0,0,0,0.2) !important;
    cursor: not-allowed !important; box-shadow: none !important;
    transform: none !important; border: 1px solid rgba(0,0,0,0.08);
}
.btn-complete { background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff; }
.btn-complete:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(2,132,199,0.35); }
.btn-decline  { background: linear-gradient(135deg, var(--error), #b91c1c); color: #fff; }
.btn-decline:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220,38,38,0.35); }
.btn-noshow   { background: linear-gradient(135deg, #64748b, #475569); color: #fff; }
.btn-noshow:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(100,116,139,0.35); }

/* TOOLTIP */
.tip-wrap { position: relative; display: inline-flex; }
.tip-wrap .tip {
    display: none; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%);
    background: #1e293b; color: #fff;
    padding: 7px 13px; border-radius: 8px; font-size: 11px;
    white-space: nowrap; border: 1px solid rgba(0,0,0,0.1);
    pointer-events: none; z-index: 50;
}
.tip-wrap:hover .tip { display: block; }

/* FLASH MESSAGES */
.flash-message {
    padding: 15px 20px; border-radius: 10px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 12px; animation: slideDown 0.3s ease;
    font-weight: 600; font-size: 14px;
}
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.flash-message.success { background: rgba(5,150,105,0.08);  border: 1px solid rgba(5,150,105,0.3);  color: var(--success); }
.flash-message.error   { background: rgba(220,38,38,0.08);  border: 1px solid rgba(220,38,38,0.3);  color: var(--error); }
.flash-message.info    { background: rgba(2,132,199,0.08);  border: 1px solid rgba(2,132,199,0.3);  color: var(--info); }
.flash-message i { font-size: 16px; }

/* EMPTY STATE */
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-secondary); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.3; color: var(--primary); }
.empty-state p { margin-bottom: 16px; }

/* GRACE EXPIRED BANNER */
.grace-expired-banner {
    background: rgba(220,38,38,0.06); border-bottom: 1px solid rgba(220,38,38,0.2);
    padding: 10px 20px; display: flex; align-items: center; gap: 10px;
    font-size: 12px; font-weight: 700; color: var(--error);
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    .header { font-size: 24px; }
    .details-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .hamburger-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .sidebar {
        position: fixed; top: 0; left: 0; width: 80%; max-width: 300px;
        height: 100vh; transform: translateX(-100%);
        flex-direction: column; padding: 25px 20px; overflow-y: auto;
    }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { flex-direction: column; gap: 8px; }
    .sidebar nav a { padding: 12px 16px; font-size: 14px; }
    .main { margin-left: 0; width: 100%; padding: 90px 20px 100px 20px; height: 100vh; }
    .header { font-size: 20px; margin-top: 0; margin-bottom: 20px; }
    .filters { gap: 8px; margin-bottom: 20px; }
    .filter-btn { padding: 8px 12px; font-size: 11px; }
    .booking-header { flex-direction: column; align-items: flex-start; gap: 12px; padding: 15px; }
    .booking-header-left { width: 100%; }
    .booking-header-right { width: 100%; text-align: left; }
    .details-grid { grid-template-columns: 1fr; gap: 10px; }
    .booking-details { padding-bottom: 80px; }
    .booking-actions { flex-direction: column; margin-bottom: 40px; }
    .btn-action { width: 100%; justify-content: center; padding: 14px 20px; }
    .booking-card { margin-bottom: 20px; }
    .booking-icon { font-size: 24px; }
    .booking-info h4 { font-size: 15px; }
    .booking-info p { font-size: 12px; }
    .booking-price { font-size: 18px; }
    .flash-message { padding: 12px 15px; font-size: 13px; }
    .empty-state { padding: 40px 20px; }
}
@media (max-width: 480px) {
    .main { padding: 80px 15px 120px; }
    .booking-actions { margin-bottom: 60px; }
    .btn-action { padding: 16px 20px; }
}
</style>
</head>
<body>

<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-wrench"></i><span>MotorService</span></div>
    <nav>
        <a href="mechanic_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="service_request.php" class="active"><i class="fas fa-clipboard-list"></i> Service Requests</a>
        <a href="mechanic_history.php"><i class="fas fa-history"></i> History</a>
        <a href="message_center.php">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="change_password.php"><i class="fas fa-lock"></i> Change Password</a>
        <a href="report_absence.php"><i class="fas fa-calendar-times"></i> File Leave</a>
        <a href="../../logout.php" class="logout-btn" style="margin-top:auto;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<main class="main">
    <div class="header"><i class="fas fa-clipboard-list"></i> Service Requests</div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="flash-message success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($_SESSION['success']) ?></span></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="flash-message error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($_SESSION['error']) ?></span></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['info'])): ?>
        <div class="flash-message info"><i class="fas fa-info-circle"></i><span><?= htmlspecialchars($_SESSION['info']) ?></span></div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <!-- DAILY LIMIT BANNER -->
    <?php if ($confirmedTodayCount >= $DAILY_LIMIT): ?>
        <div class="limit-banner full">
            <i class="fas fa-ban"></i>
            Daily limit reached: <?= $confirmedTodayCount ?>/<?= $DAILY_LIMIT ?> active bookings today.
            A slot opens up when a booking is cancelled or completed.
        </div>
    <?php elseif ($confirmedTodayCount === $DAILY_LIMIT - 1): ?>
        <div class="limit-banner warn">
            <i class="fas fa-exclamation-triangle"></i>
            <?= $confirmedTodayCount ?>/<?= $DAILY_LIMIT ?> active bookings today — 1 slot remaining.
        </div>
    <?php endif; ?>

    <!-- FILTER BUTTONS -->
    <div class="filters">
        <a href="?filter=all"         class="filter-btn <?= $filter==='all'         ? 'active':'' ?>"><i class="fas fa-list"></i> All Active</a>
        <a href="?filter=pending"     class="filter-btn <?= $filter==='pending'     ? 'active':'' ?>"><i class="fas fa-clock"></i> Pending</a>
        <a href="?filter=in_progress" class="filter-btn <?= $filter==='in_progress' ? 'active':'' ?>"><i class="fas fa-cog"></i> In Progress</a>
        <a href="?filter=completed"   class="filter-btn <?= $filter==='completed'   ? 'active':'' ?>"><i class="fas fa-check-circle"></i> Completed</a>
    </div>

    <div class="bookings-container">
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No service requests found for this filter.</p>
                <small style="color:#94a3b8;">Try selecting a different filter or check back later.</small>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b):
                $parts     = json_decode($b['parts'], true) ?: [];
                $partsList = !empty($parts) ? implode(', ', array_map(fn($p) => htmlspecialchars($p['name']), $parts)) : 'None';
                $homeFee   = ($b['service_location'] === 'home') ? 150 : 0;
                $total     = $b['labor_fee'] + $homeFee + $b['service_fee'] + $b['parts_total'];
                $statusClass = strtolower(str_replace(' ', '_', $b['status']));

                $bookingDate = date('Y-m-d', strtotime($b['schedule']));

                $isFuture       = ($bookingDate > $today);
                $isAcknowledged = !empty($b['is_acknowledged']);
                $isSameDay      = ($bookingDate === $today);

                $slotTimestamp = strtotime($b['schedule']);
                $graceExpired  = (time() >= ($slotTimestamp + (90 * 60)));

                $limStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM bookings
                    WHERE mechanic_id = ? AND DATE(schedule) = ?
                      AND status IN ('pending','in_progress')
                      AND id != ?
                ");
                $limStmt->execute([$mechanic_id, $bookingDate, $b['id']]);
                $limitHit = ((int)$limStmt->fetchColumn() >= $DAILY_LIMIT);

                $acceptDisabled = !$isSameDay || $graceExpired || $limitHit;
                $acceptTip = !$isSameDay
                    ? 'Accept is only available on the day of the booking (' . date('M d, Y', strtotime($b['schedule'])) . ')'
                    : ($graceExpired
                        ? 'Accept period has passed (cutoff: 30 mins before slot end) — booking will be auto-cancelled'
                        : 'Daily limit of ' . $DAILY_LIMIT . ' active bookings reached for this day');

                $showNoShow = ($isSameDay && $b['service_location'] === 'shop');
            ?>
                <div class="booking-card">
                    <?php if ($graceExpired && $b['status'] === 'pending'): ?>
                    <div class="grace-expired-banner">
                        <i class="fas fa-hourglass-end"></i>
                        Accept period has passed — this booking will be auto-cancelled shortly.
                    </div>
                    <?php endif; ?>

                    <div class="booking-header" onclick="toggleDetails(this)">
                        <div class="booking-header-left">
                            <div class="booking-icon"><i class="fas fa-wrench"></i></div>
                            <div class="booking-info">
                                <h4>Booking #<?= $b['id'] ?></h4>
                                <p><strong><?= htmlspecialchars($b['customer_first'].' '.$b['customer_last']) ?></strong>
                                   • <?= htmlspecialchars($b['service_name'] ?: $b['service_type']) ?></p>
                                <p><i class="fas fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($b['schedule'])) ?></p>
                                <?php if ($b['service_location'] === 'shop'): ?>
                                <p><i class="fas fa-store"></i> In-Shop</p>
                                <?php else: ?>
                                <p><i class="fas fa-home"></i> Home Service</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="booking-header-right">
                            <div class="booking-price">₱<?= number_format($total, 2) ?></div>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="booking-details">
                        <div class="details-grid">
                            <div class="detail-item">
                                <label><i class="fas fa-envelope"></i> Customer Email</label>
                                <p><?= htmlspecialchars($b['customer_email']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-motorcycle"></i> Motorcycle Brand</label>
                                <p><?= htmlspecialchars($b['brand']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-car"></i> Vehicle Type</label>
                                <p><?= htmlspecialchars($b['vehicle_type']) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-map-marker-alt"></i> Service Location</label>
                                <p><?= ucfirst($b['service_location']) ?></p>
                            </div>
                            <?php if ($b['service_location'] === 'home'): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-home"></i> Address</label>
                                <p><?= htmlspecialchars($b['service_address']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($b['tire_size'])): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-circle"></i> Tire Size</label>
                                <p><?= htmlspecialchars($b['tire_size']) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <label><i class="fas fa-dollar-sign"></i> Labor Fee</label>
                                <p>₱<?= number_format($b['labor_fee'], 2) ?></p>
                            </div>
                            <?php if ($homeFee > 0): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-house-user"></i> Home Fee</label>
                                <p>₱<?= number_format($homeFee, 2) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <label><i class="fas fa-receipt"></i> Service Fee</label>
                                <p>₱<?= number_format($b['service_fee'], 2) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-tools"></i> Parts Total</label>
                                <p>₱<?= number_format($b['parts_total'], 2) ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-box"></i> Parts Used</label>
                                <p><?= $partsList ?></p>
                            </div>
                            <div class="detail-item">
                                <label><i class="fas fa-sticky-note"></i> Note</label>
                                <p><?= htmlspecialchars($b['note'] ?: '-') ?></p>
                            </div>
                        </div>

                        <!-- ACTION BUTTONS -->
                        <div class="booking-actions">

                            <?php if ($b['status'] === 'pending'): ?>

                                <?php if ($isFuture): ?>
                                <?php if ($isAcknowledged): ?>
                                <div class="tip-wrap">
                                    <button type="button" class="btn-action btn-acknowledge btn-acknowledged-done" disabled>
                                        <i class="fas fa-check-double"></i> Acknowledged
                                    </button>
                                    <div class="tip"><i class="fas fa-info-circle"></i> Already sent acknowledgement to customer</div>
                                </div>
                                <?php else: ?>
                                <form method="POST" action="handle_booking.php" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" name="acknowledge_booking" class="btn-action btn-acknowledge"
                                            onclick="return confirm('Send acknowledgement to customer?')">
                                        <i class="fas fa-bell"></i> Acknowledge
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($acceptDisabled): ?>
                                    <div class="tip-wrap">
                                        <button type="button" class="btn-action btn-accept btn-disabled" disabled>
                                            <i class="fas fa-check"></i> Accept Booking
                                        </button>
                                        <div class="tip"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($acceptTip) ?></div>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="handle_booking.php" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                        <button type="submit" name="accept_booking" class="btn-action btn-accept">
                                            <i class="fas fa-check"></i> Accept Booking
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($showNoShow): ?>
                                <form method="POST" action="handle_booking.php" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" name="mark_noshow" class="btn-action btn-noshow"
                                            onclick="return confirm('Mark this customer as No-Show?\n\nThis will cancel the booking and add a warning to their account.')">
                                        <i class="fas fa-user-slash"></i> Mark No-Show
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" action="handle_booking.php" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" name="reject_booking" class="btn-action btn-decline"
                                            onclick="return confirm('Are you sure you want to decline this booking?')">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                </form>

                            <?php elseif ($b['status'] === 'in_progress'): ?>

                                <form method="POST" action="handle_booking.php" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" name="complete_service" class="btn-action btn-complete">
                                        <i class="fas fa-check-circle"></i> Mark Complete
                                    </button>
                                </form>

                                <?php if ($showNoShow): ?>
                                <form method="POST" action="handle_booking.php" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <button type="submit" name="mark_noshow" class="btn-action btn-noshow"
                                            onclick="return confirm('Mark this customer as No-Show?\n\nThis will cancel the booking and add a warning to their account.')">
                                        <i class="fas fa-user-slash"></i> Mark No-Show
                                    </button>
                                </form>
                                <?php endif; ?>

                            <?php elseif ($b['status'] === 'completed'): ?>
                                <div style="text-align:center;color:var(--success);padding:10px;font-weight:700;">
                                    <i class="fas fa-check-circle"></i> Service Completed
                                </div>

                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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

function toggleDetails(element) {
    element.nextElementSibling.classList.toggle('show');
}

let lastTouchEnd = 0;
document.addEventListener('touchend', function(event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) event.preventDefault();
    lastTouchEnd = now;
}, false);
</script>
</body>
</html>