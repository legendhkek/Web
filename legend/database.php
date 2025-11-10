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
            
            // MongoDB connection using MongoDB PHP Library
            $this->client = new MongoDB\Client(DatabaseConfig::MONGODB_URI);
            $this->database = $this->client->selectDatabase(DatabaseConfig::DATABASE_NAME);
            
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
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        return $users->updateOne(
            ['telegram_id' => $telegramId, 'credits' => ['$gte' => $amount]],
            ['$inc' => ['credits' => -$amount], '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]]
        )->getModifiedCount() > 0;
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
            return;
        }
        
        $users = $this->getCollection(DatabaseConfig::USERS_COLLECTION);
        $users->updateOne(
            ['telegram_id' => $telegramId],
            ['$inc' => ['credits' => $amount], '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]]
        );
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

    /* --------------------------------------------------------------------------
     | Proxy Manager APIs
     |--------------------------------------------------------------------------*/

    public function getUserProxies($telegramId, $filters = []) {
        if ($this->useFallback) {
            return $this->getFallback()->getUserProxies($telegramId, $filters);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return [];
        }

        $query = ['user_id' => $telegramId];
        if (isset($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query['status'] = ['$in' => array_values(array_unique(array_map('strtolower', $statuses)))];
        }

        $cursor = $collection->find($query, [
            'sort' => ['created_at' => -1]
        ]);

        $results = [];
        foreach ($cursor as $doc) {
            if ($normalized = $this->normalizeProxyDocument($doc)) {
                $results[] = $normalized;
            }
        }
        return $results;
    }

    public function getProxyById($telegramId, $proxyId) {
        if ($this->useFallback) {
            return $this->getFallback()->getProxyById($telegramId, $proxyId);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return null;
        }

        $objectId = $this->toObjectId($proxyId);
        if (!$objectId) {
            return null;
        }

        $doc = $collection->findOne([
            '_id' => $objectId,
            'user_id' => $telegramId
        ]);

        return $this->normalizeProxyDocument($doc);
    }

    public function addUserProxy($telegramId, $proxyString, $label = null, $metadata = []) {
        $proxy = trim((string)$proxyString);
        if ($proxy === '') {
            return [
                'status' => 'error',
                'message' => 'Proxy value cannot be empty'
            ];
        }

        if ($this->useFallback) {
            return $this->getFallback()->addUserProxy($telegramId, $proxy, $label, $metadata);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return [
                'status' => 'error',
                'message' => 'Proxy storage is unavailable'
            ];
        }

        $existing = $collection->findOne([
            'user_id' => $telegramId,
            'proxy' => $proxy
        ]);

        if ($existing) {
            $updates = array_merge(
                ['updated_at' => new MongoDB\BSON\UTCDateTime()],
                $this->prepareProxyMetadata($metadata)
            );

            if ($label !== null && $label !== '') {
                $updates['label'] = $label;
            }

            if (!empty($updates)) {
                $collection->updateOne(
                    ['_id' => $existing['_id']],
                    ['$set' => $updates]
                );
                $existing = $collection->findOne(['_id' => $existing['_id']]);
            }

            return [
                'status' => 'exists',
                'proxy' => $this->normalizeProxyDocument($existing)
            ];
        }

        $now = new MongoDB\BSON\UTCDateTime();

        $document = array_merge([
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
            'created_at' => $now,
            'updated_at' => $now
        ], $this->prepareProxyMetadata($metadata));

        $result = $collection->insertOne($document);
        $document['_id'] = $result->getInsertedId();

        return [
            'status' => 'created',
            'proxy' => $this->normalizeProxyDocument($document)
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
        $status = strtolower((string)$status);
        if ($status === '') {
            $status = 'unknown';
        }

        if ($this->useFallback) {
            return $this->getFallback()->updateProxyStatus($telegramId, $proxyId, $status, $metadata);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return [
                'status' => 'error',
                'message' => 'Proxy storage is unavailable'
            ];
        }

        $objectId = $this->toObjectId($proxyId);
        if (!$objectId) {
            return [
                'status' => 'error',
                'message' => 'Invalid proxy identifier'
            ];
        }

        $updates = array_merge(
            [
                'status' => $status,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            $this->prepareProxyMetadata($metadata)
        );

        if (!array_key_exists('last_checked_at', $metadata)) {
            $updates['last_checked_at'] = new MongoDB\BSON\UTCDateTime();
        }

        $result = $collection->updateOne(
            [
                '_id' => $objectId,
                'user_id' => $telegramId
            ],
            [
                '$set' => $updates
            ]
        );

        if ($result->getMatchedCount() === 0) {
            return [
                'status' => 'error',
                'message' => 'Proxy not found'
            ];
        }

        $doc = $collection->findOne(['_id' => $objectId]);
        return [
            'status' => 'updated',
            'proxy' => $this->normalizeProxyDocument($doc)
        ];
    }

    public function deleteUserProxies($telegramId, array $proxyIds) {
        if ($this->useFallback) {
            return $this->getFallback()->deleteUserProxies($telegramId, $proxyIds);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return 0;
        }

        $objectIds = [];
        foreach ($proxyIds as $id) {
            $objectId = $this->toObjectId($id);
            if ($objectId) {
                $objectIds[] = $objectId;
            }
        }

        if (empty($objectIds)) {
            return 0;
        }

        $result = $collection->deleteMany([
            '_id' => ['$in' => $objectIds],
            'user_id' => $telegramId
        ]);

        return $result->getDeletedCount();
    }

    public function recordProxyUsage($telegramId, $proxyId, $success, $latencyMs = null) {
        if ($this->useFallback) {
            return $this->getFallback()->recordProxyUsage($telegramId, $proxyId, $success, $latencyMs);
        }

        $collection = $this->getCollection(DatabaseConfig::PROXIES_COLLECTION);
        if (!$collection) {
            return false;
        }

        $objectId = $this->toObjectId($proxyId);
        if (!$objectId) {
            return false;
        }

        $update = [
            '$inc' => [
                'usage.total_checks' => 1
            ],
            '$set' => [
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'last_used_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ];

        if ($success) {
            $update['$inc']['usage.successful_checks'] = 1;
        }

        if ($latencyMs !== null) {
            $update['$set']['latency_ms'] = (float)$latencyMs;
        }

        $collection->updateOne(
            [
                '_id' => $objectId,
                'user_id' => $telegramId
            ],
            $update
        );

        return true;
    }

    private function normalizeProxyDocument($doc) {
        if (empty($doc)) {
            return null;
        }

        if ($doc instanceof MongoDB\Model\BSONDocument) {
            $doc = $doc->getArrayCopy();
        }

        $usage = $doc['usage'] ?? [];
        $id = $doc['id'] ?? ($doc['_id'] ?? null);

        if ($id instanceof MongoDB\BSON\ObjectId) {
            $id = (string)$id;
        }

        return [
            'id' => $id,
            'proxy' => $doc['proxy'] ?? '',
            'label' => $doc['label'] ?? null,
            'status' => $doc['status'] ?? 'unknown',
            'latency_ms' => isset($doc['latency_ms']) ? (float)$doc['latency_ms'] : null,
            'country' => $doc['country'] ?? null,
            'ip_address' => $doc['ip_address'] ?? null,
            'last_checked_at' => $this->convertDateValue($doc['last_checked_at'] ?? null),
            'last_used_at' => $this->convertDateValue($doc['last_used_at'] ?? null),
            'created_at' => $this->convertDateValue($doc['created_at'] ?? null),
            'updated_at' => $this->convertDateValue($doc['updated_at'] ?? null),
            'last_error' => $doc['last_error'] ?? null,
            'usage' => [
                'total_checks' => (int)($usage['total_checks'] ?? 0),
                'successful_checks' => (int)($usage['successful_checks'] ?? 0)
            ]
        ];
    }

    private function prepareProxyMetadata(array $metadata) {
        $prepared = [];

        if (isset($metadata['status']) && is_string($metadata['status'])) {
            $prepared['status'] = strtolower($metadata['status']);
        }

        if (isset($metadata['latency_ms']) && $metadata['latency_ms'] !== null && $metadata['latency_ms'] !== '') {
            $prepared['latency_ms'] = (float)$metadata['latency_ms'];
        }

        if (isset($metadata['country']) && $metadata['country'] !== '') {
            $prepared['country'] = (string)$metadata['country'];
        }

        if (isset($metadata['ip_address']) && $metadata['ip_address'] !== '') {
            $prepared['ip_address'] = (string)$metadata['ip_address'];
        }

        if (array_key_exists('last_checked_at', $metadata)) {
            $date = $this->nullableMongoDate($metadata['last_checked_at']);
            if ($date !== null) {
                $prepared['last_checked_at'] = $date;
            } else {
                $prepared['last_checked_at'] = null;
            }
        }

        if (array_key_exists('last_used_at', $metadata)) {
            $date = $this->nullableMongoDate($metadata['last_used_at']);
            if ($date !== null) {
                $prepared['last_used_at'] = $date;
            } else {
                $prepared['last_used_at'] = null;
            }
        }

        if (isset($metadata['last_error'])) {
            $prepared['last_error'] = (string)$metadata['last_error'];
        }

        if (isset($metadata['usage']) && is_array($metadata['usage'])) {
            $usage = [];
            if (isset($metadata['usage']['total_checks'])) {
                $usage['total_checks'] = (int)$metadata['usage']['total_checks'];
            }
            if (isset($metadata['usage']['successful_checks'])) {
                $usage['successful_checks'] = (int)$metadata['usage']['successful_checks'];
            }
            if (!empty($usage)) {
                $prepared['usage'] = array_merge([
                    'total_checks' => 0,
                    'successful_checks' => 0
                ], $usage);
            }
        }

        return $prepared;
    }

    private function convertDateValue($value) {
        if ($value instanceof MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format(DATE_ATOM);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_numeric($value)) {
            return date(DATE_ATOM, (int)$value);
        }

        if (is_string($value) && $value !== '') {
            // Assume already formatted string
            return $value;
        }

        return null;
    }

    private function nullableMongoDate($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof MongoDB\BSON\UTCDateTime) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return new MongoDB\BSON\UTCDateTime($value);
        }

        if (is_numeric($value)) {
            return new MongoDB\BSON\UTCDateTime(((int)$value) * 1000);
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return new MongoDB\BSON\UTCDateTime($timestamp * 1000);
            }
        }

        return null;
    }

    private function toObjectId($value) {
        if ($value instanceof MongoDB\BSON\ObjectId) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return new MongoDB\BSON\ObjectId($value);
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function generateProxyLabel($proxy) {
        $hash = strtoupper(substr(hash('crc32b', $proxy), 0, 6));
        return 'Proxy ' . $hash;
    }
}
?>
