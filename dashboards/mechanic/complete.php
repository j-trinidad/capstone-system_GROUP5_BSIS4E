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

// Check if booking exists and is ongoing
$stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? AND mechanic_id = ?");
$stmt->execute([$booking_id, $mechanic_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking || $booking['status'] !== 'ongoing') {
    header("Location: service_request.php");
    exit;
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ? AND mechanic_id = ? AND status = 'ongoing'");
        $result = $stmt->execute([$booking_id, $mechanic_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            $confirmed = true;
            // Optional: Log success
            error_log("Booking $booking_id completed by mechanic $mechanic_id");
        } else {
            $error = "Failed to update booking status. It may have been completed already.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        error_log("Complete booking error: " . $e->getMessage());
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
<title>Complete Booking</title>
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
.btn.confirm { background:linear-gradient(45deg,#17a2b8,#138496); color:#fff; }
.btn.cancel { background:linear-gradient(45deg,#dc3545,#c0392b); color:#fff; }
.btn.back { background:linear-gradient(45deg,#ff8a00,#e52e71); color:#fff; }
.btn:hover { transform:scale(1.05); box-shadow:0 5px 15px rgba(0,0,0,.5); }

.success-icon { font-size:60px; color:#17a2b8; margin-bottom:20px; }
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
    <h2>Booking Completed Successfully!</h2>
    <p>Congratulations! You have marked Booking #<?= $booking_id ?> as completed. The customer will be notified, and the booking is now closed.</p>
    
    <div class="next-steps">
        <h3><i class="fas fa-list-check"></i> Next Steps:</h3>
        <ul>
            <li>Ensure all parts and services were delivered as agreed.</li>
            <li>Collect any final payments if applicable.</li>
            <li>Check for customer feedback or follow-up requests.</li>
            <li>Prepare for your next booking.</li>
        </ul>
    </div>
    
    <div class="btns">
        <button class="btn back" onclick="location.href='service_request.php'">Back to Requests</button>
    </div>
<?php else: ?>
    <div class="warning-icon"><i class="fas fa-question-circle"></i></div>
    <h2>Confirm Booking Completion</h2>
    <p>By marking this booking as completed, you confirm that all services have been performed satisfactorily. This action cannot be undone without admin approval.</p>
    <p><strong>Booking ID:</strong> #<?= $booking_id ?><br>
    <strong>Status Change:</strong> Ongoing → Completed</p>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="POST" class="btns">
        <button type="submit" name="confirm" class="btn confirm">✔️ Mark as Completed</button>
        <button type="submit" name="cancel" class="btn cancel">❌ Cancel</button>
    </form>
<?php endif; ?>
</div>

</body>
</html>