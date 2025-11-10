<?php
require_once 'bin_lookup.php';

header('Content-Type: application/json');

$binInput = $_POST['bin'] ?? '';

if (empty($binInput)) {
    echo json_encode(['error' => 'BIN required']);
    exit;
}

// Extract BIN from input
$bin = BinLookup::getBinFromCC($binInput);

if (!$bin) {
    echo json_encode(['error' => 'Invalid BIN format']);
    exit;
}

// Get BIN info
$info = BinLookup::getBinInfo($bin);
$info['bin'] = $bin;

echo json_encode($info);
?>
