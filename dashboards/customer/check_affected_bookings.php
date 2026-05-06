<?php
session_start();
require '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['has_affected' => false, 'count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Check for affected bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM bookings 
        WHERE customer_id = ? 
        AND status = 'awaiting_customer_action'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = $result['count'];
    
    echo json_encode([
        'has_affected' => $count > 0,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'has_affected' => false,
        'count' => 0,
        'error' => $e->getMessage()
    ]);
}