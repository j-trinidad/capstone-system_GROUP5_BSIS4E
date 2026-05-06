<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate email format
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Clean and sanitize email
function clean_email($email) {
    return trim(strtolower($email));
}

// Generate OTP
function generate_otp($length = 6) {
    $otp = '';
    for($i = 0; $i < $length; $i++) {
        $otp .= rand(0, 9);
    }
    return $otp;
}

// Sanitize user input
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Flash message helper
function flash($name, $msg = null) {
    if($msg === null) {
        if(isset($_SESSION['flash'][$name])) {
            $m = $_SESSION['flash'][$name];
            unset($_SESSION['flash'][$name]);
            return $m;
        }
        return null;
    } else {
        $_SESSION['flash'][$name] = $msg;
    }
}

// Format date/time
function format_datetime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) return '-';
    try {
        $date = new DateTime($datetime);
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

// Get user initials for avatar
function get_initials($first_name, $last_name) {
    $first = strtoupper(substr($first_name, 0, 1));
    $last = strtoupper(substr($last_name, 0, 1));
    return $first . $last;
}

// Check if user is authenticated
function is_authenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user role
function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

// Get current user ID
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user name
function get_user_name() {
    return $_SESSION['user_name'] ?? 'User';
}

// Redirect to URL
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Set success message
function set_success($message) {
    flash('success', $message);
}

// Set error message
function set_error($message) {
    flash('error', $message);
}

// Get success message
function get_success() {
    return flash('success');
}

// Get error message
function get_error() {
    return flash('error');
}

// Format currency
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

// Check if email is valid and exists in a reasonable format
function is_valid_email_format($email) {
    return preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}

// Mask email for privacy (shows first 2 chars and domain)
function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $local = $parts[0];
    $domain = $parts[1];
    
    $masked_local = substr($local, 0, 2) . str_repeat('*', strlen($local) - 2);
    return $masked_local . '@' . $domain;
}

// Convert status to badge class
function get_status_badge_class($status) {
    $status = strtolower($status);
    $classes = [
        'pending' => 'badge-warning',
        'preparing' => 'badge-info',
        'in_progress' => 'badge-primary',
        'in progress' => 'badge-primary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'declined' => 'badge-danger',
        'active' => 'badge-success',
        'disabled' => 'badge-secondary',
    ];
    
    return $classes[$status] ?? 'badge-secondary';
}

// Get status display text
function get_status_text($status) {
    $status = strtolower($status);
    $text = [
        'pending' => 'Pending',
        'preparing' => 'Preparing',
        'in_progress' => 'In Progress',
        'in progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'declined' => 'Declined',
        'active' => 'Active',
        'disabled' => 'Disabled',
    ];
    
    return $text[$status] ?? ucfirst($status);
}

// Parse and validate URL parameter
function get_param($key, $default = null) {
    return isset($_GET[$key]) ? sanitize_input($_GET[$key]) : $default;
}

// Parse and validate POST parameter
function post_param($key, $default = null) {
    return isset($_POST[$key]) ? sanitize_input($_POST[$key]) : $default;
}

// Check if request is POST
function is_post_request() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Check if request is GET
function is_get_request() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

// Log error to file
function log_error($message, $file = 'error.log') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    error_log($log_message, 3, $file);
}