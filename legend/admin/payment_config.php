<?php
require_once 'admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentConfig = [
        'stripe_public_key' => $_POST['stripe_public_key'],
        'stripe_secret_key' => $_POST['stripe_secret_key'],
        'paypal_client_id' => $_POST['paypal_client_id'],
        'paypal_secret' => $_POST['paypal_secret'],
        'premium_price' => (float)$_POST['premium_price'],
        'vip_price' => (float)$_POST['vip_price'],
        'xcoin_rate' => (float)$_POST['xcoin_rate']
    ];

    if (SiteConfig::save($paymentConfig)) {
        $db->logAuditAction($_SESSION['user_id'], 'payment_config_updated', null, $paymentConfig);
        $successMessage = 'Payment configuration updated successfully!';
    } else {
        $errorMessage = 'Error saving payment configuration.';
    }
}

// Load current settings
$stripe_public_key = SiteConfig::get('stripe_public_key', '');
$stripe_secret_key = SiteConfig::get('stripe_secret_key', '');
$paypal_client_id = SiteConfig::get('paypal_client_id', '');
$paypal_secret = SiteConfig::get('paypal_secret', '');
$premium_price = SiteConfig::get('premium_price', 9.99);
$vip_price = SiteConfig::get('vip_price', 19.99);
$xcoin_rate = SiteConfig::get('xcoin_rate', 0.01);

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Payment Configuration</h5>
                    <p class="card-subtitle text-muted">Configure payment gateways and pricing.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <h6>Stripe Configuration</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="stripe_public_key" class="form-label">Stripe Public Key</label>
                                <input type="text" class="form-control" id="stripe_public_key" name="stripe_public_key" value="<?php echo htmlspecialchars($stripe_public_key); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="stripe_secret_key" class="form-label">Stripe Secret Key</label>
                                <input type="password" class="form-control" id="stripe_secret_key" name="stripe_secret_key" value="<?php echo htmlspecialchars($stripe_secret_key); ?>">
                            </div>
                        </div>

                        <h6>PayPal Configuration</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="paypal_client_id" class="form-label">PayPal Client ID</label>
                                <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id" value="<?php echo htmlspecialchars($paypal_client_id); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="paypal_secret" class="form-label">PayPal Secret</label>
                                <input type="password" class="form-control" id="paypal_secret" name="paypal_secret" value="<?php echo htmlspecialchars($paypal_secret); ?>">
                            </div>
                        </div>

                        <h6>Pricing Configuration</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="premium_price" class="form-label">Premium Price ($)</label>
                                <input type="number" step="0.01" class="form-control" id="premium_price" name="premium_price" value="<?php echo $premium_price; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="vip_price" class="form-label">VIP Price ($)</label>
                                <input type="number" step="0.01" class="form-control" id="vip_price" name="vip_price" value="<?php echo $vip_price; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="xcoin_rate" class="form-label">XCoin Rate ($ per coin)</label>
                                <input type="number" step="0.001" class="form-control" id="xcoin_rate" name="xcoin_rate" value="<?php echo $xcoin_rate; ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Payment Configuration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
