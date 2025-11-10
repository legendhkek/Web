<?php
/**
 * Bot Testing & Debug Script
 * Tests bot token, webhook status, and sends a test message
 */

require_once 'config.php';

$bot_token = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU';
$bot_api_url = "https://api.telegram.org/bot{$bot_token}/";

echo "<h2>Bot Testing & Debug</h2>";
echo "<hr>";

// Test 1: Bot Token Validation
echo "<h3>1. Testing Bot Token (getMe)</h3>";
$getMeUrl = $bot_api_url . 'getMe';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $getMeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo "<p style='color: red;'>✗ cURL Error: {$curl_error}</p>";
    exit;
}

$result = json_decode($response, true);

if ($result['ok']) {
    echo "<p style='color: green;'>✓ Bot is valid!</p>";
    echo "<pre>" . json_encode($result['result'], JSON_PRETTY_PRINT) . "</pre>";
    $bot_username = $result['result']['username'];
} else {
    echo "<p style='color: red;'>✗ Bot token is invalid!</p>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    exit;
}

// Test 2: Webhook Info
echo "<h3>2. Current Webhook Status</h3>";
$webhookInfoUrl = $bot_api_url . 'getWebhookInfo';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['ok']) {
    echo "<pre>" . json_encode($result['result'], JSON_PRETTY_PRINT) . "</pre>";
    
    $webhook_url = $result['result']['url'] ?? '';
    $pending_count = $result['result']['pending_update_count'] ?? 0;
    $last_error = $result['result']['last_error_message'] ?? 'None';
    
    if (empty($webhook_url)) {
        echo "<p style='color: orange;'>⚠ No webhook is set! Bot won't receive updates.</p>";
    } else {
        echo "<p style='color: green;'>✓ Webhook is set to: {$webhook_url}</p>";
    }
    
    if ($pending_count > 0) {
        echo "<p style='color: orange;'>⚠ Pending updates: {$pending_count}</p>";
    }
    
    if ($last_error !== 'None') {
        echo "<p style='color: red;'>✗ Last error: {$last_error}</p>";
    }
}

// Test 3: Set Webhook
echo "<h3>3. Set Webhook</h3>";
$domain = AppConfig::DOMAIN;
$webhook_url = $domain . '/telegram_webhook_enhanced.php';

echo "<p>Attempting to set webhook to: <code>{$webhook_url}</code></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $bot_api_url . 'setWebhook');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $webhook_url]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['ok']) {
    echo "<p style='color: green;'>✓ Webhook set successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to set webhook: " . $result['description'] . "</p>";
}

// Test 4: Send Test Message
echo "<h3>4. Send Test Message</h3>";
$owner_id = 5652614329; // @LEGEND_BL

$test_message = "🤖 <b>Bot Test Message</b>\n\n";
$test_message .= "✓ Bot Token: Valid\n";
$test_message .= "✓ Bot Username: @{$bot_username}\n";
$test_message .= "✓ Webhook: Set to {$webhook_url}\n";
$test_message .= "✓ Time: " . date('Y-m-d H:i:s') . "\n\n";
$test_message .= "Bot is operational! Try sending /start command.";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $bot_api_url . 'sendMessage');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'chat_id' => $owner_id,
    'text' => $test_message,
    'parse_mode' => 'HTML'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['ok']) {
    echo "<p style='color: green;'>✓ Test message sent to owner (ID: {$owner_id})</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to send message: " . ($result['description'] ?? 'Unknown error') . "</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Open Telegram and start a chat with @{$bot_username}</li>";
echo "<li>Send /start command to the bot</li>";
echo "<li>Bot should respond with welcome message</li>";
echo "<li>Try other commands like /credits, /help</li>";
echo "</ol>";

echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>
