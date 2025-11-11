<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'proxy_utils.php';

header('Content-Type: application/json');
setSecurityHeaders();

// Check authentication
try {
    $userId = TelegramAuth::requireAuth();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => 'dead', 'error' => 'Authentication required']);
    exit;
}

// Rate limiting
if (!TelegramAuth::checkRateLimit('proxy_check', 10, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'dead', 'error' => 'Too many requests. Please wait a moment.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$proxy = isset($input['proxy']) ? trim($input['proxy']) : '';

if ($proxy === '') {
    echo json_encode(['status' => 'dead', 'error' => 'No proxy provided']);
    exit;
}

try {
    $result = ProxyUtils::check($proxy);
} catch (Exception $e) {
    echo json_encode(['status' => 'dead', 'error' => $e->getMessage()]);
    exit;
}

if (!$result['success']) {
    echo json_encode([
        'status' => 'dead',
        'error' => $result['message'] ?? 'Proxy check failed'
    ]);
    exit;
}

echo json_encode([
    'status' => 'live',
    'ip' => $result['ip'] ?? null,
    'country' => $result['country'] ?? 'Unknown',
    'city' => $result['city'] ?? null,
    'latency_ms' => $result['latency_ms'] ?? null
]);
?>
