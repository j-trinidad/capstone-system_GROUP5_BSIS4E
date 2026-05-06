<?php
if (!isset($pdo)) {
    require __DIR__ . '/../../includes/db_connect.php';
}

/**
 * Main auto-cancel function
 * Returns the number of bookings that were cancelled
 */
function autoCancelExpiredBookings(PDO $pdo): int {
    // Step 1: Find all expired bookings that are still active
    $findStmt = $pdo->prepare("
        SELECT id, customer_id, mechanic_id, service_type, schedule, status
        FROM bookings
        WHERE status IN ('pending', 'confirmed', 'assigned')
          AND schedule < NOW()
    ");
    $findStmt->execute();
    $expiredBookings = $findStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expiredBookings)) {
        return 0; // Nothing to cancel
    }

    $cancelledCount = 0;

    foreach ($expiredBookings as $booking) {
        // Step 2: Update the booking status to cancelled
        $existingNote = trim($booking['note'] ?? '');
        $autoNote     = 'Auto-cancelled: schedule passed without completion.';
        $newNote      = $existingNote !== '' ? $existingNote . ' | ' . $autoNote : $autoNote;

        $updateStmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                note   = ?
            WHERE id = ?
              AND status IN ('pending', 'confirmed', 'assigned')
        ");
        $updateStmt->execute([$newNote, $booking['id']]);

        if ($updateStmt->rowCount() === 0) {
            continue; // Already updated by another process, skip
        }

        // Step 3: Send a notification to the customer
        try {
            $scheduleFormatted = date('M d, Y g:i A', strtotime($booking['schedule']));
            $notifTitle   = '⏰ Booking Auto-Cancelled';
            $notifMessage = "Your booking #" . $booking['id']
                          . " for " . strtoupper($booking['service_type'])
                          . " scheduled on " . $scheduleFormatted
                          . " was automatically cancelled because the scheduled time has passed.";

            $notifStmt = $pdo->prepare("
                INSERT INTO customer_notifications
                    (customer_id, booking_id, type, title, message, is_read, created_at)
                VALUES
                    (:customer_id, :booking_id, 'booking_cancelled', :title, :message, 0, NOW())
            ");
            $notifStmt->execute([
                ':customer_id' => $booking['customer_id'],
                ':booking_id'  => $booking['id'],
                ':title'       => $notifTitle,
                ':message'     => $notifMessage,
            ]);
        } catch (Exception $e) {
            // Notification failed — booking is still cancelled, just no notif
            error_log("auto_cancel: notification failed for booking #{$booking['id']}: " . $e->getMessage());
        }

        $cancelledCount++;
    }

    // Optional: log result for debugging
    if ($cancelledCount > 0) {
        error_log("auto_cancel_bookings.php: Cancelled {$cancelledCount} expired booking(s) at " . date('Y-m-d H:i:s'));
    }

    return $cancelledCount;
}

// Run it
$cancelled = autoCancelExpiredBookings($pdo);

// If running as standalone cron job, print result
if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] Auto-cancelled {$cancelled} expired booking(s).\n";
}