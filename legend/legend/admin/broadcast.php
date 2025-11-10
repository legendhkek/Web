<?php
require_once 'admin_header.php';
require_once 'admin_utils.php';
require_once 'telegram_utils.php';

// Get current user for display
$current_user = getCurrentUser();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_broadcast':
            $message_text = trim($_POST['message'] ?? '');
            $broadcast_type = $_POST['broadcast_type'] ?? 'telegram';
            $target_users = $_POST['target_users'] ?? 'all';
            $priority = $_POST['priority'] ?? 'normal';
            
            if (empty($message_text)) {
                $message = "❌ Please enter a message to broadcast";
                $message_type = 'danger';
            } else {
                $result = sendBroadcastMessage($message_text, $broadcast_type, $target_users, $priority, $current_user['telegram_id']);
                
                if ($result['success']) {
                    $message = "✅ Broadcast sent successfully! " . $result['details'];
                    $message_type = 'success';
                    
                    // Log the action
                    if (method_exists($db, 'logAuditAction')) {
                        $db->logAuditAction(
                            $current_user['telegram_id'],
                            'broadcast_sent',
                            "Sent {$broadcast_type} broadcast to {$target_users} users",
                            ['message_length' => strlen($message_text), 'priority' => $priority]
                        );
                    }
                } else {
                    $message = "❌ Error: " . $result['error'];
                    $message_type = 'danger';
                }
            }
            break;
            
        case 'delete_website_message':
            $message_id = $_POST['message_id'] ?? '';
            if (!empty($message_id)) {
                $result = deleteWebsiteMessage($message_id);
                if ($result['success']) {
                    $message = "✅ Website message deleted successfully";
                    $message_type = 'success';
                } else {
                    $message = "❌ Error: " . $result['error'];
                    $message_type = 'danger';
                }
            }
            break;
    }
}

// Get recent website messages
$website_messages = getWebsiteMessages();
$recent_broadcasts = getRecentBroadcasts();

/**
 * Send broadcast message
 */
function sendBroadcastMessage($message_text, $broadcast_type, $target_users, $priority, $admin_id) {
    global $db;
    
    try {
        $timestamp = time();
        $message_id = generateMessageId();
        
        if ($broadcast_type === 'both' || $broadcast_type === 'telegram') {
            // Send to Telegram
            $telegram_result = sendTelegramBroadcast($message_text, $target_users, $priority);
            if (!$telegram_result['success']) {
                return ['success' => false, 'error' => 'Telegram broadcast failed: ' . $telegram_result['error']];
            }
        }
        
        if ($broadcast_type === 'both' || $broadcast_type === 'website') {
            // Save to website
            $website_result = saveWebsiteMessage($message_id, $message_text, $priority, $admin_id, $target_users);
            if (!$website_result['success']) {
                return ['success' => false, 'error' => 'Website message save failed: ' . $website_result['error']];
            }
        }
        
        // Log the broadcast
        logBroadcast($message_id, $message_text, $broadcast_type, $target_users, $priority, $admin_id);
        
        $details = [];
        if ($broadcast_type === 'both' || $broadcast_type === 'telegram') {
            $details[] = "Telegram: {$telegram_result['sent_count']} users";
        }
        if ($broadcast_type === 'both' || $broadcast_type === 'website') {
            $details[] = "Website: Active message posted";
        }
        
        return [
            'success' => true,
            'details' => implode(', ', $details),
            'message_id' => $message_id
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Broadcast failed: ' . $e->getMessage()];
    }
}

/**
 * Send Telegram broadcast
 */
function sendTelegramBroadcast($message_text, $target_users, $priority) {
    global $db;
    
    try {
        $users = [];
        
        switch ($target_users) {
            case 'all':
                $users = $db->getAllUsers();
                break;
            case 'premium':
                $users = $db->getUsersByRole(['premium', 'vip', 'admin', 'owner']);
                break;
            case 'vip':
                $users = $db->getUsersByRole(['vip', 'admin', 'owner']);
                break;
            case 'admin':
                $users = $db->getUsersByRole(['admin', 'owner']);
                break;
            default:
                $users = $db->getAllUsers();
        }
        
        $sent_count = 0;
        $errors = [];
        
        foreach ($users as $user) {
            if (!empty($user['telegram_id'])) {
                try {
                    $formatted_message = formatBroadcastMessage($message_text, $priority);
                    $result = sendTelegramMessage($user['telegram_id'], $formatted_message);
                    
                    if ($result) {
                $sent_count++;
                    } else {
                        $errors[] = "Failed to send to user {$user['telegram_id']}";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error sending to user {$user['telegram_id']}: " . $e->getMessage();
                }
                
                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 second
            }
        }
        
        return [
            'success' => true,
            'sent_count' => $sent_count,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Save website message
 */
function saveWebsiteMessage($message_id, $message_text, $priority, $admin_id, $target_users) {
    global $db;
    
    try {
        $data = [
            'message_id' => $message_id,
            'message' => $message_text,
            'priority' => $priority,
            'admin_id' => $admin_id,
            'target_users' => $target_users,
            'created_at' => time(),
            'expires_at' => time() + (7 * 24 * 60 * 60), // 7 days
            'status' => 'active'
        ];
        
        if (method_exists($db, 'insertWebsiteMessage')) {
            return $db->insertWebsiteMessage($data);
        }
        
        // Fallback to file-based storage
        return saveWebsiteMessageToFile($data);
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Save website message to file (fallback)
 */
function saveWebsiteMessageToFile($data) {
    try {
        $messages_file = '../data/website_messages.json';
        $messages = [];
        
        if (file_exists($messages_file)) {
            $messages = json_decode(file_get_contents($messages_file), true) ?: [];
        }
        
        $messages[] = $data;
        
        if (file_put_contents($messages_file, json_encode($messages, JSON_PRETTY_PRINT))) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to write to file'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get website messages
 */
function getWebsiteMessages() {
    global $db;
    
    try {
        if (method_exists($db, 'getWebsiteMessages')) {
            return $db->getWebsiteMessages();
        }
        
        // Fallback to file-based retrieval
        return getWebsiteMessagesFromFile();
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get website messages from file (fallback)
 */
function getWebsiteMessagesFromFile() {
    try {
        $messages_file = '../data/website_messages.json';
        
        if (!file_exists($messages_file)) {
            return [];
        }
        
        $messages = json_decode(file_get_contents($messages_file), true) ?: [];
        
        // Filter active messages and sort by priority
        $active_messages = array_filter($messages, function($msg) {
            return $msg['status'] === 'active' && $msg['expires_at'] > time();
        });
        
        // Sort by priority (high, normal, low)
        $priority_order = ['high' => 3, 'normal' => 2, 'low' => 1];
        usort($active_messages, function($a, $b) use ($priority_order) {
            $a_priority = $priority_order[$a['priority']] ?? 1;
            $b_priority = $priority_order[$b['priority']] ?? 1;
            return $b_priority - $a_priority;
        });
        
        return $active_messages;
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Delete website message
 */
function deleteWebsiteMessage($message_id) {
    global $db;
    
    try {
        if (method_exists($db, 'deleteWebsiteMessage')) {
            return $db->deleteWebsiteMessage($message_id);
        }
        
        // Fallback to file-based deletion
        return deleteWebsiteMessageFromFile($message_id);
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete website message from file (fallback)
 */
function deleteWebsiteMessageFromFile($message_id) {
    try {
        $messages_file = '../data/website_messages.json';
        
        if (!file_exists($messages_file)) {
            return ['success' => false, 'error' => 'Messages file not found'];
        }
        
        $messages = json_decode(file_get_contents($messages_file), true) ?: [];
        
        $updated_messages = array_filter($messages, function($msg) use ($message_id) {
            return $msg['message_id'] !== $message_id;
        });
        
        if (file_put_contents($messages_file, json_encode($updated_messages, JSON_PRETTY_PRINT))) {
            return ['success' => true];
    } else {
            return ['success' => false, 'error' => 'Failed to update file'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Generate unique message ID
 */
function generateMessageId() {
    return 'Ex-Chk-Web-' . strtoupper(substr(md5(uniqid() . time()), 0, 8));
}

/**
 * Format broadcast message for Telegram
 */
function formatBroadcastMessage($message_text, $priority) {
    $priority_emoji = [
        'high' => '🚨',
        'normal' => '📢',
        'low' => 'ℹ️'
    ];
    
    $emoji = $priority_emoji[$priority] ?? '📢';
    
    return "{$emoji} **BROADCAST MESSAGE**\n\n{$message_text}\n\n_Message ID: " . generateMessageId() . "_";
}

/**
 * Log broadcast
 */
function logBroadcast($message_id, $message_text, $broadcast_type, $target_users, $priority, $admin_id) {
    global $db;
    
    try {
        if (method_exists($db, 'logBroadcast')) {
            return $db->logBroadcast($message_id, $message_text, $broadcast_type, $target_users, $priority, $admin_id);
        }
        
        // Fallback to file-based logging
        return logBroadcastToFile($message_id, $message_text, $broadcast_type, $target_users, $priority, $admin_id);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log broadcast to file (fallback)
 */
function logBroadcastToFile($message_id, $message_text, $broadcast_type, $target_users, $priority, $admin_id) {
    try {
        $log_file = '../data/broadcast_logs.json';
        $logs = [];
        
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true) ?: [];
        }
        
        $logs[] = [
            'message_id' => $message_id,
            'message' => $message_text,
            'broadcast_type' => $broadcast_type,
            'target_users' => $target_users,
            'priority' => $priority,
            'admin_id' => $admin_id,
            'timestamp' => time()
        ];
        
        return file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT)) !== false;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get recent broadcasts
 */
function getRecentBroadcasts() {
    try {
        $log_file = '../data/broadcast_logs.json';
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $logs = json_decode(file_get_contents($log_file), true) ?: [];
        
        // Get last 10 broadcasts
        return array_slice(array_reverse($logs), 0, 10);
        
    } catch (Exception $e) {
        return [];
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-megaphone"></i> Broadcast System
                </h1>
                <div class="d-flex gap-2">
                    <a href="analytics.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                    <?php endif; ?>

            <div class="row">
                <!-- Send Broadcast Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-send"></i> Send New Broadcast
                            </h5>
                        </div>
                        <div class="card-body">
                    <form method="POST">
                                <input type="hidden" name="action" value="send_broadcast">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="broadcast_type" class="form-label">Broadcast Type</label>
                                            <select class="form-select" id="broadcast_type" name="broadcast_type" required>
                                                <option value="telegram">Telegram Only</option>
                                                <option value="website">Website Only</option>
                                                <option value="both" selected>Both Telegram & Website</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="priority" class="form-label">Priority</label>
                                            <select class="form-select" id="priority" name="priority" required>
                                                <option value="low">Low</option>
                                                <option value="normal" selected>Normal</option>
                                                <option value="high">High</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                        <div class="mb-3">
                                    <label for="target_users" class="form-label">Target Users</label>
                                    <select class="form-select" id="target_users" name="target_users" required>
                                        <option value="all" selected>All Users</option>
                                <option value="premium">Premium Users Only</option>
                                        <option value="vip">VIP Users Only</option>
                                        <option value="admin">Admin Users Only</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="6" 
                                              placeholder="Enter your broadcast message here..." required></textarea>
                                    <div class="form-text">
                                        <span id="char_count">0</span> characters
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-send"></i> Send Broadcast
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                        </div>

                <!-- Current Website Messages -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-globe"></i> Active Website Messages
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($website_messages)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No active website messages</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($website_messages as $msg): ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-<?php echo $msg['priority'] === 'high' ? 'danger' : ($msg['priority'] === 'normal' ? 'primary' : 'secondary'); ?>">
                                                <?php echo ucfirst($msg['priority']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo date('M j, g:i A', $msg['created_at']); ?>
                                            </small>
                                        </div>
                                        <p class="mb-2"><?php echo htmlspecialchars($msg['message']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <code><?php echo $msg['message_id']; ?></code>
                                            </small>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?')">
                                                <input type="hidden" name="action" value="delete_website_message">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                        </button>
                    </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Broadcasts -->
            <?php if (!empty($recent_broadcasts)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history"></i> Recent Broadcasts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Message ID</th>
                                    <th>Message</th>
                                    <th>Type</th>
                                    <th>Target</th>
                                    <th>Priority</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_broadcasts as $broadcast): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($broadcast['message_id']); ?></code>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($broadcast['message']); ?>">
                                            <?php echo htmlspecialchars($broadcast['message']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $type_badges = [
                                            'telegram' => 'bg-info',
                                            'website' => 'bg-success',
                                            'both' => 'bg-primary'
                                        ];
                                        $type_badge = $type_badges[$broadcast['broadcast_type']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $type_badge; ?>">
                                            <?php echo ucfirst($broadcast['broadcast_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($broadcast['target_users']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $priority_colors = [
                                            'high' => 'danger',
                                            'normal' => 'primary',
                                            'low' => 'secondary'
                                        ];
                                        $priority_color = $priority_colors[$broadcast['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $priority_color; ?>">
                                            <?php echo ucfirst($broadcast['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, g:i A', $broadcast['timestamp']); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Character counter
document.getElementById('message').addEventListener('input', function() {
    const charCount = this.value.length;
    document.getElementById('char_count').textContent = charCount;
    
    // Change color based on length
    const counter = document.getElementById('char_count');
    if (charCount > 1000) {
        counter.className = 'text-danger';
    } else if (charCount > 500) {
        counter.className = 'text-warning';
    } else {
        counter.className = 'text-muted';
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php require_once 'admin_footer.php'; ?>
