<?php
require_once 'config.php';

// Debug Telegram bot configuration
echo "<h2>Telegram Bot Debug Information</h2>";
echo "<p><strong>Bot Name:</strong> " . TelegramConfig::BOT_NAME . "</p>";
echo "<p><strong>Current Domain:</strong> https://" . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p><strong>Auth URL:</strong> https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</p>";

// Test bot API
$botToken = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU';
$url = "https://api.telegram.org/bot{$botToken}/getMe";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>Bot API Test:</h3>";
echo "<p><strong>HTTP Code:</strong> " . $httpCode . "</p>";
echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && $data['ok']) {
        echo "<p style='color: green;'><strong>✅ Bot is working correctly!</strong></p>";
        echo "<p><strong>Bot Username:</strong> @" . $data['result']['username'] . "</p>";
        echo "<p><strong>Bot ID:</strong> " . $data['result']['id'] . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Bot API error!</strong></p>";
}

// Check domain setup
echo "<h3>Domain Setup Instructions:</h3>";
echo "<ol>";
echo "<li>Go to <a href='https://t.me/BotFather' target='_blank'>@BotFather</a> on Telegram</li>";
echo "<li>Send <code>/setdomain</code></li>";
echo "<li>Select your bot: <strong>" . TelegramConfig::BOT_NAME . "</strong></li>";
echo "<li>Set domain to: <strong>https://" . $_SERVER['HTTP_HOST'] . "</strong></li>";
echo "</ol>";

echo "<h3>Alternative: Manual Widget Test</h3>";
echo "<div style='margin: 20px 0;'>";
echo "<script async src='https://telegram.org/js/telegram-widget.js?22' ";
echo "data-telegram-login='" . TelegramConfig::BOT_NAME . "' ";
echo "data-size='large' ";
echo "data-auth-url='https://" . $_SERVER['HTTP_HOST'] . "/web/login.php' ";
echo "data-request-access='write'>";
echo "</script>";
echo "</div>";
?>
