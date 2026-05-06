<?php
/**
 * Log Admin Activity
 * 
 * This function logs all admin activities to the activity_logs table
 * 
 * @param PDO $pdo - Database connection
 * @param int $admin_id - Admin user ID
 * @param string $admin_name - Admin user name
 * @param string $action_type - Type of action: 'user_disable', 'user_enable', 'user_delete', 'user_password_reset'
 * @param string $action_description - Description of the action
 */
function logActivity($pdo, $admin_id, $admin_name, $action_type, $action_description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, admin_name, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$admin_id, $admin_name, $action_type, $action_description]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Common Activity Logging Functions
 */

// Log adding a service
function logAddService($pdo, $admin_id, $admin_name, $serviceName, $basePrice) {
    $details = "Service Name: $serviceName | Base Price: ₱" . number_format($basePrice, 2);
    logActivity($pdo, $admin_id, $admin_name, 'add_service', "Added new service: $serviceName - " . $details);
}

// Log editing a service
function logEditService($pdo, $admin_id, $admin_name, $serviceName) {
    logActivity($pdo, $admin_id, $admin_name, 'edit_service', "Updated service: $serviceName");
}

// Log deleting a service
function logDeleteService($pdo, $admin_id, $admin_name, $serviceName) {
    logActivity($pdo, $admin_id, $admin_name, 'delete_service', "Deleted service: $serviceName");
}

// Log adding a part
function logAddPart($pdo, $admin_id, $admin_name, $partName, $price, $category) {
    $details = "Part Name: $partName | Category: $category | Price: ₱" . number_format($price, 2);
    logActivity($pdo, $admin_id, $admin_name, 'add_part', "Added new part: $partName - " . $details);
}

// Log editing a part
function logEditPart($pdo, $admin_id, $admin_name, $partName) {
    logActivity($pdo, $admin_id, $admin_name, 'edit_part', "Updated part: $partName");
}

// Log deleting a part
function logDeletePart($pdo, $admin_id, $admin_name, $partName) {
    logActivity($pdo, $admin_id, $admin_name, 'delete_part', "Deleted part: $partName");
}

// Log adding a mechanic
function logAddMechanic($pdo, $admin_id, $admin_name, $mechanicName, $email) {
    $details = "Mechanic Name: $mechanicName | Email: $email";
    logActivity($pdo, $admin_id, $admin_name, 'add_mechanic', "Created mechanic account: $mechanicName - " . $details);
}

// Log disabling a user
function logDisableUser($pdo, $admin_id, $admin_name, $userName, $role) {
    logActivity($pdo, $admin_id, $admin_name, 'user_disable', "Disabled user: $userName ($role)");
}

// Log enabling a user
function logEnableUser($pdo, $admin_id, $admin_name, $userName, $role) {
    logActivity($pdo, $admin_id, $admin_name, 'user_enable', "Enabled user: $userName ($role)");
}

// Log deleting a user
function logDeleteUser($pdo, $admin_id, $admin_name, $userName, $role) {
    logActivity($pdo, $admin_id, $admin_name, 'user_delete', "Deleted user: $userName ($role)");
}

// Log resetting user password
function logResetPassword($pdo, $admin_id, $admin_name, $userName) {
    logActivity($pdo, $admin_id, $admin_name, 'user_password_reset', "Reset password for user: $userName");
}

// Log adding a customer
function logAddCustomer($pdo, $admin_id, $admin_name, $customerName, $email) {
    $details = "Customer Name: $customerName | Email: $email";
    logActivity($pdo, $admin_id, $admin_name, 'add_customer', "Created customer account: $customerName - " . $details);
}

// Log user login
function logUserLogin($pdo, $user_id, $user_name, $role) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, admin_name, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_name, 'user_login', "User login: $user_name ($role)"]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

// Log user logout
function logUserLogout($pdo, $user_id, $user_name, $role) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, admin_name, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_name, 'user_logout', "User logout: $user_name ($role)"]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}
?>