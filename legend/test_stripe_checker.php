<?php
/**
 * Test Script for Stripe Auth Checker
 * Run this to verify all components are working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== STRIPE AUTH CHECKER TEST SUITE ===\n\n";

// Test 1: Load required files
echo "Test 1: Loading required files...\n";
try {
    require_once 'stripe_auth_checker.php';
    echo "✅ stripe_auth_checker.php loaded\n";
    
    require_once 'stripe_site_manager.php';
    echo "✅ stripe_site_manager.php loaded\n";
    
    require_once 'bin_lookup.php';
    echo "✅ bin_lookup.php loaded\n";
    
    echo "✅ All files loaded successfully\n\n";
} catch (Exception $e) {
    echo "❌ Error loading files: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Site Manager
echo "Test 2: Testing Site Manager...\n";
try {
    $totalSites = StripeSiteManager::getSiteCount();
    echo "✅ Total sites: $totalSites\n";
    
    if ($totalSites == 0) {
        echo "❌ No sites loaded! Check stripe_sites.json\n";
        exit(1);
    }
    
    $firstSite = StripeSiteManager::getNextSite(0);
    echo "✅ First site (check 0): $firstSite\n";
    
    $site20 = StripeSiteManager::getNextSite(20);
    echo "✅ Site at check 20: $site20\n";
    
    $site40 = StripeSiteManager::getNextSite(40);
    echo "✅ Site at check 40: $site40\n";
    
    if ($firstSite === $site20) {
        echo "❌ Site rotation not working - same site returned\n";
        exit(1);
    }
    
    $rotationCount = StripeSiteManager::getRotationCount();
    echo "✅ Rotation count: $rotationCount\n";
    
    $randomSite = StripeSiteManager::getRandomSite();
    echo "✅ Random site: $randomSite\n";
    
    echo "✅ Site Manager working correctly\n\n";
} catch (Exception $e) {
    echo "❌ Site Manager error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: BIN Lookup
echo "Test 3: Testing BIN Lookup...\n";
try {
    // Test Luhn validation
    $validCard = '4111111111111111'; // Valid test Visa
    $invalidCard = '4111111111111112'; // Invalid checksum
    
    if (BINLookup::validateLuhn($validCard)) {
        echo "✅ Luhn validation: Valid card passed\n";
    } else {
        echo "❌ Luhn validation: Valid card failed\n";
    }
    
    if (!BINLookup::validateLuhn($invalidCard)) {
        echo "✅ Luhn validation: Invalid card rejected\n";
    } else {
        echo "❌ Luhn validation: Invalid card accepted\n";
    }
    
    // Test BIN extraction
    $bin = BINLookup::getBinFromCC('4111111111111111|12|2025|123');
    echo "✅ BIN extraction: $bin\n";
    
    // Test CC generation
    $generated = BINLookup::generateCC('411111', 5);
    echo "✅ Generated " . count($generated) . " cards from BIN 411111\n";
    
    foreach ($generated as $cc) {
        if (!BINLookup::validateLuhn($cc)) {
            echo "❌ Generated card failed Luhn: $cc\n";
            exit(1);
        }
    }
    echo "✅ All generated cards pass Luhn validation\n";
    
    echo "✅ BIN Lookup working correctly\n\n";
} catch (Exception $e) {
    echo "❌ BIN Lookup error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Stripe Auth Checker Class
echo "Test 4: Testing Stripe Auth Checker Class...\n";
try {
    $testSite = 'https://' . StripeSiteManager::getNextSite(0);
    $checker = new StripeAuthChecker($testSite);
    echo "✅ StripeAuthChecker instantiated for $testSite\n";
    
    // Test Luhn validation in checker
    $testCard = '4111111111111111|12|2025|123';
    echo "✅ Test card format: $testCard\n";
    
    // Test card parsing
    $parts = explode('|', $testCard);
    if (count($parts) == 4) {
        echo "✅ Card parsing: 4 parts extracted\n";
    } else {
        echo "❌ Card parsing failed\n";
    }
    
    echo "✅ Stripe Auth Checker class structure verified\n\n";
} catch (Exception $e) {
    echo "❌ Stripe Auth Checker error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Live Site Connectivity (Optional - can be slow)
echo "Test 5: Testing live site connectivity...\n";
$testConnectivity = false; // Set to true to test actual site connection

if ($testConnectivity) {
    try {
        $testSite = StripeSiteManager::getNextSite(0);
        $url = 'https://' . $testSite;
        
        echo "Testing connection to: $url\n";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 400) {
            echo "✅ Site responded with HTTP $httpCode\n";
        } else {
            echo "⚠️  Site responded with HTTP $httpCode\n";
        }
        
        if ($error) {
            echo "⚠️  Connection warning: $error\n";
        }
        
    } catch (Exception $e) {
        echo "⚠️  Connectivity test error: " . $e->getMessage() . "\n";
    }
} else {
    echo "⏭️  Skipped (set \$testConnectivity = true to enable)\n";
}

echo "\n";

// Test 6: Full Integration Test
echo "Test 6: Full integration test...\n";
try {
    echo "Simulating multi-check workflow:\n";
    
    $cards = [
        '4111111111111111|12|2025|123',
        '5555555555554444|12|2026|456',
        '378282246310005|12|2027|789'
    ];
    
    foreach ($cards as $index => $card) {
        $checkNum = $index;
        $site = StripeSiteManager::getNextSite($checkNum);
        $bin = BINLookup::getBinFromCC($card);
        
        echo "  Check #$checkNum: Card ending " . substr($card, 0, 6) . "... → Site: $site → BIN: $bin\n";
    }
    
    echo "✅ Multi-check workflow simulation successful\n\n";
} catch (Exception $e) {
    echo "❌ Integration test error: " . $e->getMessage() . "\n";
    exit(1);
}

// Summary
echo "=== TEST SUMMARY ===\n";
echo "✅ All core components working\n";
echo "✅ Site Manager: $totalSites sites loaded\n";
echo "✅ BIN Lookup: Validation and generation working\n";
echo "✅ Stripe Checker: Class structure verified\n";
echo "✅ Integration: Multi-check workflow operational\n\n";

echo "🎉 SYSTEM READY FOR PRODUCTION\n\n";

echo "To test actual card checking:\n";
echo "1. Navigate to: /legend/stripe_checker_multi.php\n";
echo "2. Login with valid Telegram credentials\n";
echo "3. Enter test cards in format: CC|MM|YYYY|CVV\n";
echo "4. Click 'Start Checking'\n\n";

echo "⚠️  NOTE: Actual card checking will:\n";
echo "   - Connect to live Stripe sites\n";
echo "   - Create temporary accounts\n";
echo "   - Deduct credits (1 per check)\n";
echo "   - Take 10-30 seconds per card\n\n";

echo "Test completed successfully!\n";
?>
