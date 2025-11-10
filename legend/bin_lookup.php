<?php
/**
 * BIN Lookup Module - PHP Implementation
 * Gets card type, bank name, and country from BIN (first 6-8 digits)
 * Uses binlist.net API (free, no API key required)
 */

class BINLookup {
    private static $cache = [];
    private static $cacheExpiry = 3600; // 1 hour
    
    /**
     * Extract BIN from CC string
     */
    public static function getBinFromCC($ccString) {
        // Extract digits from CC part
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
    
    /**
     * Get emoji for card type
     */
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
    
    /**
     * Get flag emoji for country code
     */
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
    
    /**
     * Get BIN information from binlist.net API
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
        
        // Check cache
        if ($useCache && isset(self::$cache[$binNumber])) {
            $cached = self::$cache[$binNumber];
            if (time() - $cached['time'] < self::$cacheExpiry) {
                return $cached['data'];
            }
        }
        
        try {
            // Call binlist.net API
            $url = "https://lookup.binlist.net/{$binNumber}";
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
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                
                // Extract information
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
                    $formattedType = ucfirst($cardType) ?: 'Unknown';
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
            }
        } catch (Exception $e) {
            // Error calling API
        }
        
        // Return empty result
        $result = [
            'type' => null,
            'brand' => null,
            'bank' => null,
            'country' => null,
            'country_code' => null,
            'level' => null
        ];
        
        // Cache empty result for shorter time
        if ($useCache) {
            self::$cache[$binNumber] = [
                'data' => $result,
                'time' => time() - (self::$cacheExpiry - 300)
            ];
        }
        
        return $result;
    }
    
    /**
     * Get card information from CC string
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
     * Format card info for response
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
        $infoText = "\n\n{$bankEmoji} Bank: {$bankName}\n";
        $infoText .= "{$cardEmoji} Card Type: {$cardType}\n";
        $infoText .= "{$countryEmoji} Country: {$country}";
        
        return $infoText;
    }
    
    /**
     * Generate valid CC numbers from BIN using Luhn algorithm
     */
    public static function generateCC($binPrefix, $count = 10) {
        $cards = [];
        $existing = [];
        $attempts = 0;
        $maxAttempts = $count * 100;
        
        while (count($cards) < $count && $attempts < $maxAttempts) {
            $attempts++;
            
            // Generate 15 digits (16th will be checksum)
            if (strlen($binPrefix) < 15) {
                $remaining = 15 - strlen($binPrefix);
                $randomPart = '';
                for ($i = 0; $i < $remaining; $i++) {
                    $randomPart .= rand(0, 9);
                }
                $ccBase = $binPrefix . $randomPart;
            } else {
                $ccBase = substr($binPrefix, 0, 15);
            }
            
            // Calculate Luhn checksum
            $checksum = self::calculateLuhnChecksum($ccBase);
            $ccFull = $ccBase . $checksum;
            
            // Check if unique
            if (!in_array($ccFull, $existing)) {
                $existing[] = $ccFull;
                $cards[] = $ccFull;
            }
        }
        
        return $cards;
    }
    
    /**
     * Calculate Luhn checksum
     */
    private static function calculateLuhnChecksum($ccBase) {
        $digits = array_reverse(str_split($ccBase));
        $sum = 0;
        
        for ($i = 0; $i < count($digits); $i++) {
            $digit = (int)$digits[$i];
            if ($i % 2 == 1) { // Every second digit from right
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + floor($digit / 10);
                }
            }
            $sum += $digit;
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum;
    }
    
    /**
     * Validate credit card using Luhn algorithm
     */
    public static function validateLuhn($ccNumber) {
        $digits = preg_replace('/[^0-9]/', '', $ccNumber);
        
        if (strlen($digits) != 16) {
            return false;
        }
        
        $sum = 0;
        $reverse = array_reverse(str_split($digits));
        
        for ($i = 0; $i < count($reverse); $i++) {
            $num = (int)$reverse[$i];
            if ($i % 2 == 1) {
                $num *= 2;
                if ($num > 9) {
                    $num = $num % 10 + floor($num / 10);
                }
            }
            $sum += $num;
        }
        
        return $sum % 10 == 0;
    }
}

// Standalone functions for easy usage
function binLookup($binOrCC) {
    if (strlen($binOrCC) >= 13) {
        // It's likely a full CC, extract BIN
        return BINLookup::getCardInfoFromCC($binOrCC);
    } else {
        // It's a BIN
        return BINLookup::getBinInfo($binOrCC);
    }
}

function generateCCFromBin($binPrefix, $count = 10) {
    return BINLookup::generateCC($binPrefix, $count);
}
?>
