<?php
session_start();
require '../../includes/db_connect.php';

// Ensure logged in as mechanic
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mechanic') {
    header("Location: ../../login.php");
    exit();
}

$mechanic_id = $_SESSION['user_id'];

// Fetch mechanic info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'mechanic'");
$stmt->execute([$mechanic_id]);
$mechanic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mechanic) {
    die("Mechanic not found. Please log in again.");
}

// Messages count (unread)
$messagesStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$messagesStmt->execute([$mechanic_id]);
$messages = $messagesStmt->fetchColumn();


$error = null;
$current_step = 1; // Step 1: Form, Step 2: Preview, Step 3: Confirmation
$preview_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['next_step'])) {
        // Going to preview step
        $absence_reason = trim($_POST['absence_reason']);
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : $start_date;
        
        // Validate dates
        if (empty($start_date)) {
            $error = "Start date is required.";
        } else {
            // If end date is before start date, set end date = start date
            if (strtotime($end_date) < strtotime($start_date)) {
                $end_date = $start_date;
            }
            
            // Check for overlapping existing leave
            $overlapStmt = $pdo->prepare("
                SELECT id, start_date, end_date FROM mechanic_absences
                WHERE mechanic_id = ?
                AND start_date <= ? AND end_date >= ?
                LIMIT 1
            ");
            $overlapStmt->execute([$mechanic_id, $end_date, $start_date]);
            $existing = $overlapStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $existFrom = date('M d, Y', strtotime($existing['start_date']));
                $existTo   = date('M d, Y', strtotime($existing['end_date']));
                $error = "You already have a filed leave that overlaps with these dates ({$existFrom} – {$existTo}). You cannot file another leave on the same days.";
            } else {
            // Fetch affected bookings for preview
            $previewStmt = $pdo->prepare("
                SELECT b.id, b.customer_id, b.service_type, b.schedule, b.status,
                       b.labor_fee, b.service_fee, b.parts_total, b.service_location,
                       u.first_name, u.last_name, u.email,
                       s.name AS service_name
                FROM bookings b
                JOIN users u ON b.customer_id = u.id
                LEFT JOIN services s ON b.service_type = s.service_key
                WHERE b.mechanic_id = ? 
                AND DATE(b.schedule) BETWEEN ? AND ?
                AND b.status IN ('pending', 'on_hold', 'preparing', 'in_progress')
                ORDER BY b.schedule ASC
            ");
            $previewStmt->execute([$mechanic_id, $start_date, $end_date]);
            $previewBookings = $previewStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Store in session for next step
            $_SESSION['leave_data'] = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'reason' => $absence_reason,
                'bookings' => $previewBookings
            ];
            
            $current_step = 2;
            } // end no-overlap check
        }
        
    } elseif (isset($_POST['go_back'])) {
        // User clicked Back — clear session and return to step 1
        unset($_SESSION['leave_data']);
        header("Location: report_absence.php");
        exit();

    } elseif (isset($_POST['confirm_file'])) {
        // Filing the leave
        if (!isset($_SESSION['leave_data'])) {
            $error = "Session expired. Please start again.";
        } else {
            $leave_data = $_SESSION['leave_data'];
            $start_date = $leave_data['start_date'];
            $end_date = $leave_data['end_date'];
            $absence_reason = $leave_data['reason'];
            $affectedBookings = $leave_data['bookings'];
            
            try {
                $pdo->beginTransaction();
            
                // 1. Insert absence record
                $stmt = $pdo->prepare("
                    INSERT INTO mechanic_absences (mechanic_id, reason, start_date, end_date, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$mechanic_id, $absence_reason, $start_date, $end_date]);
                $absence_id = $pdo->lastInsertId();
                
                // 2. Update affected bookings status to awaiting_customer_action
                if (!empty($affectedBookings)) {
                    $updateStmt = $pdo->prepare("
                        UPDATE bookings 
                        SET status = 'awaiting_customer_action',
                            absence_id = ?,
                            updated_at = NOW()
                        WHERE mechanic_id = ? 
                        AND DATE(schedule) BETWEEN ? AND ?
                        AND status IN ('pending', 'on_hold', 'preparing', 'in_progress')
                    ");
                    $updateStmt->execute([$absence_id, $mechanic_id, $start_date, $end_date]);
                    
                    // 3. Send messages to affected customers
                    $messageStmt = $pdo->prepare("
                        INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
                        VALUES (?, ?, ?, NOW(), 0)
                    ");
                    
                    foreach ($affectedBookings as $booking) {
                        $scheduleDate = date('M d, Y h:i A', strtotime($booking['schedule']));
                        $statusText = ucfirst(str_replace('_', ' ', $booking['status']));
                        
                        $message = "⚠️ URGENT ACTION REQUIRED!\n\n" .
                                  "Your assigned mechanic is unavailable on {$scheduleDate}.\n\n" .
                                  "Booking Details:\n" .
                                  "• Booking ID: #{$booking['id']}\n" .
                                  "• Current Status: {$statusText}\n" .
                                  "• Service: " . htmlspecialchars($booking['service_type']) . "\n" .
                                  "• Reason for Unavailability: {$absence_reason}\n\n" .
                                  "WHAT YOU NEED TO DO:\n" .
                                  "1. Log in to your dashboard\n" .
                                  "2. Go to your active bookings\n" .
                                  "3. Choose ONE of these options:\n" .
                                  "   ✓ Reassign to another available mechanic\n" .
                                  "   ✗ Cancel your booking\n\n" .
                                  "We sincerely apologize for the inconvenience and appreciate your understanding!";
                        
                        $messageStmt->execute([$mechanic_id, $booking['customer_id'], $message]);
                    }
                    
                    $affectedCount = count($affectedBookings);
                }
                
                $pdo->commit();
                
                // Clear session and show success
                unset($_SESSION['leave_data']);
                $_SESSION['success'] = "Leave filed successfully. " . (isset($affectedCount) ? "{$affectedCount} customer(s) have been notified." : "No bookings affected.");
                header("Location: report_absence.php");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to file leave: " . $e->getMessage();
                $current_step = 2;
            }
        }
        
    } elseif (isset($_POST['go_back'])) {
        // Back to step 1
        $current_step = 1;
    }
}

// If we have saved leave data, go to step 2
if (isset($_SESSION['leave_data']) && $current_step === 1) {
    $current_step = 2;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>File Leave - MotorService</title>
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
    --warning: #d97706;
    --info: #2563eb;
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
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    color: var(--text-primary);
    overflow: hidden;
}

a {
    color: inherit;
    text-decoration: none;
}

/* HAMBURGER MENU BUTTON */
.hamburger-btn {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.4);
    transition: all 0.3s ease;
}

.hamburger-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.6);
}

.hamburger-btn span {
    display: block;
    width: 25px;
    height: 3px;
    background: #ffffff;
    margin: 5px auto;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.hamburger-btn.active span:nth-child(1) {
    transform: rotate(45deg) translate(8px, 8px);
}

.hamburger-btn.active span:nth-child(2) {
    opacity: 0;
}

.hamburger-btn.active span:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
}

/* SIDEBAR OVERLAY */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(26, 86, 219, 0.1);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* FIXED SIDEBAR */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(180deg, #1e3a8a 0%, #1a56db 100%);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    padding: 25px 20px;
    z-index: 1000;
    transition: transform 0.3s ease;
    -webkit-overflow-scrolling: touch;
}

.sidebar::-webkit-scrollbar { width: 6px; }
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.logo {
    font-size: 1.6rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 35px;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
    position: relative;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    transform: translateX(5px);
}

.sidebar nav a i {
    width: 20px;
    text-align: center;
}

.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #ff0000;
    color: #ffffff;
    width: 18px;
    height: 18px;
    min-width: 18px;
    font-size: 10px;
    font-weight: 700;
    border-radius: 50%;
    margin-left: auto;
    line-height: 1;
    box-shadow: 0 0 8px rgba(255, 0, 0, 0.7);
}

.sidebar .footer {
    margin-top: auto;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.5);
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.logout-btn {
    background: linear-gradient(135deg, #ff4444, #c82333) !important;
    color: #fff !important;
    margin-top: auto !important;
    justify-content: center !important;
}

.logout-btn:hover {
    background: linear-gradient(135deg, #ff6666, #d44444) !important;
}

/* MAIN CONTENT */
.main {
    margin-left: 260px;
    padding: 40px;
    width: calc(100% - 260px);
    height: 100vh;
    overflow-y: auto;
    background: linear-gradient(135deg, #f0f4ff, #e8eeff);
    display: flex;
    flex-direction: column;
    -webkit-overflow-scrolling: touch;
}

.main::-webkit-scrollbar { width: 8px; }
.main::-webkit-scrollbar-track { background: rgba(26, 86, 219, 0.04); }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 5px; }
.main::-webkit-scrollbar-thumb:hover { background: var(--primary); }

.header {
    font-size: 28px;
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

/* STEP INDICATOR */
.step-indicator {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    align-items: center;
}

.step {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: var(--text-secondary);
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(26, 86, 219, 0.08);
    border: 2px solid var(--border);
    font-weight: 700;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.step.active .step-number {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: var(--primary);
    color: #ffffff;
    transform: scale(1.1);
}

.step.active {
    color: var(--primary);
    font-weight: 600;
}

.step-line {
    flex: 1;
    height: 2px;
    background: var(--border);
    max-width: 100px;
}

.step-line.active {
    background: var(--primary);
}

/* MAIN CONTENT WRAPPER */
.content-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
}

/* FORM CONTAINER */
.form-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 15px 40px rgba(26, 86, 219, 0.10), 0 0 30px rgba(26, 86, 219, 0.06);
}

.form-group {
    margin-bottom: 25px;
    display: flex;
    flex-direction: column;
}

.form-group label {
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 10px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group input,
.form-group textarea {
    padding: 12px 15px;
    background: #f8faff;
    border: 2px solid var(--border);
    color: var(--text-primary);
    border-radius: 10px;
    font-family: 'Outfit', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: #eef2ff;
    box-shadow: 0 0 10px rgba(26, 86, 219, 0.15);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #94a3b8;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

/* FLASH MESSAGES */
.flash-message {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
    font-size: 14px;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.flash-message.success {
    background: rgba(5, 150, 105, 0.1);
    border: 1px solid var(--success);
    border-left: 4px solid var(--success);
    color: var(--success);
}

.flash-message.error {
    background: rgba(220, 38, 38, 0.08);
    border: 1px solid var(--error);
    border-left: 4px solid var(--error);
    color: var(--error);
}

.flash-message i {
    font-size: 16px;
    flex-shrink: 0;
}

/* PREVIEW SECTION */
.preview-summary {
    background: rgba(26, 86, 219, 0.05);
    border-left: 4px solid var(--primary);
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.preview-summary p {
    margin: 8px 0;
    color: var(--text-secondary);
}

.preview-summary strong {
    color: var(--primary);
}

.bookings-preview {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
    padding-right: 8px;
    margin-bottom: 20px;
}

.bookings-preview::-webkit-scrollbar { width: 6px; }
.bookings-preview::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.preview-booking {
    background: rgba(26, 86, 219, 0.04);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    font-size: 13px;
}

.preview-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.preview-booking-id {
    font-weight: 700;
    color: var(--primary);
}

.preview-booking-status {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(217, 119, 6, 0.12);
    color: var(--warning);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.preview-booking-detail {
    color: var(--text-secondary);
    margin: 4px 0;
}

.preview-booking-detail strong {
    color: var(--text-primary);
}

.no-bookings {
    text-align: center;
    padding: 30px 20px;
    color: var(--text-secondary);
    background: rgba(5, 150, 105, 0.05);
    border-radius: 10px;
    border: 1px solid rgba(5, 150, 105, 0.2);
    margin-bottom: 20px;
}

.no-bookings i {
    font-size: 32px;
    opacity: 0.6;
    margin-bottom: 10px;
    display: block;
    color: var(--success);
}

/* BUTTONS */
.btn-group {
    display: flex;
    gap: 12px;
    margin-top: 10px;
}

.btn-action {
    flex: 1;
    padding: 14px 32px;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 14px;
    font-family: 'Outfit', sans-serif;
}

.btn-next {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: #ffffff;
    width: 100%;
    box-shadow: 0 4px 15px rgba(26, 86, 219, 0.3);
}

.btn-next:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 86, 219, 0.45);
}

.btn-next:active {
    transform: scale(0.98);
}

.btn-confirm {
    background: linear-gradient(135deg, var(--success), #10b981);
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
}

.btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
}

.btn-cancel {
    background: rgba(220, 38, 38, 0.08);
    color: var(--error);
    border: 2px solid rgba(220, 38, 38, 0.3);
}

.btn-cancel:hover {
    background: rgba(220, 38, 38, 0.14);
    border-color: var(--error);
    transform: translateY(-2px);
}

/* INFO BOX */
.info-box {
    background: rgba(26, 86, 219, 0.05);
    border: 1px solid var(--border);
    border-left: 4px solid var(--primary);
    border-radius: 10px;
    padding: 16px;
    margin-top: 25px;
    color: var(--text-secondary);
    font-size: 13px;
    line-height: 1.6;
}

.info-box strong {
    color: var(--primary);
    display: block;
    margin-bottom: 8px;
}

.info-box ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.info-box li {
    margin-bottom: 6px;
}

@media (max-width: 1024px) {
    .sidebar { width: 220px; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 30px 20px; }
    .header { font-size: 24px; }
    .form-container { padding: 30px; }
    .step-indicator { gap: 15px; margin-bottom: 25px; }
}

@media (max-width: 768px) {
    .hamburger-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 80%;
        max-width: 300px;
        height: 100vh;
        transform: translateX(-100%);
    }

    .sidebar.active { transform: translateX(0); }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 90px 20px 20px;
    }

    .header { font-size: 20px; }

    .step-indicator {
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .step { font-size: 12px; }

    .step-number {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }

    .step-line { display: none; }

    .content-wrapper {
        padding: 20px 0;
        align-items: flex-start;
    }

    .form-container {
        max-width: 100%;
        padding: 25px;
        margin-top: 20px;
    }

    .form-group { margin-bottom: 20px; }

    .form-group label {
        font-size: 13px;
        margin-bottom: 8px;
    }

    .form-group input,
    .form-group textarea {
        padding: 12px 14px;
        font-size: 13px;
    }

    .form-group textarea { min-height: 80px; }

    .btn-group {
        flex-direction: column;
        gap: 10px;
    }

    .btn-action {
        padding: 14px 20px;
        font-size: 12px;
    }

    .bookings-preview { max-height: 300px; }

    .preview-booking {
        padding: 12px;
        font-size: 12px;
    }

    .info-box {
        font-size: 12px;
        padding: 12px;
        margin-top: 20px;
    }
}

@media (max-width: 480px) {
    .main { padding: 80px 15px 20px; }

    .form-container { padding: 20px; }

    .form-group { margin-bottom: 18px; }

    .header {
        font-size: 18px;
        margin-bottom: 20px;
    }

    .preview-booking {
        padding: 10px;
        font-size: 11px;
    }

    .btn-group { gap: 8px; }

    .btn-action {
        padding: 12px 16px;
        font-size: 11px;
        gap: 6px;
    }
}
</style>
</head>
<body>

<!-- HAMBURGER BUTTON -->
<button class="hamburger-btn" id="hamburger-btn">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-wrench"></i>
        <span>MotorService</span>
    </div>

    <nav>
        <a href="mechanic_dashboard.php" class="<?= $currentPage == 'mechanic_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="service_request.php" class="<?= $currentPage == 'service_request.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Service Requests
        </a>
        <a href="mechanic_history.php" class="<?= $currentPage == 'mechanic_history.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> History
        </a>
        <a href="message_center.php" class="<?= $currentPage == 'message_center.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($messages > 0): ?>
                <span class="notification-badge"><?= $messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="<?= $currentPage == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="change_password.php" class="<?= $currentPage == 'change_password.php' ? 'active' : '' ?>">
            <i class="fas fa-lock"></i> Change Password
        </a>
        <a href="report_absence.php" class="<?= $currentPage == 'report_absence.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-times"></i> File Leave
        </a>
        <a href="../../logout.php" class="logout-btn" style="margin-top: auto;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="footer">v1.0 • <?= date('Y') ?></div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="header">
        <i class="fas fa-calendar-times"></i> File Leave Request
    </div>

    <!-- STEP INDICATOR -->
    <div class="step-indicator">
        <div class="step <?= $current_step >= 1 ? 'active' : '' ?>">
            <div class="step-number">1</div>
            <span>Fill Details</span>
        </div>
        <div class="step-line <?= $current_step >= 2 ? 'active' : '' ?>"></div>
        <div class="step <?= $current_step >= 2 ? 'active' : '' ?>">
            <div class="step-number">2</div>
            <span>Review & Confirm</span>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="form-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="flash-message success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash-message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- STEP 1: FORM -->
            <?php if ($current_step === 1): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="start_date">
                            <i class="fas fa-calendar"></i> Start Date
                        </label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">
                            <i class="fas fa-calendar-check"></i> End Date (Optional)
                        </label>
                        <input type="date" id="end_date" name="end_date">
                    </div>

                    <div class="form-group">
                        <label for="absence_reason">
                            <i class="fas fa-comment"></i> Reason for Leave
                        </label>
                        <textarea id="absence_reason" name="absence_reason" placeholder="e.g., Personal emergency, Medical appointment, family matters, etc." required></textarea>
                    </div>

                    <button type="submit" name="next_step" class="btn-action btn-next">
                        <i class="fas fa-arrow-right"></i> Next
                    </button>

                    <div class="info-box">
                        <strong>📋 What Happens Next:</strong>
                        <ul>
                            <li>Click "Next" to see which bookings will be affected</li>
                            <li>Review the affected bookings carefully</li>
                            <li>Confirm to file your leave</li>
                            <li>Customers will be notified automatically</li>
                        </ul>
                    </div>
                </form>

            <!-- STEP 2: PREVIEW & CONFIRM -->
            <?php elseif ($current_step === 2 && isset($_SESSION['leave_data'])): ?>
                <?php 
                $leave_data = $_SESSION['leave_data'];
                $start_date = $leave_data['start_date'];
                $end_date = $leave_data['end_date'];
                $reason = $leave_data['reason'];
                $bookings = $leave_data['bookings'];
                ?>
                <form method="POST" action="">
                    <div class="preview-summary">
                        <p><strong>📅 Leave Period:</strong> <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?></p>
                        <p><strong>📝 Reason:</strong> <?= htmlspecialchars($reason) ?></p>
                        <p><strong>⚠️ Affected Bookings:</strong> <?= count($bookings) ?> booking(s)</p>
                    </div>

                    <?php if (empty($bookings)): ?>
                        <div class="no-bookings">
                            <i class="fas fa-check-circle"></i>
                            <p>Great! No bookings will be affected during this period.</p>
                            <p style="font-size: 12px; margin-top: 10px;">You can proceed with filing your leave.</p>
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom: 20px;">
                            <div class="bookings-preview">
                                <?php foreach ($bookings as $booking): ?>
                                    <?php 
                                    $scheduleDate = date('M d, Y h:i A', strtotime($booking['schedule']));
                                    $homeFee = ($booking['service_location'] === 'home') ? 150 : 0;
                                    $total = $booking['labor_fee'] + $homeFee + $booking['service_fee'] + $booking['parts_total'];
                                    ?>
                                    <div class="preview-booking">
                                        <div class="preview-booking-header">
                                            <span class="preview-booking-id">#<?= $booking['id'] ?> - <?= htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) ?></span>
                                            <span class="preview-booking-status"><?= ucfirst(str_replace('_', ' ', $booking['status'])) ?></span>
                                        </div>
                                        <div class="preview-booking-detail">
                                            <i class="fas fa-wrench"></i> <strong><?= htmlspecialchars($booking['service_name'] ?: $booking['service_type']) ?></strong>
                                        </div>
                                        <div class="preview-booking-detail">
                                            <i class="fas fa-calendar"></i> <?= $scheduleDate ?>
                                        </div>
                                        <div class="preview-booking-detail">
                                            <i class="fas fa-peso-sign"></i> ₱<?= number_format($total, 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 20px; padding: 14px; background: rgba(26, 86, 219, 0.05); border-radius: 10px; border-left: 4px solid var(--primary); font-size: 13px; color: var(--text-secondary);">
                        <i class="fas fa-bell" style="color: var(--primary); margin-right: 8px;"></i>
                        <strong style="color: var(--primary);">Important:</strong> Customers will be notified and must reassign or cancel their bookings.
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="go_back" class="btn-action btn-cancel">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" name="confirm_file" class="btn-action btn-confirm">
                            <i class="fas fa-check-circle"></i> Confirm & File
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const hamburgerBtn   = document.getElementById('hamburger-btn');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

hamburgerBtn.addEventListener('click', () => {
    hamburgerBtn.classList.toggle('active');
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
});

sidebarOverlay.addEventListener('click', () => {
    hamburgerBtn.classList.remove('active');
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
});

sidebar.querySelectorAll('nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            hamburgerBtn.classList.remove('active');
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        }
    });
});

// Set minimum date to today
const today = new Date().toISOString().split('T')[0];
const startDateInput = document.getElementById('start_date');
const endDateInput = document.getElementById('end_date');

if (startDateInput) startDateInput.min = today;
if (endDateInput) endDateInput.min = today;

if (startDateInput) {
    startDateInput.addEventListener('change', function() {
        if (endDateInput) endDateInput.min = this.value;
    });
}
</script>

</body>
</html>