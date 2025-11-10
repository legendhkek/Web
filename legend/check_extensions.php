<!DOCTYPE html>
<html>
<head>
    <title>PHP Extensions Status</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #0a0a0a; color: #fff; }
        .status { padding: 15px; margin: 10px 0; border-radius: 8px; font-size: 18px; }
        .success { background: #10b981; color: white; }
        .error { background: #ef4444; color: white; }
        .warning { background: #f59e0b; color: white; }
        .info { background: #3b82f6; color: white; }
        h1 { color: #1da1f2; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .icon { font-size: 24px; margin-right: 10px; }
    </style>
</head>
<body>
    <h1>🔧 PHP Extensions Status Check</h1>
    
    <div class="status <?php echo extension_loaded('curl') ? 'success' : 'error'; ?>">
        <span class="icon"><?php echo extension_loaded('curl') ? '✅' : '❌'; ?></span>
        <strong>cURL Extension:</strong> <?php echo extension_loaded('curl') ? 'ENABLED' : 'DISABLED'; ?>
    </div>
    
    <div class="status <?php echo extension_loaded('openssl') ? 'success' : 'error'; ?>">
        <span class="icon"><?php echo extension_loaded('openssl') ? '✅' : '❌'; ?></span>
        <strong>OpenSSL Extension:</strong> <?php echo extension_loaded('openssl') ? 'ENABLED' : 'DISABLED'; ?>
    </div>
    
    <div class="status <?php echo extension_loaded('mbstring') ? 'success' : 'error'; ?>">
        <span class="icon"><?php echo extension_loaded('mbstring') ? '✅' : '❌'; ?></span>
        <strong>mbstring Extension:</strong> <?php echo extension_loaded('mbstring') ? 'ENABLED' : 'DISABLED'; ?>
    </div>
    
    <div class="status <?php echo extension_loaded('fileinfo') ? 'success' : 'error'; ?>">
        <span class="icon"><?php echo extension_loaded('fileinfo') ? '✅' : '❌'; ?></span>
        <strong>fileinfo Extension:</strong> <?php echo extension_loaded('fileinfo') ? 'ENABLED' : 'DISABLED'; ?>
    </div>
    
    <div class="status info">
        <span class="icon">ℹ️</span>
        <strong>PHP Version:</strong> <?php echo phpversion(); ?>
    </div>
    
    <h2>🌐 cURL Test</h2>
    <?php
    require_once 'config.php';
    $curl_test_status = 'error';
    if (extension_loaded('curl')) {
        $ch = curl_init('https://api.telegram.org/bot' . TelegramConfig::BOT_TOKEN . '/getMe');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            if ($data['ok']) {
                $curl_test_status = 'success';
            }
        }
    }
    ?>
    <div class="status <?php echo $curl_test_status; ?>">
        <span class="icon"><?php
            if (extension_loaded('curl')) {
                if (!isset($botInfo)) {
                    $ch = curl_init('https://api.telegram.org/bot' . TelegramConfig::BOT_TOKEN . '/getMe');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode == 200 && $response) {
                        $data = json_decode($response, true);
                        if ($data['ok']) {
                            echo '✅';
                            $botInfo = $data['result'];
                        } else {
                            echo '❌';
                        }
                    } else {
                        echo '❌';
                    }
                } else {
                    echo '✅';
                }
            } else {
                echo '❌';
            }
        ?></span>
        <strong>Telegram Bot API Test:</strong> 
        <?php
            if (isset($botInfo)) {
                echo "SUCCESS - Connected to @{$botInfo['username']} (ID: {$botInfo['id']})";
            } elseif (extension_loaded('curl')) {
                echo "FAILED - Could not connect to Telegram API";
            } else {
                echo "FAILED - cURL not available";
            }
        ?>
    </div>
    
    <?php if (isset($botInfo)): ?>
    <h2>🤖 Bot Information</h2>
    <pre><?php echo json_encode($botInfo, JSON_PRETTY_PRINT); ?></pre>
    <?php endif; ?>
    
    <h2>📋 Next Steps</h2>
    <div class="status warning">
        <span class="icon">⚠️</span>
        <strong>Important:</strong> Your domain needs HTTPS to set webhook.<br>
        Current domain: <?php echo AppConfig::DOMAIN; ?><br>
        <?php if (strpos(AppConfig::DOMAIN, 'https://') === false): ?>
        ❌ Using HTTP - Telegram requires HTTPS for webhooks!<br>
        Update config.php: <code>const DOMAIN = 'https://autoshopify.sonugamingop.tech';</code>
        <?php else: ?>
        ✅ Using HTTPS - Ready to set webhook!<br>
        <a href="test_bot.php" style="color: white; text-decoration: underline;">Click here to test and set webhook</a>
        <?php endif; ?>
    </div>
    
    <div class="status info">
        <span class="icon">ℹ️</span>
        <strong>All Systems Check:</strong><br>
        • Dashboard: <a href="dashboard.php" style="color: white;">Visit Dashboard</a><br>
        • Bot Test: <a href="test_bot.php" style="color: white;">Test Telegram Bot</a><br>
        • Card Checker: <a href="card_checker.php" style="color: white;">Card Checker</a><br>
        • Site Checker: <a href="site_checker.php" style="color: white;">Site Checker</a>
    </div>
    
    <p style="text-align: center; color: #666; margin-top: 40px;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
