<?php
// Simple debug script to test what's wrong with credit_claim.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing credit_claim.php issues...\n\n";

// Test basic file access
echo "1. Testing file access:\n";
if (file_exists('credit_claim.php')) {
    echo "✓ credit_claim.php exists\n";
} else {
    echo "❌ credit_claim.php not found\n";
}

// Test required files
echo "\n2. Testing required files:\n";
$required_files = ['config.php', 'database.php', 'auth.php', 'utils.php'];
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

// Test session
echo "\n3. Testing session:\n";
session_start();
echo "Session status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session data: " . print_r($_SESSION, true) . "\n";

// Test if we can include the required files
echo "\n4. Testing file includes:\n";
try {
    require_once 'config.php';
    echo "✓ config.php loaded\n";
} catch (Exception $e) {
    echo "❌ config.php error: " . $e->getMessage() . "\n";
}

try {
    require_once 'database.php';
    echo "✓ database.php loaded\n";
} catch (Exception $e) {
    echo "❌ database.php error: " . $e->getMessage() . "\n";
}

try {
    require_once 'auth.php';
    echo "✓ auth.php loaded\n";
} catch (Exception $e) {
    echo "❌ auth.php error: " . $e->getMessage() . "\n";
}

// Test database connection
echo "\n5. Testing database:\n";
try {
    $db = Database::getInstance();
    echo "✓ Database instance created\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Test authentication
echo "\n6. Testing authentication:\n";
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    echo "❌ No user session found\n";
    echo "   This is likely why credit_claim.php redirects to login.php\n";
} else {
    echo "✓ User session exists\n";
    echo "   User ID: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
    echo "   Telegram ID: " . ($_SESSION['telegram_id'] ?? 'not set') . "\n";
}

echo "\n=== SOLUTION ===\n";
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    echo "You need to login first before accessing credit_claim.php\n";
    echo "Try accessing: https://85366f4b8181.ngrok-free.app/login.php\n";
} else {
    echo "Sessions are working, there might be another issue.\n";
}
?>