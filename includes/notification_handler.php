<?php
// includes/notification_handler.php

// Unified notification handler to prevent duplicates
function sendBookingNotification($pdo, $customer_id, $booking_id, $type, $mechanic_name) {
    // Check if notification already exists (within last minute to avoid rapid duplicates)
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM customer_notifications 
        WHERE customer_id = ? 
        AND booking_id = ? 
        AND type = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $checkStmt->execute([$customer_id, $booking_id, $type]);
    
    if ($checkStmt->fetchColumn() > 0) {
        // Notification already exists, skip
        return;
    }
    
    // Define messages based on type
    $messages = [
        'booking_accepted' => [
            'title' => '✅ Booking Accepted',
            'message' => "Your mechanic {$mechanic_name} has accepted your booking and is preparing for the service."
        ],
        'booking_declined' => [
            'title' => '❌ Booking Declined',
            'message' => "Your mechanic {$mechanic_name} has declined your booking. You can book with another mechanic or reschedule."
        ],
        'service_started' => [
            'title' => '⚙️ Service Started',
            'message' => "{$mechanic_name} has started working on your motorcycle service."
        ],
        'service_completed' => [
            'title' => '✅ Service Completed',
            'message' => "Great news! {$mechanic_name} has completed your motorcycle service. Please rate your experience."
        ]
    ];
    
    if (!isset($messages[$type])) {
        // Unknown type, skip
        return;
    }
    
    // Insert notification
    $notifStmt = $pdo->prepare("
        INSERT INTO customer_notifications 
        (customer_id, booking_id, type, title, message, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    
    $notifStmt->execute([
        $customer_id,
        $booking_id,
        $type,
        $messages[$type]['title'],
        $messages[$type]['message']
    ]);
}
?>