<?php
require_once 'admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_sites') {
            // Add new sites
            $new_sites = isset($_POST['sites']) ? trim($_POST['sites']) : '';
            if (!empty($new_sites)) {
                $sites_array = array_map('trim', explode("\n", $new_sites));
                $sites_array = array_filter($sites_array, function($site) {
                    return !empty($site) && filter_var($site, FILTER_VALIDATE_DOMAIN) !== false;
                });
                
                $existing_sites = SiteConfig::get('stripe_auth_sites', []);
                $updated_sites = array_unique(array_merge($existing_sites, $sites_array));
                
                SiteConfig::save(['stripe_auth_sites' => array_values($updated_sites)]);
                $db->logAuditAction($_SESSION['user_id'], 'stripe_auth_sites_added', null, ['count' => count($sites_array)]);
                $successMessage = 'Sites added successfully!';
            }
        } elseif ($_POST['action'] === 'remove_site') {
            // Remove a site
            $site_to_remove = isset($_POST['site']) ? trim($_POST['site']) : '';
            if (!empty($site_to_remove)) {
                $existing_sites = SiteConfig::get('stripe_auth_sites', []);
                $updated_sites = array_values(array_filter($existing_sites, function($site) use ($site_to_remove) {
                    return $site !== $site_to_remove;
                }));
                
                SiteConfig::save(['stripe_auth_sites' => $updated_sites]);
                $db->logAuditAction($_SESSION['user_id'], 'stripe_auth_site_removed', null, ['site' => $site_to_remove]);
                $successMessage = 'Site removed successfully!';
            }
        } elseif ($_POST['action'] === 'update_rotation') {
            // Update rotation settings
            $rotation_counter = isset($_POST['rotation_counter']) ? (int)$_POST['rotation_counter'] : 0;
            $current_site_index = isset($_POST['current_site_index']) ? (int)$_POST['current_site_index'] : 0;
            
            SiteConfig::save([
                'stripe_auth_rotation_counter' => $rotation_counter,
                'stripe_auth_current_site_index' => $current_site_index
            ]);
            $db->logAuditAction($_SESSION['user_id'], 'stripe_auth_rotation_updated', null, [
                'rotation_counter' => $rotation_counter,
                'current_site_index' => $current_site_index
            ]);
            $successMessage = 'Rotation settings updated successfully!';
        } elseif ($_POST['action'] === 'clear_all') {
            // Clear all sites
            SiteConfig::save(['stripe_auth_sites' => []]);
            $db->logAuditAction($_SESSION['user_id'], 'stripe_auth_sites_cleared', null, []);
            $successMessage = 'All sites cleared successfully!';
        }
    }
}

// Load current sites
$stripe_sites = SiteConfig::get('stripe_auth_sites', []);
$rotation_counter = SiteConfig::get('stripe_auth_rotation_counter', 0);
$current_site_index = SiteConfig::get('stripe_auth_current_site_index', 0);
$current_site = !empty($stripe_sites) && isset($stripe_sites[$current_site_index]) ? $stripe_sites[$current_site_index] : 'None';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Stripe Auth Sites Management</h5>
                    <p class="card-subtitle text-muted">Manage Stripe Auth sites and rotation settings.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>Add Sites</h6>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add_sites">
                                <div class="mb-3">
                                    <label for="sites" class="form-label">Sites (one per line, domain only)</label>
                                    <textarea class="form-control" id="sites" name="sites" rows="10" placeholder="alternativesentiments.co.uk&#10;alphaomegastores.com&#10;attitudedanceleeds.co.uk"></textarea>
                                    <small class="form-text text-muted">Enter domain names only (without http:// or https://)</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Add Sites</button>
                            </form>
                        </div>

                        <div class="col-md-6">
                            <h6>Current Sites (<?php echo count($stripe_sites); ?>)</h6>
                            <div class="mb-3">
                                <strong>Current Active Site:</strong> 
                                <span class="badge bg-success"><?php echo htmlspecialchars($current_site); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Rotation Counter:</strong> <?php echo $rotation_counter; ?> / 20
                            </div>
                            <div class="mb-3">
                                <strong>Current Site Index:</strong> <?php echo $current_site_index; ?>
                            </div>
                            
                            <?php if (!empty($stripe_sites)): ?>
                                <div class="list-group mb-3" style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($stripe_sites as $index => $site): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <?php if ($index === $current_site_index): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($site); ?>
                                            </span>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this site?');">
                                                <input type="hidden" name="action" value="remove_site">
                                                <input type="hidden" name="site" value="<?php echo htmlspecialchars($site); ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No sites configured yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>

                    <h6>Rotation Settings</h6>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="update_rotation">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rotation_counter" class="form-label">Rotation Counter</label>
                                    <input type="number" class="form-control" id="rotation_counter" name="rotation_counter" value="<?php echo $rotation_counter; ?>" min="0" max="20">
                                    <small class="form-text text-muted">Sites rotate every 20 requests</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_site_index" class="form-label">Current Site Index</label>
                                    <input type="number" class="form-control" id="current_site_index" name="current_site_index" value="<?php echo $current_site_index; ?>" min="0" max="<?php echo max(0, count($stripe_sites) - 1); ?>">
                                    <small class="form-text text-muted">Index of currently active site (0-based)</small>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Rotation Settings</button>
                    </form>

                    <?php if (!empty($stripe_sites)): ?>
                        <hr>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to clear ALL sites? This cannot be undone!');">
                            <input type="hidden" name="action" value="clear_all">
                            <button type="submit" class="btn btn-danger">Clear All Sites</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
