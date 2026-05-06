<?php
/**
 * admin_receipt_ajax.php
 * Returns the inner HTML of a receipt for the admin modal viewer.
 * Called via fetch() from admin_receipts.php
 */
require '../../includes/session_check.php';
checkRole('admin');
require '../../includes/db_connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); exit; }

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.brand,
        b.vehicle_type,
        b.service_type,
        b.service_location,
        b.schedule,
        b.status,
        b.labor_fee,
        b.service_fee,
        b.parts_total,
        b.total_price,
        b.created_at,
        b.completed_at,
        b.note,
        s.name AS service_name,
        CONCAT(u.first_name,' ',u.last_name) AS mechanic_name,
        u.phone  AS mechanic_phone,
        CONCAT(c.first_name,' ',c.last_name) AS customer_name,
        c.email  AS customer_email,
        c.phone  AS customer_phone
    FROM bookings b
    LEFT JOIN services s ON s.service_key = b.service_type OR s.id = CAST(b.service_type AS UNSIGNED)
    LEFT JOIN users u    ON b.mechanic_id  = u.id
    LEFT JOIN users c    ON b.customer_id  = c.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$b) { http_response_code(404); exit; }

// Parts breakdown
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

$fmt = fn($n) => '₱' . number_format((float)$n, 2);
?>
<!-- Receipt header band -->
<div class="receipt-header">
    <div class="brand"><i class="fas fa-tools"></i> MotorService</div>
    <div class="ref">
        <div class="lbl">Service Receipt</div>
        <div class="num">#<?= str_pad($b['id'],6,'0',STR_PAD_LEFT) ?></div>
    </div>
</div>

<!-- Status strip -->
<div class="status-strip">
    <span style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:10px;font-weight:700;
                 text-transform:uppercase;letter-spacing:.5px;background:<?=$status['bg']?>;color:<?=$status['color']?>;">
        <?= htmlspecialchars($status['label']) ?>
    </span>
    <span class="status-date">
        <?php if ($b['completed_at']): ?>
            <i class="fas fa-check-circle" style="color:#00d084;"></i>
            Completed: <?= date('F d, Y \a\t h:i A', strtotime($b['completed_at'])) ?>
        <?php else: ?>
            <i class="fas fa-calendar-alt" style="color:#ff8c00;"></i>
            Scheduled: <?= date('F d, Y \a\t h:i A', strtotime($b['schedule'])) ?>
        <?php endif; ?>
    </span>
</div>

<!-- Body -->
<div class="receipt-body">

    <!-- Info grid -->
    <div class="info-grid">
        <div class="info-block">
            <div class="section-title"><i class="fas fa-user"></i> Customer</div>
            <div class="info-row">
                <span class="lbl">Name</span>
                <span class="val"><?= htmlspecialchars($b['customer_name']) ?></span>
            </div>
            <?php if ($b['customer_email']): ?>
            <div class="info-row">
                <span class="lbl">Email</span>
                <span class="val"><?= htmlspecialchars($b['customer_email']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($b['customer_phone']): ?>
            <div class="info-row">
                <span class="lbl">Phone</span>
                <span class="val"><?= htmlspecialchars($b['customer_phone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="lbl">Booking Date</span>
                <span class="val"><?= date('M d, Y', strtotime($b['created_at'])) ?></span>
            </div>
        </div>

        <div class="info-block">
            <div class="section-title"><i class="fas fa-user-cog"></i> Service Details</div>
            <div class="info-row">
                <span class="lbl">Mechanic</span>
                <span class="val"><?= htmlspecialchars($b['mechanic_name'] ?? 'Not assigned') ?></span>
            </div>
            <?php if ($b['mechanic_phone']): ?>
            <div class="info-row">
                <span class="lbl">Mech. Phone</span>
                <span class="val"><?= htmlspecialchars($b['mechanic_phone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="lbl">Service</span>
                <span class="val"><?= htmlspecialchars($svcLabel) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Motorcycle</span>
                <span class="val"><?= htmlspecialchars($b['brand'].' '.$b['vehicle_type']) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Location</span>
                <span class="val"><?= ucfirst(htmlspecialchars($b['service_location'] ?? 'N/A')) ?></span>
            </div>
        </div>
    </div>

    <!-- Fees -->
    <div class="section-title"><i class="fas fa-file-invoice-dollar"></i> Fee Breakdown</div>
    <table class="fees-table">
        <thead>
            <tr><th style="width:65%;">Description</th><th>Amount</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><i class="fas fa-wrench" style="color:#ff8c00;margin-right:8px;width:15px;"></i>Labor Fee</td>
                <td class="green"><?= $fmt($b['labor_fee']) ?></td>
            </tr>
            <tr>
                <td><i class="fas fa-tools" style="color:#ff8c00;margin-right:8px;width:15px;"></i>Service Fee</td>
                <td class="green"><?= $fmt($b['service_fee']) ?></td>
            </tr>
            <?php if (!empty($parts)): ?>
                <?php foreach ($parts as $part): ?>
                <tr>
                    <td>
                        <i class="fas fa-cog" style="color:#ff8c00;margin-right:8px;width:15px;"></i>
                        <?= htmlspecialchars($part['part_name']) ?>
                        <span style="color:#b0b8d4;font-size:11px;margin-left:4px;">
                            × <?= $part['quantity'] ?> @ <?= $fmt($part['unit_price']) ?>
                        </span>
                    </td>
                    <td class="green"><?= $fmt($part['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php elseif ((float)$b['parts_total'] > 0): ?>
            <tr>
                <td><i class="fas fa-boxes" style="color:#ff8c00;margin-right:8px;width:15px;"></i>Parts &amp; Materials</td>
                <td class="green"><?= $fmt($b['parts_total']) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Grand total -->
    <div class="total-box">
        <span class="lbl"><i class="fas fa-peso-sign" style="margin-right:6px;"></i>Grand Total</span>
        <span class="val"><?= $fmt($b['total_price']) ?></span>
    </div>

    <!-- Notes -->
    <?php if (!empty($b['note'])): ?>
    <div class="notes-box">
        <strong style="color:#ff8c00;font-size:10px;text-transform:uppercase;letter-spacing:.5px;">
            <i class="fas fa-sticky-note"></i> Notes
        </strong>
        <div style="margin-top:8px;"><?= nl2br(htmlspecialchars($b['note'])) ?></div>
    </div>
    <?php endif; ?>

</div><!-- /receipt-body -->

<div class="receipt-footer">
    <i class="fas fa-tools" style="color:#ff8c00;margin-right:5px;"></i>
    MotorService Admin View &nbsp;•&nbsp; Generated <?= date('F d, Y h:i A') ?>
</div>