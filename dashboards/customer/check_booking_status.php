<?php
session_start();
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("SELECT status FROM bookings WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode(['status' => $row['status'] ?? 'none']);
