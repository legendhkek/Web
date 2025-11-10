<?php
// Ex Chk - Configuration File
// MongoDB Atlas Connection & Telegram Bot Configuration

class DatabaseConfig {
    // MongoDB Configuration
    const MONGODB_URI = 'mongodb+srv://sarthakgrid_db_user:pwAyjsdl9FPsBSUS@legend.0rrvdmy.mongodb.net/?appName=legend';
    const DATABASE_NAME = 'legend_db';
    
    // Collections
    const USERS_COLLECTION = 'users';
    const USER_STATS_COLLECTION = 'user_stats';
    const CREDIT_LEDGER_COLLECTION = 'credit_ledger';
    const XCOIN_LEDGER_COLLECTION = 'xcoin_ledger';
    const TOOL_USAGE_COLLECTION = 'tool_usage';
    const PRESENCE_HEARTBEATS_COLLECTION = 'presence_heartbeats';
    const DAILY_CREDIT_CLAIMS_COLLECTION = 'daily_credit_claims';
    const CC_LOGS_COLLECTION = 'cc_logs';
    const USER_PROXIES_COLLECTION = 'user_proxies';
    const ADMIN_ROLES_COLLECTION = 'admin_roles';
    const CREDIT_CODES_COLLECTION = 'credit_codes';
}

class TelegramConfig {
    const BOT_TOKEN = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU'; // Updated bot token for Rocketsdsssdbot
    const CHAT_ID = '-1002854309982';
    const NOTIFICATION_CHAT_ID = '-1002854309982';
    const BOT_NAME = 'WebkeBot'; // Remove @ symbol for widget
    const BOT_SECRET = '7934355076:AAEHirX29ay1Q4m1EuXqXCt-1Tk9DpuLHLU'; // Using bot token as secret for security
}

class SiteConfig {
    private static $config = null;

    private static function loadConfig() {
        if (self::$config === null) {
            $configFile = __DIR__ . '/data/system_config.json';
            if (file_exists($configFile)) {
                self::$config = json_decode(file_get_contents($configFile), true);
            } else {
                self::$config = [];
            }
        }
    }

    public static function get($key, $default = null) {
        self::loadConfig();
        return self::$config[$key] ?? $default;
    }

    public static function save($newConfig) {
        $configFile = __DIR__ . '/data/system_config.json';
        // Preserve existing keys not in the new config
        self::loadConfig();
        $updatedConfig = array_merge(self::$config, $newConfig);
        return file_put_contents($configFile, json_encode($updatedConfig, JSON_PRETTY_PRINT));
    }
}

class AppConfig {
    const DOMAIN = 'https://autoshopify.sonugamingop.tech';
    const CHECKER_API_URL = 'https://redbugxapi.sonugamingop.tech/autosh.php';
    const SESSION_TIMEOUT = 86400; // 24 hours
    const DAILY_CREDIT_AMOUNT = 10; // Fallback
    const MAX_CONCURRENT_CHECKS = 20;
    
    // Role definitions
    const ROLE_FREE = 'free';
    const ROLE_PREMIUM = 'premium';
    const ROLE_VIP = 'vip';
    const ROLE_ADMIN = 'admin';
    const ROLE_OWNER = 'owner';
    
    // Admin and Owner Telegram IDs
    const ADMIN_IDS = []; // Dynamically managed by owner
    const OWNER_IDS = [5652614329]; // @LEGEND_BL
    const OWNER_USERNAME = 'LEGEND_BL';
    
    // Credit costs for tools
    const CARD_CHECK_COST = 1; // Fallback
    const SITE_CHECK_COST = 1; // Fallback
}

// Security Headers
function setSecurityHeaders() {
    // Prevent caching of sensitive pages
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN'); // Allow Telegram widget
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Check if we're using HTTPS (including ngrok proxy)
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    // Only set HSTS if using HTTPS
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Enhanced Content Security Policy for Telegram widget
    $nonce = base64_encode(random_bytes(16));
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'nonce-{$nonce}' https://telegram.org https://oauth.telegram.org https://t.me; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https: blob:; connect-src 'self' https://api.telegram.org https://oauth.telegram.org https://t.me; frame-src https://oauth.telegram.org https://telegram.org https://t.me;");
    
    return $nonce;
}

// Error handling and logging
function logError($message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logEntry .= ' - Context: ' . json_encode($context);
    }
    error_log($logEntry);
}

// Enhanced session management
function initSecureSession() {
    // Only configure session settings if no session is active
    if (session_status() === PHP_SESSION_NONE) {
        // Enhanced session security with error suppression
        if (session_status() === PHP_SESSION_NONE) {
            @ini_set('session.cookie_httponly', 1);
            @ini_set('session.cookie_secure', 0); // Set to 0 for ngrok HTTP support
            @ini_set('session.cookie_samesite', 'Lax');
            @ini_set('session.use_strict_mode', 1);
            @ini_set('session.cookie_lifetime', 86400); // 24 hours for persistence
            @ini_set('session.gc_maxlifetime', 86400);
        }
        ini_set('session.gc_maxlifetime', AppConfig::SESSION_TIMEOUT);
        
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

?>
