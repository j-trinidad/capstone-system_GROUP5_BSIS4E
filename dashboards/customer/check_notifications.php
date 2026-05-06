<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Fetch unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

// Fetch latest notifications
$stmt = $pdo->prepare("
    SELECT id, title, message, created_at, type
    FROM customer_notifications 
    WHERE customer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$user_id]);
$recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'unread_count' => $unread_count,
    'notifications' => $recentNotifications
]);
?>