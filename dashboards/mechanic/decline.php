<?php
require '../../includes/session_check.php';
checkRole('mechanic');
require '../../includes/db_connect.php';

$mechanic_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? null;
$step = $_GET['step'] ?? 'confirm'; // confirm, reason, success

if (!$booking_id) {
    header("Location: service_request.php");
    exit;
}

// Check if booking exists and is pending
$stmt = $pdo->prepare("SELECT id, status, customer_id FROM bookings WHERE id = ? AND mechanic_id = ?");
$stmt->execute([$booking_id, $mechanic_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking || $booking['status'] !== 'pending') {
    header("Location: service_request.php");
    exit;
}

// Handle confirmation (step 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_decline'])) {
    header("Location: decline.php?id=$booking_id&step=reason");
    exit;
}

// Handle cancel (back to requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_action'])) {
    header("Location: service_request.php");
    exit;
}

// Handle reason submission (step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reason'])) {
    $reason = trim($_POST['reason'] ?? '');
    if (!empty($reason)) {
        try {
            // ✅ Update booking: set status = 'cancelled' + cancelled_by = 'mechanic'
            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET status = 'cancelled', mechanic_note = ?, cancelled_by = 'mechanic' 
                WHERE id = ? AND mechanic_id = ? AND status = 'pending'
            ");
            $stmt->execute([$reason, $booking_id, $mechanic_id]);

            // ✅ Notify customer
            $message = "Your booking (ID: $booking_id) has been cancelled by the mechanic. Reason: $reason.";
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read) 
                VALUES (?, ?, ?, NOW(), 0)
            ");
            $stmt->execute([$mechanic_id, $booking['customer_id'], $message]);

            header("Location: decline.php?id=$booking_id&step=success");
            exit;
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } else {
        $error = "Reason is required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cancel Booking - MotorService</title>
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
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

html, body {
    height: 100%;
    font-family: 'Outfit', sans-serif;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    color: var(--text-primary);
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

/* MAIN WRAPPER */
.wrapper {
    width: 100%;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, var(--dark-bg), #1a1f3a);
    -webkit-overflow-scrolling: touch;
}

.wrapper::-webkit-scrollbar {
    width: 8px;
}

.wrapper::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
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

.icon-wrapper i.error {
    color: var(--error);
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
    border-left: 4px solid var(--primary);
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
}

.booking-info-box strong {
    color: var(--primary);
}

.booking-info-box .status-change {
    padding: 10px;
    background: rgba(255, 71, 87, 0.1);
    border-radius: 8px;
    margin-top: 10px;
    color: var(--error);
    font-weight: 600;
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

.form-group label .required {
    color: var(--error);
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

.error-message {
    background: rgba(255, 71, 87, 0.15);
    border: 1px solid var(--error);
    color: var(--error);
    padding: 12px 15px;
    border-radius: 8px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
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

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #1a1f3a;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(255, 140, 0, 0.5);
}

.btn-danger {
    background: linear-gradient(135deg, var(--error), #ff3838);
    color: #fff;
}

.btn-danger:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(255, 71, 87, 0.5);
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

/* SUCCESS MESSAGE */
.success-content {
    text-align: center;
}

.success-content .check-icon {
    font-size: 70px;
    color: var(--error);
    margin-bottom: 20px;
    display: block;
    animation: scaleIn 0.5s ease;
}

/* MOBILE RESPONSIVE */
@media (max-width: 768px) {
    .wrapper {
        padding: 90px 20px 100px;
    }

    .container {
        padding: 25px 20px;
        margin-bottom: 40px;
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
        margin-bottom: 60px;
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
}

@media (max-width: 480px) {
    .wrapper {
        padding: 80px 15px 120px;
    }

    .container {
        padding: 20px 15px;
        margin-bottom: 60px;
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

    .button-group {
        margin-bottom: 80px;
    }

    .btn {
        padding: 18px 28px;
        font-size: 13px;
    }

    .booking-info-box {
        padding: 15px;
    }

    .booking-info-box p {
        font-size: 12px;
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
                    <i class="fas fa-times-circle success"></i>
                </div>
                <h2 class="header-title">Booking Cancelled Successfully!</h2>
                <p class="header-description">
                    The booking has been cancelled and the customer has been notified.
                </p>
            </div>

            <div class="booking-info-box">
                <p><strong><i class="fas fa-hashtag"></i> Booking ID:</strong> #<?= htmlspecialchars($booking_id) ?></p>
                <p><strong><i class="fas fa-info-circle"></i> Status:</strong> Cancelled</p>
                <p><strong><i class="fas fa-user"></i> Cancelled By:</strong> Mechanic</p>
            </div>

            <div class="button-group">
                <a href="service_request.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Back to Requests
                </a>
                <a href="mechanic_history.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i> View History
                </a>
            </div>

        <?php elseif ($step === 'reason'): ?>
            <!-- REASON STEP -->
            <div class="header-section">
                <div class="icon-wrapper">
                    <i class="fas fa-exclamation-triangle warning"></i>
                </div>
                <h2 class="header-title">Provide Cancellation Reason</h2>
                <p class="header-description">
                    Please explain why you're cancelling this booking. This will be shared with the customer for transparency.
                </p>
            </div>

            <div class="booking-info-box">
                <p><strong><i class="fas fa-hashtag"></i> Booking ID:</strong> #<?= htmlspecialchars($booking_id) ?></p>
            </div>

            <form method="POST" class="form-section">
                <div class="form-group">
                    <label for="reason">
                        <i class="fas fa-comment-alt"></i> 
                        Reason for Cancellation 
                        <span class="required">*</span>
                    </label>
                    <textarea 
                        name="reason" 
                        id="reason" 
                        placeholder="Enter your reason for cancelling this booking..."
                        required
                    ><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                    
                    <?php if (isset($error)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="button-group">
                    <button type="submit" name="submit_reason" class="btn btn-danger">
                        <i class="fas fa-check"></i> Submit Cancellation
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
                    <i class="fas fa-question-circle error"></i>
                </div>
                <h2 class="header-title">Confirm Booking Cancellation</h2>
                <p class="header-description">
                    Are you sure you want to cancel this booking? This action cannot be undone.
                </p>
            </div>

            <div class="booking-info-box">
                <p><strong><i class="fas fa-hashtag"></i> Booking ID:</strong> #<?= htmlspecialchars($booking_id) ?></p>
                <p><strong><i class="fas fa-user"></i> Cancelled By:</strong> Mechanic</p>
                <div class="status-change">
                    <i class="fas fa-exchange-alt"></i> 
                    Status will change: <strong>Pending → Cancelled</strong>
                </div>
            </div>

            <form method="POST">
                <div class="button-group">
                    <button type="submit" name="confirm_decline" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Yes, Cancel Booking
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