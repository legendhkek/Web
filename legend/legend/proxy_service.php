<?php
/**
 * ProxyService - validation, testing, and helper utilities for user proxies.
 */

require_once __DIR__ . '/config.php';

class ProxyService
{
    private const DEFAULT_TEST_URL = 'http://httpbin.org/ip';
    private const GEO_LOOKUP_URL = 'http://ip-api.com/json/';

    /**
     * Validate and normalize a proxy string.
     *
     * Supports formats:
     *   host:port
     *   host:port:user:pass
     *
     * @param string $proxyString
     * @return array{valid:bool, proxy?:string, parts?:array, error?:string}
     */
    public static function validate(string $proxyString): array
    {
        $proxy = trim($proxyString);
        if ($proxy === '') {
            return ['valid' => false, 'error' => 'Proxy cannot be empty'];
        }

        $parts = explode(':', $proxy);
        if (!in_array(count($parts), [2, 4], true)) {
            return ['valid' => false, 'error' => 'Invalid proxy format'];
        }

        $host = $parts[0];
        $port = $parts[1] ?? null;

        if (!filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            return ['valid' => false, 'error' => 'Invalid host (use IP or domain)'];
        }

        if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
            return ['valid' => false, 'error' => 'Invalid port'];
        }

        if (count($parts) === 4) {
            if ($parts[2] === '' || $parts[3] === '') {
                return ['valid' => false, 'error' => 'Username/password cannot be empty'];
            }
        }

        return ['valid' => true, 'proxy' => $proxy, 'parts' => $parts];
    }

    /**
     * Run a live test against the proxy.
     *
     * @param string $proxyString
     * @param int $timeoutSeconds
     * @return array{status:string, latency_ms?:int, ip?:string, country?:string, error?:string}
     */
    public static function test(string $proxyString, int $timeoutSeconds = 10): array
    {
        $validation = self::validate($proxyString);
        if (!$validation['valid']) {
            return [
                'status' => 'dead',
                'error' => $validation['error'] ?? 'Invalid proxy',
            ];
        }

        $parts = $validation['parts'];
        [$host, $port] = $parts;
        $credentials = count($parts) === 4 ? [$parts[2], $parts[3]] : null;

        if (!function_exists('curl_init')) {
            return [
                'status' => 'dead',
                'error' => 'cURL extension required',
            ];
        }

        $url = self::DEFAULT_TEST_URL;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PROXY, "{$host}:{$port}");
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeoutSeconds));
        curl_setopt($ch, CURLOPT_USERAGENT, 'LegendChecker/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($credentials) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$credentials[0]}:{$credentials[1]}");
        }

        $start = microtime(true);
        $response = curl_exec($ch);
        $latency = (int) round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'status' => 'dead',
                'error' => $curlError,
            ];
        }

        if ($httpCode !== 200 || !$response) {
            return [
                'status' => 'dead',
                'error' => "HTTP {$httpCode}",
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['origin'])) {
            return [
                'status' => 'dead',
                'error' => 'Invalid response from test endpoint',
            ];
        }

        $ip = $data['origin'];
        $country = self::lookupCountry($ip);

        return [
            'status' => 'live',
            'latency_ms' => $latency,
            'ip' => $ip,
            'country' => $country,
        ];
    }

    /**
     * Lookup country for an IP using ip-api.com.
     */
    private static function lookupCountry(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $url = self::GEO_LOOKUP_URL . urlencode($ip);
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || ($data['status'] ?? null) !== 'success') {
            return null;
        }

        return $data['country'] ?? null;
    }

    /**
     * Mask a proxy string for display (hide password).
     */
    public static function maskProxy(string $proxyString): string
    {
        $parts = explode(':', $proxyString);
        if (count($parts) !== 4) {
            return $proxyString;
        }

        [$host, $port, $user] = $parts;
        return sprintf('%s:%s:%s:%s', $host, $port, $user, '******');
    }
}
