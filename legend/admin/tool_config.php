<?php
require_once 'admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toolConfig = [
        'checker_api_url' => $_POST['checker_api_url'],
        'max_concurrent_checks' => (int)$_POST['max_concurrent_checks'],
        'card_check_timeout' => (int)$_POST['card_check_timeout'],
        'site_check_timeout' => (int)$_POST['site_check_timeout'],
        'rate_limit_per_minute' => (int)$_POST['rate_limit_per_minute'],
        'enable_card_checker' => isset($_POST['enable_card_checker']),
        'enable_site_checker' => isset($_POST['enable_site_checker']),
        'proxy_enabled' => isset($_POST['proxy_enabled']),
        'proxy_list' => $_POST['proxy_list']
    ];

    if (SiteConfig::save($toolConfig)) {
        $db->logAuditAction($_SESSION['user_id'], 'tool_config_updated', null, $toolConfig);
        $successMessage = 'Tool configuration updated successfully!';
    } else {
        $errorMessage = 'Error saving tool configuration.';
    }
}

// Load current settings
$checker_api_url = SiteConfig::get('checker_api_url', AppConfig::CHECKER_API_URL);
$max_concurrent_checks = SiteConfig::get('max_concurrent_checks', AppConfig::MAX_CONCURRENT_CHECKS);
$card_check_timeout = SiteConfig::get('card_check_timeout', 30);
$site_check_timeout = SiteConfig::get('site_check_timeout', 15);
$rate_limit_per_minute = SiteConfig::get('rate_limit_per_minute', 60);
$enable_card_checker = SiteConfig::get('enable_card_checker', true);
$enable_site_checker = SiteConfig::get('enable_site_checker', true);
$proxy_enabled = SiteConfig::get('proxy_enabled', false);
$proxy_list = SiteConfig::get('proxy_list', '');

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Tool Configuration</h5>
                    <p class="card-subtitle text-muted">Configure checker tools, APIs, and rate limits.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <h6>API Configuration</h6>
                        <div class="mb-3">
                            <label for="checker_api_url" class="form-label">Checker API URL</label>
                            <input type="url" class="form-control" id="checker_api_url" name="checker_api_url" value="<?php echo htmlspecialchars($checker_api_url); ?>">
                        </div>

                        <h6>Performance Settings</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_concurrent_checks" class="form-label">Max Concurrent Checks</label>
                                    <input type="number" class="form-control" id="max_concurrent_checks" name="max_concurrent_checks" value="<?php echo $max_concurrent_checks; ?>" min="1" max="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rate_limit_per_minute" class="form-label">Rate Limit (per minute)</label>
                                    <input type="number" class="form-control" id="rate_limit_per_minute" name="rate_limit_per_minute" value="<?php echo $rate_limit_per_minute; ?>" min="1">
                                </div>
                            </div>
                        </div>

                        <h6>Timeout Settings</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="card_check_timeout" class="form-label">Card Check Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="card_check_timeout" name="card_check_timeout" value="<?php echo $card_check_timeout; ?>" min="5" max="120">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site_check_timeout" class="form-label">Site Check Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="site_check_timeout" name="site_check_timeout" value="<?php echo $site_check_timeout; ?>" min="5" max="60">
                                </div>
                            </div>
                        </div>

                        <h6>Tool Availability</h6>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enable_card_checker" name="enable_card_checker" <?php echo $enable_card_checker ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_card_checker">Enable Card Checker</label>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enable_site_checker" name="enable_site_checker" <?php echo $enable_site_checker ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_site_checker">Enable Site Checker</label>
                        </div>

                        <h6>Proxy Configuration</h6>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="proxy_enabled" name="proxy_enabled" <?php echo $proxy_enabled ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="proxy_enabled">Enable Proxy Rotation</label>
                        </div>
                        <div class="mb-3">
                            <label for="proxy_list" class="form-label">Proxy List (one per line)</label>
                            <textarea class="form-control" id="proxy_list" name="proxy_list" rows="5" placeholder="http://proxy1:port&#10;http://proxy2:port"><?php echo htmlspecialchars($proxy_list); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Tool Configuration</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
