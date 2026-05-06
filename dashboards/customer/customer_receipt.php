<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id    = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    header("Location: customer_my_receipts.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        b.*,
        CONCAT(u.first_name, ' ', u.last_name) AS mechanic_name,
        s.name AS service_name
    FROM bookings b
    LEFT JOIN users u ON b.mechanic_id = u.id
    LEFT JOIN services s ON s.service_key = b.service_type
    WHERE b.id = ? AND b.customer_id = ?
    LIMIT 1
");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header("Location: customer_my_receipts.php");
    exit;
}

$partsList = [];
if (!empty($booking['parts'])) {
    $partsArray = json_decode($booking['parts'], true);
    if (is_array($partsArray)) {
        foreach ($partsArray as $part) {
            $partsList[] = ['name' => htmlspecialchars($part['name']), 'price' => (float)$part['price']];
        }
    }
}

$laborFee     = (float)($booking['labor_fee']   ?? 0);
$brandPrice   = (float)($booking['service_fee'] ?? 0);
$partsTotal   = (float)($booking['parts_total'] ?? 0);
$extraHomeFee = $booking['service_location'] === 'home' ? 150 : 0;
$totalAmount  = (float)($booking['total_price'] ?? 0);
if ($totalAmount <= 0) $totalAmount = $laborFee + $brandPrice + $partsTotal + $extraHomeFee;

$statusMap = [
    'pending'                  => ['label'=>'Pending',       'color'=>'#92400e','bg'=>'rgba(245,158,11,0.12)','border'=>'rgba(245,158,11,0.4)'],
    'preparing'                => ['label'=>'Preparing',     'color'=>'#1e40af','bg'=>'rgba(26,86,219,0.10)', 'border'=>'rgba(26,86,219,0.3)'],
    'in_progress'              => ['label'=>'In Progress',   'color'=>'#1e40af','bg'=>'rgba(26,86,219,0.12)', 'border'=>'rgba(26,86,219,0.35)'],
    'completed'                => ['label'=>'Completed',     'color'=>'#065f46','bg'=>'rgba(5,150,105,0.12)', 'border'=>'rgba(5,150,105,0.4)'],
    'cancelled'                => ['label'=>'Cancelled',     'color'=>'#991b1b','bg'=>'rgba(220,38,38,0.08)', 'border'=>'rgba(220,38,38,0.3)'],
    'awaiting_customer_action' => ['label'=>'Action Needed', 'color'=>'#92400e','bg'=>'rgba(217,119,6,0.10)', 'border'=>'rgba(217,119,6,0.4)'],
    'assigned'                 => ['label'=>'Assigned',      'color'=>'#065f46','bg'=>'rgba(5,150,105,0.10)', 'border'=>'rgba(5,150,105,0.3)'],
];
$statusInfo = $statusMap[$booking['status']] ?? ['label'=>ucfirst($booking['status']),'color'=>'#475569','bg'=>'rgba(71,85,105,0.08)','border'=>'rgba(71,85,105,0.2)'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt #<?= $booking_id ?> — MotorService</title>
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
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px;
}
a { color: inherit; text-decoration: none; }

.page-wrapper { width: 100%; max-width: 640px; }

/* ── TOP BAR ────────────────────────────────────────────────────────── */
.top-bar {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}
.back-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 10px;
    background: var(--card-bg); color: var(--primary);
    border: 1px solid var(--border); font-weight: 700; font-size: 13px;
    cursor: pointer; transition: all 0.25s;
    box-shadow: 0 2px 8px rgba(26,86,219,0.06);
}
.back-btn:hover {
    background: rgba(26,86,219,0.06);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(26,86,219,0.12);
}
.print-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 10px; border: none;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff; font-weight: 700; font-size: 13px; cursor: pointer;
    transition: all 0.25s; box-shadow: 0 4px 15px rgba(26,86,219,0.25);
    font-family: 'Outfit', sans-serif;
}
.print-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,86,219,0.35); }

/* ── RECEIPT CARD ───────────────────────────────────────────────────── */
.receipt-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 8px 40px rgba(26,86,219,0.12);
    animation: slideUp 0.4s ease;
}
@keyframes slideUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }

/* ── RECEIPT HEADER ─────────────────────────────────────────────────── */
.receipt-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #fff; padding: 28px 25px; text-align: center;
}
.receipt-logo     { font-size: 38px; margin-bottom: 8px; }
.receipt-title    { font-size: 22px; font-weight: 700; margin-bottom: 3px; }
.receipt-subtitle { font-size: 12px; opacity: 0.9; font-weight: 600; letter-spacing: 0.3px; }

/* ── RECEIPT BODY ───────────────────────────────────────────────────── */
.receipt-body { padding: 24px 26px; color: var(--text-primary); }

.ref-block {
    text-align: center; margin-bottom: 20px; padding-bottom: 18px;
    border-bottom: 2px solid var(--border);
}
.ref-label  { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ref-number {
    font-size: 30px; font-weight: 700;
    font-family: 'Courier New', monospace;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; letter-spacing: 2px;
}
.status-badge {
    display: inline-block; padding: 4px 14px; border-radius: 20px; margin-top: 8px;
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    background: <?= $statusInfo['bg'] ?>;
    color: <?= $statusInfo['color'] ?>;
    border: 1px solid <?= $statusInfo['border'] ?>;
}
.ref-date { font-size: 11px; color: var(--text-secondary); margin-top: 7px; }

/* ── SECTIONS ───────────────────────────────────────────────────────── */
.section { margin-bottom: 18px; }
.section-title {
    font-size: 10px; font-weight: 700; color: var(--primary);
    text-transform: uppercase; letter-spacing: 1.5px;
    margin-bottom: 10px; padding-bottom: 7px;
    border-bottom: 2px solid var(--border);
}
.info-row {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 8px 0; font-size: 13px; border-bottom: 1px solid rgba(26,86,219,0.07); gap: 10px;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--text-secondary); font-weight: 600; flex-shrink: 0; }
.info-value { color: var(--text-primary); font-weight: 600; text-align: right; }

/* ── PRICING ────────────────────────────────────────────────────────── */
.pricing-divider { height: 1px; background: var(--border); margin: 18px 0; }

.price-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; font-size: 13px; border-bottom: 1px solid rgba(26,86,219,0.07);
}
.price-item:last-child { border-bottom: none; }
.price-label  { color: var(--text-secondary); font-weight: 600; }
.price-amount {
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.price-item.total {
    padding: 13px 18px; font-size: 16px; font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px; margin-top: 12px;
    display: flex; justify-content: space-between;
    box-shadow: 0 4px 15px rgba(26,86,219,0.2);
}
.price-item.total .price-label  { color: #fff; -webkit-text-fill-color: #fff; font-size: 15px; }
.price-item.total .price-amount { color: #fff; -webkit-text-fill-color: #fff; font-size: 18px; }

/* ── FOOTER ─────────────────────────────────────────────────────────── */
.receipt-footer {
    background: rgba(26,86,219,0.03);
    border-top: 1px solid var(--border);
    padding: 16px 26px; text-align: center;
}
.footer-note { font-size: 12px; color: var(--text-secondary); line-height: 1.6; }

/* ── PRINT ──────────────────────────────────────────────────────────── */
@media print {
    @page { size: A4; margin: 10mm 12mm; }
    html, body {
        background: #fff !important;
        display: block !important;
        padding: 0 !important; margin: 0 !important;
        font-size: 11px !important;
    }
    .page-wrapper { max-width: 100% !important; }
    .top-bar { display: none !important; }
    .receipt-container { box-shadow: none !important; border-radius: 0 !important; animation: none !important; border: none !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .receipt-header { padding: 14px 20px !important; }
    .receipt-logo   { font-size: 24px !important; margin-bottom: 4px !important; }
    .receipt-title  { font-size: 16px !important; margin-bottom: 2px !important; }
    .receipt-subtitle { font-size: 10px !important; }
    .receipt-body   { padding: 14px 20px !important; }
    .ref-block      { margin-bottom: 10px !important; padding-bottom: 10px !important; }
    .ref-number     { font-size: 22px !important; }
    .ref-date       { font-size: 9px !important; }
    .section        { margin-bottom: 10px !important; }
    .section-title  { font-size: 9px !important; margin-bottom: 5px !important; padding-bottom: 4px !important; }
    .info-row       { padding: 4px 0 !important; font-size: 11px !important; }
    .pricing-divider{ margin: 10px 0 !important; }
    .price-item     { padding: 4px 0 !important; font-size: 11px !important; }
    .price-item.total { padding: 9px 14px !important; font-size: 13px !important; margin-top: 7px !important; border-radius: 6px !important; }
    .price-item.total .price-amount { font-size: 14px !important; }
    .receipt-footer { padding: 10px 20px !important; }
    .footer-note    { font-size: 9px !important; }
}

/* ── MOBILE ─────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
    html, body { padding: 20px 15px; }
    .receipt-header { padding: 22px 18px; }
    .receipt-body   { padding: 18px 18px; }
    .top-bar { flex-direction: column; align-items: stretch; }
    .back-btn, .print-btn { justify-content: center; }
}
</style>
</head>
<body>
<div class="page-wrapper">

    <div class="top-bar">
        <a href="customer_my_receipts.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Go to My Receipts
        </a>
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
    </div>

    <div class="receipt-container">

        <!-- HEADER -->
        <div class="receipt-header">
            <div class="receipt-logo"><i class="fas fa-motorcycle"></i></div>
            <div class="receipt-title">Service Receipt</div>
            <div class="receipt-subtitle">MotorService &nbsp;|&nbsp; Official Booking Receipt</div>
        </div>

        <!-- BODY -->
        <div class="receipt-body">

            <!-- REFERENCE BLOCK -->
            <div class="ref-block">
                <div class="ref-label">Booking Reference</div>
                <div class="ref-number">#<?= htmlspecialchars($booking_id) ?></div>
                <div><span class="status-badge"><?= $statusInfo['label'] ?></span></div>
                <div class="ref-date">Booked on: <?= date('F d, Y h:i A', strtotime($booking['created_at'])) ?></div>
            </div>

            <!-- MOTORCYCLE & SERVICE -->
            <div class="section">
                <div class="section-title">🏍️ Motorcycle &amp; Service</div>
                <div class="info-row">
                    <span class="info-label">Motorcycle Brand</span>
                    <span class="info-value"><?= htmlspecialchars($booking['brand'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vehicle Type</span>
                    <span class="info-value"><?= htmlspecialchars($booking['vehicle_type'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Service</span>
                    <span class="info-value"><?= htmlspecialchars($booking['service_name'] ?? $booking['service_type'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($booking['tire_size'])): ?>
                <div class="info-row">
                    <span class="info-label">Tire Size</span>
                    <span class="info-value"><?= htmlspecialchars($booking['tire_size']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- SERVICE LOCATION -->
            <div class="section">
                <div class="section-title">📍 Service Location</div>
                <div class="info-row">
                    <span class="info-label">Type</span>
                    <span class="info-value"><?= ucfirst($booking['service_location'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($booking['service_address'])): ?>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?= htmlspecialchars($booking['service_address']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- MECHANIC & SCHEDULE -->
            <div class="section">
                <div class="section-title">📅 Mechanic &amp; Schedule</div>
                <div class="info-row">
                    <span class="info-label">Mechanic</span>
                    <span class="info-value"><?= htmlspecialchars($booking['mechanic_name'] ?? 'Not yet assigned') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date &amp; Time</span>
                    <span class="info-value"><?= date('M d, Y g:i A', strtotime($booking['schedule'])) ?></span>
                </div>
                <?php if (!empty($booking['note'])): ?>
                <div class="info-row">
                    <span class="info-label">Notes</span>
                    <span class="info-value"><?= htmlspecialchars($booking['note']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="pricing-divider"></div>

            <!-- PAYMENT BREAKDOWN -->
            <div class="section">
                <div class="section-title">💰 Payment Breakdown</div>
                <?php if ($laborFee > 0): ?>
                <div class="price-item">
                    <span class="price-label">🔧 Labor Fee</span>
                    <span class="price-amount">₱<?= number_format($laborFee, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($brandPrice > 0): ?>
                <div class="price-item">
                    <span class="price-label">📦 Package / Brand Fee</span>
                    <span class="price-amount">₱<?= number_format($brandPrice, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php foreach ($partsList as $part): ?>
                <div class="price-item">
                    <span class="price-label">🛠️ <?= $part['name'] ?></span>
                    <span class="price-amount">₱<?= number_format($part['price'], 2) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($extraHomeFee > 0): ?>
                <div class="price-item">
                    <span class="price-label">🏠 Home Service Fee</span>
                    <span class="price-amount">₱<?= number_format($extraHomeFee, 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="price-item total">
                    <span class="price-label">TOTAL AMOUNT</span>
                    <span class="price-amount">₱<?= number_format($totalAmount, 2) ?></span>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="receipt-footer">
            <div class="footer-note">
                <p>Thank you for choosing MotorService! &nbsp;•&nbsp; Payment is collected after service completion.</p>
                <p style="margin-top:4px;">Receipt generated on <?= date('F d, Y h:i A') ?></p>
            </div>
        </div>

    </div>
</div>
</body>
</html>