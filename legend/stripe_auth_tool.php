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
        $proxy = $_POST['proxy'] ?? null;
        
        if (empty($ccString)) {
            echo json_encode([
                'success' => false,
                'message' => 'Card data is required'
            ]);
            exit;
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
                'card' => maskCardForDisplay($ccString),
                'status' => $result['status'] ?? 'unknown'
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
            $freshUser = $db->getUserByTelegramId($userId);
            $result['site_used'] = $site;
            $result['credits_remaining'] = $freshUser['credits'] ?? ($user['credits'] - STRIPE_AUTH_COST);
            $result['card_masked'] = maskCardForDisplay($ccString);
            if (isset($result['logs']) && is_array($result['logs'])) {
                $result['logs'] = array_slice($result['logs'], -6);
            }
            
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
    } elseif ($_POST['action'] === 'mass_check') {
        $cardsRaw = $_POST['cards'] ?? '';
        $proxy = $_POST['proxy'] ?? null;
        $cards = preg_split('/\r\n|\r|\n/', $cardsRaw);
        $cards = array_values(array_filter(array_map('trim', $cards)));

        if (empty($cards)) {
            echo json_encode([
                'success' => false,
                'message' => 'Provide at least one card for mass check.'
            ]);
            exit;
        }

        $maxBatch = 25;
        $truncated = false;
        if (count($cards) > $maxBatch) {
            $cards = array_slice($cards, 0, $maxBatch);
            $truncated = true;
        }

        $currentUser = $db->getUserByTelegramId($userId);
        $availableCredits = $currentUser['credits'] ?? 0;
        $results = [];
        $processed = 0;
        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;
        $creditsSpent = 0;

        $stats = $db->getUserStats($userId);
        $totalHits = $stats['total_hits'] ?? 0;
        $totalCharge = $stats['total_charge_cards'] ?? 0;
        $totalLive = $stats['total_live_cards'] ?? 0;

        $seenCards = [];

        foreach ($cards as $cardLine) {
            if (isset($seenCards[$cardLine])) {
                $skippedCount++;
                $results[] = [
                    'success' => false,
                    'status' => 'SKIPPED',
                    'message' => 'Duplicate card detected, skipping.',
                    'card_masked' => maskCardForDisplay($cardLine),
                    'credits_spent' => 0
                ];
                continue;
            }
            $seenCards[$cardLine] = true;

            $maskedCard = maskCardForDisplay($cardLine);

            if ($availableCredits < STRIPE_AUTH_COST) {
                $skippedCount++;
                $results[] = [
                    'success' => false,
                    'status' => 'SKIPPED',
                    'message' => 'Insufficient credits, skipping.',
                    'card_masked' => $maskedCard,
                    'credits_spent' => 0
                ];
                continue;
            }

            if (!$db->deductCredits($userId, STRIPE_AUTH_COST)) {
                $skippedCount++;
                $results[] = [
                    'success' => false,
                    'status' => 'SKIPPED',
                    'message' => 'Failed to deduct credits, skipping.',
                    'card_masked' => $maskedCard,
                    'credits_spent' => 0
                ];
                continue;
            }

            $availableCredits -= STRIPE_AUTH_COST;
            $creditsSpent += STRIPE_AUTH_COST;

            $site = getNextSite();
            if (!$site) {
                $db->addCredits($userId, STRIPE_AUTH_COST);
                $availableCredits += STRIPE_AUTH_COST;
                $creditsSpent -= STRIPE_AUTH_COST;
                $failureCount++;
                $results[] = [
                    'success' => false,
                    'status' => 'ERROR',
                    'message' => 'No sites available for checking',
                    'card_masked' => $maskedCard,
                    'credits_spent' => 0
                ];
                continue;
            }

            try {
                $result = auth($site, $cardLine, $proxy);
                $result['site_used'] = $site;
                $result['card_masked'] = $maskedCard;
                $result['credits_spent'] = STRIPE_AUTH_COST;
                if (isset($result['logs']) && is_array($result['logs'])) {
                    $result['logs'] = array_slice($result['logs'], -6);
                }

                $db->logToolUsage($userId, 'stripe_auth_checker', [
                    'usage_count' => 1,
                    'credits_used' => STRIPE_AUTH_COST,
                    'site' => $site,
                    'card' => $maskedCard,
                    'status' => $result['status'] ?? 'unknown'
                ], STRIPE_AUTH_COST);

                $processed++;
                if (!empty($result['success'])) {
                    $successCount++;
                    $totalCharge++;
                    $totalLive++;
                } else {
                    $failureCount++;
                }
                $totalHits++;
                $results[] = $result;
            } catch (Exception $e) {
                $db->addCredits($userId, STRIPE_AUTH_COST);
                $availableCredits += STRIPE_AUTH_COST;
                $creditsSpent -= STRIPE_AUTH_COST;
                $failureCount++;
                $results[] = [
                    'success' => false,
                    'status' => 'ERROR',
                    'message' => 'Check failed: ' . $e->getMessage(),
                    'card_masked' => $maskedCard,
                    'credits_spent' => 0
                ];
            }
        }

        if ($stats) {
            $db->updateUserStats($userId, [
                'total_hits' => $totalHits,
                'total_charge_cards' => $totalCharge,
                'total_live_cards' => $totalLive
            ]);
        }

        $summary = [
            'attempted' => count($cards),
            'processed' => $processed,
            'success' => $successCount,
            'failed' => $failureCount,
            'skipped' => $skippedCount,
            'credits_spent' => $creditsSpent,
            'truncated' => $truncated
        ];

        $freshUser = $db->getUserByTelegramId($userId);

        echo json_encode([
            'success' => true,
            'results' => $results,
            'summary' => $summary,
            'credits_remaining' => $freshUser['credits'] ?? $availableCredits
        ]);
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

function maskCardForDisplay(string $cardLine): string {
    $parts = explode('|', $cardLine);
    $number = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
    if (strlen($number) >= 6) {
        $maskedCore = str_repeat('X', max(strlen($number) - 10, 0));
        $masked = substr($number, 0, 6) . $maskedCore . substr($number, -4);
    } else {
        $masked = $number;
    }
    $parts[0] = $masked;
    return implode('|', $parts);
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
        .form-group textarea {
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
        .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
            background: rgba(255, 255, 255, 0.15);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            font-family: 'Courier New', monospace;
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

        .result-item.warning {
            border-color: rgba(251, 191, 36, 0.4);
            background: rgba(251, 191, 36, 0.12);
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
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
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
                <i class="fas fa-clock"></i>
                <h3>Next Rotation</h3>
                <p><?php echo $requestsUntilRotation; ?> checks</p>
            </div>
        </div>

        <?php if ($is_owner): ?>
        <div class="owner-section">
            <h3><i class="fas fa-crown"></i> Owner Controls</h3>
            <p style="margin-bottom: 1rem;">Manage sites and configuration</p>
            <a href="admin/stripe_auth_sites.php" class="btn-secondary">
                <i class="fas fa-cog"></i> Manage Sites
            </a>
            <button onclick="resetRotation()" class="btn-secondary">
                <i class="fas fa-redo"></i> Reset Rotation
            </button>
        </div>
        <?php endif; ?>

        <div class="checker-card">
            <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-search"></i> Check Card</h2>
            
                <form id="checkForm">
                    <div class="form-group">
                        <label for="cardInput">
                            <i class="fas fa-credit-card"></i> Card Information
                            <span style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">(Format: XXXXXXXXXXXXXXXX|MM|YYYY|CVV, one per line for mass check)</span>
                        </label>
                        <textarea 
                            id="cardInput" 
                            name="cards" 
                            placeholder="4532015112830366|12|2025|123&#10;4485548481235456|09|2026|321"
                            required
                        ></textarea>
                    </div>

                <div class="form-group">
                    <label for="proxyInput">
                        <i class="fas fa-server"></i> Proxy (Optional)
                        <span style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">(Format: ip:port:user:pass)</span>
                    </label>
                    <input 
                        type="text" 
                        id="proxyInput" 
                        name="proxy" 
                        placeholder="proxy.example.com:8080:username:password"
                    >
                </div>

                    <button type="submit" class="btn-primary" id="checkBtn">
                        <i class="fas fa-play"></i> Check Card(s) (<?php echo STRIPE_AUTH_COST; ?> Credit each)
                    </button>
            </form>
        </div>

        <div class="results-container" id="resultsContainer">
            <h2 style="margin-bottom: 1rem;"><i class="fas fa-chart-bar"></i> Results</h2>
            <div id="resultsContent"></div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const checkForm = document.getElementById('checkForm');
        const checkBtn = document.getElementById('checkBtn');
        const resultsContainer = document.getElementById('resultsContainer');
        const resultsContent = document.getElementById('resultsContent');
        const userCreditsEl = document.getElementById('userCredits');
        const cardInputEl = document.getElementById('cardInput');
        const proxyInputEl = document.getElementById('proxyInput');

        checkForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const rawCards = cardInputEl.value.trim();
            if (!rawCards) {
                showError('Card data is required');
                return;
            }

            const cards = rawCards
                .split(/\r?\n/)
                .map(card => card.trim())
                .filter(card => card.length > 0);

            if (cards.length === 0) {
                showError('Card data is required');
                return;
            }

            const isMass = cards.length > 1;
            const formData = new FormData();
            formData.append('action', isMass ? 'mass_check' : 'check_card');
            formData.append(isMass ? 'cards' : 'card', isMass ? cards.join('\n') : cards[0]);

            const proxy = proxyInputEl.value.trim();
            if (proxy) {
                formData.append('proxy', proxy);
            }

            checkBtn.disabled = true;
            checkBtn.innerHTML = isMass
                ? `<i class="fas fa-spinner fa-spin"></i> Checking ${cards.length} cards...`
                : '<i class="fas fa-spinner fa-spin"></i> Checking...';

            try {
                const response = await fetch('stripe_auth_tool.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    if (isMass) {
                        displayMassResults(data.results || [], data.summary || null);
                    } else {
                        displayResult(data.result);
                    }

                    if (data.credits_remaining !== undefined) {
                        userCreditsEl.textContent = Number(data.credits_remaining).toLocaleString();
                    }
                } else {
                    showError(data.message || 'Check failed');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                checkBtn.disabled = false;
                checkBtn.innerHTML = '<i class="fas fa-play"></i> Check Card(s) (<?php echo STRIPE_AUTH_COST; ?> Credit each)';
            }
        });

        function displayResult(result) {
            const statusLabel = (result.status || 'UNKNOWN').toUpperCase();
            const isSuccess = result.success === true;
            let className = 'result-item ';
            if (statusLabel === 'SKIPPED') {
                className += 'warning';
            } else {
                className += isSuccess ? 'success' : 'error';
            }

            const resultDiv = document.createElement('div');
            resultDiv.className = className;

            let detailsHTML = '';
            if (result.card_masked) {
                detailsHTML += `<div><strong>Card:</strong> ${result.card_masked}</div>`;
            }
            if (result.site_used) {
                detailsHTML += `<div><strong>Site:</strong> ${result.site_used}</div>`;
            }
            if (result.account_email) {
                detailsHTML += `<div><strong>Account Email:</strong> ${result.account_email}</div>`;
            }
            if (result.pm_id) {
                detailsHTML += `<div><strong>Payment Method ID:</strong> ${result.pm_id}</div>`;
            }
            if (result.message) {
                detailsHTML += `<div><strong>Message:</strong> ${result.message}</div>`;
            }
            if (typeof result.credits_spent !== 'undefined') {
                detailsHTML += `<div><strong>Credits Used:</strong> ${result.credits_spent}</div>`;
            }

            resultDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-status">
                        <i class="fas fa-${isSuccess ? 'check-circle' : (statusLabel === 'SKIPPED' ? 'pause-circle' : 'times-circle')}"></i>
                        ${statusLabel}
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

        function displayMassResults(results, summary) {
            if (Array.isArray(results)) {
                for (let i = results.length - 1; i >= 0; i--) {
                    displayResult(results[i]);
                }
            }
            if (summary) {
                displaySummary(summary);
            }
        }

        function displaySummary(summary) {
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'result-item';

            const parts = [
                `<strong>Processed:</strong> ${summary.processed}/${summary.attempted}`,
                `<strong>Live:</strong> ${summary.success}`,
                `<strong>Failed:</strong> ${summary.failed}`,
                `<strong>Skipped:</strong> ${summary.skipped}`,
                `<strong>Credits Spent:</strong> ${summary.credits_spent}`
            ];

            if (summary.truncated) {
                parts.push('<em>Batch truncated to 25 cards.</em>');
            }

            summaryDiv.innerHTML = `
                <div class="result-header">
                    <div class="result-status">
                        <i class="fas fa-list"></i>
                        MASS SUMMARY
                    </div>
                    <div style="color: rgba(255,255,255,0.6);">
                        ${new Date().toLocaleTimeString()}
                    </div>
                </div>
                <div class="result-details">
                    <div>${parts.join('<br>')}</div>
                </div>
            `;

            resultsContent.insertBefore(summaryDiv, resultsContent.firstChild);
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

        function resetRotation() {
            if (confirm('Reset site rotation to start from the first site?')) {
                // This would require an AJAX endpoint - implement if needed
                alert('Please use the Manage Sites page to reset rotation');
            }
        }

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
