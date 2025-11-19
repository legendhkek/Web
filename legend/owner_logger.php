<?php
/**
 * Owner Logger - Telegram notification system for owner monitoring
 * Sends detailed logs to owner about all bot activities
 */

require_once 'config.php';

class OwnerLogger {
    private $owner_chat_ids = [6658831303]; // Owner chat ID
    private $bot_token;
    
    public function __construct() {
        $this->bot_token = TelegramConfig::BOT_TOKEN;
    }
    
    /**
     * Send notification to all owners
     */
    private function sendToOwners($message, $parse_mode = 'HTML') {
        $results = [];
        
        foreach ($this->owner_chat_ids as $chat_id) {
            $url = "https://api.telegram.org/bot" . $this->bot_token . "/sendMessage";
            
            $data = [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => $parse_mode,
                'disable_web_page_preview' => true
            ];
            
            $success = false;
            $response = '';
            
            // Try cURL extension first
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $success = $httpCode === 200;
            }
            // Try file_get_contents with HTTPS
            elseif (extension_loaded('openssl')) {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/x-www-form-urlencoded',
                        'content' => http_build_query($data),
                        'timeout' => 10
                    ]
                ]);
                $response = @file_get_contents($url, false, $context);
                $success = !empty($response);
            }
            // Windows curl command fallback
            else {
                // Properly escape all parameters for shell execution
                $escapedUrl = escapeshellarg($url);
                $escapedData = escapeshellarg('chat_id=' . $chat_id . '&text=' . urlencode($message) . '&parse_mode=' . $parse_mode);
                $curlCmd = 'curl -s -X POST ' . $escapedUrl . ' ' .
                          '-H "Content-Type: application/x-www-form-urlencoded" ' .
                          '-d ' . $escapedData;
                
                $response = shell_exec($curlCmd);
                $success = !empty($response) && strpos($response, '"ok":true') !== false;
            }
            
            $results[] = ['chat_id' => $chat_id, 'success' => $success, 'response' => $response];
        }
        
        return $results;
    }
    
    /**
     * Format user information for messages
     */
    private function formatUserInfo($user) {
        $info = "👤 <b>User:</b> " . htmlspecialchars($user['display_name'] ?? $user['first_name'] ?? 'Unknown') . "\n";
        $info .= "🆔 <b>ID:</b> <code>" . ($user['telegram_id'] ?? 'N/A') . "</code>\n";
        
        if (!empty($user['username'])) {
            $info .= "👨‍💼 <b>Username:</b> @" . htmlspecialchars($user['username']) . "\n";
        }
        
        if (isset($user['credits'])) {
            $info .= "� <b>Credits:</b> " . number_format($user['credits']) . "\n";
        }
        
        if (isset($user['role'])) {
            $info .= "👑 <b>Role:</b> " . strtoupper($user['role']) . "\n";
        }
        
        return $info;
    }
    
    /**
     * Send login notification to owner
     */
    public function sendLoginNotification($user) {
        $message = "� <b>USER LOGIN</b>\n\n";
        $message .= $this->formatUserInfo($user);
        $message .= "🌐 <b>IP:</b> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
        $message .= "� <b>User Agent:</b> " . substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 50) . "\n";
        $message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
        $message .= "🔗 <b>Profile:</b> <a href='tg://user?id=" . ($user['telegram_id'] ?? '') . "'>View Profile</a>";
        
        return $this->sendToOwners($message);
    }
    
    /**
     * Send system alert to owner
     */
    public function sendSystemAlert($alert_type, $details) {
        $message = "� <b>SYSTEM ALERT</b>\n\n";
        $message .= "⚠️ <b>Type:</b> " . htmlspecialchars($alert_type) . "\n";
        $message .= "📝 <b>Details:</b> " . htmlspecialchars($details) . "\n";
        $message .= "🌐 <b>Server:</b> " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
        $message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s');
        
        return $this->sendToOwners($message);
    }
    
    /**
     * Send user activity notification
     */
    public function sendUserActivity($user, $activity_type, $details) {
        $message = "👤 <b>USER ACTIVITY</b>\n\n";
        $message .= $this->formatUserInfo($user);
        $message .= "⚡ <b>Activity:</b> " . htmlspecialchars($activity_type) . "\n";
        $message .= "� <b>Details:</b> " . htmlspecialchars($details) . "\n";
        $message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s');
        
        return $this->sendToOwners($message);
    }
    
    /**
     * Send admin alert notification
     */
    public function sendAdminAlert($admin_user, $action, $details) {
        $message = "👑 <b>ADMIN ACTION</b>\n\n";
        $message .= "<b>Admin:</b> " . htmlspecialchars($admin_user['display_name'] ?? $admin_user['first_name'] ?? 'Unknown') . "\n";
        $message .= "🆔 <b>Admin ID:</b> <code>" . ($admin_user['telegram_id'] ?? 'N/A') . "</code>\n";
        $message .= "⚡ <b>Action:</b> " . htmlspecialchars($action) . "\n";
        $message .= "� <b>Details:</b> " . htmlspecialchars($details) . "\n";
        $message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s');
        
        return $this->sendToOwners($message);
    }
    
    /**
     * Send daily activity report to owner
     */
    public function sendDailyReport($stats) {
        $message = "� <b>Daily Activity Report</b>\n\n";
        $message .= "📅 <b>Date:</b> " . date('Y-m-d') . "\n\n";
        $message .= "� <b>New Users:</b> " . ($stats['new_users'] ?? 0) . "\n";
        $message .= "� <b>Total Logins:</b> " . ($stats['total_logins'] ?? 0) . "\n";
        $message .= "💳 <b>Card Checks:</b> " . ($stats['card_checks'] ?? 0) . "\n";
        $message .= "✅ <b>Successful Checks:</b> " . ($stats['successful_checks'] ?? 0) . "\n";
        $message .= "� <b>Credits Used:</b> " . ($stats['credits_used'] ?? 0) . "\n";
        $message .= "� <b>System Errors:</b> " . ($stats['errors'] ?? 0) . "\n";
        
        return $this->sendToOwners($message);
    }
    
    /**
     * Custom error handler that sends critical errors to owner
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        // Only log critical errors to avoid spam
        if ($errno === E_ERROR || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR || $errno === E_USER_ERROR) {
            try {
                $logger = new self();
                $logger->sendSystemAlert(
                    'Critical Error',
                    "File: " . basename($errfile) . ":{$errline}\nError: {$errstr}"
                );
            } catch (Exception $e) {
                error_log("Owner logging failed in error handler: " . $e->getMessage());
            }
        }
        
        // Continue with normal error handling
        return false;
    }
    
    /**
     * Exception handler that sends exceptions to owner
     */
    public static function exceptionHandler($exception) {
        try {
            $logger = new self();
            $logger->sendSystemAlert(
                'Uncaught Exception',
                "File: " . basename($exception->getFile()) . ":{$exception->getLine()}\nException: " . $exception->getMessage()
            );
        } catch (Exception $e) {
            error_log("Owner logging failed in exception handler: " . $e->getMessage());
        }
    }
    
    /**
     * Monitor system health and send alerts if needed
     */
    public function checkSystemHealth() {
        $alerts = [];
        
        // Check disk space
        $diskFree = disk_free_space(__DIR__);
        $diskTotal = disk_total_space(__DIR__);
        if ($diskFree && $diskTotal) {
            $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
            
            if ($diskUsedPercent > 90) {
                $alerts[] = "⚠️ Disk usage is at " . round($diskUsedPercent, 1) . "%";
            }
        }
        
        // Check if database is accessible
        try {
            require_once 'database.php';
            $db = Database::getInstance();
            $db->getAllUsers(1, 0); // Test query
        } catch (Exception $e) {
            $alerts[] = "❌ Database connection failed: " . $e->getMessage();
        }
        
        // Check if external API is accessible
        try {
            $testUrl = 'http://legend.sonugamingop.tech/autosh.php?cc=4111111111111111|12|2025|123&site=https://google.com';
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $response = @file_get_contents($testUrl, false, $context);
            if (!$response) {
                $alerts[] = "🌐 External API is not responding";
            }
        } catch (Exception $e) {
            $alerts[] = "🌐 External API check failed: " . $e->getMessage();
        }
        
        // Send alerts if any
        if (!empty($alerts)) {
            $this->sendSystemAlert('System Health Alert', implode("\n", $alerts));
        }
        
        return empty($alerts);
    }
    
    /**
     * Send startup notification
     */
    public function sendStartupNotification() {
        $message = "� <b>BOT STARTED</b>\n\n";
        $message .= "🤖 <b>Bot:</b> " . TelegramConfig::BOT_NAME . "\n";
        $message .= "🌐 <b>Domain:</b> " . AppConfig::DOMAIN . "\n";
        $message .= "� <b>PHP Version:</b> " . PHP_VERSION . "\n";
        $message .= "📅 <b>Started:</b> " . date('Y-m-d H:i:s') . "\n";
        $message .= "🔧 <b>Status:</b> Online ✅";
        
        return $this->sendToOwners($message);
    }
}
?>