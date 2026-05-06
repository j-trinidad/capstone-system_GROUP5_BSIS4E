<?php
/**
 * auto_cancel_noshow.php
 *
 * Run via cron every minute:
 *   * * * * * php /var/www/html/YOUR_PROJECT/cron/auto_cancel_noshow.php >> /var/log/noshow_cron.log 2>&1
 *
 * Fixed slots + grace window (auto-cancel triggers at):
 *   Slot 1: 07:00 start → cancel at 07:30
 *   Slot 2: 10:00 start → cancel at 10:30
 *   Slot 3: 13:00 start → cancel at 13:30
 *   Slot 4: 16:00 start → cancel at 16:30
 *
 * No-show penalty:
 *   Strike 1 → warning  (no_show_count = 1)
 *   Strike 2 → warning  (no_show_count = 2)
 *   Strike 3 → 7-day ban, count resets to 0
 */

if (php_sapi_name() !== 'cli') {
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require __DIR__ . '/../../includes/db_connect.php';

$GRACE_MINUTES = 30;
$now           = new DateTime();
$log           = [];
$log[]         = "=== Auto-Cancel No-Show: " . $now->format('Y-m-d H:i:s') . " ===";

// ── Find pending bookings past the 30-min grace window ────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id            AS booking_id,
           b.customer_id,
           b.mechanic_id,
           b.schedule,
           b.service_type,
           u.no_show_count,
           u.first_name,
           u.last_name
    FROM   bookings b
    JOIN   users    u ON u.id = b.customer_id
    WHERE  b.status = 'pending'
      AND  DATE_ADD(b.schedule, INTERVAL ? MINUTE) <= NOW()
");
$stmt->execute([$GRACE_MINUTES]);
$overdueBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($overdueBookings)) {
    $log[] = "No overdue bookings found.";
    echo implode("\n", $log) . "\n";
    exit;
}

$log[] = "Found " . count($overdueBookings) . " overdue booking(s).";

foreach ($overdueBookings as $booking) {
    $bookingId   = $booking['booking_id'];
    $customerId  = $booking['customer_id'];
    $noShowCount = (int)$booking['no_show_count'] + 1;

    // ── Cancel the booking ────────────────────────────────────────────────────
    $cancelStmt = $pdo->prepare("
        UPDATE bookings
        SET    status        = 'cancelled',
               cancel_reason = 'Auto-cancelled: no acknowledgement within 30-minute grace period.',
               updated_at    = NOW()
        WHERE  id     = ?
          AND  status = 'pending'
    ");
    $cancelStmt->execute([$bookingId]);

    if ($cancelStmt->rowCount() === 0) {
        $log[] = "  Booking #$bookingId already updated, skipped.";
        continue;
    }

    $log[] = "  Cancelled booking #$bookingId ({$booking['first_name']} {$booking['last_name']}, scheduled: {$booking['schedule']})";

    // ── Apply no-show penalty ─────────────────────────────────────────────────
    $banUntil = null;

    if ($noShowCount >= 3) {
        $banUntil    = (new DateTime('+7 days'))->format('Y-m-d');
        $noShowCount = 0; // reset after ban applied
        $log[]       = "  → 3rd strike: 7-day ban until $banUntil (customer #$customerId)";
    } else {
        $log[] = "  → No-show warning $noShowCount/2 (customer #$customerId)";
    }

    $pdo->prepare("
        UPDATE users
        SET    no_show_count = ?,
               no_show_until = ?
        WHERE  id = ?
    ")->execute([$noShowCount, $banUntil, $customerId]);

    // ── Notify customer ───────────────────────────────────────────────────────
    $scheduledStr = date('M d, Y h:i A', strtotime($booking['schedule']));

    if ($banUntil) {
        $notifTitle   = '🚫 Account Suspended (No-Show)';
        $notifMessage = "Booking #$bookingId on $scheduledStr was auto-cancelled due to no-show. "
                      . "Your account is suspended until $banUntil.";
    } else {
        $notifTitle   = '⚠️ Booking Auto-Cancelled (No-Show)';
        $notifMessage = "Booking #$bookingId on $scheduledStr was auto-cancelled (no mechanic acknowledgement within 30 minutes). "
                      . "No-show warning: $noShowCount/2.";
    }

    $pdo->prepare("
        INSERT INTO customer_notifications
            (customer_id, booking_id, title, message, type, is_read, created_at)
        VALUES (?, ?, ?, ?, 'booking_cancelled', 0, NOW())
    ")->execute([$customerId, $bookingId, $notifTitle, $notifMessage]);

    // ── Free mechanic if no remaining active bookings today ───────────────────
    $activeStmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE  mechanic_id = ?
          AND  DATE(schedule) = CURDATE()
          AND  status NOT IN ('cancelled', 'completed')
    ");
    $activeStmt->execute([$booking['mechanic_id']]);

    if ((int)$activeStmt->fetchColumn() === 0) {
        $pdo->prepare("UPDATE users SET is_available = 1 WHERE id = ?")
            ->execute([$booking['mechanic_id']]);
        $log[] = "  → Mechanic #{$booking['mechanic_id']} marked available.";
    }
}

$log[] = "=== Done ===\n";
echo implode("\n", $log) . "\n";
