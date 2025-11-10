<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/proxy_service.php';

header('Content-Type: application/json');

try {
    initSecureSession();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'dead', 'error' => 'Authentication required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $proxy = $input['proxy'] ?? '';

    if (empty($proxy)) {
        echo json_encode(['status' => 'dead', 'error' => 'No proxy provided']);
        exit;
    }

    $result = ProxyService::test($proxy);
    echo json_encode($result);
} catch (Throwable $e) {
    logError('Proxy check failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['status' => 'dead', 'error' => 'Unexpected server error']);
}
