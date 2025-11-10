<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';

header('Content-Type: application/json');

try {
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();
    
    // Get all user data
    $user = $db->getUserByTelegramId($userId);
    $userStats = $db->getUserStats($userId);
    
    // Prepare export data
    $exportData = [
        'user_info' => [
            'telegram_id' => $user['telegram_id'] ?? '',
            'display_name' => $user['display_name'] ?? $user['first_name'] ?? 'User',
            'role' => $user['role'] ?? 'free',
            'created_at' => date('Y-m-d H:i:s', $user['created_at'] ?? time()),
            'last_login_at' => date('Y-m-d H:i:s', $user['last_login_at'] ?? time())
        ],
        'balances' => [
            'credits' => $user['credits'] ?? 0,
            'xcoin_balance' => $user['xcoin_balance'] ?? 0
        ],
        'statistics' => [
            'total_hits' => $userStats['total_hits'] ?? 0,
            'total_charge_cards' => $userStats['total_charge_cards'] ?? 0,
            'total_live_cards' => $userStats['total_live_cards'] ?? 0,
            'expiry_date' => isset($userStats['expiry_date']) ? 
                date('Y-m-d', is_numeric($userStats['expiry_date']) ? $userStats['expiry_date'] : strtotime($userStats['expiry_date'])) : 
                'N/A'
        ],
        'export_date' => date('Y-m-d H:i:s'),
        'export_version' => '1.0'
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $exportData
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to export data'
    ]);
}
