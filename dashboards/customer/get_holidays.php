<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$stmt = $pdo->prepare("SELECT date FROM holidays");
$stmt->execute();
$holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($holidays);
?>