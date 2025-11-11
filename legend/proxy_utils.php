<?php

require_once __DIR__ . '/config.php';

class ProxyUtils
{
    public static function normalize(string $proxyString): array
    {
        $proxyString = trim($proxyString);
        if ($proxyString === '') {
            throw new Exception('Proxy string cannot be empty');
        }

        $parts = array_map('trim', explode(':', $proxyString));
        if (count($parts) !== 4) {
            throw new Exception('Invalid proxy format. Use: host:port:user:pass');
        }

        [$host, $port, $username, $password] = $parts;

        if (!self::isValidHost($host)) {
            throw new Exception('Invalid proxy host');
        }

        $port = (int) $port;
        if ($port < 1 || $port > 65535) {
            throw new Exception('Invalid proxy port');
        }

        if ($username === '' || $password === '') {
            throw new Exception('Proxy username/password cannot be empty');
        }

        return [
            'original' => sprintf('%s:%d:%s:%s', $host, $port, $username, $password),
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password
        ];
    }

    public static function check(string $proxyString, array $options = []): array
    {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL extension not available');
        }

        $settings = array_merge([
            'timeout' => 12,
            'connect_timeout' => 6,
            'test_url' => 'http://httpbin.org/ip',
            'geo_lookup' => true
        ], $options);

        $proxy = self::normalize($proxyString);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $settings['test_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROXY => "{$proxy['host']}:{$proxy['port']}",
            CURLOPT_PROXYUSERPWD => "{$proxy['username']}:{$proxy['password']}",
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_TIMEOUT => $settings['timeout'],
            CURLOPT_CONNECTTIMEOUT => $settings['connect_timeout'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        $response = @curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            $message = $error ?: 'Connection failed';
            logError('Proxy check failed', [
                'proxy' => "{$proxy['host']}:{$proxy['port']}",
                'error' => $message,
                'errno' => $errno
            ]);

            return [
                'success' => false,
                'status' => 'dead',
                'message' => $message,
                'normalized' => $proxy,
                'latency_ms' => null
            ];
        }

        if ((int) ($info['http_code'] ?? 0) !== 200) {
            $message = 'HTTP ' . ($info['http_code'] ?? 'unknown');
            return [
                'success' => false,
                'status' => 'dead',
                'message' => $message,
                'normalized' => $proxy,
                'latency_ms' => isset($info['total_time']) ? (int) round($info['total_time'] * 1000) : null
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['origin'])) {
            return [
                'success' => false,
                'status' => 'dead',
                'message' => 'Invalid response from proxy',
                'normalized' => $proxy,
                'latency_ms' => isset($info['total_time']) ? (int) round($info['total_time'] * 1000) : null
            ];
        }

        // Some responses return comma-separated IPs
        $origin = $data['origin'];
        if (strpos($origin, ',') !== false) {
            $origin = explode(',', $origin)[0];
        }
        $origin = trim($origin);

        $country = 'Unknown';
        $city = null;

        if ($settings['geo_lookup']) {
            try {
                $geoUrl = "http://ip-api.com/json/{$origin}?fields=status,country,city";
                $geoCh = curl_init();
                curl_setopt_array($geoCh, [
                    CURLOPT_URL => $geoUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3
                ]);
                $geoResponse = @curl_exec($geoCh);
                $geoInfo = curl_getinfo($geoCh);
                curl_close($geoCh);

                if ($geoResponse && (int) ($geoInfo['http_code'] ?? 0) === 200) {
                    $geoData = json_decode($geoResponse, true);
                    if (is_array($geoData) && ($geoData['status'] ?? '') === 'success') {
                        $country = $geoData['country'] ?? $country;
                        $city = $geoData['city'] ?? null;
                    }
                }
            } catch (Exception $e) {
                logError('Geo lookup failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'success' => true,
            'status' => 'live',
            'message' => 'Proxy is live',
            'normalized' => $proxy,
            'ip' => $origin,
            'country' => $country,
            'city' => $city,
            'latency_ms' => isset($info['total_time']) ? (int) round($info['total_time'] * 1000) : null
        ];
    }

    private static function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) filter_var(
            $host,
            FILTER_VALIDATE_DOMAIN,
            FILTER_FLAG_HOSTNAME
        );
    }
}
*** End Patch
