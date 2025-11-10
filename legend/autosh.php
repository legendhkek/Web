<?php
/**
 * Local Card Checker API Fallback
 * This is a fallback when the main API is not available
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get parameters
$cc = $_GET['cc'] ?? '';
$site = $_GET['site'] ?? '';
$proxy = $_GET['proxy'] ?? '';
$noproxy = $_GET['noproxy'] ?? '';

// Basic validation
if (empty($cc) || empty($site)) {
    echo json_encode([
        'error' => true,
        'message' => 'Missing required parameters: cc and site',
        'status' => 'INVALID_PARAMS'
    ]);
    exit;
}

// Parse card details
$card_parts = explode('|', $cc);
if (count($card_parts) !== 4) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid card format. Use: number|month|year|cvv',
        'status' => 'INVALID_FORMAT'
    ]);
    exit;
}

[$card_number, $month, $year, $cvv] = $card_parts;

// Basic card validation
if (!preg_match('/^\d{13,19}$/', $card_number)) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid card number format',
        'status' => 'INVALID_CARD'
    ]);
    exit;
}

// Simulate API response (this is just a fallback for testing)
// In a real implementation, this would connect to actual payment processors
$responses = [
    'LIVE' => ['Response' => 'LIVE', 'message' => 'Card is valid and live'],
    'DEAD' => ['Response' => 'DEAD', 'message' => 'Card is invalid or expired'],
    'CHARGE' => ['Response' => 'CHARGE', 'message' => 'Card charged successfully'],
    'DECLINED' => ['Response' => 'DECLINED', 'message' => 'Transaction declined'],
    'INSUFFICIENT' => ['Response' => 'INSUFFICIENT FUNDS', 'message' => 'Insufficient funds'],
    'CVV_MISMATCH' => ['Response' => 'CVV MISMATCH', 'message' => 'CVV does not match']
];

// Simple simulation based on card number patterns
$last_digit = (int)substr($card_number, -1);
$status_keys = array_keys($responses);
$selected_status = $status_keys[$last_digit % count($status_keys)];

// Add some realistic details
$response = $responses[$selected_status];
$response['card'] = $card_number . '|' . $month . '|' . $year . '|' . $cvv;
$response['site'] = $site;
$response['timestamp'] = date('Y-m-d H:i:s');
$response['bin'] = substr($card_number, 0, 6);
$response['last4'] = substr($card_number, -4);

// Add expected API fields
$response['Gateway'] = 'stripe';
$response['Price'] = '1.00';
$response['ProxyStatus'] = 'Active';
$response['ProxyIP'] = '127.0.0.1';

// Determine card type
$first_digit = substr($card_number, 0, 1);
$card_types = [
    '4' => 'VISA',
    '5' => 'MASTERCARD',
    '3' => 'AMEX',
    '6' => 'DISCOVER'
];
$response['card_type'] = $card_types[$first_digit] ?? 'UNKNOWN';

// Add delay to simulate real API
usleep(500000); // 0.5 second delay

echo json_encode($response);
?>