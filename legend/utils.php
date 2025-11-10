<?php
// Utility functions for date/time handling and common operations

/**
 * Safe date formatting that handles both MongoDB UTCDateTime and Unix timestamps
 */
function formatDate($dateValue, $format = 'M d, Y') {
    if (empty($dateValue)) {
        return date($format);
    }
    
    // Handle MongoDB UTCDateTime object
    if (is_object($dateValue) && method_exists($dateValue, 'toDateTime')) {
        return $dateValue->toDateTime()->format($format);
    }
    
    // Handle Unix timestamp (integer)
    if (is_numeric($dateValue)) {
        return date($format, $dateValue);
    }
    
    // Handle string dates
    if (is_string($dateValue)) {
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date($format, $timestamp);
        }
    }
    
    // Fallback to current date
    return date($format);
}

/**
 * Safe time ago formatting
 */
function timeAgo($dateValue) {
    $timestamp = null;
    
    // Handle MongoDB UTCDateTime object
    if (is_object($dateValue) && method_exists($dateValue, 'toDateTime')) {
        $timestamp = $dateValue->toDateTime()->getTimestamp();
    }
    // Handle Unix timestamp
    elseif (is_numeric($dateValue)) {
        $timestamp = $dateValue;
    }
    // Handle string dates
    elseif (is_string($dateValue)) {
        $timestamp = strtotime($dateValue);
    }
    
    if ($timestamp === null || $timestamp === false) {
        return 'Unknown';
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($dateValue, 'M d, Y');
    }
}

/**
 * Send Telegram notification (supports cURL with fallback)
 */
function sendTelegramNotification($message, $chatId = null, $parseMode = 'Markdown') {
    if ($chatId === null) {
            $chatId = SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID);
    }

    $url = "https://api.telegram.org/bot" . TelegramConfig::BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => $parseMode
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($result === false) {
            error_log('Telegram send error (cURL): ' . $err);
        }
        return $result;
    }

    // Fallback to file_get_contents
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context = stream_context_create($options);
    $res = @file_get_contents($url, false, $context);
    if ($res === false) {
        error_log('Telegram send error (stream)');
    }
    return $res;
}

/**
 * Convenience: send HTML-formatted Telegram notification
 */
function sendTelegramHtml($message, $chatId = null) {
    return sendTelegramNotification($message, $chatId, 'HTML');
}

/**
 * Format user display name safely
 */
function formatUserName($user) {
    if (isset($user['display_name']) && !empty($user['display_name'])) {
        return htmlspecialchars($user['display_name']);
    }
    
    if (isset($user['username']) && !empty($user['username'])) {
        return '@' . htmlspecialchars($user['username']);
    }
    
    return 'User #' . ($user['telegram_id'] ?? 'Unknown');
}

/**
 * Safe number formatting
 */
function formatNumber($number) {
    if (!is_numeric($number)) {
        return '0';
    }
    return number_format($number);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input string to prevent XSS
 */
function sanitizeInput($input, $type = 'string') {
    if (is_null($input)) {
        return null;
    }
    
    switch ($type) {
        case 'string':
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        case 'int':
            return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return (float)filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        case 'alphanumeric':
            return preg_replace('/[^a-zA-Z0-9]/', '', $input);
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate and sanitize array of inputs
 */
function sanitizeArray($data, $rules = []) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        $type = isset($rules[$key]) ? $rules[$key] : 'string';
        
        if (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value, $rules);
        } else {
            $sanitized[$key] = sanitizeInput($value, $type);
        }
    }
    
    return $sanitized;
}

/**
 * Validate credit card number format (Luhn algorithm)
 */
function validateCardNumber($cardNumber) {
    $cardNumber = preg_replace('/\D/', '', $cardNumber);
    
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        return false;
    }
    
    // Luhn algorithm
    $sum = 0;
    $numDigits = strlen($cardNumber);
    $parity = $numDigits % 2;
    
    for ($i = 0; $i < $numDigits; $i++) {
        $digit = (int)$cardNumber[$i];
        if ($i % 2 == $parity) {
            $digit *= 2;
        }
        if ($digit > 9) {
            $digit -= 9;
        }
        $sum += $digit;
    }
    
    return ($sum % 10) == 0;
}

/**
 * Validate CVV format
 */
function validateCVV($cvv) {
    $cvv = preg_replace('/\D/', '', $cvv);
    return strlen($cvv) >= 3 && strlen($cvv) <= 4;
}

/**
 * Validate expiration date format (MM/YY or MM/YYYY)
 */
function validateExpiryDate($expiry) {
    $expiry = trim($expiry);
    
    // Match MM/YY or MM/YYYY format
    if (!preg_match('/^(\d{2})\/(\d{2,4})$/', $expiry, $matches)) {
        return false;
    }
    
    $month = (int)$matches[1];
    $year = (int)$matches[2];
    
    // Convert 2-digit year to 4-digit
    if ($year < 100) {
        $year += 2000;
    }
    
    // Validate month
    if ($month < 1 || $month > 12) {
        return false;
    }
    
    // Check if card is expired
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    
    if ($year < $currentYear) {
        return false;
    }
    
    if ($year == $currentYear && $month < $currentMonth) {
        return false;
    }
    
    return true;
}

/**
 * Rate limiting helper
 */
function checkRateLimitAdvanced($key, $maxAttempts = 5, $windowSeconds = 300) {
    initSecureSession();
    
    $rateLimitKey = 'rate_limit_' . $key;
    $now = time();
    
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [];
    }
    
    // Clean old entries
    $_SESSION[$rateLimitKey] = array_filter($_SESSION[$rateLimitKey], function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });
    
    // Check limit
    if (count($_SESSION[$rateLimitKey]) >= $maxAttempts) {
        return false;
    }
    
    // Add current attempt
    $_SESSION[$rateLimitKey][] = $now;
    return true;
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash sensitive data (one-way)
 */
function hashSensitiveData($data) {
    return hash('sha256', $data . AppConfig::DOMAIN);
}

/**
 * Validate proxy format
 */
function validateProxyFormat($proxy) {
    $parts = explode(':', trim($proxy));
    
    if (count($parts) !== 4) {
        return false;
    }
    
    list($host, $port, $user, $pass) = $parts;
    
    // Validate host
    if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) {
        return false;
    }
    
    // Validate port
    $port = (int)$port;
    if ($port < 1 || $port > 65535) {
        return false;
    }
    
    return true;
}

/**
 * Enhanced error logging with context
 */
function logErrorAdvanced($message, $context = [], $level = 'error') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    if (isset($_SESSION['user_id'])) {
        $logEntry['user_id'] = $_SESSION['user_id'];
    }
    
    error_log(json_encode($logEntry));
}

/**
 * Safe JSON encoding with error handling
 */
function safeJsonEncode($data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError('JSON encoding error: ' . json_last_error_msg(), ['data' => $data]);
        return json_encode(['error' => 'Encoding failed']);
    }
    
    return $json;
}

/**
 * Safe JSON decoding with error handling
 */
function safeJsonDecode($json, $assoc = true) {
    $data = json_decode($json, $assoc);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError('JSON decoding error: ' . json_last_error_msg(), ['json' => substr($json, 0, 500)]);
        return null;
    }
    
    return $data;
}
?>
