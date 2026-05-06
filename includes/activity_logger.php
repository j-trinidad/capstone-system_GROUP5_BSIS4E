<?php
// Activity Logger Helper Function
// Place this in /includes/activity_logger.php

function logActivity($pdo, $adminId, $adminName, $actionType, $details, $ipAddress = null) {
    if ($ipAddress === null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, admin_name, action_type, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$adminId, $adminName, $actionType, $details, $ipAddress]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}
?>