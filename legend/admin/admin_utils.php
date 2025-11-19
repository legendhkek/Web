<?php
/**
 * Admin Utility Functions
 * This file contains common functions used across admin pages
 */

/**
 * Format date in a user-friendly way
 * @param int $timestamp Unix timestamp
 * @return string Formatted date
 */
if (!function_exists('formatDate')) {
    function formatDate($timestamp) {
        if (!$timestamp) return 'Never';
        
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }
}

/**
 * Format file size in human readable format
 * @param int $bytes Size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Generate random string
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $string;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize input (admin version - compatible with utils.php version)
 * @param string $input Input to sanitize
 * @param string $type Type of input (for compatibility with utils.php version)
 * @return string Sanitized input
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input, $type = 'string') {
        // Use the full version from utils.php if available, otherwise simple version
        if ($type !== 'string') {
            // Handle different types like utils.php does
            switch ($type) {
                case 'int':
                    return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                case 'float':
                    return (float)filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                case 'email':
                    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
                case 'url':
                    return filter_var(trim($input), FILTER_SANITIZE_URL);
                case 'alphanumeric':
                    return preg_replace('/[^a-zA-Z0-9]/', '', $input);
                default:
                    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            }
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Get user status badge
 * @param string $status User status
 * @return string HTML badge
 */
function getUserStatusBadge($status) {
    $status_colors = [
        'active' => 'success',
        'banned' => 'danger',
        'suspended' => 'warning',
        'pending' => 'info',
        'inactive' => 'secondary'
    ];
    
    $color = $status_colors[$status] ?? 'secondary';
    return "<span class='badge bg-{$color}'>" . ucfirst($status) . "</span>";
}

/**
 * Get credit balance badge
 * @param int $credits Credit amount
 * @return string HTML badge
 */
function getCreditBadge($credits) {
    if ($credits >= 100) {
        $color = 'success';
    } elseif ($credits >= 50) {
        $color = 'info';
    } elseif ($credits >= 20) {
        $color = 'warning';
    } else {
        $color = 'danger';
    }
    
    return "<span class='badge bg-{$color}'>{$credits} Credits</span>";
}

/**
 * Check if user is premium
 * @param array $user User data
 * @return bool True if premium
 */
function isPremiumUser($user) {
    return isset($user['role']) && in_array($user['role'], ['premium', 'vip', 'admin', 'owner']);
}

/**
 * Get premium level
 * @param array $user User data
 * @return string Premium level
 */
function getPremiumLevel($user) {
    if (isset($user['role'])) {
        switch ($user['role']) {
            case 'owner':
                return 'Owner';
            case 'admin':
                return 'Admin';
            case 'vip':
                return 'VIP';
            case 'premium':
                return 'Premium';
            default:
                return 'Free';
        }
    }
    return 'Free';
}

/**
 * Format currency
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @return string Formatted currency
 */
function formatCurrency($amount, $currency = 'USD') {
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * Get time ago in seconds
 * @param int $timestamp Unix timestamp
 * @return int Seconds ago
 */
function getTimeAgo($timestamp) {
    return time() - $timestamp;
}

/**
 * Check if timestamp is recent (within last 24 hours)
 * @param int $timestamp Unix timestamp
 * @return bool True if recent
 */
function isRecent($timestamp) {
    return (time() - $timestamp) < 86400;
}

/**
 * Get user activity status
 * @param int $lastActivity Last activity timestamp
 * @return string Activity status
 */
function getUserActivityStatus($lastActivity) {
    if (!$lastActivity) return 'Never';
    
    $timeAgo = getTimeAgo($lastActivity);
    
    if ($timeAgo < 300) { // 5 minutes
        return '<span class="text-success">🟢 Online</span>';
    } elseif ($timeAgo < 3600) { // 1 hour
        return '<span class="text-warning">🟡 Recently Active</span>';
    } elseif ($timeAgo < 86400) { // 24 hours
        return '<span class="text-info">🔵 Today</span>';
    } else {
        return '<span class="text-muted">⚫ Inactive</span>';
    }
}
?>
