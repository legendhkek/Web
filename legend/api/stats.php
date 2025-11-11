<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';

header('Content-Type: application/json');

try {
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();
    
    $user = $db->getUserByTelegramId($userId);
    $userStats = $db->getUserStats($userId) ?? [
        'total_hits' => 0,
        'total_charge_cards' => 0,
        'total_live_cards' => 0
    ];
    
    $globalStats = $db->getGlobalStats() ?? [
        'total_users' => 0,
        'total_hits' => 0,
        'total_charge_cards' => 0,
        'total_live_cards' => 0
    ];
    
    echo json_encode([
        'success' => true,
        'user' => [
            'credits' => $user['credits'] ?? 0,
            'stats' => $userStats
        ],
        'global' => $globalStats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch stats'
    ]);
}
?>
