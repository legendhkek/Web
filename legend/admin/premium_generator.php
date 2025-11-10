<?php
require_once 'admin_header.php';
require_once 'admin_utils.php';

// Get current user for display
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_codes':
            $count = (int)($_POST['count'] ?? 10);
            $duration = (int)($_POST['duration'] ?? 30); // days
            $type = $_POST['type'] ?? 'premium';
            
            $generated_codes = [];
            for ($i = 0; $i < $count; $i++) {
                $code = generatePremiumCode($type, $duration);
                $generated_codes[] = $code;
            }
            
            $successMessage = "Generated {$count} {$type} codes successfully!";
            break;
            
        case 'activate_code':
            $code = $_POST['code'] ?? '';
            $telegram_id = $_POST['telegram_id'] ?? '';
            
            if (!empty($code) && !empty($telegram_id)) {
                $result = activatePremiumCode($code, $telegram_id);
                if ($result['success']) {
                    $successMessage = "Premium code activated successfully for user {$telegram_id}";
                } else {
                    $errorMessage = $result['error'];
                }
            } else {
                $errorMessage = "Please provide both code and Telegram ID";
            }
            break;
            
        case 'delete_code':
            $code_id = $_POST['code_id'] ?? '';
            if (!empty($code_id)) {
                $result = deletePremiumCode($code_id);
                if ($result) {
                    $successMessage = "Premium code deleted successfully";
                } else {
                    $errorMessage = "Failed to delete premium code";
                }
            }
            break;
    }
}

// Get existing premium codes
$premium_codes = getPremiumCodes();
$used_codes = getUsedPremiumCodes();

/**
 * Generate a premium code
 */
function generatePremiumCode($type, $duration) {
    global $db;
    
    $code = 'PREMIUM-' . strtoupper(generateRandomString(8));
    $expires_at = time() + ($duration * 24 * 60 * 60);
    
    $data = [
        'code' => $code,
        'type' => $type,
        'duration_days' => $duration,
        'expires_at' => $expires_at,
        'created_at' => time(),
        'created_by' => $_SESSION['telegram_id'] ?? 'admin',
        'status' => 'active'
    ];
    
    // Save to database (you'll need to create this table)
    if (method_exists($db, 'insertPremiumCode')) {
        $db->insertPremiumCode($data);
    }
    
    return $data;
}

/**
 * Activate a premium code
 */
function activatePremiumCode($code, $telegram_id) {
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
    
    // Update user role
    $new_role = $code_data['type'];
    $db->updateUserRole($telegram_id, $new_role);
    
    // Mark code as used
    markPremiumCodeAsUsed($code, $telegram_id);
    
    // Send notification to user
    if (function_exists('sendTelegramMessage')) {
        $message = "🎉 Congratulations! Your account has been upgraded to <b>{$new_role}</b> level!\n\n";
        $message .= "You now have access to premium features for {$code_data['duration_days']} days.";
        sendTelegramMessage($telegram_id, $message);
    }
    
    return ['success' => true, 'user' => $user, 'code' => $code_data];
}

/**
 * Get premium codes from database
 */
function getPremiumCodes() {
    global $db;
    
    if (method_exists($db, 'getPremiumCodes')) {
        return $db->getPremiumCodes();
    }
    
    // Fallback - return sample data
    return [
        [
            'id' => 1,
            'code' => 'PREMIUM-ABC12345',
            'type' => 'premium',
            'duration_days' => 30,
            'expires_at' => time() + (30 * 24 * 60 * 60),
            'status' => 'active',
            'created_at' => time()
        ]
    ];
}

/**
 * Get used premium codes
 */
function getUsedPremiumCodes() {
    global $db;
    
    if (method_exists($db, 'getUsedPremiumCodes')) {
        return $db->getUsedPremiumCodes();
    }
    
    return [];
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
 * Delete premium code
 */
function deletePremiumCode($code_id) {
    global $db;
    
    if (method_exists($db, 'deletePremiumCode')) {
        return $db->deletePremiumCode($code_id);
    }
    
    return false;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-gem"></i> Premium Generator
                    </h1>
                    <p class="text-muted">Generate and manage premium access codes for users</p>
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
                <!-- Generate Codes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-plus-circle"></i> Generate Premium Codes
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="generate_codes">
                                
                                <div class="mb-3">
                                    <label for="count" class="form-label">Number of Codes</label>
                                    <input type="number" class="form-control" id="count" name="count" 
                                           value="10" min="1" max="100" required>
                                    <div class="form-text">How many codes to generate (1-100)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="type" class="form-label">Premium Type</label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="premium">Premium</option>
                                        <option value="vip">VIP</option>
                                        <option value="pro">Pro</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="duration" class="form-label">Duration (Days)</label>
                                    <input type="number" class="form-control" id="duration" name="duration" 
                                           value="30" min="1" max="365" required>
                                    <div class="form-text">How long the premium access lasts</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-magic"></i> Generate Codes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Activate Code -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-check-circle"></i> Activate Premium Code
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="activate_code">
                                
                                <div class="mb-3">
                                    <label for="code" class="form-label">Premium Code</label>
                                    <input type="text" class="form-control" id="code" name="code" 
                                           placeholder="e.g., PREMIUM-ABC12345" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="telegram_id" class="form-label">Telegram ID</label>
                                    <input type="text" class="form-control" id="telegram_id" name="telegram_id" 
                                           placeholder="e.g., 7580639195" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Activate Code
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Premium Codes -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check"></i> Active Premium Codes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($premium_codes as $code): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($code['code']); ?></code>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" 
                                                onclick="copyToClipboard('<?php echo $code['code']; ?>')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                    <td><?php echo getRoleBadge($code['type']); ?></td>
                                    <td><?php echo $code['duration_days']; ?> days</td>
                                    <td><?php echo formatDate($code['expires_at']); ?></td>
                                    <td>
                                        <?php if ($code['status'] === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Used</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_code">
                                            <input type="hidden" name="code_id" value="<?php echo $code['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this code?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Used Premium Codes -->
            <?php if (!empty($used_codes)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history"></i> Used Premium Codes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Used By</th>
                                    <th>Used At</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($used_codes as $code): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($code['code']); ?></code></td>
                                    <td><?php echo getRoleBadge($code['type']); ?></td>
                                    <td><?php echo htmlspecialchars($code['used_by']); ?></td>
                                    <td><?php echo formatDate($code['used_at']); ?></td>
                                    <td><?php echo $code['duration_days']; ?> days</td>
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
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const button = event.target.closest('button');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check"></i>';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    });
}
</script>

<?php require_once 'admin_footer.php'; ?>
