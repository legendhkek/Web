<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'stripe_auth_checker.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

// Update presence
$db->updatePresence($userId);

// Check if user is owner
$is_owner = in_array((int)$userId, AppConfig::OWNER_IDS);

// Cost per check
const STRIPE_AUTH_COST = 1;

// Load proxies for dropdown
$proxyFile = __DIR__ . '/data/proxies.json';
$proxyData = file_exists($proxyFile) ? json_decode(file_get_contents($proxyFile), true) : ['proxies' => []];
$liveProxies = array_filter($proxyData['proxies'] ?? [], fn($p) => $p['status'] === 'live');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'check_card') {
        // Check if user has enough credits
        if ($user['credits'] < STRIPE_AUTH_COST) {
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient credits. You need ' . STRIPE_AUTH_COST . ' credit to check.'
            ]);
            exit;
        }
        
        $ccString = $_POST['card'] ?? '';
        $useProxy = $_POST['use_proxy'] ?? 'no';
        $proxyId = $_POST['proxy_id'] ?? '';
        
        if (empty($ccString)) {
            echo json_encode([
                'success' => false,
                'message' => 'Card data is required'
            ]);
            exit;
        }
        
        // Get proxy if requested
        $proxy = null;
        if ($useProxy === 'random' && !empty($liveProxies)) {
            $randomProxy = $liveProxies[array_rand($liveProxies)];
            $proxy = $randomProxy['proxy'];
        } elseif ($useProxy === 'specific' && !empty($proxyId)) {
            foreach ($liveProxies as $p) {
                if ($p['id'] === $proxyId) {
                    $proxy = $p['proxy'];
                    break;
                }
            }
        }
        
        // Get next site from rotation
        $site = getNextSite();
        
        if (!$site) {
            echo json_encode([
                'success' => false,
                'message' => 'No sites available for checking'
            ]);
            exit;
        }
        
        // Deduct credits BEFORE checking
        if (!$db->deductCredits($userId, STRIPE_AUTH_COST)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to deduct credits. Please try again.'
            ]);
            exit;
        }
        
        try {
            // Perform the check
            $result = auth($site, $ccString, $proxy);
            
            // Log the check
            $db->logToolUsage($userId, 'stripe_auth_checker', [
                'usage_count' => 1,
                'credits_used' => STRIPE_AUTH_COST,
                'site' => $site,
                'card' => substr($ccString, 0, 6) . 'XXXXXX' . substr($ccString, -4),
                'status' => $result['status'] ?? 'unknown',
                'proxy_used' => $proxy ? 'yes' : 'no'
            ], STRIPE_AUTH_COST);
            
            // Update user stats
            $stats = $db->getUserStats($userId);
            if ($stats) {
                $db->updateUserStats($userId, [
                    'total_hits' => ($stats['total_hits'] ?? 0) + 1,
                    'total_charge_cards' => ($stats['total_charge_cards'] ?? 0) + ($result['success'] ? 1 : 0),
                    'total_live_cards' => ($stats['total_live_cards'] ?? 0) + ($result['success'] ? 1 : 0)
                ]);
            }
            
            // Add site info to result
            $result['site_used'] = $site;
            $result['credits_remaining'] = $user['credits'] - STRIPE_AUTH_COST;
            $result['proxy_used'] = $proxy ?: 'None';
            
            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            
        } catch (Exception $e) {
            // Refund credits on error
            $db->addCredits($userId, STRIPE_AUTH_COST);
            
            echo json_encode([
                'success' => false,
                'message' => 'Check failed: ' . $e->getMessage()
            ]);
        }
        exit;
    } elseif ($_POST['action'] === 'check_mass') {
        $cards = $_POST['cards'] ?? '';
        $useProxy = $_POST['use_proxy'] ?? 'no';
        
        if (empty($cards)) {
            echo json_encode([
                'success' => false,
                'message' => 'No cards provided'
            ]);
            exit;
        }
        
        $cardList = array_filter(array_map('trim', explode("\n", $cards)));
        $totalCards = count($cardList);
        $totalCreditsNeeded = $totalCards * STRIPE_AUTH_COST;
        
        // Check if user has enough credits
        if ($user['credits'] < $totalCreditsNeeded) {
            echo json_encode([
                'success' => false,
                'message' => "Insufficient credits. You need $totalCreditsNeeded credits to check $totalCards cards. You have {$user['credits']} credits."
            ]);
            exit;
        }
        
        // Deduct all credits upfront
        if (!$db->deductCredits($userId, $totalCreditsNeeded)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to deduct credits. Please try again.'
            ]);
            exit;
        }
        
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        try {
            foreach ($cardList as $index => $ccString) {
                // Get proxy if requested
                $proxy = null;
                if ($useProxy === 'random' && !empty($liveProxies)) {
                    $randomProxy = $liveProxies[array_rand($liveProxies)];
                    $proxy = $randomProxy['proxy'];
                }
                
                // Get next site
                $site = getNextSite();
                
                if (!$site) {
                    $results[] = [
                        'card' => substr($ccString, 0, 6) . 'XXXXXX' . substr($ccString, -4),
                        'status' => 'ERROR',
                        'message' => 'No sites available'
                    ];
                    $failCount++;
                    continue;
                }
                
                // Perform check
                try {
                    $result = auth($site, $ccString, $proxy);
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                    
                    $results[] = [
                        'card' => substr($ccString, 0, 6) . 'XXXXXX' . substr($ccString, -4),
                        'status' => $result['status'] ?? 'UNKNOWN',
                        'message' => $result['message'] ?? '',
                        'site_used' => $site,
                        'proxy_used' => $proxy ? 'yes' : 'no'
                    ];
                    
                    // Log the check
                    $db->logToolUsage($userId, 'stripe_auth_checker', [
                        'usage_count' => 1,
                        'credits_used' => STRIPE_AUTH_COST,
                        'site' => $site,
                        'card' => substr($ccString, 0, 6) . 'XXXXXX' . substr($ccString, -4),
                        'status' => $result['status'] ?? 'unknown',
                        'batch' => true
                    ], STRIPE_AUTH_COST);
                    
                } catch (Exception $e) {
                    $results[] = [
                        'card' => substr($ccString, 0, 6) . 'XXXXXX' . substr($ccString, -4),
                        'status' => 'ERROR',
                        'message' => $e->getMessage()
                    ];
                    $failCount++;
                }
                
                // Small delay between checks
                usleep(500000); // 0.5 seconds
            }
            
            // Update user stats
            $stats = $db->getUserStats($userId);
            if ($stats) {
                $db->updateUserStats($userId, [
                    'total_hits' => ($stats['total_hits'] ?? 0) + $totalCards,
                    'total_charge_cards' => ($stats['total_charge_cards'] ?? 0) + $successCount,
                    'total_live_cards' => ($stats['total_live_cards'] ?? 0) + $successCount
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Checked $totalCards cards. Success: $successCount, Failed: $failCount",
                'results' => $results,
                'stats' => [
                    'total' => $totalCards,
                    'success' => $successCount,
                    'failed' => $failCount
                ],
                'credits_remaining' => $user['credits'] - $totalCreditsNeeded
            ]);
            
        } catch (Exception $e) {
            // Refund remaining credits on catastrophic error
            $checkedCount = count($results);
            $refundAmount = ($totalCards - $checkedCount) * STRIPE_AUTH_COST;
            if ($refundAmount > 0) {
                $db->addCredits($userId, $refundAmount);
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Mass check failed: ' . $e->getMessage(),
                'results' => $results,
                'refunded' => $refundAmount
            ]);
        }
        exit;
    }
}

// Get site rotation function
function getNextSite() {
    $configFile = __DIR__ . '/data/stripe_auth_sites.json';
    
    if (!file_exists($configFile)) {
        return null;
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    $sites = $config['sites'] ?? [];
    
    if (empty($sites)) {
        return null;
    }
    
    $currentIndex = $config['current_index'] ?? 0;
    $requestCount = $config['request_count'] ?? 0;
    $rotationCount = $config['rotation_count'] ?? 20;
    
    // Get current site
    $site = $sites[$currentIndex];
    
    // Increment request count
    $requestCount++;
    
    // Check if we need to rotate to next site
    if ($requestCount >= $rotationCount) {
        $currentIndex = ($currentIndex + 1) % count($sites);
        $requestCount = 0;
    }
    
    // Save updated config
    $config['current_index'] = $currentIndex;
    $config['request_count'] = $requestCount;
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    
    return $site;
}

// Get current site info for display
$configFile = __DIR__ . '/data/stripe_auth_sites.json';
$siteConfig = json_decode(file_get_contents($configFile), true);
$totalSites = count($siteConfig['sites'] ?? []);
$currentSiteIndex = ($siteConfig['current_index'] ?? 0) + 1;
$requestsUntilRotation = ($siteConfig['rotation_count'] ?? 20) - ($siteConfig['request_count'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Auth Checker - LEGEND CHECKER</title>
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
            padding-bottom: 80px;
        }

        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
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

        .user-credits {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 212, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            border: 1px solid rgba(0, 212, 255, 0.3);
        }

        .container {
            max-width: 1200px;
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
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .page-title p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .info-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #00d4ff;
        }

        .info-card h3 {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
        }

        .info-card p {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .checker-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .tab-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
        }

        .tab-btn.active {
            color: #00d4ff;
            border-bottom-color: #00d4ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00d4ff;
            background: rgba(255, 255, 255, 0.15);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            font-family: 'Courier New', monospace;
        }

        .form-group select {
            cursor: pointer;
        }

        .proxy-section {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .proxy-options {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .proxy-option {
            flex: 1;
            min-width: 150px;
        }

        .proxy-option input[type="radio"] {
            width: auto;
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 1rem;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .results-container {
            display: none;
            margin-top: 2rem;
        }

        .result-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .result-item.success {
            border-color: rgba(16, 185, 129, 0.5);
            background: rgba(16, 185, 129, 0.1);
        }

        .result-item.error {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.1);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .result-status {
            font-weight: 700;
            font-size: 1.2rem;
        }

        .result-details {
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .result-details div {
            margin-bottom: 0.5rem;
        }

        .owner-section {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .owner-section h3 {
            color: #ffd700;
            margin-bottom: 1rem;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .progress-section {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            .info-cards {
                grid-template-columns: 1fr;
            }

            .proxy-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="tools.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Tools
            </a>
            <div class="user-credits">
                <i class="fas fa-coins"></i>
                <span id="userCredits"><?php echo number_format($user['credits']); ?></span> Credits
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-credit-card"></i> Stripe Auth Checker</h1>
            <p>Professional Stripe authentication testing with automatic site rotation</p>
        </div>

        <div class="info-cards">
            <div class="info-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>Cost Per Check</h3>
                <p><?php echo STRIPE_AUTH_COST; ?> Credit</p>
            </div>
            <div class="info-card">
                <i class="fas fa-globe"></i>
                <h3>Total Sites</h3>
                <p><?php echo $totalSites; ?> Sites</p>
            </div>
            <div class="info-card">
                <i class="fas fa-sync-alt"></i>
                <h3>Current Site</h3>
                <p><?php echo $currentSiteIndex; ?>/<?php echo $totalSites; ?></p>
            </div>
            <div class="info-card">
                <i class="fas fa-network-wired"></i>
                <h3>Available Proxies</h3>
                <p><?php echo count($liveProxies); ?> Live</p>
            </div>
        </div>

        <?php if ($is_owner): ?>
        <div class="owner-section">
            <h3><i class="fas fa-crown"></i> Owner Controls</h3>
            <p style="margin-bottom: 1rem;">Manage sites, proxies and configuration</p>
            <a href="admin/stripe_auth_sites.php" class="btn-secondary">
                <i class="fas fa-cog"></i> Manage Sites
            </a>
            <a href="proxy_manager.php" class="btn-secondary">
                <i class="fas fa-network-wired"></i> Manage Proxies
            </a>
        </div>
        <?php endif; ?>

        <div class="checker-card">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="single">
                    <i class="fas fa-credit-card"></i> Single Check
                </button>
                <button class="tab-btn" data-tab="mass">
                    <i class="fas fa-list"></i> Mass Check
                </button>
            </div>
            
            <div class="tab-content active" id="single">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-search"></i> Check Single Card</h2>
                
                <form id="checkForm">
                    <div class="form-group">
                        <label for="cardInput">
                            <i class="fas fa-credit-card"></i> Card Information
                            <span style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">(Format: XXXXXXXXXXXX|MM|YYYY|CVV)</span>
                        </label>
                        <input 
                            type="text" 
                            id="cardInput" 
                            name="card" 
                            placeholder="4532015112830366|12|2025|123"
                            required
                        >
                    </div>

                    <div class="proxy-section">
                        <h4 style="margin-bottom: 1rem;"><i class="fas fa-network-wired"></i> Proxy Settings</h4>
                        <div class="proxy-options">
                            <label class="proxy-option">
                                <input type="radio" name="use_proxy" value="no" checked>
                                <span>No Proxy</span>
                            </label>
                            <label class="proxy-option">
                                <input type="radio" name="use_proxy" value="random">
                                <span>Random Proxy</span>
                            </label>
                            <label class="proxy-option">
                                <input type="radio" name="use_proxy" value="specific">
                                <span>Specific Proxy</span>
                            </label>
                        </div>
                        <div class="form-group" id="specificProxyGroup" style="display:none;">
                            <label for="proxySelect">Select Proxy:</label>
                            <select id="proxySelect" name="proxy_id">
                                <option value="">-- Select a proxy --</option>
                                <?php foreach ($liveProxies as $proxy): ?>
                                <option value="<?php echo $proxy['id']; ?>">
                                    <?php echo $proxy['country']; ?> - <?php echo $proxy['ip']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" id="checkBtn">
                        <i class="fas fa-play"></i> Check Card (<?php echo STRIPE_AUTH_COST; ?> Credit)
                    </button>
                </form>
            </div>

            <div class="tab-content" id="mass">
                <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-list"></i> Mass Check Cards</h2>
                
                <form id="massCheckForm">
                    <div class="form-group">
                        <label for="cardsInput">
                            <i class="fas fa-list"></i> Card List
                            <span style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">(One card per line, Format: XXXXXXXXXXXX|MM|YYYY|CVV)</span>
                        </label>
                        <textarea 
                            id="cardsInput" 
                            name="cards" 
                            placeholder="4532015112830366|12|2025|123
4916338506082832|03|2026|456
4024007114754230|08|2027|789"
                            required
                        ></textarea>
                    </div>

                    <div class="proxy-section">
                        <h4 style="margin-bottom: 1rem;"><i class="fas fa-network-wired"></i> Proxy Settings</h4>
                        <div class="proxy-options">
                            <label class="proxy-option">
                                <input type="radio" name="use_proxy_mass" value="no" checked>
                                <span>No Proxy</span>
                            </label>
                            <label class="proxy-option">
                                <input type="radio" name="use_proxy_mass" value="random">
                                <span>Random Proxy per Check</span>
                            </label>
                        </div>
                        <p style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-top: 0.5rem;">
                            <i class="fas fa-info-circle"></i> Random proxy will rotate for each card check
                        </p>
                    </div>

                    <button type="submit" class="btn-primary" id="massCheckBtn">
                        <i class="fas fa-play"></i> Check All Cards
                    </button>
                </form>

                <div class="progress-section" id="progressSection">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Progress: <span id="progressText">0/0</span></span>
                        <span>Success: <span id="successCount">0</span> | Failed: <span id="failCount">0</span></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 0%;">0%</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-container" id="resultsContainer">
            <h2 style="margin-bottom: 1rem;"><i class="fas fa-chart-bar"></i> Results</h2>
            <div id="resultsContent"></div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        // Tab switching
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                document.getElementById(tab).classList.add('active');
            });
        });

        // Proxy option handling for single check
        const proxyRadios = document.querySelectorAll('input[name="use_proxy"]');
        const specificProxyGroup = document.getElementById('specificProxyGroup');

        proxyRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'specific') {
                    specificProxyGroup.style.display = 'block';
                } else {
                    specificProxyGroup.style.display = 'none';
                }
            });
        });

        const checkForm = document.getElementById('checkForm');
        const checkBtn = document.getElementById('checkBtn');
        const resultsContainer = document.getElementById('resultsContainer');
        const resultsContent = document.getElementById('resultsContent');
        const userCreditsEl = document.getElementById('userCredits');

        // Single check
        checkForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(checkForm);
            formData.append('action', 'check_card');
            
            checkBtn.disabled = true;
            checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            try {
                const response = await fetch('stripe_auth_tool.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayResult(data.result);
                    if (data.result.credits_remaining !== undefined) {
                        userCreditsEl.textContent = data.result.credits_remaining.toLocaleString();
                    }
                } else {
                    showError(data.message || 'Check failed');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                checkBtn.disabled = false;
                checkBtn.innerHTML = '<i class="fas fa-play"></i> Check Card (<?php echo STRIPE_AUTH_COST; ?> Credit)';
            }
        });

        // Mass check
        const massCheckForm = document.getElementById('massCheckForm');
        const massCheckBtn = document.getElementById('massCheckBtn');
        const progressSection = document.getElementById('progressSection');
        const progressText = document.getElementById('progressText');
        const progressFill = document.getElementById('progressFill');
        const successCount = document.getElementById('successCount');
        const failCount = document.getElementById('failCount');

        massCheckForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(massCheckForm);
            formData.append('action', 'check_mass');
            formData.set('use_proxy', formData.get('use_proxy_mass'));
            
            massCheckBtn.disabled = true;
            massCheckBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            progressSection.style.display = 'block';
            
            try {
                const response = await fetch('stripe_auth_tool.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update progress
                    const total = data.stats.total;
                    const success = data.stats.success;
                    const failed = data.stats.failed;
                    
                    progressText.textContent = `${total}/${total}`;
                    progressFill.style.width = '100%';
                    progressFill.textContent = '100%';
                    successCount.textContent = success;
                    failCount.textContent = failed;
                    
                    // Display results
                    data.results.forEach(result => {
                        displayMassResult(result);
                    });
                    
                    // Update credits
                    if (data.credits_remaining !== undefined) {
                        userCreditsEl.textContent = data.credits_remaining.toLocaleString();
                    }
                    
                    showSuccess(data.message);
                } else {
                    showError(data.message || 'Mass check failed');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                massCheckBtn.disabled = false;
                massCheckBtn.innerHTML = '<i class="fas fa-play"></i> Check All Cards';
            }
        });

        function displayResult(result) {
            const isSuccess = result.success === true;
            const resultDiv = document.createElement('div');
            resultDiv.className = 'result-item ' + (isSuccess ? 'success' : 'error');
            
            let detailsHTML = '';
            if (result.site_used) {
                detailsHTML += `<div><strong>Site:</strong> ${result.site_used}</div>`;
            }
            if (result.account_email) {
                detailsHTML += `<div><strong>Account Email:</strong> ${result.account_email}</div>`;
            }
            if (result.pm_id) {
                detailsHTML += `<div><strong>Payment Method ID:</strong> ${result.pm_id}</div>`;
            }
            if (result.proxy_used) {
                detailsHTML += `<div><strong>Proxy:</strong> ${result.proxy_used}</div>`;
            }
            if (result.message) {
                detailsHTML += `<div><strong>Message:</strong> ${result.message}</div>`;
            }
            
            resultDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-status">
                        <i class="fas fa-${isSuccess ? 'check-circle' : 'times-circle'}"></i>
                        ${result.status || 'UNKNOWN'}
                    </div>
                    <div style="color: rgba(255,255,255,0.6);">
                        ${new Date().toLocaleTimeString()}
                    </div>
                </div>
                <div class="result-details">
                    ${detailsHTML}
                </div>
            `;
            
            resultsContent.insertBefore(resultDiv, resultsContent.firstChild);
            resultsContainer.style.display = 'block';
        }

        function displayMassResult(result) {
            const isSuccess = result.status === 'SUCCESS';
            const resultDiv = document.createElement('div');
            resultDiv.className = 'result-item ' + (isSuccess ? 'success' : 'error');
            
            let detailsHTML = '';
            detailsHTML += `<div><strong>Card:</strong> ${result.card}</div>`;
            if (result.site_used) {
                detailsHTML += `<div><strong>Site:</strong> ${result.site_used}</div>`;
            }
            if (result.proxy_used) {
                detailsHTML += `<div><strong>Proxy:</strong> ${result.proxy_used}</div>`;
            }
            if (result.message) {
                detailsHTML += `<div><strong>Message:</strong> ${result.message}</div>`;
            }
            
            resultDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-status">
                        <i class="fas fa-${isSuccess ? 'check-circle' : 'times-circle'}"></i>
                        ${result.status}
                    </div>
                    <div style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">
                        ${new Date().toLocaleTimeString()}
                    </div>
                </div>
                <div class="result-details">
                    ${detailsHTML}
                </div>
            `;
            
            resultsContent.insertBefore(resultDiv, resultsContent.firstChild);
            resultsContainer.style.display = 'block';
        }

        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'result-item error';
            errorDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-status">
                        <i class="fas fa-exclamation-circle"></i>
                        ERROR
                    </div>
                    <div style="color: rgba(255,255,255,0.6);">
                        ${new Date().toLocaleTimeString()}
                    </div>
                </div>
                <div class="result-details">
                    <div>${message}</div>
                </div>
            `;
            
            resultsContent.insertBefore(errorDiv, resultsContent.firstChild);
            resultsContainer.style.display = 'block';
        }

        function showSuccess(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'result-item success';
            successDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-status">
                        <i class="fas fa-check-circle"></i>
                        COMPLETED
                    </div>
                    <div style="color: rgba(255,255,255,0.6);">
                        ${new Date().toLocaleTimeString()}
                    </div>
                </div>
                <div class="result-details">
                    <div>${message}</div>
                </div>
            `;
            
            resultsContent.insertBefore(successDiv, resultsContent.firstChild);
            resultsContainer.style.display = 'block';
        }

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
