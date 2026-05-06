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
    // ✅ Mark the 3 most recent UNREAD notifications as cleared (is_read = 2)
    // Using subquery to work with MySQL limitations
    $stmt = $pdo->prepare("
        UPDATE customer_notifications 
        SET is_read = 2 
        WHERE customer_id = ? 
        AND is_read = 0
        AND id IN (
            SELECT id FROM (
                SELECT id FROM customer_notifications 
                WHERE customer_id = ? 
                AND is_read = 0
                ORDER BY created_at DESC 
                LIMIT 3
            ) AS subquery
        )
    ");
    
    $stmt->execute([$user_id, $user_id]);
    
    // ✅ Get the new unread count (only is_read = 0)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM customer_notifications 
        WHERE customer_id = ? 
        AND is_read = 0
    ");
    $countStmt->execute([$user_id]);
    $unreadCount = $countStmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unreadCount,
        'message' => 'Notifications cleared from dashboard'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>