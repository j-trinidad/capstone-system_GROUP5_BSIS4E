<?php
session_start();
require '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;

try {
    if ($notification_id) {
        // Mark specific notification as read
        $stmt = $pdo->prepare("
            UPDATE customer_notifications 
            SET is_read = 1 
            WHERE id = ? AND customer_id = ?
        ");
        $stmt->execute([$notification_id, $customer_id]);
    }
    
    // Get updated unread count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM customer_notifications 
        WHERE customer_id = ? AND is_read = 0
    ");
    $countStmt->execute([$customer_id]);
    $unreadCount = $countStmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unreadCount
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>