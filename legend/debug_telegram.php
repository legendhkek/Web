<?php
require_once 'config.php';

echo "Testing Telegram API directly...\n";

$bot_token = TelegramConfig::BOT_TOKEN;
$chat_id = 5652614329;
$message = "🧪 <b>TEST MESSAGE</b>\n\nThis is a test message to verify the owner notification system is working.\n\n📅 <b>Time:</b> " . date('Y-m-d H:i:s');

echo "Bot Token: " . substr($bot_token, 0, 20) . "...\n";
echo "Chat ID: $chat_id\n";
echo "Message: $message\n\n";

$url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";

$data = [
    'chat_id' => $chat_id,
    'text' => $message,
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true
];

echo "API URL: $url\n";
echo "POST Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Try with cURL first
if (function_exists('curl_init')) {
    echo "Using cURL...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "cURL Error: " . ($error ?: 'None') . "\n";
    echo "Response: $response\n\n";
    
    curl_close($ch);
} else {
    echo "cURL not available, using file_get_contents...\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    echo "Response: " . ($response ?: 'Failed') . "\n";
}

// Also test if bot token is valid by getting bot info
echo "\n" . str_repeat("-", 50) . "\n";
echo "Testing bot info...\n";

$botInfoUrl = "https://api.telegram.org/bot" . $bot_token . "/getMe";
$botInfo = @file_get_contents($botInfoUrl);
echo "Bot Info Response: $botInfo\n";

if ($botInfo) {
    $botData = json_decode($botInfo, true);
    if ($botData && $botData['ok']) {
        echo "✓ Bot is valid: " . $botData['result']['username'] . "\n";
    } else {
        echo "❌ Bot token is invalid\n";
    }
}
?>