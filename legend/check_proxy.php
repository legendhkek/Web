<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$proxy = $input['proxy'] ?? '';

if (empty($proxy)) {
    echo json_encode(['status' => 'dead', 'error' => 'No proxy provided']);
    exit;
}

$parts = explode(':', $proxy);
if (count($parts) !== 4) {
    echo json_encode(['status' => 'dead', 'error' => 'Invalid proxy format']);
    exit;
}

list($host, $port, $user, $pass) = $parts;

// Test proxy by making a request to a simple API
$test_url = 'http://httpbin.org/ip';
$proxy_string = "$user:$pass@$host:$port";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_PROXY, "$host:$port");
curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['status' => 'dead', 'error' => $curl_error]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode(['status' => 'dead', 'error' => "HTTP $http_code"]);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['origin'])) {
    echo json_encode(['status' => 'dead', 'error' => 'Invalid response']);
    exit;
}

// Try to get country info
$country = 'Unknown';
try {
    $geo_url = "http://ip-api.com/json/{$data['origin']}";
    $geo_ch = curl_init();
    curl_setopt($geo_ch, CURLOPT_URL, $geo_url);
    curl_setopt($geo_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($geo_ch, CURLOPT_TIMEOUT, 5);
    $geo_response = curl_exec($geo_ch);
    curl_close($geo_ch);
    
    $geo_data = json_decode($geo_response, true);
    if ($geo_data && isset($geo_data['country'])) {
        $country = $geo_data['country'];
    }
} catch (Exception $e) {
    // Ignore geo lookup errors
}

echo json_encode([
    'status' => 'live',
    'ip' => $data['origin'],
    'country' => $country
]);
?>
