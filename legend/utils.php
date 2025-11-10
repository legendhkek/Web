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
 * Perform a Telegram Bot API request with automatic cURL/stream fallback.
 *
 * @param string $method  Telegram API method name (e.g., 'sendMessage')
 * @param array  $params  Parameters to send with the request
 * @param array  $options Additional options: method (HTTP verb), timeout, verify_peer, decode_json
 *
 * @return array Associative array mirroring Telegram's JSON response format plus optional raw_response key.
 */
function performTelegramApiRequest($method, array $params = [], array $options = []) {
    $botToken = TelegramConfig::BOT_TOKEN ?? '';
    if (empty($botToken)) {
        return [
            'ok' => false,
            'description' => 'Bot token not configured'
        ];
    }

    $httpMethod = strtoupper($options['method'] ?? 'POST');
    $timeout = max(1, (int)($options['timeout'] ?? 15));
    $verifyPeer = (bool)($options['verify_peer'] ?? false);
    $decodeJson = $options['decode_json'] ?? true;

    $url = "https://api.telegram.org/bot{$botToken}/{$method}";
    if ($httpMethod === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $rawResponse = false;
    $transportError = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
        if ($httpMethod !== 'GET') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $transportError = curl_error($ch) ?: 'Unknown cURL error';
        }
        curl_close($ch);
    } else {
        $contextOptions = [
            'http' => [
                'method' => $httpMethod,
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'timeout' => $timeout
            ]
        ];
        if ($httpMethod !== 'GET') {
            $contextOptions['http']['content'] = http_build_query($params);
        }
        $context = stream_context_create($contextOptions);
        $rawResponse = @file_get_contents($url, false, $context);
        if ($rawResponse === false) {
            $lastError = error_get_last();
            $transportError = $lastError['message'] ?? 'Unknown HTTP stream error';
        }
    }

    if ($rawResponse === false) {
        if ($transportError) {
            error_log('Telegram API transport error: ' . $transportError);
        }
        return [
            'ok' => false,
            'description' => $transportError ?? 'Telegram API transport error'
        ];
    }

    if (!$decodeJson) {
        return [
            'ok' => true,
            'raw_response' => $rawResponse
        ];
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        error_log('Telegram API unexpected response: ' . $rawResponse);
        return [
            'ok' => false,
            'description' => 'Invalid JSON response from Telegram API',
            'raw_response' => $rawResponse
        ];
    }

    $decoded['raw_response'] = $rawResponse;
    return $decoded;
}

/**
 * Send Telegram notification (supports cURL with fallback)
 */
function sendTelegramNotification($message, $chatId = null, $parseMode = 'Markdown') {
    if ($chatId === null) {
        $chatId = SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID);
    }

    $response = performTelegramApiRequest('sendMessage', [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => $parseMode
    ]);

    if (!($response['ok'] ?? false)) {
        error_log('Telegram send error: ' . ($response['description'] ?? 'Unknown error'));
    }

    return $response;
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
?>
