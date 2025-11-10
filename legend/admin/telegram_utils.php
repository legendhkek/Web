<?php
/**
 * Telegram Utility Functions
 * This file contains functions for Telegram bot operations
 */

require_once '../config.php';

/**
 * Send Telegram message to a user
 * @param int $chat_id Telegram chat ID
 * @param string $message Message to send
 * @param string $parse_mode Parse mode (HTML, Markdown)
 * @return bool True if sent successfully
 */
function sendTelegramMessage($chat_id, $message, $parse_mode = 'HTML') {
    try {
        $bot_token = TelegramConfig::BOT_TOKEN;
        
        if (empty($bot_token)) {
            error_log("Telegram bot token not configured");
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => true
        ];
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log("Failed to send Telegram message to {$chat_id}");
            return false;
        }
        
        $response = json_decode($result, true);
        
        if (isset($response['ok']) && $response['ok']) {
            return true;
        } else {
            error_log("Telegram API error: " . json_encode($response));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Exception sending Telegram message: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Telegram message with keyboard
 * @param int $chat_id Telegram chat ID
 * @param string $message Message to send
 * @param array $keyboard Keyboard buttons
 * @param bool $resize_keyboard Resize keyboard
 * @return bool True if sent successfully
 */
function sendTelegramMessageWithKeyboard($chat_id, $message, $keyboard = [], $resize_keyboard = true) {
    try {
        $bot_token = TelegramConfig::BOT_TOKEN;
        
        if (empty($bot_token)) {
            error_log("Telegram bot token not configured");
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => $resize_keyboard,
                'one_time_keyboard' => false
            ])
        ];
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log("Failed to send Telegram message with keyboard to {$chat_id}");
            return false;
        }
        
        $response = json_decode($result, true);
        
        if (isset($response['ok']) && $response['ok']) {
            return true;
        } else {
            error_log("Telegram API error: " . json_encode($response));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Exception sending Telegram message with keyboard: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Telegram bot info
 * @return array|false Bot info or false on error
 */
function getTelegramBotInfo() {
    try {
        $bot_token = TelegramConfig::BOT_TOKEN;
        
        if (empty($bot_token)) {
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$bot_token}/getMe";
        $result = file_get_contents($url);
        
        if ($result === false) {
            return false;
        }
        
        $response = json_decode($result, true);
        
        if (isset($response['ok']) && $response['ok']) {
            return $response['result'];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Exception getting bot info: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Telegram bot webhook info
 * @return array|false Webhook info or false on error
 */
function getTelegramWebhookInfo() {
    try {
        $bot_token = TelegramConfig::BOT_TOKEN;
        
        if (empty($bot_token)) {
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$bot_token}/getWebhookInfo";
        $result = file_get_contents($url);
        
        if ($result === false) {
            return false;
        }
        
        $response = json_decode($result, true);
        
        if (isset($response['ok']) && $response['ok']) {
            return $response['result'];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Exception getting webhook info: " . $e->getMessage());
        return false;
    }
}

/**
 * Set Telegram webhook
 * @param string $webhook_url Webhook URL
 * @return bool True if set successfully
 */
function setTelegramWebhook($webhook_url) {
    try {
        $bot_token = TelegramConfig::BOT_TOKEN;
        
        if (empty($bot_token)) {
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$bot_token}/setWebhook";
        
        $data = [
            'url' => $webhook_url
        ];
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            return false;
        }
        
        $response = json_decode($result, true);
        
        return isset($response['ok']) && $response['ok'];
        
    } catch (Exception $e) {
        error_log("Exception setting webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete Telegram webhook
 * @return bool True if deleted successfully
 */
function deleteTelegramWebhook() {
    try {
        $bot_token = TelegramConfig::BOT_TOKEN;
        
        if (empty($bot_token)) {
            return false;
        }
        
        $url = "https://api.telegram.org/bot{$bot_token}/deleteWebhook";
        $result = file_get_contents($url);
        
        if ($result === false) {
            return false;
        }
        
        $response = json_decode($result, true);
        
        return isset($response['ok']) && $response['ok'];
        
    } catch (Exception $e) {
        error_log("Exception deleting webhook: " . $e->getMessage());
        return false;
    }
}
?>
