<?php
/**
 * Telegram Bot Setup Script with cURL fallback support
 * Uses configuration from config.php instead of hard-coded credentials.
 */

require_once 'config.php';
require_once 'utils.php';

$bot_token = TelegramConfig::BOT_TOKEN ?? '';
$defaultWebhook = AppConfig::DOMAIN ? rtrim(AppConfig::DOMAIN, '/') . '/telegram_webhook_enhanced.php' : '';
$webhook_url = SiteConfig::get('custom_webhook_url', $defaultWebhook);

// Set webhook
function setWebhook($webhook_url) {
    return performTelegramApiRequest('setWebhook', ['url' => $webhook_url], [
        'method' => 'POST',
        'timeout' => 15
    ]);
}

// Get webhook info
function getWebhookInfo() {
    return performTelegramApiRequest('getWebhookInfo', [], [
        'method' => 'GET',
        'timeout' => 10
    ]);
}

// Set bot commands
function setBotCommands() {
    $commands = [
        ['command' => 'start', 'description' => 'Start the bot'],
        ['command' => 'credits', 'description' => 'Check your credit balance'],
        ['command' => 'claim', 'description' => 'Claim credit codes'],
        ['command' => 'check', 'description' => 'Check a card or site'],
        ['command' => 'help', 'description' => 'Show help information'],
        ['command' => 'admin', 'description' => 'Admin panel (admins only)']
    ];
    
    return performTelegramApiRequest('setMyCommands', [
        'commands' => json_encode($commands)
    ], [
        'method' => 'POST',
        'timeout' => 15
    ]);
}

echo "<h1>Telegram Bot Setup</h1>";

if (empty($bot_token)) {
    echo "<p style='color: red;'><strong>Warning:</strong> Telegram bot token is not configured. Update <code>config.php</code> before running setup.</p>";
}

if (isset($_GET['setup'])) {
    echo "<h2>Setting up webhook...</h2>";
    
    // Set webhook
    $result = setWebhook($webhook_url);
    echo "<p><strong>Webhook Result:</strong></p>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    // Set bot commands
    $commands_result = setBotCommands();
    echo "<p><strong>Commands Result:</strong></p>";
    echo "<pre>" . json_encode($commands_result, JSON_PRETTY_PRINT) . "</pre>";
}

// Get current webhook info
$webhook_info = getWebhookInfo();
echo "<h2>Current Webhook Status</h2>";
echo "<pre>" . json_encode($webhook_info, JSON_PRETTY_PRINT) . "</pre>";

echo "<h2>Setup Instructions</h2>";
echo "<ol>";
echo "<li>Replace 'https://your-domain.com' with your actual domain in this file</li>";
echo "<li>Upload telegram_webhook.php to your server</li>";
echo "<li>Click the setup button below</li>";
echo "<li>Test the bot by sending /start command</li>";
echo "</ol>";

echo "<a href='?setup=1' style='background: #0088cc; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Setup Bot</a>";

echo "<h2>Bot Features</h2>";
echo "<ul>";
echo "<li>🎯 Check card validity</li>";
echo "<li>🌐 Check site validity</li>";
echo "<li>💰 Credit management</li>";
echo "<li>🎁 Code claiming</li>";
echo "<li>👑 Admin panel</li>";
echo "<li>📢 Broadcasting</li>";
echo "<li>👥 User management</li>";
echo "</ul>";

echo "<h2>Admin Commands (for authorized users)</h2>";
echo "<ul>";
echo "<li>/admin - Show admin panel</li>";
echo "<li>/generate COUNT AMOUNT - Generate credit codes</li>";
echo "<li>/broadcast MESSAGE - Send announcement</li>";
echo "<li>/users - List recent users</li>";
echo "<li>/addcredits USER_ID AMOUNT - Gift credits</li>";
echo "<li>/stats - System statistics</li>";
echo "<li>/ban USER_ID - Ban user</li>";
echo "<li>/unban USER_ID - Unban user</li>";
echo "</ul>";
?>