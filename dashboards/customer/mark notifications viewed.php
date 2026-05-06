<?php
session_start();
require '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // ✅ Mark ALL unread notifications as viewed (is_read = 1)
    // This happens when user opens the bell dropdown
    $stmt = $pdo->prepare("
        UPDATE customer_notifications 
        SET is_read = 1 
        WHERE customer_id = ? 
        AND is_read = 0
    ");
    
    $stmt->execute([$user_id]);
    
    // ✅ Return success with 0 unread count
    echo json_encode([
        'success' => true,
        'unread_count' => 0,
        'message' => 'All notifications marked as viewed'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>