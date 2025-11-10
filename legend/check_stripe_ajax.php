<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

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

    // Build Python command
    $pythonCmd = 'python3 ' . escapeshellarg(__DIR__ . '/stripe_auth_checker.py') . ' ' . 
                 escapeshellarg($site) . ' ' . 
                 escapeshellarg($card);
    
    if (!empty($proxy)) {
        $pythonCmd .= ' ' . escapeshellarg($proxy);
    }

    // Execute Python script with timeout
    $startTime = microtime(true);
    $output = [];
    $returnVar = 0;
    
    // Set timeout to 60 seconds
    exec($pythonCmd . ' 2>&1', $output, $returnVar);
    
    $endTime = microtime(true);
    $responseTime = round($endTime - $startTime, 2);

    // Parse output (last line should be JSON)
    $outputStr = implode("\n", $output);
    $lastLine = trim(end($output));
    
    // Try to parse JSON from last line
    $result = @json_decode($lastLine, true);
    
    if (!$result || !is_array($result)) {
        // If JSON parsing failed, create error response
        $result = [
            'success' => false,
            'status' => 'ERROR',
            'message' => 'Failed to parse checker response: ' . substr($outputStr, 0, 200),
            'account_email' => null,
            'pm_id' => null
        ];
    }

    // Get card info using BIN lookup
    $cardInfo = null;
    try {
        $cardParts = explode('|', $card);
        $ccNumber = $cardParts[0];
        
        // Call Python BIN lookup
        $binCmd = 'python3 ' . escapeshellarg(__DIR__ . '/bin_lookup.py') . ' ' . escapeshellarg($ccNumber);
        exec($binCmd . ' 2>&1', $binOutput, $binReturnVar);
        
        $binJson = trim(end($binOutput));
        $cardInfo = @json_decode($binJson, true);
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
