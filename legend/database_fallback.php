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

    public function getAuditLogs($limit = 50, $offset = 0, $targetId = null) {
        $auditLogs = $this->loadData('audit_logs');
        
        // Filter by target_id if provided
        if ($targetId !== null) {
            $auditLogs = array_filter($auditLogs, function($log) use ($targetId) {
                return isset($log['target_id']) && $log['target_id'] == $targetId;
            });
        }
        
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
    
    public function updateUserCredits($telegramId, $newCredits) {
        $users = $this->loadData('users');
        foreach ($users as &$user) {
            if ($user['telegram_id'] == $telegramId) {
                $user['credits'] = (int)$newCredits;
                $user['updated_at'] = time();
                $this->saveData('users', $users);
                return true;
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

    // Proxy management (fallback)
    public function saveProxy(array $proxyData) {
        $proxies = $this->loadData('proxies');
        $id = $proxyData['proxy'] ?? null;
        if ($id === null) {
            throw new Exception('Proxy string missing');
        }

        $hash = sha1($id);
        $now = time();
        $status = $proxyData['status'] ?? 'unknown';

        $index = null;
        foreach ($proxies as $key => $existing) {
            if (($existing['proxy'] ?? '') === $id || ($existing['id'] ?? '') === $hash) {
                $index = $key;
                break;
            }
        }

        if ($index === null) {
            $record = [
                'id' => $hash,
                'proxy' => $id,
                'host' => $proxyData['host'] ?? null,
                'port' => (int)($proxyData['port'] ?? 0),
                'username' => $proxyData['username'] ?? null,
                'created_at' => $now,
                'added_by' => $proxyData['added_by'] ?? null,
                'total_checks' => 0,
                'live_checks' => 0,
                'dead_checks' => 0
            ];
        } else {
            $record = $proxies[$index];
        }

        $record['status'] = $status;
        $record['ip'] = $proxyData['ip'] ?? null;
        $record['country'] = $proxyData['country'] ?? null;
        $record['city'] = $proxyData['city'] ?? null;
        $record['latency_ms'] = $proxyData['latency_ms'] ?? null;
        $record['last_check_message'] = $proxyData['message'] ?? null;
        $record['last_check_at'] = $now;
        $record['updated_at'] = $now;
        $record['total_checks'] = ($record['total_checks'] ?? 0) + 1;
        $record['live_checks'] = ($record['live_checks'] ?? 0) + ($status === 'live' ? 1 : 0);
        $record['dead_checks'] = ($record['dead_checks'] ?? 0) + ($status === 'live' ? 0 : 1);

        if ($status === 'live') {
            $record['last_seen_live_at'] = $now;
        }

        if ($index === null) {
            $proxies[] = $record;
        } else {
            $proxies[$index] = $record;
        }

        $this->saveData('proxies', $proxies);
        return $record;
    }

    public function getProxies(array $filters = [], array $options = []) {
        $proxies = $this->loadData('proxies');

        if (!empty($filters['status'])) {
            $proxies = array_filter($proxies, function ($proxy) use ($filters) {
                return ($proxy['status'] ?? null) === $filters['status'];
            });
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $proxies = array_filter($proxies, function ($proxy) use ($search) {
                return (strpos(strtolower($proxy['proxy'] ?? ''), $search) !== false) ||
                       (strpos(strtolower($proxy['country'] ?? ''), $search) !== false) ||
                       (strpos(strtolower($proxy['ip'] ?? ''), $search) !== false);
            });
        }

        usort($proxies, function ($a, $b) {
            return ($b['last_check_at'] ?? 0) <=> ($a['last_check_at'] ?? 0);
        });

        $skip = isset($options['skip']) ? (int)$options['skip'] : 0;
        $limit = isset($options['limit']) ? (int)$options['limit'] : 100;

        return array_slice(array_values($proxies), $skip, $limit);
    }

    public function getProxyByString(string $proxy) {
        $proxies = $this->loadData('proxies');
        foreach ($proxies as $record) {
            if (($record['proxy'] ?? '') === $proxy) {
                return $record;
            }
        }
        return null;
    }

    public function getProxyById(string $id) {
        $proxies = $this->loadData('proxies');
        foreach ($proxies as $record) {
            if (($record['id'] ?? '') === $id || ($record['proxy'] ?? '') === $id) {
                return $record;
            }
        }
        return null;
    }

    public function removeProxyById(string $id) {
        $proxies = $this->loadData('proxies');
        $removed = false;

        $proxies = array_filter($proxies, function ($proxy) use ($id, &$removed) {
            $match = ($proxy['id'] ?? '') === $id || ($proxy['proxy'] ?? '') === $id;
            if ($match) {
                $removed = true;
                return false;
            }
            return true;
        });

        if ($removed) {
            $this->saveData('proxies', array_values($proxies));
        }

        return $removed;
    }

    public function removeDeadProxies(int $olderThanSeconds = 86400) {
        $proxies = $this->loadData('proxies');
        $threshold = time() - $olderThanSeconds;
        $removed = 0;

        $retained = [];

        foreach ($proxies as $proxy) {
            $isDead = ($proxy['status'] ?? '') === 'dead';
            $lastCheck = $proxy['last_check_at'] ?? 0;

            if ($isDead && $lastCheck <= $threshold) {
                $removed++;
                continue;
            }

            $retained[] = $proxy;
        }

        if ($removed > 0) {
            $this->saveData('proxies', $retained);
        }

        return $removed;
    }

    public function getProxyStats() {
        $proxies = $this->loadData('proxies');
        $total = count($proxies);
        $live = 0;
        $dead = 0;
        $stale = 0;
        $latest = 0;
        $staleThreshold = time() - 86400;

        foreach ($proxies as $proxy) {
            $status = $proxy['status'] ?? 'unknown';
            if ($status === 'live') {
                $live++;
            } elseif ($status === 'dead') {
                $dead++;
            }

            $lastCheck = $proxy['last_check_at'] ?? 0;
            if ($lastCheck > $latest) {
                $latest = $lastCheck;
            }

            if ($lastCheck < $staleThreshold) {
                $stale++;
            }
        }

        return [
            'total' => $total,
            'live' => $live,
            'dead' => $dead,
            'stale' => $stale,
            'last_checked_at' => $latest > 0 ? $latest : null
        ];
    }
}
?>
