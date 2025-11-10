<?php
/**
 * System Health Check & Error Detector
 * Run this file to check for common issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>LEGEND CHECKER - System Health Check</title>";
echo "<style>
    body {
        font-family: 'Courier New', monospace;
        background: #0f0f23;
        color: #00ffea;
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    .section {
        background: #1a2b49;
        border: 1px solid #00bcd4;
        border-radius: 10px;
        padding: 20px;
        margin: 20px 0;
    }
    .success {
        color: #00e676;
        font-weight: bold;
    }
    .error {
        color: #ff073a;
        font-weight: bold;
    }
    .warning {
        color: #e67e22;
        font-weight: bold;
    }
    h1 {
        color: #00e676;
        text-shadow: 0 0 10px rgba(0, 230, 118, 0.5);
    }
    h2 {
        color: #00bcd4;
        border-bottom: 2px solid #00bcd4;
        padding-bottom: 10px;
    }
    ul {
        list-style: none;
        padding-left: 0;
    }
    li {
        margin: 10px 0;
        padding: 10px;
        background: #223041;
        border-radius: 5px;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8em;
        margin-right: 10px;
    }
    .badge-success { background: #00e676; color: #000; }
    .badge-error { background: #ff073a; color: white; }
    .badge-warning { background: #e67e22; color: white; }
</style></head><body>";

echo "<h1>🔍 LEGEND CHECKER - System Health Check</h1>";
echo "<p>Running comprehensive system diagnostics...</p>";

// Test Results
$tests_passed = 0;
$tests_failed = 0;
$tests_warning = 0;

// 1. Check PHP Version
echo "<div class='section'>";
echo "<h2>1. PHP Environment</h2>";
echo "<ul>";

$php_version = phpversion();
if (version_compare($php_version, '7.4.0', '>=')) {
    echo "<li><span class='badge badge-success'>✓ PASS</span> PHP Version: $php_version</li>";
    $tests_passed++;
} else {
    echo "<li><span class='badge badge-error'>✗ FAIL</span> PHP Version: $php_version (Requires 7.4+)</li>";
    $tests_failed++;
}

// Check required extensions
$required_extensions = ['curl', 'json', 'mbstring', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<li><span class='badge badge-success'>✓ PASS</span> Extension '$ext' is loaded</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> Extension '$ext' is missing</li>";
        $tests_failed++;
    }
}

echo "</ul></div>";

// 2. Check Required Files
echo "<div class='section'>";
echo "<h2>2. File Structure</h2>";
echo "<ul>";

$required_files = [
    'config.php',
    'database.php',
    'auth.php',
    'check_card_ajax.php',
    'check_site_ajax.php',
    'telegram_webhook.php',
    'bot_setup.php',
    'advanced_checker.php',
    'admin/credit_generator.php',
    'admin/broadcast.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<li><span class='badge badge-success'>✓ PASS</span> File exists: $file</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> File missing: $file</li>";
        $tests_failed++;
    }
}

echo "</ul></div>";

// 3. Check Directory Permissions
echo "<div class='section'>";
echo "<h2>3. Directory Permissions</h2>";
echo "<ul>";

$directories = ['data', 'admin'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<li><span class='badge badge-success'>✓ PASS</span> Directory '$dir' is writable</li>";
            $tests_passed++;
        } else {
            echo "<li><span class='badge badge-warning'>⚠ WARN</span> Directory '$dir' is not writable</li>";
            $tests_warning++;
        }
    } else {
        echo "<li><span class='badge badge-warning'>⚠ WARN</span> Directory '$dir' does not exist (will be created on first use)</li>";
        $tests_warning++;
    }
}

echo "</ul></div>";

// 4. Check Configuration
echo "<div class='section'>";
echo "<h2>4. Configuration Check</h2>";
echo "<ul>";

if (file_exists('config.php')) {
    require_once 'config.php';
    
    // Check bot token
    $bot_token = TelegramConfig::BOT_TOKEN ?? '';
    if (!empty($bot_token) && $bot_token !== '8305972211:AAGpfN5uiUMqXCw3KjmF07MN059SMggDGJ4') {
        echo "<li><span class='badge badge-success'>✓ PASS</span> Bot token is configured: " . substr($bot_token, 0, 10) . "...</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> Bot token not configured or using fallback</li>";
        $tests_failed++;
    }
    
    // Check MongoDB URI
    $mongodb_uri = DatabaseConfig::MONGODB_URI ?? '';
    if (!empty($mongodb_uri) && strpos($mongodb_uri, 'mongodb') !== false) {
        echo "<li><span class='badge badge-success'>✓ PASS</span> MongoDB URI is configured</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> MongoDB URI not configured</li>";
        $tests_failed++;
    }
}

echo "</ul></div>";

// 5. Test Database Connection
echo "<div class='section'>";
echo "<h2>5. Database Connection</h2>";
echo "<ul>";

try {
    if (file_exists('database.php')) {
        require_once 'database.php';
        $db = Database::getInstance();
        echo "<li><span class='badge badge-success'>✓ PASS</span> Database instance created successfully</li>";
        $tests_passed++;
    } else {
        throw new Exception("database.php not found");
    }
} catch (Exception $e) {
    echo "<li><span class='badge badge-warning'>⚠ WARN</span> Database connection issue: " . $e->getMessage() . " (Using fallback)</li>";
    $tests_warning++;
}

echo "</ul></div>";

// 6. Test Bot Webhook
echo "<div class='section'>";
echo "<h2>6. Telegram Bot Status</h2>";
echo "<ul>";

$bot_token = TelegramConfig::BOT_TOKEN ?? '';
if (!empty($bot_token)) {
    $url = "https://api.telegram.org/bot{$bot_token}/getWebhookInfo";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && $data['ok']) {
            $webhook_url = $data['result']['url'] ?? 'Not set';
            if (!empty($webhook_url) && $webhook_url !== 'Not set') {
                echo "<li><span class='badge badge-success'>✓ PASS</span> Bot webhook is active</li>";
                echo "<li>Webhook URL: <code>$webhook_url</code></li>";
                $tests_passed++;
            } else {
                echo "<li><span class='badge badge-warning'>⚠ WARN</span> Bot webhook not configured</li>";
                echo "<li>Configure at: <a href='bot_setup.php' style='color: #00e676;'>bot_setup.php</a></li>";
                $tests_warning++;
            }
            
            $pending_updates = $data['result']['pending_update_count'] ?? 0;
            if ($pending_updates > 0) {
                echo "<li><span class='badge badge-warning'>⚠ WARN</span> Pending updates: $pending_updates</li>";
                $tests_warning++;
            }
        } else {
            echo "<li><span class='badge badge-error'>✗ FAIL</span> Invalid bot response</li>";
            $tests_failed++;
        }
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> Cannot connect to Telegram API (HTTP $http_code)</li>";
        $tests_failed++;
    }
} else {
    echo "<li><span class='badge badge-error'>✗ FAIL</span> Bot token not configured</li>";
    $tests_failed++;
}

echo "</ul></div>";

// 7. Credit System Check
echo "<div class='section'>";
echo "<h2>7. Credit System</h2>";
echo "<ul>";

if (file_exists('check_card_ajax.php')) {
    $content = file_get_contents('check_card_ajax.php');
    if (strpos($content, 'deductCredits') !== false) {
        echo "<li><span class='badge badge-success'>✓ PASS</span> Credit deduction is implemented in card checker</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> Credit deduction missing in card checker</li>";
        $tests_failed++;
    }
    
    if (strpos($content, 'session_start') !== false || strpos($content, 'SESSION') !== false) {
        echo "<li><span class='badge badge-success'>✓ PASS</span> Authentication is implemented in card checker</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> Authentication missing in card checker</li>";
        $tests_failed++;
    }
}

if (file_exists('check_site_ajax.php')) {
    $content = file_get_contents('check_site_ajax.php');
    if (strpos($content, 'deductCredits') !== false) {
        echo "<li><span class='badge badge-success'>✓ PASS</span> Credit deduction is implemented in site checker</li>";
        $tests_passed++;
    } else {
        echo "<li><span class='badge badge-error'>✗ FAIL</span> Credit deduction missing in site checker</li>";
        $tests_failed++;
    }
}

echo "</ul></div>";

// Summary
echo "<div class='section'>";
echo "<h2>📊 Test Summary</h2>";
$total_tests = $tests_passed + $tests_failed + $tests_warning;
$pass_rate = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100) : 0;

echo "<ul>";
echo "<li><span class='badge badge-success'>✓</span> Passed: $tests_passed</li>";
echo "<li><span class='badge badge-error'>✗</span> Failed: $tests_failed</li>";
echo "<li><span class='badge badge-warning'>⚠</span> Warnings: $tests_warning</li>";
echo "<li><strong>Total Tests: $total_tests</strong></li>";
echo "<li><strong>Pass Rate: {$pass_rate}%</strong></li>";
echo "</ul>";

if ($tests_failed === 0 && $tests_warning === 0) {
    echo "<p class='success'>🎉 All tests passed! Your system is fully functional.</p>";
} elseif ($tests_failed === 0) {
    echo "<p class='warning'>⚠️ System is functional but has some warnings. Review above for details.</p>";
} else {
    echo "<p class='error'>❌ System has critical errors. Fix the failed tests above.</p>";
}

echo "</div>";

// Quick Actions
echo "<div class='section'>";
echo "<h2>🚀 Quick Actions</h2>";
echo "<ul>";
echo "<li><a href='bot_setup.php' style='color: #00e676;'>🤖 Setup Telegram Bot</a></li>";
echo "<li><a href='admin/credit_generator.php' style='color: #00e676;'>💰 Generate Credit Codes</a></li>";
echo "<li><a href='advanced_checker.php' style='color: #00e676;'>🔍 Advanced Checker</a></li>";
echo "<li><a href='dashboard.php' style='color: #00e676;'>📊 Dashboard</a></li>";
echo "</ul>";
echo "</div>";

echo "<p style='text-align: center; color: #00bcd4; margin-top: 40px;'>";
echo "LEGEND CHECKER © 2025 - System Health Check Complete";
echo "</p>";

echo "</body></html>";
?>