<?php
require_once 'config.php';
require_once 'utils.php';

$response = performTelegramApiRequest('getWebhookInfo', [], [
    'method' => 'GET',
    'timeout' => 10
]);

if (!($response['ok'] ?? false)) {
    $message = $response['description'] ?? 'Unable to fetch webhook info';
    echo "Error: {$message}" . PHP_EOL;
    exit(1);
}

$data = $response['result'] ?? [];

echo "=== WEBHOOK STATUS ===" . PHP_EOL;
echo "URL: " . ($data['url'] ?: 'NOT SET') . PHP_EOL;
echo "Pending: " . ($data['pending_update_count'] ?? 0) . " messages" . PHP_EOL;
echo "Status: " . (empty($data['url']) ? '❌ INACTIVE' : '✅ ACTIVE') . PHP_EOL;
echo "Last Error: " . ($data['last_error_message'] ?? 'None') . PHP_EOL;
echo "======================" . PHP_EOL;

if (!empty($data['url'])) {
    echo PHP_EOL . "✅ WEBHOOK IS WORKING!" . PHP_EOL;
    echo "Bot is ready to receive messages." . PHP_EOL;
    echo "Send /start to @WebkeBot in Telegram!" . PHP_EOL;
} else {
    echo PHP_EOL . "❌ WEBHOOK NOT SET" . PHP_EOL;
    echo "Run: php setup_webhook.php" . PHP_EOL;
}
