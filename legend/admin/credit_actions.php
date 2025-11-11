<?php
// Check if user ID is provided before including header
if (!isset($_GET['id'])) {
    header('Location: user_management.php');
    exit;
}

require_once 'admin_header.php';
require_once 'admin_utils.php';
require_once '../owner_logger.php';

// Get current user for display
$current_user = getCurrentUser();

$user_id = $_GET['id'];
$user = $db->getUserByTelegramId($user_id);

if (!$user) {
    header('Location: user_management.php?error=user_not_found');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (int)$_POST['amount'];
    $action = $_POST['action'];
    $reason = trim($_POST['reason'] ?? 'Admin adjustment');

    // Validate amount
    if ($amount <= 0) {
        $message = "Amount must be greater than zero.";
        $message_type = "danger";
    } else {
        try {
            $current_credits = $user['credits'];
            
            if ($action === 'add') {
                $db->addCredits($user_id, $amount);
                $new_credits = $current_credits + $amount;
                $message = "Successfully added " . number_format($amount) . " credits.";
                $message_type = "success";
                
                // Log audit action
                $db->logAuditAction($_SESSION['telegram_id'], 'credits_added', $user_id, [
                    'amount' => $amount,
                    'old_credits' => $current_credits,
                    'new_credits' => $new_credits,
                    'reason' => $reason
                ]);
                
                // Owner notification
                try {
                    $ownerLogger = new OwnerLogger();
                    $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                    $ownerLogger->sendAdminAlert(
                        $admin_user,
                        'Credits Added',
                        "💰 Admin added credits:\n\n" .
                        "👤 User: {$user['display_name']}\n" .
                        "🆔 ID: {$user_id}\n" .
                        "➕ Amount: +" . number_format($amount) . "\n" .
                        "💳 New Balance: " . number_format($new_credits) . "\n" .
                        "📝 Reason: {$reason}\n" .
                        "👮 Admin: {$admin_user['display_name']}"
                    );
                } catch (Exception $e) {
                    error_log("Owner logging failed: " . $e->getMessage());
                }
                
            } elseif ($action === 'remove') {
                if ($amount > $current_credits) {
                    $message = "Cannot remove more credits than user has. User has " . number_format($current_credits) . " credits.";
                    $message_type = "danger";
                } else {
                    $new_credits = $current_credits - $amount;
                    $db->updateUserCredits($user_id, $new_credits);
                    $message = "Successfully removed " . number_format($amount) . " credits.";
                    $message_type = "success";
                    
                    // Log audit action
                    $db->logAuditAction($_SESSION['telegram_id'], 'credits_removed', $user_id, [
                        'amount' => $amount,
                        'old_credits' => $current_credits,
                        'new_credits' => $new_credits,
                        'reason' => $reason
                    ]);
                    
                    // Owner notification
                    try {
                        $ownerLogger = new OwnerLogger();
                        $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                        $ownerLogger->sendAdminAlert(
                            $admin_user,
                            'Credits Removed',
                            "💰 Admin removed credits:\n\n" .
                            "👤 User: {$user['display_name']}\n" .
                            "🆔 ID: {$user_id}\n" .
                            "➖ Amount: -" . number_format($amount) . "\n" .
                            "💳 New Balance: " . number_format($new_credits) . "\n" .
                            "📝 Reason: {$reason}\n" .
                            "👮 Admin: {$admin_user['display_name']}"
                        );
                    } catch (Exception $e) {
                        error_log("Owner logging failed: " . $e->getMessage());
                    }
                }
            } elseif ($action === 'set') {
                $db->updateUserCredits($user_id, $amount);
                $message = "Successfully set credits to " . number_format($amount) . ".";
                $message_type = "success";
                
                // Log audit action
                $db->logAuditAction($_SESSION['telegram_id'], 'credits_set', $user_id, [
                    'old_credits' => $current_credits,
                    'new_credits' => $amount,
                    'reason' => $reason
                ]);
                
                // Owner notification
                try {
                    $ownerLogger = new OwnerLogger();
                    $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                    $ownerLogger->sendAdminAlert(
                        $admin_user,
                        'Credits Set',
                        "💰 Admin set credits:\n\n" .
                        "👤 User: {$user['display_name']}\n" .
                        "🆔 ID: {$user_id}\n" .
                        "🔄 Old Balance: " . number_format($current_credits) . "\n" .
                        "💳 New Balance: " . number_format($amount) . "\n" .
                        "📝 Reason: {$reason}\n" .
                        "👮 Admin: {$admin_user['display_name']}"
                    );
                } catch (Exception $e) {
                    error_log("Owner logging failed: " . $e->getMessage());
                }
            }
            
            // Refresh user data
            $user = $db->getUserByTelegramId($user_id);
            
        } catch (Exception $e) {
            $message = "Failed to update credits: " . $e->getMessage();
            $message_type = "danger";
            error_log("Credit action failed: " . $e->getMessage());
        }
    }
}

?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="bi bi-coin"></i> Adjust Credits</h1>
                <a href="user_management.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to User List
                </a>
            </div>
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
        <div class="col-md-4">
            <!-- User Info Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-person-circle"></i> User Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center">
                            <span class="fs-1"><?php echo strtoupper(substr($user['display_name'], 0, 1)); ?></span>
                        </div>
                    </div>
                    <h5 class="text-center mb-3"><?php echo htmlspecialchars($user['display_name']); ?></h5>
                    
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Telegram ID:</strong></td>
                            <td><code><?php echo $user['telegram_id']; ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Role:</strong></td>
                            <td><?php echo getRoleBadge($user['role']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge bg-<?php echo ($user['status'] ?? 'active') === 'banned' ? 'danger' : 'success'; ?>">
                                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Current Credits:</strong></td>
                            <td>
                                <span class="badge bg-warning text-dark fs-5">
                                    <i class="bi bi-coin"></i> <?php echo number_format($user['credits']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Credit Adjustment Card -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-cash-coin"></i> Credit Adjustment</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">
                                    <i class="bi bi-123"></i> Amount <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control form-control-lg" id="amount" name="amount" 
                                       required min="1" placeholder="Enter amount">
                                <small class="form-text text-muted">Minimum: 1 credit</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="action" class="form-label">
                                    <i class="bi bi-gear"></i> Action <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-lg" id="action" name="action" required>
                                    <option value="add">➕ Add Credits</option>
                                    <option value="remove">➖ Remove Credits</option>
                                    <option value="set">🔄 Set Credits (Override)</option>
                                </select>
                                <small class="form-text text-muted">Choose adjustment type</small>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="reason" class="form-label">
                                    <i class="bi bi-chat-quote"></i> Reason (Optional)
                                </label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" 
                                          placeholder="Enter reason for this adjustment (for audit log)"></textarea>
                                <small class="form-text text-muted">This will be logged in the audit trail</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Current Balance:</strong> <?php echo number_format($user['credits']); ?> credits
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Apply Credit Adjustment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-lightning-fill"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="amount" value="100">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="reason" value="Quick add bonus">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="bi bi-plus-circle"></i> +100
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="amount" value="500">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="reason" value="Quick add bonus">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="bi bi-plus-circle"></i> +500
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="amount" value="1000">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="reason" value="Quick add bonus">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="bi bi-plus-circle"></i> +1000
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="amount" value="0">
                            <input type="hidden" name="action" value="set">
                            <input type="hidden" name="reason" value="Reset to zero">
                            <button type="submit" class="btn btn-outline-danger" 
                                    onclick="return confirm('Are you sure you want to reset credits to zero?')">
                                <i class="bi bi-x-circle"></i> Reset to 0
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    font-weight: 600;
}
</style>

<?php require_once 'admin_footer.php'; ?>
