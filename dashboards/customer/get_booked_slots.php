<?php
require '../../includes/session_check.php';
require '../../includes/db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$mechanic_id = isset($_GET['mechanic_id']) ? (int)$_GET['mechanic_id'] : 0;
$date        = isset($_GET['date'])        ? trim($_GET['date'])        : '';

if (!$mechanic_id || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['all_slots' => [], 'available_slots' => []]);
    exit;
}

// ── Fixed slot definitions ────────────────────────────────────────────────────
$FIXED_SLOTS = [
    ['hour' => 7,  'min' => 0, 'service_end_hour' => 9,  'label' => '7:00 AM – 9:00 AM'],
    ['hour' => 10, 'min' => 0, 'service_end_hour' => 12, 'label' => '10:00 AM – 12:00 PM'],
    ['hour' => 13, 'min' => 0, 'service_end_hour' => 15, 'label' => '1:00 PM – 3:00 PM'],
    ['hour' => 16, 'min' => 0, 'service_end_hour' => 18, 'label' => '4:00 PM – 6:00 PM'],
];

$stmt = $pdo->prepare("
    SELECT TIME_FORMAT(schedule, '%H:%i') AS slot_time,
           status
    FROM   bookings
    WHERE  mechanic_id = ?
      AND  DATE(schedule) = ?
      AND  status != 'cancelled'
    ORDER BY id DESC
");
$stmt->execute([$mechanic_id, $date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map: slot_time → status (latest non-cancelled booking wins)
$slotStatusMap = [];
foreach ($rows as $row) {
    $slotStatusMap[$row['slot_time']] = $row['status'];
}

// ── Date context ──────────────────────────────────────────────────────────────
$today      = date('Y-m-d');
$isToday    = ($date === $today);
$isPastDate = ($date < $today); // yesterday or earlier
$nowMinutes = (int)date('H') * 60 + (int)date('i');

// ── Build response ────────────────────────────────────────────────────────────
$allSlots       = [];
$availableSlots = [];

foreach ($FIXED_SLOTS as $slot) {
    $slotKey     = sprintf('%02d:%02d', $slot['hour'], $slot['min']);
    $slotMinutes = $slot['hour'] * 60 + $slot['min'];
    $isBlocked   = false;
    $reason      = 'available';

    // ── Rule 1: Past date entirely → ALL slots blocked ────────────────────────
    if ($isPastDate) {
        $isBlocked = true;
        $reason    = 'past';
    }

    // ── Rule 2: Booked by mechanic (pending/in_progress/completed) → blocked ──
    if (!$isBlocked && isset($slotStatusMap[$slotKey])) {
        $slotStatus = $slotStatusMap[$slotKey];
        $isBlocked  = true;
        $reason     = ($slotStatus === 'completed') ? 'completed' : 'booked';
        // cancelled excluded from query → slot stays free
    }

    // ── Rule 3: Same-day cutoff check ─────────────────────────────────────────
    // Slot is available until 1 hour before the slot END time.
    // e.g. 4:00–6:00 PM slot → bookable until 5:00 PM
    //      1:00–3:00 PM slot → bookable until 2:00 PM
    $CUTOFF_BEFORE_END_MINUTES = 60; // 1 hour before slot end
    $slotEndMinutes = $slot['service_end_hour'] * 60;
    $cutoffMinutes  = $slotEndMinutes - $CUTOFF_BEFORE_END_MINUTES;

    if (!$isBlocked && $isToday && $nowMinutes >= $cutoffMinutes) {
        $isBlocked = true;
        $reason    = 'past';
    }

    $allSlots[] = [
        'time'             => $slotKey,
        'label'            => $slot['label'],
        'service_end_hour' => $slot['service_end_hour'],
        'blocked'          => $isBlocked,
        'reason'           => $reason,
    ];

    if (!$isBlocked) {
        $availableSlots[] = $slotKey;
    }
}

echo json_encode([
    'all_slots'       => $allSlots,
    'available_slots' => $availableSlots,
], JSON_PRETTY_PRINT);