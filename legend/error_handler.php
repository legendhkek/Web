<?php
/**
 * Enhanced Error Handler
 * Provides centralized error handling and logging
 */

class ErrorHandler {
    private static $instance = null;
    private static $error_log_file = null;
    private static $max_log_size = 10485760; // 10MB
    
    private function __construct() {
        self::$error_log_file = __DIR__ . '/data/error_log.txt';
        
        // Ensure data directory exists
        $data_dir = __DIR__ . '/data';
        if (!is_dir($data_dir)) {
            @mkdir($data_dir, 0755, true);
        }
        
        // Set error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        // Don't handle errors suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $error_type = $this->getErrorType($errno);
        $message = "[$error_type] $errstr in $errfile on line $errline";
        
        $this->logError($message, [
            'type' => $error_type,
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline
        ]);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception) {
        $message = "Uncaught Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . " on line " . $exception->getLine();
        
        $this->logError($message, [
            'type' => get_class($exception),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Display friendly error page in production
        if (!$this->isDebugMode()) {
            $this->displayErrorPage();
        }
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}";
            
            $this->logError($message, [
                'type' => 'FATAL',
                'errno' => $error['type']
            ]);
            
            if (!$this->isDebugMode()) {
                $this->displayErrorPage();
            }
        }
    }
    
    /**
     * Log error to file
     */
    private function logError($message, $context = []) {
        try {
            // Rotate log if too large
            if (file_exists(self::$error_log_file) && filesize(self::$error_log_file) > self::$max_log_size) {
                $backup = self::$error_log_file . '.' . date('YmdHis') . '.bak';
                @rename(self::$error_log_file, $backup);
                
                // Keep only last 5 backup files
                $backups = glob(dirname(self::$error_log_file) . '/error_log.txt.*.bak');
                if (count($backups) > 5) {
                    array_map('unlink', array_slice($backups, 0, count($backups) - 5));
                }
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
            
            $log_entry = sprintf(
                "[%s] IP: %s | URI: %s | %s\n",
                $timestamp,
                $ip,
                $uri,
                $message
            );
            
            if (!empty($context)) {
                $log_entry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
            }
            
            $log_entry .= str_repeat('-', 80) . "\n";
            
            @file_put_contents(self::$error_log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Also log to PHP error log
            error_log($message);
            
            // Send critical errors to Telegram (if configured)
            if ($this->isCriticalError($context)) {
                $this->notifyCriticalError($message);
            }
            
        } catch (Exception $e) {
            // If logging fails, at least try to write to PHP error log
            error_log("Error Handler Failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get human-readable error type
     */
    private function getErrorType($errno) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        return $types[$errno] ?? 'UNKNOWN';
    }
    
    /**
     * Check if error is critical
     */
    private function isCriticalError($context) {
        $critical_types = ['ERROR', 'FATAL', 'CORE_ERROR', 'COMPILE_ERROR', 'USER_ERROR'];
        return isset($context['type']) && in_array($context['type'], $critical_types);
    }
    
    /**
     * Notify critical error via Telegram
     */
    private function notifyCriticalError($message) {
        try {
            if (function_exists('sendTelegramHtml') && class_exists('SiteConfig')) {
                if (SiteConfig::get('notify_critical_errors', false)) {
                    $notification = "🚨 <b>Critical Error Detected</b>\n\n";
                    $notification .= "<code>" . htmlspecialchars(substr($message, 0, 500)) . "</code>";
                    sendTelegramHtml($notification);
                }
            }
        } catch (Exception $e) {
            // Fail silently
        }
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode() {
        return (defined('DEBUG_MODE') && DEBUG_MODE) || 
               (class_exists('SiteConfig') && SiteConfig::get('debug_mode', false));
    }
    
    /**
     * Display friendly error page
     */
    private function displayErrorPage() {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code(500);
        
        // Check if this is an AJAX request
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'An unexpected error occurred. Please try again later.',
                'status' => 'SYSTEM_ERROR'
            ]);
        } else {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>System Error - LEGEND CHECKER</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    .error-container {
                        background: white;
                        border-radius: 15px;
                        padding: 40px;
                        max-width: 500px;
                        text-align: center;
                        box-shadow: 0 10px 50px rgba(0,0,0,0.3);
                    }
                    .error-icon {
                        font-size: 64px;
                        margin-bottom: 20px;
                    }
                    h1 {
                        color: #333;
                        font-size: 24px;
                        margin-bottom: 15px;
                    }
                    p {
                        color: #666;
                        line-height: 1.6;
                        margin-bottom: 25px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 12px 30px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        text-decoration: none;
                        border-radius: 8px;
                        font-weight: 600;
                        transition: transform 0.2s;
                    }
                    .btn:hover {
                        transform: translateY(-2px);
                    }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-icon">⚠️</div>
                    <h1>Oops! Something went wrong</h1>
                    <p>We apologize for the inconvenience. Our team has been notified and is working to resolve the issue.</p>
                    <a href="/" class="btn">Return to Home</a>
                </div>
            </body>
            </html>
            <?php
        }
        
        exit;
    }
    
    /**
     * Get recent errors
     */
    public static function getRecentErrors($limit = 50) {
        if (!file_exists(self::$error_log_file)) {
            return [];
        }
        
        $lines = file(self::$error_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * Clear error log
     */
    public static function clearErrorLog() {
        if (file_exists(self::$error_log_file)) {
            return @unlink(self::$error_log_file);
        }
        return true;
    }
}

// Initialize error handler
if (!defined('ERROR_HANDLER_INITIALIZED')) {
    ErrorHandler::getInstance();
    define('ERROR_HANDLER_INITIALIZED', true);
}
?>
