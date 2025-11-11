<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';
require_once '../proxy_utils.php';

header('Content-Type: application/json');
setSecurityHeaders();

function normalizeDate($value) {
    if ($value instanceof MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->format(DATE_ATOM);
    }

    if (is_numeric($value) && $value > 0) {
        return date(DATE_ATOM, (int)$value);
    }

    return $value ?: null;
}

function serializeProxy($record) {
    if ($record instanceof MongoDB\Model\BSONDocument) {
        $record = iterator_to_array($record);
    }

    if (!is_array($record)) {
        return null;
    }

    $tags = $record['tags'] ?? [];
    if ($tags instanceof MongoDB\Model\BSONArray) {
        $tags = iterator_to_array($tags);
    }

    $result = [
        'id' => isset($record['_id']) ? (string)$record['_id'] : ($record['id'] ?? null),
        'proxy' => $record['proxy'] ?? null,
        'host' => $record['host'] ?? null,
        'port' => isset($record['port']) ? (int)$record['port'] : null,
        'username' => $record['username'] ?? null,
        'status' => $record['status'] ?? 'unknown',
        'ip' => $record['ip'] ?? null,
        'country' => $record['country'] ?? null,
        'city' => $record['city'] ?? null,
        'latency_ms' => isset($record['latency_ms']) ? (int)$record['latency_ms'] : null,
        'total_checks' => isset($record['total_checks']) ? (int)$record['total_checks'] : 0,
        'live_checks' => isset($record['live_checks']) ? (int)$record['live_checks'] : 0,
        'dead_checks' => isset($record['dead_checks']) ? (int)$record['dead_checks'] : 0,
        'last_check_message' => $record['last_check_message'] ?? null,
        'added_by' => $record['added_by'] ?? null,
        'tags' => $tags
    ];

    $result['last_check_at'] = normalizeDate($record['last_check_at'] ?? null);
    $result['last_seen_live_at'] = normalizeDate($record['last_seen_live_at'] ?? null);
    $result['created_at'] = normalizeDate($record['created_at'] ?? null);
    $result['updated_at'] = normalizeDate($record['updated_at'] ?? null);

    return $result;
}

function parseInput(): array {
    $data = $_POST;
    if (!empty($data)) {
        return $data;
    }

    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }

    return [];
}

try {
    $userId = TelegramAuth::requireAuth();
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $filters = [];
        $options = [];

        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        if (!empty($_GET['limit'])) {
            $options['limit'] = min((int)$_GET['limit'], 500);
        } else {
            $options['limit'] = 200;
        }

        if (!empty($_GET['skip'])) {
            $options['skip'] = (int)$_GET['skip'];
        }

        $proxies = $db->getProxies($filters, $options);
        $serialized = [];
        foreach ($proxies as $record) {
            $normalized = serializeProxy($record);
            if ($normalized) {
                $serialized[] = $normalized;
            }
        }

        $stats = $db->getProxyStats();
        if (isset($stats['last_checked_at'])) {
            $stats['last_checked_at'] = normalizeDate($stats['last_checked_at']);
        }

        echo json_encode([
            'success' => true,
            'proxies' => $serialized,
            'stats' => $stats
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = parseInput();
    $action = $input['action'] ?? null;

    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action is required']);
        exit;
    }

    switch ($action) {
        case 'add_single':
            $proxyString = trim($input['proxy'] ?? '');
            if ($proxyString === '') {
                throw new Exception('Proxy string is required');
            }

            $check = ProxyUtils::check($proxyString);
            if (!$check['success']) {
                echo json_encode([
                    'success' => false,
                    'error' => $check['message'] ?? 'Proxy check failed'
                ]);
                exit;
            }

            $normalized = $check['normalized'];
            $record = $db->saveProxy([
                'proxy' => $normalized['original'],
                'host' => $normalized['host'],
                'port' => $normalized['port'],
                'username' => $normalized['username'],
                'status' => $check['status'],
                'ip' => $check['ip'] ?? null,
                'country' => $check['country'] ?? null,
                'city' => $check['city'] ?? null,
                'latency_ms' => $check['latency_ms'] ?? null,
                'message' => $check['message'] ?? null,
                'added_by' => $userId
            ]);

            echo json_encode([
                'success' => true,
                'proxy' => serializeProxy($record)
            ]);
            exit;

        case 'add_bulk':
            $raw = trim($input['proxies'] ?? '');
            if ($raw === '') {
                throw new Exception('No proxies provided');
            }

            $lines = preg_split('/\r\n|\r|\n/', $raw);
            $seen = [];
            $added = [];
            $failed = [];

            foreach ($lines as $line) {
                $proxyString = trim($line);
                if ($proxyString === '') {
                    continue;
                }
                if (isset($seen[$proxyString])) {
                    continue;
                }
                $seen[$proxyString] = true;

                try {
                    $check = ProxyUtils::check($proxyString);
                    if (!$check['success']) {
                        $failed[] = [
                            'proxy' => $proxyString,
                            'error' => $check['message'] ?? 'Proxy check failed'
                        ];
                        continue;
                    }

                    $normalized = $check['normalized'];
                    $record = $db->saveProxy([
                        'proxy' => $normalized['original'],
                        'host' => $normalized['host'],
                        'port' => $normalized['port'],
                        'username' => $normalized['username'],
                        'status' => $check['status'],
                        'ip' => $check['ip'] ?? null,
                        'country' => $check['country'] ?? null,
                        'city' => $check['city'] ?? null,
                        'latency_ms' => $check['latency_ms'] ?? null,
                        'message' => $check['message'] ?? null,
                        'added_by' => $userId
                    ]);
                    $added[] = serializeProxy($record);
                } catch (Exception $e) {
                    $failed[] = [
                        'proxy' => $proxyString,
                        'error' => $e->getMessage()
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'added_count' => count($added),
                'failed_count' => count($failed),
                'added' => $added,
                'failed' => $failed
            ]);
            exit;

        case 'recheck':
            $proxyId = $input['proxy_id'] ?? null;
            $proxyString = $input['proxy'] ?? null;

            if ($proxyId) {
                $record = $db->getProxyById($proxyId);
            } else {
                $record = null;
            }

            if (!$record && $proxyString) {
                $record = $db->getProxyByString($proxyString);
            }

            if (!$record) {
                throw new Exception('Proxy not found');
            }

            $serialized = serializeProxy($record);
            $targetProxy = $serialized['proxy'] ?? null;
            if (!$targetProxy) {
                throw new Exception('Stored proxy is invalid');
            }

            $check = ProxyUtils::check($targetProxy);
            $normalized = $check['normalized'];
            $updated = $db->saveProxy([
                'proxy' => $normalized['original'],
                'host' => $normalized['host'],
                'port' => $normalized['port'],
                'username' => $normalized['username'],
                'status' => $check['status'],
                'ip' => $check['ip'] ?? null,
                'country' => $check['country'] ?? null,
                'city' => $check['city'] ?? null,
                'latency_ms' => $check['latency_ms'] ?? null,
                'message' => $check['message'] ?? null,
                'added_by' => $serialized['added_by'] ?? $userId
            ]);

            echo json_encode([
                'success' => true,
                'proxy' => serializeProxy($updated)
            ]);
            exit;

        case 'remove':
            $proxyId = $input['proxy_id'] ?? ($input['proxy'] ?? null);
            if (!$proxyId) {
                throw new Exception('Proxy identifier required');
            }

            $removed = $db->removeProxyById($proxyId);
            echo json_encode([
                'success' => $removed,
                'removed' => $removed
            ]);
            exit;

        case 'refresh_stale':
            $maxAge = isset($input['max_age']) ? (int)$input['max_age'] : 86400;
            $cleanupAge = isset($input['cleanup_age']) ? (int)$input['cleanup_age'] : 172800;
            $now = time();
            $processed = 0;
            $liveCount = 0;
            $deadCount = 0;

            $options = ['limit' => 500, 'sort' => ['last_check_at' => 1]];
            $batch = $db->getProxies([], $options);

            foreach ($batch as $record) {
                $serialized = serializeProxy($record);
                if (!$serialized) {
                    continue;
                }

                $lastCheckAt = isset($serialized['last_check_at']) ? strtotime($serialized['last_check_at']) : 0;
                if ($lastCheckAt && ($now - $lastCheckAt) < $maxAge) {
                    continue;
                }

                $processed++;
                try {
                    $check = ProxyUtils::check($serialized['proxy']);
                    $normalized = $check['normalized'];
                    $db->saveProxy([
                        'proxy' => $normalized['original'],
                        'host' => $normalized['host'],
                        'port' => $normalized['port'],
                        'username' => $normalized['username'],
                        'status' => $check['status'],
                        'ip' => $check['ip'] ?? null,
                        'country' => $check['country'] ?? null,
                        'city' => $check['city'] ?? null,
                        'latency_ms' => $check['latency_ms'] ?? null,
                        'message' => $check['message'] ?? null,
                        'added_by' => $serialized['added_by'] ?? $userId
                    ]);

                    if ($check['status'] === 'live') {
                        $liveCount++;
                    } else {
                        $deadCount++;
                    }
                } catch (Exception $e) {
                    $deadCount++;
                    $db->saveProxy([
                        'proxy' => $serialized['proxy'],
                        'host' => $serialized['host'],
                        'port' => $serialized['port'],
                        'username' => $serialized['username'],
                        'status' => 'dead',
                        'message' => $e->getMessage(),
                        'added_by' => $serialized['added_by'] ?? $userId
                    ]);
                }
            }

            $removed = $db->removeDeadProxies($cleanupAge);
            $stats = $db->getProxyStats();
            if (isset($stats['last_checked_at'])) {
                $stats['last_checked_at'] = normalizeDate($stats['last_checked_at']);
            }

            echo json_encode([
                'success' => true,
                'processed' => $processed,
                'live' => $liveCount,
                'dead' => $deadCount,
                'removed_dead' => $removed,
                'stats' => $stats
            ]);
            exit;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
