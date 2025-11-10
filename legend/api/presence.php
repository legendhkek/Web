<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';

header('Content-Type: application/json');
$nonce = setSecurityHeaders();

try {
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->updatePresence($userId);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Authentication required']);
}
?>
