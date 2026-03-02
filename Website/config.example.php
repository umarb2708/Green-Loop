<?php
/**
 * Database Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file and rename to: config.php
 * 2. Update the database credentials below
 * 3. Delete this comment block
 * 
 * DO NOT commit config.php to version control!
 */

// Database Configuration
define('DB_HOST', 'localhost');        // Database host (usually 'localhost')
define('DB_USER', 'root');             // Your MySQL username
define('DB_PASS', '');                 // Your MySQL password
define('DB_NAME', 'greenloop');        // Database name

// Site Configuration (optional)
define('SITE_URL', 'http://localhost/greenloop');  // Your website URL

// Debug Mode (set to false in production!)
define('DEBUG_MODE', true);

// Timezone
date_default_timezone_set('UTC');

// ==================== DO NOT EDIT BELOW THIS LINE ====================

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        if (DEBUG_MODE) {
            die("Connection failed: " . $conn->connect_error);
        } else {
            die("Database connection error. Please contact administrator.");
        }
    }
    
    return $conn;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: user_dashboard.php");
        exit();
    }
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Error handler
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
