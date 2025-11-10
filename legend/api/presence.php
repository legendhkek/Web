<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';
require_once '../utils.php';

header('Content-Type: application/json');
$nonce = setSecurityHeaders();

try {
    // Rate limiting
    if (!TelegramAuth::checkRateLimit('presence_update', 30, 60)) {
        http_response_code(429);
        echo safeJsonEncode(['error' => 'Too many requests']);
        exit;
    }
    
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = safeJsonDecode(file_get_contents('php://input'), true);
        $timestamp = isset($input['timestamp']) ? (int)$input['timestamp'] : time();
        
        // Update presence
        $db->updatePresence($userId);
        
        echo safeJsonEncode([
            'success' => true,
            'timestamp' => $timestamp,
            'server_time' => time()
        ]);
    } else {
        http_response_code(405);
        echo safeJsonEncode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    logError('Presence API error: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'user_id' => $userId ?? 'unknown'
    ]);
    
    http_response_code(401);
    echo safeJsonEncode(['error' => 'Authentication required']);
}
?>
