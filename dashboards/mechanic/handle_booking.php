<?php
session_start();
require '../../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    $_SESSION['error'] = 'Unauthorized access';
    header("Location: service_request.php");
    exit();
}

$mechanic_id = $_SESSION['user_id'];
$booking_id  = $_POST['booking_id'] ?? null;
$DAILY_LIMIT = 4;

if (!$booking_id) {
    $_SESSION['error'] = 'Invalid booking ID';
    header("Location: service_request.php");
    exit();
}

function notifyCustomer($pdo, $cid, $bid, $type, $title, $msg) {
    $s = $pdo->prepare("INSERT INTO customer_notifications
        (customer_id, booking_id, type, title, message, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $s->execute([$cid, $bid, $type, $title, $msg]);
}

function sendMessage($pdo, $from, $to, $bid, $msg) {
    $s = $pdo->prepare("INSERT INTO messages
        (sender_id, receiver_id, booking_id, message_text, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())");
    $s->execute([$from, $to, $bid, $msg]);
}

/**
 * Process no-show with monthly reset logic.
 *
 * Rules:
 *  - If no_show_month != current month → reset count to 0 first (new month)
 *  - 1st no-show: count=1, disabled today only (no_show_last_date = today)
 *  - 2nd no-show: count=2, disabled today only (no_show_last_date = today)
 *  - 3rd no-show: 7-day ban (no_show_until), reset count to 0, no_show_month updated
 *
 * Returns: ['count' => int, 'ban_until' => string|null]
 */
function processNoShow(PDO $pdo, int $cid): array {
    $today        = date('Y-m-d');
    $currentMonth = date('Y-m');

    $stmt = $pdo->prepare("
        SELECT no_show_count, no_show_month, no_show_until, no_show_last_date
        FROM users WHERE id = ?
    ");
    $stmt->execute([$cid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $count       = (int)($user['no_show_count'] ?? 0);
    $noShowMonth = $user['no_show_month'] ?? null;

    // ── Monthly reset ──────────────────────────────────────────────────────
    if ($noShowMonth !== $currentMonth) {
        $count = 0; // New month — fresh start
    }

    $count++; // Increment for this no-show

    if ($count >= 3) {
        // 3rd no-show this month → 7-day ban, reset counter
        $banUntil = date('Y-m-d', strtotime('+7 days'));
        $pdo->prepare("
            UPDATE users
            SET no_show_count     = 0,
                no_show_month     = ?,
                no_show_last_date = ?,
                no_show_until     = ?
            WHERE id = ?
        ")->execute([$currentMonth, $today, $banUntil, $cid]);

        return ['count' => $count, 'ban_until' => $banUntil];

    } else {
        // 1st or 2nd no-show → disable booking for rest of today only
        $pdo->prepare("
            UPDATE users
            SET no_show_count     = ?,
                no_show_month     = ?,
                no_show_last_date = ?,
                no_show_until     = NULL
            WHERE id = ?
        ")->execute([$count, $currentMonth, $today, $cid]);

        return ['count' => $count, 'ban_until' => null];
    }
}

try {
    $checkStmt = $pdo->prepare("
        SELECT id, status, mechanic_id, customer_id, schedule,
               is_acknowledged, service_location, note
        FROM bookings WHERE id = ?
    ");
    $checkStmt->execute([$booking_id]);
    $booking = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['error'] = "Booking #$booking_id not found!";
        header("Location: service_request.php"); exit();
    }
    if ($booking['mechanic_id'] != $mechanic_id) {
        $_SESSION['error'] = "This booking doesn't belong to you!";
        header("Location: service_request.php"); exit();
    }

    $cid         = $booking['customer_id'];
    $scheduleFmt = date('F d, Y \a\t h:i A', strtotime($booking['schedule']));
    $bookingDate = date('Y-m-d', strtotime($booking['schedule']));
    $today       = date('Y-m-d');

    // =====================================================================
    // ACKNOWLEDGE
    // =====================================================================
    if (isset($_POST['acknowledge_booking'])) {
        if ($booking['status'] !== 'pending') {
            $_SESSION['error'] = "Only pending bookings can be acknowledged.";
            header("Location: service_request.php"); exit();
        }
        if ($booking['is_acknowledged']) {
            $_SESSION['error'] = "Booking #$booking_id has already been acknowledged.";
            header("Location: service_request.php"); exit();
        }

        $autoMsg = "Hi! Your booking #{$booking_id} scheduled on {$scheduleFmt} has been acknowledged "
                 . "by your mechanic. We will accept and start your service on the day "
                 . "of your appointment. Thank you for your patience!";

        sendMessage($pdo, $mechanic_id, $cid, $booking_id, $autoMsg);
        notifyCustomer($pdo, $cid, $booking_id,
            'booking_acknowledged', 'Booking Acknowledged',
            "Your booking #{$booking_id} on {$scheduleFmt} has been acknowledged. "
            . "Your mechanic will accept and start it on the day of the appointment."
        );
        $pdo->prepare("UPDATE bookings SET is_acknowledged = 1 WHERE id = ?")
            ->execute([$booking_id]);

        $_SESSION['info'] = "Acknowledgement sent to customer for Booking #{$booking_id}.";
        header("Location: service_request.php"); exit();
    }

    // =====================================================================
    // ACCEPT → IN PROGRESS
    // =====================================================================
    elseif (isset($_POST['accept_booking'])) {
        if ($booking['status'] !== 'pending') {
            $_SESSION['error'] = "Cannot accept booking with status: {$booking['status']}";
            header("Location: service_request.php"); exit();
        }
        if ($bookingDate !== $today) {
            $_SESSION['error'] = "You can only accept bookings on the day of the appointment ({$scheduleFmt}).";
            header("Location: service_request.php"); exit();
        }

        // Mechanic can accept until 30 mins before slot end (slot start + 90 mins).
        // e.g. 4:00 PM slot → ends 6:00 PM → accept until 5:30 PM
        $GRACE_SECONDS = 90 * 60;
        if ((strtotime($booking['schedule']) + $GRACE_SECONDS) <= time()) {
            $_SESSION['error'] = "Accept period has passed for Booking #{$booking_id} (cutoff: 30 mins before slot end).";
            header("Location: service_request.php"); exit();
        }

        $limStmt = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE mechanic_id = ? AND DATE(schedule) = ?
              AND status IN ('pending','in_progress') AND id != ?
        ");
        $limStmt->execute([$mechanic_id, $bookingDate, $booking_id]);
        if ((int)$limStmt->fetchColumn() >= $DAILY_LIMIT) {
            $_SESSION['error'] = "Daily limit of {$DAILY_LIMIT} active bookings reached for today.";
            header("Location: service_request.php"); exit();
        }

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'in_progress', started_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        if ($stmt->execute([$booking_id]) && $stmt->rowCount() > 0) {
            sendMessage($pdo, $mechanic_id, $cid, $booking_id,
                "Your booking #{$booking_id} on {$scheduleFmt} has been accepted! "
                . "Your service is now in progress. See you soon!"
            );
            notifyCustomer($pdo, $cid, $booking_id,
                'booking_accepted', 'Booking Accepted — Service In Progress!',
                "Your booking #{$booking_id} on {$scheduleFmt} has been accepted. "
                . "Your mechanic has started your service."
            );
            $_SESSION['success'] = "Booking #{$booking_id} accepted! Service is now in progress.";
        } else {
            $_SESSION['error'] = "Failed to update booking status.";
        }
    }

    // =====================================================================
    // REJECT / DECLINE
    // =====================================================================
    elseif (isset($_POST['reject_booking'])) {
        if ($booking['status'] !== 'pending') {
            $_SESSION['error'] = "Cannot decline booking with status: {$booking['status']}";
            header("Location: service_request.php"); exit();
        }

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled', cancelled_by = 'mechanic', updated_at = NOW()
            WHERE id = ?
        ");
        if ($stmt->execute([$booking_id]) && $stmt->rowCount() > 0) {
            sendMessage($pdo, $mechanic_id, $cid, $booking_id,
                "I'm sorry, I'm unable to accept your booking #{$booking_id} on {$scheduleFmt}. "
                . "Please re-book with another available mechanic."
            );
            notifyCustomer($pdo, $cid, $booking_id,
                'booking_declined', 'Booking Declined',
                "Your booking #{$booking_id} on {$scheduleFmt} has been declined. "
                . "Please book again with another available mechanic."
            );
            $_SESSION['success'] = "Booking #{$booking_id} declined.";
        } else {
            $_SESSION['error'] = "Failed to decline booking.";
        }
    }

    // =====================================================================
    // COMPLETE SERVICE
    // =====================================================================
    elseif (isset($_POST['complete_service'])) {
        if ($booking['status'] !== 'in_progress') {
            $_SESSION['error'] = "This booking cannot be completed.";
            header("Location: service_request.php"); exit();
        }

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'completed', completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        if ($stmt->execute([$booking_id]) && $stmt->rowCount() > 0) {
            sendMessage($pdo, $mechanic_id, $cid, $booking_id,
                "Your booking #{$booking_id} has been completed! "
                . "Thank you for choosing MotorService. Hope to serve you again!"
            );
            notifyCustomer($pdo, $cid, $booking_id,
                'booking_completed', 'Service Completed!',
                "Your booking #{$booking_id} has been completed! Thank you for choosing MotorService."
            );
            $_SESSION['success'] = "Service marked as completed!";
        } else {
            $_SESSION['error'] = "Failed to complete service.";
        }
    }

    // =====================================================================
    // NO SHOW — pending OR in_progress, in-shop only, same day only
    // =====================================================================
    elseif (isset($_POST['no_show']) || isset($_POST['mark_noshow'])) {

        if (!in_array($booking['status'], ['pending', 'in_progress'])) {
            $_SESSION['error'] = "Cannot mark this booking as no-show.";
            header("Location: service_request.php"); exit();
        }
        if ($bookingDate !== $today) {
            $_SESSION['error'] = "No-show can only be marked on the day of the booking.";
            header("Location: service_request.php"); exit();
        }
        if ($booking['service_location'] !== 'shop') {
            $_SESSION['error'] = "No-show only applies to in-shop service bookings.";
            header("Location: service_request.php"); exit();
        }

        // Cancel the booking
        $existingNote = trim($booking['note'] ?? '');
        $noShowNote   = 'No-show: customer did not arrive.';
        $newNote      = $existingNote !== '' ? $existingNote . ' | ' . $noShowNote : $noShowNote;

        $pdo->prepare("
            UPDATE bookings
            SET status       = 'cancelled',
                cancelled_by = 'no_show',
                note         = ?,
                updated_at   = NOW()
            WHERE id = ?
        ")->execute([$newNote, $booking_id]);

        // Process no-show with monthly reset
        $result      = processNoShow($pdo, $cid);
        $noShowCount = $result['count'];
        $banUntil    = $result['ban_until'];

        if ($noShowCount === 1) {
            notifyCustomer($pdo, $cid, $booking_id,
                'no_show_warning', '⚠️ No-Show Warning (1/3)',
                "You missed your booking #{$booking_id} on {$scheduleFmt}. "
                . "This is your 1st no-show this month. "
                . "Booking has been disabled for today. You may book again tomorrow."
            );
            sendMessage($pdo, $mechanic_id, $cid, $booking_id,
                "You missed your booking #{$booking_id} on {$scheduleFmt}. "
                . "1st no-show warning this month (1/3). Booking disabled for today."
            );
            $_SESSION['success'] = "Marked as no-show. Warning 1/3 — booking disabled for customer today.";

        } elseif ($noShowCount === 2) {
            notifyCustomer($pdo, $cid, $booking_id,
                'no_show_warning', '⚠️ No-Show Warning (2/3)',
                "You missed your booking #{$booking_id} on {$scheduleFmt}. "
                . "This is your 2nd no-show this month. "
                . "Booking has been disabled for today. "
                . "One more no-show this month will result in a 7-day suspension."
            );
            sendMessage($pdo, $mechanic_id, $cid, $booking_id,
                "You missed your booking #{$booking_id} on {$scheduleFmt}. "
                . "2nd no-show warning this month (2/3). One more = 7-day ban."
            );
            $_SESSION['success'] = "Marked as no-show. Warning 2/3 — booking disabled for customer today.";

        } else {
            // 3rd+ — 7-day ban
            $banFormatted = date('M d, Y', strtotime($banUntil));
            notifyCustomer($pdo, $cid, $booking_id,
                'no_show_banned', '🚫 Account Suspended — 7 Days',
                "You missed your booking #{$booking_id} on {$scheduleFmt}. "
                . "This is your 3rd no-show this month. "
                . "Your account has been suspended for 7 days. "
                . "You can book again on {$banFormatted}."
            );
            sendMessage($pdo, $mechanic_id, $cid, $booking_id,
                "Account suspended for 7 days due to 3 no-shows this month. "
                . "Customer can book again on {$banFormatted}."
            );
            $_SESSION['success'] = "Marked as no-show. Customer suspended until {$banFormatted}.";
        }
    }

    else {
        $_SESSION['error'] = "Invalid action.";
    }

} catch (Exception $e) {
    error_log("Error handling booking: " . $e->getMessage());
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: service_request.php");
exit();