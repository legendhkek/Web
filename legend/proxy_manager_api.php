<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $proxy = trim($_POST['proxy'] ?? '');
            if (empty($proxy)) {
                echo json_encode(['success' => false, 'message' => 'Proxy is required']);
                exit;
            }
            
            $parts = explode(':', $proxy);
            if (count($parts) !== 4) {
                echo json_encode(['success' => false, 'message' => 'Invalid format. Use: host:port:user:pass']);
                exit;
            }
            
            // Test proxy first
            require_once 'check_proxy.php';
            $testResult = testProxyConnection($proxy);
            
            if ($testResult['status'] === 'live') {
                $added = $db->addProxy($userId, $proxy, 'live');
                if ($added) {
                    $db->updateProxyStatus(
                        $db->getUserProxies($userId)[0]['_id']->__toString(),
                        'live',
                        $testResult['country'] ?? null,
                        $testResult['ip'] ?? null,
                        $testResult['response_time'] ?? null
                    );
                    echo json_encode([
                        'success' => true,
                        'message' => 'Proxy added successfully',
                        'proxy' => [
                            'status' => 'live',
                            'country' => $testResult['country'] ?? 'Unknown',
                            'ip' => $testResult['ip'] ?? 'N/A'
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Proxy already exists']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Proxy is dead: ' . ($testResult['error'] ?? 'Unknown error')]);
            }
            break;
            
        case 'bulk_add':
            $proxiesText = trim($_POST['proxies'] ?? '');
            if (empty($proxiesText)) {
                echo json_encode(['success' => false, 'message' => 'Proxies are required']);
                exit;
            }
            
            $proxyLines = array_filter(array_map('trim', explode("\n", $proxiesText)));
            $result = $db->bulkAddProxies($userId, $proxyLines);
            
            echo json_encode([
                'success' => true,
                'message' => "Added {$result['added']} proxies, {$result['failed']} failed",
                'added' => $result['added'],
                'failed' => $result['failed']
            ]);
            break;
            
        case 'check':
            $proxyId = $_POST['proxy_id'] ?? '';
            if (empty($proxyId)) {
                echo json_encode(['success' => false, 'message' => 'Proxy ID is required']);
                exit;
            }
            
            $proxies = $db->getUserProxies($userId);
            $proxy = null;
            foreach ($proxies as $p) {
                if ($p['_id']->__toString() === $proxyId) {
                    $proxy = $p;
                    break;
                }
            }
            
            if (!$proxy) {
                echo json_encode(['success' => false, 'message' => 'Proxy not found']);
                exit;
            }
            
            require_once 'check_proxy.php';
            $testResult = testProxyConnection($proxy['proxy_string']);
            
            $db->updateProxyStatus(
                $proxyId,
                $testResult['status'],
                $testResult['country'] ?? null,
                $testResult['ip'] ?? null,
                $testResult['response_time'] ?? null
            );
            
            echo json_encode([
                'success' => true,
                'status' => $testResult['status'],
                'country' => $testResult['country'] ?? 'Unknown',
                'ip' => $testResult['ip'] ?? 'N/A',
                'response_time' => $testResult['response_time'] ?? null,
                'error' => $testResult['error'] ?? null
            ]);
            break;
            
        case 'delete':
            $proxyId = $_POST['proxy_id'] ?? '';
            if (empty($proxyId)) {
                echo json_encode(['success' => false, 'message' => 'Proxy ID is required']);
                exit;
            }
            
            $deleted = $db->deleteProxy($proxyId, $userId);
            if ($deleted) {
                echo json_encode(['success' => true, 'message' => 'Proxy deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete proxy']);
            }
            break;
            
        case 'list':
            $status = $_GET['status'] ?? null;
            $proxies = $db->getUserProxies($userId, $status);
            
            $formatted = [];
            foreach ($proxies as $proxy) {
                $formatted[] = [
                    'id' => $proxy['_id']->__toString(),
                    'proxy_string' => $proxy['proxy_string'],
                    'host' => $proxy['host'],
                    'port' => $proxy['port'],
                    'status' => $proxy['status'] ?? 'pending',
                    'country' => $proxy['country'] ?? null,
                    'ip' => $proxy['ip'] ?? null,
                    'response_time' => $proxy['response_time'] ?? null,
                    'last_checked' => $proxy['last_checked'] ? 
                        (is_object($proxy['last_checked']) && method_exists($proxy['last_checked'], 'toDateTime') ?
                            $proxy['last_checked']->toDateTime()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s')) : null,
                    'created_at' => $proxy['created_at'] ? 
                        (is_object($proxy['created_at']) && method_exists($proxy['created_at'], 'toDateTime') ?
                            $proxy['created_at']->toDateTime()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s')) : null
                ];
            }
            
            echo json_encode(['success' => true, 'proxies' => $formatted]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function testProxyConnection($proxy) {
    $parts = explode(':', $proxy);
    if (count($parts) !== 4) {
        return ['status' => 'dead', 'error' => 'Invalid format'];
    }
    
    [$host, $port, $user, $pass] = $parts;
    $test_url = 'http://httpbin.org/ip';
    
    $startTime = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PROXY, "$host:$port");
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        return [
            'status' => 'dead',
            'error' => $curl_error ?: "HTTP $http_code",
            'response_time' => $responseTime
        ];
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['origin'])) {
        return ['status' => 'dead', 'error' => 'Invalid response', 'response_time' => $responseTime];
    }
    
    // Get country info
    $country = 'Unknown';
    try {
        $geo_url = "http://ip-api.com/json/{$data['origin']}";
        $geo_ch = curl_init();
        curl_setopt($geo_ch, CURLOPT_URL, $geo_url);
        curl_setopt($geo_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($geo_ch, CURLOPT_TIMEOUT, 5);
        $geo_response = curl_exec($geo_ch);
        curl_close($geo_ch);
        
        $geo_data = json_decode($geo_response, true);
        if ($geo_data && isset($geo_data['country'])) {
            $country = $geo_data['country'];
        }
    } catch (Exception $e) {
        // Ignore geo lookup errors
    }
    
    return [
        'status' => 'live',
        'ip' => $data['origin'],
        'country' => $country,
        'response_time' => $responseTime
    ];
}
?>
