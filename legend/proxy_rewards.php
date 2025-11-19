<?php
/**
 * Proxy Rewards System
 * Earn credits/keys by finding working proxies
 */

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

// Initialize secure session
initSecureSession();

// Check authentication
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
$user = $db->getUserByTelegramId($telegram_id);

// Reward configuration
$REWARDS = [
    'proxy_verified' => 5,      // Credits for verified working proxy
    'proxy_contributed' => 10,  // Credits for contributing new proxy
    'bulk_verified' => 50,      // Bonus for 10+ verified proxies
    'key_unlock' => 100         // Credits to unlock premium key
];

// Handle proxy testing
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_proxy') {
        $proxy = trim($_POST['proxy'] ?? '');
        
        if (!empty($proxy)) {
            $result = testProxy($proxy);
            
            if ($result['success']) {
                // Reward user for finding working proxy
                $reward = $REWARDS['proxy_verified'];
                $db->addCredits($telegram_id, $reward);
                
                // Log the contribution
                logProxyContribution($telegram_id, $proxy, 'working');
                
                $message = "✅ Proxy verified! You earned {$reward} credits!";
                $message_type = 'success';
            } else {
                $message = "❌ Proxy failed: " . $result['error'];
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'fetch_proxies') {
        $count = intval($_POST['count'] ?? 10);
        $proxies = fetchProxiesFromAPI($count);
        
        if (!empty($proxies)) {
            $_SESSION['fetched_proxies'] = $proxies;
            $message = "✅ Fetched {$count} proxies! Test them to earn credits.";
            $message_type = 'info';
        } else {
            $message = "❌ Failed to fetch proxies. Try again later.";
            $message_type = 'danger';
        }
    } elseif ($action === 'claim_key') {
        $user_credits = $user['credits'] ?? 0;
        
        if ($user_credits >= $REWARDS['key_unlock']) {
            // Generate premium key
            $key = generatePremiumKey($telegram_id);
            $db->deductCredits($telegram_id, $REWARDS['key_unlock']);
            
            $_SESSION['generated_key'] = $key;
            $message = "🎉 Premium key generated! Check below.";
            $message_type = 'success';
        } else {
            $needed = $REWARDS['key_unlock'] - $user_credits;
            $message = "❌ Need {$needed} more credits to unlock premium key.";
            $message_type = 'warning';
        }
    }
}

// Get user stats
$stats = getUserProxyStats($telegram_id);
$fetched_proxies = $_SESSION['fetched_proxies'] ?? [];

/**
 * Test proxy functionality
 */
function testProxy($proxy) {
    $test_url = 'http://legend.sonugamingop.tech/autosh.php';
    $timeout = 50;
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 400 && empty($error)) {
            return ['success' => true, 'proxy' => $proxy, 'code' => $http_code];
        } else {
            return ['success' => false, 'error' => $error ?: "HTTP {$http_code}"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fetch proxies from external API
 */
function fetchProxiesFromAPI($count = 10) {
    $api_url = 'http://legend.sonugamingop.tech/fetch_proxies.php?count=' . $count;
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            return $data['proxies'] ?? explode("\n", trim($response));
        }
    } catch (Exception $e) {
        error_log("Proxy fetch error: " . $e->getMessage());
    }
    
    return [];
}

/**
 * Log proxy contribution
 */
function logProxyContribution($user_id, $proxy, $status) {
    $log_file = __DIR__ . '/data/proxy_contributions.json';
    $logs = [];
    
    if (file_exists($log_file)) {
        $logs = json_decode(file_get_contents($log_file), true) ?? [];
    }
    
    $logs[] = [
        'user_id' => $user_id,
        'proxy' => $proxy,
        'status' => $status,
        'timestamp' => time(),
        'date' => date('Y-m-d H:i:s')
    ];
    
    // Keep last 1000 logs
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }
    
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }
    
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
}

/**
 * Get user proxy statistics
 */
function getUserProxyStats($user_id) {
    $log_file = __DIR__ . '/data/proxy_contributions.json';
    
    if (!file_exists($log_file)) {
        return ['total' => 0, 'working' => 0, 'failed' => 0];
    }
    
    $logs = json_decode(file_get_contents($log_file), true) ?? [];
    $user_logs = array_filter($logs, function($log) use ($user_id) {
        return $log['user_id'] == $user_id;
    });
    
    $working = count(array_filter($user_logs, function($log) {
        return $log['status'] === 'working';
    }));
    
    return [
        'total' => count($user_logs),
        'working' => $working,
        'failed' => count($user_logs) - $working
    ];
}

/**
 * Generate premium key
 */
function generatePremiumKey($user_id) {
    $key = 'PREMIUM-' . strtoupper(bin2hex(random_bytes(8)));
    
    $key_data = [
        'key' => $key,
        'user_id' => $user_id,
        'type' => 'premium',
        'benefits' => [
            'unlimited_checks' => true,
            'priority_support' => true,
            'advanced_features' => true,
            'api_access' => true
        ],
        'created_at' => time(),
        'expires_at' => time() + (90 * 24 * 60 * 60), // 90 days
        'status' => 'active'
    ];
    
    $keys_file = __DIR__ . '/data/premium_keys.json';
    $keys = [];
    
    if (file_exists($keys_file)) {
        $keys = json_decode(file_get_contents($keys_file), true) ?? [];
    }
    
    $keys[] = $key_data;
    
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0755, true);
    }
    
    file_put_contents($keys_file, json_encode($keys, JSON_PRETTY_PRINT));
    
    return $key_data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Rewards - LEGEND CHECKER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00e676;
            --secondary-color: #00bcd4;
            --danger-color: #ff073a;
            --dark-bg: #0f0f23;
            --card-bg: #1a2b49;
            --card-hover: #223041;
            --text-light: #00ffea;
            --success-glow: rgba(0, 230, 118, 0.3);
            --danger-glow: rgba(255, 7, 58, 0.3);
        }

        body {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background: var(--card-bg);
            border-bottom: 2px solid var(--secondary-color);
            box-shadow: 0 4px 20px rgba(0, 188, 212, 0.2);
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: bold;
            text-shadow: 0 0 10px var(--success-glow);
        }

        .card-custom {
            background: var(--card-bg);
            border: 1px solid var(--secondary-color);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.15);
            transition: all 0.3s ease;
            margin-bottom: 25px;
        }

        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 230, 118, 0.25);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--card-hover), var(--card-bg));
            border-bottom: 2px solid var(--secondary-color);
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }

        .card-title-custom {
            color: var(--text-light);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--card-bg), var(--card-hover));
            border: 1px solid var(--primary-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px var(--success-glow);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-shadow: 0 0 10px var(--success-glow);
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control-custom {
            background: var(--card-hover);
            border: 1px solid var(--secondary-color);
            color: var(--text-light);
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            background: var(--card-hover);
            border-color: var(--primary-color);
            box-shadow: 0 0 15px var(--success-glow);
            color: var(--text-light);
        }

        .form-control-custom::placeholder {
            color: rgba(224, 224, 224, 0.5);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), #69f0ae);
            border: none;
            color: #000;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px var(--success-glow);
            background: linear-gradient(135deg, #69f0ae, var(--primary-color));
        }

        .btn-secondary-custom {
            background: linear-gradient(135deg, var(--secondary-color), #4dd0e1);
            border: none;
            color: #000;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-secondary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 188, 212, 0.4);
        }

        .btn-danger-custom {
            background: linear-gradient(135deg, var(--danger-color), #c0392b);
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-danger-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px var(--danger-glow);
        }

        .proxy-item {
            background: var(--card-hover);
            border: 1px solid var(--secondary-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .proxy-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 3px 15px var(--success-glow);
        }

        .proxy-text {
            font-family: 'Courier New', monospace;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .reward-badge {
            background: linear-gradient(135deg, var(--primary-color), #69f0ae);
            color: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .key-display {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            padding: 20px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 5px 25px rgba(255, 215, 0, 0.4);
            margin: 20px 0;
        }

        .alert-custom {
            border-radius: 10px;
            border: none;
            animation: slideInDown 0.5s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
            
            .card-custom {
                margin-bottom: 20px;
            }

            .btn-primary-custom, .btn-secondary-custom {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check"></i> LEGEND CHECKER
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="bi bi-house"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="proxy_rewards.php"><i class="bi bi-trophy"></i> Proxy Rewards</a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link"><i class="bi bi-coin"></i> <?php echo number_format($user['credits'] ?? 0); ?> Credits</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show alert-custom" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="text-center mb-4">
            <h1 style="color: var(--primary-color); text-shadow: 0 0 20px var(--success-glow);">
                <i class="bi bi-trophy-fill"></i> Proxy Rewards System
            </h1>
            <p style="color: var(--text-light); font-size: 1.1rem;">
                Find working proxies, earn credits, unlock premium keys!
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($user['credits'] ?? 0); ?></div>
                    <div class="stat-label">Your Credits</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['working']; ?></div>
                    <div class="stat-label">Working Proxies</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Tested</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total'] > 0 ? round(($stats['working'] / $stats['total']) * 100) : 0; ?>%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Fetch Proxies -->
            <div class="col-md-6">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">
                            <i class="bi bi-cloud-download"></i> Fetch Proxies
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <p style="color: var(--text-light);">
                            Get fresh proxies from our API and test them to earn rewards!
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="fetch_proxies">
                            
                            <div class="mb-3">
                                <label class="form-label" style="color: var(--text-light);">Number of Proxies</label>
                                <input type="number" name="count" class="form-control form-control-custom" value="10" min="1" max="50">
                                <small style="color: rgba(224, 224, 224, 0.6);">Fetch 1-50 proxies at once</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary-custom w-100">
                                <i class="bi bi-download"></i> Fetch Proxies
                            </button>
                        </form>
                        
                        <div class="mt-3 p-3" style="background: var(--card-hover); border-radius: 8px;">
                            <strong style="color: var(--primary-color);"><i class="bi bi-info-circle"></i> Rewards:</strong>
                            <ul style="color: var(--text-light); margin-top: 10px; margin-bottom: 0;">
                                <li>+<?php echo $REWARDS['proxy_verified']; ?> credits per working proxy</li>
                                <li>+<?php echo $REWARDS['bulk_verified']; ?> bonus for 10+ verified</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Proxy -->
            <div class="col-md-6">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5 class="card-title-custom">
                            <i class="bi bi-speedometer2"></i> Test Proxy
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <p style="color: var(--text-light);">
                            Test any proxy to verify it's working and earn credits!
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="test_proxy">
                            
                            <div class="mb-3">
                                <label class="form-label" style="color: var(--text-light);">Proxy (IP:PORT)</label>
                                <input type="text" name="proxy" class="form-control form-control-custom" 
                                       placeholder="123.456.789.0:8080" required>
                                <small style="color: rgba(224, 224, 224, 0.6);">Format: IP:PORT or HOST:PORT</small>
                            </div>
                            
                            <button type="submit" class="btn btn-secondary-custom w-100">
                                <i class="bi bi-play-circle"></i> Test Proxy
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fetched Proxies List -->
        <?php if (!empty($fetched_proxies)): ?>
        <div class="card-custom mt-4">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-list-ul"></i> Fetched Proxies (<?php echo count($fetched_proxies); ?>)
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info alert-custom">
                    <i class="bi bi-lightbulb"></i> Click "Test" on each proxy to verify and earn <strong><?php echo $REWARDS['proxy_verified']; ?> credits</strong> per working proxy!
                </div>
                
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($fetched_proxies as $index => $proxy): ?>
                    <div class="proxy-item">
                        <span class="proxy-text"><?php echo htmlspecialchars($proxy); ?></span>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="test_proxy">
                            <input type="hidden" name="proxy" value="<?php echo htmlspecialchars($proxy); ?>">
                            <button type="submit" class="btn btn-sm btn-secondary-custom">
                                <i class="bi bi-check-circle"></i> Test
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Premium Key Section -->
        <div class="card-custom mt-4">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-key-fill"></i> Premium Key Unlock
                </h5>
            </div>
            <div class="card-body p-4">
                <p style="color: var(--text-light);">
                    Unlock premium features by spending your earned credits!
                </p>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color);">Premium Benefits:</h6>
                        <ul style="color: var(--text-light);">
                            <li>Unlimited card checks</li>
                            <li>Priority support</li>
                            <li>Advanced features</li>
                            <li>API access</li>
                            <li>90 days validity</li>
                        </ul>
                    </div>
                    <div class="col-md-6 text-center">
                        <div class="mb-3">
                            <span class="reward-badge" style="font-size: 1.5rem;">
                                <?php echo $REWARDS['key_unlock']; ?> Credits
                            </span>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="claim_key">
                            <button type="submit" class="btn btn-primary-custom" 
                                    <?php echo ($user['credits'] ?? 0) < $REWARDS['key_unlock'] ? 'disabled' : ''; ?>>
                                <i class="bi bi-unlock"></i> Unlock Premium Key
                            </button>
                        </form>
                        
                        <?php if (($user['credits'] ?? 0) < $REWARDS['key_unlock']): ?>
                        <small class="d-block mt-2" style="color: rgba(224, 224, 224, 0.6);">
                            Need <?php echo $REWARDS['key_unlock'] - ($user['credits'] ?? 0); ?> more credits
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['generated_key'])): ?>
                <div class="key-display mt-4">
                    <i class="bi bi-key-fill"></i> <?php echo $_SESSION['generated_key']['key']; ?>
                    <div style="margin-top: 10px; font-size: 0.9rem;">
                        Valid until: <?php echo date('Y-m-d', $_SESSION['generated_key']['expires_at']); ?>
                    </div>
                </div>
                <?php unset($_SESSION['generated_key']); endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
