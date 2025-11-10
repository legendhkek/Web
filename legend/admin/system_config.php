<?php
require_once 'admin_header.php';

// Check if user is owner (this page requires owner access)
if (!isOwner()) {
    header('Location: analytics.php?error=owner_required');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newConfig = [
        'maintenance_mode' => isset($_POST['maintenance_mode']),
        'bot_token' => $_POST['bot_token'],
        'notification_chat_id' => $_POST['notification_chat_id'],
        'daily_credit_amount' => (int)$_POST['daily_credit_amount'],
        'card_check_cost' => (int)$_POST['card_check_cost'],
        'site_check_cost' => (int)$_POST['site_check_cost'],
    ];

    if (SiteConfig::save($newConfig)) {
        $successMessage = 'Configuration saved successfully!';
    } else {
        $errorMessage = 'Error saving configuration. Please check file permissions.';
    }
}

// Load current settings
$maintenance_mode = SiteConfig::get('maintenance_mode', false);
$bot_token = SiteConfig::get('bot_token', TelegramConfig::BOT_TOKEN);
$notification_chat_id = SiteConfig::get('notification_chat_id', TelegramConfig::NOTIFICATION_CHAT_ID);
$daily_credit_amount = SiteConfig::get('daily_credit_amount', AppConfig::DAILY_CREDIT_AMOUNT);
$card_check_cost = SiteConfig::get('card_check_cost', AppConfig::CARD_CHECK_COST);
$site_check_cost = SiteConfig::get('site_check_cost', AppConfig::SITE_CHECK_COST);

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-gear"></i> System Configuration
                    </h1>
                    <p class="text-muted">Manage site-wide settings and configurations.</p>
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

            <!-- Configuration Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-sliders"></i> Site Settings
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <!-- Maintenance Mode -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" 
                                           name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        <strong>Maintenance Mode</strong>
                                    </label>
                                    <div class="form-text">Enable this to put the site in maintenance mode.</div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Telegram Configuration -->
                        <h6 class="mb-3">
                            <i class="bi bi-telegram"></i> Telegram Bot Configuration
                        </h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="bot_token" class="form-label">Bot Token</label>
                                <input type="text" class="form-control" id="bot_token" name="bot_token" 
                                       value="<?php echo htmlspecialchars($bot_token); ?>" required>
                                <div class="form-text">Your Telegram bot token from @BotFather</div>
                            </div>
                            <div class="col-md-6">
                                <label for="notification_chat_id" class="form-label">Notification Chat ID</label>
                                <input type="text" class="form-control" id="notification_chat_id" 
                                       name="notification_chat_id" 
                                       value="<?php echo htmlspecialchars($notification_chat_id); ?>" required>
                                <div class="form-text">Chat ID where notifications will be sent</div>
                            </div>
                        </div>

                        <hr>

                        <!-- Credit Configuration -->
                        <h6 class="mb-3">
                            <i class="bi bi-coin"></i> Credit System Configuration
                        </h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="daily_credit_amount" class="form-label">Daily Credit Amount</label>
                                <input type="number" class="form-control" id="daily_credit_amount" 
                                       name="daily_credit_amount" 
                                       value="<?php echo (int)$daily_credit_amount; ?>" min="1" max="1000" required>
                                <div class="form-text">Credits given to users daily</div>
                            </div>
                            <div class="col-md-4">
                                <label for="card_check_cost" class="form-label">Card Check Cost</label>
                                <input type="number" class="form-control" id="card_check_cost" 
                                       name="card_check_cost" 
                                       value="<?php echo (int)$card_check_cost; ?>" min="1" max="100" required>
                                <div class="form-text">Credits required for card checking</div>
                            </div>
                            <div class="col-md-4">
                                <label for="site_check_cost" class="form-label">Site Check Cost</label>
                                <input type="number" class="form-control" id="site_check_cost" 
                                       name="site_check_cost" 
                                       value="<?php echo (int)$site_check_cost; ?>" min="1" max="100" required>
                                <div class="form-text">Credits required for site checking</div>
                            </div>
                        </div>

                        <hr>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset to Defaults
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Configuration Display -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i> Current Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>System Status</h6>
                            <p><strong>Maintenance Mode:</strong> 
                                <?php echo $maintenance_mode ? '<span class="badge bg-warning">Enabled</span>' : '<span class="badge bg-success">Disabled</span>'; ?>
                            </p>
                            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                            <p><strong>Database:</strong> <?php echo DatabaseConfig::DATABASE_NAME; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Credit System</h6>
                            <p><strong>Daily Credits:</strong> <?php echo $daily_credit_amount; ?></p>
                            <p><strong>Card Check Cost:</strong> <?php echo $card_check_cost; ?></p>
                            <p><strong>Site Check Cost:</strong> <?php echo $site_check_cost; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
