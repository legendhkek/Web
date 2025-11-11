<?php
require_once 'admin_header.php';
require_once '../owner_logger.php';

// Check admin access (owners and admins can broadcast)
$current_user = checkAdminAccess();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $broadcast_message = trim($_POST['message'] ?? '');
    $target = $_POST['target'] ?? 'all';
    $parse_mode = $_POST['parse_mode'] ?? 'HTML';
    
    if (empty($broadcast_message)) {
        $message = "Message cannot be empty";
        $message_type = "danger";
    } else {
        try {
            // Get target users
            $users = [];
            switch ($target) {
                case 'all':
                    $users = $db->getAllUsers(10000, 0);
                    break;
                case 'online':
                    $users = $db->getOnlineUsers();
                    break;
                case 'free':
                case 'premium':
                case 'vip':
                    $all_users = $db->getAllUsers(10000, 0);
                    $users = array_filter($all_users, fn($u) => $u['role'] === $target);
                    break;
                case 'active':
                    $all_users = $db->getAllUsers(10000, 0);
                    $users = array_filter($all_users, fn($u) => ($u['status'] ?? 'active') === 'active');
                    break;
                case 'banned':
                    $all_users = $db->getAllUsers(10000, 0);
                    $users = array_filter($all_users, fn($u) => ($u['status'] ?? 'active') === 'banned');
                    break;
            }
            
            $sent_count = 0;
            $failed_count = 0;
            
            // Send via Telegram
            foreach ($users as $user) {
                try {
                    $telegram_id = $user['telegram_id'];
                    $url = "https://api.telegram.org/bot" . TelegramConfig::BOT_TOKEN . "/sendMessage";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, [
                        'chat_id' => $telegram_id,
                        'text' => $broadcast_message,
                        'parse_mode' => $parse_mode
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http_code === 200) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                    
                    // Small delay to avoid rate limiting
                    usleep(100000); // 0.1 seconds
                    
                } catch (Exception $e) {
                    $failed_count++;
                    error_log("Broadcast failed for user {$telegram_id}: " . $e->getMessage());
                }
            }
            
            // Log the broadcast
            $db->logAuditAction($_SESSION['telegram_id'], 'broadcast_sent', null, [
                'target' => $target,
                'sent' => $sent_count,
                'failed' => $failed_count,
                'message_length' => strlen($broadcast_message)
            ]);
            
            // Owner notification
            try {
                $ownerLogger = new OwnerLogger();
                $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                $ownerLogger->sendAdminAlert(
                    $admin_user,
                    'Broadcast Sent',
                    "📢 Broadcast message sent:\n\n" .
                    "👮 By: {$admin_user['display_name']}\n" .
                    "🎯 Target: {$target}\n" .
                    "✅ Sent: {$sent_count}\n" .
                    "❌ Failed: {$failed_count}\n" .
                    "📝 Message preview: " . substr($broadcast_message, 0, 100) . (strlen($broadcast_message) > 100 ? '...' : '')
                );
            } catch (Exception $e) {
                error_log("Owner logging failed: " . $e->getMessage());
            }
            
            $message = "Broadcast completed! Sent: {$sent_count}, Failed: {$failed_count}";
            $message_type = "success";
            
        } catch (Exception $e) {
            $message = "Broadcast failed: " . $e->getMessage();
            $message_type = "danger";
            error_log("Broadcast error: " . $e->getMessage());
        }
    }
}

// Get user counts
$all_users = $db->getAllUsers(10000, 0);
$online_users = $db->getOnlineUsers();

$target_counts = [
    'all' => count($all_users),
    'online' => count($online_users),
    'free' => count(array_filter($all_users, fn($u) => $u['role'] === 'free')),
    'premium' => count(array_filter($all_users, fn($u) => $u['role'] === 'premium')),
    'vip' => count(array_filter($all_users, fn($u) => $u['role'] === 'vip')),
    'active' => count(array_filter($all_users, fn($u) => ($u['status'] ?? 'active') === 'active')),
    'banned' => count(array_filter($all_users, fn($u) => ($u['status'] ?? 'active') === 'banned'))
];

?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="bi bi-megaphone"></i> Broadcast Message</h1>
            <p class="text-muted">Send messages to multiple users via Telegram</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Broadcast Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-chat-square-text"></i> Compose Broadcast</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="target" class="form-label">
                                <i class="bi bi-people"></i> Target Audience <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="target" name="target" required onchange="updateTargetCount()">
                                <option value="all">All Users (<?php echo $target_counts['all']; ?>)</option>
                                <option value="online">Online Users (<?php echo $target_counts['online']; ?>)</option>
                                <option value="free">Free Users (<?php echo $target_counts['free']; ?>)</option>
                                <option value="premium">Premium Users (<?php echo $target_counts['premium']; ?>)</option>
                                <option value="vip">VIP Users (<?php echo $target_counts['vip']; ?>)</option>
                                <option value="active">Active Users (<?php echo $target_counts['active']; ?>)</option>
                                <option value="banned">Banned Users (<?php echo $target_counts['banned']; ?>)</option>
                            </select>
                            <small class="form-text text-muted">Select which users will receive the message</small>
                        </div>

                        <div class="mb-3">
                            <label for="parse_mode" class="form-label">
                                <i class="bi bi-code"></i> Message Format
                            </label>
                            <select class="form-select" id="parse_mode" name="parse_mode">
                                <option value="HTML">HTML</option>
                                <option value="Markdown">Markdown</option>
                                <option value="">Plain Text</option>
                            </select>
                            <small class="form-text text-muted">Telegram message formatting mode</small>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">
                                <i class="bi bi-envelope"></i> Message <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="message" name="message" rows="10" 
                                      required placeholder="Enter your message here..."></textarea>
                            <small class="form-text text-muted">
                                <strong>HTML:</strong> &lt;b&gt;bold&lt;/b&gt;, &lt;i&gt;italic&lt;/i&gt;, &lt;code&gt;code&lt;/code&gt;<br>
                                <strong>Markdown:</strong> *bold*, _italic_, `code`
                            </small>
                        </div>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Warning:</strong> This will send the message to <span id="targetCountDisplay"><?php echo $target_counts['all']; ?></span> users. 
                            This action cannot be undone.
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" 
                                    onclick="return confirm('Are you sure you want to send this broadcast message?')">
                                <i class="bi bi-send"></i> Send Broadcast
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Quick Templates -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-file-text"></i> Quick Templates</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <button class="list-group-item list-group-item-action" onclick="useTemplate('announcement')">
                            <i class="bi bi-megaphone"></i> Announcement
                        </button>
                        <button class="list-group-item list-group-item-action" onclick="useTemplate('maintenance')">
                            <i class="bi bi-tools"></i> Maintenance Notice
                        </button>
                        <button class="list-group-item list-group-item-action" onclick="useTemplate('promotion')">
                            <i class="bi bi-gift"></i> Promotion
                        </button>
                        <button class="list-group-item list-group-item-action" onclick="useTemplate('update')">
                            <i class="bi bi-arrow-repeat"></i> System Update
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart"></i> User Statistics</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Total Users:</strong></td>
                            <td class="text-end"><?php echo number_format($target_counts['all']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Online Now:</strong></td>
                            <td class="text-end text-success"><?php echo number_format($target_counts['online']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Free Users:</strong></td>
                            <td class="text-end"><?php echo number_format($target_counts['free']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Premium Users:</strong></td>
                            <td class="text-end"><?php echo number_format($target_counts['premium']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>VIP Users:</strong></td>
                            <td class="text-end"><?php echo number_format($target_counts['vip']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const targetCounts = <?php echo json_encode($target_counts); ?>;

function updateTargetCount() {
    const target = document.getElementById('target').value;
    const count = targetCounts[target] || 0;
    document.getElementById('targetCountDisplay').textContent = count;
}

function useTemplate(type) {
    const templates = {
        announcement: `<b>📢 Important Announcement</b>

Hello everyone!

We have an important announcement to share with you.

[Your announcement here]

Thank you for being part of our community!

Best regards,
LEGEND CHECKER Team`,
        
        maintenance: `<b>🔧 Scheduled Maintenance</b>

Dear users,

We will be performing scheduled maintenance on [DATE] from [TIME] to [TIME].

During this time, the service may be temporarily unavailable.

We apologize for any inconvenience.

Thank you for your patience!`,
        
        promotion: `<b>🎉 Special Promotion!</b>

Great news!

We're offering a special promotion for a limited time!

[Promotion details]

Don't miss out on this opportunity!

Valid until: [DATE]`,
        
        update: `<b>🚀 System Update</b>

We've just released a new update!

<b>New Features:</b>
• [Feature 1]
• [Feature 2]
• [Feature 3]

<b>Bug Fixes:</b>
• [Fix 1]
• [Fix 2]

Enjoy the improvements!`
    };
    
    document.getElementById('message').value = templates[type] || '';
}

// Initialize target count on load
updateTargetCount();
</script>

<?php require_once 'admin_footer.php'; ?>
