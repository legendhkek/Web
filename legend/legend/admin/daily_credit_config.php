<?php
require_once 'admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creditConfig = [
        'daily_credit_amount' => (int)$_POST['daily_credit_amount'],
        'max_daily_claims' => (int)$_POST['max_daily_claims'],
        'credit_reset_hour' => (int)$_POST['credit_reset_hour'],
        'bonus_weekend_credits' => (int)$_POST['bonus_weekend_credits'],
        'new_user_bonus' => (int)$_POST['new_user_bonus']
    ];

    if (SiteConfig::save($creditConfig)) {
        $db->logAuditAction($_SESSION['user_id'], 'credit_config_updated', null, $creditConfig);
        $successMessage = 'Daily credit configuration updated successfully!';
    } else {
        $errorMessage = 'Error saving credit configuration.';
    }
}

// Load current settings
$daily_credit_amount = SiteConfig::get('daily_credit_amount', 10);
$max_daily_claims = SiteConfig::get('max_daily_claims', 1);
$credit_reset_hour = SiteConfig::get('credit_reset_hour', 0);
$bonus_weekend_credits = SiteConfig::get('bonus_weekend_credits', 0);
$new_user_bonus = SiteConfig::get('new_user_bonus', 50);

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Daily Credit Configuration</h5>
                    <p class="card-subtitle text-muted">Manage daily credit amounts and claim settings.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="daily_credit_amount" class="form-label">Daily Credit Amount</label>
                                    <input type="number" class="form-control" id="daily_credit_amount" name="daily_credit_amount" value="<?php echo $daily_credit_amount; ?>" min="1">
                                    <small class="text-muted">Credits given per daily claim</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_daily_claims" class="form-label">Max Daily Claims</label>
                                    <input type="number" class="form-control" id="max_daily_claims" name="max_daily_claims" value="<?php echo $max_daily_claims; ?>" min="1">
                                    <small class="text-muted">Maximum claims per user per day</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="credit_reset_hour" class="form-label">Daily Reset Hour (UTC)</label>
                                    <select class="form-select" id="credit_reset_hour" name="credit_reset_hour">
                                        <?php for ($i = 0; $i < 24; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i == $credit_reset_hour ? 'selected' : ''; ?>>
                                                <?php echo sprintf('%02d:00', $i); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <small class="text-muted">Hour when daily credits reset</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bonus_weekend_credits" class="form-label">Weekend Bonus Credits</label>
                                    <input type="number" class="form-control" id="bonus_weekend_credits" name="bonus_weekend_credits" value="<?php echo $bonus_weekend_credits; ?>" min="0">
                                    <small class="text-muted">Extra credits on weekends (0 to disable)</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_user_bonus" class="form-label">New User Bonus Credits</label>
                            <input type="number" class="form-control" id="new_user_bonus" name="new_user_bonus" value="<?php echo $new_user_bonus; ?>" min="0">
                            <small class="text-muted">Credits given to new users upon registration</small>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Credit Configuration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
