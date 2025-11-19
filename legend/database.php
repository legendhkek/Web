<?php
require_once 'config.php';
require_once 'database_fallback.php';

class Database {
    private static $instance = null;
    private $client;
    private $database;
    private $useFallback = false;
    
    private function __construct() {
        try {
            // Check if MongoDB extension is available
            if (!class_exists('MongoDB\Client')) {
                throw new Exception('MongoDB extension not installed');
            }
            
            // MongoDB connection using MongoDB PHP Library with environment variables
            $mongoUri = DatabaseConfig::getMongoDBUri();
            $dbName = DatabaseConfig::getDatabaseName();
            
            $this->client = new MongoDB\Client($mongoUri);
            $this->database = $this->client->selectDatabase($dbName);
            
            // Test connection
            $this->database->command(['ping' => 1]);
            
        } catch (Exception $e) {
            logError('MongoDB connection failed, using fallback: ' . $e->getMessage());
            $this->useFallback = true;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getFallback() {
        return DatabaseFallback::getInstance();
    }
    
    public function getCollection($collectionName) {
        if ($this->useFallback) {
            // Return null for fallback - methods will handle this
            return null;
        }
        return $this->database->selectCollection($collectionName);
    }
    
    // User operations
    public function createUser($telegramData) {
        if ($this->useFallback) {
            return $this->getFallback()->createUser($telegramData);
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        $userStats = $this->getCollection(DatabaseConfig::USER_STATS_COLLECTION);
        
        $userData = [
            'telegram_id' => $telegramData['id'],
            'username' => $telegramData['username'] ?? null,
            'display_name' => $telegramData['first_name'] . ' ' . ($telegramData['last_name'] ?? ''),
            'avatar_url' => $telegramData['photo_url'] ?? null,
            'role' => AppConfig::ROLE_FREE,
            'credits' => 50, // Starting credits
            'xcoin_balance' => 0,
            'status' => 'active',
            'last_login_at' => new MongoDB\BSON\UTCDateTime(),
            'membership_verified_at' => new MongoDB\BSON\UTCDateTime(),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $statsData = [
            'user_id' => $telegramData['id'],
            'total_hits' => 0,
            'total_charge_cards' => 0,
            'total_live_cards' => 0,
            'expiry_date' => null,
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $users->insertOne($userData);
        $userStats->insertOne($statsData);
        
        return $userData;
    }
    
    public function getAllUsers($limit = 25, $offset = 0) {
        if ($this->useFallback) {
            $users = $this->getFallback()->loadData('users');
            return array_slice($users, $offset, $limit);
        }

        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        return $users->find([], ['limit' => $limit, 'skip' => $offset, 'sort' => ['created_at' => -1]])->toArray();
    }

    public function getUserById($userId) {
        if ($this->useFallback) {
            $users = $this->getFallback()->loadData('users');
            foreach ($users as $user) {
                // Check both _id and telegram_id fields for compatibility
                if ((isset($user['_id']) && $user['_id'] == $userId) || 
                    (isset($user['telegram_id']) && $user['telegram_id'] == $userId)) {
                    return $user;
                }
            }
            return null;
        }

        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        // Try to find by ObjectId first, then by telegram_id as fallback
        try {
            $result = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            if ($result) return $result;
        } catch (Exception $e) {
            // If ObjectId conversion fails, try as telegram_id
        }
        
        // Fallback to telegram_id search
        return $users->findOne(['telegram_id' => (int)$userId]);
    }
    
    public function getUserByTelegramId($telegramId) {
        if ($this->useFallback) {
            return $this->getFallback()->getUserByTelegramId($telegramId);
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        return $users->findOne(['telegram_id' => $telegramId]);
    }
    
    public function updateUserStatus($telegramId, $status) {
        if ($this->useFallback) {
            return $this->getFallback()->updateUserStatus($telegramId, $status);
        }

        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        $users->updateOne(
            ['telegram_id' => $telegramId],
            ['$set' => ['status' => $status, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
        );
    }

    public function updateUserRole($telegramId, $role) {
        if ($this->useFallback) {
            return $this->getFallback()->updateUserRole($telegramId, $role);
        }

        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        $result = $users->updateOne(
            ['telegram_id' => $telegramId],
            ['$set' => ['role' => $role, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0;
    }

    public function updateUserLastLogin($telegramId) {
        if ($this->useFallback) {
            return $this->getFallback()->updateUserLastLogin($telegramId);
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        $users->updateOne(
            ['telegram_id' => $telegramId],
            ['$set' => [
                'last_login_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    }
    
    public function getUserStats($telegramId) {
        if ($this->useFallback) {
            return $this->getFallback()->getUserStats($telegramId);
        }
        
        $userStats = $this->getCollection(DatabaseConfig::USER_STATS_COLLECTION);
        return $userStats->findOne(['user_id' => $telegramId]);
    }
    
    public function updateUserStats($telegramId, $statsUpdate) {
        $userStats = $this->getCollection(DatabaseConfig::USER_STATS_COLLECTION);
        $statsUpdate['updated_at'] = new MongoDB\BSON\UTCDateTime();
        $userStats->updateOne(
            ['user_id' => $telegramId],
            ['$set' => $statsUpdate]
        );
    }
    
    public function deductCredits($telegramId, $amount) {
        if ($this->useFallback) {
            return $this->getFallback()->deductCredits($telegramId, $amount);
        }
        
        try {
            $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
            if (!$users) {
                logError('Failed to get users collection for credit deduction', ['telegram_id' => $telegramId]);
                return false;
            }
            
            $result = $users->updateOne(
                ['telegram_id' => (int)$telegramId, 'credits' => ['$gte' => (int)$amount]],
                ['$inc' => ['credits' => -(int)$amount], '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            logError('Error deducting credits: ' . $e->getMessage(), [
                'telegram_id' => $telegramId,
                'amount' => $amount,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function addCredits($telegramId, $amount) {
        if ($this->useFallback) {
            $users = $this->getFallback()->loadData('users');
            foreach ($users as &$user) {
                if ($user['telegram_id'] == $telegramId) {
                    $user['credits'] += $amount;
                    $user['updated_at'] = time();
                    break;
                }
            }
            $this->getFallback()->saveData('users', $users);
            return true;
        }
        
        try {
            $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
            if (!$users) {
                logError('Failed to get users collection for adding credits', ['telegram_id' => $telegramId]);
                return false;
            }
            
            $result = $users->updateOne(
                ['telegram_id' => (int)$telegramId],
                ['$inc' => ['credits' => (int)$amount], '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            logError('Error adding credits: ' . $e->getMessage(), [
                'telegram_id' => $telegramId,
                'amount' => $amount,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function updateUserCredits($telegramId, $newCredits) {
        if ($this->useFallback) {
            $users = $this->getFallback()->loadData('users');
            foreach ($users as &$user) {
                if ($user['telegram_id'] == $telegramId) {
                    $user['credits'] = (int)$newCredits;
                    $user['updated_at'] = time();
                    break;
                }
            }
            $this->getFallback()->saveData('users', $users);
            return true;
        }
        
        try {
            $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
            if (!$users) {
                logError('Failed to get users collection for updating credits', ['telegram_id' => $telegramId]);
                return false;
            }
            
            $result = $users->updateOne(
                ['telegram_id' => (int)$telegramId],
                ['$set' => ['credits' => (int)$newCredits, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            logError('Error updating credits: ' . $e->getMessage(), [
                'telegram_id' => $telegramId,
                'new_credits' => $newCredits,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    // Daily credit claim
    public function canClaimDailyCredits($telegramId) {
        if ($this->useFallback) {
            return $this->getFallback()->canClaimDailyCredits($telegramId);
        }
        
        $claims = $this->getCollection(DatabaseConfig::DAILY_CREDIT_CLAIMS_COLLECTION);
        $today = date('Y-m-d');
        $existingClaim = $claims->findOne([
            'user_id' => $telegramId,
            'claim_date' => $today
        ]);
        return $existingClaim === null;
    }
    
    // Alias for backward compatibility
    public function canClaimDailyCredit($telegramId) {
        return $this->canClaimDailyCredits($telegramId);
    }
    
    public function claimDailyCredits($telegramId, $amountOverride = null) {
        if ($this->useFallback) {
            return $this->getFallback()->claimDailyCredits($telegramId, $amountOverride);
        }
        
        if (!$this->canClaimDailyCredits($telegramId)) {
            return false;
        }
        
        $claims = $this->getCollection(DatabaseConfig::DAILY_CREDIT_CLAIMS_COLLECTION);
        $claims->insertOne([
            'user_id' => $telegramId,
            'claim_date' => date('Y-m-d'),
            'credits_awarded' => (int)($amountOverride ?? AppConfig::DAILY_CREDIT_AMOUNT),
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        
        $this->addCredits($telegramId, (int)($amountOverride ?? AppConfig::DAILY_CREDIT_AMOUNT));
        return true;
    }
    
    // Alias for backward compatibility
    public function claimDailyCredit($telegramId) {
        return $this->claimDailyCredits($telegramId);
    }
    
    // Presence system
    public function updatePresence($telegramId) {
        if ($this->useFallback) {
            return $this->getFallback()->updatePresence($telegramId);
        }
        
        $presence = $this->getCollection(DatabaseConfig::PRESENCE_HEARTBEATS_COLLECTION);
        $presence->updateOne(
            ['user_id' => $telegramId],
            ['$set' => ['last_seen_at' => new MongoDB\BSON\UTCDateTime()]],
            ['upsert' => true]
        );
    }
    
    public function getOnlineUsers($limit = 50) {
        if ($this->useFallback) {
            return $this->getFallback()->getOnlineUsers($limit);
        }
        
        $presence = $this->getCollection(DatabaseConfig::PRESENCE_HEARTBEATS_COLLECTION);
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        
        $fiveMinutesAgo = new MongoDB\BSON\UTCDateTime((time() - 300) * 1000);
        
        $pipeline = [
            ['$match' => ['last_seen_at' => ['$gte' => $fiveMinutesAgo]]],
            ['$lookup' => [
                'from' => DatabaseConfig::USERS_COLLECTION,
                'localField' => 'user_id',
                'foreignField' => 'telegram_id',
                'as' => 'user'
            ]],
            ['$unwind' => '$user'],
            ['$limit' => $limit],
            ['$sort' => ['last_seen_at' => -1]]
        ];
        
        return $presence->aggregate($pipeline)->toArray();
    }
    
    // Leaderboard
    public function getTopUsers($limit = 10) {
        if ($this->useFallback) {
            return $this->getFallback()->getTopUsers($limit);
        }
        
        $userStats = $this->getCollection(DatabaseConfig::USER_STATS_COLLECTION);
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        
        $pipeline = [
            ['$lookup' => [
                'from' => DatabaseConfig::USERS_COLLECTION,
                'localField' => 'user_id',
                'foreignField' => 'telegram_id',
                'as' => 'user'
            ]],
            ['$unwind' => '$user'],
            ['$sort' => ['total_hits' => -1]],
            ['$limit' => $limit]
        ];
        
        return $userStats->aggregate($pipeline)->toArray();
    }
    
    // Global statistics
    public function getGlobalStats() {
        if ($this->useFallback) {
            return $this->getFallback()->getGlobalStats();
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        $totalUsers = $users->countDocuments();

        // Prefer CC logs for accurate global live/charged counts
        try {
            $ccLogs = $this->getCollection(DatabaseConfig::CC_LOGS_COLLECTION);
            $totalHits = $ccLogs->countDocuments([]);
            $totalCharged = $ccLogs->countDocuments(['status' => 'charged']);
            // Count 'live' and also treat 'approved' as live if present
            $totalLive = $ccLogs->countDocuments(['status' => 'live']) + $ccLogs->countDocuments(['status' => 'approved']);

            return [
                'total_users' => $totalUsers,
                'total_hits' => $totalHits,
                'total_charge_cards' => $totalCharged,
                'total_live_cards' => $totalLive
            ];
        } catch (Exception $e) {
            // Fallback to user_stats aggregation if CC logs not available
            $userStats = $this->getCollection(DatabaseConfig::USER_STATS_COLLECTION);
            $statsAgg = $userStats->aggregate([
                ['$group' => [
                    '_id' => null,
                    'total_hits' => ['$sum' => '$total_hits'],
                    'total_charge_cards' => ['$sum' => '$total_charge_cards'],
                    'total_live_cards' => ['$sum' => '$total_live_cards']
                ]]
            ])->toArray();
            $stats = $statsAgg[0] ?? [
                'total_hits' => 0,
                'total_charge_cards' => 0,
                'total_live_cards' => 0
            ];
            return [
                'total_users' => $totalUsers,
                'total_hits' => $stats['total_hits'],
                'total_charge_cards' => $stats['total_charge_cards'],
                'total_live_cards' => $stats['total_live_cards']
            ];
        }
    }
    
    // Admin Dashboard Stats
    public function getTotalUsersCount() {
        if ($this->useFallback) {
            $users = $this->getFallback()->loadData('users');
            return count($users);
        }
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        return $users->countDocuments();
    }

    public function getTotalCreditsClaimed() {
        if ($this->useFallback) {
            $claims = $this->getFallback()->loadData('daily_credit_claims');
            return count($claims);
        }
        $claims = $this->getCollection(DatabaseConfig::DAILY_CREDIT_CLAIMS_COLLECTION);
        return $claims->countDocuments();
    }

    public function getTotalToolUses() {
        if ($this->useFallback) {
            // Fallback does not currently log tool usage, returning 0.
            // To implement, create a 'tool_usage.json' and log to it.
            return 0;
        }
        $toolUsage = $this->getCollection(DatabaseConfig::TOOL_USAGE_COLLECTION);
        return $toolUsage->countDocuments();
    }

    public function logAuditAction($adminId, $action, $targetId = null, $details = []) {
        if ($this->useFallback) {
            return $this->getFallback()->logAuditAction($adminId, $action, $targetId, $details);
        }

        $auditLog = $this->getCollection('audit_logs');
        $auditLog->insertOne([
            'admin_id' => $adminId,
            'action' => $action,
            'target_id' => $targetId,
            'details' => $details,
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    public function getAuditLogs($limit = 50, $offset = 0) {
        if ($this->useFallback) {
            return $this->getFallback()->getAuditLogs($limit, $offset);
        }

        $auditLog = $this->getCollection('audit_logs');
        return $auditLog->find([], [
            'limit' => $limit, 
            'skip' => $offset, 
            'sort' => ['timestamp' => -1]
        ])->toArray();
    }

    // Tool usage logging (supports either numeric count/credits or details array)
    public function logToolUsage($userId, $toolName, $payload, $creditsUsed = null) {
        if ($this->useFallback) {
            // Fallback accepts only (userId, toolName, count, creditsUsed)
            $countVal = is_array($payload) ? ($payload['usage_count'] ?? 1) : $payload;
            $creditsVal = is_array($payload) ? ($payload['credits_used'] ?? ($creditsUsed ?? 0)) : ($creditsUsed ?? 0);
            return $this->getFallback()->logToolUsage($userId, $toolName, $countVal, $creditsVal);
        }
        
        $toolUsage = $this->getCollection(DatabaseConfig::TOOL_USAGE_COLLECTION);
        
        $usageData = [
            'user_id' => $userId,
            'tool_name' => $toolName,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        if (is_array($payload)) {
            $usageData['usage_count'] = $payload['usage_count'] ?? 1;
            $usageData['credits_used'] = $payload['credits_used'] ?? ($creditsUsed ?? 0);
            $usageData['details'] = $payload;
        } else {
            $usageData['usage_count'] = (int)$payload;
            $usageData['credits_used'] = (int)($creditsUsed ?? 0);
        }
        
        return $toolUsage->insertOne($usageData);
    }

    // Proxy management
    public function saveProxy(array $proxyData) {
        if ($this->useFallback) {
            return $this->getFallback()->saveProxy($proxyData);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            throw new Exception('Proxy collection unavailable');
        }

        $now = new MongoDB\BSON\UTCDateTime();
        $status = $proxyData['status'] ?? 'unknown';

        $update = [
            '$set' => [
                'proxy' => $proxyData['proxy'],
                'host' => $proxyData['host'],
                'port' => (int)$proxyData['port'],
                'username' => $proxyData['username'],
                'status' => $status,
                'ip' => $proxyData['ip'] ?? null,
                'country' => $proxyData['country'] ?? null,
                'city' => $proxyData['city'] ?? null,
                'latency_ms' => $proxyData['latency_ms'] ?? null,
                'last_check_message' => $proxyData['message'] ?? null,
                'last_check_at' => $now,
                'updated_at' => $now
            ],
            '$inc' => [
                'total_checks' => 1,
                'live_checks' => $status === 'live' ? 1 : 0,
                'dead_checks' => $status === 'live' ? 0 : 1
            ],
            '$setOnInsert' => [
                'created_at' => $now,
                'added_by' => $proxyData['added_by'] ?? null
            ]
        ];

        if (!empty($proxyData['tags']) && is_array($proxyData['tags'])) {
            $update['$set']['tags'] = array_values(array_unique($proxyData['tags']));
        }

        if ($status === 'live') {
            $update['$set']['last_seen_live_at'] = $now;
        }

        $collection->updateOne(
            ['proxy' => $proxyData['proxy']],
            $update,
            ['upsert' => true]
        );

        return $collection->findOne(['proxy' => $proxyData['proxy']]);
    }

    public function bulkSaveProxies(array $proxies) {
        $results = [];
        foreach ($proxies as $proxy) {
            try {
                $results[] = $this->saveProxy($proxy);
            } catch (Exception $e) {
                logError('Bulk proxy save failed', [
                    'proxy' => $proxy['proxy'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }
        return $results;
    }

    public function getProxies(array $filters = [], array $options = []) {
        if ($this->useFallback) {
            return $this->getFallback()->getProxies($filters, $options);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return [];
        }

        $query = [];

        if (!empty($filters['status'])) {
            $query['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $regex = new MongoDB\BSON\Regex($filters['search'], 'i');
            $query['$or'] = [
                ['proxy' => $regex],
                ['country' => $regex],
                ['ip' => $regex]
            ];
        }

        $limit = isset($options['limit']) ? (int)$options['limit'] : 100;
        $skip = isset($options['skip']) ? (int)$options['skip'] : 0;
        $sort = $options['sort'] ?? ['last_check_at' => -1];

        return $collection->find($query, [
            'limit' => $limit,
            'skip' => $skip,
            'sort' => $sort
        ])->toArray();
    }

    public function getProxyByString(string $proxy) {
        if ($this->useFallback) {
            return $this->getFallback()->getProxyByString($proxy);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return null;
        }

        return $collection->findOne(['proxy' => $proxy]);
    }

    public function getProxyById(string $id) {
        if ($this->useFallback) {
            return $this->getFallback()->getProxyById($id);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return null;
        }

        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
            $record = $collection->findOne(['_id' => $objectId]);
            if ($record) {
                return $record;
            }
        } catch (Exception $e) {
            // Fall through to lookup by proxy string
        }

        return $collection->findOne(['proxy' => $id]);
    }

    public function removeProxyById(string $id) {
        if ($this->useFallback) {
            return $this->getFallback()->removeProxyById($id);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return false;
        }

        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
            $result = $collection->deleteOne(['_id' => $objectId]);
            return $result->getDeletedCount() > 0;
        } catch (Exception $e) {
            // Allow fallback to string match on proxy field
            $result = $collection->deleteOne(['proxy' => $id]);
            return $result->getDeletedCount() > 0;
        }
    }

    public function removeDeadProxies(int $olderThanSeconds = 86400) {
        if ($this->useFallback) {
            return $this->getFallback()->removeDeadProxies($olderThanSeconds);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return 0;
        }

        $threshold = new MongoDB\BSON\UTCDateTime((time() - $olderThanSeconds) * 1000);
        $result = $collection->deleteMany([
            'status' => 'dead',
            'last_check_at' => ['$lte' => $threshold]
        ]);

        return $result->getDeletedCount();
    }

    public function getProxyStats() {
        if ($this->useFallback) {
            return $this->getFallback()->getProxyStats();
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return [
                'total' => 0,
                'live' => 0,
                'dead' => 0,
                'stale' => 0,
                'last_checked_at' => null
            ];
        }

        $total = $collection->countDocuments();
        $live = $collection->countDocuments(['status' => 'live']);
        $dead = $collection->countDocuments(['status' => 'dead']);

        $staleThreshold = new MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);
        $stale = $collection->countDocuments([
            'last_check_at' => ['$lt' => $staleThreshold]
        ]);

        $lastCheck = $collection->findOne([], [
            'projection' => ['last_check_at' => 1],
            'sort' => ['last_check_at' => -1]
        ]);

        return [
            'total' => $total,
            'live' => $live,
            'dead' => $dead,
            'stale' => $stale,
            'last_checked_at' => $lastCheck['last_check_at'] ?? null
        ];
    }
    
    public function redeemCredits($userId, $credits, $xcoinCost) {
        if ($this->useFallback) {
            return $this->getFallback()->redeemCredits($userId, $credits, $xcoinCost);
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        
        return $users->updateOne(
            ['telegram_id' => $userId],
            [
                '$inc' => [
                    'credits' => $credits,
                    'xcoin_balance' => -$xcoinCost
                ],
                '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
            ]
        );
    }
    
    public function upgradeMembership($userId, $plan, $xcoinCost) {
        if ($this->useFallback) {
            return $this->getFallback()->upgradeMembership($userId, $plan, $xcoinCost);
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        
        // Set daily credit amounts based on plan
        $dailyCredits = [
            'premium' => 25,
            'vip' => 50
        ];
        
        return $users->updateOne(
            ['telegram_id' => $userId],
            [
                '$set' => [
                    'role' => $plan,
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ],
                '$inc' => [
                    'xcoin_balance' => -$xcoinCost,
                    'credits' => $dailyCredits[$plan] ?? 0
                ]
            ]
        );
    }
}
?>
