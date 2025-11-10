<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');
setSecurityHeaders();

// Check authentication
try {
    $userId = TelegramAuth::requireAuth();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => 'dead', 'error' => 'Authentication required']);
    exit;
}

// Rate limiting
if (!TelegramAuth::checkRateLimit('proxy_check', 10, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'dead', 'error' => 'Too many requests. Please wait a moment.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$proxy = isset($input['proxy']) ? trim($input['proxy']) : '';

if (empty($proxy)) {
    echo json_encode(['status' => 'dead', 'error' => 'No proxy provided']);
    exit;
}

// Sanitize and validate proxy format
$proxy = filter_var($proxy, FILTER_SANITIZE_STRING);
$parts = explode(':', $proxy);
if (count($parts) !== 4) {
    echo json_encode(['status' => 'dead', 'error' => 'Invalid proxy format. Use: host:port:user:pass']);
    exit;
}

list($host, $port, $user, $pass) = array_map('trim', $parts);

// Validate host
if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) {
    echo json_encode(['status' => 'dead', 'error' => 'Invalid host format']);
    exit;
}

// Validate port
$port = (int)$port;
if ($port < 1 || $port > 65535) {
    echo json_encode(['status' => 'dead', 'error' => 'Invalid port number']);
    exit;
}

// Test proxy by making a request to a simple API
$test_url = 'http://httpbin.org/ip';
$proxy_string = "$user:$pass@$host:$port";

if (!function_exists('curl_init')) {
    echo json_encode(['status' => 'dead', 'error' => 'cURL extension not available']);
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $test_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_PROXY => "$host:$port",
    CURLOPT_PROXYUSERPWD => "$user:$pass",
    CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);

$response = @curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

if ($curl_error || $curl_errno) {
    logError('Proxy check failed', [
        'proxy' => "$host:$port",
        'error' => $curl_error,
        'errno' => $curl_errno,
        'user_id' => $userId
    ]);
    echo json_encode(['status' => 'dead', 'error' => $curl_error ?: 'Connection failed']);
    exit;
}

if ($http_code !== 200) {
    echo json_encode(['status' => 'dead', 'error' => "HTTP $http_code"]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['origin'])) {
    echo json_encode(['status' => 'dead', 'error' => 'Invalid response from proxy']);
    exit;
}

// Try to get country info
$country = 'Unknown';
$city = null;
try {
    $geo_url = "http://ip-api.com/json/{$data['origin']}?fields=status,country,city";
    $geo_ch = curl_init();
    curl_setopt_array($geo_ch, [
        CURLOPT_URL => $geo_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    $geo_response = @curl_exec($geo_ch);
    $geo_http_code = curl_getinfo($geo_ch, CURLINFO_HTTP_CODE);
    curl_close($geo_ch);
    
    if ($geo_response && $geo_http_code === 200) {
        $geo_data = json_decode($geo_response, true);
        if ($geo_data && isset($geo_data['country']) && $geo_data['status'] === 'success') {
            $country = $geo_data['country'];
            $city = isset($geo_data['city']) ? $geo_data['city'] : null;
        }
    }
} catch (Exception $e) {
    // Ignore geo lookup errors
    logError('Geo lookup failed', ['error' => $e->getMessage()]);
}

// Log successful proxy check
logError('Proxy check successful', [
    'proxy' => "$host:$port",
    'ip' => $data['origin'],
    'country' => $country,
    'user_id' => $userId
]);

echo json_encode([
    'status' => 'live',
    'ip' => $data['origin'],
    'country' => $country,
    'city' => $city
]);
?>
