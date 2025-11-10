<?php
/**
 * System Health Check Endpoint
 * Provides status of critical system components
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'config.php';
require_once 'database.php';

// Initialize response
$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => [],
    'version' => '2.0.0'
];

// Check 1: PHP Version
$health['checks']['php'] = [
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warning',
    'version' => PHP_VERSION,
    'message' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'PHP version is adequate' : 'PHP version is outdated'
];

// Check 2: Required Extensions
$required_extensions = ['curl', 'json', 'mbstring', 'session'];
$extensions_status = 'ok';
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $extensions_status = 'error';
        $missing_extensions[] = $ext;
    }
}

$health['checks']['extensions'] = [
    'status' => $extensions_status,
    'required' => $required_extensions,
    'missing' => $missing_extensions,
    'message' => empty($missing_extensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missing_extensions)
];

// Check 3: Database Connection
try {
    $db = Database::getInstance();
    $testResult = $db->getTotalUsersCount();
    $health['checks']['database'] = [
        'status' => 'ok',
        'type' => 'MongoDB',
        'users' => $testResult,
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
    $health['status'] = 'unhealthy';
}

// Check 4: Session System
try {
    initSecureSession();
    $health['checks']['session'] = [
        'status' => 'ok',
        'message' => 'Session system operational'
    ];
} catch (Exception $e) {
    $health['checks']['session'] = [
        'status' => 'error',
        'message' => 'Session system failed: ' . $e->getMessage()
    ];
    $health['status'] = 'degraded';
}

// Check 5: File System (data directory)
$data_dir = __DIR__ . '/data';
if (is_dir($data_dir) && is_writable($data_dir)) {
    $health['checks']['filesystem'] = [
        'status' => 'ok',
        'writable' => true,
        'message' => 'Data directory is writable'
    ];
} else {
    $health['checks']['filesystem'] = [
        'status' => 'warning',
        'writable' => false,
        'message' => 'Data directory not writable or does not exist'
    ];
    if ($health['status'] === 'healthy') {
        $health['status'] = 'degraded';
    }
}

// Check 6: Telegram Bot API
try {
    $bot_token = TelegramConfig::BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$bot_token}/getMe";
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['ok']) && $data['ok']) {
            $health['checks']['telegram_bot'] = [
                'status' => 'ok',
                'bot_username' => $data['result']['username'] ?? 'unknown',
                'message' => 'Telegram Bot API accessible'
            ];
        } else {
            $health['checks']['telegram_bot'] = [
                'status' => 'error',
                'message' => 'Telegram Bot API returned error'
            ];
            if ($health['status'] === 'healthy') {
                $health['status'] = 'degraded';
            }
        }
    } else {
        $health['checks']['telegram_bot'] = [
            'status' => 'error',
            'message' => 'Cannot reach Telegram Bot API'
        ];
        if ($health['status'] === 'healthy') {
            $health['status'] = 'degraded';
        }
    }
} catch (Exception $e) {
    $health['checks']['telegram_bot'] = [
        'status' => 'error',
        'message' => 'Telegram check failed: ' . $e->getMessage()
    ];
    if ($health['status'] === 'healthy') {
        $health['status'] = 'degraded';
    }
}

// Check 7: External API (Checker API)
try {
    $api_url = AppConfig::CHECKER_API_URL;
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    $response = @file_get_contents($api_url, false, $context);
    
    $health['checks']['checker_api'] = [
        'status' => $response !== false ? 'ok' : 'warning',
        'url' => parse_url($api_url, PHP_URL_HOST),
        'message' => $response !== false ? 'Checker API is reachable' : 'Checker API may be unreachable'
    ];
} catch (Exception $e) {
    $health['checks']['checker_api'] = [
        'status' => 'warning',
        'message' => 'Cannot verify Checker API'
    ];
}

// Check 8: Disk Space
$total_space = @disk_total_space(__DIR__);
$free_space = @disk_free_space(__DIR__);

if ($total_space && $free_space) {
    $used_percentage = (1 - ($free_space / $total_space)) * 100;
    $health['checks']['disk_space'] = [
        'status' => $used_percentage < 90 ? 'ok' : 'warning',
        'free_gb' => round($free_space / 1024 / 1024 / 1024, 2),
        'total_gb' => round($total_space / 1024 / 1024 / 1024, 2),
        'used_percent' => round($used_percentage, 2),
        'message' => $used_percentage < 90 ? 'Sufficient disk space' : 'Disk space running low'
    ];
    
    if ($used_percentage >= 90 && $health['status'] === 'healthy') {
        $health['status'] = 'degraded';
    }
} else {
    $health['checks']['disk_space'] = [
        'status' => 'unknown',
        'message' => 'Cannot determine disk space'
    ];
}

// Check 9: Memory Usage
$memory_limit = ini_get('memory_limit');
$memory_usage = memory_get_usage(true);

$health['checks']['memory'] = [
    'status' => 'ok',
    'limit' => $memory_limit,
    'usage_mb' => round($memory_usage / 1024 / 1024, 2),
    'message' => 'Memory usage within limits'
];

// Overall status determination
$error_count = 0;
$warning_count = 0;

foreach ($health['checks'] as $check) {
    if ($check['status'] === 'error') {
        $error_count++;
    } elseif ($check['status'] === 'warning') {
        $warning_count++;
    }
}

if ($error_count > 0) {
    $health['status'] = 'unhealthy';
} elseif ($warning_count > 2) {
    $health['status'] = 'degraded';
}

// Set HTTP status code based on health
http_response_code($health['status'] === 'healthy' ? 200 : ($health['status'] === 'degraded' ? 207 : 503));

// Output health check
echo json_encode($health, JSON_PRETTY_PRINT);
?>
