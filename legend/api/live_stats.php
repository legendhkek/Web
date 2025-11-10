<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';

header('Content-Type: application/json');

try {
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();
    
    // Get fresh user data
    $user = $db->getUserByTelegramId($userId);
    
    // Get online count
    $onlineUsers = $db->getOnlineUsers(100);
    $onlineCount = count($onlineUsers);
    
    // Get user stats
    $userStats = $db->getUserStats($userId);
    
    echo json_encode([
        'success' => true,
        'credits' => $user['credits'] ?? 0,
        'xcoin_balance' => $user['xcoin_balance'] ?? 0,
        'online_count' => $onlineCount,
        'total_hits' => $userStats['total_hits'] ?? 0,
        'total_charge_cards' => $userStats['total_charge_cards'] ?? 0,
        'total_live_cards' => $userStats['total_live_cards'] ?? 0,
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch stats'
    ]);
}
