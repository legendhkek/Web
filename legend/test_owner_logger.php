<?php
require_once 'owner_logger.php';

echo "Testing Owner Logger...\n";

try {
    $logger = new OwnerLogger();
    echo "✓ Owner Logger initialized\n";
    
    // Test startup notification
    $result = $logger->sendStartupNotification();
    echo "✓ Startup notification sent\n";
    
    // Test system alert
    $result = $logger->sendSystemAlert('Test Alert', 'This is a test system alert from the owner logger system.');
    echo "✓ System alert sent\n";
    
    echo "\nOwner Logger is working correctly!\n";
    echo "Check @LEGEND_BL Telegram for notifications.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>