<?php
session_start();
require_once '../config.php';
require_once '../database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['telegram_id'])) {
    echo json_encode(['error' => true, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['telegram_id'];
$db = Database::getInstance();

$user = $db->getUserByTelegramId($userId);

if (!$user) {
    echo json_encode(['error' => true, 'message' => 'User not found']);
    exit;
}

echo json_encode([
    'credits' => intval($user['credits'] ?? 0),
    'xcoin_balance' => intval($user['xcoin_balance'] ?? 0),
    'role' => $user['role'] ?? 'free'
]);
?>