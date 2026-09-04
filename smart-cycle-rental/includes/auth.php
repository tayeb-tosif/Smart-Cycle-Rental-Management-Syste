<?php
/**
 * Authentication and Role-Based Access Control (RBAC) Guard
 * Smart Cycle Rental Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently authenticated
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user payload from session
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'user_id'   => $_SESSION['user_id'],
        'name'      => $_SESSION['user_name'] ?? 'User',
        'email'     => $_SESSION['user_email'] ?? '',
        'user_type' => $_SESSION['user_type'] ?? 'customer',
        'wallet'    => $_SESSION['wallet'] ?? 0.00
    ];
}

/**
 * Refresh user session info directly from database
 */
function refreshUserSession(PDO $pdo): void {
    if (!isLoggedIn()) return;
    $stmt = $pdo->prepare("SELECT user_id, Name, Email, User_type, wallet, revenue FROM user WHERE user_id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_id']   = $u['user_id'];
        $_SESSION['user_name'] = $u['Name'];
        $_SESSION['user_email']= $u['Email'];
        $_SESSION['user_type'] = $u['User_type'];
        $_SESSION['wallet']    = (float)$u['wallet'];
        $_SESSION['revenue']   = (float)$u['revenue'];
    }
}

/**
 * Require login. Redirect to login page if unauthenticated.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Please sign in to access this page.'
        ];
        // Calculate relative path to login.php depending on directory level
        $path = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../login.php' : 'login.php';
        header("Location: {$path}");
        exit;
    }
}

/**
 * Require specific user role(s).
 * 
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): void {
    requireLogin();
    
    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $currentRole = $_SESSION['user_type'] ?? 'customer';

    if (!in_array($currentRole, $allowed, true)) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Access denied: You do not have permission to view this section.'
        ];
        
        // Redirect to their appropriate dashboard
        if ($currentRole === 'admin') {
            header("Location: " . (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'dashboard.php' : 'admin/dashboard.php'));
        } elseif ($currentRole === 'staff') {
            header("Location: " . (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'dashboard.php' : 'admin/dashboard.php'));
        } else {
            header("Location: " . (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../dashboard.php' : 'dashboard.php'));
        }
        exit;
    }
}

/**
 * Shorthand: Only Customer
 */
function requireCustomer(): void {
    requireRole(['customer']);
}

/**
 * Shorthand: Staff or Admin
 */
function requireStaffOrAdmin(): void {
    requireRole(['staff', 'admin']);
}

/**
 * Shorthand: Admin Only
 */
function requireAdmin(): void {
    requireRole(['admin']);
}
