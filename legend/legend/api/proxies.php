<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../proxy_service.php';

header('Content-Type: application/json');

initSecureSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

/**
 * Normalize proxy documents for JSON responses.
 *
 * @param array|MongoDB\Model\BSONDocument $proxy
 * @return array
 */
function normalizeProxyDocument($proxy): array
{
    if (class_exists('\MongoDB\Model\BSONDocument') && $proxy instanceof \MongoDB\Model\BSONDocument) {
        $proxy = $proxy->getArrayCopy();
    }

    $normalized = $proxy;
    if (isset($normalized['_id'])) {
        $normalized['id'] = (string) $normalized['_id'];
        unset($normalized['_id']);
    }

    foreach (['created_at', 'updated_at', 'last_checked_at'] as $key) {
        if (!isset($normalized[$key])) {
            continue;
        }

        $value = $normalized[$key];

        if ($value instanceof MongoDB\BSON\UTCDateTime) {
            $normalized[$key] = $value->toDateTime()->format(DATE_ATOM);
        } elseif (is_numeric($value)) {
            // Assume UNIX timestamp (seconds)
            $normalized[$key] = date(DATE_ATOM, (int) $value);
        }
    }

    if (isset($normalized['proxy'])) {
        $normalized['masked_proxy'] = ProxyService::maskProxy($normalized['proxy']);
    }

    return $normalized;
}

/**
 * Extract proxies from the request payload (array or newline separated string).
 *
 * @param mixed $input
 * @return array<string>
 */
function extractProxyList($input): array
{
    if (is_array($input)) {
        return array_filter(array_map('trim', $input), fn($line) => $line !== '');
    }

    if (is_string($input)) {
        $lines = preg_split('/\r\n|\n|\r/', $input);
        return array_filter(array_map('trim', $lines), fn($line) => $line !== '');
    }

    return [];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $proxies = $db->getUserProxies($userId);
        $list = array_map('normalizeProxyDocument', $proxies ?? []);
        echo json_encode([
            'success' => true,
            'proxies' => $list,
        ]);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = strtolower($payload['action'] ?? 'add');

    switch ($action) {
        case 'add':
            $proxyInput = $payload['proxies'] ?? ($payload['proxy'] ?? []);
            $autoTest = (bool)($payload['autoTest'] ?? false);
            $label = isset($payload['label']) ? trim($payload['label']) : null;

            $proxies = extractProxyList($proxyInput);
            if (empty($proxies)) {
                throw new InvalidArgumentException('No proxies provided');
            }

            $added = [];
            $errors = [];

            foreach ($proxies as $proxyString) {
                $validation = ProxyService::validate($proxyString);
                if (!$validation['valid']) {
                    $errors[] = [
                        'proxy' => $proxyString,
                        'error' => $validation['error'] ?? 'Invalid proxy',
                    ];
                    continue;
                }

                $proxyPayload = [
                    'proxy' => $validation['proxy'],
                    'label' => $label,
                ];

                if ($autoTest) {
                    $testResult = ProxyService::test($validation['proxy']);
                    $proxyPayload['status'] = $testResult['status'];
                    $proxyPayload['country'] = $testResult['country'] ?? null;
                    $proxyPayload['latency_ms'] = $testResult['latency_ms'] ?? null;
                    $proxyPayload['last_checked_at'] = time() * 1000;
                    if ($testResult['status'] === 'dead') {
                        $proxyPayload['error'] = $testResult['error'] ?? null;
                    }
                } else {
                    $proxyPayload['status'] = 'unknown';
                }

                $id = $db->addUserProxy($userId, $proxyPayload);
                $added[] = [
                    'id' => $id,
                    'proxy' => $validation['proxy'],
                    'label' => $label,
                    'status' => $proxyPayload['status'],
                    'country' => $proxyPayload['country'] ?? null,
                    'latency_ms' => $proxyPayload['latency_ms'] ?? null,
                    'last_checked_at' => isset($proxyPayload['last_checked_at'])
                        ? date(DATE_ATOM, (int)($proxyPayload['last_checked_at'] / 1000))
                        : null,
                    'masked_proxy' => ProxyService::maskProxy($validation['proxy']),
                    'error' => $proxyPayload['error'] ?? null,
                ];
            }

            echo json_encode([
                'success' => true,
                'added' => $added,
                'errors' => $errors,
            ]);
            break;

        case 'remove':
            $ids = $payload['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                throw new InvalidArgumentException('No proxy IDs provided');
            }

            $deleted = $db->removeUserProxies($userId, $ids);
            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
            ]);
            break;

        case 'test':
            $id = $payload['id'] ?? null;
            $proxyString = $payload['proxy'] ?? null;

            if ($id === null && $proxyString === null) {
                throw new InvalidArgumentException('Proxy ID or string required');
            }

            if ($proxyString === null) {
                $proxies = $db->getUserProxies($userId) ?? [];
                $matched = null;
                foreach ($proxies as $proxy) {
                    $normalized = normalizeProxyDocument($proxy);
                    if (($normalized['id'] ?? null) === $id) {
                        $matched = $normalized;
                        break;
                    }
                }

                if ($matched === null) {
                    throw new RuntimeException('Proxy not found');
                }
                $proxyString = $matched['proxy'] ?? $matched['masked_proxy'] ?? null;
                if ($proxyString === null && isset($matched['masked_proxy'])) {
                    throw new RuntimeException('Cannot test masked proxy');
                }
            }

            $testResult = ProxyService::test($proxyString);
            $statusUpdate = [
                'status' => $testResult['status'],
                'country' => $testResult['country'] ?? null,
                'latency_ms' => $testResult['latency_ms'] ?? null,
                'last_checked_at' => time() * 1000,
            ];
            if ($id !== null) {
                $db->updateUserProxyStatus($userId, $id, $statusUpdate);
            }

            echo json_encode([
                'success' => true,
                'result' => $testResult,
            ]);
            break;

        default:
            throw new InvalidArgumentException('Unsupported action');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    logError('Proxy API failure', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
