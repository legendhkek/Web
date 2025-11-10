<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../utils.php';

header('Content-Type: application/json');

initSecureSession();

$telegramId = $_SESSION['user_id'] ?? $_SESSION['telegram_id'] ?? null;
if (!$telegramId) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Authentication required']);
    exit;
}

$db = Database::getInstance();

$rawInput = file_get_contents('php://input');
$decoded = json_decode($rawInput, true);

// Support form-encoded submissions as fallback
if (!is_array($decoded)) {
    $decoded = $_POST ?? [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $decoded['action'] ?? null;

try {
    switch (strtoupper($method)) {
        case 'GET':
            handleGet($db, $telegramId);
            break;
        case 'POST':
            handlePost($db, $telegramId, $action, $decoded);
            break;
        case 'DELETE':
            handleDelete($db, $telegramId, $decoded);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    logError('Proxy API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Internal server error'
    ]);
}

/* --------------------------------------------------------------------------
 | Handlers
 --------------------------------------------------------------------------*/

function handleGet(Database $db, int $telegramId): void {
    $statusFilter = $_GET['status'] ?? null;
    $filters = [];
    if (!empty($statusFilter)) {
        $filters['status'] = array_filter(array_map('trim', explode(',', (string)$statusFilter)));
    }

    $proxies = $db->getUserProxies($telegramId, $filters);

    echo json_encode([
        'error' => false,
        'proxies' => $proxies
    ]);
}

function handlePost(Database $db, int $telegramId, ?string $action, array $payload): void {
    $action = $action ? strtolower($action) : 'add';

    switch ($action) {
        case 'add':
            $proxy = trim((string)($payload['proxy'] ?? ''));
            $label = $payload['label'] ?? null;
            $autoTest = !empty($payload['auto_test']);

            $result = $db->addUserProxy($telegramId, $proxy, $label);

            if ($result['status'] === 'error') {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => $result['message']]);
                return;
            }

            $proxyData = $result['proxy'];
            if ($autoTest && isset($proxyData['proxy'])) {
                $test = testProxyConnection($proxyData['proxy']);
                $meta = [
                    'latency_ms' => $test['latency_ms'] ?? null,
                    'ip_address' => $test['ip'] ?? null,
                    'country' => $test['country'] ?? null,
                    'last_error' => $test['error'] ?? null
                ];
                $update = $db->updateProxyStatus($telegramId, $proxyData['id'], $test['status'], $meta);
                if ($update['status'] === 'updated') {
                    $proxyData = $update['proxy'];
                }
            }

            echo json_encode([
                'error' => false,
                'status' => $result['status'],
                'proxy' => $proxyData
            ]);
            break;

        case 'bulk_add':
            $items = $payload['proxies'] ?? [];
            if (is_string($items)) {
                $items = preg_split('/\r\n|\r|\n/', $items);
            }
            if (!is_array($items)) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Invalid proxies payload']);
                return;
            }

            $labelPrefix = $payload['label_prefix'] ?? null;
            $summary = $db->addUserProxiesBulk($telegramId, $items, $labelPrefix);

            echo json_encode([
                'error' => false,
                'summary' => $summary
            ]);
            break;

        case 'test':
            $proxyId = $payload['proxy_id'] ?? $payload['id'] ?? null;
            $rawProxy = trim((string)($payload['proxy'] ?? ''));

            if (!$proxyId && $rawProxy === '') {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Proxy id or value is required']);
                return;
            }

            if ($proxyId) {
                $existing = $db->getProxyById($telegramId, $proxyId);
                if (!$existing) {
                    http_response_code(404);
                    echo json_encode(['error' => true, 'message' => 'Proxy not found']);
                    return;
                }
                $rawProxy = $existing['proxy'];
            }

            $testResult = testProxyConnection($rawProxy);

            if ($proxyId) {
                $metadata = [
                    'latency_ms' => $testResult['latency_ms'] ?? null,
                    'ip_address' => $testResult['ip'] ?? null,
                    'country' => $testResult['country'] ?? null,
                    'last_error' => $testResult['error'] ?? null
                ];
                $update = $db->updateProxyStatus($telegramId, $proxyId, $testResult['status'], $metadata);
                if ($update['status'] === 'updated') {
                    $testResult['proxy'] = $update['proxy'];
                }
            }

            echo json_encode([
                'error' => false,
                'result' => $testResult
            ]);
            break;

        case 'bulk_test':
            $ids = $payload['ids'] ?? [];
            if (!is_array($ids)) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Invalid ids payload']);
                return;
            }

            $results = [];
            foreach ($ids as $id) {
                $proxy = $db->getProxyById($telegramId, $id);
                if (!$proxy) {
                    $results[] = [
                        'id' => $id,
                        'error' => true,
                        'message' => 'Proxy not found'
                    ];
                    continue;
                }

                $testResult = testProxyConnection($proxy['proxy']);
                $metadata = [
                    'latency_ms' => $testResult['latency_ms'] ?? null,
                    'ip_address' => $testResult['ip'] ?? null,
                    'country' => $testResult['country'] ?? null,
                    'last_error' => $testResult['error'] ?? null
                ];
                $update = $db->updateProxyStatus($telegramId, $proxy['id'], $testResult['status'], $metadata);
                if ($update['status'] === 'updated') {
                    $testResult['proxy'] = $update['proxy'];
                }
                $testResult['id'] = $proxy['id'];
                $results[] = $testResult;
            }

            echo json_encode([
                'error' => false,
                'results' => $results
            ]);
            break;

        case 'rename':
            $proxyId = $payload['proxy_id'] ?? $payload['id'] ?? null;
            $label = trim((string)($payload['label'] ?? ''));

            if (!$proxyId || $label === '') {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Proxy id and label are required']);
                return;
            }

            $existing = $db->getProxyById($telegramId, $proxyId);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['error' => true, 'message' => 'Proxy not found']);
                return;
            }

            $result = $db->addUserProxy($telegramId, $existing['proxy'], $label);
            echo json_encode([
                'error' => false,
                'proxy' => $result['proxy'],
                'status' => $result['status']
            ]);
            break;

        case 'record_usage':
            $proxyId = $payload['proxy_id'] ?? $payload['id'] ?? null;
            if (!$proxyId) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Proxy id required']);
                return;
            }

            $success = !empty($payload['success']);
            $latencyMs = isset($payload['latency_ms']) ? (float)$payload['latency_ms'] : null;

            $db->recordProxyUsage($telegramId, $proxyId, $success, $latencyMs);

            echo json_encode([
                'error' => false,
                'recorded' => true
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'Unsupported action']);
    }
}

function handleDelete(Database $db, int $telegramId, array $payload): void {
    $ids = $payload['ids'] ?? $payload['id'] ?? null;
    if ($ids === null) {
        // Allow query string fallback
        if (isset($_GET['id'])) {
            $ids = [$_GET['id']];
        } elseif (isset($_GET['ids'])) {
            $ids = explode(',', $_GET['ids']);
        }
    }

    if ($ids === null) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'No proxies specified']);
        return;
    }

    if (!is_array($ids)) {
        $ids = [$ids];
    }

    $deleted = $db->deleteUserProxies($telegramId, $ids);

    echo json_encode([
        'error' => false,
        'deleted' => $deleted
    ]);
}

/* --------------------------------------------------------------------------
 | Helpers
 --------------------------------------------------------------------------*/

function testProxyConnection(string $proxyString, int $timeout = 12): array {
    $proxyString = trim($proxyString);
    if ($proxyString === '') {
        return [
            'status' => 'dead',
            'error' => 'Proxy is empty'
        ];
    }

    $parts = explode(':', $proxyString);
    if (count($parts) < 2) {
        return [
            'status' => 'dead',
            'error' => 'Invalid proxy format'
        ];
    }

    [$host, $port] = $parts;
    $user = $parts[2] ?? null;
    $pass = $parts[3] ?? null;

    if (!function_exists('curl_init')) {
        return [
            'status' => 'dead',
            'error' => 'cURL extension is required'
        ];
    }

    $testUrl = 'https://api.ipify.org?format=json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_PROXY, "{$host}:{$port}");
    if ($user !== null && $pass !== null) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$user}:{$pass}");
    }
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $start = microtime(true);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $latencyMs = round((microtime(true) - $start) * 1000, 2);

    if ($curlError) {
        return [
            'status' => 'dead',
            'error' => $curlError,
            'latency_ms' => $latencyMs
        ];
    }

    if ($httpCode < 200 || $httpCode >= 400) {
        return [
            'status' => 'dead',
            'error' => "HTTP {$httpCode}",
            'latency_ms' => $latencyMs
        ];
    }

    $data = json_decode($response, true);
    if (!isset($data['ip'])) {
        return [
            'status' => 'dead',
            'error' => 'Invalid response',
            'latency_ms' => $latencyMs
        ];
    }

    $country = null;
    try {
        $geoResponse = @file_get_contents("http://ip-api.com/json/{$data['ip']}?fields=status,country");
        if ($geoResponse !== false) {
            $geo = json_decode($geoResponse, true);
            if (($geo['status'] ?? null) === 'success') {
                $country = $geo['country'] ?? null;
            }
        }
    } catch (Exception $e) {
        // Ignore geo lookup errors
    }

    return [
        'status' => 'live',
        'ip' => $data['ip'],
        'country' => $country,
        'latency_ms' => $latencyMs
    ];
}
