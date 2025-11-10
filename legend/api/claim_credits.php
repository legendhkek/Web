<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../database.php';
require_once '../owner_logger.php';

header('Content-Type: application/json');
$nonce = setSecurityHeaders();

// Rate limiting check
function checkRateLimit($userId) {
    $cacheKey = "claim_attempt_{$userId}";
    
    // Check if APCu is available, otherwise use file-based fallback
    if (function_exists('apcu_fetch')) {
        $attempts = apcu_fetch($cacheKey, $success);
        
        if (!$success) {
            $attempts = 0;
        }
        
        if ($attempts >= 5) {
            return false; // Too many attempts
        }
        
        apcu_store($cacheKey, $attempts + 1, 300); // 5 minutes
        return true;
    } else {
        // File-based fallback when APCu is not available
        $tempDir = sys_get_temp_dir();
        $cacheFile = $tempDir . DIRECTORY_SEPARATOR . "claim_rate_{$userId}.tmp";
        
        $attempts = 0;
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && $data['expires'] > time()) {
                $attempts = $data['attempts'] ?? 0;
            } else {
                // Expired, remove file
                unlink($cacheFile);
            }
        }
        
        if ($attempts >= 5) {
            return false; // Too many attempts
        }
        
        // Store new attempt count
        file_put_contents($cacheFile, json_encode([
            'attempts' => $attempts + 1,
            'expires' => time() + 300 // 5 minutes
        ]));
        
        return true;
    }
}

try {
    $userId = TelegramAuth::requireAuth();
    
    // Check rate limiting
    if (!checkRateLimit($userId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Too many claim attempts. Please wait 5 minutes before trying again.',
            'error_code' => 'RATE_LIMITED'
        ]);
        exit();
    }
    
    $db = Database::getInstance();
    
    // Get user data for additional checks
    $user = $db->getUserByTelegramId($userId);
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found. Please login again.',
            'error_code' => 'USER_NOT_FOUND'
        ]);
        exit();
    }
    
    // Check if user account is active
    if ($user['status'] !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'Your account is not active. Please contact support.',
            'error_code' => 'ACCOUNT_INACTIVE'
        ]);
        exit();
    }
    
    // Check if user can claim credits today
    $canClaim = $db->canClaimDailyCredits($userId);
    
    if (!$canClaim) {
    // Compute next claim time for UX (midnight next day)
    $nextClaimTime = strtotime('+1 day', strtotime('today'));
    $timeUntilNext = $nextClaimTime ? max(0, $nextClaimTime - time()) : 0;
    $timeHint = $timeUntilNext ? gmdate('H\h i\m', $timeUntilNext) : 'tomorrow';
        
        echo json_encode([
            'success' => false,
            'message' => "You have already claimed your daily credits. Next claim available in {$timeHint}.",
            'error_code' => 'ALREADY_CLAIMED',
            'next_claim_time' => $nextClaimTime
        ]);
        exit();
    }
    
    // Calculate credit amount based on user role
    $creditAmount = AppConfig::DAILY_CREDIT_AMOUNT;
    if ($user['role'] === 'premium') {
        $creditAmount = AppConfig::DAILY_CREDIT_AMOUNT * 2; // Premium users get double
    } elseif ($user['role'] === 'admin' || $user['role'] === 'owner') {
        $creditAmount = AppConfig::DAILY_CREDIT_AMOUNT * 3; // Admin/Owner get triple
    }
    
    // Claim the credits with transaction safety
    // Claim the credits (supports optional amount override)
    if (method_exists($db, 'claimDailyCredits')) {
        $result = $db->claimDailyCredits($userId, $creditAmount);
    } else {
        $result = false;
    }
    
    if ($result) {
        // Log the successful claim
        // Log via tool usage (generic activity log)
        if (method_exists($db, 'logToolUsage')) {
            $db->logToolUsage($userId, 'daily_credit_claim', [
                'usage_count' => 1,
                'credits_used' => 0,
                'amount_awarded' => $creditAmount,
                'user_role' => $user['role']
            ]);
        }
        
        // Clear rate limit on successful claim
        if (function_exists('apcu_delete')) {
            apcu_delete("claim_attempt_{$userId}");
        } else {
            // File-based fallback cleanup
            $tempDir = sys_get_temp_dir();
            $cacheFile = $tempDir . DIRECTORY_SEPARATOR . "claim_rate_{$userId}.tmp";
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
        
        // Send owner notification for credit claim
        try {
            $ownerLogger = new OwnerLogger();
            $ownerLogger->sendUserActivity(
                $user,
                'Daily Credits Claimed',
                "Claimed {$creditAmount} credits. New balance: " . (($user['credits'] ?? 0) + $creditAmount)
            );
        } catch (Exception $e) {
            error_log("Owner logging failed: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Daily credits claimed successfully! You received {$creditAmount} credits.",
            'credits_awarded' => $creditAmount,
            'new_balance' => ($user['credits'] ?? 0) + $creditAmount,
            'bonus_applied' => $user['role'] !== 'free' ? true : false,
            'next_claim_time' => strtotime('+1 day', strtotime('today'))
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to claim credits due to a database error. Please try again.',
            'error_code' => 'DATABASE_ERROR'
        ]);
    }
    
} catch (Exception $e) {
    logError('Credit claim error: ' . $e->getMessage() . ' - User: ' . ($userId ?? 'unknown'));
    
    // Different error messages based on exception type
    $errorMessage = 'System error occurred. Please try again later.';
    $errorCode = 'SYSTEM_ERROR';
    
    if (strpos($e->getMessage(), 'auth') !== false) {
        $errorMessage = 'Authentication failed. Please login again.';
        $errorCode = 'AUTH_ERROR';
    } elseif (strpos($e->getMessage(), 'database') !== false) {
        $errorMessage = 'Database connection error. Please try again in a few moments.';
        $errorCode = 'DB_CONNECTION_ERROR';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error_code' => $errorCode
    ]);
}
?>
