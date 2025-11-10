<?php
/**
 * BIN Lookup Module - Pure PHP Implementation
 * Gets card type, bank name, and country from BIN (first 6-8 digits)
 * Uses binlist.net API (free, no API key required)
 */

class BinLookup {
    private static $cache = [];
    private static $cacheExpiry = 3600; // 1 hour
    
    public static function getBinFromCC($ccString) {
        // Extract only digits from CC part
        if (strpos($ccString, '|') !== false) {
            $ccPart = explode('|', $ccString)[0];
        } elseif (strpos($ccString, ' ') !== false) {
            $ccPart = explode(' ', $ccString)[0];
        } else {
            $ccPart = $ccString;
        }
        
        // Extract digits only
        $digits = preg_replace('/[^0-9]/', '', $ccPart);
        
        // Get first 6-8 digits as BIN
        if (strlen($digits) >= 8) {
            return substr($digits, 0, 8);
        } elseif (strlen($digits) >= 6) {
            return substr($digits, 0, 6);
        }
        
        return null;
    }
    
    public static function getCardTypeEmoji($cardType) {
        $cardType = strtolower($cardType);
        if (strpos($cardType, 'visa') !== false) {
            return '💳';
        } elseif (strpos($cardType, 'mastercard') !== false || strpos($cardType, 'master') !== false) {
            return '💳';
        } elseif (strpos($cardType, 'amex') !== false || strpos($cardType, 'american express') !== false) {
            return '💳';
        } elseif (strpos($cardType, 'discover') !== false) {
            return '💳';
        }
        return '💳';
    }
    
    public static function getCountryEmoji($countryCode) {
        if (!$countryCode || strlen($countryCode) != 2) {
            return '🌍';
        }
        
        try {
            $code = strtoupper($countryCode);
            $flag = '';
            for ($i = 0; $i < strlen($code); $i++) {
                $flag .= mb_chr(0x1F1E6 + ord($code[$i]) - ord('A'), 'UTF-8');
            }
            return $flag;
        } catch (Exception $e) {
            return '🌍';
        }
    }
    
    public static function getBinInfo($binNumber, $useCache = true) {
        if (!$binNumber || strlen($binNumber) < 6) {
            return [
                'type' => null,
                'brand' => null,
                'bank' => null,
                'country' => null,
                'country_code' => null,
                'level' => null
            ];
        }
        
        // Check cache first
        if ($useCache && isset(self::$cache[$binNumber])) {
            $cached = self::$cache[$binNumber];
            if (time() - $cached['time'] < self::$cacheExpiry) {
                return $cached['data'];
            }
        }
        
        try {
            $url = "https://lookup.binlist.net/{$binNumber}";
            $ch = curl_init($url);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept-Version: 3',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($statusCode == 200) {
                $data = json_decode($response, true);
                
                $brand = $data['scheme'] ?? $data['brand'] ?? '';
                $cardType = $data['type'] ?? '';
                $level = $data['level'] ?? '';
                
                // Format card type
                if ($brand && $level) {
                    $formattedType = ucfirst($brand) . ' ' . ucfirst($level);
                } elseif ($brand && $cardType) {
                    $formattedType = ucfirst($brand) . ' ' . ucfirst($cardType);
                } elseif ($brand) {
                    $formattedType = ucfirst($brand);
                } else {
                    $formattedType = $cardType ? ucfirst($cardType) : 'Unknown';
                }
                
                // Get bank name
                $bankData = $data['bank'] ?? [];
                $bankName = is_array($bankData) ? ($bankData['name'] ?? '') : '';
                
                // Get country
                $countryData = $data['country'] ?? [];
                $countryName = is_array($countryData) ? ($countryData['name'] ?? '') : '';
                $countryCode = is_array($countryData) ? ($countryData['alpha2'] ?? '') : '';
                
                $result = [
                    'type' => $formattedType,
                    'brand' => $brand ? ucfirst($brand) : null,
                    'bank' => $bankName,
                    'country' => $countryName,
                    'country_code' => $countryCode,
                    'level' => $level ? ucfirst($level) : null
                ];
                
                // Cache the result
                if ($useCache) {
                    self::$cache[$binNumber] = [
                        'data' => $result,
                        'time' => time()
                    ];
                }
                
                return $result;
            } else {
                // API error, return empty result
                $result = [
                    'type' => null,
                    'brand' => null,
                    'bank' => null,
                    'country' => null,
                    'country_code' => null,
                    'level' => null
                ];
                
                // Cache empty result for shorter time (5 minutes)
                if ($useCache) {
                    self::$cache[$binNumber] = [
                        'data' => $result,
                        'time' => time() - (self::$cacheExpiry - 300)
                    ];
                }
                
                return $result;
            }
        } catch (Exception $e) {
            return [
                'type' => null,
                'brand' => null,
                'bank' => null,
                'country' => null,
                'country_code' => null,
                'level' => null
            ];
        }
    }
    
    public static function getCardInfoFromCC($ccString) {
        $binNumber = self::getBinFromCC($ccString);
        if (!$binNumber) {
            return [
                'type' => null,
                'bank' => null,
                'country' => null,
                'country_code' => null
            ];
        }
        
        $binInfo = self::getBinInfo($binNumber);
        
        return [
            'type' => $binInfo['type'],
            'bank' => $binInfo['bank'],
            'country' => $binInfo['country'],
            'country_code' => $binInfo['country_code']
        ];
    }
    
    public static function formatCardInfoForResponse($cardInfo) {
        $cardType = $cardInfo['type'] ?? 'Unknown';
        $bankName = $cardInfo['bank'] ?? 'Unknown';
        $country = $cardInfo['country'] ?? 'Unknown';
        $countryCode = $cardInfo['country_code'] ?? '';
        
        // Get emojis
        $cardEmoji = self::getCardTypeEmoji($cardType);
        $bankEmoji = '🏦';
        $countryEmoji = self::getCountryEmoji($countryCode);
        
        // Format response
        $infoText = "\n\n{$bankEmoji} Bank: {$bankName}\n";
        $infoText .= "{$cardEmoji} Card Type: {$cardType}\n";
        $infoText .= "{$countryEmoji} Country: {$country}";
        
        return $infoText;
    }
}

// Helper functions for backwards compatibility
function get_bin_from_cc($ccString) {
    return BinLookup::getBinFromCC($ccString);
}

function get_bin_info($binNumber, $useCache = true) {
    return BinLookup::getBinInfo($binNumber, $useCache);
}

function get_card_info_from_cc($ccString) {
    return BinLookup::getCardInfoFromCC($ccString);
}

function format_card_info_for_response($cardInfo) {
    return BinLookup::formatCardInfoForResponse($cardInfo);
}
?>
