<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;

$query = "
    SELECT u.id, u.first_name, u.last_name
    FROM users u
    WHERE u.role = 'mechanic' AND u.is_available = 1 AND u.is_disabled = 0
    AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.mechanic_id = u.id AND DATE(b.schedule) = ? AND b.status IN ('assigned', 'in_progress', 'ongoing')
    )
";

$params = [$date ?: date('Y-m-d')]; // Use today if no date

if ($date) {
    $query .= " AND NOT EXISTS (
        SELECT 1 FROM mechanic_absence ma
        WHERE ma.mechanic_id = u.id AND ma.absence_date = ?
    )";
    $params[] = $date;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($mechanics);
?>