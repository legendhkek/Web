<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';

header('Content-Type: application/json');

try {
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();
    
    // Get recent activities (mock data for now - can be enhanced with actual activity tracking)
    $user = $db->getUserByTelegramId($userId);
    $userStats = $db->getUserStats($userId);
    
    $activities = [];
    
    // Add recent check activity
    if (isset($userStats['total_hits']) && $userStats['total_hits'] > 0) {
        $activities[] = [
            'type' => 'check',
            'title' => 'Card checked successfully',
            'timestamp' => time() - 3600, // 1 hour ago
            'value' => null
        ];
    }
    
    // Add credit claim activity (if user has credits)
    if (isset($user['credits']) && $user['credits'] > 0) {
        $activities[] = [
            'type' => 'claim',
            'title' => 'Daily credits claimed',
            'timestamp' => time() - 7200, // 2 hours ago
            'value' => '+10'
        ];
    }
    
    // Add login activity
    $activities[] = [
        'type' => 'login',
        'title' => 'Logged in successfully',
        'timestamp' => isset($user['last_login_at']) ? $user['last_login_at'] : time(),
        'value' => null
    ];
    
    // Add achievement if user has many hits
    if (isset($userStats['total_hits']) && $userStats['total_hits'] >= 100) {
        $activities[] = [
            'type' => 'achievement',
            'title' => 'Century Checker achievement unlocked!',
            'timestamp' => time() - 86400, // 1 day ago
            'value' => '🏆'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'activities' => array_slice($activities, 0, 5) // Return max 5 activities
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch activities'
    ]);
}
