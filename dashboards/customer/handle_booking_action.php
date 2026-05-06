<?php
session_start();
require '../../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['booking_id'])) {
    $customer_id = $_SESSION['user_id'];
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    try {
        // Verify booking belongs to customer
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND customer_id = ?");
        $stmt->execute([$booking_id, $customer_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $_SESSION['error'] = "Booking not found.";
            header("Location: customer_dashboard.php");
            exit();
        }
        
        if ($action === 'cancel') {
            // Cancel booking
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            $_SESSION['success'] = "Booking #$booking_id has been cancelled successfully.";
            
        } elseif ($action === 'reassign') {
            // Redirect to reassign page
            $_SESSION['reassign_booking_id'] = $booking_id;
            header("Location: reassign_mechanic.php?booking_id=$booking_id");
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

header("Location: customer_dashboard.php");
exit();