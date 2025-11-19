<?php
// Test script to verify admin/owner access
require_once '../config.php';

// Get the Telegram ID from the URL parameter
$telegram_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID provided, use the one from the problem report
if ($telegram_id === 0) {
    $telegram_id = 7580639195; // Default to the owner ID mentioned in the problem
}

// Get admin and owner IDs from AppConfig
$admin_ids = defined('AppConfig::ADMIN_IDS') ? AppConfig::ADMIN_IDS : [];
$owner_ids = defined('AppConfig::OWNER_IDS') ? AppConfig::OWNER_IDS : [];

// Convert to arrays if they're not already
if (!is_array($admin_ids)) {
    $admin_ids = [$admin_ids];
}

if (!is_array($owner_ids)) {
    $owner_ids = [$owner_ids];
}

// Check if the ID is in the admin or owner lists
$is_admin = in_array($telegram_id, $admin_ids);
$is_owner = in_array($telegram_id, $owner_ids);

// Initialize secure session
initSecureSession();

// Check session variables
$session_is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$session_is_owner = isset($_SESSION['is_owner']) && $_SESSION['is_owner'] === true;

// Output the results
header('Content-Type: application/json');
echo json_encode([
    'telegram_id' => $telegram_id,
    'admin_ids' => $admin_ids,
    'owner_ids' => $owner_ids,
    'direct_is_admin' => $is_admin,
    'direct_is_owner' => $is_owner,
    'session_is_admin' => $session_is_admin,
    'session_is_owner' => $session_is_owner,
    'has_admin_access' => $is_admin || $is_owner || $session_is_admin || $session_is_owner,
    'session_data' => $_SESSION,
    'in_array_check' => [
        'admin_check' => in_array($telegram_id, $admin_ids, true),
        'owner_check' => in_array($telegram_id, $owner_ids, true),
        'admin_check_loose' => in_array($telegram_id, $admin_ids),
        'owner_check_loose' => in_array($telegram_id, $owner_ids)
    ],
    'type_info' => [
        'telegram_id_type' => gettype($telegram_id),
        'admin_ids_type' => gettype($admin_ids),
        'owner_ids_type' => gettype($owner_ids),
        'admin_ids_is_array' => is_array($admin_ids),
        'owner_ids_is_array' => is_array($owner_ids)
    ]
]);