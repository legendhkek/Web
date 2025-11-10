<?php
require_once 'admin_header.php';
require_once '../stripe_auth_sites.php';

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_site':
                $newSite = trim($_POST['new_site'] ?? '');
                if ($newSite === '') {
                    throw new InvalidArgumentException('Please provide a site URL to add.');
                }
                StripeAuthSites::addSite($newSite, $current_user['telegram_id'] ?? null, $current_user['username'] ?? null);
                $successMessage = 'Site added successfully.';
                break;

            case 'bulk_add':
                $bulkSites = trim($_POST['bulk_sites'] ?? '');
                if ($bulkSites === '') {
                    throw new InvalidArgumentException('Please paste one or more site URLs.');
                }
                $lines = preg_split('/\r\n|\r|\n/', $bulkSites);
                $added = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    try {
                        StripeAuthSites::addSite($line, $current_user['telegram_id'] ?? null, $current_user['username'] ?? null);
                        $added++;
                    } catch (InvalidArgumentException $e) {
                        // Skip duplicates silently
                    }
                }
                $successMessage = $added > 0
                    ? "Successfully added {$added} site(s)."
                    : 'No new sites were added (duplicates were skipped).';
                break;

            case 'update_limit':
                $limit = (int) ($_POST['per_site_limit'] ?? 20);
                StripeAuthSites::setPerSiteLimit($limit);
                $successMessage = 'Per-site rotation limit updated.';
                break;

            case 'reset_rotation':
                StripeAuthSites::resetRotation(0);
                $successMessage = 'Rotation counter reset to the first active site.';
                break;

            case 'toggle_site':
                $siteUrl = $_POST['site_url'] ?? '';
                $newStatus = ($_POST['new_status'] ?? '') === '1';
                if ($siteUrl === '') {
                    throw new InvalidArgumentException('Invalid site URL provided.');
                }
                StripeAuthSites::setSiteActive($siteUrl, $newStatus);
                $successMessage = 'Site status updated.';
                break;

            case 'remove_site':
                $siteUrl = $_POST['site_url'] ?? '';
                if ($siteUrl === '') {
                    throw new InvalidArgumentException('Invalid site URL provided.');
                }
                $removed = StripeAuthSites::removeSite($siteUrl);
                $successMessage = $removed ? 'Site removed from rotation.' : 'Site not found.';
                break;

            case 'set_current':
                $siteIndex = (int) ($_POST['site_index'] ?? -1);
                if ($siteIndex < 0) {
                    throw new InvalidArgumentException('Invalid site index specified.');
                }
                StripeAuthSites::resetRotation($siteIndex);
                $successMessage = 'Rotation now starts from the selected site.';
                break;

            default:
                throw new InvalidArgumentException('Unknown action requested.');
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$sites = StripeAuthSites::getAllSites();
$rotation = StripeAuthSites::getRotationState();
$activeCount = count(array_filter($sites, fn($site) => !empty($site['active'])));
$totalCount = count($sites);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Stripe Auth Site Rotation</h2>
                    <p class="text-muted mb-0">Manage the pool of Stripe-enabled stores and rotation behaviour.</p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Rotation Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div><strong>Active Sites:</strong> <?php echo number_format($activeCount); ?> / <?php echo number_format($totalCount); ?></div>
                        <div><strong>Current Index:</strong> <?php echo number_format($rotation['current_index'] ?? 0); ?></div>
                        <div><strong>Usage on Current Site:</strong> <?php echo number_format($rotation['current_usage'] ?? 0); ?></div>
                        <div><strong>Limit per Site:</strong> <?php echo number_format($rotation['per_site_limit'] ?? 20); ?></div>
                    </div>
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="update_limit">
                        <div class="mb-3">
                            <label for="per_site_limit" class="form-label">Requests per Site</label>
                            <input type="number" min="1" class="form-control" id="per_site_limit" name="per_site_limit" value="<?php echo htmlspecialchars($rotation['per_site_limit'] ?? 20); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Update Limit</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Reset rotation to the first active site?');">
                        <input type="hidden" name="action" value="reset_rotation">
                        <button type="submit" class="btn btn-warning w-100">Reset Rotation</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add Single Site</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_site">
                        <div class="mb-3">
                            <label for="new_site" class="form-label">Site URL</label>
                            <input type="text" class="form-control" id="new_site" name="new_site" placeholder="https://example.com">
                            <div class="form-text">Protocol is optional; HTTPS will be enforced automatically.</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Add Site</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Bulk Add Sites</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="bulk_add">
                        <div class="mb-3">
                            <label for="bulk_sites" class="form-label">One URL per line</label>
                            <textarea class="form-control" id="bulk_sites" name="bulk_sites" rows="6" placeholder="example.com&#10;shop.example.net"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">Add Multiple Sites</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Managed Sites</h5>
                    <span class="badge bg-primary"><?php echo number_format($totalCount); ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>URL</th>
                                    <th>Active</th>
                                    <th>Added By</th>
                                    <th>Added At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sites)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No sites configured yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($sites as $index => $site): ?>
                                    <?php
                                        $isActive = !empty($site['active']);
                                        $addedBy = $site['added_by'] ?? 'system';
                                        if (is_numeric($addedBy)) {
                                            $addedBy = 'User ID ' . $addedBy;
                                        }
                                        if (!empty($site['added_by_username'])) {
                                            $addedBy .= ' (@' . $site['added_by_username'] . ')';
                                        }
                                        $addedAt = $site['added_at'] ?? null;
                                        if ($addedAt) {
                                            $addedAtFormatted = date('M d, Y H:i', strtotime($addedAt));
                                        } else {
                                            $addedAtFormatted = '—';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" rel="noopener">
                                                <?php echo htmlspecialchars($site['url']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($addedBy); ?></td>
                                        <td><?php echo htmlspecialchars($addedAtFormatted); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <?php if ($isActive): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="set_current">
                                                        <input type="hidden" name="site_index" value="<?php echo $index; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Make Current</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_site">
                                                    <input type="hidden" name="site_url" value="<?php echo htmlspecialchars($site['url']); ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $isActive ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $isActive ? 'btn-warning' : 'btn-success'; ?>">
                                                        <?php echo $isActive ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this site from rotation?');">
                                                    <input type="hidden" name="action" value="remove_site">
                                                    <input type="hidden" name="site_url" value="<?php echo htmlspecialchars($site['url']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
