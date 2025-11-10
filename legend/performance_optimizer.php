<?php
/**
 * Performance Optimization Script
 * Implements caching, connection pooling, and performance monitoring
 */

class PerformanceOptimizer {
    
    private static $cache = [];
    private static $cacheFile = '../data/cache.json';
    
    /**
     * Simple file-based caching system
     */
    public static function cache($key, $data = null, $ttl = 300) {
        self::loadCache();
        
        if ($data === null) {
            // Get from cache
            if (isset(self::$cache[$key])) {
                $item = self::$cache[$key];
                if (time() < $item['expires']) {
                    return $item['data'];
                }
                unset(self::$cache[$key]);
            }
            return null;
        }
        
        // Set cache
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        
        self::saveCache();
        return $data;
    }
    
    private static function loadCache() {
        if (file_exists(self::$cacheFile)) {
            self::$cache = json_decode(file_get_contents(self::$cacheFile), true) ?? [];
        }
    }
    
    private static function saveCache() {
        // Clean expired entries
        $now = time();
        foreach (self::$cache as $key => $item) {
            if ($now >= $item['expires']) {
                unset(self::$cache[$key]);
            }
        }
        
        file_put_contents(self::$cacheFile, json_encode(self::$cache));
    }
    
    /**
     * Optimize cURL requests with connection reuse
     */
    public static function optimizedCurl($url, $options = []) {
        static $curlHandle = null;
        
        if ($curlHandle === null) {
            $curlHandle = curl_init();
        }
        
        $defaultOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'ExChk/1.0',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 120,
            CURLOPT_TCP_KEEPINTVL => 60
        ];
        
        $finalOptions = array_merge($defaultOptions, $options);
        curl_setopt_array($curlHandle, $finalOptions);
        
        $result = curl_exec($curlHandle);
        $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $error = curl_error($curlHandle);
        
        return [
            'data' => $result,
            'http_code' => $httpCode,
            'error' => $error
        ];
    }
    
    /**
     * Database query optimization with caching
     */
    public static function cachedQuery($db, $collection, $query, $ttl = 60) {
        $cacheKey = 'db_' . md5($collection . serialize($query));
        
        $cached = self::cache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $db->getCollection($collection)->find($query)->toArray();
        return self::cache($cacheKey, $result, $ttl);
    }
    
    /**
     * Memory usage monitoring
     */
    public static function logMemoryUsage($checkpoint = '') {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        error_log("Memory Usage [$checkpoint]: " . 
                 self::formatBytes($memoryUsage) . 
                 " (Peak: " . self::formatBytes($peakMemory) . ")");
    }
    
    public static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Response compression
     */
    public static function enableCompression() {
        if (!headers_sent() && extension_loaded('zlib')) {
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
    }
    
    /**
     * Asset minification for CSS/JS
     */
    public static function minifyCSS($css) {
        $css = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': '], [';', '{', '{', '}', '}', ':'], $css);
        return trim($css);
    }
    
    public static function minifyJS($js) {
        // Basic JS minification (for production, use a proper minifier)
        $js = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $js);
        $js = preg_replace('/\/\/.*$/m', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        return trim($js);
    }
}

/**
 * Performance monitoring middleware
 */
class PerformanceMonitor {
    private static $startTime;
    private static $startMemory;
    
    public static function start() {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage(true);
    }
    
    public static function end($operation = 'request') {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = round(($endTime - self::$startTime) * 1000, 2);
        $memoryUsed = $endMemory - self::$startMemory;
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'execution_time_ms' => $executionTime,
            'memory_used' => PerformanceOptimizer::formatBytes($memoryUsed),
            'peak_memory' => PerformanceOptimizer::formatBytes(memory_get_peak_usage(true))
        ];
        
        // Log to performance file
        $logFile = '../data/performance_logs.json';
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?? [];
        }
        
        $logs[] = $logEntry;
        
        // Keep only last 500 entries
        if (count($logs) > 500) {
            $logs = array_slice($logs, -500);
        }
        
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
        
        return $logEntry;
    }
}
?>
