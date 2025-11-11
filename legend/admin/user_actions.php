<?php
require_once 'admin_header.php';
require_once '../owner_logger.php';

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: user_management.php?error=invalid_request');
    exit;
}

$user_id = $_GET['id'];
$action = $_GET['action'];

// Verify CSRF token if present
if (isset($_GET['token'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['token'])) {
        header('Location: user_management.php?error=invalid_token');
        exit;
    }
}

// Get user first to verify they exist
$target_user = $db->getUserByTelegramId($user_id);
if (!$target_user) {
    header('Location: user_management.php?error=user_not_found');
    exit;
}

try {
    switch ($action) {
        case 'ban':
            $db->updateUserStatus($user_id, 'banned');
            $db->logAuditAction($_SESSION['telegram_id'], 'user_banned', $user_id, ['reason' => 'Admin action']);
            
            // Owner notification
            try {
                $ownerLogger = new OwnerLogger();
                $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                $ownerLogger->sendAdminAlert(
                    $admin_user,
                    'User Banned',
                    "🚫 Admin banned user:\n\n" .
                    "👤 User: {$target_user['display_name']}\n" .
                    "🆔 ID: {$user_id}\n" .
                    "👮 Admin: {$admin_user['display_name']}"
                );
            } catch (Exception $e) {
                error_log("Owner logging failed: " . $e->getMessage());
            }
            
            header('Location: user_management.php?success=user_banned');
            break;
            
        case 'unban':
            $db->updateUserStatus($user_id, 'active');
            $db->logAuditAction($_SESSION['telegram_id'], 'user_unbanned', $user_id, ['reason' => 'Admin action']);
            
            // Owner notification
            try {
                $ownerLogger = new OwnerLogger();
                $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                $ownerLogger->sendAdminAlert(
                    $admin_user,
                    'User Unbanned',
                    "✅ Admin unbanned user:\n\n" .
                    "👤 User: {$target_user['display_name']}\n" .
                    "🆔 ID: {$user_id}\n" .
                    "👮 Admin: {$admin_user['display_name']}"
                );
            } catch (Exception $e) {
                error_log("Owner logging failed: " . $e->getMessage());
            }
            
            header('Location: user_management.php?success=user_unbanned');
            break;
            
        case 'delete':
            // Only owners can delete users
            if (!isOwner()) {
                header('Location: user_management.php?error=owner_required');
                exit;
            }
            
            // Delete user (if method exists)
            if (method_exists($db, 'deleteUser')) {
                $db->deleteUser($user_id);
                $db->logAuditAction($_SESSION['telegram_id'], 'user_deleted', $user_id, ['name' => $target_user['display_name']]);
                
                header('Location: user_management.php?success=user_deleted');
            } else {
                header('Location: user_management.php?error=feature_not_available');
            }
            break;
            
        case 'reset_password':
            // Reset user's password/session
            if (method_exists($db, 'resetUserSession')) {
                $db->resetUserSession($user_id);
                $db->logAuditAction($_SESSION['telegram_id'], 'password_reset', $user_id, []);
                
                header('Location: user_management.php?success=password_reset');
            } else {
                header('Location: user_management.php?error=feature_not_available');
            }
            break;
            
        default:
            header('Location: user_management.php?error=invalid_action');
            break;
    }
} catch (Exception $e) {
    error_log("User action failed: " . $e->getMessage());
    header('Location: user_management.php?error=action_failed');
}

exit;
?>
