<?php
require '../../includes/session_check.php';
checkRole('customer');
require '../../includes/db_connect.php';

$customer_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    die("Invalid booking ID.");
}

// Fetch booking details
$stmt = $pdo->prepare("
    SELECT b.*, u.first_name, u.last_name,
           ma.reason as absence_reason
    FROM bookings b
    LEFT JOIN users u ON b.mechanic_id = u.id
    LEFT JOIN mechanic_absences ma ON b.absence_id = ma.id
    WHERE b.id = ? AND b.customer_id = ?
");
$stmt->execute([$booking_id, $customer_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("Booking not found or you don't have permission to access it.");
}

// Fetch available mechanics (excluding the current one)
$mechanicsStmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, display_name, profile_pic, address
    FROM users 
    WHERE role = 'mechanic' 
    AND is_available = 1 
    AND is_disabled = 0
    AND id != ?
    ORDER BY first_name ASC
");
$mechanicsStmt->execute([$booking['mechanic_id']]);
$availableMechanics = $mechanicsStmt->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$error = '';

// Handle reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign'])) {
    $new_mechanic_id = (int)$_POST['new_mechanic_id'];
    
    if ($new_mechanic_id <= 0) {
        $error = "Please select a mechanic.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $updateStmt = $pdo->prepare("
                UPDATE bookings 
                SET mechanic_id = ?, 
                    status = 'pending', 
                    absence_id = NULL,
                    updated_at = NOW()
                WHERE id = ? AND customer_id = ?
            ");
            $updateStmt->execute([$new_mechanic_id, $booking_id, $customer_id]);
            
            $mechanicStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $mechanicStmt->execute([$new_mechanic_id]);
            $newMechanic = $mechanicStmt->fetch(PDO::FETCH_ASSOC);
            
            $scheduleDate = date('M d, Y h:i A', strtotime($booking['schedule']));
            $messageToMechanic = "🔔 NEW BOOKING ASSIGNED!\n\n" .
                                "You have been assigned to a booking.\n\n" .
                                "Booking ID: #{$booking_id}\n" .
                                "Service: " . htmlspecialchars($booking['service_type']) . "\n" .
                                "Scheduled: {$scheduleDate}\n\n" .
                                "Please check your dashboard to ACCEPT or DECLINE this booking.";
            
            $notifyStmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
                VALUES (?, ?, ?, NOW(), 0)
            ");
            $notifyStmt->execute([$customer_id, $new_mechanic_id, $messageToMechanic]);
            
            $pdo->commit();
            $success = true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to reassign mechanic: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reassign Mechanic - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #1a56db;
    --secondary: #1e40af;
    --dark-bg: #f0f4ff;
    --card-bg: #ffffff;
    --border: rgba(26, 86, 219, 0.2);
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --success: #059669;
    --error: #dc2626;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    min-height: 100vh;
    padding: 20px;
}

a {
    color: inherit;
    text-decoration: none;
}

.container {
    max-width: 1000px;
    margin: 40px auto;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 8px 32px rgba(26, 86, 219, 0.1);
}

h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 15px;
}

h1 i {
    font-size: 36px;
}

h3 {
    color: var(--primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
}

.booking-info {
    background: rgba(26, 86, 219, 0.04);
    border: 1px solid var(--border);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.booking-info h3 {
    color: var(--primary);
    margin-top: 0;
}

.booking-info p {
    color: var(--text-secondary);
    margin: 10px 0;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.booking-label {
    color: var(--primary);
    font-weight: 700;
    min-width: 120px;
}

.alert {
    background: rgba(220, 38, 38, 0.06);
    border: 1px solid rgba(220, 38, 38, 0.3);
    color: var(--error);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert i {
    font-size: 20px;
}

.success-msg {
    background: rgba(5, 150, 105, 0.06);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: var(--success);
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
}

.success-msg i {
    font-size: 60px;
    margin-bottom: 15px;
    display: block;
}

.success-msg h2 {
    font-size: 24px;
    margin-bottom: 10px;
}

.success-msg p {
    font-size: 14px;
    color: var(--text-secondary);
}

.mechanics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.mechanic-card {
    background: #f8faff;
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.mechanic-card:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 24px rgba(26, 86, 219, 0.15);
    transform: translateY(-5px);
    background: rgba(26, 86, 219, 0.04);
}

.mechanic-card.selected {
    border-color: var(--success);
    background: rgba(5, 150, 105, 0.06);
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.2);
}

.mechanic-card input[type="radio"] {
    display: none;
}

.profile-pic {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 15px;
    overflow: hidden;
    border: 4px solid var(--primary);
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.profile-pic img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 15px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: #ffffff;
    font-weight: 700;
}

.mechanic-card h4 {
    color: var(--text-primary);
    font-size: 16px;
    margin-bottom: 10px;
    text-align: center;
    font-weight: 700;
}

.mechanic-card p {
    color: var(--text-secondary);
    font-size: 13px;
    margin: 6px 0;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.mechanic-card p i {
    color: var(--primary);
    font-size: 12px;
}

.no-mechanics {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.no-mechanics i {
    font-size: 60px;
    margin-bottom: 15px;
    display: block;
    color: var(--border);
}

.no-mechanics h3 {
    color: var(--primary);
    font-size: 22px;
    margin: 15px 0;
    justify-content: center;
}

.no-mechanics p {
    font-size: 14px;
    color: var(--text-secondary);
}

.button-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(26, 86, 219, 0.1);
    font-family: 'Outfit', sans-serif;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(26, 86, 219, 0.3);
}

.btn-secondary {
    background: rgba(26, 86, 219, 0.06);
    color: var(--primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(26, 86, 219, 0.12);
    transform: translateY(-2px);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.text-center {
    text-align: center;
}

@media (max-width: 768px) {
    .container {
        padding: 20px;
        margin: 20px auto;
    }

    h1 {
        font-size: 24px;
    }

    .mechanics-grid {
        grid-template-columns: 1fr;
    }

    .button-group {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .booking-info {
        padding: 15px;
    }

    .booking-info p {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>
<body>

<div class="container">
    <?php if ($success): ?>
        <div class="success-msg">
            <i class="fas fa-check-circle"></i>
            <h2>Mechanic Reassigned Successfully!</h2>
            <p>Your new mechanic has been notified and will need to accept the booking.</p>
        </div>
        <div class="text-center">
            <a href="customer_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    <?php else: ?>
        <h1><i class="fas fa-user-cog"></i> Reassign Mechanic</h1>
        
        <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Booking Information -->
        <div class="booking-info">
            <h3><i class="fas fa-info-circle"></i> Current Booking</h3>
            <p>
                <span class="booking-label"><i class="fas fa-hashtag"></i> ID:</span>
                #<?= $booking['id'] ?>
            </p>
            <p>
                <span class="booking-label"><i class="fas fa-wrench"></i> Service:</span>
                <?= htmlspecialchars($booking['service_type']) ?>
            </p>
            <p>
                <span class="booking-label"><i class="fas fa-calendar"></i> Schedule:</span>
                <?= date('M d, Y h:i A', strtotime($booking['schedule'])) ?>
            </p>
            <?php if (!empty($booking['absence_reason'])): ?>
                <div class="alert" style="margin-top: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Previous mechanic unavailable:</strong>
                        <?= htmlspecialchars($booking['absence_reason']) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Available Mechanics -->
        <?php if (empty($availableMechanics)): ?>
            <div class="no-mechanics">
                <i class="fas fa-user-slash"></i>
                <h3>No Available Mechanics</h3>
                <p>Sorry, there are no available mechanics at the moment.<br>Please try again later or cancel your booking.</p>
            </div>
            <div class="text-center" style="margin-top: 25px;">
                <a href="customer_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <h3><i class="fas fa-users"></i> Select a New Mechanic</h3>
            
            <form method="POST" id="reassignForm">
                <div class="mechanics-grid">
                    <?php foreach ($availableMechanics as $mechanic): 
                        $initials = strtoupper(substr($mechanic['first_name'], 0, 1) . substr($mechanic['last_name'], 0, 1));
                    ?>
                        <label class="mechanic-card" onclick="selectMechanic(this, <?= $mechanic['id'] ?>)">
                            <input type="radio" name="new_mechanic_id" value="<?= $mechanic['id'] ?>" required>
                            
                            <?php if (!empty($mechanic['profile_pic'])): ?>
                                <div class="profile-pic">
                                    <img src="../../uploads/profile_pics/<?= htmlspecialchars($mechanic['profile_pic']) ?>" alt="Profile">
                                </div>
                            <?php else: ?>
                                <div class="default-avatar"><?= $initials ?></div>
                            <?php endif; ?>
                            
                            <h4>
                                <?php 
                                if (!empty($mechanic['display_name'])) {
                                    echo htmlspecialchars($mechanic['display_name']);
                                } else {
                                    echo htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']);
                                }
                                ?>
                            </h4>
                            
                            <p>
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($mechanic['email']) ?>
                            </p>
                            
                            <?php if (!empty($mechanic['address'])): ?>
                                <p>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($mechanic['address']) ?>
                                </p>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="reassign" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="fas fa-check"></i> Confirm Reassignment
                    </button>
                    <a href="customer_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function selectMechanic(card, mechanicId) {
    document.querySelectorAll('.mechanic-card').forEach(c => {
        c.classList.remove('selected');
    });
    card.classList.add('selected');
    card.querySelector('input[type="radio"]').checked = true;
    document.getElementById('submitBtn').disabled = false;
}
</script>

</body>
</html>