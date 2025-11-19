<?php
/**
 * Admin Panel Authentication System
 * This file provides secure authentication for all admin pages
 */

require_once '../config.php';
require_once '../database.php';

// Initialize secure session
initSecureSession();

// Initialize database instance
$db = Database::getInstance();

/**
 * Check if user has admin access
 * @param bool $require_owner Whether owner access is required
 * @return array User data if access granted
 */
function checkAdminAccess($require_owner = false) {
    global $db;
    
    // Check if user is logged in
    if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
        // No session data - redirect to admin login page
        header('Location: login_redirect.php?error=session_expired');
        exit;
    }
    
    // Get user's Telegram ID
    $telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
    
    // Get user data from database
    $user = $db->getUserByTelegramId($telegram_id);
    
    if (!$user) {
        // Log the error for debugging
        error_log("Admin access failed: User not found. Telegram ID: {$telegram_id}");
        session_destroy();
        header('Location: login_redirect.php?error=invalid_session');
        exit;
    }
    
    // Check user status
    if (($user['status'] ?? 'active') === 'banned') {
        session_destroy();
        header('Location: login_redirect.php?error=account_banned');
        exit;
    }
    
    // Get admin and owner IDs from config
    $admin_ids = AppConfig::ADMIN_IDS;
    $owner_ids = AppConfig::OWNER_IDS;
    
    // Check if user is in admin or owner arrays
    $is_admin = in_array($telegram_id, $admin_ids);
    $is_owner = in_array($telegram_id, $owner_ids);
    
    // Also check database role
    if (!$is_owner && !empty($user['role']) && $user['role'] === 'owner') {
        $is_owner = true;
    }
    if (!$is_admin && !$is_owner && !empty($user['role']) && $user['role'] === 'admin') {
        $is_admin = true;
    }
    
    // Set session variables
    $_SESSION['telegram_id'] = $telegram_id;
    $_SESSION['is_admin'] = $is_admin || $is_owner;
    $_SESSION['is_owner'] = $is_owner;
    $_SESSION['user_role'] = $is_owner ? 'owner' : ($is_admin ? 'admin' : 'user');
    
    // Update user role in database if needed
    if ($is_owner && ($user['role'] ?? '') !== 'owner') {
        $db->updateUserRole($telegram_id, 'owner');
        $user['role'] = 'owner';
    } elseif ($is_admin && !in_array($user['role'] ?? '', ['admin', 'owner'])) {
        $db->updateUserRole($telegram_id, 'admin');
        $user['role'] = 'admin';
    }
    
    // Check if owner access is required
    if ($require_owner && !$is_owner) {
        header('Location: analytics.php?error=owner_required');
        exit;
    }
    
    // Check if user has any admin privileges
    if (!$is_admin && !$is_owner) {
        // Log unauthorized access attempt
        if (method_exists($db, 'logAuditAction')) {
            $db->logAuditAction($telegram_id, 'unauthorized_admin_access', null, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'requested_page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        }
        
        header('Location: login_redirect.php?error=access_denied');
        exit;
    }
    
    return $user;
}

/**
 * Check if current user is owner
 * @return bool
 */
function isOwner() {
    return isset($_SESSION['is_owner']) && $_SESSION['is_owner'] === true;
}

/**
 * Require owner access or redirect
 */
function requireOwner() {
    $user = checkAdminAccess(true);
    if (!isOwner()) {
        header('Location: analytics.php?error=owner_required');
        exit;
    }
    return $user;
}

/**
 * Check if current user is admin (includes owners)
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Get user role badge HTML
 * @param string $role
 * @return string
 */
function getRoleBadge($role) {
    $role_colors = [
        'free' => 'secondary',
        'premium' => 'warning',
        'vip' => 'success',
        'admin' => 'info',
        'owner' => 'danger'
    ];
    $color = $role_colors[$role] ?? 'secondary';
    return "<span class='badge bg-{$color}'>" . ucfirst($role) . "</span>";
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (empty($_SESSION['telegram_id']) && empty($_SESSION['user_id'])) {
        return null;
    }
    
    $telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
    $db = Database::getInstance();
    return $db->getUserByTelegramId($telegram_id);
}

/**
 * Check if user can access specific admin page
 * @param string $page_name
 * @return bool
 */
function canAccessPage($page_name) {
    $owner_only_pages = [
        'system_config.php',
        'payment_config.php',
        'financial_reports.php',
        'database_backup.php',
        'role_management.php'
    ];
    
    if (in_array($page_name, $owner_only_pages)) {
        return isOwner();
    }
    
    return isAdmin();
}

// Auto-check access for all admin pages
$current_file = basename($_SERVER['PHP_SELF']);
if ($current_file !== 'index.php' && $current_file !== 'admin_access.php' && 
    $current_file !== 'debug_access.php' && $current_file !== 'fix_session.php' && 
    $current_file !== 'fix_admin_access.php' && $current_file !== 'test_access.php' &&
    $current_file !== 'access_test.php' && $current_file !== 'login_redirect.php' &&
    $current_file !== 'restore_session.php' && $current_file !== 'db_test.php') {
    
    // Check if page requires owner access
    $require_owner = canAccessPage($current_file);
    
    // Authenticate user
    $current_user = checkAdminAccess($require_owner);
    
    // Make user data available globally
    $GLOBALS['current_user'] = $current_user;
}
?>
