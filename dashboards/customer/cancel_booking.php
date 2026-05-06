<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$booking_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$booking_id) {
    $_SESSION['error'] = 'Invalid booking ID.';
    header('Location: customer_my_bookings.php');
    exit;
}

// Verify the booking belongs to this customer and is still pending
$stmt = $pdo->prepare("SELECT status FROM bookings WHERE id = ? AND customer_id = ?");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    $_SESSION['error'] = 'Booking not found.';
    header('Location: customer_my_bookings.php');
    exit;
}

if ($booking['status'] !== 'pending') {
    $_SESSION['error'] = 'Only pending bookings can be cancelled. This booking is already ' . str_replace('_', ' ', $booking['status']) . '.';
    header('Location: customer_my_bookings.php');
    exit;
}

// Update booking status to cancelled_by_customer
$updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_by = 'customer', updated_at = NOW() WHERE id = ?");
if ($updateStmt->execute([$booking_id])) {
    $_SESSION['success'] = 'Booking #' . $booking_id . ' has been cancelled successfully.';
} else {
    $_SESSION['error'] = 'Failed to cancel booking. Please try again.';
}

header('Location: customer_my_bookings.php');
exit;