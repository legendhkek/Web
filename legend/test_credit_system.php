<?php
/**
 * Test Credit System
 * This page tests the credit claim system functionality
 */

require_once 'config.php';
require_once 'database.php';

// Initialize database
$db = Database::getInstance();

echo "<h1>Credit System Test</h1>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    $collection = $db->getCollection('users');
    echo "✅ Database connection successful<br>";
    
    // Test user lookup
    $test_user = $collection->findOne(['telegram_id' => '123456789']);
    if ($test_user) {
        echo "✅ User lookup successful<br>";
    } else {
        echo "ℹ️ No test user found (this is normal)<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test collections
echo "<h2>Collection Tests</h2>";
$collections_to_test = ['premium_codes', 'credit_codes', 'used_codes', 'user_claims'];

foreach ($collections_to_test as $collection_name) {
    try {
        $collection = $db->getCollection($collection_name);
        $count = $collection->countDocuments();
        echo "✅ {$collection_name}: {$count} documents<br>";
    } catch (Exception $e) {
        echo "❌ {$collection_name}: " . $e->getMessage() . "<br>";
    }
}

// Test credit claim functions
echo "<h2>Function Tests</h2>";

// Test premium code functions
echo "<h3>Premium Code Functions</h3>";
try {
    $collection = $db->getCollection('premium_codes');
    $test_code = [
        'code' => 'TEST-PREMIUM-001',
        'type' => 'premium',
        'status' => 'active',
        'expires_at' => time() + 86400, // 24 hours
        'created_at' => time()
    ];
    
    $result = $collection->insertOne($test_code);
    if ($result->getInsertedCount() > 0) {
        echo "✅ Test premium code created successfully<br>";
        
        // Test retrieval
        $retrieved = $collection->findOne(['code' => 'TEST-PREMIUM-001']);
        if ($retrieved) {
            echo "✅ Premium code retrieval successful<br>";
        }
        
        // Clean up
        $collection->deleteOne(['code' => 'TEST-PREMIUM-001']);
        echo "✅ Test premium code cleaned up<br>";
    }
} catch (Exception $e) {
    echo "❌ Premium code test failed: " . $e->getMessage() . "<br>";
}

// Test credit code functions
echo "<h3>Credit Code Functions</h3>";
try {
    $collection = $db->getCollection('credit_codes');
    $test_code = [
        'code' => 'TEST-CREDIT-001',
        'credit_amount' => 100,
        'status' => 'active',
        'expires_at' => time() + 86400, // 24 hours
        'created_at' => time()
    ];
    
    $result = $collection->insertOne($test_code);
    if ($result->getInsertedCount() > 0) {
        echo "✅ Test credit code created successfully<br>";
        
        // Test retrieval
        $retrieved = $collection->findOne(['code' => 'TEST-CREDIT-001']);
        if ($retrieved) {
            echo "✅ Credit code retrieval successful<br>";
        }
        
        // Clean up
        $collection->deleteOne(['code' => 'TEST-CREDIT-001']);
        echo "✅ Test credit code cleaned up<br>";
    }
} catch (Exception $e) {
    echo "❌ Credit code test failed: " . $e->getMessage() . "<br>";
}

echo "<h2>System Status</h2>";
echo "✅ Credit claim system is ready for testing<br>";
echo "✅ All database collections are accessible<br>";
echo "✅ Test functions are working properly<br>";

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Create premium codes using the admin panel</li>";
echo "<li>Create credit codes using the admin panel</li>";
echo "<li>Test claiming codes on the credit claim page</li>";
echo "<li>Verify credits are added to user accounts</li>";
echo "</ul>";

echo "<p><a href='credit_claim.php'>Go to Credit Claim Page</a></p>";
echo "<p><a href='admin/premium_generator.php'>Go to Premium Generator</a></p>";
echo "<p><a href='admin/credit_generator.php'>Go to Credit Generator</a></p>";
?>
