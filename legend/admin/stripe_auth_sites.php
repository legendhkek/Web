<?php
session_start();
require_once '../config.php';
require_once '../database.php';

// Check if user is admin/owner
$db = Database::getInstance();
$userId = $_SESSION['telegram_user_id'] ?? null;

if (!$userId) {
    header('Location: ../login.php');
    exit;
}

$user = $db->getUserByTelegramId($userId);
$isOwner = in_array($userId, AppConfig::OWNER_IDS);
$isAdmin = $db->isAdmin($userId);

if (!$isOwner && !$isAdmin) {
    die('Access denied. Owner/Admin only.');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_site') {
        $domain = trim($_POST['domain'] ?? '');
        if (!empty($domain)) {
            // Remove http:// or https:// if present
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');
            
            $result = $db->addStripeAuthSite($domain, $userId);
            $message = $result ? 'Site added successfully!' : 'Site already exists or failed to add.';
        } else {
            $message = 'Domain cannot be empty.';
        }
    } elseif ($action === 'remove_site') {
        $domain = $_POST['domain'] ?? '';
        if (!empty($domain)) {
            $db->removeStripeAuthSite($domain);
            $message = 'Site removed successfully!';
        }
    } elseif ($action === 'toggle_status') {
        $domain = $_POST['domain'] ?? '';
        $active = isset($_POST['active']) && $_POST['active'] === '1';
        if (!empty($domain)) {
            $db->updateStripeAuthSiteStatus($domain, $active);
            $message = 'Site status updated!';
        }
    } elseif ($action === 'bulk_add') {
        $sites = $_POST['sites'] ?? '';
        $sitesList = array_filter(array_map('trim', explode("\n", $sites)));
        $added = 0;
        $failed = 0;
        
        foreach ($sitesList as $site) {
            // Remove http:// or https:// if present
            $site = preg_replace('#^https?://#', '', $site);
            $site = rtrim($site, '/');
            
            if (!empty($site)) {
                $result = $db->addStripeAuthSite($site, $userId);
                if ($result) {
                    $added++;
                } else {
                    $failed++;
                }
            }
        }
        
        $message = "Bulk add completed: {$added} added, {$failed} failed/duplicates.";
    }
}

// Get all sites
$allSites = $db->getAllStripeAuthSites();
$activeSites = $db->getActiveStripeAuthSites();
$totalSites = count($allSites);
$activeSitesCount = count($activeSites);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Auth Sites Management - LEGEND ADMIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-card {
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #00d4ff;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #00d4ff;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        textarea {
            min-height: 150px;
            font-family: monospace;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff4444, #cc0000);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .message {
            padding: 1rem;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 10px;
            margin-bottom: 1.5rem;
            color: #00ff88;
        }

        .sites-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .sites-table th {
            background: rgba(0, 212, 255, 0.1);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(0, 212, 255, 0.3);
        }

        .sites-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sites-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
        }

        .status-inactive {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #00d4ff;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Admin Panel</a>
        
        <div class="header">
            <h1><i class="fas fa-stripe-s"></i> Stripe Auth Sites Management</h1>
            <p>Manage Stripe authentication testing sites with automatic rotation</p>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalSites; ?></div>
                    <div class="stat-label">Total Sites</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $activeSitesCount; ?></div>
                    <div class="stat-label">Active Sites</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalSites - $activeSitesCount; ?></div>
                    <div class="stat-label">Inactive Sites</div>
                </div>
            </div>
        </div>

        <?php if (isset($message)): ?>
        <div class="message">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2><i class="fas fa-plus"></i> Add Single Site</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_site">
                <div class="form-group">
                    <label>Domain (without http://)</label>
                    <input type="text" name="domain" placeholder="example.com" required>
                </div>
                <button type="submit" class="btn">Add Site</button>
            </form>
        </div>

        <div class="section">
            <h2><i class="fas fa-layer-group"></i> Bulk Add Sites</h2>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_add">
                <div class="form-group">
                    <label>Sites (one per line, without http://)</label>
                    <textarea name="sites" placeholder="site1.com&#10;site2.com&#10;site3.com"></textarea>
                </div>
                <button type="submit" class="btn">Bulk Add Sites</button>
            </form>
        </div>

        <div class="section">
            <h2><i class="fas fa-list"></i> All Sites</h2>
            <table class="sites-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Requests</th>
                        <th>Last Used</th>
                        <th>Added By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSites as $site): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($site['domain']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $site['active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $site['active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo $site['request_count'] ?? 0; ?>/20</td>
                        <td><?php echo $site['last_used'] ? date('Y-m-d H:i', $site['last_used']->toDateTime()->getTimestamp()) : 'Never'; ?></td>
                        <td><?php echo $site['added_by'] ?? 'Unknown'; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($site['domain']); ?>">
                                <input type="hidden" name="active" value="<?php echo $site['active'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-small">
                                    <?php echo $site['active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_site">
                                <input type="hidden" name="domain" value="<?php echo htmlspecialchars($site['domain']); ?>">
                                <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Remove this site?')">
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($allSites)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.5);">
                            No sites added yet. Add sites using the forms above.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
