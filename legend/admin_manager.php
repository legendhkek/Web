<?php
/**
 * Admin Management System
 * Handles dynamic admin role management
 */

require_once 'config.php';
require_once 'database.php';

class AdminManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Check if user is owner
     */
    public function isOwner($telegram_id) {
        return in_array($telegram_id, AppConfig::OWNER_IDS);
    }
    
    /**
     * Check if user is admin (includes owner)
     */
    public function isAdmin($telegram_id) {
        if ($this->isOwner($telegram_id)) {
            return true;
        }
        
        // Check static admin list
        if (in_array($telegram_id, AppConfig::ADMIN_IDS)) {
            return true;
        }
        
        // Check dynamic admin roles in database
        return $this->hasAdminRole($telegram_id);
    }
    
    /**
     * Check if user has admin role in database
     */
    private function hasAdminRole($telegram_id) {
        try {
            if ($this->db->useFallback ?? false) {
                $admins = $this->loadAdminsFallback();
                return isset($admins[$telegram_id]) && $admins[$telegram_id]['status'] === 'active';
            }
            
            $collection = $this->db->getCollection(DatabaseConfig::ADMIN_ROLES_COLLECTION);
            $admin = $collection->findOne([
                'telegram_id' => $telegram_id,
                'status' => 'active'
            ]);
            
            return $admin !== null;
        } catch (Exception $e) {
            logError('Error checking admin role: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add new admin (only owner can do this)
     */
    public function addAdmin($telegram_id, $username = null, $added_by_id) {
        // Verify owner permission
        if (!$this->isOwner($added_by_id)) {
            return ['success' => false, 'message' => 'Only owner can add admins'];
        }
        
        // Check if already admin
        if ($this->isAdmin($telegram_id)) {
            return ['success' => false, 'message' => 'User is already an admin'];
        }
        
        try {
            $adminData = [
                'telegram_id' => $telegram_id,
                'username' => $username,
                'added_by' => $added_by_id,
                'status' => 'active',
                'permissions' => [
                    'manage_users' => true,
                    'generate_credits' => true,
                    'broadcast' => true,
                    'view_stats' => true,
                    'manage_tools' => true
                ],
                'added_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            if ($this->db->useFallback ?? false) {
                $this->saveAdminFallback($telegram_id, $adminData);
            } else {
                $collection = $this->db->getCollection(DatabaseConfig::ADMIN_ROLES_COLLECTION);
                $collection->insertOne($adminData);
            }
            
            // Update user role in users collection
            $this->db->updateUserRole($telegram_id, AppConfig::ROLE_ADMIN);
            
            return [
                'success' => true,
                'message' => 'Admin added successfully',
                'data' => $adminData
            ];
        } catch (Exception $e) {
            logError('Error adding admin: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove admin (only owner can do this)
     */
    public function removeAdmin($telegram_id, $removed_by_id) {
        // Verify owner permission
        if (!$this->isOwner($removed_by_id)) {
            return ['success' => false, 'message' => 'Only owner can remove admins'];
        }
        
        // Cannot remove owner
        if ($this->isOwner($telegram_id)) {
            return ['success' => false, 'message' => 'Cannot remove owner'];
        }
        
        try {
            if ($this->db->useFallback ?? false) {
                $this->removeAdminFallback($telegram_id);
            } else {
                $collection = $this->db->getCollection(DatabaseConfig::ADMIN_ROLES_COLLECTION);
                $collection->updateOne(
                    ['telegram_id' => $telegram_id],
                    ['$set' => [
                        'status' => 'removed',
                        'removed_by' => $removed_by_id,
                        'removed_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }
            
            // Update user role back to free
            $this->db->updateUserRole($telegram_id, AppConfig::ROLE_FREE);
            
            return ['success' => true, 'message' => 'Admin removed successfully'];
        } catch (Exception $e) {
            logError('Error removing admin: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all active admins
     */
    public function getAllAdmins() {
        try {
            if ($this->db->useFallback ?? false) {
                return $this->loadAdminsFallback();
            }
            
            $collection = $this->db->getCollection(DatabaseConfig::ADMIN_ROLES_COLLECTION);
            $admins = $collection->find(['status' => 'active'])->toArray();
            
            // Also include static admins
            $allAdmins = [];
            foreach (AppConfig::ADMIN_IDS as $admin_id) {
                $allAdmins[] = [
                    'telegram_id' => $admin_id,
                    'type' => 'static',
                    'status' => 'active'
                ];
            }
            
            foreach (AppConfig::OWNER_IDS as $owner_id) {
                $allAdmins[] = [
                    'telegram_id' => $owner_id,
                    'username' => AppConfig::OWNER_USERNAME,
                    'type' => 'owner',
                    'status' => 'active'
                ];
            }
            
            foreach ($admins as $admin) {
                $allAdmins[] = [
                    'telegram_id' => $admin['telegram_id'],
                    'username' => $admin['username'] ?? 'Unknown',
                    'type' => 'dynamic',
                    'status' => $admin['status'],
                    'added_at' => $admin['added_at']
                ];
            }
            
            return $allAdmins;
        } catch (Exception $e) {
            logError('Error getting admins: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fallback methods for file-based storage
     */
    private function loadAdminsFallback() {
        $file = __DIR__ . '/data/admin_roles.json';
        if (!file_exists($file)) {
            return [];
        }
        return json_decode(file_get_contents($file), true) ?? [];
    }
    
    private function saveAdminFallback($telegram_id, $adminData) {
        $admins = $this->loadAdminsFallback();
        $admins[$telegram_id] = $adminData;
        
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        file_put_contents($dir . '/admin_roles.json', json_encode($admins, JSON_PRETTY_PRINT));
    }
    
    private function removeAdminFallback($telegram_id) {
        $admins = $this->loadAdminsFallback();
        if (isset($admins[$telegram_id])) {
            $admins[$telegram_id]['status'] = 'removed';
            $admins[$telegram_id]['removed_at'] = date('c');
        }
        
        $dir = __DIR__ . '/data';
        file_put_contents($dir . '/admin_roles.json', json_encode($admins, JSON_PRETTY_PRINT));
    }
}
?>
