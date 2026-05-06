<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? null;
$confirmed = false;
$error = '';

if (!$booking_id) {
    header("Location: service_request.php");
    exit;
}

// Check if booking exists and is pending
$stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? AND mechanic_id = ?");
$stmt->execute([$booking_id, $mechanic_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking || $booking['status'] !== 'pending') {
    header("Location: service_request.php");
    exit;
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'ongoing' WHERE id = ? AND mechanic_id = ? AND status = 'pending'");
        $result = $stmt->execute([$booking_id, $mechanic_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $confirmed = true;
            // Optional: Log success
            error_log("Booking $booking_id accepted by mechanic $mechanic_id, status set to 'ongoing'");
        } else {
            $error = "Failed to update booking status. It may have been accepted by another mechanic.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Accept booking error: " . $e->getMessage());
    }
}

// Handle cancel
if (isset($_POST['cancel'])) {
    header("Location: service_request.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Accept Booking</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body {
    font-family:'Poppins',sans-serif;
    background:linear-gradient(135deg,rgba(0,0,0,.95),rgba(20,20,20,.95)),
               url('../../assets/img/bg.png') center/cover no-repeat fixed;
    color:#fff; margin:0; padding:0; min-height:100vh;
    display:flex; justify-content:center; align-items:center;
    animation:fadeIn 1.2s ease-in-out;
}
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

.container {
    background:linear-gradient(135deg,#1a1a1a,#2c2c2c); padding:40px; border-radius:20px;
    width:100%; max-width:600px; text-align:center;
    box-shadow:0 10px 40px rgba(255,138,0,.5); border:1px solid rgba(255,138,0,.3);
    animation:slideIn 0.5s ease;
}
@keyframes slideIn { from { transform:translateY(-20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

h2 { color:#ffb347; margin-bottom:20px; text-shadow:0 0 10px rgba(255,138,0,.5); }
p { color:#ccc; margin-bottom:20px; font-size:16px; line-height:1.6; }

.btns { display:flex; justify-content:center; gap:15px; flex-wrap:wrap; margin-top:30px; }
.btn {
    padding:12px 25px; border:none; border-radius:25px; cursor:pointer; font-weight:600;
    transition:0.4s ease; box-shadow:0 3px 10px rgba(0,0,0,.3);
}
.btn.confirm { background:linear-gradient(45deg,#28a745,#27ae60); color:#fff; }
.btn.cancel { background:linear-gradient(45deg,#dc3545,#c0392b); color:#fff; }
.btn.back { background:linear-gradient(45deg,#ff8a00,#e52e71); color:#fff; }
.btn:hover { transform:scale(1.05); box-shadow:0 5px 15px rgba(0,0,0,.5); }

.success-icon { font-size:60px; color:#28a745; margin-bottom:20px; }
.warning-icon { font-size:50px; color:#ffb347; margin-bottom:20px; }

.next-steps { background:rgba(255,255,255,.05); padding:20px; border-radius:10px; margin-top:20px; text-align:left; }
.next-steps h3 { color:#ffb347; margin-bottom:10px; }
.next-steps ul { color:#fff; padding-left:20px; }
.next-steps li { margin-bottom:8px; }

.error { color:#ff6b6b; font-size:14px; margin-top:10px; }

@media (max-width:768px) {
    .container { padding:20px; max-width:95%; }
    .btns { flex-direction:column; align-items:center; }
}
</style>
</head>
<body>

<div class="container">
<?php if ($confirmed): ?>
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
    <h2>Booking Accepted Successfully!</h2>
    <p>Congratulations! You have accepted Booking #<?= $booking_id ?>. The status has been updated to "Ongoing". The customer will be notified, and you are now responsible for completing this service.</p>
    
    <div class="next-steps">
        <h3><i class="fas fa-list-check"></i> Next Steps:</h3>
        <ul>
            <li>Contact the customer to confirm the schedule and any special instructions.</li>
            <li>Prepare your tools and any required parts for the service.</li>
            <li>Update the booking status to "Completed" once the service is finished.</li>
            <li>If any issues arise, communicate with the customer or admin immediately.</li>
        </ul>
    </div>
    
    <div class="btns">
        <button class="btn back" onclick="location.href='service_request.php'">Back to Requests</button>
    </div>
<?php else: ?>
    <div class="warning-icon"><i class="fas fa-question-circle"></i></div>
    <h2>Confirm Booking Acceptance</h2>
    <p>By accepting this booking, you agree to take responsibility for completing the service as scheduled.</p>
    <p><strong>Booking ID:</strong> #<?= $booking_id ?><br>
    <strong>Status Change:</strong> Pending → Ongoing</p>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="POST" class="btns">
        <button type="submit" name="confirm" class="btn confirm">✅ Accept Booking</button>
        <button type="submit" name="cancel" class="btn cancel">❌ Cancel</button>
    </form>
<?php endif; ?>
</div>

</body>
</html>