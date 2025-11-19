<?php
/**
 * Admin Panel Entry Point
 * This page handles admin authentication and redirects to the appropriate section
 */

require_once '../config.php';
require_once '../database.php';
require_once '../admin_manager.php';
require_once 'admin_auth.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    header('Location: ../login.php?redirect=admin');
    exit;
}

// Get user's Telegram ID
$telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];

// Get user data
$db = Database::getInstance();
$user = $db->getUserByTelegramId($telegram_id);

if (!$user) {
    session_destroy();
    header('Location: ../login.php?error=invalid_session');
    exit;
}

// Use AdminManager for consistent admin checking
$adminManager = new AdminManager();
$is_owner = $adminManager->isOwner($telegram_id);
$is_admin = $adminManager->isAdmin($telegram_id);

// Also check database role for backward compatibility
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

// Check if user has admin privileges
if (!$is_admin && !$is_owner) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Redirect to admin dashboard
header('Location: analytics.php');
exit;
?>
