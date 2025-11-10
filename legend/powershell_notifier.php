<?php
/**
 * Alternative notification system using PowerShell for HTTPS requests
 * This works around the missing cURL/OpenSSL extensions
 */

require_once 'config.php';

class PowerShellNotifier {
    private $bot_token;
    private $owner_chat_id;
    
    public function __construct() {
        $this->bot_token = TelegramConfig::BOT_TOKEN;
        $this->owner_chat_id = 6658831303;
    }
    
    /**
     * Send notification using PowerShell Invoke-RestMethod
     */
    public function sendNotification($message, $parse_mode = 'HTML') {
        $url = "https://api.telegram.org/bot" . $this->bot_token . "/sendMessage";
        
        $data = [
            'chat_id' => $this->owner_chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => true
        ];
        
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        // Create PowerShell command
        $powershellCmd = 'powershell.exe -Command "' .
            '$headers = @{\'Content-Type\' = \'application/json\'}; ' .
            '$body = \'' . addslashes($jsonData) . '\'; ' .
            'try { ' .
            '$response = Invoke-RestMethod -Uri \'' . $url . '\' -Method Post -Body $body -Headers $headers; ' .
            'Write-Output \"SUCCESS: $($response | ConvertTo-Json -Compress)\"; ' .
            '} catch { ' .
            'Write-Output \"ERROR: $($_.Exception.Message)\"; ' .
            '}"';
        
        // Execute the command
        $output = shell_exec($powershellCmd);
        
        return [
            'success' => strpos($output, 'SUCCESS:') !== false,
            'response' => $output
        ];
    }
    
    /**
     * Test the notification system
     */
    public function test() {
        $message = "🧪 <b>TEST MESSAGE</b>\n\n" .
                  "This is a test message using PowerShell fallback method.\n\n" .
                  "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n" .
                  "🔧 <b>Method:</b> PowerShell Invoke-RestMethod";
        
        return $this->sendNotification($message);
    }
}

// Test the PowerShell notifier
echo "Testing PowerShell-based notification system...\n\n";

try {
    $notifier = new PowerShellNotifier();
    $result = $notifier->test();
    
    echo "Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Response: " . trim($result['response']) . "\n";
    
    if ($result['success']) {
        echo "\n✅ PowerShell notification method is working!\n";
        echo "Check your Telegram for the test message.\n";
    } else {
        echo "\n❌ PowerShell notification failed.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>