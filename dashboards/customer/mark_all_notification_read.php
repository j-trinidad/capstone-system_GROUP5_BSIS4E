<?php
session_start();
require '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = $_SESSION['user_id'];

try {
    // Mark ALL notifications as read
    $stmt = $pdo->prepare("
        UPDATE customer_notifications 
        SET is_read = 1 
        WHERE customer_id = ? AND is_read = 0
    ");
    $stmt->execute([$customer_id]);
    
    echo json_encode([
        'success' => true,
        'unread_count' => 0
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>