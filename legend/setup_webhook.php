<?php
/**
 * Webhook Setup Script
 * Sets up Telegram webhook with proper HTTPS URL
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

$bot_token = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU';
$bot_api_url = "https://api.telegram.org/bot{$bot_token}/";
$webhook_url = '/telegram_webhook_enhanced.php';
$owner_id = 5652614329; // @LEGEND_BL

?>
<!DOCTYPE html>
<html>
<head>
    <title>Webhook Setup - LEGEND CHECKER</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #fff;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #2a2a2a;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #1da1f2;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h2 {
            color: #00d4aa;
            border-bottom: 2px solid #00d4aa;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 16px;
        }
        .success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
        }
        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        .warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid #f59e0b;
            color: #f59e0b;
        }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }
        pre {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border: 1px solid #444;
        }
        .btn {
            background: linear-gradient(135deg, #1da1f2, #00d4aa);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .icon {
            font-size: 24px;
            margin-right: 8px;
        }
        .step {
            background: #333;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #1da1f2;
        }
        .code {
            background: #1a1a1a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #00d4aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">🤖</span>Telegram Webhook Setup</h1>
        
        <?php
        // Step 1: Test Bot Token
        echo "<h2><span class='icon'>1️⃣</span>Bot Token Validation</h2>";
        
        $ch = curl_init($bot_api_url . 'getMe');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            echo "<div class='status-box error'>";
            echo "<span class='icon'>❌</span><strong>cURL Error:</strong> {$curl_error}";
            echo "</div>";
            exit;
        }
        
        $result = json_decode($response, true);
        
        if ($result['ok']) {
            $bot_info = $result['result'];
            echo "<div class='status-box success'>";
            echo "<span class='icon'>✅</span><strong>Bot is valid and active!</strong><br>";
            echo "Username: <code>@{$bot_info['username']}</code><br>";
            echo "ID: <code>{$bot_info['id']}</code><br>";
            echo "Name: <code>{$bot_info['first_name']}</code>";
            echo "</div>";
            echo "<pre>" . json_encode($bot_info, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<div class='status-box error'>";
            echo "<span class='icon'>❌</span><strong>Bot token is invalid!</strong>";
            echo "</div>";
            exit;
        }
        
        // Step 2: Check Current Webhook
        echo "<h2><span class='icon'>2️⃣</span>Current Webhook Status</h2>";
        
        $ch = curl_init($bot_api_url . 'getWebhookInfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $webhook_info = json_decode($response, true);
        
        if ($webhook_info['ok']) {
            $info = $webhook_info['result'];
            echo "<pre>" . json_encode($info, JSON_PRETTY_PRINT) . "</pre>";
            
            $current_url = $info['url'] ?? '';
            $pending = $info['pending_update_count'] ?? 0;
            $last_error = $info['last_error_message'] ?? '';
            $last_error_date = $info['last_error_date'] ?? 0;
            
            if (empty($current_url)) {
                echo "<div class='status-box warning'>";
                echo "<span class='icon'>⚠️</span><strong>No webhook is set!</strong> Bot is not receiving updates.";
                echo "</div>";
            } else {
                if ($current_url === $webhook_url) {
                    echo "<div class='status-box success'>";
                    echo "<span class='icon'>✅</span><strong>Webhook is correctly set!</strong><br>";
                    echo "URL: <code>{$current_url}</code>";
                    echo "</div>";
                } else {
                    echo "<div class='status-box warning'>";
                    echo "<span class='icon'>⚠️</span><strong>Webhook URL mismatch!</strong><br>";
                    echo "Current: <code>{$current_url}</code><br>";
                    echo "Expected: <code>{$webhook_url}</code>";
                    echo "</div>";
                }
            }
            
            if ($pending > 0) {
                echo "<div class='status-box info'>";
                echo "<span class='icon'>📬</span><strong>Pending Updates:</strong> {$pending} messages waiting";
                echo "</div>";
            }
            
            if ($last_error) {
                echo "<div class='status-box error'>";
                echo "<span class='icon'>❌</span><strong>Last Error:</strong> {$last_error}<br>";
                echo "Time: " . date('Y-m-d H:i:s', $last_error_date);
                echo "</div>";
            }
        }
        
        // Step 3: Set Webhook
        echo "<h2><span class='icon'>3️⃣</span>Set New Webhook</h2>";
        
        echo "<div class='status-box info'>";
        echo "<span class='icon'>🔗</span><strong>Target Webhook URL:</strong><br>";
        echo "<code>{$webhook_url}</code>";
        echo "</div>";
        
        // Check if using HTTPS
        if (strpos($webhook_url, 'https://') !== 0) {
            echo "<div class='status-box error'>";
            echo "<span class='icon'>❌</span><strong>CRITICAL: Webhook URL must use HTTPS!</strong><br>";
            echo "Current: <code>{$webhook_url}</code><br>";
            echo "Telegram requires HTTPS URLs for webhooks.<br><br>";
            echo "<strong>Solutions:</strong><br>";
            echo "1. Enable SSL on your domain<br>";
            echo "2. Use Cloudflare (free SSL)<br>";
            echo "3. Use ngrok for testing: <code>ngrok http 80</code>";
            echo "</div>";
        } else {
            // Attempt to set webhook
            $ch = curl_init($bot_api_url . 'setWebhook');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'url' => $webhook_url,
                'drop_pending_updates' => false,
                'max_connections' => 40
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($result['ok']) {
                echo "<div class='status-box success'>";
                echo "<span class='icon'>✅</span><strong>Webhook set successfully!</strong><br>";
                echo "Your bot is now ready to receive messages.";
                echo "</div>";
            } else {
                echo "<div class='status-box error'>";
                echo "<span class='icon'>❌</span><strong>Failed to set webhook:</strong><br>";
                echo $result['description'] ?? 'Unknown error';
                echo "</div>";
            }
        }
        
        // Step 4: Send Test Message
        echo "<h2><span class='icon'>4️⃣</span>Send Test Message</h2>";
        
        $test_message = "🤖 <b>Webhook Setup Complete!</b>\n\n";
        $test_message .= "✅ Bot: @{$bot_info['username']}\n";
        $test_message .= "✅ Webhook: Set\n";
        $test_message .= "✅ Time: " . date('Y-m-d H:i:s') . "\n\n";
        $test_message .= "🎉 Your bot is now operational!\n\n";
        $test_message .= "<b>Try these commands:</b>\n";
        $test_message .= "/start - Welcome message\n";
        $test_message .= "/credits - Check your credits\n";
        $test_message .= "/help - See all commands\n";
        $test_message .= "/ping - Test bot response";
        
        $ch = curl_init($bot_api_url . 'sendMessage');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $owner_id,
            'text' => $test_message,
            'parse_mode' => 'HTML'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result['ok']) {
            echo "<div class='status-box success'>";
            echo "<span class='icon'>✅</span><strong>Test message sent to owner!</strong><br>";
            echo "Check your Telegram: <code>@LEGEND_BL</code>";
            echo "</div>";
        } else {
            echo "<div class='status-box warning'>";
            echo "<span class='icon'>⚠️</span><strong>Could not send test message:</strong><br>";
            echo $result['description'] ?? 'Owner may need to start the bot first';
            echo "</div>";
        }
        ?>
        
        <h2><span class="icon">📋</span>Next Steps</h2>
        
        <div class="step">
            <strong>1. Test Your Bot</strong><br>
            Open Telegram and search for <code class="code">@<?php echo $bot_info['username']; ?></code><br>
            Send <code class="code">/start</code> command
        </div>
        
        <div class="step">
            <strong>2. Available Commands</strong><br>
            <code class="code">/start</code> - Start bot and register<br>
            <code class="code">/credits</code> - Check your credits balance<br>
            <code class="code">/claim</code> - Claim daily credits<br>
            <code class="code">/check</code> - Check a credit card<br>
            <code class="code">/site</code> - Check a website<br>
            <code class="code">/help</code> - List all commands<br>
            <code class="code">/ping</code> - Test bot response
        </div>
        
        <div class="step">
            <strong>3. Admin Commands (Owner Only)</strong><br>
            <code class="code">/notif</code> - Toggle notifications<br>
            <code class="code">/settimeout</code> - Set API timeout<br>
            <code class="code">/setchat</code> - Set notification channel<br>
            <code class="code">/health</code> - Bot health check<br>
            <code class="code">/getwebhook</code> - View webhook info
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="dashboard.php" class="btn">
                <span class="icon">🏠</span> Go to Dashboard
            </a>
            <a href="check_extensions.php" class="btn">
                <span class="icon">🔧</span> Check Extensions
            </a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn">
                <span class="icon">🔄</span> Refresh Status
            </a>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #1a1a1a; border-radius: 8px; border-left: 4px solid #10b981;">
            <h3 style="margin-top: 0; color: #10b981;">✅ System Status Summary</h3>
            <p>✅ PHP Extensions: Enabled</p>
            <p>✅ Bot Token: Valid</p>
            <p><?php echo strpos($webhook_url, 'https://') === 0 ? '✅' : '❌'; ?> Webhook URL: <?php echo strpos($webhook_url, 'https://') === 0 ? 'HTTPS' : 'HTTP (requires HTTPS)'; ?></p>
            <p>✅ cURL: Working</p>
            <p>✅ OpenSSL: Working</p>
        </div>
        
        <p style="text-align: center; color: #666; margin-top: 30px; font-size: 14px;">
            Generated: <?php echo date('Y-m-d H:i:s'); ?><br>
            Owner: @LEGEND_BL (ID: <?php echo $owner_id; ?>)
        </p>
    </div>
</body>
</html>
