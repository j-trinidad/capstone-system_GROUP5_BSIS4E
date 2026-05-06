<?php
session_start();
require '../../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$mechanic_id = $_SESSION['user_id'];

// ✅ Get updated stats with new status flow
$serviceRequestsStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'pending'");
$serviceRequestsStmt->execute([$mechanic_id]);
$serviceRequests = $serviceRequestsStmt->fetchColumn();

$preparingStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'preparing'");
$preparingStmt->execute([$mechanic_id]);
$preparingJobs = $preparingStmt->fetchColumn();

$inProgressStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'in_progress'");
$inProgressStmt->execute([$mechanic_id]);
$inProgressJobs = $inProgressStmt->fetchColumn();

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status = 'completed'");
$completedStmt->execute([$mechanic_id]);
$completedJobs = $completedStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'serviceRequests' => $serviceRequests,
    'preparingJobs' => $preparingJobs,
    'inProgressJobs' => $inProgressJobs,
    'completedJobs' => $completedJobs
]);