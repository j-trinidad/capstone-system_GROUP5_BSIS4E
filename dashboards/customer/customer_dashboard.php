<?php // UPDATED — time-slot blocking added, all original code preserved
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';
require 'auto_cancel_bookings.php';

// Fetch parts
$stmt = $pdo->query("
    SELECT id, name, price, category
    FROM parts
    ORDER BY category, name
");
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch services and brands dynamically
$stmt = $pdo->query("SELECT id, service_key, name, COALESCE(duration_hours, 2.0) as duration_hours FROM services ORDER BY name");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build svcDurations map for JS  { serviceId: hours, serviceKey: hours }
$svcDurationsMap = [];
foreach ($services as $svc) {
    $svcDurationsMap[(int)$svc['id']]          = (float)$svc['duration_hours'];
    $svcDurationsMap[$svc['service_key']]       = (float)$svc['duration_hours'];
}
$svcDurationsJson = json_encode($svcDurationsMap);

// Build brandsData array using SERVICE_ID as key
$brandsData = [];
foreach ($services as $service) {
    $serviceKey = strtolower(trim($service['service_key']));
    $serviceId  = (int)$service['id'];

    $brandStmt = $pdo->prepare("SELECT id, name, price, coverage FROM brands WHERE service_id = ?");
    $brandStmt->execute([$serviceId]);
    $brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($brands)) continue;

    $uniqueBrands = [];
    foreach ($brands as $brand) {
        if (!isset($uniqueBrands[$brand['name']])) {
            $uniqueBrands[$brand['name']] = $brand;
        }
    }
    $brands = array_values($uniqueBrands);

    $brandsData[$serviceId] = [
        'service_key' => $serviceKey,
        'items' => array_map(function($brand) {
            $data = [
                'id'    => (int)$brand['id'],
                'name'  => $brand['name'],
                'price' => (float)$brand['price']
            ];
            if (!empty($brand['coverage'])) {
                $coverage = json_decode($brand['coverage'], true);
                if (is_array($coverage) && count($coverage) > 0) {
                    $data['coverage'] = array_values($coverage);
                }
            }
            return $data;
        }, $brands)
    ];
}

$brandsDataJson = json_encode($brandsData, JSON_NUMERIC_CHECK);

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

$hour     = date('H');
$greeting = ($hour < 12) ? 'Good Morning' : (($hour < 18) ? 'Good Afternoon' : 'Good Evening');

function getCount($pdo, $q, $id) {
    $stmt = $pdo->prepare($q);
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

$pending    = getCount($pdo, "SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = 'pending'", $user_id);
$inProgress = getCount($pdo, "SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = 'in_progress'", $user_id);
$completed  = getCount($pdo, "SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = 'completed'", $user_id);

$notifStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$notifStmt->execute([$user_id]);
$unreadNotifications = $notifStmt->fetchColumn();

$recentNotifStmt = $pdo->prepare("
    SELECT * FROM customer_notifications
    WHERE customer_id = ? AND is_read = 0
    ORDER BY created_at DESC LIMIT 3
");
$recentNotifStmt->execute([$user_id]);
$recentNotifications = $recentNotifStmt->fetchAll(PDO::FETCH_ASSOC);

$allNotifStmt = $pdo->prepare("
    SELECT * FROM customer_notifications
    WHERE customer_id = ?
    ORDER BY created_at DESC LIMIT 10
");
$allNotifStmt->execute([$user_id]);
$allNotifications = $allNotifStmt->fetchAll(PDO::FETCH_ASSOC);

$messages = getCount(
    $pdo,
    "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0",
    $user_id
);

$stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$saved_addresses = $stmt->fetchAll();

$mechanicStmt = $pdo->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.is_available,
        (
            SELECT COUNT(*)
            FROM bookings
            WHERE mechanic_id = u.id
            AND status IN ('assigned','preparing','in_progress')
        ) AS active_jobs
    FROM users u
    WHERE u.role = 'mechanic'
    AND u.is_disabled = 0
    ORDER BY u.is_available DESC, u.first_name ASC
");
$mechanicStmt->execute();
$mechanics = $mechanicStmt->fetchAll(PDO::FETCH_ASSOC);

$affectedStmt = $pdo->prepare("
    SELECT b.*,
           ma.reason as absence_reason,
           ma.start_date,
           ma.end_date,
           u.first_name as mechanic_first_name,
           u.last_name  as mechanic_last_name
    FROM bookings b
    LEFT JOIN mechanic_absences ma ON b.absence_id = ma.id
    LEFT JOIN users u ON b.mechanic_id = u.id
    WHERE b.customer_id = ?
    AND b.status = 'awaiting_customer_action'
    ORDER BY b.schedule ASC
");
$affectedStmt->execute([$user_id]);
$affectedBookings = $affectedStmt->fetchAll(PDO::FETCH_ASSOC);

// Blocked dates for the booking calendar:
// 1. Any active booking (pending/in_progress) on any date → blocked
// 2. Completed booking on any date → blocked (can't book twice same day)
// 3. Cancelled bookings → NOT blocked (can rebook)
// 4. Past dates with pending/in_progress that weren't completed → also blocked
$bookedDatesStmt = $pdo->prepare("
    SELECT DATE(schedule) as booked_date
    FROM bookings
    WHERE customer_id = ?
    AND status NOT IN ('cancelled')
    GROUP BY DATE(schedule)
");
$bookedDatesStmt->execute([$user_id]);
$bookedDatesRaw  = $bookedDatesStmt->fetchAll(PDO::FETCH_COLUMN);
$bookedDatesJson = json_encode($bookedDatesRaw);

// No-show ban info — fetch all needed columns
$banStmt = $pdo->prepare("
    SELECT no_show_count, no_show_until, no_show_last_date, no_show_month
    FROM users WHERE id = ?
");
$banStmt->execute([$user_id]);
$banInfo = $banStmt->fetch(PDO::FETCH_ASSOC);

$today        = date('Y-m-d');
$currentMonth = date('Y-m');

// Monthly reset: if stored month != current month, treat count as 0
$effectiveNoShowCount = (int)($banInfo['no_show_count'] ?? 0);
if (($banInfo['no_show_month'] ?? null) !== $currentMonth) {
    $effectiveNoShowCount = 0;
}

// 7-day ban check (3rd no-show)
$isBanned    = !empty($banInfo['no_show_until']) && $today <= $banInfo['no_show_until'];
$bannedUntil = $isBanned ? date('M d, Y', strtotime($banInfo['no_show_until'])) : '';

// Same-day disable: marked no-show today but not yet 7-day banned
$isDisabledToday = !$isBanned
    && ($banInfo['no_show_last_date'] ?? null) === $today
    && $effectiveNoShowCount < 3;

// Combined block flag
$isBookingBlocked = $isBanned || $isDisabledToday;

// Button label + title
if ($isBanned) {
    $bookingBlockedMsg   = "Booking Suspended Until {$bannedUntil}";
    $bookingBlockedTitle = "Your account is suspended until {$bannedUntil} due to repeated no-shows.";
} elseif ($isDisabledToday) {
    $bookingBlockedMsg   = "Booking Disabled Today (No-Show #{$effectiveNoShowCount}/3)";
    $bookingBlockedTitle = "You were marked as no-show today. You may book again tomorrow.";
} else {
    $bookingBlockedMsg   = '';
    $bookingBlockedTitle = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_service'])) {
    $schedule    = $_POST['schedule'];
    $bookingDate = date('Y-m-d', strtotime($schedule));
    $today       = date('Y-m-d');

    // Check ban/disable first
    if ($isBookingBlocked) {
        $_SESSION['error'] = $isBanned
            ? "Your account is suspended until {$bannedUntil} due to repeated no-shows."
            : "You were marked as no-show today. You may book again tomorrow.";
        header("Location: customer_dashboard.php"); exit();
    }

    // Block fully past dates
    if ($bookingDate < $today) {
        die("Past dates are not allowed.");
    }

    // Same-day: block slot if current time >= 1 hour before slot end
    // Slots are 2 hours, so slot end = slot start + 2 hrs
    // Cutoff = slot end - 1 hr = slot start + 1 hr
    if ($bookingDate === $today) {
        $slotStartHour   = (int)date('H', strtotime($schedule));
        $cutoffMinutes   = ($slotStartHour + 1) * 60; // slot start + 1 hr
        $nowMinutesPHP   = (int)date('H') * 60 + (int)date('i');
        if ($nowMinutesPHP >= $cutoffMinutes) {
            die("This time slot is no longer available.");
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND DATE(schedule) = ? AND status NOT IN ('cancelled','completed')");
    $stmt->execute([$user_id, $bookingDate]);
    if ($stmt->fetchColumn() > 0) {
        die("You already have a booking for this date.");
    }

    $_SESSION['booking_temp'] = $_POST;
    header("Location: confirm_booking.php");
    exit;
}

function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60)   . ' minutes ago';
    if ($diff < 86400)  return floor($diff / 3600)  . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', strtotime($timestamp));
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    overflow: hidden;
}
a { color: inherit; text-decoration: none; }
.hamburger-btn {
    display: none;
    position: fixed; top: 20px; left: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 12px;
    cursor: pointer; box-shadow: 0 4px 15px rgba(26,86,219,0.4);
    transition: all 0.3s ease;
}
.hamburger-btn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(26,86,219,0.6); }
.hamburger-btn span {
    display: block; width: 25px; height: 3px;
    background: #ffffff; margin: 5px auto;
    border-radius: 2px; transition: all 0.3s ease;
}
.hamburger-btn.active span:nth-child(1) { transform: rotate(45deg) translate(8px,8px); }
.hamburger-btn.active span:nth-child(2) { opacity: 0; }
.hamburger-btn.active span:nth-child(3) { transform: rotate(-45deg) translate(7px,-7px); }
.notification-bell {
    position: fixed; top: 20px; right: 20px; z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none; width: 50px; height: 50px; border-radius: 50%;
    cursor: pointer; box-shadow: 0 4px 15px rgba(26,86,219,0.4);
    transition: all 0.3s ease;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #ffffff;
}
.notification-bell:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(26,86,219,0.6); }
.notification-bell .bell-badge {
    position: absolute; top: -5px; right: -5px;
    background: #ff0000; color: #fff;
    width: 22px; height: 22px; border-radius: 50%;
    font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 10px rgba(255,0,0,0.8);
    animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
.notification-dropdown {
    position: fixed; top: 80px; right: 20px;
    width: 380px; max-height: 500px;
    background: #ffffff; border: 2px solid var(--border);
    border-radius: 15px; box-shadow: 0 15px 40px rgba(26,86,219,0.15);
    z-index: 1000; overflow: hidden; display: none;
    animation: slideDown 0.3s ease;
}
.notification-dropdown.show { display: block; }
.notification-dropdown-header {
    padding: 20px; border-bottom: 2px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
    background: linear-gradient(135deg, rgba(26,86,219,0.08), rgba(30,64,175,0.05));
}
.notification-dropdown-header h3 { color: var(--primary); font-size: 16px; font-weight: 700; margin: 0; }
.notification-dropdown-body { max-height: 400px; overflow-y: auto; padding: 10px; }
.notification-dropdown-body::-webkit-scrollbar { width: 6px; }
.notification-dropdown-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
.notification-dropdown-item {
    background: rgba(26,86,219,0.04); padding: 12px; border-radius: 10px;
    margin-bottom: 10px; border-left: 4px solid var(--success);
    transition: all 0.3s ease; cursor: pointer;
}
.notification-dropdown-item:hover { background: rgba(26,86,219,0.08); transform: translateX(5px); }
.notification-dropdown-item.unread { border-left-color: var(--primary); background: rgba(26,86,219,0.08); }
.notification-dropdown-item.declined { border-left-color: var(--error); background: rgba(220,38,38,0.08); }
.notification-dropdown-item .notif-title { color: var(--primary); font-weight: 700; font-size: 13px; margin-bottom: 5px; }
.notification-dropdown-item .notif-message { color: var(--text-secondary); font-size: 12px; line-height: 1.4; }
.notification-dropdown-item .notif-time { color: #666; font-size: 11px; margin-top: 5px; }
.notification-dropdown-footer { padding: 12px; border-top: 1px solid var(--border); text-align: center; }
.notification-dropdown-footer a { color: var(--success); font-weight: 700; font-size: 12px; transition: 0.3s ease; }
.notification-dropdown-footer a:hover { color: var(--primary); }
.no-notifications { text-align: center; padding: 40px 20px; color: var(--text-secondary); font-size: 13px; }
.no-notifications i { font-size: 40px; margin-bottom: 15px; opacity: 0.5; }
.sidebar-overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(26,86,219,0.1); z-index: 999;
    opacity: 0; transition: opacity 0.3s ease;
}
.sidebar-overlay.active { display: block; opacity: 1; }
.sidebar {
    position: fixed; top: 0; left: 0;
    width: 260px; height: 100vh; overflow-y: auto;
    background: linear-gradient(180deg, #1e3a8a 0%, #1a56db 100%);
    border-right: 1px solid rgba(255,255,255,0.1);
    display: flex; flex-direction: column;
    padding: 25px 20px; z-index: 1000;
    transition: transform 0.3s ease; max-height: 100vh;
}
.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }
.logo {
    font-size: 1.6rem; font-weight: 700;
    color: #ffffff;
    margin-bottom: 35px; letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 10px;
}
.sidebar nav { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.sidebar nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 10px;
    color: rgba(255,255,255,0.75); font-weight: 600;
    transition: all 0.3s ease; font-size: 14px; position: relative;
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
    color: #ffffff !important;
    margin-top: auto !important; justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }
.main {
    margin-left: 260px; padding: 40px;
    width: calc(100% - 260px); height: 100vh; overflow-y: auto;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
}
.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
.header {
    font-size: 28px; font-weight: 700; margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; display: flex; align-items: center; gap: 15px;
}
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card {
    background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px;
    padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26,86,219,0.08);
}
.stat-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 12px 40px rgba(26,86,219,0.2); }
.stat-title { color: var(--text-secondary); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 10px; }
.stat-number { font-size: 32px; font-weight: 700; background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.panel {
    background: var(--card-bg); border: 1px solid var(--border);
    border-radius: 12px; padding: 25px; margin-bottom: 25px;
    transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(26,86,219,0.08);
}
.panel:hover { border-color: var(--primary); box-shadow: 0 12px 40px rgba(26,86,219,0.15); transform: translateY(-3px); }
.panel h3 { color: var(--primary); font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
.panel ul { list-style: none; padding-left: 0; }
.panel li { padding: 10px 0; border-bottom: 1px solid var(--border); color: var(--text-secondary); display: flex; align-items: center; gap: 10px; transition: 0.3s ease; }
.panel li:last-child { border-bottom: none; }
.panel li:hover { color: var(--primary); transform: translateX(5px); }
.btn-book {
    padding: 14px 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; border: none; border-radius: 10px;
    cursor: pointer; font-weight: 700; transition: all 0.3s ease;
    font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    box-shadow: 0 4px 15px rgba(26,86,219,0.2);
    margin: 20px auto 25px auto; max-width: 300px; width: 100%;
}
.btn-book:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(26,86,219,0.25); }
/* ✅ Disabled book button style for banned accounts */
.btn-book:disabled {
    background: linear-gradient(135deg, #555, #333);
    color: rgba(255,255,255,0.4);
    cursor: not-allowed;
    box-shadow: none;
    opacity: 0.6;
}
.notification-panel {
    background: linear-gradient(135deg, rgba(0,208,132,0.15), rgba(32,201,151,0.1));
    border: 2px solid rgba(0,208,132,0.4);
    padding: 25px; border-radius: 15px; margin-bottom: 25px;
    animation: slideDown 0.5s ease;
}
.notification-header {
    color: var(--success); font-size: 18px; margin-bottom: 20px;
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; font-weight: 700; flex-wrap: wrap;
}
.clear-recent-btn {
    background: linear-gradient(135deg, #ff4444, #c82333);
    border: none; color: #fff; padding: 8px 16px; border-radius: 8px;
    font-size: 12px; font-weight: 700; cursor: pointer;
    transition: all 0.3s ease; text-transform: uppercase;
    display: flex; align-items: center; gap: 6px;
    box-shadow: 0 3px 10px rgba(255,68,68,0.3);
}
.clear-recent-btn:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(255,68,68,0.5); }
.notification-item {
    background: rgba(26,111,196,0.05); padding: 15px; border-radius: 10px;
    margin-bottom: 12px; border-left: 4px solid var(--success);
    transition: all 0.3s ease; cursor: pointer;
}
.notification-item:hover { background: rgba(26,111,196,0.1); transform: translateX(5px); box-shadow: 0 5px 15px rgba(26,111,196,0.15); }
.notification-item.unread { border-left-color: var(--primary); background: rgba(26,111,196,0.08); }
.notification-item.declined { border-left-color: var(--error); background: rgba(230,57,70,0.06); }
.notification-title { color: var(--primary); font-weight: 700; font-size: 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.notification-message { color: var(--text-secondary); font-size: 13px; line-height: 1.5; }
.notification-time { color: #8899aa; font-size: 12px; margin-top: 8px; }
.affected-warning-panel {
    background: linear-gradient(135deg, rgba(255,69,0,0.25), rgba(255,0,0,0.15)) !important;
    border: 3px solid rgba(255,69,0,0.6) !important;
    animation: pulseWarning 2s infinite; margin-bottom: 25px !important;
}
@keyframes pulseWarning {
    0%,100% { box-shadow: 0 5px 15px rgba(255,69,0,0.3); border-color: rgba(255,69,0,0.5); }
    50%      { box-shadow: 0 10px 30px rgba(255,69,0,0.6); border-color: rgba(255,69,0,0.8); }
}
.affected-booking-card {
    border: 2px solid #ff6b6b; padding: 15px; margin: 15px 0;
    border-radius: 12px; background: rgba(26,86,219,0.06); transition: all 0.3s ease;
}
.affected-booking-card:hover { background: rgba(26,86,219,0.09); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(255,69,0,0.4); }
.mechanic-unavailable-badge {
    display: inline-block;
    background: linear-gradient(45deg, #ff4444, #c82333);
    color: #fff; padding: 4px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 700; margin-left: 10px;
    box-shadow: 0 2px 8px rgba(255,68,68,0.4);
}
.button-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
.reassign-btn, .cancel-btn {
    padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
    font-weight: 700; transition: 0.3s ease; box-shadow: 0 3px 10px rgba(26,86,219,0.05);
    font-size: 12px; text-transform: uppercase;
    display: inline-flex; align-items: center; gap: 8px;
}
.reassign-btn { background: linear-gradient(45deg, #28a745, #20c997); color: #fff; }
.reassign-btn:hover { transform: scale(1.05) translateY(-2px); box-shadow: 0 5px 15px rgba(40,167,69,0.5); }
.cancel-btn { background: linear-gradient(45deg, #dc3545, #c82333); color: #fff; }
.cancel-btn:hover { transform: scale(1.05) translateY(-2px); box-shadow: 0 5px 15px rgba(220,53,69,0.5); }
.message {
    padding: 15px 20px; border-radius: 10px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 12px;
    animation: slideDown 0.3s ease; font-weight: 600; font-size: 14px;
}
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp   { from{opacity:1;transform:translateY(0)} to{opacity:0;transform:translateY(-20px)} }
.message.success { background: rgba(0,208,132,0.15); border: 1px solid var(--success); color: var(--success); }
.message.error   { background: rgba(220,38,38,0.08);  border: 1px solid var(--error);   color: var(--error);   }
.modal {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15,23,80,0.65);
    justify-content: center; align-items: center;
    z-index: 9999; animation: modalFadeIn 0.3s ease;
}
@keyframes modalFadeIn { from{opacity:0} to{opacity:1} }
.modal.show { display: flex; }
.modal-content {
    background: var(--card-bg); padding: 30px; border-radius: 15px;
    width: 95%; max-width: 700px; max-height: 85vh; overflow-y: auto;
    box-shadow: 0 15px 40px rgba(26,86,219,0.15), 0 0 30px rgba(26,86,219,0.2);
    border: 1px solid var(--border); animation: modalSlideIn 0.4s ease; position: relative;
}
@keyframes modalSlideIn { from{transform:scale(0.9) translateY(-20px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
.close-btn {
    position: absolute; top: 15px; right: 15px;
    background: linear-gradient(45deg, #ff4444, #e52e71);
    border: none; color: #fff; width: 35px; height: 35px; border-radius: 50%;
    cursor: pointer; transition: 0.3s ease; box-shadow: 0 3px 10px rgba(255,68,68,0.3);
    font-size: 20px; display: flex; align-items: center; justify-content: center; z-index: 10;
}
.close-btn:hover { transform: scale(1.1); box-shadow: 0 5px 15px rgba(255,68,68,0.5); }
.modal-content h3 { color: var(--primary); font-size: 22px; margin-bottom: 20px; padding-right: 50px; }
.modal-content form label {
    display: block; margin-top: 15px; color: var(--primary);
    font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;
}
.modal-content form select,
.modal-content form input[type="date"],
.modal-content form input,
.modal-content form textarea {
    width: 100%; padding: 12px; border-radius: 10px; border: 1px solid var(--border);
    margin-top: 8px; background: #ffffff; color: var(--text-primary);
    box-sizing: border-box; transition: 0.3s ease; font-size: 13px; font-family: 'Outfit', sans-serif;
}
.modal-content form select:focus,
.modal-content form input:focus,
.modal-content form textarea:focus {
    border-color: var(--primary); box-shadow: 0 0 15px rgba(26,86,219,0.2);
    outline: none; background: rgba(26,86,219,0.08);
}
.modal-content form select option { background: #ffffff; color: var(--text-primary); }
.modal-content form button {
    margin-top: 20px; padding: 12px 25px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; border: none; border-radius: 10px; cursor: pointer;
    font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    transition: 0.3s ease; box-shadow: 0 4px 15px rgba(26,86,219,0.2);
}
.modal-content form button:hover { transform: scale(1.05) translateY(-2px); box-shadow: 0 6px 20px rgba(26,86,219,0.3); }
.modal-content form button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.brand-options, .location-options {
    display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;
}
.brand-option, .location-option {
    display: flex; align-items: center; cursor: pointer;
    padding: 10px 15px; border: 2px solid var(--border);
    border-radius: 10px; background: #ffffff;
    transition: 0.3s ease; font-weight: 500; font-size: 13px;
    flex: 1; min-width: 120px; justify-content: center; text-align: center;
}
.brand-option:hover, .brand-option.selected,
.location-option:hover, .location-option.selected {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; border-color: var(--primary); box-shadow: 0 0 15px rgba(26,86,219,0.25);
}
.brand-option input[type="radio"],
.location-option input[type="radio"] { display: none; }
.address-fields { display: none; }
.brands-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 8px; }
.brand-btn {
    padding: 12px 15px; border: 3px solid var(--border); border-radius: 10px;
    background: #ffffff; color: var(--text-primary);
    cursor: pointer; transition: all 0.3s ease;
    font-weight: 600; font-size: 13px; text-align: center;
    white-space: normal; word-wrap: break-word; min-height: 50px;
    display: flex; align-items: center; justify-content: center; position: relative;
}
.brand-btn:hover { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #ffffff; border-color: var(--primary); box-shadow: 0 0 15px rgba(26,86,219,0.25); transform: translateY(-2px); }
.brand-btn.selected {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; border-color: var(--primary);
    box-shadow: 0 0 20px rgba(26,86,219,0.35), inset 0 0 10px rgba(255,255,255,0.4);
    font-weight: 700; transform: scale(1.05);
}
.brand-btn.selected::after {
    content: '✓'; position: absolute; top: 5px; right: 8px;
    background: #ffffff; color: var(--primary); width: 22px; height: 22px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px;
}
.parts-container {
    margin-top: 8px; max-height: 180px; overflow-y: auto;
    border: 1px solid var(--border); padding: 12px; border-radius: 10px;
    background: #ffffff;
}
.parts-container label {
    display: flex; align-items: center; margin: 8px 0;
    color: var(--text-primary); cursor: pointer; padding: 8px;
    border-radius: 8px; transition: 0.3s ease; font-weight: 500; font-size: 13px;
}
.parts-container label:hover { background: rgba(26,86,219,0.08); }
.parts-container input[type="checkbox"] { margin-right: 12px; accent-color: var(--primary); }
#price-display-container {
    display: none; margin-top: 15px; padding: 15px;
    background: rgba(26,86,219,0.08); border-radius: 10px; border: 1px solid var(--border);
}
#coverage-list {
    margin-top: 15px; padding: 15px; background: rgba(26,86,219,0.05);
    border-radius: 10px; border: 1px solid var(--border); display: none;
}
#coverage-list h4 { color: var(--primary); margin-bottom: 10px; font-size: 15px; }
#coverage-list ul { list-style: disc; padding-left: 20px; color: var(--text-secondary); margin: 0; }
#coverage-list li { margin: 8px 0; font-size: 13px; }
.form-error {
    display: none; background: rgba(220,38,38,0.08);
    border: 1px solid var(--error); color: var(--error);
    padding: 12px; border-radius: 8px; margin-top: 10px;
    font-size: 12px; font-weight: 600; animation: slideDown 0.3s ease;
}
.form-error.show { display: flex; align-items: center; gap: 8px; }
.booking-steps {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 22px; font-size: 12px; font-weight: 700;
    color: var(--text-secondary);
}
.step-circle {
    border-radius: 50%; width: 26px; height: 26px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0; transition: all 0.3s;
}
.step-circle.active   { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #ffffff; }
.step-circle.inactive { background: var(--border); color: var(--text-secondary); }
.step-divider { flex: 1; height: 2px; background: var(--border); border-radius: 2px; }
.step-label { transition: color 0.3s; }
.step-label.active   { color: var(--primary); }
.step-label.inactive { color: var(--text-secondary); }
.schedule-hint {
    color: var(--text-secondary); font-size: 12px; margin-top: 12px;
    padding: 10px 14px; background: rgba(26,86,219,0.05);
    border-radius: 8px; border-left: 3px solid var(--primary);
}
.time-slot-card {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border: 2px solid var(--border); border-radius: 12px;
    background: #ffffff; color: var(--text-primary);
    cursor: pointer; transition: all 0.25s ease; width: 100%; text-align: left; font-family: inherit;
}
.time-slot-card:hover:not(.slot-blocked) {
    border-color: var(--primary); background: rgba(26,86,219,0.08);
    transform: translateY(-2px); box-shadow: 0 4px 15px rgba(26,86,219,0.12);
}
.time-slot-card.slot-selected {
    background: linear-gradient(135deg, rgba(26,86,219,0.12), rgba(30,64,175,0.12));
    border-color: var(--primary); box-shadow: 0 0 18px rgba(26,86,219,0.25);
}
.time-slot-card.slot-blocked {
    background: rgba(220,38,38,0.04); border-color: rgba(220,38,38,0.12);
    cursor: not-allowed; opacity: 0.5;
}
.slot-card-left { display: flex; align-items: center; gap: 12px; }
.slot-card-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: #eef2ff; border: 2px solid #c7d4f5;
    display: flex; align-items: center; justify-content: center;
    color: var(--primary); font-size: 14px; flex-shrink: 0;
}
.slot-blocked .slot-card-icon { background: rgba(220,38,38,0.06); border-color: rgba(220,38,38,0.18); color: var(--error); }
.slot-card-info { display: flex; flex-direction: column; gap: 2px; }
.slot-card-time { font-size: 15px; font-weight: 700; color: var(--text-primary); }
.slot-blocked .slot-card-time { color: rgba(0,0,0,0.3); }
.slot-card-status { font-size: 12px; font-weight: 600; color: var(--success); }
.slot-blocked .slot-card-status { color: var(--error); }
.slot-card-right {
    font-size: 11px; font-weight: 700; color: var(--text-secondary);
    background: #e8edf5; padding: 4px 10px;
    border-radius: 20px; letter-spacing: 0.5px; flex-shrink: 0;
}
.slot-selected .slot-card-right { color: var(--primary); background: rgba(26,86,219,0.10); }
/* ── Flatpickr dark theme overrides ────────────────────────────────────── */
.flatpickr-calendar {
    background: var(--card-bg) !important;
    border: 1px solid var(--border) !important;
    border-radius: 12px !important;
    box-shadow: 0 15px 40px rgba(26,86,219,0.1) !important;
    font-family: 'Outfit', sans-serif !important;
}
.flatpickr-months, .flatpickr-weekdays {
    background: rgba(26,86,219,0.08) !important;
    border-radius: 12px 12px 0 0 !important;
}
.flatpickr-month { color: var(--primary) !important; }
.flatpickr-current-month .cur-month,
.flatpickr-current-month input.cur-year { color: var(--primary) !important; font-weight: 700 !important; }
.flatpickr-weekday { color: var(--text-secondary) !important; font-weight: 600 !important; }
.flatpickr-day { color: var(--text-primary) !important; border-radius: 8px !important; }
.flatpickr-day:hover:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay) {
    background: rgba(26,86,219,0.12) !important;
    border-color: var(--primary) !important;
}
.flatpickr-day.selected, .flatpickr-day.selected:hover {
    background: linear-gradient(135deg, var(--primary), var(--secondary)) !important;
    border-color: var(--primary) !important;
    color: #ffffff !important;
    font-weight: 700 !important;
}
.flatpickr-day.flatpickr-disabled,
.flatpickr-day.flatpickr-disabled:hover {
    color: rgba(0,0,0,0.2) !important;
    background: rgba(220,38,38,0.04) !important;
    border-color: transparent !important;
    cursor: not-allowed !important;
    text-decoration: line-through !important;
}
.flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: rgba(0,0,0,0.25) !important; }
.flatpickr-prev-month svg, .flatpickr-next-month svg { fill: var(--primary) !important; }
.flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: var(--secondary) !important; }
/* fake input style to match other inputs */
.flatpickr-input {
    background: #ffffff !important;
    border: 1px solid var(--border) !important;
    border-radius: 10px !important;
    color: var(--text-primary) !important;
    padding: 12px !important;
    font-family: 'Outfit', sans-serif !important;
    font-size: 14px !important;
    width: 100% !important;
    cursor: pointer !important;
    transition: border-color 0.3s ease !important;
}
.flatpickr-input:focus { border-color: var(--primary) !important; outline: none !important; }
.flatpickr-input.banned-input { opacity: 0.4 !important; cursor: not-allowed !important; border-color: var(--error) !important; pointer-events: none !important; }
/* ── End Flatpickr overrides ─────────────────────────────────────────────── */
@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .notification-dropdown { width: calc(100% - 40px); right: 20px; left: 20px; top: 80px; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }
    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }
    .header { font-size: 20px; margin-top: 20px; }
    .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .stat-card { padding: 12px 8px; }
    .stat-title { font-size: 10px; letter-spacing: 0; margin-bottom: 6px; }
    .stat-number { font-size: 22px; }
    .panel { padding: 20px; }
    .btn-book { width: 100%; justify-content: center; }
    .modal-content { width: 95%; max-height: 90vh; padding: 25px 20px; margin: 20px 10px; }
    .modal-content h3 { font-size: 18px; padding-right: 45px; }
    .close-btn { top: 10px; right: 10px; width: 32px; height: 32px; font-size: 18px; }
    .brand-options { flex-direction: row; flex-wrap: wrap; gap: 6px; }
    .brand-option { flex: 1 1 calc(50% - 6px); min-width: unset; padding: 7px 8px; font-size: 11px; }
    .brands-container { grid-template-columns: repeat(2, 1fr); gap: 6px; }
    .brand-btn { min-height: 40px; padding: 8px 6px; font-size: 11px; }
    .brand-btn.selected::after { width: 16px; height: 16px; font-size: 10px; top: 3px; right: 4px; }
    .location-options { flex-direction: row; gap: 6px; }
    .location-option { flex: 1; min-width: unset; padding: 7px 8px; font-size: 11px; }
    .time-slot-card { padding: 10px 12px; }
    .slot-card-time { font-size: 13px; }
    .slot-card-status { font-size: 11px; }
    .slot-card-icon { width: 30px; height: 30px; font-size: 12px; }
    .slot-card-right { font-size: 10px; padding: 3px 8px; }
    .button-group { flex-direction: column; }
    .reassign-btn, .cancel-btn { width: 100%; justify-content: center; }
    .modal-content form label { margin-top: 12px; font-size: 12px; }
    .modal-content form select,
    .modal-content form input,
    .modal-content form textarea { padding: 10px; font-size: 12px; }
    .modal-content form button { width: 100%; padding: 14px; }
    .parts-container { max-height: 150px; padding: 10px; }
    .parts-container label { font-size: 12px; padding: 6px; }
    #coverage-list { padding: 12px; }
    #coverage-list h4 { font-size: 14px; }
    #coverage-list li { font-size: 12px; }
    #time-slot-grid { grid-template-columns: repeat(3, 1fr) !important; }
}
</style>
</head>
<body>

<script>
window.brandsDataFromPHP  = <?= $brandsDataJson ?>;
window.bookedDates        = <?= $bookedDatesJson ?>;
window.svcDurations       = <?= $svcDurationsJson ?>;
window.isBanned           = <?= $isBanned ? 'true' : 'false' ?>;
window.isDisabledToday    = <?= $isDisabledToday ? 'true' : 'false' ?>;
window.isBookingBlocked   = <?= $isBookingBlocked ? 'true' : 'false' ?>;
window.bannedUntil        = "<?= htmlspecialchars($bannedUntil) ?>";
window.noShowCount        = <?= $effectiveNoShowCount ?>;
</script>

<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<button class="notification-bell" id="notification-bell">
    <i class="fas fa-bell"></i>
    <?php if ($unreadNotifications > 0): ?>
        <span class="bell-badge"><?= $unreadNotifications ?></span>
    <?php endif; ?>
</button>

<div class="notification-dropdown" id="notification-dropdown">
    <div class="notification-dropdown-header">
        <h3><i class="fas fa-bell"></i> Notifications</h3>
    </div>
    <div class="notification-dropdown-body" id="notification-body">
        <?php if (!empty($allNotifications)): ?>
            <?php foreach ($allNotifications as $notif): ?>
                <div class="notification-dropdown-item
                     <?= $notif['is_read'] == 0 ? 'unread' : '' ?>
                     <?= $notif['type'] == 'booking_declined' ? 'declined' : '' ?>"
                     data-notif-id="<?= $notif['id'] ?>"
                     data-booking-id="<?= $notif['booking_id'] ?>"
                     onclick="markAsRead(<?= $notif['id'] ?>, <?= $notif['booking_id'] ?>)">
                    <div class="notif-title"><i class="fas fa-bell"></i> <?= htmlspecialchars($notif['title']) ?></div>
                    <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                    <div class="notif-time"><i class="fas fa-clock"></i> <?= timeAgo($notif['created_at']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-notifications">
                <i class="fas fa-bell-slash"></i><p>No notifications yet</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="notification-dropdown-footer">
        <a href="customer_notifications.php">View All Notifications →</a>
    </div>
</div>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-tools"></i><span>MotorService</span></div>
    <nav>
        <a href="customer_dashboard.php" <?= $currentPage==='customer_dashboard.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-home"></i> Home
        </a>
        <a href="customer_my_bookings.php" <?= $currentPage==='customer_my_bookings.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-calendar-alt"></i> My Bookings
        </a>
        <a href="customer_history.php" <?= $currentPage==='customer_history.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-history"></i> History
        </a>
        <a href="customer_messages.php" <?= $currentPage==='customer_messages.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <!-- ✅ MY RECEIPTS — BAGO -->
        <a href="customer_my_receipts.php" <?= $currentPage==='customer_my_receipts.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-receipt"></i> My Receipts
        </a>
        <a href="customer_address.php" <?= $currentPage==='customer_address.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-map-marker-alt"></i> Address
        </a>
        <a href="customer_profile.php" <?= $currentPage==='customer_profile.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="customer_change_password.php" <?= $currentPage==='customer_change_password.php' ? 'class="active"' : '' ?>>
            <i class="fas fa-lock"></i> Change Password
        </a>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<main class="main">
    <div class="header">
        <i class="fas fa-wave-square"></i>
        <?= $greeting ?>, <?= htmlspecialchars($user_name) ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="message success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($_SESSION['success']) ?></span></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($_SESSION['error']) ?></span></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='customer_my_bookings.php?filter=pending'">
            <div class="stat-title">⏳ Pending</div>
            <div class="stat-number"><?= $pending ?></div>
        </div>
        <div class="stat-card" onclick="location.href='customer_my_bookings.php?filter=in_progress'">
            <div class="stat-title">⚙️ In Progress</div>
            <div class="stat-number"><?= $inProgress ?></div>
        </div>
        <div class="stat-card" onclick="location.href='customer_history.php'">
            <div class="stat-title">✅ Completed</div>
            <div class="stat-number"><?= $completed ?></div>
        </div>
    </div>

    <?php if ($isBanned): ?>
    <div class="panel" style="background:linear-gradient(135deg,rgba(220,38,38,0.12),rgba(220,38,38,0.06));border:2px solid rgba(220,38,38,0.3);animation:pulseWarning 2s infinite;">
        <h3 style="color:var(--error);"><i class="fas fa-ban"></i> Account Suspended (7 Days)</h3>
        <p style="color:var(--text-primary);font-size:14px;margin-top:10px;">
            Your account is suspended due to 3 no-shows this month.<br>
            You can book again on <strong style="color:var(--error);"><?= $bannedUntil ?></strong>.
        </p>
    </div>

    <?php elseif ($isDisabledToday): ?>
    <div class="panel" style="background:linear-gradient(135deg,rgba(220,38,38,0.08),rgba(220,38,38,0.06));border:2px solid rgba(220,38,38,0.3);animation:pulseWarning 2s infinite;">
        <h3 style="color:var(--error);"><i class="fas fa-user-slash"></i> Booking Disabled Today — No-Show (<?= $effectiveNoShowCount ?>/3)</h3>
        <p style="color:var(--text-primary);font-size:14px;margin-top:10px;">
            You were marked as no-show today. Booking is disabled for the rest of today.<br>
            You may book again <strong>tomorrow</strong>.
            <?php if ($effectiveNoShowCount >= 2): ?>
                <br><strong style="color:var(--error);">⚠️ One more no-show this month = 7-day suspension!</strong>
            <?php endif; ?>
        </p>
    </div>

    <?php elseif ($effectiveNoShowCount === 1): ?>
    <div class="panel" style="background:linear-gradient(135deg,rgba(217,119,6,0.08),rgba(217,119,6,0.05));border:2px solid rgba(217,119,6,0.3);">
        <h3 style="color:var(--warning);"><i class="fas fa-exclamation-triangle"></i> No-Show Warning (1/3)</h3>
        <p style="color:var(--text-primary);font-size:14px;margin-top:10px;">
            You have 1 no-show this month. 2 more no-shows will result in a 7-day suspension.
        </p>
    </div>

    <?php elseif ($effectiveNoShowCount === 2): ?>
    <div class="panel" style="background:linear-gradient(135deg,rgba(220,38,38,0.08),rgba(220,38,38,0.06));border:2px solid rgba(220,38,38,0.25);">
        <h3 style="color:var(--error);"><i class="fas fa-exclamation-circle"></i> Final Warning (2/3)</h3>
        <p style="color:var(--text-primary);font-size:14px;margin-top:10px;">
            You have 2 no-shows this month. One more no-show will result in a <strong style="color:var(--error);">7-day suspension</strong>.
        </p>
    </div>
    <?php endif; ?>

    <?php if (!empty($recentNotifications)): ?>
    <div class="notification-panel" id="recent-notifications-panel">
        <div class="notification-header">
            <div style="display:flex;align-items:center;gap:10px;"><i class="fas fa-bell"></i> 📢 Recent Notifications</div>
            <button class="clear-recent-btn" id="clear-recent-notif"><i class="fas fa-trash-alt"></i> Clear</button>
        </div>
        <?php foreach ($recentNotifications as $notif): ?>
            <div class="notification-item <?= $notif['is_read']==0?'unread':'' ?> <?= $notif['type']=='booking_declined'?'declined':'' ?>"
                 onclick="markAsRead(<?= $notif['id'] ?>, <?= $notif['booking_id'] ?>)">
                <div class="notification-title"><i class="fas fa-bell"></i> <?= htmlspecialchars($notif['title']) ?></div>
                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="notification-time"><i class="fas fa-clock"></i> <?= timeAgo($notif['created_at']) ?></div>
            </div>
        <?php endforeach; ?>
        <p style="text-align:center;margin-top:20px;color:var(--text-secondary);font-size:13px;">
            <a href="customer_notifications.php" style="color:var(--success);font-weight:700;">View All Notifications →</a>
        </p>
    </div>
    <?php endif; ?>

    <?php if (!empty($affectedBookings)): ?>
    <div class="panel affected-warning-panel" id="affected-bookings-panel">
        <h3 style="color:#ff4444;font-size:22px;margin-bottom:15px;">
            <i class="fas fa-exclamation-triangle"></i> ⚠️ URGENT: Action Required!
        </h3>
        <p style="color:var(--text-primary);font-size:15px;margin-bottom:20px;font-weight:600;">
            Your assigned mechanic is unavailable. Please take immediate action on your booking(s):
        </p>
        <?php foreach ($affectedBookings as $booking): ?>
            <div class="affected-booking-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:10px;">
                    <h5 style="color:var(--primary);font-size:16px;margin:0;">Booking #<?= $booking['id'] ?></h5>
                    <span class="mechanic-unavailable-badge">MECHANIC UNAVAILABLE</span>
                </div>
                <p style="margin:8px 0;color:var(--text-secondary);font-size:13px;">
                    <strong style="color:var(--primary);">Mechanic:</strong>
                    <span style="color:var(--error);font-weight:600;"><?= htmlspecialchars($booking['mechanic_first_name'].' '.$booking['mechanic_last_name']) ?></span>
                </p>
                <p style="margin:8px 0;color:var(--text-secondary);font-size:13px;">
                    <strong style="color:var(--primary);">Reason:</strong> <?= htmlspecialchars($booking['absence_reason']??'Not specified') ?>
                </p>
                <p style="margin:8px 0;color:var(--text-secondary);font-size:13px;">
                    <strong style="color:var(--primary);">Service:</strong> <?= htmlspecialchars($booking['service_type']) ?>
                </p>
                <p style="margin:8px 0;color:var(--text-secondary);font-size:13px;">
                    <strong style="color:var(--primary);">Scheduled Date:</strong> <?= date('M d, Y h:i A',strtotime($booking['schedule'])) ?>
                </p>
                <?php if ($booking['start_date'] && $booking['end_date']): ?>
                <p style="margin:8px 0;color:var(--error);font-size:12px;font-weight:600;">
                    <i class="fas fa-calendar-times"></i>
                    Mechanic unavailable from <?= date('M d',strtotime($booking['start_date'])) ?> to <?= date('M d, Y',strtotime($booking['end_date'])) ?>
                </p>
                <?php endif; ?>
                <div class="button-group">
                    <form method="POST" action="handle_booking_action.php" style="display:inline;">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <button type="submit" name="action" value="reassign" class="reassign-btn">
                            <i class="fas fa-user-check"></i> Reassign Mechanic
                        </button>
                    </form>
                    <form method="POST" action="handle_booking_action.php" style="display:inline;">
                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                        <button type="submit" name="action" value="cancel" class="cancel-btn"
                                onclick="return confirm('Are you sure you want to cancel this booking?')">
                            <i class="fas fa-times-circle"></i> Cancel Booking
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <p style="color:var(--text-secondary);font-size:12px;margin-top:20px;text-align:center;font-style:italic;">
            <i class="fas fa-info-circle"></i> We sincerely apologize for the inconvenience.
        </p>
    </div>
    <?php endif; ?>

    <button id="book-now-btn" class="btn-book"
        <?= $isBookingBlocked ? 'disabled title="' . htmlspecialchars($bookingBlockedTitle) . '"' : '' ?>>
        <?php if ($isBookingBlocked): ?>
            <i class="fas fa-ban"></i> <?= htmlspecialchars($bookingBlockedMsg) ?>
        <?php else: ?>
            <i class="fas fa-calendar-plus"></i> Book New Service
        <?php endif; ?>
    </button>

    <div class="panel">
        <h3><i class="fas fa-lightbulb"></i> 💡 Quick Maintenance Tips</h3>
        <ul>
            <li><i class="fas fa-check"></i> Check tire pressure monthly for safety and fuel efficiency.</li>
            <li><i class="fas fa-check"></i> Change engine oil every 3,000-5,000 km for optimal performance.</li>
            <li><i class="fas fa-check"></i> Inspect brakes regularly to prevent accidents.</li>
            <li><i class="fas fa-check"></i> Clean air filters to improve engine breathing and power.</li>
        </ul>
    </div>

    <div class="panel" id="about-us-panel">
        <h3><i class="fas fa-info-circle"></i> About MotorService</h3>
        <p style="color:var(--text-secondary);margin:0;font-size:13px;">
            Learn more about our mission, services, and commitment to excellence. Click here to explore!
        </p>
    </div>
</main>

<div id="about-us-modal" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <button id="close-about-modal" class="close-btn">&times;</button>
        <h3 style="margin-right:40px;font-size:28px;">About MotorService</h3>
        <p style="color:var(--text-secondary);line-height:1.6;font-size:14px;">
            Welcome to MotorService, your trusted partner for all motorcycle maintenance and repair needs.
        </p>
        <h4 style="color:var(--primary);font-size:18px;margin-top:25px;margin-bottom:15px;">🎯 Our Mission</h4>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:20px;">
            To revolutionize motorcycle servicing by offering convenient, transparent, and high-quality solutions.
        </p>
        <h4 style="color:var(--primary);font-size:18px;margin-top:25px;margin-bottom:15px;">🛠️ Our Services</h4>
        <ul style="color:var(--text-secondary);padding-left:20px;font-size:13px;margin-bottom:20px;">
            <li>Oil Changes and Filter Replacements</li>
            <li>Brake Inspections</li>
            <li>Tire Changes and Balancing</li>
            <li>Engine Tune-Ups</li>
            <li>Battery Replacements</li>
            <li>Chain Maintenance</li>
            <li>Spark Plug Replacements</li>
            <li>Air Filter Changes</li>
        </ul>
        <p style="color:var(--text-secondary);font-size:13px;margin-top:30px;padding-top:20px;
                  border-top:1px solid var(--border);text-align:center;">
            Thank you for choosing MotorService. Ride safe, ride smart! 🏍️
        </p>
    </div>
</div>

<div id="booking-modal" class="modal">
    <div class="modal-content">
        <button id="close-modal" class="close-btn">&times;</button>
        <h3>📅 Book Service</h3>

        <div class="booking-steps">
            <span class="step-circle active"  id="step1-circle">1</span>
            <span class="step-label active">Pick Schedule</span>
            <span class="step-divider"></span>
            <span class="step-circle inactive" id="step2-circle">2</span>
            <span class="step-label inactive" id="step2-label">Fill in Details</span>
        </div>

        <form method="POST" id="booking-form">

            <label>
                Date: <span style="color:var(--error);">*</span>
                <small style="color:var(--text-secondary);font-size:11px;text-transform:none;font-weight:400;">
                    — Choose your preferred service date
                </small>
            </label>
            <input type="text" id="booking_date" name="booking_date"
                   placeholder="Select a date..." readonly required
                   autocomplete="off">

            <div class="form-error" id="past-date-error">
                <i class="fas fa-exclamation-circle"></i>
                <span>Past bookings are not allowed.</span>
            </div>
            <div class="form-error" id="date-already-booked-error">
                <i class="fas fa-calendar-times"></i>
                <span>You already have a booking on this date. Please choose a different day.</span>
            </div>

            <div id="mechanic-step-wrapper" style="display:none;">
                <label>Choose Mechanic: <span style="color:var(--error);">*</span>
                    <small style="color:var(--text-secondary);font-size:11px;text-transform:none;font-weight:400;">
                        — Time slots will load after selecting a mechanic
                    </small>
                </label>
                <select name="mechanic" id="mechanic-select" required>
                    <option value="" disabled selected>Select Mechanic</option>
                    <?php foreach($mechanics as $m): ?>
                        <?php if($m['active_jobs'] > 0 || $m['is_available'] == 0): ?>
                            <option value="<?= $m['id'] ?>" disabled>
                                <?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?>
                                <?= $m['is_available']==0 ? '(UNAVAILABLE)' : '(BUSY)' ?>
                            </option>
                        <?php else: ?>
                            <option value="<?= $m['id'] ?>">
                                <?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?> (AVAILABLE)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="time-slot-wrapper" style="display:none;margin-top:15px;">
                <label>
                    SELECT TIME SLOT: <span style="color:var(--error);">*</span>
                    <small style="color:var(--text-secondary);font-size:11px;text-transform:none;font-weight:400;">
                        — Each slot is 2 hours of service
                    </small>
                </label>
                <div id="time-slot-loading" style="display:none;color:var(--text-secondary);font-size:13px;padding:10px 0;">
                    <i class="fas fa-spinner fa-spin"></i> Loading available slots...
                </div>
                <div id="time-slot-grid" style="display:flex;flex-direction:column;gap:10px;margin-top:10px;">
                </div>
                <div class="form-error" id="time-slot-error">
                    <i class="fas fa-clock"></i>
                    <span>Please select a time slot.</span>
                </div>
            </div>

            <input type="hidden" name="schedule" id="schedule" required>

            <p class="schedule-hint" id="schedule-hint-msg">
                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                Pick a date first, then select a mechanic to see available time slots.
            </p>

            <div id="booking-details-section" style="display:none;">
                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

                <label>Brand: <span style="color:var(--error);">*</span></label>
                <div class="brand-options" id="brand-radio-group">
                    <label class="brand-option" for="honda">
                        <input type="radio" id="honda" name="brand" value="Honda" required> Honda
                    </label>
                    <label class="brand-option" for="suzuki">
                        <input type="radio" id="suzuki" name="brand" value="Suzuki" required> Suzuki
                    </label>
                    <label class="brand-option" for="yamaha">
                        <input type="radio" id="yamaha" name="brand" value="Yamaha" required> Yamaha
                    </label>
                    <label class="brand-option" for="kawasaki">
                        <input type="radio" id="kawasaki" name="brand" value="Kawasaki" required> Kawasaki
                    </label>
                </div>
                <div class="form-error" id="brand-radio-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Please select a motorcycle brand.</span>
                </div>

                <label>Vehicle Type: <span style="color:var(--error);">*</span></label>
                <select name="vehicle_type" id="vehicle_type" required>
                    <option value="" disabled selected>Select Vehicle Type</option>
                    <option value="Automatic">Automatic</option>
                    <option value="Manual">Manual</option>
                </select>

                <label>Service Location: <span style="color:var(--error);">*</span></label>
                <div class="location-options" id="location-radio-group">
                    <label class="location-option" for="home">
                        <input type="radio" id="home" name="service_location" value="home" required>
                        🏠 Home Service
                    </label>
                    <label class="location-option" for="shop">
                        <input type="radio" id="shop" name="service_location" value="shop" required>
                        🏪 In-Shop Service
                    </label>
                </div>
                <div class="form-error" id="location-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Please select a service location.</span>
                </div>

                <div id="address-fields" class="address-fields">
                    <label>Saved Address: <span style="color:var(--error);">*</span></label>
                    <select name="saved_address" id="saved_address">
                        <option value="" disabled selected>Select Saved Address</option>
                        <?php foreach($saved_addresses as $addr):
                            $full = trim(
                                $addr['address']
                                . (!empty($addr['barangay']) ? ', '.$addr['barangay'] : '')
                                . (!empty($addr['city'])     ? ', '.$addr['city']     : '')
                            );
                        ?>
                        <option value="<?= htmlspecialchars($full) ?>"><?= htmlspecialchars($full) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-error" id="address-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Please select a saved address for home service.</span>
                    </div>
                </div>

                <label>Service Type: <span style="color:var(--error);">*</span></label>
                <select name="service_type" id="service_type" required>
                    <option value="" disabled selected>Select Service</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['id'] ?>"
                                data-key="<?= htmlspecialchars(strtolower($service['service_key'])) ?>"
                                data-duration="<?= $service['duration_hours'] ?>">
                            <?= htmlspecialchars($service['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-error" id="service-type-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Please select a service type.</span>
                </div>

                <div id="tire-size-section" style="display:none;">
                    <label>Tire Size: <span style="color:var(--error);">*</span></label>
                    <select name="tire_size" id="tire_size">
                        <option value="" disabled selected>Select Tire Size</option>
                    </select>
                    <div class="form-error" id="tire-size-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Please select a tire size.</span>
                    </div>
                </div>

                <div id="brands-section" style="display:none;">
                    <label id="brands-label">Available Options: <span style="color:var(--error);">*</span></label>
                    <div id="brands-container" class="brands-container"></div>
                    <div class="form-error" id="brand-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Please select a brand or package.</span>
                    </div>
                    <div id="coverage-list"></div>
                    <div id="package-details" style="display:none;margin-top:15px;">
                        <p style="color:var(--text-secondary);font-size:12px;">
                            <strong>Pricing Note:</strong> Package pricing varies based on included services and parts.
                        </p>
                    </div>
                </div>

                <div id="price-display-container" style="display:none;">
                    <label>Estimated Price (Service + Parts):</label>
                    <input type="text" id="price_display" readonly placeholder="Auto calculated"
                           style="background:rgba(26,86,219,0.08);border:1px solid var(--border);">
                </div>

                <div id="parts-section" style="display:none;">
                    <label>Additional Parts Needed:</label>
                    <div id="parts-container" class="parts-container">
                        <?php
                        $currentCategory = '';
                        foreach ($parts as $part) {
                            if ($currentCategory !== $part['category']) {
                                if ($currentCategory !== '') echo '</div>';
                                echo '<div class="parts-category" style="margin-bottom:15px;">'
                                   . '<strong style="color:var(--primary);">' . htmlspecialchars($part['category']) . ':</strong></div>';
                                echo '<div class="parts-group">';
                                $currentCategory = $part['category'];
                            }
                            echo '<label>'
                               . '<input type="checkbox" value="' . htmlspecialchars($part['price']) . '"'
                               . ' data-name="' . htmlspecialchars($part['name']) . '"> '
                               . htmlspecialchars($part['name']) . ' - ₱' . htmlspecialchars($part['price'])
                               . '</label>';
                        }
                        if ($currentCategory !== '') echo '</div>';
                        ?>
                    </div>
                    <button type="button" id="clear-parts-btn"
                            style="margin-top:15px;background:rgba(26,86,219,0.08);color:var(--primary);
                                   border:1px solid var(--border);padding:8px 15px;border-radius:8px;
                                   cursor:pointer;font-weight:600;">
                        Clear All Parts
                    </button>
                </div>

                <input type="hidden" name="selected_brand_id"    id="selected_brand_id_input"    value="">
                <input type="hidden" name="selected_brand_name"  id="selected_brand_name_input"  value="">
                <input type="hidden" name="selected_brand_price" id="selected_brand_price_input" value="">
                <input type="hidden" name="selected_parts"       id="selected_parts_input"       value="">

                <label>Note: <small style="color:var(--text-secondary);text-transform:none;font-weight:400;">(optional)</small></label>
                <textarea name="note" placeholder="Any special instructions..." style="resize:vertical;min-height:80px;"></textarea>

                <button type="submit" name="book_service" id="submit-booking">Submit Booking</button>
            </div>
        </form>
    </div>
</div>

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

const notificationBell     = document.getElementById('notification-bell');
const notificationDropdown = document.getElementById('notification-dropdown');
notificationBell.addEventListener('click', e => {
    e.stopPropagation();
    notificationDropdown.classList.toggle('show');
    if (notificationDropdown.classList.contains('show')) {
        fetch('mark_notifications_viewed.php', { method:'POST', headers:{'Content-Type':'application/json'} })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(0);
                document.querySelectorAll('.notification-dropdown-item.unread').forEach(i => i.classList.remove('unread'));
                const rp = document.getElementById('recent-notifications-panel');
                if (rp) { rp.style.animation = 'slideUp .3s ease'; setTimeout(() => rp.style.display='none',300); }
            }
        }).catch(console.error);
    }
});
document.addEventListener('click', e => {
    if (!notificationDropdown.contains(e.target) && e.target !== notificationBell)
        notificationDropdown.classList.remove('show');
});
const clearRecentBtn = document.getElementById('clear-recent-notif');
if (clearRecentBtn) {
    clearRecentBtn.addEventListener('click', () => {
        if (!confirm('Mark all recent notifications as read?')) return;
        fetch('clear_recent_notifications.php', { method:'POST', headers:{'Content-Type':'application/json'} })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const panel = document.getElementById('recent-notifications-panel');
                if (panel) { panel.style.animation='slideUp .3s ease'; setTimeout(()=>panel.remove(),300); }
                updateNotificationBadge(data.unread_count || 0);
            }
        }).catch(console.error);
    });
}

const modal      = document.getElementById('booking-modal');
const openBtn    = document.getElementById('book-now-btn');
const closeBtn   = document.getElementById('close-modal');
const aboutModal = document.getElementById('about-us-modal');
const aboutPanel = document.getElementById('about-us-panel');
const closeAbout = document.getElementById('close-about-modal');

// Block modal open if booking is blocked (banned or disabled today)
openBtn.onclick = () => {
    if (window.isBookingBlocked) return;
    modal.classList.add('show');
};
closeBtn.onclick   = () => modal.classList.remove('show');
aboutPanel.onclick = () => aboutModal.classList.add('show');
closeAbout.onclick = () => aboutModal.classList.remove('show');
window.addEventListener('click', e => {
    if (e.target === modal)      modal.classList.remove('show');
    if (e.target === aboutModal) aboutModal.classList.remove('show');
});

document.querySelectorAll('input[name="brand"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.brand-option').forEach(o => o.classList.remove('selected'));
        radio.parentElement.classList.add('selected');
        document.getElementById('brand-radio-error')?.classList.remove('show');
    });
});

document.querySelectorAll('input[name="service_location"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const sel = document.querySelector('input[name="service_location"]:checked');
        document.getElementById('address-fields').style.display = sel?.value==='home' ? 'block' : 'none';
        document.getElementById('parts-section').style.display  = sel?.value==='home' ? 'block' : 'none';
        document.querySelectorAll('.location-option').forEach(o => o.classList.remove('selected'));
        sel?.parentElement.classList.add('selected');
        document.getElementById('location-error')?.classList.remove('show');
        if (sel?.value !== 'home') document.getElementById('address-error')?.classList.remove('show');
    });
});
document.getElementById('saved_address')?.addEventListener('change', () => {
    document.getElementById('address-error')?.classList.remove('show');
});

const bookingDateInput   = document.getElementById('booking_date');
const mechanicWrapper    = document.getElementById('mechanic-step-wrapper');
const mechanicSelect     = document.getElementById('mechanic-select');
const timeSlotWrapper    = document.getElementById('time-slot-wrapper');
const timeSlotGrid       = document.getElementById('time-slot-grid');
const timeSlotLoading    = document.getElementById('time-slot-loading');
const scheduleHidden     = document.getElementById('schedule');
const bookingDetails     = document.getElementById('booking-details-section');
const step2Circle        = document.getElementById('step2-circle');
const step2Label         = document.getElementById('step2-label');
const pastDateError      = document.getElementById('past-date-error');
const alreadyBookedError = document.getElementById('date-already-booked-error');
const timeSlotError      = document.getElementById('time-slot-error');

const now          = new Date();
const currentHour  = now.getHours();
const currentMin   = now.getMinutes();
const nowMinutes   = currentHour * 60 + currentMin;

// Use LOCAL date parts to avoid UTC timezone off-by-one
const pad = n => String(n).padStart(2, '0');
const todayStr = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
const tomorrowDate = new Date(now);
tomorrowDate.setDate(tomorrowDate.getDate() + 1);
const tomorrowStr = `${tomorrowDate.getFullYear()}-${pad(tomorrowDate.getMonth()+1)}-${pad(tomorrowDate.getDate())}`;

// Last slot is 4:00–6:00 PM. Cutoff = 1 hour before end = 5:00 PM.
// Today is fully blocked (no slots left) only after 5:00 PM.
const LAST_SLOT_CUTOFF_MINUTES = 17 * 60; // 5:00 PM
const todayFullyBlocked = nowMinutes >= LAST_SLOT_CUTOFF_MINUTES;

// Build list of ALL disabled dates for flatpickr
const bookedDateSet = new Set(window.bookedDates || []);

// Minimum selectable date:
// - If today has no slots left (after 5:00 PM) → minDate = tomorrow
// - Otherwise → minDate = today
const minDate = todayFullyBlocked ? tomorrowStr : todayStr;

let flatpickrInstance = null;

if (!window.isBookingBlocked) {
    flatpickrInstance = flatpickr(bookingDateInput, {
        dateFormat:    'Y-m-d',
        minDate:       minDate,
        disableMobile: true,
        disable: [
            // Disable any booked date (completed, pending, in_progress)
            // Use local date parts to avoid UTC timezone off-by-one bugs
            function(date) {
                const y  = date.getFullYear();
                const m  = String(date.getMonth() + 1).padStart(2, '0');
                const d  = String(date.getDate()).padStart(2, '0');
                const ds = `${y}-${m}-${d}`;
                return bookedDateSet.has(ds);
            }
        ],
        onChange: function(selectedDates, dateStr) {
            pastDateError.classList.remove('show');
            alreadyBookedError.classList.remove('show');
            bookingDateInput.style.borderColor = '';

            resetTimeSlots();
            lockDetailsSection();

            if (!dateStr) return;

            // Extra guard (should never trigger due to flatpickr disable, but just in case)
            if (bookedDateSet.has(dateStr)) {
                alreadyBookedError.classList.add('show');
                bookingDateInput.style.borderColor = 'var(--error)';
                mechanicWrapper.style.display = 'none';
                return;
            }

            mechanicWrapper.style.display = 'block';
            mechanicWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
} else {
    // Booking blocked — disable the input entirely
    bookingDateInput.disabled = true;
    bookingDateInput.classList.add('banned-input');
    bookingDateInput.title = window.isBanned
        ? '⛔ Your account is suspended until ' + window.bannedUntil
        : '⛔ Booking disabled today due to no-show. Try again tomorrow.';
}

let selectedTimeSlot = '';

function validateDate() {
    const val = bookingDateInput.value;
    pastDateError.classList.remove('show');
    alreadyBookedError.classList.remove('show');
    bookingDateInput.style.borderColor = '';
    if (!val) return false;

    // Block dates with existing non-cancelled bookings
    if (bookedDateSet.has(val)) {
        alreadyBookedError.classList.add('show');
        bookingDateInput.style.borderColor = 'var(--error)';
        return false;
    }

    return true;
}

mechanicSelect.addEventListener('change', () => {
    resetTimeSlots();
    lockDetailsSection();
    const mId   = mechanicSelect.value;
    const mDate = bookingDateInput.value;
    if (!mId || !mDate) return;
    timeSlotWrapper.style.display = 'block';
    timeSlotLoading.style.display = 'block';
    timeSlotGrid.innerHTML        = '';
    fetch(`get_booked_slots.php?mechanic_id=${encodeURIComponent(mId)}&date=${encodeURIComponent(mDate)}&_=${Date.now()}`)
        .then(r => r.json())
        .then(data => {
            timeSlotLoading.style.display = 'none';
            renderTimeSlots(data.all_slots || []);
            timeSlotWrapper.scrollIntoView({ behavior:'smooth', block:'nearest' });
        })
        .catch(err => {
            timeSlotLoading.style.display = 'none';
            timeSlotGrid.innerHTML = '<p style="color:var(--error);font-size:12px;">Failed to load slots. Please refresh and try again.</p>';
            console.error(err);
        });
});

function renderTimeSlots(slots) {
    timeSlotGrid.innerHTML = '';
    if (!slots.length) {
        timeSlotGrid.innerHTML = '<p style="color:var(--text-secondary);font-size:13px;">No slots available for this date.</p>';
        return;
    }
    slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type         = 'button';
        btn.dataset.time = slot.time;
        btn.disabled     = slot.blocked;
        const statusText = slot.blocked
            ? (slot.reason === 'past' ? '\u274c No longer available' : '\ud83d\udd12 Already booked')
            : '\u2705 Available \u2014 tap to select';
        const iconClass = slot.blocked ? 'fa-lock' : 'fa-clock';
        btn.className = 'time-slot-card' + (slot.blocked ? ' slot-blocked' : '');
        btn.innerHTML = `
            <div class="slot-card-left">
                <div class="slot-card-icon"><i class="fas ${iconClass}"></i></div>
                <div class="slot-card-info">
                    <span class="slot-card-time">${slot.label}</span>
                    <span class="slot-card-status">${statusText}</span>
                </div>
            </div>
            <div class="slot-card-right">2 HRS</div>
        `;
        if (!slot.blocked) {
            btn.addEventListener('click', () => {
                timeSlotGrid.querySelectorAll('.time-slot-card').forEach(b => b.classList.remove('slot-selected'));
                btn.classList.add('slot-selected');
                selectedTimeSlot = slot.time;
                timeSlotError.classList.remove('show');
                scheduleHidden.value = bookingDateInput.value + ' ' + slot.time + ':00';
                unlockDetailsSection();
            });
        }
        timeSlotGrid.appendChild(btn);
    });
}

function resetTimeSlots() {
    selectedTimeSlot              = '';
    scheduleHidden.value          = '';
    timeSlotGrid.innerHTML        = '';
    timeSlotWrapper.style.display = 'none';
    timeSlotLoading.style.display = 'none';
    timeSlotError.classList.remove('show');
}

function unlockDetailsSection() {
    bookingDetails.style.display = 'block';
    step2Circle.classList.replace('inactive','active');
    step2Label.classList.replace('inactive','active');
    bookingDetails.scrollIntoView({ behavior:'smooth', block:'start' });
}

function lockDetailsSection() {
    bookingDetails.style.display = 'none';
    step2Circle.classList.replace('active','inactive');
    step2Label.classList.replace('active','inactive');
}

const serviceSelect   = document.getElementById('service_type');
const brandsSection   = document.getElementById('brands-section');
const brandsContainer = document.getElementById('brands-container');
const tireSizeSection = document.getElementById('tire-size-section');
const tireSizeSelect  = document.getElementById('tire_size');
const brandsData      = window.brandsDataFromPHP || {};
const brandError      = document.getElementById('brand-error');

let selectedBrandPrice = 0;
let currentServiceKey  = '';
let brandSelected      = false;

serviceSelect.addEventListener('change', () => {
    document.getElementById('service-type-error')?.classList.remove('show');
    serviceSelect.style.borderColor = '';
    brandsContainer.innerHTML = '';
    tireSizeSelect.innerHTML  = '<option value="" disabled selected>Select Tire Size</option>';
    selectedBrandPrice        = 0;
    brandSelected             = false;
    document.getElementById('selected_brand_id_input').value    = '';
    document.getElementById('selected_brand_name_input').value  = '';
    document.getElementById('selected_brand_price_input').value = '';
    brandError.classList.remove('show');
    tireSizeSection.style.display = 'none';
    brandsSection.style.display   = 'none';
    document.getElementById('coverage-list').style.display  = 'none';
    document.getElementById('coverage-list').innerHTML      = '';
    document.getElementById('package-details').style.display = 'none';

    const selectedOption    = serviceSelect.options[serviceSelect.selectedIndex];
    const selectedServiceId = parseInt(serviceSelect.value, 10);
    currentServiceKey       = (selectedOption.dataset.key || '').toLowerCase().trim();

    if (!selectedServiceId || isNaN(selectedServiceId)) return;

    const isMaintenanceType = currentServiceKey.includes('maintenance') || currentServiceKey.includes('tune');
    const isTireService     = currentServiceKey === 'tire' || currentServiceKey.includes('tire');
    const brandsLabel       = document.getElementById('brands-label');
    const coverageList      = document.getElementById('coverage-list');

    if (isTireService) {
        const serviceData = brandsData[selectedServiceId];
        if (!serviceData?.items) return;
        const tireBrands = serviceData.items;
        const allSizes   = new Set();
        tireBrands.forEach(b => b.coverage?.forEach(s => allSizes.add(s)));
        allSizes.forEach(size => {
            const opt = document.createElement('option');
            opt.value = opt.textContent = size;
            tireSizeSelect.appendChild(opt);
        });
        tireSizeSection.style.display = 'block';
        tireSizeSelect.onchange = () => {
            brandsContainer.innerHTML   = '';
            brandsSection.style.display = 'none';
            brandSelected               = false;
            brandError.classList.remove('show');
            document.getElementById('tire-size-error')?.classList.remove('show');
            const selectedSize = tireSizeSelect.value;
            if (!selectedSize) return;
            const matching = tireBrands.filter(b => b.coverage?.includes(selectedSize));
            if (!matching.length) return;
            brandsLabel.textContent     = `Available Tire Brands for ${selectedSize}: *`;
            brandsSection.style.display = 'block';
            matching.forEach(brand => addBrandBtn(brand, coverageList));
        };
        return;
    }

    const serviceData = brandsData[selectedServiceId];
    if (!serviceData?.items) {
        brandsLabel.textContent     = '❌ No options available for this service.';
        brandsSection.style.display = 'block';
        return;
    }
    brandsSection.style.display = 'block';
    brandsLabel.textContent     = isMaintenanceType ? 'Available Packages: *' : 'Available Brands: *';
    document.getElementById('package-details').style.display = isMaintenanceType ? 'block' : 'none';
    serviceData.items.forEach(brand => addBrandBtn(brand, coverageList));
});

function addBrandBtn(brand, coverageList) {
    const btn       = document.createElement('button');
    btn.type        = 'button';
    btn.className   = 'brand-btn';
    btn.textContent = `${brand.name} - ₱${brand.price}`;
    btn.dataset.id    = brand.id;
    btn.dataset.name  = brand.name;
    btn.dataset.price = brand.price;
    btn.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('.brand-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('selected_brand_id_input').value    = btn.dataset.id;
        document.getElementById('selected_brand_name_input').value  = btn.dataset.name;
        document.getElementById('selected_brand_price_input').value = btn.dataset.price;
        selectedBrandPrice = parseFloat(btn.dataset.price);
        brandSelected      = true;
        brandError.classList.remove('show');
        if (brand.coverage?.length) {
            coverageList.innerHTML =
                '<h4 style="color:var(--primary);margin-bottom:12px;">📋 What\'s Included:</h4>'
                + '<ul style="color:var(--text-secondary);padding-left:20px;margin:0;">'
                + brand.coverage.map(i => `<li style="margin:8px 0;font-size:13px;">${i}</li>`).join('')
                + '</ul>';
            coverageList.style.display = 'block';
        } else {
            coverageList.style.display = 'none';
        }
        calculatePrice();
    });
    brandsContainer.appendChild(btn);
}

const priceDisplay = document.getElementById('price_display');
function calculatePrice() {
    let total = selectedBrandPrice;
    document.querySelectorAll('#parts-container input[type="checkbox"]:checked')
            .forEach(cb => { total += parseFloat(cb.value); });
    priceDisplay.value = '₱' + total.toFixed(2);
    document.getElementById('price-display-container').style.display = total > 0 ? 'block' : 'none';
}
function collectParts() {
    const parts = [];
    document.querySelectorAll('#parts-container input[type="checkbox"]:checked')
            .forEach(cb => parts.push({ name: cb.dataset.name, price: parseFloat(cb.value) }));
    document.getElementById('selected_parts_input').value = JSON.stringify(parts);
}
document.querySelectorAll('#parts-container input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => { collectParts(); calculatePrice(); });
});
document.getElementById('clear-parts-btn').addEventListener('click', () => {
    document.querySelectorAll('#parts-container input[type="checkbox"]').forEach(cb => cb.checked = false);
    collectParts(); calculatePrice();
});

document.getElementById('booking-form').addEventListener('submit', e => {
    // Extra safety — block form submit if booking is blocked
    if (window.isBookingBlocked) { e.preventDefault(); return; }

    document.querySelectorAll('#booking-form .form-error').forEach(el => {
        if (!['past-date-error','date-already-booked-error'].includes(el.id))
            el.classList.remove('show');
    });
    document.querySelectorAll('#booking-form select, #booking-form input[type="date"]')
            .forEach(el => el.style.borderColor = '');

    let firstErrorEl = null;
    let hasError     = false;

    function flagError(errorId, inputEl) {
        const errEl = document.getElementById(errorId);
        if (errEl) errEl.classList.add('show');
        if (inputEl) inputEl.style.borderColor = 'var(--error)';
        if (!firstErrorEl) firstErrorEl = errEl || inputEl;
    }

    if (!validateDate()) {
        hasError = true;
        if (!firstErrorEl) firstErrorEl = bookingDateInput;
    }
    if (!mechanicSelect.value) {
        hasError = true;
        mechanicSelect.style.borderColor = 'var(--error)';
        if (!firstErrorEl) firstErrorEl = mechanicSelect;
    }
    if (!selectedTimeSlot || !scheduleHidden.value) {
        hasError = true;
        flagError('time-slot-error', null);
        if (!firstErrorEl) firstErrorEl = document.getElementById('time-slot-error');
    }
    if (!document.querySelector('input[name="brand"]:checked')) {
        hasError = true;
        flagError('brand-radio-error', null);
    }
    const vehicleTypeEl = document.getElementById('vehicle_type');
    if (!vehicleTypeEl.value) {
        hasError = true;
        flagError(null, vehicleTypeEl);
        if (!firstErrorEl) firstErrorEl = vehicleTypeEl;
    }
    if (!document.querySelector('input[name="service_location"]:checked')) {
        hasError = true;
        flagError('location-error', null);
    }
    const locPicked = document.querySelector('input[name="service_location"]:checked');
    if (locPicked?.value === 'home') {
        const addrEl = document.getElementById('saved_address');
        if (!addrEl?.value) {
            hasError = true;
            flagError('address-error', addrEl);
        }
    }
    const serviceTypeEl = document.getElementById('service_type');
    if (!serviceTypeEl.value) {
        hasError = true;
        flagError('service-type-error', serviceTypeEl);
    }
    if (tireSizeSection.style.display !== 'none') {
        const tsEl = document.getElementById('tire_size');
        if (!tsEl?.value) {
            hasError = true;
            flagError('tire-size-error', tsEl);
        }
    }
    if (brandsSection.style.display !== 'none') {
        if (!brandSelected || !document.getElementById('selected_brand_id_input').value) {
            hasError = true;
            flagError('brand-error', null);
        }
    }
    if (hasError) {
        e.preventDefault();
        firstErrorEl?.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }
    collectParts();
});

function updateNotificationBadge(count) {
    const badge = document.querySelector('.bell-badge');
    if (count > 0) {
        if (badge) { badge.textContent = count; }
        else {
            const nb = document.createElement('span');
            nb.className = 'bell-badge'; nb.textContent = count;
            notificationBell.appendChild(nb);
        }
    } else { badge?.remove(); }
}
function markAsRead(notifId, bookingId) {
    fetch('mark_notification_read.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ notification_id: notifId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateNotificationBadge(data.unread_count);
            document.querySelector(`.notification-dropdown-item[data-notif-id="${notifId}"]`)?.classList.remove('unread');
            notificationDropdown.classList.remove('show');
            window.location.href = 'customer_my_bookings.php?id=' + bookingId;
        }
    }).catch(console.error);
}

let lastKnownNotifCount = parseInt(document.querySelector('.bell-badge')?.textContent || '0');
function checkNotifications() {
    fetch('check_notifications.php')
        .then(r => r.json())
        .then(data => {
            if (data.unread_count !== lastKnownNotifCount) {
                updateNotificationBadge(data.unread_count);
                if (data.unread_count > lastKnownNotifCount) {
                    if (Notification.permission === 'granted')
                        new Notification('🔔 New Notification', { body:'You have a new booking update!' });
                    setTimeout(() => location.reload(), 2000);
                }
                lastKnownNotifCount = data.unread_count;
            }
        }).catch(console.error);
}
setInterval(checkNotifications, 15000);
if ('Notification' in window && Notification.permission === 'default')
    Notification.requestPermission();

console.log('✅ Dashboard JS loaded — same-day booking enabled, past slots auto-blocked.');
</script>
</body>
</html>