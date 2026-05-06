<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? null;
$step = $_GET['step'] ?? 'confirm'; // confirm, note, success

if (!$booking_id) {
    header("Location: service_request.php");
    exit;
}

// Check if booking exists and is pending
$stmt = $pdo->prepare("
    SELECT b.*, u.first_name, u.last_name, s.name AS service_name
    FROM bookings b
    JOIN users u ON b.customer_id = u.id
    LEFT JOIN services s ON b.service_type = s.service_key
    WHERE b.id = ? AND b.mechanic_id = ?
");
$stmt->execute([$booking_id, $mechanic_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking || $booking['status'] !== 'pending') {
    header("Location: service_request.php");
    exit;
}

// Handle confirmation (step 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_hold'])) {
    header("Location: hold.php?id=$booking_id&step=note");
    exit;
}

// Handle cancel (back to requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_action'])) {
    header("Location: service_request.php");
    exit;
}

// Handle note submission (step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_hold'])) {
    $note = trim($_POST['note'] ?? '');
    
    try {
        // ✅ Update booking: set status = 'on_hold' + add mechanic note
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'on_hold', mechanic_note = ? 
            WHERE id = ? AND mechanic_id = ? AND status = 'pending'
        ");
        $stmt->execute([$note, $booking_id, $mechanic_id]);

        // ✅ Notify customer
        $customerName = $booking['first_name'] . ' ' . $booking['last_name'];
        $serviceName = $booking['service_name'] ?: $booking['service_type'];
        $scheduleDate = date('M d, Y h:i A', strtotime($booking['schedule']));
        
        $message = "Good news! Your booking (ID: #$booking_id) for $serviceName scheduled on $scheduleDate is now ON HOLD and confirmed by the mechanic.";
        if (!empty($note)) {
            $message .= " Note: $note";
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read) 
            VALUES (?, ?, ?, NOW(), 0)
        ");
        $stmt->execute([$mechanic_id, $booking['customer_id'], $message]);

        header("Location: hold.php?id=$booking_id&step=success");
        exit;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

$customerName = htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']);
$serviceName = htmlspecialchars($booking['service_name'] ?: $booking['service_type']);
$scheduleDate = date('M d, Y h:i A', strtotime($booking['schedule']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hold Booking - MotorService</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #ff8c00;
    --secondary: #e52e71;
    --dark-bg: #0a0e27;
    --card-bg: #1a1f3a;
    --border: rgba(255, 140, 0, 0.2);
    --text-primary: #fff;
    --text-secondary: #b0b8d4;
    --success: #00d084;
    --error: #ff4757;
    --warning: #ffa502;
    --info: #2196F3;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

html, body {
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
}

a {
    color: inherit;
    text-decoration: none;
}

/* MAIN WRAPPER */
.wrapper {
    width: 100%;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
}

/* CONTAINER */
.container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* HEADER SECTION */
.header-section {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--border);
}

.icon-wrapper {
    margin-bottom: 20px;
}

.icon-wrapper i {
    font-size: 60px;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.icon-wrapper i.info {
    color: var(--info);
}

.icon-wrapper i.warning {
    color: var(--warning);
}

.icon-wrapper i.success {
    color: var(--success);
}

.header-title {
    font-size: 24px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 15px;
}

.header-description {
    color: var(--text-secondary);
    font-size: 15px;
    line-height: 1.6;
}

/* BOOKING INFO BOX */
.booking-info-box {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    border-left: 4px solid var(--info);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.booking-info-box p {
    margin: 8px 0;
    font-size: 14px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 10px;
    word-break: break-word;
}

.booking-info-box strong {
    color: var(--primary);
    white-space: nowrap;
}

.booking-info-box .status-change {
    padding: 10px;
    background: rgba(33, 150, 243, 0.1);
    border-radius: 8px;
    margin-top: 10px;
    color: var(--info);
    font-weight: 600;
    font-size: 14px;
}

/* FORM SECTION */
.form-section {
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 8px;
}

.form-group label .optional {
    color: var(--text-secondary);
    font-weight: 400;
    font-size: 12px;
}

.form-group textarea {
    width: 100%;
    background: rgba(255, 255, 255, 0.08);
    border: 2px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    padding: 12px 15px;
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
    resize: vertical;
    min-height: 120px;
    transition: all 0.3s ease;
}

.form-group textarea:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 10px rgba(255, 140, 0, 0.3);
    background: rgba(255, 255, 255, 0.12);
}

.form-group textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.info-note {
    background: rgba(33, 150, 243, 0.15);
    border: 1px solid var(--info);
    color: var(--info);
    padding: 12px 15px;
    border-radius: 8px;
    margin-top: 10px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    line-height: 1.6;
}

.info-note i {
    margin-top: 2px;
    flex-shrink: 0;
}

.info-note div {
    flex: 1;
}

.info-note div strong {
    color: var(--info);
    display: block;
    margin-bottom: 8px;
}

.info-note div ul {
    margin: 8px 0 0 20px;
    padding: 0;
}

.info-note div li {
    margin: 4px 0;
}

/* BUTTON GROUP */
.button-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 30px;
}

.btn {
    padding: 14px 28px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.3s ease;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    font-family: 'Outfit', sans-serif;
}

.btn:active {
    transform: scale(0.98);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #1976D2);
    color: #fff;
}

.btn-info:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(33, 150, 243, 0.5);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.5);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: var(--primary);
    transform: translateY(-3px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #00b86d);
    color: #1a1f3a;
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 208, 132, 0.5);
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .wrapper {
        padding: 80px 15px 100px;
    }

    .container {
        padding: 25px 20px;
    }

    .header-title {
        font-size: 20px;
    }

    .header-description {
        font-size: 14px;
    }

    .icon-wrapper i {
        font-size: 50px;
    }

    .button-group {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        padding: 16px 28px;
    }

    .booking-info-box p {
        font-size: 13px;
    }

    .form-group textarea {
        font-size: 14px;
        padding: 14px;
    }

    .info-note {
        flex-direction: column;
    }

    .info-note i {
        margin-top: 0;
    }
}

@media (max-width: 480px) {
    .wrapper {
        padding: 80px 12px 120px;
    }

    .container {
        padding: 20px 15px;
    }

    .header-title {
        font-size: 18px;
    }

    .header-description {
        font-size: 13px;
    }

    .icon-wrapper i {
        font-size: 45px;
    }

    .btn {
        padding: 14px 20px;
        font-size: 13px;
        gap: 8px;
    }

    .booking-info-box {
        padding: 15px;
    }

    .booking-info-box p {
        font-size: 12px;
    }

    .form-group label {
        font-size: 13px;
    }

    .form-group textarea {
        min-height: 100px;
        font-size: 13px;
    }

    .info-note {
        font-size: 12px;
        padding: 10px 12px;
    }

    .header-section {
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="container">
        <?php if ($step === 'success'): ?>
            <!-- SUCCESS STEP -->
            <div class="header-section">
                <div class="icon-wrapper">
                    <i class="fas fa-check-circle success"></i>
                </div>
                <h2 class="header-title">Booking On Hold Successfully!</h2>
                <p class="header-description">
                    The booking is now on hold and the customer has been notified.
                </p>
            </div>

            <div class="booking-info-box">
                <p><strong><i class="fas fa-hashtag"></i> Booking ID:</strong> #<?= htmlspecialchars($booking_id) ?></p>
                <p><strong><i class="fas fa-user"></i> Customer:</strong> <?= $customerName ?></p>
                <p><strong><i class="fas fa-wrench"></i> Service:</strong> <?= $serviceName ?></p>
                <p><strong><i class="fas fa-calendar"></i> Schedule:</strong> <?= $scheduleDate ?></p>
                <div class="status-change">
                    <i class="fas fa-info-circle"></i> 
                    Status: <strong>ON HOLD</strong> - Customer has been notified via messages
                </div>
            </div>

            <div class="button-group">
                <a href="service_request.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Back to Requests
                </a>
                <a href="mechanic_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>

        <?php elseif ($step === 'note'): ?>
            <!-- NOTE STEP -->
            <div class="header-section">
                <div class="icon-wrapper">
                    <i class="fas fa-comment-alt warning"></i>
                </div>
                <h2 class="header-title">Add Note (Optional)</h2>
                <p class="header-description">
                    You can add a note to inform the customer about the hold status or any preparations.
                </p>
            </div>

            <div class="booking-info-box">
                <p><strong><i class="fas fa-hashtag"></i> Booking ID:</strong> #<?= htmlspecialchars($booking_id) ?></p>
                <p><strong><i class="fas fa-user"></i> Customer:</strong> <?= $customerName ?></p>
                <p><strong><i class="fas fa-calendar"></i> Schedule:</strong> <?= $scheduleDate ?></p>
            </div>

            <form method="POST" class="form-section">
                <div class="form-group">
                    <label for="note">
                        <i class="fas fa-sticky-note"></i> 
                        Note to Customer
                        <span class="optional">(Optional)</span>
                    </label>
                    <textarea 
                        name="note" 
                        id="note" 
                        placeholder="Example: Your booking is confirmed. I'm preparing the necessary parts and will be ready on the scheduled date..."
                    ><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
                    
                    <div class="info-note">
                        <i class="fas fa-info-circle"></i>
                        <span>This note will be included in the notification message sent to the customer.</span>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" name="submit_hold" class="btn btn-info">
                        <i class="fas fa-hand-paper"></i> Confirm Hold
                    </button>
                    <button type="submit" name="cancel_action" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                </div>
            </form>

        <?php else: ?>
            <!-- CONFIRM STEP -->
            <div class="header-section">
                <div class="icon-wrapper">
                    <i class="fas fa-hand-paper info"></i>
                </div>
                <h2 class="header-title">Put Booking On Hold</h2>
                <p class="header-description">
                    Placing this booking on hold will notify the customer that you've confirmed their reservation and are preparing for the service.
                </p>
            </div>

            <div class="booking-info-box">
                <p><strong><i class="fas fa-hashtag"></i> Booking ID:</strong> #<?= htmlspecialchars($booking_id) ?></p>
                <p><strong><i class="fas fa-user"></i> Customer:</strong> <?= $customerName ?></p>
                <p><strong><i class="fas fa-wrench"></i> Service:</strong> <?= $serviceName ?></p>
                <p><strong><i class="fas fa-calendar"></i> Schedule:</strong> <?= $scheduleDate ?></p>
                <div class="status-change">
                    <i class="fas fa-exchange-alt"></i> 
                    Status will change: <strong>Pending → On Hold</strong>
                </div>
            </div>

            <div class="info-note" style="margin-bottom: 20px;">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong>What happens when you hold a booking?</strong>
                    <ul>
                        <li>Customer receives a notification that their booking is confirmed</li>
                        <li>Booking status changes to "On Hold"</li>
                        <li>You can still accept it later to start preparing</li>
                        <li>Customer knows you've reserved their time slot</li>
                    </ul>
                </div>
            </div>

            <form method="POST">
                <div class="button-group">
                    <button type="submit" name="confirm_hold" class="btn btn-info">
                        <i class="fas fa-hand-paper"></i> Yes, Hold This Booking
                    </button>
                    <button type="submit" name="cancel_action" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> No, Go Back
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Prevent zoom on double tap (iOS)
let lastTouchEnd = 0;
document.addEventListener('touchend', function (event) {
    const now = (new Date()).getTime();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);
</script>

</body>
</html>