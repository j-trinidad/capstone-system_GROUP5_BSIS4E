<?php
session_start();
require '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$mechanic_id = $_SESSION['user_id'];

try {
    // Get current availability status
    $stmt = $pdo->prepare("SELECT is_available FROM users WHERE id = ? AND role = 'mechanic'");
    $stmt->execute([$mechanic_id]);
    $currentStatus = $stmt->fetchColumn();
    
    // Toggle the status
    $newStatus = $currentStatus == 1 ? 0 : 1;
    
    // Update availability
    $updateStmt = $pdo->prepare("UPDATE users SET is_available = ? WHERE id = ?");
    $updateStmt->execute([$newStatus, $mechanic_id]);
    
    $statusText = $newStatus == 1 ? 'Available' : 'Unavailable';
    $statusColor = $newStatus == 1 ? '#28a745' : '#dc3545';
    
    echo json_encode([
        'success' => true,
        'is_available' => $newStatus,
        'status_text' => $statusText,
        'status_color' => $statusColor,
        'message' => 'Status updated to: ' . $statusText
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}