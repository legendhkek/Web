<?php
/**
 * Daily Proxy Check Script
 * Run this script daily via cron to check all proxies and remove dead ones
 * 
 * Cron example (runs daily at 2 AM):
 * 0 2 * * * /usr/bin/php /path/to/daily_proxy_check.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Set execution time limit for large proxy lists
set_time_limit(300); // 5 minutes

$db = Database::getInstance();

echo "Starting daily proxy check...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Get all proxies
$proxies = $db->getAllProxies();
$totalProxies = count($proxies);

echo "Total proxies to check: $totalProxies\n\n";

if ($totalProxies === 0) {
    echo "No proxies to check.\n";
    exit(0);
}

$checked = 0;
$live = 0;
$dead = 0;
$errors = 0;

foreach ($proxies as $proxy) {
    // Handle MongoDB ObjectId
    if (is_object($proxy['_id']) && method_exists($proxy['_id'], '__toString')) {
        $proxyId = $proxy['_id']->__toString();
    } elseif (is_array($proxy['_id'])) {
        $proxyId = $proxy['_id']->__toString();
    } else {
        $proxyId = (string)$proxy['_id'];
    }
    $proxyString = $proxy['proxy'];
    
    echo "Checking: $proxyString ... ";
    
    // Check proxy
    $checkResult = checkProxy($proxyString);
    
    if ($checkResult['success']) {
        $db->updateProxyStatus($proxyId, 'live', [
            'ip' => $checkResult['ip'] ?? null,
            'country' => $checkResult['country'] ?? null,
            'city' => $checkResult['city'] ?? null,
            'response_time' => $checkResult['response_time'] ?? null
        ]);
        $live++;
        echo "LIVE ({$checkResult['response_time']}ms)\n";
    } else {
        $db->updateProxyStatus($proxyId, 'dead', [
            'last_error' => $checkResult['error'] ?? 'Unknown error'
        ]);
        $dead++;
        echo "DEAD ({$checkResult['error']})\n";
    }
    
    $checked++;
    
    // Small delay to avoid overwhelming the system
    usleep(100000); // 0.1 second delay
}

echo "\n";
echo "========================================\n";
echo "Daily Proxy Check Complete\n";
echo "========================================\n";
echo "Total checked: $checked\n";
echo "Live proxies: $live\n";
echo "Dead proxies: $dead\n";
echo "Errors: $errors\n";
echo "\n";

// Delete dead proxies
if ($dead > 0) {
    echo "Deleting dead proxies...\n";
    $deleted = $db->deleteDeadProxies();
    echo "Deleted $deleted dead proxy(ies)\n";
}

// Get updated stats
$stats = $db->getProxyStats();
echo "\n";
echo "Current Proxy Stats:\n";
echo "Total: {$stats['total']}\n";
echo "Live: {$stats['live']}\n";
echo "Dead: {$stats['dead']}\n";
echo "Unknown: {$stats['unknown']}\n";

echo "\nDaily proxy check completed at " . date('Y-m-d H:i:s') . "\n";

/**
 * Check proxy function (same as in proxy_manager.php)
 */
function checkProxy($proxy) {
    $parts = explode(':', $proxy);
    if (count($parts) < 2) {
        return ['success' => false, 'error' => 'Invalid format'];
    }
    
    $host = trim($parts[0]);
    $port = (int)trim($parts[1]);
    $user = isset($parts[2]) ? trim($parts[2]) : '';
    $pass = isset($parts[3]) ? trim($parts[3]) : '';
    
    $testUrl = 'http://httpbin.org/ip';
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROXY => "$host:$port",
        CURLOPT_PROXYUSERPWD => !empty($user) ? "$user:$pass" : null,
        CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $response = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000);
    curl_close($ch);
    
    if ($curlError || $curlErrno) {
        return [
            'success' => false,
            'error' => $curlError ?: 'Connection failed',
            'response_time' => $responseTime
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $httpCode",
            'response_time' => $responseTime
        ];
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['origin'])) {
        return [
            'success' => false,
            'error' => 'Invalid response',
            'response_time' => $responseTime
        ];
    }
    
    // Get geo info
    $country = 'Unknown';
    $city = null;
    try {
        $geoUrl = "http://ip-api.com/json/{$data['origin']}?fields=status,country,city";
        $geoCh = curl_init();
        curl_setopt_array($geoCh, [
            CURLOPT_URL => $geoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        $geoResponse = @curl_exec($geoCh);
        $geoHttpCode = curl_getinfo($geoCh, CURLINFO_HTTP_CODE);
        curl_close($geoCh);
        
        if ($geoResponse && $geoHttpCode === 200) {
            $geoData = json_decode($geoResponse, true);
            if ($geoData && isset($geoData['country']) && $geoData['status'] === 'success') {
                $country = $geoData['country'];
                $city = isset($geoData['city']) ? $geoData['city'] : null;
            }
        }
    } catch (Exception $e) {
        // Ignore geo lookup errors
    }
    
    return [
        'success' => true,
        'ip' => $data['origin'],
        'country' => $country,
        'city' => $city,
        'response_time' => $responseTime
    ];
}
