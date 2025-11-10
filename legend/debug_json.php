<?php
// Test script to debug JSON corruption in card checker

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing card checker JSON output...\n";

// Test 1: Check if files can be included without output
echo "\n1. Testing file includes:\n";
ob_start();
try {
    require_once 'config.php';
    echo "✓ config.php included\n";
} catch (Exception $e) {
    echo "❌ config.php error: " . $e->getMessage() . "\n";
}

$config_output = ob_get_contents();
ob_end_clean();

if (!empty($config_output)) {
    echo "❌ config.php produces output: " . json_encode($config_output) . "\n";
} else {
    echo "✓ config.php no unwanted output\n";
}

// Test 2: Check database include
ob_start();
try {
    require_once 'database.php';
    echo "✓ database.php included\n";
} catch (Exception $e) {
    echo "❌ database.php error: " . $e->getMessage() . "\n";
}

$db_output = ob_get_contents();
ob_end_clean();

if (!empty($db_output)) {
    echo "❌ database.php produces output: " . json_encode($db_output) . "\n";
} else {
    echo "✓ database.php no unwanted output\n";
}

// Test 3: Check auth include
ob_start();
try {
    require_once 'auth.php';
    echo "✓ auth.php included\n";
} catch (Exception $e) {
    echo "❌ auth.php error: " . $e->getMessage() . "\n";
}

$auth_output = ob_get_contents();
ob_end_clean();

if (!empty($auth_output)) {
    echo "❌ auth.php produces output: " . json_encode($auth_output) . "\n";
} else {
    echo "✓ auth.php no unwanted output\n";
}

// Test 4: Check other includes
$other_files = ['owner_logger.php', 'cc_logs_manager.php', 'utils.php'];
foreach ($other_files as $file) {
    if (file_exists($file)) {
        ob_start();
        try {
            require_once $file;
            echo "✓ $file included\n";
        } catch (Exception $e) {
            echo "❌ $file error: " . $e->getMessage() . "\n";
        }
        
        $file_output = ob_get_contents();
        ob_end_clean();
        
        if (!empty($file_output)) {
            echo "❌ $file produces output: " . json_encode($file_output) . "\n";
        } else {
            echo "✓ $file no unwanted output\n";
        }
    } else {
        echo "⚠️ $file not found\n";
    }
}

// Test 5: Simple JSON test
echo "\n5. Testing JSON output:\n";
ob_start();
$test_data = ['status' => 'test', 'message' => 'hello'];
echo json_encode($test_data);
$json_output = ob_get_contents();
ob_end_clean();

echo "JSON output: " . json_encode($json_output) . "\n";
echo "JSON length: " . strlen($json_output) . "\n";

// Test 6: Check for BOM or hidden characters
$json_chars = str_split($json_output);
echo "First 10 characters (with ASCII codes):\n";
for ($i = 0; $i < min(10, count($json_chars)); $i++) {
    $char = $json_chars[$i];
    $ascii = ord($char);
    echo "  [$i] '$char' (ASCII: $ascii)\n";
}

echo "\nLast 10 characters (with ASCII codes):\n";
$start = max(0, count($json_chars) - 10);
for ($i = $start; $i < count($json_chars); $i++) {
    $char = $json_chars[$i];
    $ascii = ord($char);
    echo "  [$i] '$char' (ASCII: $ascii)\n";
}
?>