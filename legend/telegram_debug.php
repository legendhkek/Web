<?php
require_once 'config.php';
require_once 'utils.php';

// Debug Telegram bot configuration
echo "<h2>Telegram Bot Debug Information</h2>";
echo "<p><strong>Bot Name:</strong> " . htmlspecialchars(TelegramConfig::BOT_NAME) . "</p>";
echo "<p><strong>Current Domain:</strong> https://" . htmlspecialchars($_SERVER['HTTP_HOST']) . "</p>";
echo "<p><strong>Auth URL:</strong> https://" . htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "</p>";

$botToken = TelegramConfig::BOT_TOKEN ?? '';
if (empty($botToken)) {
    echo "<p style='color: red;'><strong>❌ Bot token is not configured.</strong></p>";
} else {
    // Test bot API
    $response = performTelegramApiRequest('getMe', [], [
        'method' => 'GET',
        'timeout' => 10
    ]);

    echo "<h3>Bot API Test:</h3>";
    if ($response['ok'] ?? false) {
        echo "<p style='color: green;'><strong>✅ Bot is working correctly!</strong></p>";
        echo "<p><strong>Bot Username:</strong> @" . htmlspecialchars($response['result']['username'] ?? 'unknown') . "</p>";
        echo "<p><strong>Bot ID:</strong> " . htmlspecialchars($response['result']['id'] ?? 'unknown') . "</p>";
    } else {
        $description = $response['description'] ?? 'Unknown error';
        echo "<p style='color: red;'><strong>❌ Bot API error:</strong> " . htmlspecialchars($description) . "</p>";
        if (isset($response['raw_response'])) {
            echo "<pre>" . htmlspecialchars($response['raw_response']) . "</pre>";
        }
    }
}

// Check domain setup
echo "<h3>Domain Setup Instructions:</h3>";
echo "<ol>";
echo "<li>Go to <a href='https://t.me/BotFather' target='_blank'>@BotFather</a> on Telegram</li>";
echo "<li>Send <code>/setdomain</code></li>";
echo "<li>Select your bot: <strong>" . htmlspecialchars(TelegramConfig::BOT_NAME) . "</strong></li>";
echo "<li>Set domain to: <strong>https://" . htmlspecialchars($_SERVER['HTTP_HOST']) . "</strong></li>";
echo "</ol>";

echo "<h3>Alternative: Manual Widget Test</h3>";
echo "<div style='margin: 20px 0;'>";
echo "<script async src='https://telegram.org/js/telegram-widget.js?22' ";
echo "data-telegram-login='" . htmlspecialchars(TelegramConfig::BOT_NAME) . "' ";
echo "data-size='large' ";
echo "data-auth-url='https://" . htmlspecialchars($_SERVER['HTTP_HOST']) . "/web/login.php' ";
echo "data-request-access='write'>";
echo "</script>";
echo "</div>";
?>
