<?php
require_once 'config.php';

$bot_token = TelegramConfig::BOT_TOKEN;
$ch = curl_init("https://api.telegram.org/bot{$bot_token}/getWebhookInfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "=== WEBHOOK STATUS ===" . PHP_EOL;
echo "URL: " . ($data['result']['url'] ?: 'NOT SET') . PHP_EOL;
echo "Pending: " . ($data['result']['pending_update_count'] ?? 0) . " messages" . PHP_EOL;
echo "Status: " . (empty($data['result']['url']) ? '❌ INACTIVE' : '✅ ACTIVE') . PHP_EOL;
echo "Last Error: " . ($data['result']['last_error_message'] ?? 'None') . PHP_EOL;
echo "======================" . PHP_EOL;

if (!empty($data['result']['url'])) {
    echo PHP_EOL . "✅ WEBHOOK IS WORKING!" . PHP_EOL;
    echo "Bot is ready to receive messages." . PHP_EOL;
    echo "Send /start to @WebkeBot in Telegram!" . PHP_EOL;
} else {
    echo PHP_EOL . "❌ WEBHOOK NOT SET" . PHP_EOL;
    echo "Run: php setup_webhook.php" . PHP_EOL;
}
