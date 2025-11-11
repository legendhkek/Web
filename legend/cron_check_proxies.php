<?php
/**
 * Daily Proxy Check Cron Job
 * This script should be run daily via cron to check all proxies
 * and automatically remove dead ones if configured
 * 
 * Add to crontab:
 * 0 2 * * * /usr/bin/php /path/to/legend/cron_check_proxies.php
 */

require_once __DIR__ . '/config.php';

// Proxy file path
$proxyFile = __DIR__ . '/data/proxies.json';

// Load proxies
function loadProxies() {
    global $proxyFile;
    if (!file_exists($proxyFile)) {
        return [
            'proxies' => [],
            'stats' => ['total' => 0, 'live' => 0, 'dead' => 0, 'last_check' => null],
            'settings' => ['auto_check_enabled' => true, 'check_interval_hours' => 24, 'remove_dead_auto' => true]
        ];
    }
    return json_decode(file_get_contents($proxyFile), true);
}

// Save proxies
function saveProxies($data) {
    global $proxyFile;
    return file_put_contents($proxyFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Update stats
function updateStats(&$data) {
    $data['stats']['total'] = count($data['proxies']);
    $data['stats']['live'] = count(array_filter($data['proxies'], fn($p) => $p['status'] === 'live'));
    $data['stats']['dead'] = count(array_filter($data['proxies'], fn($p) => $p['status'] === 'dead'));
}

// Check single proxy
function checkProxy($proxy) {
    $parts = explode(':', $proxy);
    if (count($parts) !== 4) {
        return ['status' => 'dead', 'error' => 'Invalid format'];
    }
    
    list($host, $port, $user, $pass) = array_map('trim', $parts);
    
    // Validate port
    $port = (int)$port;
    if ($port < 1 || $port > 65535) {
        return ['status' => 'dead', 'error' => 'Invalid port'];
    }
    
    // Test proxy
    $test_url = 'http://httpbin.org/ip';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $test_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROXY => "$host:$port",
        CURLOPT_PROXYUSERPWD => "$user:$pass",
        CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $response = @curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        return ['status' => 'dead', 'error' => $curl_error ?: "HTTP $http_code"];
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['origin'])) {
        return ['status' => 'dead', 'error' => 'Invalid response'];
    }
    
    // Get geo info
    $country = 'Unknown';
    $city = null;
    try {
        $geo_url = "http://ip-api.com/json/{$data['origin']}?fields=status,country,city";
        $geo_ch = curl_init();
        curl_setopt_array($geo_ch, [
            CURLOPT_URL => $geo_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        $geo_response = @curl_exec($geo_ch);
        curl_close($geo_ch);
        
        if ($geo_response) {
            $geo_data = json_decode($geo_response, true);
            if ($geo_data && $geo_data['status'] === 'success') {
                $country = $geo_data['country'] ?? 'Unknown';
                $city = $geo_data['city'] ?? null;
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    return [
        'status' => 'live',
        'ip' => $data['origin'],
        'country' => $country,
        'city' => $city
    ];
}

// Log function
function logMessage($message) {
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

// Main execution
logMessage("=== Starting Daily Proxy Check ===");

$data = loadProxies();

if (empty($data['proxies'])) {
    logMessage("No proxies to check. Exiting.");
    exit(0);
}

$settings = $data['settings'] ?? ['auto_check_enabled' => true, 'check_interval_hours' => 24, 'remove_dead_auto' => true];

if (!$settings['auto_check_enabled']) {
    logMessage("Auto check is disabled. Exiting.");
    exit(0);
}

logMessage("Checking " . count($data['proxies']) . " proxies...");

$checked = 0;
$live = 0;
$dead = 0;

foreach ($data['proxies'] as &$proxy) {
    logMessage("Checking: " . $proxy['proxy']);
    
    $checkResult = checkProxy($proxy['proxy']);
    $proxy['status'] = $checkResult['status'];
    $proxy['last_check'] = date('Y-m-d H:i:s');
    $proxy['check_count'] = ($proxy['check_count'] ?? 0) + 1;
    
    if ($checkResult['status'] === 'live') {
        $proxy['ip'] = $checkResult['ip'] ?? $proxy['ip'];
        $proxy['country'] = $checkResult['country'] ?? $proxy['country'];
        $proxy['city'] = $checkResult['city'] ?? $proxy['city'];
        $live++;
        logMessage("✓ LIVE - IP: " . ($proxy['ip'] ?? 'N/A') . ", Country: " . ($proxy['country'] ?? 'Unknown'));
    } else {
        $dead++;
        logMessage("✗ DEAD - Error: " . ($checkResult['error'] ?? 'Unknown'));
    }
    
    $checked++;
    
    // Small delay to avoid rate limiting
    usleep(500000); // 0.5 seconds
}

logMessage("\n=== Check Summary ===");
logMessage("Total Checked: $checked");
logMessage("Live: $live");
logMessage("Dead: $dead");

// Remove dead proxies if configured
if ($settings['remove_dead_auto'] && $dead > 0) {
    $before = count($data['proxies']);
    $data['proxies'] = array_values(array_filter($data['proxies'], fn($p) => $p['status'] === 'live'));
    $removed = $before - count($data['proxies']);
    logMessage("Removed $removed dead proxies automatically");
}

// Update stats
updateStats($data);
$data['stats']['last_check'] = date('Y-m-d H:i:s');

// Save results
if (saveProxies($data)) {
    logMessage("Results saved successfully");
} else {
    logMessage("ERROR: Failed to save results");
    exit(1);
}

logMessage("=== Daily Proxy Check Completed ===");
exit(0);
?>
