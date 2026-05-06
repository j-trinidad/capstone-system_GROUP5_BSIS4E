<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) AS upcoming FROM bookings WHERE customer_id = ? AND status IN ('assigned', 'ongoing')");
$stmt->execute([$user_id]);
$upcoming = $stmt->fetch()['upcoming'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS completed FROM bookings WHERE customer_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed = $stmt->fetch()['completed'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS pending FROM bookings WHERE customer_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending = $stmt->fetch()['pending'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS messages FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$msg_count = $stmt->fetch()['messages'];

// Active booking
$stmt = $pdo->prepare("SELECT b.*, m.name AS mechanic_name FROM bookings b LEFT JOIN mechanics m ON b.mechanic_id = m.id WHERE b.customer_id = ? AND b.status IN ('assigned', 'ongoing') ORDER BY b.created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$active_booking = $stmt->fetch(PDO::FETCH_ASSOC);

// Latest 3 messages
$stmt = $pdo->prepare("SELECT sender_id, message_text, created_at FROM messages WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'stats' => [
        'upcoming' => $upcoming,
        'completed' => $completed,
        'pending' => $pending,
        'messages' => $msg_count
    ],
    'active_booking' => $active_booking,
    'messages' => $messages
]);
