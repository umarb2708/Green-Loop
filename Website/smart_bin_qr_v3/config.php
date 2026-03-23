<?php
/**
 * Green Loop - Configuration File
 * Database connection and session management
 * Version 3.0
 */

// Start session
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'green_loop');
define('DB_USER', 'root');  // Change this to your database username
define('DB_PASS', '');      // Change this to your database password
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Green Loop');
define('APP_VERSION', '3.0');
define('TIMEZONE', 'Asia/Kolkata');  // Change to your timezone

// Security Configuration
define('SESSION_LIFETIME', 3600);  // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);  // 15 minutes in seconds

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880);  // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// QR Code Configuration
define('QR_CODE_LENGTH', 10);
define('QR_CODE_CHARSET', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

// Reward Code Configuration
define('REWARD_CODE_LENGTH', 6);

// Points Configuration
define('POINTS_PET', 10);
define('POINTS_HDPE', 20);
define('POINTS_PP', 30);
define('POINTS_OTHERS', 5);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Check if user is manufacturer
 */
function isManufacturer() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 2;
}

/**
 * Check if user is regular user
 */
function isUser() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 0;
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Require admin - redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: user_dashboard.php');
        exit();
    }
}

/**
 * Require manufacturer - redirect if not manufacturer
 */
function requireManufacturer() {
    requireLogin();
    if (!isManufacturer()) {
        header('Location: user_dashboard.php');
        exit();
    }
}

/**
 * Require regular user - redirect if not user
 */
function requireUser() {
    requireLogin();
    if (!isUser()) {
        header('Location: admin_dashboard.php');
        exit();
    }
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate random alphanumeric code
 */
function generateCode($length, $charset = QR_CODE_CHARSET) {
    $code = '';
    $charsetLength = strlen($charset);
    for ($i = 0; $i < $length; $i++) {
        $code .= $charset[random_int(0, $charsetLength - 1)];
    }
    return $code;
}

/**
 * Generate QR Code ID
 */
function generateQRCode() {
    global $conn;
    
    do {
        $qr_id = generateCode(QR_CODE_LENGTH);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM product_data WHERE qr_id = ?");
        $stmt->execute([$qr_id]);
        $count = $stmt->fetchColumn();
    } while ($count > 0);
    
    return $qr_id;
}

/**
 * Generate Reward Code
 */
function generateRewardCode() {
    global $conn;
    
    do {
        $code = generateCode(REWARD_CODE_LENGTH);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rewards_data WHERE unique_code = ?");
        $stmt->execute([$code]);
        $count = $stmt->fetchColumn();
    } while ($count > 0);
    
    return $code;
}

/**
 * Get points for plastic type
 */
function getPoints($type) {
    switch ($type) {
        case 'PET':
            return POINTS_PET;
        case 'HDPE':
            return POINTS_HDPE;
        case 'PP':
            return POINTS_PP;
        case 'Others':
            return POINTS_OTHERS;
        default:
            return 0;
    }
}

/**
 * Format date time
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) return 'N/A';
    $date = new DateTime($datetime);
    return $date->format($format);
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return 'N/A';
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Get bin by ID
 */
function getBinById($binId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM bin_data WHERE id = ?");
    $stmt->execute([$binId]);
    return $stmt->fetch();
}

/**
 * Get product by QR ID
 */
function getProductByQR($qrId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM product_data WHERE qr_id = ?");
    $stmt->execute([$qrId]);
    return $stmt->fetch();
}

/**
 * Log activity
 */
function logActivity($userId, $action, $description = null) {
    global $conn;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $description, $ipAddress]);
}

/**
 * Check bin chamber status
 * Returns true if chamber is available, false if full
 */
function isChamberAvailable($binStatus, $chamberIndex) {
    if (strlen($binStatus) != 4 || $chamberIndex < 0 || $chamberIndex > 3) {
        return false;
    }
    return $binStatus[$chamberIndex] == '0';
}

/**
 * Get chamber index from plastic type
 */
function getChamberIndex($type) {
    switch ($type) {
        case 'PET':
            return 0;
        case 'HDPE':
            return 1;
        case 'PP':
            return 2;
        case 'Others':
            return 3;
        default:
            return -1;
    }
}

/**
 * Send JSON response
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Send success response
 */
function sendSuccess($message, $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJSON($response);
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400) {
    sendJSON(['success' => false, 'error' => $message], $statusCode);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return getUserById($_SESSION['user_id']);
}

/**
 * Update session timeout
 */
function updateSessionTimeout() {
    $_SESSION['last_activity'] = time();
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_LIFETIME) {
            session_destroy();
            header('Location: index.php?timeout=1');
            exit();
        }
    }
    updateSessionTimeout();
}

// Check session timeout on each page load
if (isLoggedIn()) {
    checkSessionTimeout();
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Get flash message and clear it
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Set flash message
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

?>
