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

    /**
     * Proxy manager fallback helpers
     */
    public function getUserProxies($telegramId) {
        $proxies = $this->loadData('user_proxies');
        return array_values(array_filter($proxies, function($proxy) use ($telegramId) {
            return isset($proxy['user_id']) && $proxy['user_id'] == $telegramId;
        }));
    }

    public function addUserProxy($telegramId, array $proxyData) {
        $proxies = $this->loadData('user_proxies');
        $now = time();
        $existingId = null;

        foreach ($proxies as &$proxy) {
            if ($proxy['user_id'] == $telegramId && $proxy['proxy'] === $proxyData['proxy']) {
                $proxy['label'] = $proxyData['label'] ?? ($proxy['label'] ?? null);
                $proxy['status'] = $proxyData['status'] ?? ($proxy['status'] ?? 'unknown');
                $proxy['error'] = $proxyData['error'] ?? ($proxy['error'] ?? null);
                $proxy['last_checked_at'] = $proxyData['last_checked_at'] ?? ($proxy['last_checked_at'] ?? null);
                $proxy['latency_ms'] = $proxyData['latency_ms'] ?? ($proxy['latency_ms'] ?? null);
                $proxy['country'] = $proxyData['country'] ?? ($proxy['country'] ?? null);
                $proxy['updated_at'] = $now;
                $existingId = $proxy['id'];
                break;
            }
        }

        if ($existingId === null) {
            $newProxy = [
                'id' => uniqid('proxy_', true),
                'user_id' => $telegramId,
                'proxy' => $proxyData['proxy'],
                'label' => $proxyData['label'] ?? null,
                'status' => $proxyData['status'] ?? 'unknown',
                'error' => $proxyData['error'] ?? null,
                'last_checked_at' => $proxyData['last_checked_at'] ?? null,
                'latency_ms' => $proxyData['latency_ms'] ?? null,
                'country' => $proxyData['country'] ?? null,
                'usage_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $proxies[] = $newProxy;
            $existingId = $newProxy['id'];
        }

        $this->saveData('user_proxies', $proxies);
        return $existingId;
    }

    public function removeUserProxies($telegramId, array $proxyIds) {
        $proxies = $this->loadData('user_proxies');
        $initialCount = count($proxies);

        $proxies = array_filter($proxies, function($proxy) use ($telegramId, $proxyIds) {
            if ($proxy['user_id'] != $telegramId) {
                return true;
            }
            return !in_array($proxy['id'], $proxyIds, true);
        });

        $this->saveData('user_proxies', array_values($proxies));
        return $initialCount - count($proxies);
    }

    public function updateUserProxyStatus($telegramId, $proxyId, array $statusData) {
        $proxies = $this->loadData('user_proxies');
        $updated = false;
        $now = time();

        foreach ($proxies as &$proxy) {
            if ($proxy['user_id'] == $telegramId && $proxy['id'] === $proxyId) {
                foreach ($statusData as $key => $value) {
                    $proxy[$key] = $value;
                }
                $proxy['updated_at'] = $now;
                if (isset($statusData['last_checked_at']) && !empty($statusData['last_checked_at'])) {
                    $proxy['last_checked_at'] = $statusData['last_checked_at'];
                }
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $this->saveData('user_proxies', $proxies);
        }

        return $updated;
    }
}
?>
