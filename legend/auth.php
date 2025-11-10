<?php
require_once 'config.php';
require_once 'database.php';

// Production error handling - disable display, enable logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

class TelegramAuth {
    
    public static function verifyTelegramAuth($authData) {
        // Log the received data for debugging
        logError('Telegram auth data received', $authData);
        
        if (!isset($authData['hash']) || empty($authData['hash'])) {
            logError('No hash provided in auth data');
            return false;
        }
        
        $checkHash = $authData['hash'];
        unset($authData['hash']);
        
        // Remove empty values and sort
        $dataCheckArr = [];
        foreach ($authData as $key => $value) {
            if ($value !== '' && $value !== null) {
                $dataCheckArr[] = $key . '=' . $value;
            }
        }
        sort($dataCheckArr);
        
        $dataCheckString = implode("\n", $dataCheckArr);
        logError('Data check string', ['string' => $dataCheckString]);
        
        // Create secret key from bot token (primary)
        $calculatedHashes = [];
        $primarySecretKey = hash('sha256', TelegramConfig::BOT_TOKEN, true);
        $primaryHash = hash_hmac('sha256', $dataCheckString, $primarySecretKey);
        $calculatedHashes[] = $primaryHash;
        
        // Try alternate tokens from environment or dynamic config to avoid lockouts
        $altTokens = [];
        $envToken = getenv('TELEGRAM_BOT_TOKEN');
        if (!empty($envToken) && $envToken !== TelegramConfig::BOT_TOKEN) {
            $altTokens[] = $envToken;
        }
        // Allow dynamic override via system_config.json if present
        $siteTokenKeys = ['telegram_bot_token', 'bot_token_override', 'alt_bot_token'];
        foreach ($siteTokenKeys as $k) {
            $tok = SiteConfig::get($k);
            if (!empty($tok) && !in_array($tok, $altTokens, true) && $tok !== TelegramConfig::BOT_TOKEN) {
                $altTokens[] = $tok;
            }
        }
        
        foreach ($altTokens as $idx => $tok) {
            $sk = hash('sha256', $tok, true);
            $h = hash_hmac('sha256', $dataCheckString, $sk);
            $calculatedHashes[] = $h;
        }
        
        // Compare (case-insensitive) against any calculated hash
        $receivedHashLower = strtolower($checkHash);
        $match = false;
        foreach ($calculatedHashes as $h) {
            if (hash_equals($h, $checkHash) || hash_equals($h, $receivedHashLower)) {
                $match = true;
                break;
            }
        }
        
        logError('Hash comparison', ['received' => $checkHash, 'matches_any' => $match, 'alts_tried' => count($calculatedHashes)]);
        
        if (!$match) {
            // If explicitly allowed via config, permit weak auth (DEV ONLY)
            $allowWeak = (bool) SiteConfig::get('allow_insecure_telegram_auth', false);
            if ($allowWeak) {
                logError('Hash verification failed, but allow_insecure_telegram_auth=true — proceeding (DEV MODE)');
            } else {
                logError('Hash verification failed');
                return false;
            }
        }
        
        // Check if auth data is not too old (within 86400 seconds = 24 hours)
        if (!isset($authData['auth_date']) || (time() - $authData['auth_date']) > 86400) {
            logError('Auth data too old or missing auth_date');
            return false;
        }
        
        logError('Telegram auth verification successful');
        return true;
    }
    
    public static function handleTelegramLogin($authData) {
        logError('Handling Telegram login', $authData);
        
        if (!self::verifyTelegramAuth($authData)) {
            // Provide richer diagnostics if enabled, always log diagnosis
            $diagnosis = self::diagnoseAuthFailure();
            if ($diagnosis) {
                logError('Auth failure diagnosis: ' . $diagnosis);
            }
            $debug = (bool) SiteConfig::get('debug_auth', false);
            if ($debug && $diagnosis) {
                return ['success' => false, 'error' => $diagnosis];
            }
            return ['success' => false, 'error' => 'Invalid authentication data. Please try again.'];
        }
        
        try {
            // Initialize secure session
            initSecureSession();
            
            $db = Database::getInstance();
            $user = $db->getUserByTelegramId($authData['id']);
            
            $isNewUser = false;
            if ($user) {
                // Check if user is banned
                if (isset($user['status']) && $user['status'] === 'banned') {
                    logError('Banned user login attempt', ['telegram_id' => $authData['id']]);
                    return ['success' => false, 'error' => 'Your account has been suspended. Please contact support.'];
                }
                
                logError('Updating existing user login', ['telegram_id' => $authData['id']]);
                $db->updateUserLastLogin($authData['id']);
            } else {
                logError('Creating new user', ['telegram_id' => $authData['id']]);
                $user = $db->createUser($authData);
                $isNewUser = true;
            }
            
            // Store user data in session
            $_SESSION['user_id'] = $user['telegram_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['credits'] = $user['credits'];
            $_SESSION['last_login'] = time();
            
            // Send notifications
            if ($isNewUser && SiteConfig::get('notify_register', true)) {
                self::sendRegistrationNotification($user);
            }
            if (SiteConfig::get('notify_login', true)) {
                self::sendLoginNotification($user);
            }
            
            // Set session data
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Update presence
            $db->updatePresence($authData['id']);
            
            logError('Login successful', ['user_id' => $authData['id']]);
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            logError('Login error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['success' => false, 'error' => 'System error occurred. Please try again later.'];
        }
    }
    
    // Attempt to diagnose common misconfigurations when auth fails
    private static function diagnoseAuthFailure() {
        $messages = [];
        
        // 1) Check BOT_NAME vs BOT_TOKEN via Telegram getMe
        try {
            $token = TelegramConfig::BOT_TOKEN;
            $url = "https://api.telegram.org/bot{$token}/getMe";
            $ctx = stream_context_create([ 'http' => [ 'timeout' => 5 ] ]);
            $resp = @file_get_contents($url, false, $ctx);
            if ($resp !== false) {
                $data = json_decode($resp, true);
                if (isset($data['ok']) && $data['ok'] && isset($data['result']['username'])) {
                    $apiUsername = strtolower($data['result']['username']);
                    $cfgUsername = strtolower(str_replace('@', '', TelegramConfig::BOT_NAME));
                    if ($apiUsername !== $cfgUsername) {
                        $messages[] = "Configured BOT_NAME (@" . TelegramConfig::BOT_NAME . ") does not match the bot username from BOT_TOKEN (@{$apiUsername}). Update BOT_NAME or BOT_TOKEN so they match.";
                    }
                } else {
                    $messages[] = 'Unable to verify bot token via getMe (Telegram API did not return ok).';
                }
            } else {
                $messages[] = 'Network error contacting Telegram API (getMe). Check outbound network connectivity.';
            }
        } catch (\Throwable $e) {
            $messages[] = 'Exception during getMe check: ' . $e->getMessage();
        }
        
        // 2) Check for override tokens present in config
        $overrideKeys = ['telegram_bot_token', 'bot_token_override', 'alt_bot_token'];
        $foundOverride = false;
        foreach ($overrideKeys as $k) {
            $tok = SiteConfig::get($k);
            if (!empty($tok)) { $foundOverride = true; break; }
        }
        if ($foundOverride) {
            $messages[] = 'Detected bot token override in system_config.json. Ensure the override token belongs to the same bot as BOT_NAME.';
        }
        
        // 3) Provide actionable hint
        if (empty($messages)) {
            return 'Authentication failed. Possible bot token mismatch. Verify that BOT_NAME matches the bot for the configured token or set TELEGRAM_BOT_TOKEN env var.';
        }
        
        return implode(' ', $messages);
    }
    
    private static function sendLoginNotification($userData) {
        require_once 'utils.php';
        $message = "<b>New Login</b>\n\n";
        $message .= "👤 <b>User:</b> " . formatUserName($userData) . "\n";
        $message .= "🆔 <b>ID:</b> " . $userData['telegram_id'] . "\n";
        $message .= "👑 <b>Role:</b> " . htmlspecialchars(ucfirst($userData['role'])) . "\n";
        $message .= "💰 <b>Credits:</b> " . formatNumber($userData['credits']) . "\n";
        $message .= "🕐 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
        $message .= "🌐 <b>Domain:</b> " . htmlspecialchars(AppConfig::DOMAIN);
    sendTelegramHtml($message);
    }

    private static function sendRegistrationNotification($userData) {
        require_once 'utils.php';
        $message = "🎉 <b>New Registration</b>\n\n";
        $message .= "👤 <b>User:</b> " . formatUserName($userData) . "\n";
        $message .= "🆔 <b>ID:</b> " . $userData['telegram_id'] . "\n";
        $message .= "👑 <b>Role:</b> " . htmlspecialchars(ucfirst($userData['role'])) . "\n";
        $message .= "💰 <b>Starting Credits:</b> " . formatNumber($userData['credits']) . "\n";
        $message .= "🕐 <b>Time:</b> " . date('Y-m-d H:i:s');
    sendTelegramHtml($message);
    }
    
    public static function requireAuth() {
        initSecureSession();
        
        if (!isset($_SESSION['user_id'])) {
            // Store the current page for redirect after login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: login.php');
            exit();
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > AppConfig::SESSION_TIMEOUT) {
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return $_SESSION['user_id'];
    }
    
    public static function getCurrentUser() {
        initSecureSession();
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            $db = Database::getInstance();
            return $db->getUserByTelegramId($_SESSION['user_id']);
        } catch (Exception $e) {
            logError('Error getting current user: ' . $e->getMessage());
            return null;
        }
    }
    
    public static function logout() {
        initSecureSession();
        
        // Clear all session data
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
        header('Location: login.php?logged_out=1');
        exit();
    }
    
    public static function checkMembership($telegramId) {
        // This would typically check if user is member of required Telegram channel
        // For now, we'll assume all authenticated users have membership
        return true;
    }
    
    // CSRF Protection
    public static function generateCSRFToken() {
        initSecureSession();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        initSecureSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Rate limiting
    public static function checkRateLimit($action, $limit = 5, $window = 300) {
        initSecureSession();
        $key = 'rate_limit_' . $action;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Clean old entries
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        if (count($_SESSION[$key]) >= $limit) {
            return false;
        }
        
        $_SESSION[$key][] = $now;
        return true;
    }
}
?>
