<?php
/**
 * BIN Lookup Module
 * Gets card type, bank name, and country from BIN (first 6-8 digits)
 * Uses binlist.net API (free, no API key required)
 */

class BinLookup {
    private static $cache = [];
    private static $cacheExpiry = 3600; // 1 hour cache
    
    /**
     * Extract BIN (first 6-8 digits) from CC string
     * 
     * @param string $ccString Credit card string (e.g., "4111111111111111|12|2025|123")
     * @return string|null BIN string (6-8 digits) or null
     */
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
            return substr($digits, 0, 8); // Use 8 digits for better accuracy
        } elseif (strlen($digits) >= 6) {
            return substr($digits, 0, 6); // Use 6 digits minimum
        }
        
        return null;
    }
    
    /**
     * Get emoji for card type
     * 
     * @param string $cardType Card type/brand name
     * @return string Emoji
     */
    public static function getCardTypeEmoji($cardType) {
        $cardTypeLower = strtolower($cardType);
        if (strpos($cardTypeLower, 'visa') !== false) {
            return '💳';
        } elseif (strpos($cardTypeLower, 'mastercard') !== false || strpos($cardTypeLower, 'master') !== false) {
            return '💳';
        } elseif (strpos($cardTypeLower, 'amex') !== false || strpos($cardTypeLower, 'american express') !== false) {
            return '💳';
        } elseif (strpos($cardTypeLower, 'discover') !== false) {
            return '💳';
        } else {
            return '💳';
        }
    }
    
    /**
     * Get flag emoji for country code
     * 
     * @param string $countryCode 2-letter country code
     * @return string Flag emoji or globe
     */
    public static function getCountryEmoji($countryCode) {
        if (!$countryCode || strlen($countryCode) != 2) {
            return '🌍';
        }
        
        try {
            // Convert country code to flag emoji
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
    
    /**
     * Get BIN information from binlist.net API
     * 
     * @param string $binNumber BIN number (6-8 digits)
     * @param bool $useCache Whether to use cached results
     * @return array Dict with 'type', 'brand', 'bank', 'country', 'country_code'
     */
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
            $cachedData = self::$cache[$binNumber];
            if (time() - $cachedData['time'] < self::$cacheExpiry) {
                return $cachedData['data'];
            }
        }
        
        try {
            // Call binlist.net API (free, no API key needed)
            $url = "https://lookup.binlist.net/$binNumber";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept-Version: 3',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $response) {
                $data = json_decode($response, true);
                
                // Extract information
                $brand = $data['scheme'] ?? $data['brand'] ?? '';
                $cardType = $data['type'] ?? '';
                $level = $data['level'] ?? ''; // debit, credit, prepaid, etc.
                
                // Format card type: "Visa Gold" or "Mastercard Platinum" etc.
                if ($brand && $level) {
                    $formattedType = ucfirst($brand) . ' ' . ucfirst($level);
                } elseif ($brand && $cardType) {
                    $formattedType = ucfirst($brand) . ' ' . ucfirst($cardType);
                } elseif ($brand) {
                    $formattedType = ucfirst($brand);
                } else {
                    $formattedType = $cardType ? ucfirst($cardType) : "Unknown";
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
                
                // Cache empty result for shorter time (5 minutes) to retry later
                if ($useCache) {
                    self::$cache[$binNumber] = [
                        'data' => $result,
                        'time' => time() - (self::$cacheExpiry - 300)
                    ];
                }
                
                return $result;
            }
        } catch (Exception $e) {
            // Error calling API, return empty result
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
    
    /**
     * Get card information from CC string
     * 
     * @param string $ccString Credit card string (e.g., "4111111111111111|12|2025|123")
     * @return array Dict with card type, bank, country information
     */
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
    
    /**
     * Format card info for bot response
     * 
     * @param array $cardInfo Dict with 'type', 'bank', 'country', 'country_code'
     * @return string Formatted string with emojis
     */
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
        $infoText = "\n\n$bankEmoji Bank: $bankName\n";
        $infoText .= "$cardEmoji Card Type: $cardType\n";
        $infoText .= "$countryEmoji Country: $country";
        
        return $infoText;
    }
}
