<?php
/**
 * CC Generator from BIN
 * Generates valid credit card numbers from BIN (Bank Identification Number)
 */

class CCGenerator {
    /**
     * Generate a valid credit card number from BIN using Luhn algorithm
     */
    public static function generateFromBin($bin, $count = 1) {
        $bin = preg_replace('/\D/', '', $bin);
        
        if (strlen($bin) < 6 || strlen($bin) > 8) {
            throw new Exception('BIN must be 6-8 digits');
        }
        
        $cards = [];
        for ($i = 0; $i < $count; $i++) {
            $card = self::generateCardNumber($bin);
            $cards[] = $card;
        }
        
        return $cards;
    }

    private static function generateCardNumber($bin) {
        // Determine card length based on BIN
        $length = 16; // Default for most cards
        
        // Visa starts with 4
        if (substr($bin, 0, 1) == '4') {
            $length = 16;
        }
        // Mastercard starts with 5
        elseif (substr($bin, 0, 1) == '5') {
            $length = 16;
        }
        // Amex starts with 3
        elseif (substr($bin, 0, 2) == '34' || substr($bin, 0, 2) == '37') {
            $length = 15;
        }
        // Discover starts with 6
        elseif (substr($bin, 0, 1) == '6') {
            $length = 16;
        }
        
        // Generate random digits to fill remaining length
        $remaining_length = $length - strlen($bin) - 1; // -1 for check digit
        $random_digits = '';
        for ($i = 0; $i < $remaining_length; $i++) {
            $random_digits .= mt_rand(0, 9);
        }
        
        // Combine BIN + random digits
        $card_without_check = $bin . $random_digits;
        
        // Calculate Luhn check digit
        $check_digit = self::calculateLuhnCheckDigit($card_without_check);
        
        return $card_without_check . $check_digit;
    }

    private static function calculateLuhnCheckDigit($number) {
        $sum = 0;
        $num_digits = strlen($number);
        $parity = $num_digits % 2;
        
        for ($i = 0; $i < $num_digits; $i++) {
            $digit = (int)$number[$i];
            
            if ($i % 2 == $parity) {
                $digit *= 2;
            }
            
            if ($digit > 9) {
                $digit -= 9;
            }
            
            $sum += $digit;
        }
        
        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Generate full card with expiry and CVV
     */
    public static function generateFullCard($bin, $count = 1) {
        $cards = self::generateFromBin($bin, $count);
        $full_cards = [];
        
        foreach ($cards as $card) {
            // Generate random expiry (not expired)
            $month = str_pad(mt_rand(1, 12), 2, '0', STR_PAD_LEFT);
            $year = mt_rand(date('Y'), date('Y') + 5);
            
            // Generate CVV
            $cvv = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
            
            $full_cards[] = [
                'card' => $card,
                'month' => $month,
                'year' => $year,
                'cvv' => $cvv,
                'full' => "{$card}|{$month}|{$year}|{$cvv}"
            ];
        }
        
        return $full_cards;
    }
}
