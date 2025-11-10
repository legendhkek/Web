<?php
/**
 * Database Test AJAX Handler
 * This file handles AJAX requests for database testing
 */

require_once '../config.php';
require_once '../database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get action
$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance();
    
    if (!$db) {
        throw new Exception('Database instance is null');
    }
    
    switch ($action) {
        case 'test_get_user':
            $telegram_id = $_GET['telegram_id'] ?? '';
            if (empty($telegram_id)) {
                throw new Exception('Telegram ID is required');
            }
            
            $user = $db->getUserByTelegramId($telegram_id);
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found'
                ]);
            }
            break;
            
        case 'test_get_all_users':
            $users = $db->getAllUsers(10, 0);
            if (is_array($users)) {
                echo json_encode([
                    'success' => true,
                    'count' => count($users),
                    'users' => array_slice($users, 0, 3) // Show first 3 users
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to get users'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
