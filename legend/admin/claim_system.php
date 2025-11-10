<?php
require_once 'admin_header.php';
require_once 'admin_utils.php';
require_once 'telegram_utils.php';

// Get current user for display
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'claim_premium':
            $code = $_POST['code'] ?? '';
            $telegram_id = $_POST['telegram_id'] ?? '';
            
            if (!empty($code) && !empty($telegram_id)) {
                $result = claimPremiumCode($code, $telegram_id);
                if ($result['success']) {
                    $successMessage = "Premium code claimed successfully! User {$telegram_id} is now {$result['role']}";
                } else {
                    $errorMessage = $result['error'];
                }
            } else {
                $errorMessage = "Please provide both code and Telegram ID";
            }
            break;
            
        case 'claim_credits':
            $code = $_POST['code'] ?? '';
            $telegram_id = $_POST['telegram_id'] ?? '';
            
            if (!empty($code) && !empty($telegram_id)) {
                $result = claimCreditCode($code, $telegram_id);
                if ($result['success']) {
                    $successMessage = "Credit code claimed successfully! User {$telegram_id} received {$result['credits']} credits";
                } else {
                    $errorMessage = $result['error'];
                }
            } else {
                $errorMessage = "Please provide both code and Telegram ID";
            }
            break;
            
        case 'bulk_claim':
            $codes = array_filter(explode("\n", $_POST['codes'] ?? ''));
            $telegram_id = $_POST['telegram_id'] ?? '';
            
            if (!empty($codes) && !empty($telegram_id)) {
                $success_count = 0;
                $errors = [];
                
                foreach ($codes as $code) {
                    $code = trim($code);
                    if (empty($code)) continue;
                    
                    // Try to claim as premium first
                    $result = claimPremiumCode($code, $telegram_id);
                    if ($result['success']) {
                        $success_count++;
                        continue;
                    }
                    
                    // Try to claim as credit
                    $result = claimCreditCode($code, $telegram_id);
                    if ($result['success']) {
                        $success_count++;
                        continue;
                    }
                    
                    $errors[] = "Code {$code}: " . $result['error'];
                }
                
                if ($success_count > 0) {
                    $successMessage = "Successfully claimed {$success_count} codes!";
                    if (!empty($errors)) {
                        $errorMessage = "Some codes failed: " . implode(", ", $errors);
                    }
                } else {
                    $errorMessage = "No codes could be claimed. Errors: " . implode(", ", $errors);
                }
            } else {
                $errorMessage = "Please provide codes and Telegram ID";
            }
            break;
    }
}

// Get recent claims
$recent_claims = getRecentClaims();

/**
 * Claim a premium code
 */
function claimPremiumCode($code, $telegram_id) {
    global $db;
    
    // Check if code exists and is valid
    $code_data = getPremiumCodeByCode($code);
    if (!$code_data) {
        return ['success' => false, 'error' => 'Invalid premium code'];
    }
    
    if ($code_data['status'] !== 'active') {
        return ['success' => false, 'error' => 'Code is not active'];
    }
    
    if ($code_data['expires_at'] < time()) {
        return ['success' => false, 'error' => 'Code has expired'];
    }
    
    // Check if user exists
    $user = $db->getUserByTelegramId($telegram_id);
    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }
    
    // Check if code was already used by this user
    if (isPremiumCodeUsedByUser($code, $telegram_id)) {
        return ['success' => false, 'error' => 'Code already used by this user'];
    }
    
    // Update user role
    $new_role = $code_data['type'];
    $db->updateUserRole($telegram_id, $new_role);
    
    // Mark code as used
    markPremiumCodeAsUsed($code, $telegram_id);
    
    // Log the claim
    logClaim($telegram_id, 'premium', $code, $code_data['type']);
    
    // Send notification to user
    if (function_exists('sendTelegramMessage')) {
        $message = "🎉 Congratulations! Your account has been upgraded to <b>{$new_role}</b> level!\n\n";
        $message .= "You now have access to premium features for {$code_data['duration_days']} days.";
        sendTelegramMessage($telegram_id, $message);
    }
    
    return ['success' => true, 'user' => $user, 'role' => $new_role];
}

/**
 * Claim a credit code
 */
function claimCreditCode($code, $telegram_id) {
    global $db;
    
    // Check if code exists and is valid
    $code_data = getCreditCodeByCode($code);
    if (!$code_data) {
        return ['success' => false, 'error' => 'Invalid credit code'];
    }
    
    if ($code_data['status'] !== 'active') {
        return ['success' => false, 'error' => 'Code is not active'];
    }
    
    if ($code_data['expires_at'] < time()) {
        return ['success' => false, 'error' => 'Code has expired'];
    }
    
    // Check if user exists
    $user = $db->getUserByTelegramId($telegram_id);
    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }
    
    // Check if code was already used by this user
    if (isCreditCodeUsedByUser($code, $telegram_id)) {
        return ['success' => false, 'error' => 'Code already used by this user'];
    }
    
    // Add credits to user
    $current_credits = $user['credits'] ?? 0;
    $new_credits = $current_credits + $code_data['credit_amount'];
    
    if (method_exists($db, 'updateUserCredits')) {
        $db->updateUserCredits($telegram_id, $new_credits);
    }
    
    // Mark code as used
    markCreditCodeAsUsed($code, $telegram_id);
    
    // Log the claim
    logClaim($telegram_id, 'credit', $code, $code_data['credit_amount']);
    
    // Send notification to user
    if (function_exists('sendTelegramMessage')) {
        $message = "🎉 Congratulations! You've redeemed a credit code!\n\n";
        $message .= "💰 <b>{$code_data['credit_amount']} credits</b> have been added to your account.\n";
        $message .= "💳 Your new balance: <b>{$new_credits} credits</b>";
        sendTelegramMessage($telegram_id, $message);
    }
    
    return [
        'success' => true, 
        'user' => $user, 
        'credits' => $code_data['credit_amount']
    ];
}

/**
 * Check if premium code was used by specific user
 */
function isPremiumCodeUsedByUser($code, $telegram_id) {
    global $db;
    
    if (method_exists($db, 'isPremiumCodeUsedByUser')) {
        return $db->isPremiumCodeUsedByUser($code, $telegram_id);
    }
    
    return false;
}

/**
 * Mark premium code as used
 */
function markPremiumCodeAsUsed($code, $telegram_id) {
    global $db;
    
    if (method_exists($db, 'markPremiumCodeAsUsed')) {
        return $db->markPremiumCodeAsUsed($code, $telegram_id);
    }
    
    return false;
}

/**
 * Get premium code by code string
 */
function getPremiumCodeByCode($code) {
    global $db;
    
    if (method_exists($db, 'getPremiumCodeByCode')) {
        return $db->getPremiumCodeByCode($code);
    }
    
    return null;
}

/**
 * Get credit code by code string
 */
function getCreditCodeByCode($code) {
    global $db;
    
    if (method_exists($db, 'getCreditCodeByCode')) {
        return $db->getCreditCodeByCode($code);
    }
    
    return null;
}

/**
 * Check if credit code was used by specific user
 */
function isCreditCodeUsedByUser($code, $telegram_id) {
    global $db;
    
    if (method_exists($db, 'isCreditCodeUsedByUser')) {
        return $db->isCreditCodeUsedByUser($code, $telegram_id);
    }
    
    return false;
}

/**
 * Mark credit code as used
 */
function markCreditCodeAsUsed($code, $telegram_id) {
    global $db;
    
    if (method_exists($db, 'markCreditCodeAsUsed')) {
        return $db->markCreditCodeAsUsed($code, $telegram_id);
    }
    
    return false;
}

/**
 * Log a claim
 */
function logClaim($telegram_id, $type, $code, $value) {
    global $db;
    
    if (method_exists($db, 'logClaim')) {
        $db->logClaim($telegram_id, $type, $code, $value);
    }
}

/**
 * Get recent claims
 */
function getRecentClaims() {
    global $db;
    
    if (method_exists($db, 'getRecentClaims')) {
        return $db->getRecentClaims(10);
    }
    
    return [];
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-ticket-perforated"></i> Claim System
                    </h1>
                    <p class="text-muted">Redeem premium and credit codes for users</p>
                </div>
                <div>
                    <a href="analytics.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Claim Premium Code -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-gem"></i> Claim Premium Code
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_premium">
                                
                                <div class="mb-3">
                                    <label for="premium_code" class="form-label">Premium Code</label>
                                    <input type="text" class="form-control" id="premium_code" name="code" 
                                           placeholder="e.g., PREMIUM-ABC12345" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="premium_telegram_id" class="form-label">Telegram ID</label>
                                    <input type="text" class="form-control" id="premium_telegram_id" name="telegram_id" 
                                           placeholder="e.g., 7580639195" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-circle"></i> Claim Premium
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Claim Credit Code -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-coin"></i> Claim Credit Code
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_credits">
                                
                                <div class="mb-3">
                                    <label for="credit_code" class="form-label">Credit Code</label>
                                    <input type="text" class="form-control" id="credit_code" name="code" 
                                           placeholder="e.g., CREDIT-ABC12345" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="credit_telegram_id" class="form-label">Telegram ID</label>
                                    <input type="text" class="form-control" id="credit_telegram_id" name="telegram_id" 
                                           placeholder="e.g., 7580639195" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-circle"></i> Claim Credits
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Bulk Claim -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-collection"></i> Bulk Claim
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="bulk_claim">
                                
                                <div class="mb-3">
                                    <label for="bulk_codes" class="form-label">Codes (One per line)</label>
                                    <textarea class="form-control" id="bulk_codes" name="codes" rows="4" 
                                              placeholder="Enter codes here&#10;One per line&#10;e.g.,&#10;PREMIUM-ABC12345&#10;CREDIT-DEF67890" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bulk_telegram_id" class="form-label">Telegram ID</label>
                                    <input type="text" class="form-control" id="bulk_telegram_id" name="telegram_id" 
                                           placeholder="e.g., 7580639195" required>
                                </div>
                                
                                <button type="submit" class="btn btn-warning w-100" 
                                        onclick="return confirm('Are you sure you want to claim all these codes?')">
                                    <i class="bi bi-collection"></i> Claim All Codes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Claims -->
            <?php if (!empty($recent_claims)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history"></i> Recent Claims
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Code</th>
                                    <th>Value</th>
                                    <th>Claimed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_claims as $claim): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($claim['telegram_id']); ?></td>
                                    <td>
                                        <?php if ($claim['type'] === 'premium'): ?>
                                            <span class="badge bg-primary">Premium</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Credits</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($claim['code']); ?></code></td>
                                    <td>
                                        <?php if ($claim['type'] === 'premium'): ?>
                                            <?php echo getRoleBadge($claim['value']); ?>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?php echo $claim['value']; ?> Credits</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($claim['claimed_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="premium_generator.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-gem"></i> Generate Premium Codes
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="credit_generator.php" class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-coin"></i> Generate Credit Codes
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="user_management.php" class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-people"></i> Manage Users
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="analytics.php" class="btn btn-outline-secondary w-100 mb-2">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
