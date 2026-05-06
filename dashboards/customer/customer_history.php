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

// Handle date filter
$filter_date  = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$where_clause = "b.customer_id = ? AND b.status IN ('completed','cancelled','cancelled_by_customer','cancelled_by_mechanic')";
$params       = [$user_id];

if (!empty($filter_date)) {
    $where_clause .= " AND DATE(b.created_at) = ?";
    $params[] = $filter_date;
}

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.brand,
        b.vehicle_type,
        b.service_type,
        b.service_location,
        b.service_address,
        b.tire_size,
        b.schedule,
        b.booking_fee,
        b.labor_fee,
        b.parts,
        b.parts_total,
        b.service_fee,
        b.total_price,
        b.note,
        CONCAT(u.first_name,' ',u.last_name) AS mechanic_name,
        b.status,
        b.created_at,
        s.id AS service_id,
        s.name AS service_name,
        s.service_key
    FROM bookings b
    LEFT JOIN users u ON b.mechanic_id = u.id
    LEFT JOIN services s ON b.service_type = s.service_key
    WHERE $where_clause
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$per_page       = 10;
$total_bookings = count($bookings);
$total_pages    = ceil($total_bookings / $per_page);
$page           = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page           = max(1, min($page, $total_pages ?: 1));
$offset             = ($page - 1) * $per_page;
$paginated_bookings = array_slice($bookings, $offset, $per_page);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking History - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── ROOT: exact match to customer_dashboard.php ───────────────────── */
:root {
    --primary:        #1a56db;
    --secondary:      #1e40af;
    --dark-bg:        #f0f4ff;
    --card-bg:        #ffffff;
    --border:         rgba(26, 86, 219, 0.2);
    --text-primary:   #1e293b;
    --text-secondary: #475569;
    --success:        #059669;
    --error:          #dc2626;
    --warning:        #d97706;
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

/* ── HAMBURGER ──────────────────────────────────────────────────────── */
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

/* ── NOTIFICATION BELL ──────────────────────────────────────────────── */
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

/* ── SIDEBAR OVERLAY ────────────────────────────────────────────────── */
.sidebar-overlay {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(26,86,219,0.1); z-index: 999;
    opacity: 0; transition: opacity 0.3s ease;
}
.sidebar-overlay.active { display: block; opacity: 1; }

/* ── SIDEBAR ────────────────────────────────────────────────────────── */
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
    font-size: 1.6rem; font-weight: 700; color: #ffffff;
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
.sidebar nav a:hover,
.sidebar nav a.active {
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
    color: var(--text-primary) !important;
    margin-top: auto !important; justify-content: center !important;
}
.logout-btn:hover { background: linear-gradient(135deg, #ff6666, #d44444) !important; }

/* ── MAIN CONTENT ───────────────────────────────────────────────────── */
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

/* ── FILTER SECTION ─────────────────────────────────────────────────── */
.filter-section {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px; padding: 20px; margin-bottom: 25px;
    display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;
    box-shadow: 0 4px 15px rgba(26,86,219,0.08);
    transition: all 0.3s ease;
}
.filter-section:hover { border-color: var(--primary); box-shadow: 0 8px 25px rgba(26,86,219,0.12); }
.filter-section label {
    color: var(--primary); font-weight: 700;
    font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;
    display: block; margin-bottom: 6px;
}
.filter-section input[type="date"] {
    padding: 10px 14px; border-radius: 10px;
    border: 1px solid var(--border); background: #ffffff;
    color: var(--text-primary); font-size: 13px;
    font-family: 'Outfit', sans-serif; transition: 0.3s ease;
}
.filter-section input[type="date"]:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(26,86,219,0.1);
    outline: none;
}
.filter-buttons { display: flex; gap: 10px; }
.btn {
    padding: 10px 20px; border-radius: 10px; border: none;
    cursor: pointer; font-weight: 700; font-size: 13px;
    transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 6px;
    text-decoration: none; font-family: 'Outfit', sans-serif;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; box-shadow: 0 3px 10px rgba(26,86,219,0.2);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(26,86,219,0.3); }
.btn-secondary {
    background: var(--card-bg); color: var(--primary);
    border: 1px solid var(--border);
}
.btn-secondary:hover { background: rgba(26,86,219,0.06); border-color: var(--primary); transform: translateY(-2px); }

/* ── BOOKING CARD ───────────────────────────────────────────────────── */
.booking-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px; margin-bottom: 15px; overflow: hidden;
    box-shadow: 0 4px 15px rgba(26,86,219,0.08);
    transition: all 0.3s ease;
}
.booking-card:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: 0 12px 40px rgba(26,86,219,0.15);
}

/* ── BOOKING SUMMARY (clickable header) ────────────────────────────── */
.booking-summary {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px; cursor: pointer;
    background: rgba(26,86,219,0.03);
    border-bottom: 1px solid var(--border);
    transition: background 0.2s ease;
}
.booking-summary:hover { background: rgba(26,86,219,0.07); }
.summary-left { display: flex; align-items: center; gap: 15px; flex: 1; }
.summary-left i { color: var(--primary); font-size: 24px; }
.summary-info h4 { margin: 0; color: var(--primary); font-size: 16px; font-weight: 700; }
.summary-info p  { margin: 5px 0 0 0; color: var(--text-secondary); font-size: 13px; }
.summary-right { text-align: right; min-width: 150px; }
.total-price {
    font-size: 18px; font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ── STATUS BADGES ──────────────────────────────────────────────────── */
.status {
    padding: 5px 12px; border-radius: 20px;
    font-weight: 700; font-size: 11px;
    display: inline-block; text-transform: capitalize;
    margin-top: 8px; letter-spacing: 0.3px;
}
.status.completed { background: linear-gradient(45deg, #059669, #047857); color: #fff; }
.status.cancelled { background: linear-gradient(45deg, #dc2626, #b91c1c); color: #fff; }
.status.declined  { background: linear-gradient(45deg, #d97706, #b45309); color: #fff; }

/* ── BOOKING DETAILS (expanded) ─────────────────────────────────────── */
.booking-details {
    display: none; padding: 20px;
    background: rgba(26,86,219,0.02);
    border-top: 1px solid var(--border);
}
.booking-details.show { display: block; animation: slideDown 0.3s ease; }
@keyframes slideDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

.details-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 12px;
}
.detail-item {
    background: rgba(26,86,219,0.04);
    border: 1px solid var(--border);
    padding: 12px; border-radius: 10px;
    transition: background 0.2s ease;
}
.detail-item:hover { background: rgba(26,86,219,0.08); }
.detail-item strong {
    color: var(--primary); display: block;
    margin-bottom: 5px; font-size: 11px;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.detail-item p { margin: 0; color: var(--text-secondary); font-size: 13px; }

/* ── NO BOOKINGS ────────────────────────────────────────────────────── */
.no-bookings {
    text-align: center; color: var(--text-secondary);
    font-size: 16px; padding: 60px 0;
}
.no-bookings i {
    font-size: 56px; margin-bottom: 20px; display: block;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; opacity: 0.4;
}

/* ── PAGINATION ─────────────────────────────────────────────────────── */
.pagination {
    display: flex; justify-content: center; gap: 8px;
    margin-top: 30px; flex-wrap: wrap;
}
.pagination a {
    padding: 9px 14px; border-radius: 8px;
    color: var(--primary); background: var(--card-bg);
    border: 1px solid var(--border);
    text-decoration: none; transition: all 0.3s ease;
    font-weight: 600; font-size: 13px;
}
.pagination a:hover,
.pagination a.current {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; border-color: var(--primary);
    transform: translateY(-2px); box-shadow: 0 4px 12px rgba(26,86,219,0.2);
}

/* ── MOBILE ─────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .hamburger-btn { display: block; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .sidebar nav { padding-bottom: 20px; }
    .main { margin-left: 0; width: 100%; padding: 80px 20px 20px 20px; }
    .header { font-size: 20px; margin-top: 20px; }
    .filter-section { flex-direction: column; align-items: stretch; padding: 15px; }
    .filter-section input[type="date"] { width: 100%; }
    .filter-buttons { width: 100%; flex-direction: column; }
    .btn { width: 100%; justify-content: center; padding: 12px 20px; }
    .booking-summary { flex-direction: column; text-align: center; gap: 10px; padding: 15px; }
    .summary-left { flex-direction: column; gap: 8px; width: 100%; }
    .summary-right { min-width: auto; width: 100%; }
    .details-grid { grid-template-columns: 1fr; gap: 10px; }
    .detail-item { padding: 10px; }
    .no-bookings { padding: 30px 15px; }
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
    <?php if ($unreadNotifications > 0): ?>
        <span class="bell-badge"><?= $unreadNotifications ?></span>
    <?php endif; ?>
</button>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
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

<!-- MAIN CONTENT -->
<main class="main">
    <div class="header">
        <i class="fas fa-history"></i> Booking History
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div style="flex:1;">
            <label for="filter_date">Filter by Date</label>
            <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <input type="date" name="filter_date" id="filter_date"
                       value="<?= htmlspecialchars($filter_date) ?>"
                       style="flex:1;min-width:200px;">
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($filter_date)): ?>
                        <a href="customer_history.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- BOOKINGS LIST -->
    <?php if (empty($paginated_bookings)): ?>
        <div class="no-bookings">
            <i class="fas fa-inbox"></i>
            <p>No booking history found<?= !empty($filter_date) ? ' for the selected date' : '' ?>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($paginated_bookings as $b): ?>
            <?php
            $displayStatus = 'Unknown';
            $statusClass   = 'cancelled';
            if ($b['status'] === 'completed') {
                $displayStatus = 'Completed';
                $statusClass   = 'completed';
            } elseif ($b['status'] === 'cancelled_by_customer') {
                $displayStatus = 'Cancelled by You';
                $statusClass   = 'cancelled';
            } elseif ($b['status'] === 'cancelled_by_mechanic') {
                $displayStatus = 'Declined by Mechanic';
                $statusClass   = 'declined';
            } elseif ($b['status'] === 'cancelled') {
                $displayStatus = 'Cancelled';
                $statusClass   = 'cancelled';
            }
            ?>
            <div class="booking-card">
                <div class="booking-summary" onclick="toggleDetails(this)">
                    <div class="summary-left">
                        <i class="fas fa-wrench"></i>
                        <div class="summary-info">
                            <h4><?= htmlspecialchars($b['service_name'] ?: $b['service_type']) ?></h4>
                            <p>Booked on <?= date('M d, Y', strtotime($b['created_at'])) ?></p>
                        </div>
                    </div>
                    <div class="summary-right">
                        <div class="total-price">₱<?= number_format($b['total_price'], 2) ?></div>
                        <div class="status <?= $statusClass ?>"><?= $displayStatus ?></div>
                    </div>
                </div>

                <div class="booking-details">
                    <div class="details-grid">
                        <div class="detail-item">
                            <strong>📋 Booking ID</strong>
                            <p>#<?= htmlspecialchars($b['id']) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>🏍️ Brand</strong>
                            <p><?= htmlspecialchars($b['brand']) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>🚗 Vehicle Type</strong>
                            <p><?= htmlspecialchars($b['vehicle_type']) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>🔧 Service Type</strong>
                            <p><?= htmlspecialchars($b['service_name'] ?: $b['service_type']) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>📍 Service Location</strong>
                            <p><?= htmlspecialchars(ucfirst($b['service_location'])) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>🏠 Address</strong>
                            <p><?= htmlspecialchars($b['service_address'] ?: 'N/A') ?></p>
                        </div>
                        <?php if ($b['service_key'] === 'tire_change' && !empty($b['tire_size'])): ?>
                        <div class="detail-item">
                            <strong>🛞 Tire Size</strong>
                            <p><?= htmlspecialchars($b['tire_size']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <strong>⏰ Schedule</strong>
                            <p><?= !empty($b['schedule']) ? date('M d, Y h:i A', strtotime($b['schedule'])) : '-' ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>💰 Labor Fee</strong>
                            <p>₱<?= number_format($b['labor_fee'], 2) ?></p>
                        </div>
                        <?php if ($b['service_location'] === 'home'): ?>
                        <div class="detail-item">
                            <strong>🏠 Home Service Fee</strong>
                            <p>₱150.00</p>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <strong>💵 Service / Package Fee</strong>
                            <p>₱<?= number_format($b['service_fee'], 2) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>🔩 Parts</strong>
                            <p>
                                <?php
                                $parts = json_decode($b['parts'], true);
                                if ($parts && is_array($parts)) {
                                    foreach ($parts as $p) {
                                        echo htmlspecialchars($p['name']) . ' (₱' . number_format($p['price'], 2) . ')<br>';
                                    }
                                } else { echo '-'; }
                                ?>
                            </p>
                        </div>
                        <div class="detail-item">
                            <strong>📦 Parts Total</strong>
                            <p>₱<?= number_format($b['parts_total'], 2) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>📝 Special Instructions</strong>
                            <p><?= htmlspecialchars($b['note'] ?: '-') ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>👨‍🔧 Mechanic</strong>
                            <p><?= htmlspecialchars($b['mechanic_name'] ?: 'TBA') ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>📊 Status</strong>
                            <p><?= $displayStatus ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>🕐 Booked At</strong>
                            <p><?= date('M d, Y h:i A', strtotime($b['created_at'])) ?></p>
                        </div>
                        <div class="detail-item">
                            <strong>💳 Total Amount</strong>
                            <p style="color:var(--primary);font-weight:700;font-size:14px;">₱<?= number_format($b['total_price'], 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&filter_date=<?= urlencode($filter_date) ?>"><i class="fas fa-chevron-left"></i> Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&filter_date=<?= urlencode($filter_date) ?>" class="<?= $i==$page ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&filter_date=<?= urlencode($filter_date) ?>">Next <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
// ── Hamburger ────────────────────────────────────────────────────────
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

// ── Notification bell ────────────────────────────────────────────────
document.getElementById('notification-bell').addEventListener('click', () => {
    window.location.href = 'customer_notifications.php';
});

// ── Toggle booking details ───────────────────────────────────────────
function toggleDetails(element) {
    const details = element.nextElementSibling;
    details.classList.toggle('show');
}
</script>

</body>
</html>