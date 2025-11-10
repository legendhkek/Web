<?php
require_once 'card_generator.php';
require_once 'bin_lookup.php';

header('Content-Type: application/json');

$count = isset($_POST['count']) ? (int)$_POST['count'] : 10;
$bin = $_POST['bin'] ?? '';
$month = isset($_POST['month']) ? (int)$_POST['month'] : null;
$year = isset($_POST['year']) ? (int)$_POST['year'] : null;
$cvv = isset($_POST['cvv']) ? (int)$_POST['cvv'] : null;

// Validate count
if ($count < 1 || $count > 1000) {
    echo json_encode(['error' => 'Count must be between 1 and 1000']);
    exit;
}

// Validate BIN if provided
if ($bin && !preg_match('/^\d{6,14}$/', $bin)) {
    echo json_encode(['error' => 'Invalid BIN format (must be 6-14 digits)']);
    exit;
}

// Validate month if provided
if ($month && ($month < 1 || $month > 12)) {
    echo json_encode(['error' => 'Invalid month (must be 1-12)']);
    exit;
}

// Validate year if provided
if ($year && ($year < 2025 || $year > 2035)) {
    echo json_encode(['error' => 'Invalid year (must be 2025-2035)']);
    exit;
}

// Validate CVV if provided
if ($cvv && ($cvv < 0 || $cvv > 999)) {
    echo json_encode(['error' => 'Invalid CVV (must be 0-999)']);
    exit;
}

try {
    $cards = CardGenerator::generateCards($count, $bin, $month, $year, $cvv);
    
    // Get card info for first generated card
    $cardInfo = null;
    if (!empty($cards) && $bin) {
        $cardInfo = BinLookup::getCardInfoFromCC($cards[0]);
    }
    
    echo json_encode([
        'success' => true,
        'cards' => $cards,
        'count' => count($cards),
        'card_info' => $cardInfo
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Generation failed: ' . $e->getMessage()]);
}
?>
