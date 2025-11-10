<?php
/**
 * Security Enhancement Script
 * Implements additional security measures for the Ex Chk platform
 */

class SecurityEnhancements {
    
    /**
     * Rate limiting implementation
     */
    public static function checkRateLimit($identifier, $maxAttempts = 10, $timeWindow = 300) {
        $rateLimitFile = '../data/rate_limits.json';
        $rateLimits = [];
        
        if (file_exists($rateLimitFile)) {
            $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?? [];
        }
        
        $currentTime = time();
        $key = md5($identifier . $_SERVER['REMOTE_ADDR']);
        
        // Clean old entries
        foreach ($rateLimits as $k => $data) {
            if ($currentTime - $data['first_attempt'] > $timeWindow) {
                unset($rateLimits[$k]);
            }
        }
        
        if (!isset($rateLimits[$key])) {
            $rateLimits[$key] = [
                'attempts' => 1,
                'first_attempt' => $currentTime
            ];
        } else {
            $rateLimits[$key]['attempts']++;
        }
        
        file_put_contents($rateLimitFile, json_encode($rateLimits));
        
        return $rateLimits[$key]['attempts'] <= $maxAttempts;
    }
    
    /**
     * Enhanced input validation
     */
    public static function validateInput($input, $type = 'string', $maxLength = 1000) {
        if (empty($input)) return false;
        
        switch ($type) {
            case 'card':
                return preg_match('/^[0-9|]+$/', $input) && strlen($input) <= 50;
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) && strlen($input) <= 500;
            case 'proxy':
                return preg_match('/^[a-zA-Z0-9.-]+:[0-9]+:[a-zA-Z0-9_.-]+:[a-zA-Z0-9_.-]+$/', $input);
            case 'telegram_id':
                return is_numeric($input) && $input > 0;
            default:
                return strlen($input) <= $maxLength && !preg_match('/[<>"\']/', $input);
        }
    }
    
    /**
     * CSRF token validation
     */
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event' => $event,
            'details' => $details
        ];
        
        $logFile = '../data/security_logs.json';
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?? [];
        }
        
        $logs[] = $logEntry;
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }
}

/**
 * Enhanced error handler
 */
function secureErrorHandler($errno, $errstr, $errfile, $errline) {
    // Don't expose file paths in production
    $sanitizedFile = basename($errfile);
    
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    
    // Log error securely
    error_log("[$errorType] $errstr in $sanitizedFile:$errline");
    
    // Don't display errors in production
    if (ini_get('display_errors')) {
        echo "An error occurred. Please check the logs.";
    }
    
    return true;
}

// Set custom error handler
set_error_handler('secureErrorHandler');
?>
