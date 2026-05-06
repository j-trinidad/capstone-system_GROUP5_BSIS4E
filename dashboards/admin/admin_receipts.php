<?php
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

// Filters
$search    = trim($_GET['search']    ?? '');
$statusF   = $_GET['status']         ?? '';
$startDate = $_GET['start_date']     ?? '';
$endDate   = $_GET['end_date']       ?? '';

// Build query
$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where   .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ? OR b.brand LIKE ? OR b.id = ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like, $like, (int)$search]);
}
if ($statusF !== '') {
    $where .= " AND b.status = ?";
    $params[] = $statusF;
}
if ($startDate !== '') {
    $where .= " AND DATE(b.created_at) >= ?";
    $params[] = date('Y-m-d', strtotime($startDate));
}
if ($endDate !== '') {
    $where .= " AND DATE(b.created_at) <= ?";
    $params[] = date('Y-m-d', strtotime($endDate));
}

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
        b.labor_fee,
        b.service_fee,
        b.parts_total,
        b.created_at,
        b.completed_at,
        s.name AS service_name,
        CONCAT(c.first_name,' ',c.last_name) AS customer_name,
        CONCAT(u.first_name,' ',u.last_name) AS mechanic_name
    FROM bookings b
    LEFT JOIN services s ON s.service_key = b.service_type
    LEFT JOIN users c    ON b.customer_id  = c.id
    LEFT JOIN users u    ON b.mechanic_id  = u.id
    $where
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);

function adminStatusBadge($status) {
    $map = [
        'pending'                  => ['label'=>'Pending',       'color'=>'#856404','bg'=>'rgba(255,243,205,0.15)','border'=>'#856404'],
        'preparing'                => ['label'=>'Preparing',     'color'=>'#60a5fa','bg'=>'rgba(59,130,246,0.15)', 'border'=>'#3b82f6'],
        'in_progress'              => ['label'=>'In Progress',   'color'=>'#34d399','bg'=>'rgba(52,211,153,0.15)', 'border'=>'#10b981'],
        'completed'                => ['label'=>'Completed',     'color'=>'#00d084','bg'=>'rgba(0,208,132,0.15)',  'border'=>'#00d084'],
        'cancelled'                => ['label'=>'Cancelled',     'color'=>'#ff4757','bg'=>'rgba(255,71,87,0.15)',  'border'=>'#ff4757'],
        'awaiting_customer_action' => ['label'=>'Action Needed', 'color'=>'#fb923c','bg'=>'rgba(251,146,60,0.15)','border'=>'#f97316'],
        'assigned'                 => ['label'=>'Assigned',      'color'=>'#a3e635','bg'=>'rgba(163,230,53,0.15)','border'=>'#84cc16'],
    ];
    $s = $map[$status] ?? ['label'=>ucfirst($status),'color'=>'#b0b8d4','bg'=>'rgba(176,184,212,0.1)','border'=>'#b0b8d4'];
    return "<span style='display:inline-block;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;
                         text-transform:uppercase;letter-spacing:.5px;background:{$s['bg']};color:{$s['color']};
                         border:1px solid {$s['border']};'>{$s['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Receipts — MotorService Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#ff8c00;--secondary:#e52e71;--dark-bg:#0a0e27;--card-bg:#1a1f3a;--border:rgba(255,140,0,0.2);--text-primary:#fff;--text-secondary:#b0b8d4;--success:#00d084;--error:#ff4757}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;font-family:'Outfit',sans-serif;background:linear-gradient(135deg,var(--dark-bg),#1a1f3a);color:var(--text-primary);overflow:hidden}
a{color:inherit;text-decoration:none}

/* hamburger */
.hamburger-btn{display:none;position:fixed;top:20px;left:20px;z-index:1001;background:linear-gradient(135deg,var(--primary),var(--secondary));border:none;width:50px;height:50px;border-radius:12px;cursor:pointer;box-shadow:0 4px 15px rgba(255,140,0,.4);transition:all .3s}
.hamburger-btn:hover{transform:scale(1.05)}
.hamburger-btn span{display:block;width:25px;height:3px;background:#1a1f3a;margin:5px auto;border-radius:2px;transition:all .3s}
.hamburger-btn.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
.hamburger-btn.active span:nth-child(2){opacity:0}
.hamburger-btn.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}

/* overlay */
.sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:999;opacity:0;transition:opacity .3s}
.sidebar-overlay.active{display:block;opacity:1}

/* sidebar */
.sidebar{position:fixed;top:0;left:0;width:260px;height:100vh;overflow-y:auto;background:linear-gradient(180deg,#0f1419 0%,#1a1f3a 100%);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:25px 20px;z-index:1000;transition:transform .3s}
.sidebar::-webkit-scrollbar{width:6px}.sidebar::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
.logo{font-size:1.6rem;font-weight:700;background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:35px;letter-spacing:.5px;display:flex;align-items:center;gap:10px}
.sidebar nav{display:flex;flex-direction:column;gap:8px;flex:1}
.sidebar nav a{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;color:var(--text-secondary);font-weight:600;transition:all .3s;font-size:14px}
.sidebar nav a:hover,.sidebar nav a.active{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#1a1f3a;transform:translateX(5px)}
.sidebar nav a i{width:20px;text-align:center}
.sidebar .footer{margin-top:auto;font-size:12px;color:var(--text-secondary);text-align:center;padding-top:20px;border-top:1px solid var(--border)}

/* main */
.main{margin-left:260px;padding:40px;width:calc(100% - 260px);height:100vh;overflow-y:auto;background:linear-gradient(135deg,var(--dark-bg),#1a1f3a)}
.main::-webkit-scrollbar{width:8px}.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

h1{font-size:32px;font-weight:700;margin-bottom:6px;background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:flex;align-items:center;gap:15px}
.page-sub{color:var(--text-secondary);font-size:14px;margin-bottom:30px}

/* filter bar */
.filter-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:18px 20px;margin-bottom:28px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:6px;flex:1;min-width:160px}
.filter-group label{font-size:11px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;font-weight:700}
.filter-group input,.filter-group select{padding:9px 12px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);font-size:13px;font-family:'Outfit',sans-serif;transition:.3s}
.filter-group input:focus,.filter-group select:focus{outline:none;border-color:var(--primary);background:rgba(255,140,0,.08)}
.filter-group select option{background:var(--card-bg)}
.filter-actions{display:flex;gap:8px;align-items:flex-end;flex-shrink:0}
.btn{padding:10px 18px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#1a1f3a;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:13px;font-family:'Outfit',sans-serif;transition:all .3s;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(255,140,0,.3)}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text-secondary)}
.btn-ghost:hover{background:rgba(255,140,0,.1);border-color:var(--primary);color:var(--primary)}

/* summary bar */
.summary-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:28px}
.summary-card{background:linear-gradient(135deg,rgba(255,140,0,.08),rgba(229,46,113,.08));border:1px solid var(--border);border-radius:10px;padding:16px 18px;text-align:center}
.summary-card .s-num{font-size:24px;font-weight:700;color:var(--text-primary)}
.summary-card .s-lbl{font-size:11px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-top:4px}

/* table */
.table-container{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.3)}
table{width:100%;border-collapse:collapse}
thead{background:linear-gradient(90deg,rgba(255,140,0,.2),rgba(229,46,113,.2));border-bottom:2px solid var(--border)}
th{padding:14px 16px;text-align:left;color:var(--text-secondary);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13px}
tbody tr{transition:.2s;cursor:pointer}
tbody tr:hover{background:rgba(255,140,0,.07)}
tbody tr:last-child td{border-bottom:none}
.amount{color:var(--success);font-weight:700}
.empty-state{text-align:center;padding:50px 20px;color:var(--text-secondary)}
.empty-state i{font-size:52px;margin-bottom:16px;opacity:.3;display:block}

/* view btn inside table */
.view-receipt-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:7px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#1a1f3a;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.4px;transition:all .3s;white-space:nowrap;border:none;cursor:pointer;font-family:'Outfit',sans-serif}
.view-receipt-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(255,140,0,.4)}

/* ── RECEIPT MODAL ── */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);justify-content:center;align-items:flex-start;z-index:9999;overflow-y:auto;padding:30px 20px}
.modal.show{display:flex}
.modal-shell{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;max-width:760px;width:100%;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.8);overflow:hidden;animation:slideUp .35s ease}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

/* modal top bar */
.modal-topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:rgba(0,0,0,.25);border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap}
.modal-topbar .receipt-title{font-weight:700;font-size:15px;color:var(--text-primary);display:flex;align-items:center;gap:8px}
.modal-actions{display:flex;gap:8px;align-items:center}
.modal-close-btn{background:transparent;border:1px solid var(--border);color:var(--text-secondary);width:34px;height:34px;border-radius:8px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:.3s}
.modal-close-btn:hover{color:var(--error);border-color:var(--error);background:rgba(255,71,87,.1)}
.print-modal-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#1a1f3a;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:12px;font-family:'Outfit',sans-serif;text-transform:uppercase;letter-spacing:.4px;transition:.3s}
.print-modal-btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(255,140,0,.4)}

/* receipt content inside modal */
.receipt-header{background:linear-gradient(135deg,var(--primary),var(--secondary));padding:22px 28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.receipt-header .brand{font-size:19px;font-weight:700;color:#fff;display:flex;align-items:center;gap:8px}
.receipt-header .ref{text-align:right}
.receipt-header .ref .num{font-size:24px;font-weight:700;color:#fff}
.receipt-header .ref .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.85)}
.status-strip{background:rgba(255,255,255,.04);border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.status-date{font-size:12px;color:var(--text-secondary)}
.receipt-body{padding:24px 28px}
.section-title{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:7px;border-bottom:1px solid var(--border);padding-bottom:7px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px}
.info-block{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:9px;padding:14px}
.info-row{display:flex;justify-content:space-between;align-items:flex-start;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px;gap:8px}
.info-row:last-child{border-bottom:none}
.info-row .lbl{color:var(--text-secondary);flex-shrink:0}
.info-row .val{color:var(--text-primary);font-weight:600;text-align:right}
.fees-table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12px}
.fees-table th{text-align:left;padding:8px 12px;background:rgba(255,140,0,.1);color:var(--text-secondary);font-size:10px;text-transform:uppercase;letter-spacing:.5px;font-weight:700}
.fees-table th:last-child,.fees-table td:last-child{text-align:right}
.fees-table td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.05)}
.fees-table tbody tr:last-child td{border-bottom:none}
.fees-table .green{color:var(--success);font-weight:700}
.total-box{background:linear-gradient(135deg,rgba(255,140,0,.12),rgba(229,46,113,.12));border:1px solid var(--border);border-radius:10px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}
.total-box .lbl{color:var(--text-secondary);font-size:12px;text-transform:uppercase;letter-spacing:.5px;font-weight:700}
.total-box .val{font-size:26px;font-weight:700;background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.notes-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:14px;margin-top:16px;font-size:12px;color:var(--text-secondary);line-height:1.7}
.receipt-footer{background:rgba(0,0,0,.2);border-top:1px solid var(--border);padding:12px 28px;text-align:center;font-size:11px;color:var(--text-secondary)}

/* loading skeleton */
.skeleton{background:linear-gradient(90deg,rgba(255,255,255,.05) 25%,rgba(255,255,255,.1) 50%,rgba(255,255,255,.05) 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;border-radius:6px;height:16px;margin:6px 0}
@keyframes shimmer{from{background-position:200% 0}to{background-position:-200% 0}}

/* print receipt from modal — compact 1-page layout */
@media print{
    @page{size:A4 portrait;margin:10mm 12mm}
    *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;box-sizing:border-box!important}

    /* hide EVERYTHING except the modal */
    body>*{display:none!important}
    .modal{display:block!important}

    body{background:#fff!important;color:#111!important;font-size:11px!important;line-height:1.3!important;margin:0!important;padding:0!important}

    /* flatten modal to fill page */
    .modal{position:static!important;background:none!important;padding:0!important;overflow:visible!important}
    .modal-shell{border:none!important;box-shadow:none!important;max-width:100%!important;width:100%!important;animation:none!important;border-radius:0!important;max-height:none!important;overflow:visible!important}
    .modal-topbar,.modal-close-btn,.print-modal-btn{display:none!important}

    /* receipt header */
    .receipt-header{background:#ff8c00!important;border-radius:0!important;padding:10px 16px!important}
    .receipt-header .brand{font-size:15px!important;color:#fff!important}
    .receipt-header .ref .num{font-size:17px!important;color:#fff!important}
    .receipt-header .ref .lbl{color:rgba(255,255,255,.85)!important;font-size:9px!important}

    /* status strip */
    .status-strip{background:#f8f8f8!important;border-color:#ddd!important;padding:6px 16px!important}
    .status-date{font-size:10px!important}

    /* body */
    .receipt-body{padding:10px 16px!important;background:#fff!important}
    .info-grid{grid-template-columns:1fr 1fr!important;gap:10px!important;margin-bottom:10px!important}
    .info-block{background:#f8f9fa!important;border-color:#ddd!important;padding:8px 10px!important;border-radius:5px!important}
    .info-row{padding:3px 0!important;font-size:10px!important}
    .info-row .lbl{color:#555!important}
    .info-row .val{color:#111!important;font-size:10px!important}
    .section-title{color:#ff8c00!important;border-color:#ffd080!important;font-size:9px!important;padding-bottom:4px!important;margin-bottom:6px!important}
    .fees-table{margin-bottom:8px!important;font-size:10px!important}
    .fees-table th{background:#fff3e0!important;color:#555!important;padding:5px 8px!important;font-size:9px!important}
    .fees-table td{color:#111!important;border-color:#eee!important;padding:5px 8px!important}
    .fees-table .green{color:#157a15!important}
    .total-box{background:#fff3e0!important;border-color:#ffd080!important;padding:8px 12px!important;border-radius:6px!important}
    .total-box .lbl{color:#555!important;font-size:10px!important}
    .total-box .val{-webkit-text-fill-color:#ff8c00!important;color:#ff8c00!important;font-size:18px!important}
    .notes-box{background:#f8f8f8!important;color:#555!important;padding:8px 10px!important;font-size:10px!important;margin-top:8px!important}
    .receipt-footer{background:#f8f8f8!important;border-color:#ddd!important;color:#666!important;padding:6px 16px!important;font-size:9px!important}

    /* no page breaks inside sections */
    .receipt-header,.status-strip,.receipt-body,.receipt-footer{page-break-inside:avoid!important}
    html,body{height:auto!important;overflow:visible!important}
}

@media(max-width:1024px){.sidebar{width:220px}.main{margin-left:220px;width:calc(100% - 220px);padding:30px 20px}}
@media(max-width:768px){
    .hamburger-btn{display:block}
    .sidebar{transform:translateX(-100%)}
    .sidebar.active{transform:translateX(0)}
    .sidebar nav{padding-bottom:20px}
    .main{margin-left:0;width:100%;padding:80px 20px 20px 20px}
    h1{font-size:22px;margin-top:10px}
    .filter-bar{gap:10px}
    .filter-group{min-width:100%}
    .filter-actions{width:100%;flex-wrap:wrap}
    .filter-actions .btn{flex:1;text-align:center;justify-content:center}
    .summary-bar{grid-template-columns:repeat(2,1fr);gap:10px}
    .table-container{overflow-x:auto}
    table{font-size:11px;min-width:650px}
    th,td{padding:9px 10px}
    .info-grid{grid-template-columns:1fr}
    .modal{padding:15px 10px}
    .modal-shell{border-radius:12px}
    .receipt-body{padding:16px 18px}
    .total-box .val{font-size:20px}
}
@media(max-width:480px){
    .main{padding:75px 15px 15px 15px}
    .summary-bar{grid-template-columns:1fr}
}
</style>
</head>
<body>

<button class="hamburger-btn" id="hamburger-btn">
    <span></span><span></span><span></span>
</button>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="logo"><i class="fas fa-tools"></i><span>MotorService</span></div>
    <nav>
        <a href="admin_profile.php"         class="<?= $currentPage=='admin_profile.php'         ?'active':'' ?>"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="admin_dashboard.php"       class="<?= $currentPage=='admin_dashboard.php'       ?'active':'' ?>"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="activity_log.php"          class="<?= $currentPage=='activity_log.php'          ?'active':'' ?>"><i class="fas fa-clipboard-list"></i> Activity Log</a>
        <a href="messages.php"              class="<?= $currentPage=='messages.php'              ?'active':'' ?>"><i class="fas fa-comments"></i> Messages</a>
        <a href="sales.php"                 class="<?= $currentPage=='sales.php'                 ?'active':'' ?>"><i class="fas fa-chart-bar"></i> Sales</a>
        <a href="admin_receipts.php"        class="<?= $currentPage=='admin_receipts.php'        ?'active':'' ?>"><i class="fas fa-receipt"></i> All Receipts</a>
        <a href="admin_change_password.php" class="<?= $currentPage=='admin_change_password.php' ?'active':'' ?>"><i class="fas fa-key"></i> Change Password</a>
        <a href="../../logout.php" style="margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<main class="main">
    <h1><i class="fas fa-receipt"></i> All Receipts</h1>
    <p class="page-sub">View and print receipts for all customer bookings.</p>

    <!-- Filter bar -->
    <div class="filter-bar">
        <form method="GET" style="display:contents;">
            <div class="filter-group">
                <label><i class="fas fa-search" style="margin-right:4px;"></i>Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Name, booking #, brand…">
            </div>
            <div class="filter-group" style="max-width:160px;">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach(['pending','preparing','assigned','in_progress','awaiting_customer_action','completed','cancelled'] as $st): ?>
                    <option value="<?=$st?>" <?=$statusF===$st?'selected':''?>>
                        <?= ucwords(str_replace('_',' ',$st)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group" style="max-width:160px;">
                <label>From Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="filter-group" style="max-width:160px;">
                <label>To Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
                <a href="admin_receipts.php" class="btn btn-ghost"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Summary bar -->
    <?php
    $total    = count($bookings);
    $completed = 0; $pending = 0; $cancelled = 0; $sumRevenue = 0;
    foreach ($bookings as $bk) {
        if ($bk['status'] === 'completed')  { $completed++; $sumRevenue += $bk['total_price']; }
        if ($bk['status'] === 'pending')     $pending++;
        if ($bk['status'] === 'cancelled')   $cancelled++;
    }
    ?>
    <div class="summary-bar">
        <div class="summary-card"><div class="s-num"><?= $total ?></div><div class="s-lbl">Total Records</div></div>
        <div class="summary-card"><div class="s-num" style="color:var(--success);"><?= $completed ?></div><div class="s-lbl">Completed</div></div>
        <div class="summary-card"><div class="s-num" style="color:#fbbf24;"><?= $pending ?></div><div class="s-lbl">Pending</div></div>
        <div class="summary-card"><div class="s-num" style="color:var(--error);"><?= $cancelled ?></div><div class="s-lbl">Cancelled</div></div>
        <div class="summary-card"><div class="s-num" style="color:var(--primary);">₱<?= number_format($sumRevenue, 2) ?></div><div class="s-lbl">Revenue Shown</div></div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <?php if (!empty($bookings)): ?>
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Customer</th>
                    <th>Mechanic</th>
                    <th>Service</th>
                    <th>Vehicle</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:center;">Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $bk):
                    $svcLabel = $bk['service_name'] ?? ucwords(str_replace('_',' ',$bk['service_type']));
                ?>
                <tr onclick="openReceipt(<?= $bk['id'] ?>)">
                    <td style="color:var(--primary);font-weight:700;">#<?= str_pad($bk['id'],5,'0',STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($bk['customer_name']) ?></td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($bk['mechanic_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($svcLabel) ?></td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($bk['brand'].' '.$bk['vehicle_type']) ?></td>
                    <td style="color:var(--text-secondary);"><?= date('M d, Y', strtotime($bk['schedule'])) ?></td>
                    <td><?= adminStatusBadge($bk['status']) ?></td>
                    <td class="amount" style="text-align:right;">₱<?= number_format((float)$bk['total_price'],2) ?></td>
                    <td style="text-align:center;" onclick="event.stopPropagation()">
                        <button class="view-receipt-btn" onclick="openReceipt(<?= $bk['id'] ?>)">
                            <i class="fas fa-receipt"></i> View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-receipt"></i>
            <p>No bookings found matching the selected filters.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Receipt Modal -->
<div class="modal" id="receiptModal">
    <div class="modal-shell" id="modalShell">

        <!-- Modal top bar -->
        <div class="modal-topbar">
            <div class="receipt-title"><i class="fas fa-receipt" style="color:var(--primary);"></i> <span id="modalTitle">Loading…</span></div>
            <div class="modal-actions">
                <button class="print-modal-btn" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print / PDF
                </button>
                <button class="modal-close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Dynamic receipt content -->
        <div id="receiptContent">
            <!-- Skeleton loader -->
            <div style="padding:28px;" id="skeletonLoader">
                <div class="skeleton" style="width:40%;height:20px;"></div>
                <div class="skeleton" style="width:70%;margin-top:16px;"></div>
                <div class="skeleton" style="width:55%;"></div>
                <div class="skeleton" style="width:80%;margin-top:16px;"></div>
                <div class="skeleton" style="width:60%;"></div>
                <div class="skeleton" style="width:75%;margin-top:16px;"></div>
            </div>
        </div>

    </div>
</div>

<script>
// Hamburger sidebar toggle
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

// Booking data passed from PHP
const allBookings = <?= json_encode(array_column($bookings, null, 'id')) ?>;

const serviceTypes = {
    general_maintenance: 'General Maintenance', oil_change: 'Oil Change',
    brake_inspection: 'Brake Inspection', tire_replacement: 'Tire Replacement',
    battery_replacement: 'Battery Replacement', engine_diagnostic: 'Engine Diagnostic',
    chain_replacement: 'Chain Replacement', suspension_repair: 'Suspension Repair',
    electrical_repair: 'Electrical Repair'
};

const statusMap = {
    pending:                  {label:'Pending',       color:'#856404', bg:'#fff3cd'},
    preparing:                {label:'Preparing',     color:'#004085', bg:'#cce5ff'},
    in_progress:              {label:'In Progress',   color:'#155724', bg:'#d4edda'},
    completed:                {label:'Completed',     color:'#155724', bg:'#d4edda'},
    cancelled:                {label:'Cancelled',     color:'#721c24', bg:'#f8d7da'},
    awaiting_customer_action: {label:'Action Needed', color:'#e65100', bg:'#ffe0b2'},
    assigned:                 {label:'Assigned',      color:'#1b5e20', bg:'#c8e6c9'},
};

let currentBookingId = null;

function openReceipt(id) {
    currentBookingId = id;
    const modal = document.getElementById('receiptModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    const bk = allBookings[id];
    if (!bk) return;

    document.getElementById('modalTitle').textContent = 'Receipt #' + String(bk.id).padStart(6,'0');

    // Fetch full receipt via AJAX (for parts breakdown etc.)
    fetch('admin_receipt_ajax.php?id=' + id)
        .then(r => r.ok ? r.text() : null)
        .then(html => {
            if (html) {
                document.getElementById('receiptContent').innerHTML = html;
            } else {
                // Fallback: render from PHP data we already have
                renderReceiptFromData(bk);
            }
        })
        .catch(() => renderReceiptFromData(bk));
}

function renderReceiptFromData(bk) {
    const svcLabel = bk.service_name || (serviceTypes[bk.service_type] || bk.service_type.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase()));
    const status   = statusMap[bk.status] || {label: bk.status, color:'#b0b8d4', bg:'rgba(176,184,212,.2)'};

    const completedDate = bk.completed_at
        ? 'Completed: ' + new Date(bk.completed_at).toLocaleString('en-US',{month:'long',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'})
        : 'Scheduled: ' + new Date(bk.schedule).toLocaleString('en-US',{month:'long',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});

    const laborFee   = parseFloat(bk.labor_fee   || 0);
    const serviceFee = parseFloat(bk.service_fee || 0);
    const partsTotal = parseFloat(bk.parts_total || 0);
    const total      = parseFloat(bk.total_price || 0);

    const partsRow = partsTotal > 0
        ? `<tr><td><i class="fas fa-boxes" style="color:#ff8c00;margin-right:8px;width:15px;"></i>Parts &amp; Materials</td><td class="green">₱${partsTotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',')}</td></tr>`
        : '';

    const fmt = n => '₱' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');

    document.getElementById('receiptContent').innerHTML = `
        <div class="receipt-header">
            <div class="brand"><i class="fas fa-tools"></i> MotorService</div>
            <div class="ref">
                <div class="lbl">Service Receipt</div>
                <div class="num">#${String(bk.id).padStart(6,'0')}</div>
            </div>
        </div>
        <div class="status-strip">
            <span style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:${status.bg};color:${status.color};">${status.label}</span>
            <span class="status-date">${completedDate}</span>
        </div>
        <div class="receipt-body">
            <div class="info-grid">
                <div class="info-block">
                    <div class="section-title"><i class="fas fa-user"></i> Customer</div>
                    <div class="info-row"><span class="lbl">Name</span><span class="val">${bk.customer_name||'—'}</span></div>
                    <div class="info-row"><span class="lbl">Booking Date</span><span class="val">${new Date(bk.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</span></div>
                </div>
                <div class="info-block">
                    <div class="section-title"><i class="fas fa-cogs"></i> Service</div>
                    <div class="info-row"><span class="lbl">Mechanic</span><span class="val">${bk.mechanic_name||'Not assigned'}</span></div>
                    <div class="info-row"><span class="lbl">Service</span><span class="val">${svcLabel}</span></div>
                    <div class="info-row"><span class="lbl">Motorcycle</span><span class="val">${bk.brand} ${bk.vehicle_type}</span></div>
                    <div class="info-row"><span class="lbl">Location</span><span class="val">${(bk.service_location||'').charAt(0).toUpperCase()+(bk.service_location||'').slice(1)}</span></div>
                </div>
            </div>
            <div class="section-title"><i class="fas fa-file-invoice-dollar"></i> Fee Breakdown</div>
            <table class="fees-table">
                <thead><tr><th style="width:65%;">Description</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr><td><i class="fas fa-wrench" style="color:#ff8c00;margin-right:8px;width:15px;"></i>Labor Fee</td><td class="green">${fmt(laborFee)}</td></tr>
                    <tr><td><i class="fas fa-tools" style="color:#ff8c00;margin-right:8px;width:15px;"></i>Service Fee</td><td class="green">${fmt(serviceFee)}</td></tr>
                    ${partsRow}
                </tbody>
            </table>
            <div class="total-box">
                <span class="lbl"><i class="fas fa-peso-sign" style="margin-right:6px;"></i>Grand Total</span>
                <span class="val">${fmt(total)}</span>
            </div>
        </div>
        <div class="receipt-footer">
            <i class="fas fa-tools" style="color:#ff8c00;margin-right:5px;"></i>
            MotorService Admin View &nbsp;•&nbsp; Generated ${new Date().toLocaleString('en-US',{month:'long',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'})}
        </div>`;
}

function closeModal() {
    document.getElementById('receiptModal').classList.remove('show');
    document.body.style.overflow = '';
    currentBookingId = null;
}

function printReceipt() {
    window.print();
}

// Close on overlay click
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Escape key
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>