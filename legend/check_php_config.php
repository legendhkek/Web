<?php
echo "PHP Configuration Check:\n";
echo "========================\n\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'enabled' : 'disabled') . "\n";
echo "cURL extension: " . (extension_loaded('curl') ? 'available' : 'not available') . "\n";
echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'available' : 'not available') . "\n";

echo "\nTesting simple HTTP request...\n";

// Test basic connectivity
$testUrl = "http://httpbin.org/get";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5
    ]
]);

$result = @file_get_contents($testUrl, false, $context);
echo "HTTP test result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

if ($result) {
    echo "Response length: " . strlen($result) . " bytes\n";
}

// Check if we can access HTTPS
echo "\nTesting HTTPS request...\n";
$httpsUrl = "https://httpbin.org/get";
$httpsResult = @file_get_contents($httpsUrl, false, $context);
echo "HTTPS test result: " . ($httpsResult ? "SUCCESS" : "FAILED") . "\n";

// List all loaded extensions
echo "\nLoaded Extensions:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    if (strpos(strtolower($ext), 'curl') !== false || 
        strpos(strtolower($ext), 'http') !== false || 
        strpos(strtolower($ext), 'ssl') !== false ||
        strpos(strtolower($ext), 'socket') !== false) {
        echo "- $ext (relevant)\n";
    }
}
?>