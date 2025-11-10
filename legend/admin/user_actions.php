<?php
require_once 'admin_header.php';
require_once '../owner_logger.php';

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: user_management.php');
    exit;
}

$user_id = $_GET['id'];
$action = $_GET['action'];

switch ($action) {
    case 'ban':
        $db->updateUserStatus($user_id, 'banned');
        $db->logAuditAction($_SESSION['user_id'], 'user_banned', $user_id, ['reason' => 'Admin action']);
        
        // Owner notification
        try {
            $ownerLogger = new OwnerLogger();
            $admin_user = $db->getUserByTelegramId($_SESSION['user_id']);
            $target_user = $db->getUserByTelegramId($user_id);
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
        $db->updateUserStatus($user_id, 'active');
        $db->logAuditAction($_SESSION['user_id'], 'user_unbanned', $user_id, ['reason' => 'Admin action']);
        
        // Owner notification
        try {
            $ownerLogger = new OwnerLogger();
            $admin_user = $db->getUserByTelegramId($_SESSION['user_id']);
            $target_user = $db->getUserByTelegramId($user_id);
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

