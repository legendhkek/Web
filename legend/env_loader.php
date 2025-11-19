<?php
/**
 * Environment Variable Loader
 * Loads configuration from .env file if it exists
 * Falls back to default values from config if not found
 */

class EnvLoader {
    private static $loaded = false;
    private static $env = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($envFile === null) {
            $envFile = __DIR__ . '/.env';
        }
        
        // Load from file if it exists
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse KEY=VALUE format
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    // Store in environment
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    self::$env[$key] = $value;
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with fallback
     */
    public static function get($key, $default = null) {
        self::load();
        
        // Check in order: $_ENV, $_SERVER, getenv(), loaded .env, default
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }
        
        return $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        self::load();
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false || isset(self::$env[$key]);
    }
}

// Auto-load on require
EnvLoader::load();
?>
