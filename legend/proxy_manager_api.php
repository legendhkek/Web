<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

header('Content-Type: application/json');
$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Check if user is owner
$is_owner = in_array((int)$userId, AppConfig::OWNER_IDS);

if (!$is_owner) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only owners can manage proxies.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
    
    // Validate host and port
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
        $geo_url = "http://ip-api.com/json/{$data['origin']}?fields=status,country,city,isp";
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

// Handle actions
switch ($action) {
    case 'add_single':
        $proxy = trim($_POST['proxy'] ?? '');
        if (empty($proxy)) {
            echo json_encode(['success' => false, 'message' => 'Proxy is required']);
            exit;
        }
        
        // Load existing proxies
        $data = loadProxies();
        
        // Check if already exists
        foreach ($data['proxies'] as $p) {
            if ($p['proxy'] === $proxy) {
                echo json_encode(['success' => false, 'message' => 'Proxy already exists']);
                exit;
            }
        }
        
        // Check proxy
        $checkResult = checkProxy($proxy);
        
        if ($checkResult['status'] === 'dead') {
            echo json_encode(['success' => false, 'message' => 'Proxy is dead: ' . ($checkResult['error'] ?? 'Unknown error')]);
            exit;
        }
        
        // Add proxy
        $data['proxies'][] = [
            'id' => uniqid(),
            'proxy' => $proxy,
            'status' => 'live',
            'ip' => $checkResult['ip'] ?? null,
            'country' => $checkResult['country'] ?? 'Unknown',
            'city' => $checkResult['city'] ?? null,
            'added_at' => date('Y-m-d H:i:s'),
            'last_check' => date('Y-m-d H:i:s'),
            'check_count' => 1
        ];
        
        updateStats($data);
        saveProxies($data);
        
        echo json_encode(['success' => true, 'message' => 'Proxy added successfully', 'proxy' => end($data['proxies'])]);
        break;
        
    case 'add_mass':
        $proxies = $_POST['proxies'] ?? '';
        if (empty($proxies)) {
            echo json_encode(['success' => false, 'message' => 'No proxies provided']);
            exit;
        }
        
        $proxyList = array_filter(array_map('trim', explode("\n", $proxies)));
        $data = loadProxies();
        
        $added = 0;
        $failed = 0;
        $duplicate = 0;
        $results = [];
        
        foreach ($proxyList as $proxy) {
            // Check if already exists
            $exists = false;
            foreach ($data['proxies'] as $p) {
                if ($p['proxy'] === $proxy) {
                    $exists = true;
                    $duplicate++;
                    break;
                }
            }
            
            if ($exists) {
                $results[] = ['proxy' => $proxy, 'status' => 'duplicate'];
                continue;
            }
            
            // Check proxy
            $checkResult = checkProxy($proxy);
            
            if ($checkResult['status'] === 'dead') {
                $failed++;
                $results[] = ['proxy' => $proxy, 'status' => 'failed', 'error' => $checkResult['error'] ?? 'Unknown'];
                continue;
            }
            
            // Add proxy
            $data['proxies'][] = [
                'id' => uniqid(),
                'proxy' => $proxy,
                'status' => 'live',
                'ip' => $checkResult['ip'] ?? null,
                'country' => $checkResult['country'] ?? 'Unknown',
                'city' => $checkResult['city'] ?? null,
                'added_at' => date('Y-m-d H:i:s'),
                'last_check' => date('Y-m-d H:i:s'),
                'check_count' => 1
            ];
            $added++;
            $results[] = ['proxy' => $proxy, 'status' => 'added'];
        }
        
        updateStats($data);
        saveProxies($data);
        
        echo json_encode([
            'success' => true,
            'message' => "Added: $added, Failed: $failed, Duplicate: $duplicate",
            'stats' => ['added' => $added, 'failed' => $failed, 'duplicate' => $duplicate],
            'results' => $results
        ]);
        break;
        
    case 'check_all':
        $data = loadProxies();
        $checked = 0;
        $live = 0;
        $dead = 0;
        
        foreach ($data['proxies'] as &$proxy) {
            $checkResult = checkProxy($proxy['proxy']);
            $proxy['status'] = $checkResult['status'];
            $proxy['last_check'] = date('Y-m-d H:i:s');
            $proxy['check_count'] = ($proxy['check_count'] ?? 0) + 1;
            
            if ($checkResult['status'] === 'live') {
                $proxy['ip'] = $checkResult['ip'] ?? $proxy['ip'];
                $proxy['country'] = $checkResult['country'] ?? $proxy['country'];
                $proxy['city'] = $checkResult['city'] ?? $proxy['city'];
                $live++;
            } else {
                $dead++;
            }
            $checked++;
        }
        
        updateStats($data);
        $data['stats']['last_check'] = date('Y-m-d H:i:s');
        saveProxies($data);
        
        echo json_encode([
            'success' => true,
            'message' => "Checked: $checked, Live: $live, Dead: $dead",
            'stats' => $data['stats']
        ]);
        break;
        
    case 'remove_dead':
        $data = loadProxies();
        $before = count($data['proxies']);
        $data['proxies'] = array_values(array_filter($data['proxies'], fn($p) => $p['status'] === 'live'));
        $removed = $before - count($data['proxies']);
        
        updateStats($data);
        saveProxies($data);
        
        echo json_encode([
            'success' => true,
            'message' => "Removed $removed dead proxies",
            'removed' => $removed,
            'stats' => $data['stats']
        ]);
        break;
        
    case 'remove_proxy':
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Proxy ID is required']);
            exit;
        }
        
        $data = loadProxies();
        $before = count($data['proxies']);
        $data['proxies'] = array_values(array_filter($data['proxies'], fn($p) => $p['id'] !== $id));
        
        if (count($data['proxies']) === $before) {
            echo json_encode(['success' => false, 'message' => 'Proxy not found']);
            exit;
        }
        
        updateStats($data);
        saveProxies($data);
        
        echo json_encode(['success' => true, 'message' => 'Proxy removed successfully', 'stats' => $data['stats']]);
        break;
        
    case 'get_proxies':
        $data = loadProxies();
        echo json_encode(['success' => true, 'proxies' => $data['proxies'], 'stats' => $data['stats']]);
        break;
        
    case 'get_stats':
        $data = loadProxies();
        echo json_encode(['success' => true, 'stats' => $data['stats']]);
        break;
        
    case 'get_random_proxy':
        $data = loadProxies();
        $liveProxies = array_filter($data['proxies'], fn($p) => $p['status'] === 'live');
        
        if (empty($liveProxies)) {
            echo json_encode(['success' => false, 'message' => 'No live proxies available']);
            exit;
        }
        
        $proxy = $liveProxies[array_rand($liveProxies)];
        echo json_encode(['success' => true, 'proxy' => $proxy['proxy']]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
