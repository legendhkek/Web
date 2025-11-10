<?php
require_once 'admin_header.php';
require_once '../stripe_auth_manager.php';

if (!isOwner()) {
    header('Location: analytics.php?error=owner_required');
    exit;
}

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    try {
        if ($action === 'reset_defaults') {
            StripeAuthSiteManager::setSites(StripeAuthSiteManager::getDefaultSiteList());
            $successMessage = 'Stripe auth site list restored to defaults.';
        } elseif ($action === 'reset_rotation') {
            StripeAuthSiteManager::resetState();
            $successMessage = 'Stripe auth rotation counters reset.';
        } else {
            $sitesRaw = $_POST['sites'] ?? '';
            $sitesList = array_filter(array_map('trim', preg_split('/\r?\n/', $sitesRaw)));
            StripeAuthSiteManager::setSites($sitesList);

            $requestsPerSite = max(1, (int)($_POST['requests_per_site'] ?? 20));
            StripeAuthSiteManager::setRequestsPerSite($requestsPerSite);

            $creditCost = max(1, (int)($_POST['credit_cost'] ?? 1));
            StripeAuthSiteManager::setCreditCost($creditCost);

            $manualSite = trim($_POST['manual_site'] ?? '');
            StripeAuthSiteManager::setManualSite($manualSite !== '' ? $manualSite : null);

            $pythonPath = trim($_POST['python_path'] ?? 'python3');
            SiteConfig::save(['stripe_auth_python_path' => $pythonPath !== '' ? $pythonPath : 'python3']);

            $notifyLive = isset($_POST['notify_live']);
            $notifyDead = isset($_POST['notify_dead']);
            $notifyError = isset($_POST['notify_error']);
            StripeAuthSiteManager::setNotificationPreferences($notifyLive, $notifyDead, $notifyError);

            $successMessage = 'Stripe auth configuration updated successfully.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$sitesArray = StripeAuthSiteManager::getSites();
$sitesText = implode("\n", $sitesArray);
$siteCount = count($sitesArray);
$requestsPerSite = StripeAuthSiteManager::getRequestsPerSite();
$creditCost = StripeAuthSiteManager::getCreditCost();
$manualSite = StripeAuthSiteManager::getManualSite();
$pythonPath = SiteConfig::get('stripe_auth_python_path', 'python3');
$notificationPrefs = StripeAuthSiteManager::getNotificationPreferences();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-lightning"></i> Stripe Auth Configuration
                    </h1>
                    <p class="text-muted">Manage Stripe auth merchants, rotation, credit cost, and notifications.</p>
                </div>
                <a href="analytics.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Stripe Auth Sites</h5>
                        <small class="text-muted">Total sites configured: <?php echo $siteCount; ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="reset_defaults">
                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                    onclick="return confirm('Restore default Stripe auth sites?');">
                                <i class="bi bi-arrow-counterclockwise"></i> Load Defaults
                            </button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="reset_rotation">
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-arrow-repeat"></i> Reset Rotation
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="save">
                        <div class="mb-3">
                            <label for="sites" class="form-label">Stripe Merchants (one per line)</label>
                            <textarea id="sites" name="sites" class="form-control" rows="12"
                                      placeholder="example.com"><?php echo htmlspecialchars($sitesText); ?></textarea>
                            <div class="form-text">Domains only (protocol optional). The list rotates automatically based on the counter below.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="requests_per_site" class="form-label">Requests per Site</label>
                                    <input type="number" class="form-control" id="requests_per_site" name="requests_per_site"
                                           value="<?php echo (int)$requestsPerSite; ?>" min="1" max="100">
                                    <div class="form-text">How many checks to run before switching to the next site.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="credit_cost" class="form-label">Credit Cost per Check</label>
                                    <input type="number" class="form-control" id="credit_cost" name="credit_cost"
                                           value="<?php echo (int)$creditCost; ?>" min="1" max="50">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="python_path" class="form-label">Python Executable</label>
                                    <input type="text" class="form-control" id="python_path" name="python_path"
                                           value="<?php echo htmlspecialchars($pythonPath); ?>" placeholder="python3">
                                    <div class="form-text">Path to python binary used to run stripe_auth_checker.py</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="manual_site" class="form-label">Manual Site Override</label>
                            <input type="text" class="form-control" id="manual_site" name="manual_site"
                                   value="<?php echo htmlspecialchars($manualSite ?? ''); ?>" placeholder="leave blank to rotate">
                            <div class="form-text">Optional. When set, all checks will use this site until cleared.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Telegram Notifications</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notify_live" name="notify_live"
                                       <?php echo $notificationPrefs['live'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notify_live">
                                    Notify on live approvals
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notify_dead" name="notify_dead"
                                       <?php echo $notificationPrefs['dead'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notify_dead">
                                    Notify on declined results
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notify_error" name="notify_error"
                                       <?php echo $notificationPrefs['error'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notify_error">
                                    Notify on system errors
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Configuration
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-info-circle"></i> Current Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6>Total Sites</h6>
                                <p class="fs-4 mb-0"><?php echo $siteCount; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6>Requests per Site</h6>
                                <p class="fs-4 mb-0"><?php echo (int)$requestsPerSite; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <h6>Credit Cost</h6>
                                <p class="fs-4 mb-0"><?php echo (int)$creditCost; ?> credit(s)</p>
                            </div>
                        </div>
                    </div>
                    <?php if ($manualSite): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-circle"></i> Manual override is active. All checks use: <strong><?php echo htmlspecialchars($manualSite); ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-arrow-repeat"></i> Rotation enabled. Sites change every <?php echo (int)$requestsPerSite; ?> request(s).
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
