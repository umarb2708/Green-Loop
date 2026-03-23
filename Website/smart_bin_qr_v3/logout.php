<?php
/**
 * Green Loop - Logout Page
 * Version 3.0
 */

require_once 'config.php';

// Log activity before logout
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>
