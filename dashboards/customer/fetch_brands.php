<?php
require '../../includes/db_connect.php';

$service_key = $_GET['service_key'] ?? '';
if (!$service_key) {
    echo json_encode([]);
    exit;
}

// Fetch brands/packages/sizes for the service
$stmt = $pdo->prepare("SELECT * FROM brands WHERE service_id = (SELECT id FROM services WHERE service_key = ?)");
$stmt->execute([$service_key]);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($brands);
?>