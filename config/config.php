<?php
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application settings
define('APP_NAME', 'Invoice Movement Tracker');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/invoice-tracker/');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/invoices/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Pagination
define('RECORDS_PER_PAGE', 10);

// Check if user is logged in
function isLoggedIn() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// Check user role
function hasRole($required_role) {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $required_role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format date
function formatDate($date) {
    return date('d-M-Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime) {
    return date('d-M-Y h:i A', strtotime($datetime));
}

// Calculate days difference
function getDaysDifference($from_date) {
    $now = new DateTime();
    $date = new DateTime($from_date);
    $diff = $now->diff($date);
    return $diff->days;
}

// Generate unique filename
function generateUniqueFilename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Create upload directory if not exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
?>