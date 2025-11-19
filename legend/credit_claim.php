<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'utils.php';

// Use secure session initialization
initSecureSession();

// Check if user is logged in
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
$user = $db->getUserByTelegramId($telegram_id);

if (!$user) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle form submissions with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $message = "❌ Security error: Invalid request token";
        $message_type = 'danger';
    } else {
        // Rate limiting
        if (!checkRateLimitAdvanced('code_claim', 5, 300)) {
            $message = "❌ Too many attempts. Please wait a few minutes.";
            $message_type = 'danger';
        } else {
            $action = sanitizeInput($_POST['action'] ?? '', 'alphanumeric');
            
            switch ($action) {
                case 'claim_premium':
                    $code = sanitizeInput(trim($_POST['code'] ?? ''), 'alphanumeric');
                    
                    if (!empty($code) && strlen($code) <= 50) {
                        $result = claimPremiumCode($code, $telegram_id);
                        if ($result['success']) {
                            $message = "🎉 Congratulations! Your account has been upgraded to {$result['role']} level!";
                            $message_type = 'success';
                            // Refresh user data
                            $user = $db->getUserByTelegramId($telegram_id);
                            // Notify Telegram channel (toggle)
                            if (SiteConfig::get('notify_claim', true)) {
                                $notif = "💎 <b>Premium Code Redeemed</b>\n\n" .
                                     "👤 <b>User:</b> " . formatUserName($user) . "\n" .
                                     "🆔 <b>ID:</b> {$telegram_id}\n" .
                                     "🎟️ <b>Code:</b> " . htmlspecialchars($code) . "\n" .
                                     "⭐ <b>New Role:</b> " . htmlspecialchars(ucfirst($result['role'])) . "\n";
                                sendTelegramHtml($notif);
                            }
                        } else {
                            $message = "❌ Error: " . htmlspecialchars($result['error']);
                            $message_type = 'danger';
                        }
                    } else {
                        $message = "❌ Please enter a valid premium code";
                        $message_type = 'danger';
                    }
                    break;
                    
                case 'claim_credits':
                    $code = sanitizeInput(trim($_POST['code'] ?? ''), 'alphanumeric');
                    
                    if (!empty($code) && strlen($code) <= 50) {
                        $result = claimCreditCode($code, $telegram_id);
                        if ($result['success']) {
                            $message = "🎉 Success! You've received {$result['credits']} credits!";
                            $message_type = 'success';
                            // Refresh user data
                            $user = $db->getUserByTelegramId($telegram_id);
                            // Notify Telegram channel (toggle)
                            if (SiteConfig::get('notify_claim', true)) {
                                $notif = "💰 <b>Credit Code Redeemed</b>\n\n" .
                                     "👤 <b>User:</b> " . formatUserName($user) . "\n" .
                                     "🆔 <b>ID:</b> {$telegram_id}\n" .
                                     "🎟️ <b>Code:</b> " . htmlspecialchars($code) . "\n" .
                                     "➕ <b>Credits Added:</b> {$result['credits']}\n" .
                                     "💳 <b>New Balance:</b> " . formatNumber($user['credits'] ?? 0);
                                sendTelegramHtml($notif);
                            }
                        } else {
                            $message = "❌ Error: " . htmlspecialchars($result['error']);
                            $message_type = 'danger';
                        }
                    } else {
                        $message = "❌ Please enter a valid credit code";
                        $message_type = 'danger';
                    }
                    break;
            }
        }
    }
}

// Get user's recent claims
$recent_claims = getUserRecentClaims($telegram_id);

/**
 * Claim premium code
 */
function claimPremiumCode($code, $telegram_id) {
    global $db, $user;
    
    try {
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
        
        // Check if code was already used by this user
        if (isPremiumCodeUsedByUser($code, $telegram_id)) {
            return ['success' => false, 'error' => 'Code already used by this user'];
        }
        
        // Update user role
        $new_role = $code_data['type'];
        $result = $db->updateUserRole($telegram_id, $new_role);
        
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to update user role'];
        }
        
        // Mark code as used
        markPremiumCodeAsUsed($code, $telegram_id);
        
        // Log the claim
        logUserClaim($telegram_id, 'premium', $code, $code_data['type']);
        
        return ['success' => true, 'role' => $new_role];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'System error: ' . $e->getMessage()];
    }
}

/**
 * Claim credit code
 */
function claimCreditCode($code, $telegram_id) {
    global $db, $user;
    
    try {
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
        
        // Check if code was already used by this user
        if (isCreditCodeUsedByUser($code, $telegram_id)) {
            return ['success' => false, 'error' => 'Code already used by this user'];
        }
        
        // Add credits to user
        $current_credits = intval($user['credits'] ?? 0);
        $new_credits = $current_credits + intval($code_data['credit_amount']);
        
        // Update user credits in database
        $result = updateUserCredits($telegram_id, $new_credits);
        
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to update user credits'];
        }
        
        // Mark code as used
        markCreditCodeAsUsed($code, $telegram_id);
        
        // Log the claim
        logUserClaim($telegram_id, 'credit', $code, $code_data['credit_amount']);
        
        return ['success' => true, 'credits' => $code_data['credit_amount']];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'System error: ' . $e->getMessage()];
    }
}

/**
 * Update user credits
 */
function updateUserCredits($telegram_id, $new_credits) {
    global $db;
    
    try {
        if (method_exists($db, 'updateUserCredits')) {
            return $db->updateUserCredits($telegram_id, $new_credits);
        }
        
        // Fallback: Update directly in database
        $collection = $db->getCollection('users');
        $result = $collection->updateOne(
            ['telegram_id' => $telegram_id],
            ['$set' => ['credits' => $new_credits, 'updated_at' => time()]]
        );
        
        return $result->getModifiedCount() > 0;
        
    } catch (Exception $e) {
        error_log("Error updating user credits: " . $e->getMessage());
        return false;
    }
}

// Helper functions (these would be implemented in your database class)
function getPremiumCodeByCode($code) {
    global $db;
    
    try {
        if (method_exists($db, 'getPremiumCodeByCode')) {
            return $db->getPremiumCodeByCode($code);
        }
        
        // Fallback: Check in premium codes collection
        $collection = $db->getCollection('premium_codes');
        $code_data = $collection->findOne(['code' => $code]);
        
        return $code_data ? $code_data : null;
        
    } catch (Exception $e) {
        error_log("Error getting premium code: " . $e->getMessage());
        return null;
    }
}

function getCreditCodeByCode($code) {
    global $db;
    
    try {
        if (method_exists($db, 'getCreditCodeByCode')) {
            return $db->getCreditCodeByCode($code);
        }
        
        // Fallback: Check in credit codes collection
        $collection = $db->getCollection('credit_codes');
        $code_data = $collection->findOne(['code' => $code]);
        
        return $code_data ? $code_data : null;
        
    } catch (Exception $e) {
        error_log("Error getting credit code: " . $e->getMessage());
        return null;
    }
}

function isPremiumCodeUsedByUser($code, $telegram_id) {
    global $db;
    
    try {
        if (method_exists($db, 'isPremiumCodeUsedByUser')) {
            return $db->isPremiumCodeUsedByUser($code, $telegram_id);
        }
        
        // Fallback: Check in used codes collection
        $collection = $db->getCollection('used_codes');
        $used = $collection->findOne([
            'code' => $code,
            'telegram_id' => $telegram_id,
            'type' => 'premium'
        ]);
        
        return $used !== null;
        
    } catch (Exception $e) {
        error_log("Error checking premium code usage: " . $e->getMessage());
        return false;
    }
}

function isCreditCodeUsedByUser($code, $telegram_id) {
    global $db;
    
    try {
        if (method_exists($db, 'isCreditCodeUsedByUser')) {
            return $db->isCreditCodeUsedByUser($code, $telegram_id);
        }
        
        // Fallback: Check in used codes collection
        $collection = $db->getCollection('used_codes');
        $used = $collection->findOne([
            'code' => $code,
            'telegram_id' => $telegram_id,
            'type' => 'credit'
        ]);
        
        return $used !== null;
        
    } catch (Exception $e) {
        error_log("Error checking credit code usage: " . $e->getMessage());
        return false;
    }
}

function markPremiumCodeAsUsed($code, $telegram_id) {
    global $db;
    
    try {
        if (method_exists($db, 'markPremiumCodeAsUsed')) {
            return $db->markPremiumCodeAsUsed($code, $telegram_id);
        }
        
        // Fallback: Mark as used in database
        $collection = $db->getCollection('used_codes');
        $result = $collection->insertOne([
            'code' => $code,
            'telegram_id' => $telegram_id,
            'type' => 'premium',
            'used_at' => time()
        ]);
        
        return $result->getInsertedCount() > 0;
        
    } catch (Exception $e) {
        error_log("Error marking premium code as used: " . $e->getMessage());
        return false;
    }
}

function markCreditCodeAsUsed($code, $telegram_id) {
    global $db;
    
    try {
        if (method_exists($db, 'markCreditCodeAsUsed')) {
            return $db->markCreditCodeAsUsed($code, $telegram_id);
        }
        
        // Fallback: Mark as used in database
        $collection = $db->getCollection('used_codes');
        $result = $collection->insertOne([
            'code' => $code,
            'telegram_id' => $telegram_id,
            'type' => 'credit',
            'used_at' => time()
        ]);
        
        return $result->getInsertedCount() > 0;
        
    } catch (Exception $e) {
        error_log("Error marking credit code as used: " . $e->getMessage());
        return false;
    }
}

function logUserClaim($telegram_id, $type, $code, $value) {
    global $db;
    
    try {
        if (method_exists($db, 'logUserClaim')) {
            return $db->logUserClaim($telegram_id, $type, $code, $value);
        }
        
        // Fallback: Log to claims collection
        $collection = $db->getCollection('user_claims');
        $result = $collection->insertOne([
            'telegram_id' => $telegram_id,
            'type' => $type,
            'code' => $code,
            'value' => $value,
            'claimed_at' => time()
        ]);
        
        return $result->getInsertedCount() > 0;
        
    } catch (Exception $e) {
        error_log("Error logging user claim: " . $e->getMessage());
        return false;
    }
}

function getUserRecentClaims($telegram_id) {
    global $db;
    
    try {
        if (method_exists($db, 'getUserRecentClaims')) {
            return $db->getUserRecentClaims($telegram_id, 5);
        }
        
        // Fallback: Get from claims collection
        $collection = $db->getCollection('user_claims');
        $claims = $collection->find(
            ['telegram_id' => $telegram_id],
            ['sort' => ['claimed_at' => -1], 'limit' => 5]
        )->toArray();
        
        return $claims;
        
    } catch (Exception $e) {
        error_log("Error getting user claims: " . $e->getMessage());
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Claim - Ex Chk Web</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .claim-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
        }
        .user-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
        }
        .code-input {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 15px;
            font-size: 18px;
            text-align: center;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
        }
        .code-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-claim {
            border-radius: 15px;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .status-badge {
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .avatar-xl {
            width: 80px;
            height: 80px;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: bold;
        }
        .alert {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .table {
            border-radius: 15px;
            overflow: hidden;
        }
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        .table td {
            border: none;
            vertical-align: middle;
        }
        .quick-actions .btn {
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .quick-actions .btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="text-center mb-4">
                    <h1 class="text-white display-4 fw-bold">
                        <i class="bi bi-coin"></i> Credit Claim Center
                    </h1>
                    <p class="text-white-50 lead">Redeem your codes and upgrade your account</p>
                </div>

                <!-- User Info Card -->
                <div class="claim-card">
                    <div class="user-info text-center">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="avatar-xl mb-3">
                                    <?php echo strtoupper(substr($user['display_name'] ?? 'U', 0, 2)); ?>
                                </div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['display_name'] ?? 'User'); ?></h4>
                                <p class="mb-0 opacity-75">@<?php echo htmlspecialchars($user['username'] ?? 'username'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="mb-2">Current Balance</h5>
                                    <div class="display-6 fw-bold"><?php echo number_format($user['credits'] ?? 0); ?></div>
                                    <small class="opacity-75">Credits</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <h5 class="mb-2">Account Type</h5>
                                    <?php
                                    $role_colors = [
                                        'free' => 'light',
                                        'premium' => 'warning',
                                        'vip' => 'success',
                                        'admin' => 'info',
                                        'owner' => 'danger'
                                    ];
                                    $role_color = $role_colors[$user['role'] ?? 'free'] ?? 'light';
                                    ?>
                                    <span class="badge bg-<?php echo $role_color; ?> status-badge">
                                        <?php echo ucfirst($user['role'] ?? 'free'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
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
                    <!-- Premium Code Claim -->
                    <div class="col-md-6">
                        <div class="claim-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="bi bi-gem text-warning" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="card-title">Premium Code</h5>
                                <p class="text-muted">Upgrade your account with premium codes</p>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="claim_premium">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div class="mb-3">
                                        <input type="text" class="form-control code-input" name="code" 
                                               placeholder="PREMIUM-XXXXX" maxlength="20" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-claim w-100">
                                        <i class="bi bi-check-circle"></i> Claim Premium
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Code Claim -->
                    <div class="col-md-6">
                        <div class="claim-card">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <i class="bi bi-coin text-success" style="font-size: 3rem;"></i>
                                </div>
                                <h5 class="card-title">Credit Code</h5>
                                <p class="text-muted">Redeem credit codes for instant credits</p>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="claim_credits">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div class="mb-3">
                                        <input type="text" class="form-control code-input" name="code" 
                                               placeholder="CREDIT-XXXXX" maxlength="20" required>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-claim w-100">
                                        <i class="bi bi-check-circle"></i> Claim Credits
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Claims History -->
                <?php if (!empty($recent_claims)): ?>
                <div class="claim-card">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-clock-history"></i> Recent Claims
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Code</th>
                                        <th>Value</th>
                                        <th>Claimed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_claims as $claim): ?>
                                    <tr>
                                        <td>
                                            <?php if ($claim['type'] === 'premium'): ?>
                                                <span class="badge bg-warning">Premium</span>
                                            <?php elseif ($claim['type'] === 'credit'): ?>
                                                <span class="badge bg-success">Credits</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Other</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($claim['code']); ?></code></td>
                                        <td>
                                            <?php if ($claim['type'] === 'premium'): ?>
                                                <?php echo ucfirst($claim['value']); ?>
                                            <?php else: ?>
                                                <?php echo $claim['value']; ?> Credits
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', $claim['claimed_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="claim-card">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-lightning"></i> Quick Actions
                        </h5>
                        <div class="row quick-actions">
                            <div class="col-md-3">
                                <a href="dashboard.php" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="tools.php" class="btn btn-outline-success w-100 mb-2">
                                    <i class="bi bi-tools"></i> Tools
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="wallet.php" class="btn btn-outline-warning w-100 mb-2">
                                    <i class="bi bi-wallet2"></i> Wallet
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="logout.php" class="btn btn-outline-danger w-100 mb-2">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format code inputs
        document.querySelectorAll('.code-input').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.toUpperCase();
                value = value.replace(/[^A-Z0-9-]/g, '');
                e.target.value = value;
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
