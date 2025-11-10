<?php
// MongoDB Installation Guide and System Check

echo "<h1>MongoDB PHP Extension Installation Guide</h1>";

// Check current PHP version
echo "<h2>System Information</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Operating System:</strong> " . PHP_OS . "</p>";
echo "<p><strong>Architecture:</strong> " . (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit') . "</p>";

// Check if MongoDB extension is loaded
echo "<h2>MongoDB Extension Status</h2>";
if (extension_loaded('mongodb')) {
    echo "<p style='color: green;'><strong>✅ MongoDB extension is installed and loaded!</strong></p>";
    $version = phpversion('mongodb');
    echo "<p><strong>Version:</strong> " . $version . "</p>";
} else {
    echo "<p style='color: red;'><strong>❌ MongoDB extension is NOT installed</strong></p>";
    
    echo "<h3>Installation Instructions for Windows (XAMPP):</h3>";
    echo "<ol>";
    echo "<li><strong>Download MongoDB PHP Extension:</strong><br>";
    echo "Visit: <a href='https://pecl.php.net/package/mongodb' target='_blank'>https://pecl.php.net/package/mongodb</a><br>";
    echo "Download the appropriate version for PHP " . phpversion() . " (" . (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit') . ")</li>";
    
    echo "<li><strong>Extract and Copy:</strong><br>";
    echo "Extract the downloaded file and copy <code>php_mongodb.dll</code> to:<br>";
    echo "<code>" . PHP_EXTENSION_DIR . "</code></li>";
    
    echo "<li><strong>Enable Extension:</strong><br>";
    echo "Add this line to your php.ini file:<br>";
    echo "<code>extension=mongodb</code></li>";
    
    echo "<li><strong>Restart Apache:</strong><br>";
    echo "Restart your XAMPP Apache server</li>";
    echo "</ol>";
    
    echo "<h3>Alternative: Use Composer (Recommended)</h3>";
    echo "<p>You can also install MongoDB PHP Library via Composer:</p>";
    echo "<pre>composer require mongodb/mongodb</pre>";
}

// Check if composer autoload exists
echo "<h2>Composer Status</h2>";
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    echo "<p style='color: green;'><strong>✅ Composer autoload found</strong></p>";
    echo "<p>Path: " . $composerAutoload . "</p>";
} else {
    echo "<p style='color: orange;'><strong>⚠️ Composer autoload not found</strong></p>";
    echo "<p>To install MongoDB library via Composer:</p>";
    echo "<pre>cd " . __DIR__ . "\ncomposer init\ncomposer require mongodb/mongodb</pre>";
}

// Test fallback system
echo "<h2>Fallback Database Test</h2>";
try {
    require_once 'database_fallback.php';
    $fallback = DatabaseFallback::getInstance();
    echo "<p style='color: green;'><strong>✅ Fallback database system is working</strong></p>";
    
    // Test data directory
    $dataDir = __DIR__ . '/data/';
    if (is_dir($dataDir)) {
        echo "<p><strong>Data directory:</strong> " . $dataDir . " (exists)</p>";
    } else {
        echo "<p><strong>Data directory:</strong> " . $dataDir . " (will be created)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Fallback system error:</strong> " . $e->getMessage() . "</p>";
}

echo "<h2>Current Status</h2>";
if (extension_loaded('mongodb') || file_exists($composerAutoload)) {
    echo "<p style='color: green;'><strong>✅ Your system can use MongoDB</strong></p>";
} else {
    echo "<p style='color: blue;'><strong>ℹ️ System will use JSON file fallback database</strong></p>";
    echo "<p>This is perfectly fine for development and testing!</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>← Back to Login</a> | <a href='telegram_debug.php'>Telegram Debug →</a></p>";
?>
