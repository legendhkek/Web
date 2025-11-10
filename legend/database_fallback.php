<?php
// Fallback database system using JSON files when MongoDB is not available

class DatabaseFallback {
    private static $instance = null;
    private $dataDir;
    
    private function __construct() {
        $this->dataDir = __DIR__ . '/data/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function loadData($collection) {
        $file = $this->dataDir . $collection . '.json';
        if (!file_exists($file)) {
            return [];
        }
        $data = file_get_contents($file);
        return json_decode($data, true) ?: [];
    }
    
    public function saveData($collection, $data) {
        $file = $this->dataDir . $collection . '.json';
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function getUserByTelegramId($telegramId) {
        $users = $this->loadData('users');
        foreach ($users as $user) {
            if ($user['telegram_id'] == $telegramId) {
                return $user;
            }
        }
        return null;
    }
    
    public function getUserById($userId) {
        $users = $this->loadData('users');
        foreach ($users as $user) {
            if ($user['_id'] == $userId) {
                return $user;
            }
        }
        return null;
    }
    
    public function updateUserRole($telegramId, $role) {
        $users = $this->loadData('users');
        $updated = false;
        
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                $user['role'] = $role;
                $user['updated_at'] = time();
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            $this->saveData('users', $users);
            return true;
        }
        return false;
    }
    
    public function createUser($telegramData) {
        $users = $this->loadData('users');
        
        $userData = [
            'telegram_id' => $telegramData['id'],
            'username' => $telegramData['username'] ?? null,
            'display_name' => $telegramData['first_name'] . ' ' . ($telegramData['last_name'] ?? ''),
            'avatar_url' => $telegramData['photo_url'] ?? null,
            'role' => AppConfig::ROLE_FREE,
            'credits' => 50,
            'xcoin_balance' => 0,
            'status' => 'active',
            'last_login_at' => time(),
            'membership_verified_at' => time(),
            'created_at' => time(),
            'updated_at' => time()
        ];
        
        $users[] = $userData;
        $this->saveData('users', $users);
        
        // Create user stats
        $stats = $this->loadData('user_stats');
        $statsData = [
            'user_id' => $telegramData['id'],
            'total_hits' => 0,
            'total_charge_cards' => 0,
            'total_live_cards' => 0,
            'expiry_date' => null,
            'updated_at' => time()
        ];
        $stats[] = $statsData;
        $this->saveData('user_stats', $stats);
        
        return $userData;
    }
    
    public function updateUserStatus($telegramId, $status) {
        $users = $this->loadData('users');
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                $user['status'] = $status;
                $user['updated_at'] = time();
                break;
            }
        }
        $this->saveData('users', $users);
    }

    // This duplicate method was removed to fix the 'Cannot redeclare DatabaseFallback::updateUserRole()' error

    public function updateUserLastLogin($telegramId) {
        $users = $this->loadData('users');
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                $user['last_login_at'] = time();
                $user['updated_at'] = time();
                break;
            }
        }
        $this->saveData('users', $users);
    }

    public function logAuditAction($adminId, $action, $targetId = null, $details = []) {
        $auditLogs = $this->loadData('audit_logs');
        $auditLogs[] = [
            'admin_id' => $adminId,
            'action' => $action,
            'target_id' => $targetId,
            'details' => $details,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        $this->saveData('audit_logs', $auditLogs);
    }

    public function getAuditLogs($limit = 50, $offset = 0) {
        $auditLogs = $this->loadData('audit_logs');
        // Sort by timestamp descending
        usort($auditLogs, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        return array_slice($auditLogs, $offset, $limit);
    }
    
    public function updatePresence($telegramId) {
        $presence = $this->loadData('presence_heartbeats');
        $found = false;
        
        foreach ($presence as &$p) {
            if ($p['user_id'] == $telegramId) {
                $p['last_seen'] = time();
                $p['updated_at'] = time();
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $presence[] = [
                'user_id' => $telegramId,
                'last_seen' => time(),
                'created_at' => time(),
                'updated_at' => time()
            ];
        }
        
        $this->saveData('presence_heartbeats', $presence);
    }
    
    public function canClaimDailyCredits($telegramId) {
        $claims = $this->loadData('daily_credit_claims');
        $today = date('Y-m-d');
        
        foreach ($claims as $claim) {
            if ($claim['user_id'] == $telegramId && $claim['claim_date'] == $today) {
                return false;
            }
        }
        return true;
    }
    
    public function claimDailyCredits($telegramId, $amount = null) {
        if (!$this->canClaimDailyCredits($telegramId)) {
            return false;
        }
        
        // Add credit claim record
        $claims = $this->loadData('daily_credit_claims');
        $award = (int)($amount ?? AppConfig::DAILY_CREDIT_AMOUNT);
        $claims[] = [
            'user_id' => $telegramId,
            'claim_date' => date('Y-m-d'),
            'credits_awarded' => $award,
            'created_at' => time()
        ];
        $this->saveData('daily_credit_claims', $claims);
        
        // Update user credits
        $users = $this->loadData('users');
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                $user['credits'] += $award;
                $user['updated_at'] = time();
                break;
            }
        }
        $this->saveData('users', $users);
        
        return true;
    }
    
    public function deductCredits($telegramId, $amount) {
        $users = $this->loadData('users');
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                if ($user['credits'] >= $amount) {
                    $user['credits'] -= $amount;
                    $user['updated_at'] = time();
                    $this->saveData('users', $users);
                    return true;
                }
                return false;
            }
        }
        return false;
    }
    
    public function logToolUsage($userId, $toolName, $count, $creditsUsed) {
        $usage = $this->loadData('tool_usage');
        $usage[] = [
            'user_id' => $userId,
            'tool_name' => $toolName,
            'usage_count' => $count,
            'credits_used' => $creditsUsed,
            'created_at' => time()
        ];
        $this->saveData('tool_usage', $usage);
    }
    
    public function getUserStats($telegramId) {
        $stats = $this->loadData('user_stats');
        foreach ($stats as $stat) {
            if ($stat['user_id'] == $telegramId) {
                return $stat;
            }
        }
        return ['total_hits' => 0, 'total_charge_cards' => 0, 'total_live_cards' => 0];
    }
    
    public function updateUserStats($telegramId, $type, $increment = 1) {
        $stats = $this->loadData('user_stats');
        foreach ($stats as &$stat) {
            if ($stat['user_id'] == $telegramId) {
                $stat[$type] += $increment;
                $stat['updated_at'] = time();
                $this->saveData('user_stats', $stats);
                return;
            }
        }
    }
    
    public function getOnlineUsers($limit = 50) {
        $presence = $this->loadData('presence_heartbeats');
        $users = $this->loadData('users');
        $onlineUsers = [];
        $fiveMinutesAgo = time() - 300;
        
        foreach ($presence as $p) {
            if ($p['last_seen'] > $fiveMinutesAgo) {
                foreach ($users as $user) {
                    if ($user['telegram_id'] == $p['user_id']) {
                        $onlineUsers[] = array_merge($user, ['last_seen' => $p['last_seen']]);
                        break;
                    }
                }
            }
        }
        
        return array_slice($onlineUsers, 0, $limit);
    }
    
    public function getTopUsers($limit = 10) {
        $users = $this->loadData('users');
        $stats = $this->loadData('user_stats');
        
        // Merge users with their stats
        foreach ($users as &$user) {
            foreach ($stats as $stat) {
                if ($stat['user_id'] == $user['telegram_id']) {
                    $user = array_merge($user, $stat);
                    break;
                }
            }
        }
        
        // Sort by total hits
        usort($users, function($a, $b) {
            return ($b['total_hits'] ?? 0) - ($a['total_hits'] ?? 0);
        });
        
        return array_slice($users, 0, $limit);
    }
    
    public function getGlobalStats() {
        $users = $this->loadData('users');
        $stats = $this->loadData('user_stats');
        
        $totalUsers = count($users);
        $totalHits = 0;
        $totalChargeCards = 0;
        $totalLiveCards = 0;
        
        foreach ($stats as $stat) {
            $totalHits += $stat['total_hits'] ?? 0;
            $totalChargeCards += $stat['total_charge_cards'] ?? 0;
            $totalLiveCards += $stat['total_live_cards'] ?? 0;
        }
        
        return [
            'total_users' => $totalUsers,
            'total_hits' => $totalHits,
            'total_charge_cards' => $totalChargeCards,
            'total_live_cards' => $totalLiveCards
        ];
    }
    
    public function redeemCredits($userId, $credits, $xcoinCost) {
        $users = $this->loadData('users');
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $userId && $user['xcoin_balance'] >= $xcoinCost) {
                $user['credits'] += $credits;
                $user['xcoin_balance'] -= $xcoinCost;
                $user['updated_at'] = time();
                $this->saveData('users', $users);
                return true;
            }
        }
        return false;
    }
    
    public function upgradeMembership($userId, $plan, $xcoinCost) {
        $users = $this->loadData('users');
        $dailyCredits = ['premium' => 25, 'vip' => 50];
        
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $userId && $user['xcoin_balance'] >= $xcoinCost) {
                $user['role'] = $plan;
                $user['xcoin_balance'] -= $xcoinCost;
                $user['credits'] += $dailyCredits[$plan] ?? 0;
                $user['updated_at'] = time();
                $this->saveData('users', $users);
                return true;
            }
        }
        return false;
    }

    /* --------------------------------------------------------------------------
     | Proxy Manager (Fallback Storage)
     |--------------------------------------------------------------------------*/

    public function getUserProxies($telegramId, $filters = []) {
        $proxies = $this->loadData('proxies');
        $results = [];
        $statusFilter = null;

        if (isset($filters['status'])) {
            $statusFilter = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $statusFilter = array_map(function ($status) {
                return strtolower((string)$status);
            }, $statusFilter);
        }

        foreach ($proxies as $proxy) {
            if (($proxy['user_id'] ?? null) != $telegramId) {
                continue;
            }
            $proxyStatus = strtolower($proxy['status'] ?? 'unknown');
            if ($statusFilter && !in_array($proxyStatus, $statusFilter, true)) {
                continue;
            }
            $results[] = $this->normalizeProxyRecord($proxy);
        }

        usort($results, function ($a, $b) {
            $aTime = strtotime($a['created_at'] ?? '') ?: 0;
            $bTime = strtotime($b['created_at'] ?? '') ?: 0;
            return $bTime <=> $aTime;
        });

        return $results;
    }

    public function getProxyById($telegramId, $proxyId) {
        $proxies = $this->loadData('proxies');
        foreach ($proxies as $proxy) {
            if (($proxy['user_id'] ?? null) == $telegramId && ($proxy['id'] ?? null) === $proxyId) {
                return $this->normalizeProxyRecord($proxy);
            }
        }
        return null;
    }

    public function addUserProxy($telegramId, $proxyString, $label = null, $metadata = []) {
        $proxy = trim((string)$proxyString);
        if ($proxy === '') {
            return [
                'status' => 'error',
                'message' => 'Proxy value cannot be empty'
            ];
        }

        $proxies = $this->loadData('proxies');
        foreach ($proxies as &$existing) {
            if (($existing['user_id'] ?? null) == $telegramId && ($existing['proxy'] ?? null) === $proxy) {
                $existing['updated_at'] = time();
                if ($label !== null && $label !== '') {
                    $existing['label'] = $label;
                }
                $this->applyProxyMetadata($existing, $metadata);
                $this->saveData('proxies', $proxies);
                return [
                    'status' => 'exists',
                    'proxy' => $this->normalizeProxyRecord($existing)
                ];
            }
        }

        $record = [
            'id' => uniqid('proxy_', true),
            'user_id' => $telegramId,
            'proxy' => $proxy,
            'label' => ($label !== null && $label !== '') ? $label : $this->generateProxyLabel($proxy),
            'status' => 'unknown',
            'latency_ms' => null,
            'country' => null,
            'ip_address' => null,
            'last_checked_at' => null,
            'last_used_at' => null,
            'last_error' => null,
            'usage' => [
                'total_checks' => 0,
                'successful_checks' => 0
            ],
            'created_at' => time(),
            'updated_at' => time()
        ];

        $this->applyProxyMetadata($record, $metadata);

        $proxies[] = $record;
        $this->saveData('proxies', $proxies);

        return [
            'status' => 'created',
            'proxy' => $this->normalizeProxyRecord($record)
        ];
    }

    public function addUserProxiesBulk($telegramId, array $proxyStrings, $labelPrefix = null) {
        $summary = [
            'created' => [],
            'duplicates' => [],
            'invalid' => []
        ];

        $counter = 1;
        foreach ($proxyStrings as $rawProxy) {
            $proxy = trim((string)$rawProxy);
            if ($proxy === '') {
                $summary['invalid'][] = [
                    'proxy' => $rawProxy,
                    'reason' => 'empty'
                ];
                continue;
            }

            $label = $labelPrefix ? ($labelPrefix . ' #' . $counter) : null;
            $result = $this->addUserProxy($telegramId, $proxy, $label);

            if ($result['status'] === 'created') {
                $summary['created'][] = $result['proxy'];
                $counter++;
            } elseif ($result['status'] === 'exists') {
                $summary['duplicates'][] = $result['proxy'];
            } else {
                $summary['invalid'][] = [
                    'proxy' => $proxy,
                    'reason' => $result['message'] ?? 'unknown'
                ];
            }
        }

        return $summary;
    }

    public function updateProxyStatus($telegramId, $proxyId, $status, $metadata = []) {
        $proxies = $this->loadData('proxies');
        $updated = false;

        foreach ($proxies as &$proxy) {
            if (($proxy['user_id'] ?? null) == $telegramId && ($proxy['id'] ?? null) === $proxyId) {
                $proxy['status'] = strtolower((string)$status) ?: 'unknown';
                $proxy['updated_at'] = time();
                $proxy['last_checked_at'] = time();
                $this->applyProxyMetadata($proxy, $metadata);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $this->saveData('proxies', $proxies);
            foreach ($proxies as $proxy) {
                if (($proxy['user_id'] ?? null) == $telegramId && ($proxy['id'] ?? null) === $proxyId) {
                    return [
                        'status' => 'updated',
                        'proxy' => $this->normalizeProxyRecord($proxy)
                    ];
                }
            }
        }

        return [
            'status' => 'error',
            'message' => 'Proxy not found'
        ];
    }

    public function deleteUserProxies($telegramId, array $proxyIds) {
        $proxyIds = array_values(array_unique(array_map('strval', $proxyIds)));
        if (empty($proxyIds)) {
            return 0;
        }

        $proxies = $this->loadData('proxies');
        $remaining = [];
        $deleted = 0;

        foreach ($proxies as $proxy) {
            if (($proxy['user_id'] ?? null) == $telegramId && in_array($proxy['id'] ?? null, $proxyIds, true)) {
                $deleted++;
                continue;
            }
            $remaining[] = $proxy;
        }

        if ($deleted > 0) {
            $this->saveData('proxies', $remaining);
        }

        return $deleted;
    }

    public function recordProxyUsage($telegramId, $proxyId, $success, $latencyMs = null) {
        $proxies = $this->loadData('proxies');

        foreach ($proxies as &$proxy) {
            if (($proxy['user_id'] ?? null) == $telegramId && ($proxy['id'] ?? null) === $proxyId) {
                if (!isset($proxy['usage']) || !is_array($proxy['usage'])) {
                    $proxy['usage'] = [
                        'total_checks' => 0,
                        'successful_checks' => 0
                    ];
                }

                $proxy['usage']['total_checks'] = (int)($proxy['usage']['total_checks'] ?? 0) + 1;
                if ($success) {
                    $proxy['usage']['successful_checks'] = (int)($proxy['usage']['successful_checks'] ?? 0) + 1;
                }

                if ($latencyMs !== null) {
                    $proxy['latency_ms'] = (float)$latencyMs;
                }

                $proxy['last_used_at'] = time();
                $proxy['updated_at'] = time();
                $this->saveData('proxies', $proxies);
                return true;
            }
        }

        return false;
    }

    private function normalizeProxyRecord(array $proxy) {
        $usage = $proxy['usage'] ?? ['total_checks' => 0, 'successful_checks' => 0];

        return [
            'id' => $proxy['id'] ?? null,
            'proxy' => $proxy['proxy'] ?? '',
            'label' => $proxy['label'] ?? null,
            'status' => strtolower($proxy['status'] ?? 'unknown'),
            'latency_ms' => isset($proxy['latency_ms']) ? (float)$proxy['latency_ms'] : null,
            'country' => $proxy['country'] ?? null,
            'ip_address' => $proxy['ip_address'] ?? null,
            'last_checked_at' => $this->formatDateValue($proxy['last_checked_at'] ?? null),
            'last_used_at' => $this->formatDateValue($proxy['last_used_at'] ?? null),
            'created_at' => $this->formatDateValue($proxy['created_at'] ?? null),
            'updated_at' => $this->formatDateValue($proxy['updated_at'] ?? null),
            'last_error' => $proxy['last_error'] ?? null,
            'usage' => [
                'total_checks' => (int)($usage['total_checks'] ?? 0),
                'successful_checks' => (int)($usage['successful_checks'] ?? 0)
            ]
        ];
    }

    private function applyProxyMetadata(array &$proxy, array $metadata) {
        if (isset($metadata['status']) && $metadata['status'] !== '') {
            $proxy['status'] = strtolower((string)$metadata['status']);
        }

        if (array_key_exists('latency_ms', $metadata) && $metadata['latency_ms'] !== null && $metadata['latency_ms'] !== '') {
            $proxy['latency_ms'] = (float)$metadata['latency_ms'];
        }

        if (array_key_exists('country', $metadata)) {
            $proxy['country'] = $metadata['country'] === null ? null : (string)$metadata['country'];
        }

        if (array_key_exists('ip_address', $metadata)) {
            $proxy['ip_address'] = $metadata['ip_address'] === null ? null : (string)$metadata['ip_address'];
        }

        if (array_key_exists('last_error', $metadata)) {
            $proxy['last_error'] = $metadata['last_error'] === null ? null : (string)$metadata['last_error'];
        }

        if (array_key_exists('last_checked_at', $metadata)) {
            $proxy['last_checked_at'] = $this->coerceTimestamp($metadata['last_checked_at']);
        }

        if (array_key_exists('last_used_at', $metadata)) {
            $proxy['last_used_at'] = $this->coerceTimestamp($metadata['last_used_at']);
        }

        if (isset($metadata['usage']) && is_array($metadata['usage'])) {
            if (!isset($proxy['usage']) || !is_array($proxy['usage'])) {
                $proxy['usage'] = [
                    'total_checks' => 0,
                    'successful_checks' => 0
                ];
            }
            if (isset($metadata['usage']['total_checks'])) {
                $proxy['usage']['total_checks'] = (int)$metadata['usage']['total_checks'];
            }
            if (isset($metadata['usage']['successful_checks'])) {
                $proxy['usage']['successful_checks'] = (int)$metadata['usage']['successful_checks'];
            }
        }
    }

    private function coerceTimestamp($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return null;
    }

    private function formatDateValue($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return date(DATE_ATOM, (int)$value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    private function generateProxyLabel($proxy) {
        $hash = strtoupper(substr(hash('crc32b', $proxy), 0, 6));
        return 'Proxy ' . $hash;
    }
}
?>
