<?php
/**
 * Stripe Site Manager
 * Manages sites for Stripe Auth Checker with rotation
 */

class StripeSiteManager {
    private static $sitesFile = __DIR__ . '/stripe_sites.json';
    
    /**
     * Get all sites
     */
    public static function getSites() {
        if (!file_exists(self::$sitesFile)) {
            self::initializeDefaultSites();
        }
        
        $data = json_decode(file_get_contents(self::$sitesFile), true);
        return $data['sites'] ?? [];
    }
    
    /**
     * Get rotation count
     */
    public static function getRotationCount() {
        if (!file_exists(self::$sitesFile)) {
            return 20;
        }
        
        $data = json_decode(file_get_contents(self::$sitesFile), true);
        return $data['rotation_count'] ?? 20;
    }
    
    /**
     * Get next site based on rotation
     */
    public static function getNextSite($checkNumber = 0) {
        $sites = self::getSites();
        if (empty($sites)) {
            return null;
        }
        
        $rotationCount = self::getRotationCount();
        $siteIndex = floor($checkNumber / $rotationCount) % count($sites);
        
        return $sites[$siteIndex];
    }
    
    /**
     * Get random site
     */
    public static function getRandomSite() {
        $sites = self::getSites();
        if (empty($sites)) {
            return null;
        }
        
        return $sites[array_rand($sites)];
    }
    
    /**
     * Add site
     */
    public static function addSite($site) {
        $data = self::loadData();
        
        // Clean site URL
        $site = trim($site);
        $site = preg_replace('#^https?://#', '', $site);
        $site = rtrim($site, '/');
        
        // Check if already exists
        if (in_array($site, $data['sites'])) {
            return false;
        }
        
        $data['sites'][] = $site;
        $data['last_updated'] = date('Y-m-d');
        
        return self::saveData($data);
    }
    
    /**
     * Remove site
     */
    public static function removeSite($site) {
        $data = self::loadData();
        
        // Clean site URL
        $site = trim($site);
        $site = preg_replace('#^https?://#', '', $site);
        $site = rtrim($site, '/');
        
        $key = array_search($site, $data['sites']);
        if ($key === false) {
            return false;
        }
        
        unset($data['sites'][$key]);
        $data['sites'] = array_values($data['sites']); // Re-index array
        $data['last_updated'] = date('Y-m-d');
        
        return self::saveData($data);
    }
    
    /**
     * Get site count
     */
    public static function getSiteCount() {
        return count(self::getSites());
    }
    
    /**
     * Update rotation count
     */
    public static function updateRotationCount($count) {
        $data = self::loadData();
        $data['rotation_count'] = max(1, min(100, (int)$count)); // Between 1-100
        $data['last_updated'] = date('Y-m-d');
        
        return self::saveData($data);
    }
    
    /**
     * Load data from file
     */
    private static function loadData() {
        if (!file_exists(self::$sitesFile)) {
            self::initializeDefaultSites();
        }
        
        return json_decode(file_get_contents(self::$sitesFile), true);
    }
    
    /**
     * Save data to file
     */
    private static function saveData($data) {
        return file_put_contents(
            self::$sitesFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }
    
    /**
     * Initialize default sites
     */
    private static function initializeDefaultSites() {
        // This will be called if file doesn't exist
        // File should already exist from our JSON file
        $defaultData = [
            'sites' => [],
            'rotation_count' => 20,
            'last_updated' => date('Y-m-d')
        ];
        
        self::saveData($defaultData);
    }
}
?>
