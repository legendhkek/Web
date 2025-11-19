<?php
require_once 'admin_header.php';
require_once '../owner_logger.php';

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: user_management.php');
    exit;
}

$user_id = $_GET['id'];
$action = $_GET['action'];

// Get user to ensure we have telegram_id
$user = $db->getUserById($user_id);
if (!$user && is_numeric($user_id)) {
    $user = $db->getUserByTelegramId((int)$user_id);
}
$user_telegram_id = $user['telegram_id'] ?? $user_id;

switch ($action) {
    case 'ban':
        $db->updateUserStatus($user_telegram_id, 'banned');
        $admin_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'] ?? 'system';
        $db->logAuditAction($admin_id, 'user_banned', $user_telegram_id, ['reason' => 'Admin action']);
        
        // Owner notification
        try {
            $ownerLogger = new OwnerLogger();
            $admin_user = $db->getUserByTelegramId($admin_id);
            $target_user = $user ?: $db->getUserByTelegramId($user_telegram_id);
            $ownerLogger->sendAdminAlert(
                $admin_user,
                'User Banned',
                "Admin banned user: {$target_user['display_name']} (ID: {$user_id})"
            );
        } catch (Exception $e) {
            error_log("Owner logging failed: " . $e->getMessage());
        }
        break;
    case 'unban':
        $db->updateUserStatus($user_telegram_id, 'active');
        $admin_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'] ?? 'system';
        $db->logAuditAction($admin_id, 'user_unbanned', $user_telegram_id, ['reason' => 'Admin action']);
        
        // Owner notification
        try {
            $ownerLogger = new OwnerLogger();
            $admin_user = $db->getUserByTelegramId($admin_id);
            $target_user = $user ?: $db->getUserByTelegramId($user_telegram_id);
            $ownerLogger->sendAdminAlert(
                $admin_user,
                'User Unbanned',
                "Admin unbanned user: {$target_user['display_name']} (ID: {$user_id})"
            );
        } catch (Exception $e) {
            error_log("Owner logging failed: " . $e->getMessage());
        }
        break;
    // Future actions like 'force_logout' can be added here
}

// Redirect back to the user management page
header('Location: user_management.php');
exit;

