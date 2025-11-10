<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'stripe_auth_checker.php';
require_once 'bin_lookup.php';
require_once 'stripe_site_manager.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);
$isOwner = in_array($userId, AppConfig::OWNER_IDS);

// Update presence
$db->updatePresence($userId);

// Handle site management (Owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!$isOwner) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $action = $_POST['action'];
    
    if ($action === 'add_site') {
        $site = $_POST['site'] ?? '';
        $success = StripeSiteManager::addSite($site);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Site added successfully' : 'Site already exists or invalid',
            'total_sites' => StripeSiteManager::getSiteCount()
        ]);
        exit;
    }
    
    if ($action === 'remove_site') {
        $site = $_POST['site'] ?? '';
        $success = StripeSiteManager::removeSite($site);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Site removed successfully' : 'Site not found',
            'total_sites' => StripeSiteManager::getSiteCount()
        ]);
        exit;
    }
    
    if ($action === 'update_rotation') {
        $count = $_POST['count'] ?? 20;
        $success = StripeSiteManager::updateRotationCount($count);
        echo json_encode([
            'success' => $success,
            'message' => 'Rotation count updated'
        ]);
        exit;
    }
    
    if ($action === 'get_sites') {
        echo json_encode([
            'success' => true,
            'sites' => StripeSiteManager::getSites(),
            'rotation_count' => StripeSiteManager::getRotationCount()
        ]);
        exit;
    }
}

// Handle multi-check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['multi_check'])) {
    header('Content-Type: application/json');
    
    $cards = $_POST['cards'] ?? '';
    $proxy = $_POST['proxy'] ?? null;
    
    if (empty($cards)) {
        echo json_encode(['success' => false, 'error' => 'No cards provided']);
        exit;
    }
    
    // Parse cards
    $cardList = array_filter(array_map('trim', explode("\n", $cards)));
    $totalCards = count($cardList);
    
    // Check credits (owners bypass)
    if (!$isOwner && $user['credits'] < $totalCards) {
        echo json_encode([
            'success' => false,
            'error' => "Insufficient credits. You need {$totalCards} credits but have {$user['credits']}"
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Multi-check started',
        'total_cards' => $totalCards,
        'session_id' => uniqid('check_')
    ]);
    exit;
}

// Handle single check from multi-checker
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_card'])) {
    header('Content-Type: application/json');
    
    $cc = $_GET['cc'] ?? '';
    $checkNumber = (int)($_GET['check_num'] ?? 0);
    $proxy = $_GET['proxy'] ?? null;
    
    // Get site based on rotation
    $site = StripeSiteManager::getNextSite($checkNumber);
    
    if (!$site) {
        echo json_encode([
            'success' => false,
            'error' => 'No sites available'
        ]);
        exit;
    }
    
    // Check credits (owners bypass)
    if (!$isOwner) {
        if ($user['credits'] < 1) {
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient credits',
                'credits_exhausted' => true
            ]);
            exit;
        }
        
        // Deduct credit
        if (!$db->deductCredits($userId, 1)) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to deduct credits'
            ]);
            exit;
        }
    }
    
    try {
        // Check card
        $checker = new StripeAuthChecker('https://' . $site, $proxy);
        $result = $checker->checkCard($cc);
        
        // Get BIN info
        $binInfo = BINLookup::getCardInfoFromCC($cc);
        
        // Log tool usage
        $db->logToolUsage($userId, 'stripe_auth_checker', [
            'usage_count' => 1,
            'credits_used' => $isOwner ? 0 : 1,
            'result' => $result['status']
        ]);
        
        // Get updated credits
        $freshUser = $db->getUserByTelegramId($userId);
        
        echo json_encode([
            'success' => true,
            'result' => $result,
            'bin_info' => $binInfo,
            'site_used' => $site,
            'remaining_credits' => $isOwner ? 999999 : ($freshUser['credits'] ?? 0)
        ]);
    } catch (Exception $e) {
        // Refund credits on error
        if (!$isOwner) {
            $db->addCredits($userId, 1);
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Check failed: ' . $e->getMessage()
        ]);
    }
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Multi Checker - LEGEND CHECKER</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
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

        .header-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .credits-display {
            background: rgba(0, 212, 255, 0.1);
            padding: 10px 20px;
            border-radius: 25px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .owner-badge {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
        }

        textarea {
            min-height: 200px;
            resize: vertical;
            font-family: 'Courier New', monospace;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff073a, #c0392b);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .results-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        .result-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }

        .result-item.success {
            border-left-color: #28a745;
        }

        .result-item.error {
            border-left-color: #dc3545;
        }

        .result-item.pending {
            border-left-color: #ffc107;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .progress-bar-container {
            background: rgba(0, 0, 0, 0.3);
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #00d4ff, #7c3aed);
            height: 100%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .site-list {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 10px;
        }

        .site-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 5px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
        }

        .site-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .info-box {
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #00d4ff;
            margin-bottom: 10px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="tools.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Tools
            </a>
            <div class="header-right">
                <?php if ($isOwner): ?>
                <div class="owner-badge">
                    <i class="fas fa-crown"></i>
                    OWNER
                </div>
                <?php endif; ?>
                <div class="credits-display">
                    <i class="fas fa-coins"></i>
                    <span id="creditsDisplay"><?php echo $isOwner ? '∞' : number_format($user['credits']); ?> Credits</span>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Checker Section -->
            <div>
                <div class="card">
                    <h2 class="card-title"><i class="fas fa-layer-group"></i> Multi Stripe Checker</h2>
                    
                    <div class="info-box">
                        <h3>Features:</h3>
                        <ul style="padding-left: 20px;">
                            <li>Multi-threaded checking (up to 10 concurrent)</li>
                            <li>Auto site rotation every <?php echo StripeSiteManager::getRotationCount(); ?> checks</li>
                            <li>BIN info & country display</li>
                            <li>Real-time progress tracking</li>
                            <li>1 credit per check <?php echo $isOwner ? '(FREE for owner)' : ''; ?></li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label for="cardsInput">Credit Cards (one per line)</label>
                        <textarea id="cardsInput" placeholder="4111111111111111|12|2025|123&#10;4111111111111111|12|2026|456"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="proxyInput">Proxy (Optional)</label>
                        <input type="text" id="proxyInput" placeholder="ip:port:user:pass">
                    </div>

                    <div class="form-group">
                        <label for="threadsInput">Concurrent Checks (1-10)</label>
                        <input type="number" id="threadsInput" value="5" min="1" max="10">
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-primary" id="startBtn">
                            <i class="fas fa-play"></i> Start Checking
                        </button>
                        <button class="btn btn-danger" id="stopBtn" disabled>
                            <i class="fas fa-stop"></i> Stop
                        </button>
                    </div>
                </div>

                <div class="card">
                    <h3 class="card-title">Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value" id="totalStat">0</div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value" id="successStat" style="color: #28a745;">0</div>
                            <div class="stat-label">Success</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value" id="failedStat" style="color: #dc3545;">0</div>
                            <div class="stat-label">Failed</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value" id="remainingStat" style="color: #ffc107;">0</div>
                            <div class="stat-label">Remaining</div>
                        </div>
                    </div>
                    
                    <div class="progress-bar-container">
                        <div class="progress-bar" id="progressBar" style="width: 0%;">0%</div>
                    </div>
                </div>
            </div>

            <!-- Results & Management Section -->
            <div>
                <div class="card">
                    <h3 class="card-title"><i class="fas fa-list"></i> Results</h3>
                    <div class="results-container" id="resultsContainer">
                        <p style="text-align: center; color: rgba(255,255,255,0.5);">No results yet. Start checking to see results here.</p>
                    </div>
                    <div class="btn-group" style="margin-top: 15px;">
                        <button class="btn btn-success" id="downloadSuccess">
                            <i class="fas fa-download"></i> Download Success
                        </button>
                        <button class="btn btn-primary" id="copySuccess">
                            <i class="fas fa-copy"></i> Copy Success
                        </button>
                    </div>
                </div>

                <?php if ($isOwner): ?>
                <div class="card">
                    <h3 class="card-title"><i class="fas fa-cog"></i> Site Management (Owner)</h3>
                    
                    <div class="form-group">
                        <label>Total Sites: <?php echo StripeSiteManager::getSiteCount(); ?></label>
                        <label>Rotation: Every <?php echo StripeSiteManager::getRotationCount(); ?> checks</label>
                    </div>

                    <div class="form-group">
                        <label for="newSiteInput">Add New Site</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="newSiteInput" placeholder="example.com">
                            <button class="btn btn-success" id="addSiteBtn">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rotationInput">Update Rotation Count</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="number" id="rotationInput" value="<?php echo StripeSiteManager::getRotationCount(); ?>" min="1" max="100">
                            <button class="btn btn-primary" id="updateRotationBtn">
                                <i class="fas fa-sync"></i> Update
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-primary" id="viewSitesBtn">
                        <i class="fas fa-eye"></i> View All Sites
                    </button>

                    <div id="siteListContainer" style="display: none; margin-top: 15px;">
                        <div class="site-list" id="siteList"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        let checking = false;
        let stopRequested = false;
        let checkNumber = 0;
        let successCards = [];
        let failedCards = [];
        let totalCards = 0;
        let processed = 0;
        const isOwner = <?php echo $isOwner ? 'true' : 'false'; ?>;

        // Statistics
        function updateStats() {
            document.getElementById('totalStat').textContent = totalCards;
            document.getElementById('successStat').textContent = successCards.length;
            document.getElementById('failedStat').textContent = failedCards.length;
            document.getElementById('remainingStat').textContent = totalCards - processed;
            
            const progress = totalCards > 0 ? Math.round((processed / totalCards) * 100) : 0;
            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressBar').textContent = progress + '%';
        }

        // Add result to display
        function addResult(card, result, binInfo, site) {
            const container = document.getElementById('resultsContainer');
            if (container.children.length === 1 && container.children[0].tagName === 'P') {
                container.innerHTML = '';
            }

            const div = document.createElement('div');
            div.className = 'result-item ' + (result.success ? 'success' : 'error');
            
            const country = binInfo?.country ? ` (${binInfo.country})` : '';
            const bank = binInfo?.bank || 'Unknown';
            const cardType = binInfo?.type || 'Unknown';
            
            div.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <strong>${result.success ? '✅ SUCCESS' : '❌ FAILED'}</strong>
                        <div style="font-size: 0.9rem; margin-top: 5px;">
                            <div>💳 ${card}</div>
                            <div>🏦 ${bank} - ${cardType}${country}</div>
                            <div>🌐 Site: ${site}</div>
                            <div>📝 ${result.message}</div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertBefore(div, container.firstChild);
            
            // Keep only last 50 results
            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        }

        // Check single card
        async function checkCard(card, threads) {
            const proxy = document.getElementById('proxyInput').value;
            const num = checkNumber++;
            
            const params = new URLSearchParams({
                check_card: '1',
                cc: card,
                check_num: num,
                proxy: proxy
            });

            try {
                const response = await fetch('stripe_checker_multi.php?' + params);
                const data = await response.json();
                
                if (data.credits_exhausted) {
                    stopRequested = true;
                    alert('Credits exhausted! Stopping checks.');
                    return false;
                }
                
                processed++;
                
                if (data.success && data.result.success) {
                    successCards.push(card);
                    addResult(card, data.result, data.bin_info, data.site_used);
                } else {
                    failedCards.push(card);
                    addResult(card, data.result || { success: false, message: data.error }, data.bin_info || {}, data.site_used || 'N/A');
                }
                
                // Update credits display
                if (!isOwner && data.remaining_credits !== undefined) {
                    document.getElementById('creditsDisplay').textContent = data.remaining_credits.toLocaleString() + ' Credits';
                }
                
                updateStats();
                return true;
            } catch (error) {
                processed++;
                failedCards.push(card);
                addResult(card, { success: false, message: 'Network error: ' + error.message }, {}, 'N/A');
                updateStats();
                return true;
            }
        }

        // Multi-threaded checker
        async function startChecking() {
            const cardsText = document.getElementById('cardsInput').value;
            const threads = parseInt(document.getElementById('threadsInput').value);
            
            const cards = cardsText.split('\n').map(c => c.trim()).filter(c => c);
            
            if (cards.length === 0) {
                alert('Please enter at least one card');
                return;
            }
            
            // Check credits
            if (!isOwner) {
                const currentCredits = parseInt(document.getElementById('creditsDisplay').textContent.replace(/[^0-9]/g, ''));
                if (currentCredits < cards.length) {
                    alert(`Insufficient credits! You need ${cards.length} credits but have ${currentCredits}`);
                    return;
                }
            }
            
            checking = true;
            stopRequested = false;
            checkNumber = 0;
            successCards = [];
            failedCards = [];
            totalCards = cards.length;
            processed = 0;
            
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('resultsContainer').innerHTML = '';
            
            updateStats();
            
            // Process cards with concurrency limit
            const promises = [];
            for (let i = 0; i < cards.length && !stopRequested; i++) {
                if (promises.length >= threads) {
                    await Promise.race(promises);
                    promises.splice(promises.findIndex(p => p.resolved), 1);
                }
                
                const promise = checkCard(cards[i], threads);
                promise.then(() => promise.resolved = true);
                promises.push(promise);
            }
            
            // Wait for all to complete
            await Promise.all(promises);
            
            checking = false;
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            
            alert(`Checking complete!\nSuccess: ${successCards.length}\nFailed: ${failedCards.length}`);
        }

        // Event listeners
        document.getElementById('startBtn').addEventListener('click', startChecking);
        
        document.getElementById('stopBtn').addEventListener('click', () => {
            stopRequested = true;
            document.getElementById('stopBtn').disabled = true;
        });

        document.getElementById('downloadSuccess').addEventListener('click', () => {
            if (successCards.length === 0) {
                alert('No success cards to download');
                return;
            }
            
            const blob = new Blob([successCards.join('\n')], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'success_cards.txt';
            a.click();
            URL.revokeObjectURL(url);
        });

        document.getElementById('copySuccess').addEventListener('click', async () => {
            if (successCards.length === 0) {
                alert('No success cards to copy');
                return;
            }
            
            try {
                await navigator.clipboard.writeText(successCards.join('\n'));
                alert('Success cards copied to clipboard!');
            } catch (error) {
                alert('Failed to copy: ' + error.message);
            }
        });

        <?php if ($isOwner): ?>
        // Owner functions
        document.getElementById('addSiteBtn').addEventListener('click', async () => {
            const site = document.getElementById('newSiteInput').value;
            if (!site) return;
            
            const formData = new FormData();
            formData.append('action', 'add_site');
            formData.append('site', site);
            
            const response = await fetch('stripe_checker_multi.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            alert(data.message);
            
            if (data.success) {
                document.getElementById('newSiteInput').value = '';
                location.reload();
            }
        });

        document.getElementById('updateRotationBtn').addEventListener('click', async () => {
            const count = document.getElementById('rotationInput').value;
            
            const formData = new FormData();
            formData.append('action', 'update_rotation');
            formData.append('count', count);
            
            const response = await fetch('stripe_checker_multi.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            alert(data.message);
            location.reload();
        });

        document.getElementById('viewSitesBtn').addEventListener('click', async () => {
            const container = document.getElementById('siteListContainer');
            const list = document.getElementById('siteList');
            
            if (container.style.display === 'none') {
                const formData = new FormData();
                formData.append('action', 'get_sites');
                
                const response = await fetch('stripe_checker_multi.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                list.innerHTML = '';
                data.sites.forEach(site => {
                    const div = document.createElement('div');
                    div.className = 'site-item';
                    div.innerHTML = `
                        <span>${site}</span>
                        <button class="btn btn-danger" style="padding: 5px 15px; font-size: 0.8rem;" onclick="removeSite('${site}')">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    `;
                    list.appendChild(div);
                });
                
                container.style.display = 'block';
                document.getElementById('viewSitesBtn').innerHTML = '<i class="fas fa-eye-slash"></i> Hide Sites';
            } else {
                container.style.display = 'none';
                document.getElementById('viewSitesBtn').innerHTML = '<i class="fas fa-eye"></i> View All Sites';
            }
        });

        async function removeSite(site) {
            if (!confirm(`Remove site: ${site}?`)) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_site');
            formData.append('site', site);
            
            const response = await fetch('stripe_checker_multi.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            alert(data.message);
            location.reload();
        }
        <?php endif; ?>
    </script>
</body>
</html>
