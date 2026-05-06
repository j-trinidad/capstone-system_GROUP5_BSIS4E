<?php
session_start();
require '../../includes/db_connect.php';

// ✅ Check if mechanic is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$mechanic_id = $_SESSION['user_id'];

// ✅ Get POST data
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$message = trim($_POST['message'] ?? '');

if ($customer_id === 0 || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// ✅ Verify customer exists (optional but recommended)
$custCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'customer'");
$custCheck->execute([$customer_id]);
if (!$custCheck->fetch()) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid customer']);
    exit();
}

// ✅ Insert message into database
try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->execute([$mechanic_id, $customer_id, $message]);
    
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
}
?>