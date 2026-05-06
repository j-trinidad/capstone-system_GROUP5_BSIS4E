<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

if (!isset($_SESSION['booking_temp'])) {
    header("Location: customer_dashboard.php");
    exit;
}

$booking   = $_SESSION['booking_temp'];
$user_id   = $_SESSION['user_id'];
$confirmed = false;

$mechanic_name = 'N/A';
$mechanic_id   = $booking['mechanic'] ?? null;
if ($mechanic_id) {
    $stmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id = ?");
    $stmt->execute([$mechanic_id]);
    $mechanic_name = $stmt->fetchColumn() ?: 'N/A';
}

$service_id   = $booking['service_type'] ?? null;
$service_name = 'N/A';
$service_key  = 'N/A';
$laborFee     = 0;
if ($service_id) {
    $stmt = $pdo->prepare("SELECT id, name, service_key, base_price FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($service) {
        $service_name = $service['name'];
        $service_key  = $service['service_key'];
        $laborFee     = (float)$service['base_price'];
    }
}

$selectedBrandId   = $booking['selected_brand_id']    ?? null;
$selectedBrandName = $booking['selected_brand_name']  ?? 'N/A';
$brandPrice        = (float)($booking['selected_brand_price'] ?? 0);
$extraHomeFee      = ($booking['service_location'] === 'home') ? 150 : 0;
$tireSize          = $booking['tire_size'] ?? null;

$partsTotal = 0;
$partsList  = [];
$partsJSON  = $booking['selected_parts'] ?? null;
if (!empty($partsJSON)) {
    $partsArray = json_decode($partsJSON, true);
    if (is_array($partsArray)) {
        foreach ($partsArray as $part) {
            $partsTotal += (float)$part['price'];
            $partsList[] = ['name' => htmlspecialchars($part['name']), 'price' => (float)$part['price']];
        }
    }
}

$totalAmount = $laborFee + $extraHomeFee + $brandPrice + $partsTotal;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {

    $scheduleVal = $booking['schedule'] ?? null;
    $bookingDate = $scheduleVal ? date('Y-m-d', strtotime($scheduleVal)) : null;
    $mechanicId  = $booking['mechanic'] ?? null;

    // Use MySQL GET_LOCK to serialize concurrent requests for the same slot.
    // Two devices confirming at the same time will queue — the second one
    // will see the booking already inserted and be blocked.
    $lockKey     = 'slot_' . $mechanicId . '_' . str_replace([' ', ':'], '_', $scheduleVal);
    $lockTimeout = 5;

    $lockResult = $pdo->query("SELECT GET_LOCK('" . $lockKey . "', " . $lockTimeout . ")")->fetchColumn();

    if (!$lockResult) {
        unset($_SESSION['booking_temp']);
        $_SESSION['error'] = "This time slot is being booked right now. Please try a different slot.";
        header("Location: customer_dashboard.php");
        exit;
    }

    // Check: same customer already has a booking on this date
    $dupStmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE customer_id = ?
          AND DATE(schedule) = ?
          AND status NOT IN ('cancelled', 'completed')
    ");
    $dupStmt->execute([$user_id, $bookingDate]);

    if ($dupStmt->fetchColumn() > 0) {
        $pdo->query("SELECT RELEASE_LOCK('" . $lockKey . "')");
        unset($_SESSION['booking_temp']);
        $_SESSION['error'] = "You already have an active booking on this date.";
        header("Location: customer_dashboard.php");
        exit;
    }

    // Check: same mechanic + same time slot already booked by anyone
    $slotStmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE mechanic_id = ?
          AND schedule = ?
          AND status NOT IN ('cancelled')
    ");
    $slotStmt->execute([$mechanicId, $scheduleVal]);

    if ($slotStmt->fetchColumn() > 0) {
        $pdo->query("SELECT RELEASE_LOCK('" . $lockKey . "')");
        unset($_SESSION['booking_temp']);
        $_SESSION['error'] = "Sorry, this time slot was just taken. Please choose another slot.";
        header("Location: customer_dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO bookings
        (customer_id, mechanic_id, brand, vehicle_type, service_type, service_location,
         service_address, tire_size, schedule, note,
         labor_fee, service_fee, parts_total, parts, total_price, status)
        VALUES
        (:customer_id, :mechanic_id, :brand, :vehicle_type, :service_type, :service_location,
         :service_address, :tire_size, :schedule, :note,
         :labor_fee, :service_fee, :parts_total, :parts, :total_price, :status)
    ");
    $stmt->execute([
        ':customer_id'      => $user_id,
        ':mechanic_id'      => $mechanic_id,
        ':brand'            => $booking['brand']            ?? null,
        ':vehicle_type'     => $booking['vehicle_type']     ?? null,
        ':service_type'     => $service_key,
        ':service_location' => $booking['service_location'] ?? null,
        ':service_address'  => $booking['saved_address']    ?? null,
        ':tire_size'        => $tireSize,
        ':schedule'         => $booking['schedule']         ?? null,
        ':note'             => $booking['note']             ?? null,
        ':labor_fee'        => $laborFee,
        ':service_fee'      => $brandPrice,
        ':parts_total'      => $partsTotal,
        ':parts'            => $partsJSON,
        ':total_price'      => $totalAmount,
        ':status'           => 'pending'
    ]);
    $booking_id = $pdo->lastInsertId();
    $pdo->query("SELECT RELEASE_LOCK('" . $lockKey . "')");
    unset($_SESSION['booking_temp']);
    $confirmed = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    unset($_SESSION['booking_temp']);
    header("Location: customer_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Booking Confirmation - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #1a56db;
    --secondary: #1e40af;
    --dark-bg: #f0f4ff;
    --border: rgba(26,86,219,0.2);
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --success: #059669;
}
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
    font-family:'Outfit',sans-serif;
    background:linear-gradient(135deg,#f0f4ff,#e8eeff);
    color:var(--text-primary); min-height:100vh;
    display:flex; justify-content:center; align-items:center; padding:20px;
}
.page-wrapper { width:100%; max-width:620px; }

.header-top { text-align:center; margin-bottom:28px; }
.header-top h1 {
    font-size:30px;
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    margin-bottom:8px; display:flex; align-items:center; justify-content:center; gap:10px;
}
.header-top p { color:var(--text-secondary); font-size:14px; }

.receipt-container {
    background:#fff; border-radius:16px; overflow:hidden;
    box-shadow:0 20px 60px rgba(26,86,219,0.15); animation:slideUp .5s ease;
}
@keyframes slideUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }

.receipt-header {
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#fff; padding:28px 25px; text-align:center;
}
.receipt-logo    { font-size:38px; margin-bottom:10px; }
.receipt-title   { font-size:22px; font-weight:700; margin-bottom:4px; }
.receipt-subtitle{ font-size:12px; opacity:.95; font-weight:600; }

.receipt-body { padding:24px 25px; color:#333; }

.section { margin-bottom:18px; }
.section-title {
    font-size:10px; font-weight:700; color:var(--primary);
    text-transform:uppercase; letter-spacing:1.5px;
    margin-bottom:10px; padding-bottom:8px;
    border-bottom:2px solid rgba(26,86,219,0.2);
}
.info-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 0; font-size:13px; border-bottom:1px solid #f0f0f0;
}
.info-row:last-child { border-bottom:none; }
.info-label { color:#666; font-weight:600; }
.info-value  { color:#333; font-weight:600; text-align:right; }

.pricing-divider { height:1px; background:rgba(26,86,219,0.15); margin:16px 0; }

.price-item {
    display:flex; justify-content:space-between; align-items:center;
    padding:8px 0; font-size:13px; color:#666; border-bottom:1px solid #f0f0f0;
}
.price-item:last-child { border-bottom:none; }
.price-label  { color:#333; }
.price-amount { color:#1a56db; font-weight:700; }
.price-item.total {
    padding:14px 16px; font-size:18px; font-weight:700; color:#fff;
    background:linear-gradient(135deg,#1a56db,#1e40af);
    border-radius:10px; margin-top:10px;
    display:flex; justify-content:space-between;
}
.price-item.total .price-label  { color:#fff; }
.price-item.total .price-amount { color:#fff; font-size:20px; }

.receipt-footer {
    background:#f5f5f5; padding:16px 25px; text-align:center;
    border-top:2px solid rgba(26,86,219,0.15);
}
.footer-note { font-size:11px; color:#999; line-height:1.5; }

/* ── BUTTONS ── */
.button-group {
    display:flex; gap:12px; margin-top:24px; flex-wrap:wrap; justify-content:center;
}
.btn {
    padding:13px 26px; border-radius:10px; border:none;
    cursor:pointer; font-weight:700; font-size:13px; transition:all .3s;
    display:inline-flex; align-items:center; gap:8px;
    text-decoration:none; text-transform:uppercase; letter-spacing:.5px;
    box-shadow:0 4px 15px rgba(26,86,219,0.12); min-width:130px; justify-content:center;
    position: relative; overflow: hidden;
}
.btn-confirm { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; }
.btn-confirm:hover { transform:translateY(-3px); box-shadow:0 6px 25px rgba(26,86,219,0.35); }
.btn-cancel  { background:#fff; color:var(--primary); border:2px solid var(--primary); }
.btn-cancel:hover { background:rgba(26,86,219,0.06); transform:translateY(-3px); }

.btn-view-receipt {
    background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#fff; flex:1;
}
.btn-view-receipt:hover { transform:translateY(-3px); box-shadow:0 6px 25px rgba(26,86,219,0.35); }
.btn-dashboard {
    background:#fff; color:var(--primary); border:2px solid var(--primary); flex:1;
}
.btn-dashboard:hover { background:rgba(26,86,219,0.06); transform:translateY(-3px); }

/* ── LOADING OVERLAY ── */
.loading-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 80, 0.65);
    z-index: 9999;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 28px;
    backdrop-filter: blur(6px);
    animation: fadeInOverlay 0.3s ease;
}
.loading-overlay.active { display: flex; }

@keyframes fadeInOverlay {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.loading-card {
    background: #ffffff;
    border: 1px solid rgba(26,86,219,0.2);
    border-radius: 20px;
    padding: 40px 50px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(26,86,219,0.15), 0 0 40px rgba(26,86,219,0.08);
    animation: cardPop 0.4s ease 0.1s both;
    max-width: 320px;
    width: 90%;
}
@keyframes cardPop {
    from { opacity:0; transform: scale(0.85) translateY(20px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}

/* Motorcycle spinner */
.moto-spinner-wrap {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
}
.moto-ring {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: 3px solid transparent;
    border-top-color: var(--primary);
    border-right-color: var(--secondary);
    animation: spinRing 1s linear infinite;
}
.moto-ring-2 {
    position: absolute;
    inset: 8px;
    border-radius: 50%;
    border: 2px solid transparent;
    border-bottom-color: rgba(26,86,219,0.3);
    animation: spinRing 1.5s linear infinite reverse;
}
.moto-icon {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    animation: motoRock 0.6s ease-in-out infinite alternate;
}
@keyframes spinRing {
    to { transform: rotate(360deg); }
}
@keyframes motoRock {
    from { transform: translateY(0px) rotate(-3deg); }
    to   { transform: translateY(-3px) rotate(3deg); }
}

.loading-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 8px;
}
.loading-subtitle {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Animated dots */
.loading-dots {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
}
.loading-dots span {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--primary);
    animation: dotBounce 1.2s ease-in-out infinite;
}
.loading-dots span:nth-child(2) {
    animation-delay: 0.2s;
    background: #3b6fd4;
}
.loading-dots span:nth-child(3) {
    animation-delay: 0.4s;
    background: var(--secondary);
}
@keyframes dotBounce {
    0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; }
    40%            { transform: scale(1.2); opacity: 1; }
}

/* Progress steps inside loading */
.loading-steps {
    margin-top: 18px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    text-align: left;
}
.loading-step {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: rgba(30,41,59,0.35);
    transition: color 0.4s ease;
}
.loading-step.done   { color: var(--success); }
.loading-step.active { color: var(--text-primary); }
.step-dot {
    width: 18px; height: 18px;
    border-radius: 50%;
    border: 2px solid currentColor;
    display: flex; align-items: center; justify-content: center;
    font-size: 9px; flex-shrink: 0;
    transition: all 0.4s ease;
}
.loading-step.done .step-dot {
    background: var(--success);
    border-color: var(--success);
}
.loading-step.active .step-dot {
    border-color: var(--primary);
    animation: pulseDot 0.8s ease-in-out infinite;
}
@keyframes pulseDot {
    0%,100% { box-shadow: 0 0 0 0 rgba(26,86,219,0.4); }
    50%      { box-shadow: 0 0 0 5px rgba(26,86,219,0); }
}

/* ── SUCCESS STATE ── */
.success-icon { font-size:60px; color:var(--success); margin-bottom:16px; animation:bounceIn .6s ease; }
@keyframes bounceIn {
    0%{opacity:0;transform:scale(0.3)} 50%{opacity:1;transform:scale(1.05)}
    70%{transform:scale(0.9)} 100%{opacity:1;transform:scale(1)}
}
.success-message {
    padding:18px; background:#f0fdf4; border:2px solid var(--success);
    border-radius:12px; margin-bottom:18px; text-align:center;
}
.success-message h3 { color:var(--success); font-size:17px; margin-bottom:6px; }
.success-message p  { color:#333; font-size:12px; line-height:1.5; }
.booking-id-box {
    background:linear-gradient(135deg,rgba(0,208,132,0.1),rgba(0,208,132,0.05));
    border:2px solid var(--success); border-radius:12px; padding:20px; margin:18px 0; text-align:center;
}
.booking-id-label { font-size:10px; color:#666; margin-bottom:8px; text-transform:uppercase; font-weight:700; letter-spacing:1px; }
.booking-id-value { font-size:28px; color:var(--success); font-weight:700; font-family:'Courier New',monospace; letter-spacing:2px; }

.next-steps {
    background:#f9f9f9; padding:18px; border-radius:12px; margin-bottom:18px; text-align:left;
}
.next-steps-title { color:#333; font-size:12px; font-weight:700; margin-bottom:12px; }
.next-steps ul { list-style:none; padding:0; }
.next-steps li { color:#666; font-size:12px; margin-bottom:8px; display:flex; align-items:center; gap:8px; }
.next-steps li:last-child { margin-bottom:0; }
.step-icon { color:var(--success); }

.action-buttons { display:flex; gap:12px; flex-wrap:wrap; margin-top:8px; }

/* ══ PRINT ══ */
@media print {
    @page { size: A4; margin: 10mm 12mm; }
    html, body { background:#fff!important; display:block!important; padding:0!important; margin:0!important; }
    .page-wrapper   { max-width:100%!important; }
    .header-top, .button-group, .action-buttons, .success-message, .next-steps, .loading-overlay { display:none!important; }
    .receipt-container { box-shadow:none!important; border-radius:0!important; animation:none!important; }
    * { -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
    .receipt-header { padding:14px 20px!important; background:linear-gradient(135deg,#1a56db,#1e40af)!important; }
    .receipt-logo { font-size:22px!important; margin-bottom:4px!important; }
    .receipt-title { font-size:16px!important; margin-bottom:2px!important; }
    .receipt-subtitle { font-size:10px!important; }
    .receipt-body { padding:12px 18px!important; }
    .booking-id-box { padding:12px!important; margin:10px 0!important; }
    .booking-id-value { font-size:22px!important; }
    .booking-id-label { font-size:9px!important; }
    .section { margin-bottom:10px!important; }
    .section-title { font-size:9px!important; margin-bottom:5px!important; padding-bottom:4px!important; }
    .info-row { padding:4px 0!important; font-size:11px!important; }
    .pricing-divider { margin:8px 0!important; }
    .price-item { padding:4px 0!important; font-size:11px!important; }
    .price-item.total { padding:9px 14px!important; font-size:14px!important; margin-top:7px!important; border-radius:6px!important; background:linear-gradient(135deg,#1a56db,#1e40af)!important; }
    .price-item.total .price-amount { font-size:15px!important; }
    .receipt-footer { padding:10px 18px!important; }
    .footer-note { font-size:9px!important; }
}

@media(max-width:600px){
    .receipt-header{padding:24px 18px}
    .receipt-body  {padding:20px 18px}
    .button-group  {flex-direction:column}
    .action-buttons{flex-direction:column}
    .btn-view-receipt,.btn-dashboard{flex:none;width:100%}
    .btn{width:100%;min-width:unset}
    .loading-card { padding: 32px 28px; }
}
</style>
</head>
<body>

<!-- ── LOADING OVERLAY ── -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-card">
        <div class="moto-spinner-wrap">
            <div class="moto-ring"></div>
            <div class="moto-ring-2"></div>
            <div class="moto-icon">🏍️</div>
        </div>

        <div class="loading-title">Processing Booking</div>
        <div class="loading-subtitle">Please wait while we confirm your appointment...</div>

        <div class="loading-steps">
            <div class="loading-step active" id="step1">
                <div class="step-dot"><i class="fas fa-circle" style="font-size:6px"></i></div>
                <span>Validating your details</span>
            </div>
            <div class="loading-step" id="step2">
                <div class="step-dot"><i class="fas fa-circle" style="font-size:6px"></i></div>
                <span>Scheduling with mechanic</span>
            </div>
            <div class="loading-step" id="step3">
                <div class="step-dot"><i class="fas fa-circle" style="font-size:6px"></i></div>
                <span>Confirming your booking</span>
            </div>
        </div>

        <div class="loading-dots">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>

<div class="page-wrapper">

<?php if ($confirmed): ?>

    <div class="header-top">
        <h1><i class="fas fa-check-circle"></i> Booking Confirmed!</h1>
        <p>Your booking has been successfully submitted</p>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <div class="receipt-logo"><i class="fas fa-motorcycle"></i></div>
            <div class="receipt-title">Booking Confirmed</div>
            <div class="receipt-subtitle">MotorService &nbsp;|&nbsp; Official Receipt</div>
        </div>

        <div class="receipt-body" style="text-align:center;">

            <div class="success-message">
                <h3><i class="fas fa-check"></i> All Set!</h3>
                <p>Your booking is now pending approval. You'll receive updates via notifications or messages.</p>
            </div>

            <div class="booking-id-box">
                <div class="booking-id-label">📋 Booking Reference</div>
                <div class="booking-id-value">#<?= htmlspecialchars($booking_id) ?></div>
                <div style="font-size:10px;color:#666;margin-top:6px;">Issued: <?= date('F d, Y h:i A') ?></div>
            </div>

            <div style="text-align:left;">
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
                        <span class="info-value"><?= htmlspecialchars($service_name) ?></span>
                    </div>
                    <?php if ($tireSize): ?>
                    <div class="info-row">
                        <span class="info-label">Tire Size</span>
                        <span class="info-value"><?= htmlspecialchars($tireSize) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($selectedBrandName !== 'N/A' && $brandPrice > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Package / Brand</span>
                        <span class="info-value"><?= htmlspecialchars($selectedBrandName) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <div class="section-title">📍 Location &amp; Schedule</div>
                    <div class="info-row">
                        <span class="info-label">Service Location</span>
                        <span class="info-value"><?= ucfirst($booking['service_location'] ?? 'N/A') ?></span>
                    </div>
                    <?php if (!empty($booking['saved_address'])): ?>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($booking['saved_address']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Mechanic</span>
                        <span class="info-value"><?= htmlspecialchars($mechanic_name) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date &amp; Time</span>
                        <span class="info-value"><?= date('M d, Y g:i A', strtotime($booking['schedule'])) ?></span>
                    </div>
                </div>

                <div class="pricing-divider"></div>

                <div class="section">
                    <div class="section-title">💰 Payment Breakdown</div>
                    <?php if ($laborFee > 0): ?>
                    <div class="price-item">
                        <span class="price-label">🔧 Labor Fee</span>
                        <span class="price-amount">₱<?= number_format($laborFee,2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($brandPrice > 0): ?>
                    <div class="price-item">
                        <span class="price-label">📦 <?= htmlspecialchars($selectedBrandName) ?></span>
                        <span class="price-amount">₱<?= number_format($brandPrice,2) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($partsList as $part): ?>
                    <div class="price-item">
                        <span class="price-label">🛠️ <?= $part['name'] ?></span>
                        <span class="price-amount">₱<?= number_format($part['price'],2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($extraHomeFee > 0): ?>
                    <div class="price-item">
                        <span class="price-label">🏠 Home Service Fee</span>
                        <span class="price-amount">₱<?= number_format($extraHomeFee,2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="price-item total">
                        <span class="price-label">TOTAL AMOUNT</span>
                        <span class="price-amount">₱<?= number_format($totalAmount,2) ?></span>
                    </div>
                </div>
            </div>

            <div class="next-steps" style="text-align:left;">
                <div class="next-steps-title">What's Next?</div>
                <ul>
                    <li><span class="step-icon"><i class="fas fa-check"></i></span> 🔍 Booking is under review</li>
                    <li><span class="step-icon"><i class="fas fa-check"></i></span> 🔔 You'll be notified once there's an update</li>
                    <li><span class="step-icon"><i class="fas fa-check"></i></span> 📄 View receipt anytime in <strong>My Receipts</strong></li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="customer_receipt.php?id=<?= $booking_id ?>" class="btn btn-view-receipt">
                    <i class="fas fa-receipt"></i> View &amp; Print Receipt
                </a>
                <a href="customer_dashboard.php" class="btn btn-dashboard">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>

        </div>

        <div class="receipt-footer">
            <div class="footer-note">
                <p>Thank you for choosing MotorService! You can view this receipt anytime in <strong>My Receipts</strong>.</p>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="header-top">
        <h1><i class="fas fa-clipboard-check"></i> Review Booking</h1>
        <p>Please verify all details before confirming</p>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <div class="receipt-logo">🏍️</div>
            <div class="receipt-title">Service Booking</div>
            <div class="receipt-subtitle">MotorService Platform</div>
        </div>

        <div class="receipt-body">

            <div class="section">
                <div class="section-title">🏍️ Motorcycle &amp; Service</div>
                <div class="info-row">
                    <span class="info-label">Motorcycle Brand</span>
                    <span class="info-value"><?= htmlspecialchars($booking['brand']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vehicle Type</span>
                    <span class="info-value"><?= htmlspecialchars($booking['vehicle_type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Service Type</span>
                    <span class="info-value"><?= htmlspecialchars($service_name) ?></span>
                </div>
                <?php if ($service_key === 'tire_change' && $tireSize): ?>
                <div class="info-row">
                    <span class="info-label">Tire Size</span>
                    <span class="info-value"><?= htmlspecialchars($tireSize) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($selectedBrandName !== 'N/A' && $brandPrice > 0): ?>
                <div class="info-row">
                    <span class="info-label">Service Package</span>
                    <span class="info-value"><?= htmlspecialchars($selectedBrandName) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <div class="section-title">📍 Service Location</div>
                <div class="info-row">
                    <span class="info-label">Type</span>
                    <span class="info-value"><?= ucfirst($booking['service_location']) ?></span>
                </div>
                <?php if ($booking['service_location'] === 'home'): ?>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?= htmlspecialchars($booking['saved_address'] ?? 'N/A') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <div class="section-title">📅 Mechanic &amp; Schedule</div>
                <div class="info-row">
                    <span class="info-label">Mechanic</span>
                    <span class="info-value"><?= htmlspecialchars($mechanic_name) ?></span>
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

            <div class="section">
                <div class="section-title">💰 Payment Breakdown</div>
                <div class="price-item">
                    <span class="price-label">🔧 Service Labor Fee</span>
                    <span class="price-amount">₱<?= number_format($laborFee,2) ?></span>
                </div>
                <?php if ($brandPrice > 0): ?>
                <div class="price-item">
                    <span class="price-label">📦 <?= htmlspecialchars($selectedBrandName) ?></span>
                    <span class="price-amount">₱<?= number_format($brandPrice,2) ?></span>
                </div>
                <?php endif; ?>
                <?php foreach ($partsList as $part): ?>
                <div class="price-item">
                    <span class="price-label">🛠️ <?= $part['name'] ?></span>
                    <span class="price-amount">₱<?= number_format($part['price'],2) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($extraHomeFee > 0): ?>
                <div class="price-item">
                    <span class="price-label">🏠 Home Service Fee</span>
                    <span class="price-amount">₱<?= number_format($extraHomeFee,2) ?></span>
                </div>
                <?php endif; ?>
                <div class="price-item total">
                    <span class="price-label">TOTAL AMOUNT</span>
                    <span class="price-amount">₱<?= number_format($totalAmount,2) ?></span>
                </div>
            </div>

        </div>

        <div class="receipt-footer">
            <div class="footer-note">
                <p>By confirming, you agree to our terms and conditions. Payment will be collected after service completion.</p>
            </div>
        </div>
    </div>

    <form method="POST" class="button-group" id="bookingForm">
        <button type="submit" name="confirm" class="btn btn-confirm" id="confirmBtn">
            <i class="fas fa-check"></i> Confirm Booking
        </button>
        <button type="submit" name="cancel" class="btn btn-cancel">
            <i class="fas fa-times"></i> Cancel
        </button>
    </form>

<?php endif; ?>

</div>

<script>
const confirmBtn     = document.getElementById('confirmBtn');
const bookingForm    = document.getElementById('bookingForm');
const loadingOverlay = document.getElementById('loadingOverlay');

if (confirmBtn && bookingForm) {
    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault(); // Stop form from submitting immediately

        // Show overlay
        loadingOverlay.classList.add('active');

        const steps = [
            document.getElementById('step1'),
            document.getElementById('step2'),
            document.getElementById('step3'),
        ];

        const markDone = (step) => {
            step.classList.remove('active');
            step.classList.add('done');
            step.querySelector('.step-dot').innerHTML = '<i class="fas fa-check" style="font-size:8px"></i>';
        };

        // Step 1 active (already is), done after 700ms
        setTimeout(() => {
            markDone(steps[0]);
            steps[1].classList.add('active');
        }, 700);

        // Step 2 done after 1400ms
        setTimeout(() => {
            markDone(steps[1]);
            steps[2].classList.add('active');
        }, 1400);

        // Step 3 done after 2000ms, THEN actually submit the form
        setTimeout(() => {
            markDone(steps[2]);
        }, 2000);

        setTimeout(() => {
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'confirm';
            hidden.value = '1';
            bookingForm.appendChild(hidden);
            bookingForm.submit();
        }, 2300);
    });
}
</script>
</body>
</html>