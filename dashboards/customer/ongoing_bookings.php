<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// FETCH ONGOING BOOKINGS ONLY
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        CONCAT(u.first_name,' ',u.last_name) AS mechanic_name
    FROM bookings b
    LEFT JOIN users u ON b.mechanic_id = u.id
    WHERE b.customer_id = ?
    AND b.status IN ('assigned','ongoing','in_progress')
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ongoing Bookings</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>
body {
    background-color: #0f0f0f;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    margin: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 30px;
}
.container {
    width: 90%;
    max-width: 1000px;
    background: #121212;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 6px 20px rgba(255, 138, 0, 0.5);
}
.back-dashboard {
    display: inline-block;
    margin-bottom: 15px;
    color: #f58025;
    text-decoration: none;
    font-weight: 600;
}
.back-dashboard:hover { color: #ffa64d; }

header {
    font-size: 24px;
    font-weight: 600;
    color: #ff7f00;
    text-align: center;
    margin-bottom: 20px;
}

/* TABLE SCROLL WRAPPER */
.table-wrapper {
    max-height: 420px; /* Adjust height as needed */
    overflow-y: auto;
    padding-right: 5px;
}
/* CUSTOM SCROLLBAR */
.table-wrapper::-webkit-scrollbar {
    width: 8px;
}
.table-wrapper::-webkit-scrollbar-track {
    background: #1a1a1a;
    border-radius: 10px;
}
.table-wrapper::-webkit-scrollbar-thumb {
    background: #ff7f00;
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
}
th {
    color: #ff7f00;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #ff7f00;
    position: sticky;
    top: 0;
    background: #121212; /* Ensure header stays visible */
    z-index: 10;
}
tbody tr {
    background-color: #222;
    box-shadow: 0 4px 8px rgba(255, 138, 0, 0.3);
}
td {
    padding: 12px 15px;
}

.status {
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    text-transform: capitalize;
    color: #fff;
    display: inline-block;
    min-width: 90px;
    text-align: center;
}
.assigned { background-color: #2980b9; }
.ongoing { background-color: #8e44ad; }
.in_progress { background-color: #27ae60; }

.no-bookings {
    text-align: center;
    color: #bbb;
    font-style: italic;
    font-size: 18px;
    padding: 40px 0;
}
</style>
</head>

<body>
<div class="container">

    <a href="customer_dashboard.php" class="back-dashboard">← Back to Dashboard</a>
    <header>Ongoing Bookings</header>

<?php if (empty($bookings)): ?>
    <p class="no-bookings">You have no ongoing bookings.</p>
<?php else: ?>
<div class="table-wrapper">
<table>
    <thead>
        <tr>
            <th>Service Type</th>
            <th>Vehicle</th>
            <th>Address</th>
            <th>Schedule</th>
            <th>Mechanic</th>
            <th>Status</th>
            <th>Note</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
        <tr>
            <td><?= htmlspecialchars($b['service_type']) ?></td>
            <td><?= htmlspecialchars($b['vehicle_type'] ?? '-') ?></td>
            <td><?= htmlspecialchars($b['saved_address'] ?? '-') ?></td>
            <td>
                <?= !empty($b['schedule']) ? date('M d, Y h:i A', strtotime($b['schedule'])) : '-' ?>
            </td>
            <td><?= htmlspecialchars($b['mechanic_name'] ?? 'TBA') ?></td>
            <td>
                <span class="status <?= strtolower($b['status']) ?>">
                    <?= ucfirst(str_replace('_',' ',$b['status'])) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($b['note'] ?: '-') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

</div>
</body>
</html>