<?php
// Debug Admin Access Issues
// This script will help identify why admin access is not working

require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Admin Access Debug Information</h2>";
echo "<hr>";

// 1. Check Session Data
echo "<h3>1. Session Data:</h3>";
if (empty($_SESSION)) {
    echo "<p style='color: red;'>❌ No session data found</p>";
} else {
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
}

// 2. Check Config Constants
echo "<h3>2. Configuration Constants:</h3>";
echo "<p><strong>ADMIN_IDS:</strong> " . json_encode(AppConfig::ADMIN_IDS) . "</p>";
echo "<p><strong>OWNER_IDS:</strong> " . json_encode(AppConfig::OWNER_IDS) . "</p>";

// 3. Check Database Connection
echo "<h3>3. Database Connection:</h3>";
try {
    $db = Database::getInstance();
    if ($db) {
        echo "<p style='color: green;'>✅ Database connection successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// 4. Check User Data
echo "<h3>4. User Data Check:</h3>";
if (!empty($_SESSION['user_id'])) {
    echo "<p><strong>Session user_id:</strong> " . $_SESSION['user_id'] . "</p>";
    
    try {
        // Try to get user by ID
        $user = $db->getUserById($_SESSION['user_id']);
        if ($user) {
            echo "<p style='color: green;'>✅ User found by ID</p>";
            echo "<pre>" . print_r($user, true) . "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ User not found by ID</p>";
        }
        
        // Try to get user by Telegram ID
        $userByTelegram = $db->getUserByTelegramId($_SESSION['user_id']);
        if ($userByTelegram) {
            echo "<p style='color: green;'>✅ User found by Telegram ID</p>";
            echo "<pre>" . print_r($userByTelegram, true) . "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ User not found by Telegram ID</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error getting user: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ No user_id in session</p>";
}

// 5. Check Telegram ID
echo "<h3>5. Telegram ID Check:</h3>";
if (!empty($_SESSION['telegram_id'])) {
    echo "<p><strong>Session telegram_id:</strong> " . $_SESSION['telegram_id'] . "</p>";
    
    try {
        $userByTelegram = $db->getUserByTelegramId($_SESSION['telegram_id']);
        if ($userByTelegram) {
            echo "<p style='color: green;'>✅ User found by session telegram_id</p>";
            echo "<pre>" . print_r($userByTelegram, true) . "</pre>";
        } else {
            echo "<p style='color: orange;'>⚠️ User not found by session telegram_id</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error getting user by telegram_id: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ No telegram_id in session</p>";
}

// 6. Access Check Simulation
echo "<h3>6. Access Check Simulation:</h3>";
if (!empty($_SESSION['user_id']) || !empty($_SESSION['telegram_id'])) {
    $telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
    
    $admin_ids = AppConfig::ADMIN_IDS;
    $owner_ids = AppConfig::OWNER_IDS;
    
    $is_admin = in_array($telegram_id, $admin_ids);
    $is_owner = in_array($telegram_id, $owner_ids);
    
    echo "<p><strong>Your Telegram ID:</strong> " . $telegram_id . "</p>";
    echo "<p><strong>Is Admin:</strong> " . ($is_admin ? '✅ Yes' : '❌ No') . "</p>";
    echo "<p><strong>Is Owner:</strong> " . ($is_owner ? '✅ Yes' : '❌ No') . "</p>";
    
    if (!$is_admin && !$is_owner) {
        echo "<p style='color: red;'>❌ Your Telegram ID is not in ADMIN_IDS or OWNER_IDS arrays</p>";
        echo "<p><strong>Solution:</strong> Add your Telegram ID to the config.php file</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Cannot check access - no user identification found</p>";
}

// 7. Quick Fix Option
echo "<h3>7. Quick Fix:</h3>";
echo "<p>If you want to quickly add your Telegram ID as an owner, you can:</p>";
echo "<ol>";
echo "<li>Find your Telegram ID from the session data above</li>";
echo "<li>Edit the config.php file</li>";
echo "<li>Add your ID to the OWNER_IDS array</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='admin_access.php'>← Back to Admin Access</a></p>";
echo "<p><a href='../dashboard.php'>← Back to Dashboard</a></p>";
?>
