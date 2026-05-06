<?php
/**
 * admin_receipt_view.php
 * Standalone admin-side receipt viewer.
 * Linked from sales.php "View" button and admin_receipts.php modal fallback.
 */
require '../../includes/session_check.php';
checkRole('admin');
// db_connect.php already loaded by session_check.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: admin_receipts.php'); exit; }

$stmt = $pdo->prepare("
    SELECT
        b.id, b.brand, b.vehicle_type, b.service_type, b.service_location,
        b.schedule, b.status, b.labor_fee, b.service_fee, b.parts_total,
        b.total_price, b.created_at, b.completed_at, b.note,
        s.name AS service_name,
        CONCAT(u.first_name,' ',u.last_name) AS mechanic_name,
        CONCAT(c.first_name,' ',c.last_name) AS customer_name,
        c.email  AS customer_email,
        c.email  AS customer_email
    FROM bookings b
    LEFT JOIN services s ON s.service_key = b.service_type OR s.id = CAST(b.service_type AS UNSIGNED)
    LEFT JOIN users u    ON b.mechanic_id  = u.id
    LEFT JOIN users c    ON b.customer_id  = c.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$b) { header('Location: admin_receipts.php'); exit; }

$parts = [];
try {
    $pStmt = $pdo->prepare("
        SELECT part_name, quantity, unit_price, (quantity*unit_price) AS subtotal
        FROM booking_parts WHERE booking_id = ? ORDER BY id ASC
    ");
    $pStmt->execute([$id]);
    $parts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$statusMap = [
    'pending'                  => ['label'=>'Pending',       'color'=>'#856404','bg'=>'#fff3cd'],
    'preparing'                => ['label'=>'Preparing',     'color'=>'#004085','bg'=>'#cce5ff'],
    'in_progress'              => ['label'=>'In Progress',   'color'=>'#155724','bg'=>'#d4edda'],
    'completed'                => ['label'=>'Completed',     'color'=>'#155724','bg'=>'#d4edda'],
    'cancelled'                => ['label'=>'Cancelled',     'color'=>'#721c24','bg'=>'#f8d7da'],
    'awaiting_customer_action' => ['label'=>'Action Needed', 'color'=>'#e65100','bg'=>'#ffe0b2'],
    'assigned'                 => ['label'=>'Assigned',      'color'=>'#1b5e20','bg'=>'#c8e6c9'],
];
$status = $statusMap[$b['status']] ?? ['label'=>ucfirst($b['status']),'color'=>'#b0b8d4','bg'=>'rgba(176,184,212,.15)'];

$serviceTypes = [
    'general_maintenance'=>'General Maintenance','oil_change'=>'Oil Change',
    'brake_inspection'=>'Brake Inspection','tire_replacement'=>'Tire Replacement',
    'battery_replacement'=>'Battery Replacement','engine_diagnostic'=>'Engine Diagnostic',
    'chain_replacement'=>'Chain Replacement','suspension_repair'=>'Suspension Repair',
    'electrical_repair'=>'Electrical Repair'
];
$svcLabel = $b['service_name'] ?? ($serviceTypes[$b['service_type']] ?? ucwords(str_replace('_',' ',$b['service_type'])));

// Referrer — back link
$backUrl  = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'admin_receipts') !== false
            ? 'admin_receipts.php'
            : 'sales.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Receipt #<?= $b['id'] ?> — MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#ff8c00;--secondary:#e52e71;--dark-bg:#0a0e27;--card-bg:#1a1f3a;--border:rgba(255,140,0,0.2);--text-primary:#fff;--text-secondary:#b0b8d4;--success:#00d084}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Outfit',sans-serif;background:linear-gradient(135deg,var(--dark-bg),#1a1f3a);color:var(--text-primary);min-height:100vh;padding:30px 20px}
a{color:inherit;text-decoration:none}

.page-wrap{max-width:800px;margin:0 auto}

/* top action bar */
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;border:1px solid var(--border);color:var(--text-secondary);font-weight:600;font-size:13px;transition:all .3s;background:transparent;cursor:pointer;font-family:'Outfit',sans-serif}
.back-btn:hover{border-color:var(--primary);color:var(--primary)}
.top-right{display:flex;gap:10px;flex-wrap:wrap}
.btn-secondary{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;border:1px solid var(--border);color:var(--text-secondary);background:transparent;font-weight:600;font-size:13px;cursor:pointer;font-family:'Outfit',sans-serif;transition:.3s;text-decoration:none;white-space:nowrap}
.btn-secondary:hover{border-color:var(--primary);color:var(--primary);background:rgba(255,140,0,.08)}
.print-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#1a1f3a;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:13px;font-family:'Outfit',sans-serif;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 4px 15px rgba(255,140,0,.35);transition:all .3s;white-space:nowrap}
.print-btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(255,140,0,.5)}

/* admin notice bar */
.admin-notice{background:rgba(229,46,113,.1);border:1px solid rgba(229,46,113,.3);border-radius:10px;padding:10px 16px;margin-bottom:20px;font-size:12px;color:#e88daa;display:flex;align-items:center;gap:8px}

/* receipt */
.receipt{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.4)}
.receipt-header{background:linear-gradient(135deg,var(--primary),var(--secondary));padding:26px 32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px}
.receipt-header .brand{font-size:21px;font-weight:700;color:#fff;display:flex;align-items:center;gap:9px}
.receipt-header .ref{text-align:right}
.receipt-header .ref .num{font-size:26px;font-weight:700;color:#fff}
.receipt-header .ref .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.85)}
.status-strip{background:rgba(255,255,255,.04);border-bottom:1px solid var(--border);padding:13px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.status-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.status-date{font-size:12px;color:var(--text-secondary)}
.receipt-body{padding:26px 32px}
.section-title{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--primary);font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:7px;border-bottom:1px solid var(--border);padding-bottom:7px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.info-block{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:16px}
.info-row{display:flex;justify-content:space-between;align-items:flex-start;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:13px;gap:10px}
.info-row:last-child{border-bottom:none}
.info-row .lbl{color:var(--text-secondary);flex-shrink:0}
.info-row .val{color:var(--text-primary);font-weight:600;text-align:right}
.fees-table{width:100%;border-collapse:collapse;margin-bottom:18px;font-size:13px}
.fees-table th{text-align:left;padding:9px 13px;background:rgba(255,140,0,.1);color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.5px;font-weight:700}
.fees-table th:last-child,.fees-table td:last-child{text-align:right}
.fees-table td{padding:11px 13px;border-bottom:1px solid rgba(255,255,255,.05)}
.fees-table tbody tr:last-child td{border-bottom:none}
.fees-table .green{color:var(--success);font-weight:700}
.total-box{background:linear-gradient(135deg,rgba(255,140,0,.12),rgba(229,46,113,.12));border:1px solid var(--border);border-radius:12px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center}
.total-box .lbl{color:var(--text-secondary);font-size:13px;text-transform:uppercase;letter-spacing:.5px;font-weight:700}
.total-box .val{font-size:28px;font-weight:700;background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.notes-box{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:14px;margin-top:18px;font-size:13px;color:var(--text-secondary);line-height:1.7}
.receipt-footer{background:rgba(0,0,0,.2);border-top:1px solid var(--border);padding:13px 32px;text-align:center;font-size:12px;color:var(--text-secondary)}

/* ===================== PRINT ===================== */
@media print{
    *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
    body{background:#fff!important;color:#111!important;padding:0!important;min-height:unset!important}
    .top-bar,.admin-notice{display:none!important}
    .page-wrap{max-width:100%!important}
    .receipt{border:none!important;box-shadow:none!important;border-radius:0!important;background:#fff!important}
    .receipt-header{background:#ff8c00!important;padding:18px 22px!important;border-radius:0!important}
    .receipt-header .brand,.receipt-header .ref .num{color:#fff!important}
    .receipt-header .ref .lbl{color:rgba(255,255,255,.85)!important}
    .status-strip{background:#f8f8f8!important;border-color:#ddd!important;padding:10px 22px!important}
    .receipt-body{padding:18px 22px!important;background:#fff!important}
    .info-grid{grid-template-columns:1fr 1fr}
    .info-block{background:#f8f9fa!important;border-color:#ddd!important}
    .info-row .lbl{color:#555!important}.info-row .val{color:#111!important}
    .section-title{color:#ff8c00!important;border-color:#ffd080!important}
    .fees-table th{background:#fff3e0!important;color:#555!important}
    .fees-table td{color:#111!important;border-color:#eee!important}
    .fees-table .green{color:#157a15!important}
    .total-box{background:#fff3e0!important;border-color:#ffd080!important}
    .total-box .lbl{color:#555!important}
    .total-box .val{-webkit-text-fill-color:#ff8c00!important;color:#ff8c00!important}
    .receipt-footer{background:#f8f8f8!important;border-color:#ddd!important;color:#666!important}
    .notes-box{background:#f8f8f8!important;color:#555!important}
}

@media(max-width:600px){
    .info-grid{grid-template-columns:1fr}
    .receipt-header{padding:18px 20px}.receipt-body{padding:18px 20px}.status-strip{padding:11px 20px}.receipt-footer{padding:12px 20px}
    .print-btn span{display:none}
}
</style>
</head>
<body>
<div class="page-wrap">

    <div class="top-bar">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="top-right">
            <a href="admin_receipts.php" class="btn-secondary">
                <i class="fas fa-list"></i> All Receipts
            </a>
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i>
                <span>Print / Save as PDF</span>
            </button>
        </div>
    </div>

    <!-- Admin-only notice -->
    <div class="admin-notice">
        <i class="fas fa-shield-alt"></i>
        Admin view — this receipt is visible to administrators only. Booking ID: <strong>#<?= $b['id'] ?></strong>
    </div>

    <div class="receipt">

        <!-- Header -->
        <div class="receipt-header">
            <div class="brand"><i class="fas fa-tools"></i> MotorService</div>
            <div class="ref">
                <div class="lbl">Service Receipt</div>
                <div class="num">#<?= str_pad($b['id'],6,'0',STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <!-- Status strip -->
        <div class="status-strip">
            <span class="status-badge" style="background:<?=$status['bg']?>;color:<?=$status['color']?>;">
                <?= htmlspecialchars($status['label']) ?>
            </span>
            <span class="status-date">
                <?php if ($b['completed_at']): ?>
                    <i class="fas fa-check-circle" style="color:var(--success);"></i>
                    Completed: <?= date('F d, Y \a\t h:i A', strtotime($b['completed_at'])) ?>
                <?php else: ?>
                    <i class="fas fa-calendar-alt" style="color:var(--primary);"></i>
                    Scheduled: <?= date('F d, Y \a\t h:i A', strtotime($b['schedule'])) ?>
                <?php endif; ?>
            </span>
        </div>

        <!-- Body -->
        <div class="receipt-body">

            <div class="info-grid">

                <!-- Customer -->
                <div class="info-block">
                    <div class="section-title"><i class="fas fa-user"></i> Customer</div>
                    <div class="info-row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($b['customer_name']) ?></span></div>
                    <?php if ($b['customer_email']): ?>
                    <div class="info-row"><span class="lbl">Email</span><span class="val"><?= htmlspecialchars($b['customer_email']) ?></span></div>
                    <?php endif; ?>
                    <div class="info-row"><span class="lbl">Booking Date</span><span class="val"><?= date('M d, Y', strtotime($b['created_at'])) ?></span></div>
                </div>

                <!-- Service / Mechanic -->
                <div class="info-block">
                    <div class="section-title"><i class="fas fa-user-cog"></i> Service Details</div>
                    <div class="info-row"><span class="lbl">Mechanic</span><span class="val"><?= htmlspecialchars($b['mechanic_name'] ?? 'Not assigned') ?></span></div>

                    <div class="info-row"><span class="lbl">Service</span><span class="val"><?= htmlspecialchars($svcLabel) ?></span></div>
                    <div class="info-row"><span class="lbl">Motorcycle</span><span class="val"><?= htmlspecialchars($b['brand'].' '.$b['vehicle_type']) ?></span></div>
                    <div class="info-row"><span class="lbl">Location</span><span class="val"><?= ucfirst(htmlspecialchars($b['service_location'] ?? 'N/A')) ?></span></div>
                    <div class="info-row"><span class="lbl">Scheduled</span><span class="val"><?= date('M d, Y g:i A', strtotime($b['schedule'])) ?></span></div>
                </div>
            </div>

            <!-- Fees -->
            <div class="section-title"><i class="fas fa-file-invoice-dollar"></i> Fee Breakdown</div>
            <table class="fees-table">
                <thead><tr><th style="width:65%;">Description</th><th>Amount</th></tr></thead>
                <tbody>
                    <tr>
                        <td><i class="fas fa-wrench" style="color:var(--primary);margin-right:8px;width:15px;"></i>Labor Fee</td>
                        <td class="green">₱<?= number_format((float)$b['labor_fee'],2) ?></td>
                    </tr>
                    <tr>
                        <td><i class="fas fa-tools" style="color:var(--primary);margin-right:8px;width:15px;"></i>Service Fee</td>
                        <td class="green">₱<?= number_format((float)$b['service_fee'],2) ?></td>
                    </tr>
                    <?php if (!empty($parts)): ?>
                        <?php foreach ($parts as $part): ?>
                        <tr>
                            <td>
                                <i class="fas fa-cog" style="color:var(--primary);margin-right:8px;width:15px;"></i>
                                <?= htmlspecialchars($part['part_name']) ?>
                                <span style="color:var(--text-secondary);font-size:12px;margin-left:4px;">
                                    × <?= $part['quantity'] ?> @ ₱<?= number_format((float)$part['unit_price'],2) ?>
                                </span>
                            </td>
                            <td class="green">₱<?= number_format((float)$part['subtotal'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php elseif ((float)$b['parts_total'] > 0): ?>
                    <tr>
                        <td><i class="fas fa-boxes" style="color:var(--primary);margin-right:8px;width:15px;"></i>Parts &amp; Materials</td>
                        <td class="green">₱<?= number_format((float)$b['parts_total'],2) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Grand total -->
            <div class="total-box">
                <span class="lbl"><i class="fas fa-peso-sign" style="margin-right:6px;"></i>Grand Total</span>
                <span class="val">₱<?= number_format((float)$b['total_price'],2) ?></span>
            </div>

            <!-- Notes -->
            <?php if (!empty($b['note'])): ?>
            <div class="notes-box">
                <strong style="color:var(--primary);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                    <i class="fas fa-sticky-note"></i> Notes
                </strong>
                <div style="margin-top:8px;"><?= nl2br(htmlspecialchars($b['note'])) ?></div>
            </div>
            <?php endif; ?>

        </div><!-- /receipt-body -->

        <div class="receipt-footer">
            <i class="fas fa-tools" style="color:var(--primary);margin-right:5px;"></i>
            MotorService Admin &nbsp;•&nbsp; Generated <?= date('F d, Y h:i A') ?>
        </div>

    </div><!-- /receipt -->
</div><!-- /page-wrap -->
</body>
</html>