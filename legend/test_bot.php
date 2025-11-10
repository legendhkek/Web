<?php
/**
 * Bot Testing & Debug Script
 * Tests bot token, webhook status, and sends a test message
 */

require_once 'config.php';
require_once 'utils.php';

$bot_token = TelegramConfig::BOT_TOKEN ?? '';
if (empty($bot_token)) {
    echo "<p style='color: red;'>✗ Bot token is not configured. Update <code>config.php</code> to continue.</p>";
    exit;
}

echo "<h2>Bot Testing & Debug</h2>";
echo "<hr>";

// Test 1: Bot Token Validation
echo "<h3>1. Testing Bot Token (getMe)</h3>";
$result = performTelegramApiRequest('getMe', [], [
    'method' => 'GET',
    'timeout' => 10
]);

if ($result['ok'] ?? false) {
    echo "<p style='color: green;'>✓ Bot is valid!</p>";
    echo "<pre>" . json_encode($result['result'], JSON_PRETTY_PRINT) . "</pre>";
    $bot_username = $result['result']['username'] ?? 'unknown';
} else {
    $description = $result['description'] ?? 'Bot token is invalid';
    echo "<p style='color: red;'>✗ {$description}</p>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    exit;
}

// Test 2: Webhook Info
echo "<h3>2. Current Webhook Status</h3>";
$result = performTelegramApiRequest('getWebhookInfo', [], [
    'method' => 'GET',
    'timeout' => 10
]);

if ($result['ok'] ?? false) {
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
} else {
    $description = $result['description'] ?? 'Unable to fetch webhook info';
    echo "<p style='color: red;'>✗ {$description}</p>";
}

// Test 3: Set Webhook
echo "<h3>3. Set Webhook</h3>";
$domain = AppConfig::DOMAIN;
$webhook_url = $domain . '/telegram_webhook_enhanced.php';

echo "<p>Attempting to set webhook to: <code>{$webhook_url}</code></p>";

$result = performTelegramApiRequest('setWebhook', ['url' => $webhook_url], [
    'method' => 'POST',
    'timeout' => 10
]);

if ($result['ok'] ?? false) {
    echo "<p style='color: green;'>✓ Webhook set successfully!</p>";
} else {
    $description = $result['description'] ?? 'Failed to set webhook';
    echo "<p style='color: red;'>✗ {$description}</p>";
}

// Test 4: Send Test Message
echo "<h3>4. Send Test Message</h3>";
$ownerIds = AppConfig::OWNER_IDS ?? [];
$owner_id = $ownerIds[0] ?? (TelegramConfig::CHAT_ID ?? null);

$test_message = "🤖 <b>Bot Test Message</b>\n\n";
$test_message .= "✓ Bot Token: Valid\n";
$test_message .= "✓ Bot Username: @{$bot_username}\n";
$test_message .= "✓ Webhook: Set to {$webhook_url}\n";
$test_message .= "✓ Time: " . date('Y-m-d H:i:s') . "\n\n";
$test_message .= "Bot is operational! Try sending /start command.";

if ($owner_id === null) {
    echo "<p style='color: orange;'>⚠ No owner or notification chat configured. Skipping test message.</p>";
} else {
    $result = performTelegramApiRequest('sendMessage', [
        'chat_id' => $owner_id,
        'text' => $test_message,
        'parse_mode' => 'HTML'
    ], [
        'method' => 'POST',
        'timeout' => 10
    ]);
    
    if ($result['ok'] ?? false) {
        echo "<p style='color: green;'>✓ Test message sent to chat ID: {$owner_id}</p>";
    } else {
        $description = $result['description'] ?? 'Unknown error';
        echo "<p style='color: red;'>✗ Failed to send message: {$description}</p>";
    }
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
