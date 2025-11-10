<?php
/**
 * Card Generator - Pure PHP Implementation
 * Generates valid credit card numbers using Luhn algorithm
 */

class CardGenerator {
    
    public static function calculateLuhnChecksum($ccBase) {
        $digits = array_reverse(str_split($ccBase));
        $sum = 0;
        
        foreach ($digits as $index => $digit) {
            $digit = (int)$digit;
            
            // Double every second digit (from right, starting at index 1)
            if ($index % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = $digit - 9;
                }
            }
            
            $sum += $digit;
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum;
    }
    
    public static function generateValidCC($binPrefix = '', $existingCCs = []) {
        $maxAttempts = 10000;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            if ($binPrefix) {
                $remaining = 16 - strlen($binPrefix);
                if ($remaining > 0) {
                    // Generate random digits for the rest (minus 1 for checksum)
                    $randomPart = '';
                    for ($i = 0; $i < $remaining - 1; $i++) {
                        $randomPart .= mt_rand(0, 9);
                    }
                    $ccBase = $binPrefix . $randomPart;
                } else {
                    $ccBase = substr($binPrefix, 0, 15);
                }
            } else {
                // Generate 15 random digits
                $ccBase = '';
                for ($i = 0; $i < 15; $i++) {
                    $ccBase .= mt_rand(0, 9);
                }
            }
            
            // Calculate Luhn checksum
            $checksum = self::calculateLuhnChecksum($ccBase);
            $ccDigits = $ccBase . $checksum;
            
            // Check if already exists
            if (in_array($ccDigits, $existingCCs)) {
                $attempts++;
                continue;
            }
            
            // Verify the full 16-digit number is valid
            if (strlen($ccDigits) === 16 && self::validateLuhn($ccDigits)) {
                return $ccDigits;
            }
            
            $attempts++;
        }
        
        throw new Exception("Failed to generate valid CC after maximum attempts");
    }
    
    public static function validateLuhn($ccNumber) {
        $digits = preg_replace('/[^0-9]/', '', $ccNumber);
        
        if (strlen($digits) !== 16) {
            return false;
        }
        
        $sum = 0;
        $reverse = strrev($digits);
        
        for ($i = 0; $i < strlen($reverse); $i++) {
            $digit = (int)$reverse[$i];
            
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }
    
    public static function generateCards($count, $binPrefix = '', $month = null, $year = null, $cvv = null) {
        $cards = [];
        $existingCCs = [];
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $ccDigits = self::generateValidCC($binPrefix, $existingCCs);
                $existingCCs[] = $ccDigits;
                
                // Generate or use provided month
                if ($month) {
                    $finalMm = str_pad($month, 2, '0', STR_PAD_LEFT);
                } else {
                    $finalMm = str_pad(mt_rand($currentMonth, 12), 2, '0', STR_PAD_LEFT);
                }
                
                // Generate or use provided year
                if ($year) {
                    $finalYyyy = $year;
                } else {
                    $finalYyyy = (string)mt_rand($currentYear, $currentYear + 10);
                }
                
                // Generate or use provided CVV
                if ($cvv) {
                    $finalCvv = str_pad($cvv, 3, '0', STR_PAD_LEFT);
                } else {
                    $finalCvv = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
                }
                
                $cardLine = "{$ccDigits}|{$finalMm}|{$finalYyyy}|{$finalCvv}";
                $cards[] = $cardLine;
                
            } catch (Exception $e) {
                // If generation fails, continue
                continue;
            }
        }
        
        return $cards;
    }
}
?>
