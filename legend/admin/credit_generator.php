<?php
require_once 'admin_header.php';
require_once 'admin_utils.php';
require_once '../database.php';

// Initialize database
$db = Database::getInstance();

// Get current user for display
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_credits':
            $count = (int)($_POST['count'] ?? 10);
            $credit_amount = (int)($_POST['credit_amount'] ?? 100);
            $expiry_days = (int)($_POST['expiry_days'] ?? 30);
            $type = $_POST['type'] ?? 'standard';
            
            $generated_codes = [];
            for ($i = 0; $i < $count; $i++) {
                $code = generateCreditCode($credit_amount, $expiry_days, $type);
                $generated_codes[] = $code;
            }
            
            $successMessage = "Generated {$count} credit codes worth {$credit_amount} credits each!";
            break;
            
        case 'redeem_code':
            $code = $_POST['code'] ?? '';
            $telegram_id = $_POST['telegram_id'] ?? '';
            
            if (!empty($code) && !empty($telegram_id)) {
                $result = redeemCreditCode($code, $telegram_id);
                if ($result['success']) {
                    $successMessage = "Credit code redeemed successfully! User {$telegram_id} received {$result['credits']} credits";
                } else {
                    $errorMessage = $result['error'];
                }
            } else {
                $errorMessage = "Please provide both code and Telegram ID";
            }
            break;
            
        case 'delete_credit_code':
            $code_id = $_POST['code_id'] ?? '';
            if (!empty($code_id)) {
                $result = deleteCreditCode($code_id);
                if ($result) {
                    $successMessage = "Credit code deleted successfully";
                } else {
                    $errorMessage = "Failed to delete credit code";
                }
            }
            break;
            
        case 'bulk_credit_gift':
            $telegram_ids = array_filter(explode(',', $_POST['telegram_ids'] ?? ''));
            $credit_amount = (int)($_POST['credit_amount'] ?? 50);
            $message = $_POST['message'] ?? '';
            
            if (!empty($telegram_ids) && $credit_amount > 0) {
                $success_count = 0;
                foreach ($telegram_ids as $telegram_id) {
                    $telegram_id = trim($telegram_id);
                    if (is_numeric($telegram_id)) {
                        $result = giftCredits($telegram_id, $credit_amount, $message);
                        if ($result) $success_count++;
                    }
                }
                $successMessage = "Gifted {$credit_amount} credits to {$success_count} users successfully!";
            } else {
                $errorMessage = "Please provide valid Telegram IDs and credit amount";
            }
            break;
    }
}

// Get existing credit codes
$credit_codes = getCreditCodes();
$used_credit_codes = getUsedCreditCodes();

/**
 * Generate random string
 */
function generateRandomString($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Format date helper function
 */
function formatDate($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * Generate a credit code
 */
function generateCreditCode($credit_amount, $expiry_days, $type) {
    global $db;
    
    $code = 'CREDIT-' . strtoupper(generateRandomString(8));
    $expires_at = time() + ($expiry_days * 24 * 60 * 60);
    
    $data = [
        'code' => $code,
        'credit_amount' => $credit_amount,
        'type' => $type,
        'expiry_days' => $expiry_days,
        'expires_at' => $expires_at,
        'created_at' => time(),
        'created_by' => $_SESSION['telegram_id'] ?? 'admin',
        'status' => 'active'
    ];
    
    // Save to database
    try {
        if (method_exists($db, 'insertCreditCode')) {
            $db->insertCreditCode($data);
        } else {
            // Fallback - save to file
            $codes_file = __DIR__ . '/../data/credit_codes.json';
            $existing_codes = [];
            if (file_exists($codes_file)) {
                $existing_codes = json_decode(file_get_contents($codes_file), true) ?? [];
            }
            $existing_codes[] = $data;
            
            // Ensure data directory exists
            if (!is_dir(__DIR__ . '/../data')) {
                mkdir(__DIR__ . '/../data', 0755, true);
            }
            
            file_put_contents($codes_file, json_encode($existing_codes, JSON_PRETTY_PRINT));
        }
    } catch (Exception $e) {
        error_log("Failed to save credit code: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Redeem a credit code
 */
function redeemCreditCode($code, $telegram_id) {
    global $db;
    
    // Check if code exists and is valid
    $code_data = getCreditCodeByCode($code);
    if (!$code_data) {
        return ['success' => false, 'error' => 'Invalid credit code'];
    }
    
    if ($code_data['status'] !== 'active') {
        return ['success' => false, 'error' => 'Code is not active'];
    }
    
    if ($code_data['expires_at'] < time()) {
        return ['success' => false, 'error' => 'Code has expired'];
    }
    
    // Check if user exists
    $user = $db->getUserByTelegramId($telegram_id);
    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }
    
    // Check if code was already used by this user
    if (isCreditCodeUsedByUser($code, $telegram_id)) {
        return ['success' => false, 'error' => 'Code already used by this user'];
    }
    
    // Add credits to user
    $current_credits = $user['credits'] ?? 0;
    $new_credits = $current_credits + $code_data['credit_amount'];
    
    if (method_exists($db, 'updateUserCredits')) {
        $db->updateUserCredits($telegram_id, $new_credits);
    }
    
    // Mark code as used
    markCreditCodeAsUsed($code, $telegram_id);
    
    // Send notification to user
    if (function_exists('sendTelegramMessage')) {
        $message = "🎉 Congratulations! You've redeemed a credit code!\n\n";
        $message .= "💰 <b>{$code_data['credit_amount']} credits</b> have been added to your account.\n";
        $message .= "💳 Your new balance: <b>{$new_credits} credits</b>";
        sendTelegramMessage($telegram_id, $message);
    }
    
    return [
        'success' => true, 
        'user' => $user, 
        'code' => $code_data, 
        'credits' => $code_data['credit_amount']
    ];
}

/**
 * Gift credits to a user
 */
function giftCredits($telegram_id, $credit_amount, $message = '') {
    global $db;
    
    // Check if user exists
    $user = $db->getUserByTelegramId($telegram_id);
    if (!$user) {
        return false;
    }
    
    // Add credits to user
    $current_credits = $user['credits'] ?? 0;
    $new_credits = $current_credits + $credit_amount;
    
    if (method_exists($db, 'updateUserCredits')) {
        $db->updateUserCredits($telegram_id, $new_credits);
    }
    
    // Send notification to user
    if (function_exists('sendTelegramMessage')) {
        $gift_message = "🎁 You've received a gift from the admin!\n\n";
        $gift_message .= "💰 <b>{$credit_amount} credits</b> have been added to your account.\n";
        $gift_message .= "💳 Your new balance: <b>{$new_credits} credits</b>";
        
        if (!empty($message)) {
            $gift_message .= "\n\n💬 Message: {$message}";
        }
        
        sendTelegramMessage($telegram_id, $gift_message);
    }
    
    return true;
}

/**
 * Get credit codes from database
 */
function getCreditCodes() {
    global $db;
    
    try {
        if (method_exists($db, 'getCreditCodes')) {
            return $db->getCreditCodes();
        } else {
            // Fallback - load from file
            $codes_file = __DIR__ . '/../data/credit_codes.json';
            if (file_exists($codes_file)) {
                $codes = json_decode(file_get_contents($codes_file), true) ?? [];
                return array_filter($codes, function($code) {
                    return $code['status'] === 'active';
                });
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get credit codes: " . $e->getMessage());
    }
    
    return [];
}

/**
 * Get used credit codes
 */
function getUsedCreditCodes() {
    global $db;
    
    try {
        if (method_exists($db, 'getUsedCreditCodes')) {
            return $db->getUsedCreditCodes();
        } else {
            // Fallback - load from file
            $used_codes_file = __DIR__ . '/../data/used_credit_codes.json';
            if (file_exists($used_codes_file)) {
                return json_decode(file_get_contents($used_codes_file), true) ?? [];
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get used credit codes: " . $e->getMessage());
    }
    
    return [];
}

/**
 * Get credit code by code string
 */
function getCreditCodeByCode($code) {
    $codes = getCreditCodes();
    foreach ($codes as $credit_code) {
        if ($credit_code['code'] === $code) {
            return $credit_code;
        }
    }
    return null;
}

/**
 * Check if credit code was used by specific user
 */
function isCreditCodeUsedByUser($code, $telegram_id) {
    $used_codes = getUsedCreditCodes();
    foreach ($used_codes as $used_code) {
        if ($used_code['code'] === $code && $used_code['used_by'] == $telegram_id) {
            return true;
        }
    }
    return false;
}

/**
 * Mark credit code as used
 */
function markCreditCodeAsUsed($code, $telegram_id) {
    try {
        // Add to used codes
        $used_codes_file = __DIR__ . '/../data/used_credit_codes.json';
        $used_codes = [];
        if (file_exists($used_codes_file)) {
            $used_codes = json_decode(file_get_contents($used_codes_file), true) ?? [];
        }
        
        $used_codes[] = [
            'code' => $code,
            'used_by' => $telegram_id,
            'used_at' => time()
        ];
        
        // Ensure data directory exists
        if (!is_dir(__DIR__ . '/../data')) {
            mkdir(__DIR__ . '/../data', 0755, true);
        }
        
        file_put_contents($used_codes_file, json_encode($used_codes, JSON_PRETTY_PRINT));
        
        // Remove from active codes
        $codes_file = __DIR__ . '/../data/credit_codes.json';
        if (file_exists($codes_file)) {
            $codes = json_decode(file_get_contents($codes_file), true) ?? [];
            foreach ($codes as &$credit_code) {
                if ($credit_code['code'] === $code) {
                    $credit_code['status'] = 'used';
                    break;
                }
            }
            file_put_contents($codes_file, json_encode($codes, JSON_PRETTY_PRINT));
        }
    } catch (Exception $e) {
        error_log("Failed to mark credit code as used: " . $e->getMessage());
    }
}

/**
 * Delete credit code
 */
function deleteCreditCode($code_id) {
    try {
        $codes_file = __DIR__ . '/../data/credit_codes.json';
        if (file_exists($codes_file)) {
            $codes = json_decode(file_get_contents($codes_file), true) ?? [];
            $codes = array_filter($codes, function($code) use ($code_id) {
                return ($code['id'] ?? $code['code']) !== $code_id;
            });
            file_put_contents($codes_file, json_encode(array_values($codes), JSON_PRETTY_PRINT));
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to delete credit code: " . $e->getMessage());
    }
    return false;
}
?>

<!-- Rest of your HTML code remains the same -->
