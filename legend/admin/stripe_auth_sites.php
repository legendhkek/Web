<?php
require_once '../config.php';
require_once 'admin_auth.php';

$nonce = setSecurityHeaders();
requireOwner(); // Only owner can manage sites

$configFile = __DIR__ . '/../data/stripe_auth_sites.json';

// Initialize config if file doesn't exist
if (!file_exists($configFile)) {
    $defaultConfig = [
        'sites' => [],
        'rotation_count' => 20,
        'current_index' => 0,
        'request_count' => 0
    ];
    $dir = dirname($configFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
}

// Load current configuration
$config = json_decode(file_get_contents($configFile), true);
if (!is_array($config)) {
    $config = [
        'sites' => [],
        'rotation_count' => 20,
        'current_index' => 0,
        'request_count' => 0
    ];
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_site') {
        $newSite = trim($_POST['site'] ?? '');
        if (!empty($newSite)) {
            // Remove www. prefix and ensure no http/https
            $newSite = str_replace(['http://', 'https://', 'www.'], '', $newSite);
            
            if (!in_array($newSite, $config['sites'])) {
                $config['sites'][] = $newSite;
                file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
                $message = "Site '$newSite' added successfully!";
                $messageType = 'success';
            } else {
                $message = "Site already exists!";
                $messageType = 'error';
            }
        }
    } elseif ($action === 'remove_site') {
        $siteIndex = (int)($_POST['index'] ?? -1);
        if ($siteIndex >= 0 && $siteIndex < count($config['sites'])) {
            $removedSite = $config['sites'][$siteIndex];
            array_splice($config['sites'], $siteIndex, 1);
            $config['sites'] = array_values($config['sites']); // Reindex
            
            // Reset rotation if current index is out of bounds
            if ($config['current_index'] >= count($config['sites'])) {
                $config['current_index'] = 0;
                $config['request_count'] = 0;
            }
            
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            $message = "Site '$removedSite' removed successfully!";
            $messageType = 'success';
        }
    } elseif ($action === 'update_rotation') {
        $rotationCount = (int)($_POST['rotation_count'] ?? 20);
        if ($rotationCount > 0 && $rotationCount <= 1000) {
            $config['rotation_count'] = $rotationCount;
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            $message = "Rotation count updated to $rotationCount!";
            $messageType = 'success';
        }
    } elseif ($action === 'reset_rotation') {
        $config['current_index'] = 0;
        $config['request_count'] = 0;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        $message = "Rotation reset to first site!";
        $messageType = 'success';
    } elseif ($action === 'bulk_add') {
        $bulkSites = $_POST['bulk_sites'] ?? '';
        $lines = array_filter(array_map('trim', explode("\n", $bulkSites)));
        $added = 0;
        
        foreach ($lines as $site) {
            // Remove www. prefix and ensure no http/https
            $site = str_replace(['http://', 'https://', 'www.'], '', $site);
            $site = trim($site, ' "\'');
            
            if (!empty($site) && !in_array($site, $config['sites'])) {
                $config['sites'][] = $site;
                $added++;
            }
        }
        
        if ($added > 0) {
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            $message = "$added site(s) added successfully!";
            $messageType = 'success';
        } else {
            $message = "No new sites to add!";
            $messageType = 'error';
        }
    }
    
    // Reload config after changes
    $config = json_decode(file_get_contents($configFile), true);
}

$totalSites = count($config['sites']);
$currentIndex = $config['current_index'] ?? 0;
$rotationCount = $config['rotation_count'] ?? 20;
$requestCount = $config['request_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Auth Sites Management - LEGEND CHECKER</title>
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
            padding-bottom: 2rem;
        }

        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-btn {
            color: #00d4ff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            color: #ffffff;
            transform: translateX(-5px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card h3 {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 2rem;
            font-weight: 700;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card h2 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        .form-group textarea {
            min-height: 150px;
            font-family: 'Courier New', monospace;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .sites-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            max-height: 600px;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        .site-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .site-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .site-item.current {
            border-color: #00d4ff;
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .site-name {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .btn-remove {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-remove:hover {
            background: rgba(239, 68, 68, 0.3);
        }

        .search-box {
            margin-bottom: 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(15, 15, 35, 0.95);
            padding: 1rem;
            border-radius: 10px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .sites-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="../stripe_auth_tool.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Stripe Auth Tool
            </a>
            <a href="admin_access.php" class="back-btn">
                <i class="fas fa-shield-alt"></i>
                Admin Panel
            </a>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-crown"></i> Stripe Auth Sites Management</h1>
            <p style="color: rgba(255,255,255,0.7);">Manage sites for automatic rotation</p>
        </div>

        <?php if (isset($message)): ?>
        <div class="alert <?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-globe"></i>
                <h3>Total Sites</h3>
                <p><?php echo $totalSites; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-sync-alt"></i>
                <h3>Current Site Index</h3>
                <p><?php echo $currentIndex + 1; ?> / <?php echo $totalSites; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3>Rotation Count</h3>
                <p><?php echo $rotationCount; ?> checks</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line"></i>
                <h3>Requests</h3>
                <p><?php echo $requestCount; ?> / <?php echo $rotationCount; ?></p>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add New Site</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_site">
                <div class="form-group">
                    <label>Site Domain</label>
                    <input type="text" name="site" placeholder="example.com" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Site
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-upload"></i> Bulk Add Sites</h2>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_add">
                <div class="form-group">
                    <label>Paste Sites (one per line)</label>
                    <textarea name="bulk_sites" placeholder="site1.com&#10;site2.com&#10;site3.com"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Bulk Add
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-cog"></i> Rotation Settings</h2>
            <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end;">
                <input type="hidden" name="action" value="update_rotation">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Requests per site before rotation</label>
                    <input type="number" name="rotation_count" value="<?php echo $rotationCount; ?>" min="1" max="1000" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update
                </button>
            </form>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="reset_rotation">
                <button type="submit" class="btn btn-warning" onclick="return confirm('Reset rotation to first site?')">
                    <i class="fas fa-redo"></i> Reset Rotation
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-list"></i> Sites List</h2>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search sites..." onkeyup="filterSites()">
            </div>
            <div class="sites-list" id="sitesList">
                <?php foreach ($config['sites'] as $index => $site): ?>
                <div class="site-item <?php echo $index === $currentIndex ? 'current' : ''; ?>" data-site="<?php echo htmlspecialchars($site); ?>">
                    <div>
                        <div class="site-name"><?php echo htmlspecialchars($site); ?></div>
                        <?php if ($index === $currentIndex): ?>
                        <small style="color: #00d4ff;">
                            <i class="fas fa-arrow-right"></i> Currently active
                        </small>
                        <?php endif; ?>
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="remove_site">
                        <input type="hidden" name="index" value="<?php echo $index; ?>">
                        <button type="submit" class="btn-remove" onclick="return confirm('Remove this site?')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        function filterSites() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const sitesList = document.getElementById('sitesList');
            const sites = sitesList.getElementsByClassName('site-item');
            
            for (let i = 0; i < sites.length; i++) {
                const siteName = sites[i].getAttribute('data-site').toLowerCase();
                if (siteName.indexOf(filter) > -1) {
                    sites[i].style.display = '';
                } else {
                    sites[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>
