<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// Fetch all bookings for this customer (any status — receipt is always accessible)
$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.brand,
        b.vehicle_type,
        b.service_type,
        b.service_location,
        b.schedule,
        b.status,
        b.total_price,
        b.created_at,
        s.name AS service_name,
        CONCAT(u.first_name, ' ', u.last_name) AS mechanic_name
    FROM bookings b
    LEFT JOIN services s ON s.service_key = b.service_type
    LEFT JOIN users u ON b.mechanic_id = u.id
    WHERE b.customer_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sidebar counters
$messages = (function() use ($pdo, $user_id) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $s->execute([$user_id]); return $s->fetchColumn();
})();

$unreadNotifications = (function() use ($pdo, $user_id) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
    $s->execute([$user_id]); return $s->fetchColumn();
})();

$currentPage = basename($_SERVER['PHP_SELF']);

function statusBadge($status) {
    $map = [
        'pending'                  => ['label'=>'Pending',        'color'=>'#856404','bg'=>'#fff3cd'],
        'preparing'                => ['label'=>'Preparing',      'color'=>'#004085','bg'=>'#cce5ff'],
        'in_progress'              => ['label'=>'In Progress',    'color'=>'#155724','bg'=>'#d4edda'],
        'completed'                => ['label'=>'Completed',      'color'=>'#155724','bg'=>'#d4edda'],
        'cancelled'                => ['label'=>'Cancelled',      'color'=>'#721c24','bg'=>'#f8d7da'],
        'awaiting_customer_action' => ['label'=>'Action Needed',  'color'=>'#e65100','bg'=>'#ffe0b2'],
        'assigned'                 => ['label'=>'Assigned',       'color'=>'#1b5e20','bg'=>'#c8e6c9'],
    ];
    $s = $map[$status] ?? ['label'=>ucfirst($status),'color'=>'#333','bg'=>'#eee'];
    return "<span style='display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;
                         text-transform:uppercase;letter-spacing:.5px;background:{$s['bg']};color:{$s['color']};'>
                {$s['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Receipts — MotorService</title>
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

/* ── hamburger ── */
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

/* ── notification bell ── */
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

/* ── sidebar ── */
.sidebar-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(26,86,219,0.1); z-index:999; opacity:0; transition:opacity .3s;
}
.sidebar-overlay.active{display:block;opacity:1}
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
.sidebar nav { display:flex; flex-direction:column; gap:8px; flex:1; }
.sidebar nav a {
    display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:10px;
    color:rgba(255,255,255,0.75); font-weight:600; transition:all .3s; font-size:14px;
}
.sidebar nav a:hover, .sidebar nav a.active {
    background:rgba(255,255,255,0.2); color:#ffffff; transform:translateX(5px);
}
.sidebar nav a i { width:20px; text-align:center; }
.notification-badge {
    display:inline-flex; align-items:center; justify-content:center;
    background:#ff0000; color:#fff; width:18px; height:18px; min-width:18px;
    font-size:10px; font-weight:700; border-radius:50%; margin-left:auto;
    box-shadow:0 0 8px rgba(255,0,0,0.7);
}
.logout-btn {
    background:linear-gradient(135deg,#ff4444,#c82333)!important;
    color:var(--text-primary)!important; margin-top:auto!important; justify-content:center!important;
}
.sidebar .footer {
    margin-top:auto; font-size:12px; color:rgba(255,255,255,0.5);
    text-align:center; padding-top:20px; border-top:1px solid rgba(255,255,255,0.15);
}

/* ── main ── */
.main {
    margin-left:260px; padding:40px; width:calc(100% - 260px);
    height:100vh; overflow-y:auto;
    background:linear-gradient(135deg,#f0f4ff,#e8eeff);
}
.main::-webkit-scrollbar{width:8px}
.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.page-header {
    font-size:26px; font-weight:700; margin-bottom:8px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    display:flex; align-items:center; gap:12px;
}
.page-sub { color:var(--text-secondary); font-size:14px; margin-bottom:30px; }

/* ── search/filter bar ── */
.filter-bar {
    display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; align-items:center;
}
.filter-bar input {
    padding:10px 16px; border-radius:10px; border:1px solid var(--border);
    background:#ffffff; color:var(--text-primary);
    font-family:'Outfit',sans-serif; font-size:13px; flex:1; min-width:180px;
    transition:.3s;
}
.filter-bar input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(26,86,219,0.1); }
.filter-bar select {
    padding:10px 14px; border-radius:10px; border:1px solid var(--border);
    background:#ffffff; color:var(--text-primary);
    font-family:'Outfit',sans-serif; font-size:13px; cursor:pointer;
}
.filter-bar select option { background:#ffffff; }

/* ── receipts grid ── */
.receipts-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px;
}
.receipt-card {
    background:var(--card-bg); border:1px solid var(--border); border-radius:14px;
    padding:22px; transition:all .3s; position:relative; overflow:hidden;
    box-shadow:0 4px 15px rgba(26,86,219,0.08);
}
.receipt-card::before {
    content:''; position:absolute; top:0; left:0; right:0; height:4px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
}
.receipt-card:hover {
    transform:translateY(-4px); border-color:var(--primary);
    box-shadow:0 12px 35px rgba(26,86,219,0.2);
}
.card-top {
    display:flex; justify-content:space-between; align-items:flex-start;
    margin-bottom:14px; gap:8px;
}
.booking-ref {
    font-size:18px; font-weight:700;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.card-body { margin-bottom:16px; }
.card-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:7px 0; border-bottom:1px solid rgba(26,86,219,0.08); font-size:13px;
}
.card-row:last-child { border-bottom:none; }
.card-label { color:var(--text-secondary); font-weight:600; }
.card-value { color:var(--text-primary); font-weight:600; text-align:right; }
.card-total {
    display:flex; justify-content:space-between; align-items:center;
    background:rgba(26,86,219,0.06); border:1px solid var(--border);
    border-radius:10px; padding:12px 14px; margin-bottom:16px; font-weight:700;
}
.total-label { color:var(--text-secondary); font-size:12px; text-transform:uppercase; letter-spacing:.5px; }
.total-amount {
    font-size:20px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.view-btn {
    display:flex; align-items:center; justify-content:center; gap:8px; width:100%;
    padding:11px; border-radius:10px; border:none;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#ffffff; font-weight:700; font-size:13px; cursor:pointer;
    text-decoration:none; transition:all .3s;
    box-shadow:0 4px 12px rgba(26,86,219,0.25);
}
.view-btn:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(26,86,219,0.35); }

/* ── empty state ── */
.empty-state {
    text-align:center; padding:80px 20px; color:var(--text-secondary);
}
.empty-state i {
    font-size:60px; opacity:.4; margin-bottom:20px; display:block;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.empty-state h3 { font-size:20px; margin-bottom:10px; color:var(--text-primary); }
.empty-state p  { font-size:14px; }

/* ── responsive ── */
@media(max-width:768px){
    .hamburger-btn{display:block}
    .sidebar{transform:translateX(-100%)}
    .sidebar.active{transform:translateX(0)}
    .main{margin-left:0;width:100%;padding:80px 20px 20px}
    .receipts-grid{grid-template-columns:1fr}
    .filter-bar{flex-direction:column}
    .filter-bar input,.filter-bar select{width:100%}
}
</style>
</head>
<body>

<button class="hamburger-btn" id="hamburger-btn"><span></span><span></span><span></span></button>

<button class="notification-bell" id="notification-bell">
    <i class="fas fa-bell"></i>
    <?php if($unreadNotifications>0):?>
        <span class="bell-badge"><?=$unreadNotifications?></span>
    <?php endif;?>
</button>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-tools"></i><span>MotorService</span></div>
    <nav>
        <a href="customer_dashboard.php"  <?= $currentPage==='customer_dashboard.php' ?'class="active"':'' ?>><i class="fas fa-home"></i> Home</a>
        <a href="customer_my_bookings.php"<?= $currentPage==='customer_my_bookings.php'?'class="active"':'' ?>><i class="fas fa-calendar-alt"></i> My Bookings</a>
        <a href="customer_history.php"    <?= $currentPage==='customer_history.php'   ?'class="active"':'' ?>><i class="fas fa-history"></i> History</a>
        <a href="customer_messages.php"   <?= $currentPage==='customer_messages.php'  ?'class="active"':'' ?>>
            <i class="fas fa-envelope"></i> Messages
            <?php if($messages>0):?><span class="notification-badge"><?=$messages?></span><?php endif;?>
        </a>
        <a href="customer_my_receipts.php" class="active"><i class="fas fa-receipt"></i> My Receipts</a>
        <a href="customer_address.php"    <?= $currentPage==='customer_address.php'   ?'class="active"':'' ?>><i class="fas fa-map-marker-alt"></i> Address</a>
        <a href="customer_profile.php"    <?= $currentPage==='customer_profile.php'   ?'class="active"':'' ?>><i class="fas fa-user-circle"></i> Profile</a>
        <a href="customer_change_password.php"<?= $currentPage==='customer_change_password.php'?'class="active"':'' ?>><i class="fas fa-lock"></i> Change Password</a>
        <a href="../../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<main class="main">
    <div class="page-header"><i class="fas fa-receipt"></i> My Receipts</div>
    <p class="page-sub">View and print receipts for all your bookings.</p>

    <!-- Filter bar -->
    <div class="filter-bar">
        <input type="text" id="search-input" placeholder="🔍  Search by service, brand, or booking #…">
        <select id="status-filter">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="preparing">Preparing</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <h3>No bookings yet</h3>
            <p>Your receipts will appear here once you make a booking.</p>
        </div>
    <?php else: ?>
        <div class="receipts-grid" id="receipts-grid">
            <?php foreach ($bookings as $b): ?>
            <div class="receipt-card"
                 data-search="<?= strtolower(htmlspecialchars(
                     $b['id'].' '.$b['brand'].' '.$b['vehicle_type'].' '.($b['service_name']??$b['service_type'])
                 )) ?>"
                 data-status="<?= htmlspecialchars($b['status']) ?>">

                <div class="card-top">
                    <span class="booking-ref">#<?= htmlspecialchars($b['id']) ?></span>
                    <?= statusBadge($b['status']) ?>
                </div>

                <div class="card-body">
                    <div class="card-row">
                        <span class="card-label"><i class="fas fa-motorcycle" style="color:var(--primary);margin-right:5px;"></i> Motorcycle</span>
                        <span class="card-value"><?= htmlspecialchars($b['brand'].' '.$b['vehicle_type']) ?></span>
                    </div>
                    <div class="card-row">
                        <span class="card-label"><i class="fas fa-wrench" style="color:var(--primary);margin-right:5px;"></i> Service</span>
                        <span class="card-value"><?= htmlspecialchars($b['service_name'] ?? $b['service_type'] ?? 'N/A') ?></span>
                    </div>
                    <div class="card-row">
                        <span class="card-label"><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:5px;"></i> Location</span>
                        <span class="card-value"><?= ucfirst($b['service_location'] ?? 'N/A') ?></span>
                    </div>
                    <div class="card-row">
                        <span class="card-label"><i class="fas fa-calendar" style="color:var(--primary);margin-right:5px;"></i> Schedule</span>
                        <span class="card-value"><?= date('M d, Y g:i A', strtotime($b['schedule'])) ?></span>
                    </div>
                    <div class="card-row">
                        <span class="card-label"><i class="fas fa-user-cog" style="color:var(--primary);margin-right:5px;"></i> Mechanic</span>
                        <span class="card-value"><?= htmlspecialchars($b['mechanic_name'] ?? 'Not assigned') ?></span>
                    </div>
                </div>

                <div class="card-total">
                    <span class="total-label">Total Amount</span>
                    <span class="total-amount">₱<?= number_format((float)$b['total_price'], 2) ?></span>
                </div>

                <a href="customer_receipt.php?id=<?= $b['id'] ?>" class="view-btn">
                    <i class="fas fa-receipt"></i> View &amp; Print Receipt
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
// Hamburger
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
document.getElementById('notification-bell').addEventListener('click', () => {
    window.location.href = 'customer_notifications.php';
});

// Search + filter
const searchInput  = document.getElementById('search-input');
const statusFilter = document.getElementById('status-filter');
const cards        = document.querySelectorAll('.receipt-card');

function filterCards() {
    const q      = searchInput.value.toLowerCase();
    const status = statusFilter.value;
    cards.forEach(card => {
        const matchSearch = !q      || card.dataset.search.includes(q);
        const matchStatus = !status || card.dataset.status === status;
        card.style.display = (matchSearch && matchStatus) ? '' : 'none';
    });
}
searchInput.addEventListener('input',  filterCards);
statusFilter.addEventListener('change', filterCards);
</script>
</body>
</html>