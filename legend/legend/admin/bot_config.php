<?php
require_once 'admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $botConfig = [
        'welcome_message' => $_POST['welcome_message'],
        'daily_credit_message' => $_POST['daily_credit_message'],
        'insufficient_credits_message' => $_POST['insufficient_credits_message'],
        'banned_user_message' => $_POST['banned_user_message'],
        'maintenance_message' => $_POST['maintenance_message'],
        'help_message' => $_POST['help_message']
    ];

    if (SiteConfig::save($botConfig)) {
        $successMessage = 'Bot configuration updated successfully!';
    } else {
        $errorMessage = 'Error saving bot configuration.';
    }
}

// Load current bot messages
$welcome_message = SiteConfig::get('welcome_message', '🎉 Welcome to LEGEND CHECKER! Your account has been created successfully.');
$daily_credit_message = SiteConfig::get('daily_credit_message', '💰 You have claimed your daily credits! Amount: {amount}');
$insufficient_credits_message = SiteConfig::get('insufficient_credits_message', '❌ Insufficient credits. You need {required} credits for this action.');
$banned_user_message = SiteConfig::get('banned_user_message', '🚫 Your account has been suspended. Please contact support.');
$maintenance_message = SiteConfig::get('maintenance_message', '🔧 System is under maintenance. Please try again later.');
$help_message = SiteConfig::get('help_message', '📖 Available commands:\n/start - Get started\n/credits - Check your credits\n/help - Show this help');

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Bot Configuration</h5>
                    <p class="card-subtitle text-muted">Customize bot messages and responses.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="welcome_message" class="form-label">Welcome Message</label>
                            <textarea class="form-control" id="welcome_message" name="welcome_message" rows="3"><?php echo htmlspecialchars($welcome_message); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="daily_credit_message" class="form-label">Daily Credit Claim Message</label>
                            <textarea class="form-control" id="daily_credit_message" name="daily_credit_message" rows="2"><?php echo htmlspecialchars($daily_credit_message); ?></textarea>
                            <small class="text-muted">Use {amount} for credit amount placeholder</small>
                        </div>

                        <div class="mb-3">
                            <label for="insufficient_credits_message" class="form-label">Insufficient Credits Message</label>
                            <textarea class="form-control" id="insufficient_credits_message" name="insufficient_credits_message" rows="2"><?php echo htmlspecialchars($insufficient_credits_message); ?></textarea>
                            <small class="text-muted">Use {required} for required credits placeholder</small>
                        </div>

                        <div class="mb-3">
                            <label for="banned_user_message" class="form-label">Banned User Message</label>
                            <textarea class="form-control" id="banned_user_message" name="banned_user_message" rows="2"><?php echo htmlspecialchars($banned_user_message); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="maintenance_message" class="form-label">Maintenance Mode Message</label>
                            <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="2"><?php echo htmlspecialchars($maintenance_message); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="help_message" class="form-label">Help Message</label>
                            <textarea class="form-control" id="help_message" name="help_message" rows="4"><?php echo htmlspecialchars($help_message); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Bot Configuration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
