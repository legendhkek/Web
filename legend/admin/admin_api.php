<?php
/**
 * Admin API - Handles all admin panel actions via AJAX
 */

require_once '../config.php';
require_once '../database.php';
require_once 'admin_auth.php';

header('Content-Type: application/json');

// Check admin access
try {
    $current_user = checkAdminAccess();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$db = Database::getInstance();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // User management actions
        case 'ban_user':
            $user_id = $_POST['user_id'] ?? '';
            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }
            
            $db->updateUserStatus($user_id, 'banned');
            $db->logAuditAction($_SESSION['telegram_id'], 'user_banned', $user_id, ['reason' => $_POST['reason'] ?? 'Admin action']);
            
            echo json_encode(['success' => true, 'message' => 'User banned successfully']);
            break;
            
        case 'unban_user':
            $user_id = $_POST['user_id'] ?? '';
            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }
            
            $db->updateUserStatus($user_id, 'active');
            $db->logAuditAction($_SESSION['telegram_id'], 'user_unbanned', $user_id, ['reason' => $_POST['reason'] ?? 'Admin action']);
            
            echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
            break;
            
        case 'update_user_role':
            if (!isOwner()) {
                throw new Exception('Owner access required');
            }
            
            $user_id = $_POST['user_id'] ?? '';
            $new_role = $_POST['role'] ?? '';
            
            if (empty($user_id) || empty($new_role)) {
                throw new Exception('User ID and role are required');
            }
            
            $valid_roles = ['free', 'premium', 'vip', 'admin', 'owner'];
            if (!in_array($new_role, $valid_roles)) {
                throw new Exception('Invalid role');
            }
            
            $db->updateUserRole($user_id, $new_role);
            $db->logAuditAction($_SESSION['telegram_id'], 'role_changed', $user_id, ['old_role' => $_POST['old_role'] ?? 'unknown', 'new_role' => $new_role]);
            
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            break;
            
        case 'add_credits':
            $user_id = $_POST['user_id'] ?? '';
            $amount = (int)($_POST['amount'] ?? 0);
            
            if (empty($user_id) || $amount <= 0) {
                throw new Exception('Invalid user ID or amount');
            }
            
            $db->addCredits($user_id, $amount);
            $db->logAuditAction($_SESSION['telegram_id'], 'credits_added', $user_id, ['amount' => $amount]);
            
            echo json_encode(['success' => true, 'message' => "Added $amount credits successfully"]);
            break;
            
        case 'remove_credits':
            $user_id = $_POST['user_id'] ?? '';
            $amount = (int)($_POST['amount'] ?? 0);
            
            if (empty($user_id) || $amount <= 0) {
                throw new Exception('Invalid user ID or amount');
            }
            
            $user = $db->getUserByTelegramId($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $new_credits = max(0, $user['credits'] - $amount);
            $db->updateUserCredits($user_id, $new_credits);
            $db->logAuditAction($_SESSION['telegram_id'], 'credits_removed', $user_id, ['amount' => $amount, 'new_balance' => $new_credits]);
            
            echo json_encode(['success' => true, 'message' => "Removed $amount credits successfully"]);
            break;
            
        case 'set_credits':
            $user_id = $_POST['user_id'] ?? '';
            $amount = (int)($_POST['amount'] ?? 0);
            
            if (empty($user_id) || $amount < 0) {
                throw new Exception('Invalid user ID or amount');
            }
            
            $db->updateUserCredits($user_id, $amount);
            $db->logAuditAction($_SESSION['telegram_id'], 'credits_set', $user_id, ['new_amount' => $amount]);
            
            echo json_encode(['success' => true, 'message' => "Set credits to $amount successfully"]);
            break;
            
        case 'bulk_action':
            $user_ids = $_POST['user_ids'] ?? [];
            $bulk_action = $_POST['bulk_action'] ?? '';
            
            if (empty($user_ids) || !is_array($user_ids)) {
                throw new Exception('No users selected');
            }
            
            if (empty($bulk_action)) {
                throw new Exception('No action specified');
            }
            
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($user_ids as $user_id) {
                try {
                    switch ($bulk_action) {
                        case 'ban':
                            $db->updateUserStatus($user_id, 'banned');
                            $success_count++;
                            break;
                        case 'unban':
                            $db->updateUserStatus($user_id, 'active');
                            $success_count++;
                            break;
                        case 'role_free':
                        case 'role_premium':
                        case 'role_vip':
                            $role = str_replace('role_', '', $bulk_action);
                            $db->updateUserRole($user_id, $role);
                            $success_count++;
                            break;
                        case 'add_credits':
                        case 'remove_credits':
                            $amount = (int)($_POST['credit_amount'] ?? 100);
                            if ($bulk_action === 'add_credits') {
                                $db->addCredits($user_id, $amount);
                            } else {
                                $user = $db->getUserByTelegramId($user_id);
                                $new_credits = max(0, $user['credits'] - $amount);
                                $db->updateUserCredits($user_id, $new_credits);
                            }
                            $success_count++;
                            break;
                        default:
                            $failed_count++;
                    }
                } catch (Exception $e) {
                    $failed_count++;
                }
            }
            
            $db->logAuditAction($_SESSION['telegram_id'], 'bulk_action', null, [
                'action' => $bulk_action,
                'user_count' => count($user_ids),
                'success' => $success_count,
                'failed' => $failed_count
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Bulk action completed. Success: $success_count, Failed: $failed_count"
            ]);
            break;
            
        case 'get_user_info':
            $user_id = $_GET['user_id'] ?? '';
            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }
            
            $user = $db->getUserByTelegramId($user_id);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $stats = $db->getUserStats($user_id);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'stats' => $stats
            ]);
            break;
            
        case 'search_users':
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            $limit = (int)($_GET['limit'] ?? 50);
            
            $all_users = $db->getAllUsers(1000, 0);
            
            $filtered = array_filter($all_users, function($user) use ($search, $role, $status) {
                if ($search && stripos($user['display_name'] . ' ' . $user['username'], $search) === false) {
                    return false;
                }
                if ($role && $user['role'] !== $role) {
                    return false;
                }
                if ($status && ($user['status'] ?? 'active') !== $status) {
                    return false;
                }
                return true;
            });
            
            $filtered = array_slice(array_values($filtered), 0, $limit);
            
            echo json_encode([
                'success' => true,
                'users' => $filtered,
                'total' => count($filtered)
            ]);
            break;
            
        case 'get_stats':
            $stats = [
                'total_users' => $db->getTotalUsersCount(),
                'total_credits_claimed' => $db->getTotalCreditsClaimed(),
                'total_tool_uses' => $db->getTotalToolUses(),
                'online_users' => count($db->getOnlineUsers()),
                'admin_count' => count(AppConfig::ADMIN_IDS),
                'owner_count' => count(AppConfig::OWNER_IDS),
                'banned_users' => count(array_filter($db->getAllUsers(1000, 0), fn($u) => ($u['status'] ?? 'active') === 'banned'))
            ];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'broadcast_message':
            if (!isOwner() && !isAdmin()) {
                throw new Exception('Admin or Owner access required');
            }
            
            $message = $_POST['message'] ?? '';
            $target = $_POST['target'] ?? 'all';
            
            if (empty($message)) {
                throw new Exception('Message is required');
            }
            
            // Get target users
            $users = [];
            if ($target === 'all') {
                $users = $db->getAllUsers(10000, 0);
            } elseif ($target === 'online') {
                $users = $db->getOnlineUsers();
            } elseif (in_array($target, ['free', 'premium', 'vip'])) {
                $all_users = $db->getAllUsers(10000, 0);
                $users = array_filter($all_users, fn($u) => $u['role'] === $target);
            }
            
            $sent_count = 0;
            $failed_count = 0;
            
            // Send via Telegram (if available)
            if (defined('TelegramConfig::BOT_TOKEN')) {
                foreach ($users as $user) {
                    try {
                        $telegram_id = $user['telegram_id'];
                        $url = "https://api.telegram.org/bot" . TelegramConfig::BOT_TOKEN . "/sendMessage";
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                            'chat_id' => $telegram_id,
                            'text' => $message,
                            'parse_mode' => 'HTML'
                        ]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code === 200) {
                            $sent_count++;
                        } else {
                            $failed_count++;
                        }
                    } catch (Exception $e) {
                        $failed_count++;
                    }
                }
            }
            
            $db->logAuditAction($_SESSION['telegram_id'], 'broadcast_sent', null, [
                'target' => $target,
                'sent' => $sent_count,
                'failed' => $failed_count,
                'message_length' => strlen($message)
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Broadcast sent. Success: $sent_count, Failed: $failed_count"
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
