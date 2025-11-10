<?php
/**
 * BIN Lookup Module - PHP Version
 * Gets card type, bank name, and country from BIN (first 6-8 digits)
 * Uses binlist.net API (free, no API key required)
 */

class BinLookup {
    private static $cache = [];
    private static $cache_expiry = 3600; // 1 hour

    public static function getBinFromCC($cc_string) {
        if (strpos($cc_string, '|') !== false) {
            $cc_part = explode('|', $cc_string)[0];
        } elseif (strpos($cc_string, ' ') !== false) {
            $cc_part = explode(' ', $cc_string)[0];
        } else {
            $cc_part = $cc_string;
        }
        
        $digits = preg_replace('/\D/', '', $cc_part);
        
        if (strlen($digits) >= 8) {
            return substr($digits, 0, 8);
        } elseif (strlen($digits) >= 6) {
            return substr($digits, 0, 6);
        }
        
        return null;
    }

    public static function getBinInfo($bin_number, $use_cache = true) {
        if (!$bin_number || strlen($bin_number) < 6) {
            return [
                'type' => null,
                'brand' => null,
                'bank' => null,
                'country' => null,
                'country_code' => null,
                'level' => null
            ];
        }
        
        // Check cache
        if ($use_cache && isset(self::$cache[$bin_number])) {
            list($cached_data, $cached_time) = self::$cache[$bin_number];
            if (time() - $cached_time < self::$cache_expiry) {
                return $cached_data;
            }
        }
        
        try {
            $url = "https://lookup.binlist.net/{$bin_number}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept-Version: 3',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $data = json_decode($response, true);
                
                $brand = $data['scheme'] ?? $data['brand'] ?? '';
                $card_type = $data['type'] ?? '';
                $level = $data['level'] ?? '';
                
                $formatted_type = '';
                if ($brand && $level) {
                    $formatted_type = ucfirst($brand) . ' ' . ucfirst($level);
                } elseif ($brand && $card_type) {
                    $formatted_type = ucfirst($brand) . ' ' . ucfirst($card_type);
                } elseif ($brand) {
                    $formatted_type = ucfirst($brand);
                } else {
                    $formatted_type = ucfirst($card_type) ?: 'Unknown';
                }
                
                $bank_data = $data['bank'] ?? [];
                $bank_name = is_array($bank_data) ? ($bank_data['name'] ?? '') : '';
                
                $country_data = $data['country'] ?? [];
                $country_name = is_array($country_data) ? ($country_data['name'] ?? '') : '';
                $country_code = is_array($country_data) ? ($country_data['alpha2'] ?? '') : '';
                
                $result = [
                    'type' => $formatted_type,
                    'brand' => $brand ? ucfirst($brand) : null,
                    'bank' => $bank_name,
                    'country' => $country_name,
                    'country_code' => $country_code,
                    'level' => $level ? ucfirst($level) : null
                ];
                
                if ($use_cache) {
                    self::$cache[$bin_number] = [$result, time()];
                }
                
                return $result;
            }
        } catch (Exception $e) {
            // Error handling
        }
        
        $result = [
            'type' => null,
            'brand' => null,
            'bank' => null,
            'country' => null,
            'country_code' => null,
            'level' => null
        ];
        
        return $result;
    }

    public static function getCardInfoFromCC($cc_string) {
        $bin_number = self::getBinFromCC($cc_string);
        if (!$bin_number) {
            return [
                'type' => null,
                'bank' => null,
                'country' => null,
                'country_code' => null
            ];
        }
        
        $bin_info = self::getBinInfo($bin_number);
        
        return [
            'type' => $bin_info['type'],
            'bank' => $bin_info['bank'],
            'country' => $bin_info['country'],
            'country_code' => $bin_info['country_code']
        ];
    }
}
