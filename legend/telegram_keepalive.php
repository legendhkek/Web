<?php
/**
 * Telegram Keepalive Endpoint
 * - Pings getMe to keep the bot/network warm
 * - Optional: sends a heartbeat message (limited) to notification chat
 * Usage examples:
 *   /telegram_keepalive.php
 *   /telegram_keepalive.php?heartbeat=1 (sends message at most once every 30 minutes)
 */
require_once 'config.php';
require_once 'utils.php';

$botToken = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU';
$apiUrl = "https://api.telegram.org/bot{$botToken}/getMe";

$resp = @file_get_contents($apiUrl);
$ok = false; $uname = 'unknown';
if ($resp !== false) {
    $j = json_decode($resp, true);
    $ok = $j['ok'] ?? false;
    $uname = $j['result']['username'] ?? 'unknown';
}

// Optional heartbeat message
$sendHeartbeat = isset($_GET['heartbeat']) && ($_GET['heartbeat'] === '1' || $_GET['heartbeat'] === 'true');
if ($sendHeartbeat && $ok) {
    $stateFile = __DIR__ . '/data/keepalive_state.json';
    $lastSent = 0;
    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true) ?: [];
        $lastSent = (int)($state['last_heartbeat'] ?? 0);
    }
    // Allow one heartbeat every 30 minutes
    if (time() - $lastSent >= 1800) {
        $chatId = SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID);
        if (!empty($chatId)) {
            @sendTelegramHtml("🟢 <b>Bot heartbeat</b> — @{$uname} — " . date('Y-m-d H:i:s'), $chatId);
            file_put_contents($stateFile, json_encode(['last_heartbeat' => time()], JSON_PRETTY_PRINT));
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => $ok,
    'bot' => $uname,
    'time' => date('c'),
]);
http_response_code(200);
