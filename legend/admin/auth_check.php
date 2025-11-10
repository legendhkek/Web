<?php
// Admin Panel Authentication Check
// Include this file at the top of every admin page

require_once '../config.php';
require_once '../database.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    initSecureSession();
}

// Function to check admin access
function checkAdminAccess($require_owner = false) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php?redirect=admin&error=login_required');
        exit;
    }

    // Get user details
    $db = Database::getInstance();
    $user = null;
    
    // First try to get by ID directly
    $user = $db->getUserById($_SESSION['user_id']);
    
    // If that fails, try by Telegram ID if available
    if (!$user && !empty($_SESSION['telegram_id'])) {
        $user = $db->getUserByTelegramId($_SESSION['telegram_id']);
    }
    
    // If still no user found, try using user_id as telegram_id (common in our system)
    if (!$user && !empty($_SESSION['user_id'])) {
        $user = $db->getUserByTelegramId($_SESSION['user_id']);
    }
    
    if (!$user) {
        // Log the error for debugging
        error_log("Failed to find user in auth_check. Session user_id: {$_SESSION['user_id']}, Telegram ID: " . ($_SESSION['telegram_id'] ?? 'Not set'));
        session_destroy();
        header('Location: ../login.php?error=invalid_session');
        exit;
    }
    
    // Store telegram_id in session for future use
    if (!empty($user['telegram_id'])) {
        $_SESSION['telegram_id'] = $user['telegram_id'];
    }

    // Check user status
    if (($user['status'] ?? 'active') === 'banned') {
        session_destroy();
        header('Location: ../login.php?error=account_banned');
        exit;
    }

    // Check admin/owner privileges
    $user_telegram_id = !empty($user['telegram_id']) ? (int)$user['telegram_id'] : null;
    
    if (empty($user_telegram_id) && !empty($_SESSION['user_id'])) {
        // In our system, user_id in session might be the telegram_id
        $user_telegram_id = (int)$_SESSION['user_id'];
    }
    
    if (empty($user_telegram_id)) {
        error_log("Missing telegram_id in auth_check");
        session_destroy();
        header('Location: ../login.php?error=invalid_user_data');
        exit;
    }
    
    // Get admin and owner IDs from AppConfig
    $admin_ids = defined('AppConfig::ADMIN_IDS') ? AppConfig::ADMIN_IDS : [];
    $owner_ids = defined('AppConfig::OWNER_IDS') ? AppConfig::OWNER_IDS : [];
    
    // Convert to arrays if they're not already
    if (!is_array($admin_ids)) {
        $admin_ids = [$admin_ids];
    }
    
    if (!is_array($owner_ids)) {
        $owner_ids = [$owner_ids];
    }
    
    // Debug information
    error_log("Auth Check - User Telegram ID: {$user_telegram_id}");
    error_log("Auth Check - Admin IDs: " . json_encode($admin_ids));
    error_log("Auth Check - Owner IDs: " . json_encode($owner_ids));
    
    // Check if user is already authenticated via session
    $session_is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $session_is_owner = isset($_SESSION['is_owner']) && $_SESSION['is_owner'] === true;
    
    // Direct check against IDs
    $is_admin = in_array($user_telegram_id, $admin_ids);
    $is_owner = in_array($user_telegram_id, $owner_ids);
    
    // If user has owner role in database, also consider them an owner
    if (!$is_owner && !empty($user['role']) && $user['role'] === 'owner') {
        $is_owner = true;
        error_log("User {$user_telegram_id} has owner role in database but not in OWNER_IDS constant");
    }
    
    // If user has admin role in database, also consider them an admin
    if (!$is_admin && !$is_owner && !empty($user['role']) && $user['role'] === 'admin') {
        $is_admin = true;
        error_log("User {$user_telegram_id} has admin role in database but not in ADMIN_IDS constant");
    }
    
    // More detailed debug information
    error_log("Auth Check - Detailed - User ID: {$user_telegram_id}");
    error_log("Auth Check - Detailed - Admin IDs check: " . ($is_admin ? 'true' : 'false') . " - IDs: " . json_encode($admin_ids));
    error_log("Auth Check - Detailed - Owner IDs check: " . ($is_owner ? 'true' : 'false') . " - IDs: " . json_encode($owner_ids));
    error_log("Auth Check - Detailed - Session data: " . json_encode($_SESSION));

    // Use either session or direct check for access control
    $has_admin_access = $session_is_admin || $is_admin || $is_owner;
    $has_owner_access = $session_is_owner || $is_owner;
    
    // Debug access information
    error_log("Auth Check - session_is_admin: " . ($session_is_admin ? 'true' : 'false'));
    error_log("Auth Check - session_is_owner: " . ($session_is_owner ? 'true' : 'false'));
    error_log("Auth Check - direct_is_admin: " . ($is_admin ? 'true' : 'false'));
    error_log("Auth Check - direct_is_owner: " . ($is_owner ? 'true' : 'false'));
    error_log("Auth Check - has_admin_access: " . ($has_admin_access ? 'true' : 'false'));
    error_log("Auth Check - has_owner_access: " . ($has_owner_access ? 'true' : 'false'));
    
    // Always set session variables to ensure consistency
    $_SESSION['is_admin'] = $has_admin_access;
    $_SESSION['is_owner'] = $has_owner_access;
    
    // If owner access is required, check specifically for owner
    if ($require_owner && !$has_owner_access) {
        header('Location: analytics.php?error=owner_required');
        exit;
    }

    // Check if user has any admin privileges
    if (!$has_admin_access) {
        // Log unauthorized access attempt
        $db->logAuditAction($_SESSION['user_id'], 'unauthorized_admin_access', null, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'requested_page' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        header('Location: ../dashboard.php?error=access_denied');
        exit;
    }

    // Update user role in database if needed
    $current_role = $user['role'];
    if ($is_owner && $current_role !== 'owner') {
        $db->updateUserRole($user['telegram_id'], 'owner');
        $_SESSION['user_role'] = 'owner';
        $user['role'] = 'owner';
    } elseif ($is_admin && !in_array($current_role, ['admin', 'owner'])) {
        $db->updateUserRole($user['telegram_id'], 'admin');
        $_SESSION['user_role'] = 'admin';
        $user['role'] = 'admin';
    }

    // Set session variables
    $_SESSION['is_admin'] = true;
    $_SESSION['is_owner'] = $is_owner;
    $_SESSION['user_role'] = $user['role'];

    return $user;
}

// Function to check if current user is owner
function isOwner() {
    return isset($_SESSION['is_owner']) && $_SESSION['is_owner'] === true;
}

// Function to check if current user is admin (includes owners)
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Function to get user role badge HTML
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

// Auto-check access for all admin pages (except index.php)
$current_file = basename($_SERVER['PHP_SELF']);
if ($current_file !== 'index.php') {
    // Owner-only pages
    $owner_only_pages = [
        'system_config.php',
        'payment_config.php',
        'financial_reports.php',
        'database_backup.php'
    ];
    
    $require_owner = in_array($current_file, $owner_only_pages);
    $current_user = checkAdminAccess($require_owner);
}
?>
