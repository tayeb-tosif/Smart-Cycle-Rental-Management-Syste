<?php
/**
 * User Logout Page
 * Feature 1: User Management
 * Smart Cycle Rental Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session just for the flash message
session_start();
$_SESSION['flash'] = [
    'type'    => 'info',
    'message' => 'You have been successfully signed out.'
];

header("Location: login.php");
exit;
