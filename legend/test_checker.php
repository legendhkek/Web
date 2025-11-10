<?php
require_once 'CardChecker.php';

echo "Testing CardChecker functionality...\n\n";

// Test 1: Initialize CardChecker
try {
    $checker = new CardChecker();
    echo "✓ CardChecker initialized successfully\n";
} catch (Exception $e) {
    echo "✗ CardChecker initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Test card validation
$testCard = "4242424242424242|12|2025|123";
$testSite = "https://kokorosastudio.com";

echo "\nTesting card: $testCard\n";
echo "Testing site: $testSite\n\n";

try {
    $result = $checker->checkCard($testCard, $testSite);
    
    if ($result) {
        echo "✓ Card check completed successfully\n";
        echo "Status: " . $result['status'] . "\n";
        echo "Gateway: " . $result['gateway'] . "\n";
        echo "Price: $" . $result['price'] . "\n";
        echo "Time: " . $result['time'] . "\n";
        echo "UI Status: " . $result['ui_status_type'] . "\n";
    } else {
        echo "✗ Card check returned null/false\n";
    }
} catch (Exception $e) {
    echo "✗ Card check failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test completed.\n";
?>
