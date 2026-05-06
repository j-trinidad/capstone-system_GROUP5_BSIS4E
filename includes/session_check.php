<?php
// includes/session_check.php
// FIXED: Both customers and mechanics can be disabled (but NOT admin)
if(session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db_connect.php';

function checkLogin() {
    if(!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function checkDisabled() {
    // ✅ Check if user is disabled (BOTH customers and mechanics)
    // ONLY ADMIN is exempt from disable check
    if(isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        $role = $_SESSION['user_role'];
        
        // Admin is exempt from disable check
        if($role === 'admin') {
            return; // Skip for admin
        }
        
        // Check for both customers and mechanics
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT is_disabled FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$user) {
                // User account was deleted
                session_destroy();
                header('Location: /login.php?error=account_deleted');
                exit;
            }
            
            // If customer OR mechanic is disabled, logout automatically
            if($user['is_disabled'] == 1) {
                session_destroy();
                header('Location: /login.php?error=account_disabled');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Session check error: " . $e->getMessage());
        }
    }
}

function checkRole($required) {
    checkLogin();
    checkDisabled(); // Check if disabled (for customers AND mechanics, NOT admin)
    
    $role = $_SESSION['user_role'] ?? null;
    if($role !== $required) {
        // Redirect to their dashboard if wrong role
        switch($role) {
            case 'admin': header('Location: /dashboards/admin/admin_dashboard.php'); break;
            case 'mechanic': header('Location: /dashboards/mechanic/mechanic_dashboard.php'); break;
            default: header('Location: /dashboards/customer/customer_dashboard.php'); break;
        }
        exit;
    }
}

/**
 * Get current user info
 */
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'role' => $_SESSION['user_role'] ?? null
    ];
}

/**
 * Logout function
 */
function logoutUser() {
    session_destroy();
    header('Location: /login.php?logout=success');
    exit;
}
?>