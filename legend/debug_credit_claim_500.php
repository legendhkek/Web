<?php
// Debug version of credit_claim.php to identify the 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting credit_claim.php debug...\n\n";

try {
    echo "1. Testing config.php...\n";
    require_once 'config.php';
    echo "✓ config.php loaded\n";

    echo "2. Testing database.php...\n";
    require_once 'database.php';
    echo "✓ database.php loaded\n";

    echo "3. Testing auth.php...\n";
    require_once 'auth.php';
    echo "✓ auth.php loaded\n";

    echo "4. Testing utils.php...\n";
    require_once 'utils.php';
    echo "✓ utils.php loaded\n";

    echo "5. Testing session...\n";
    initSecureSession();
    echo "✓ Secure session initialized\n";

    echo "6. Testing authentication check...\n";
    if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
        echo "❌ No user session - would redirect to login\n";
        echo "This is expected behavior, not an error.\n";
    } else {
        echo "✓ User session exists\n";
    }

    echo "7. Testing database connection...\n";
    $db = Database::getInstance();
    echo "✓ Database instance created\n";

    if (!empty($_SESSION['telegram_id'])) {
        echo "8. Testing user data fetch...\n";
        $telegram_id = $_SESSION['telegram_id'];
        $user = $db->getUserByTelegramId($telegram_id);
        if ($user) {
            echo "✓ User data retrieved\n";
        } else {
            echo "❌ User not found in database\n";
        }
    } else {
        echo "8. Skipping user data fetch (no session)\n";
    }

    echo "\n=== CONCLUSION ===\n";
    echo "No fatal errors detected in the main logic.\n";
    echo "The 500 error might be caused by:\n";
    echo "1. Missing session (normal redirect behavior)\n";
    echo "2. A specific function call later in the code\n";
    echo "3. A dependency issue\n";

} catch (Error $e) {
    echo "\n❌ FATAL ERROR FOUND:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "\n❌ EXCEPTION FOUND:\n";
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>