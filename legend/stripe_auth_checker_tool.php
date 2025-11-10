<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

// Update presence
$db->updatePresence($userId);

// Get total active stripe auth sites
$stripeAuthSites = $db->getActiveStripeAuthSites();
$totalSites = count($stripeAuthSites);
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
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .info-banner {
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .info-banner i {
            color: #00d4ff;
            margin-right: 0.5rem;
        }

        .checker-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .checker-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #00d4ff;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        textarea, input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', monospace;
            font-size: 0.9rem;
            resize: vertical;
        }

        textarea {
            min-height: 200px;
        }

        textarea:focus, input[type="text"]:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            margin-top: 0.5rem;
        }

        .results-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            grid-column: 1 / -1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .result-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            padding: 1rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .tab-btn.active {
            color: #00d4ff;
            border-bottom-color: #00d4ff;
        }

        .results-container {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }

        .result-card.live {
            border-left-color: #00ff88;
        }

        .result-card.dead {
            border-left-color: #ff4444;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .result-status {
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .result-status.live {
            background: rgba(0, 255, 136, 0.2);
            color: #00ff88;
        }

        .result-status.dead {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
        }

        .result-details {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
        }

        .copy-btn {
            background: rgba(0, 212, 255, 0.2);
            border: 1px solid rgba(0, 212, 255, 0.3);
            color: #00d4ff;
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: rgba(0, 212, 255, 0.3);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #00d4ff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1024px) {
            .checker-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-title h1 {
                font-size: 2rem;
            }

            .checker-panel {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <span><?php echo number_format($user['credits']); ?> Credits</span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-credit-card"></i> Stripe Auth Checker</h1>
            <p>Professional Stripe authentication testing tool</p>
        </div>

        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            <strong><?php echo $totalSites; ?></strong> active Stripe Auth sites available | 
            <strong>1 Credit</strong> per check | 
            Sites rotate every <strong>20 requests</strong>
        </div>

        <div class="checker-container">
            <div class="checker-panel">
                <h2 class="panel-title"><i class="fas fa-edit"></i> Input</h2>
                
                <div class="input-group">
                    <label for="cardInput">
                        <i class="fas fa-credit-card"></i> Credit Cards
                        <small style="color: rgba(255,255,255,0.5);">(Format: cc|mm|yyyy|cvv)</small>
                    </label>
                    <textarea id="cardInput" placeholder="4111111111111111|12|2025|123&#10;5444224035733160|02|2029|832&#10;..."></textarea>
                </div>

                <div class="input-group">
                    <label for="proxyInput">
                        <i class="fas fa-network-wired"></i> Proxy (Optional)
                        <small style="color: rgba(255,255,255,0.5);">(Format: ip:port:user:pass)</small>
                    </label>
                    <input type="text" id="proxyInput" placeholder="proxy.example.com:8080:username:password">
                </div>

                <button class="btn" id="startBtn" onclick="startChecking()">
                    <i class="fas fa-play"></i> Start Checking
                </button>
                <button class="btn btn-secondary" id="stopBtn" onclick="stopChecking()" disabled>
                    <i class="fas fa-stop"></i> Stop Checking
                </button>
                <button class="btn btn-secondary" onclick="clearResults()">
                    <i class="fas fa-trash"></i> Clear Results
                </button>
            </div>

            <div class="checker-panel">
                <h2 class="panel-title"><i class="fas fa-chart-bar"></i> Statistics</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" id="totalChecked" style="color: #00d4ff;">0</div>
                        <div class="stat-label">Total Checked</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="liveCount" style="color: #00ff88;">0</div>
                        <div class="stat-label">Live</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="deadCount" style="color: #ff4444;">0</div>
                        <div class="stat-label">Dead</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="remainingCount" style="color: #ffa500;">0</div>
                        <div class="stat-label">Remaining</div>
                    </div>
                </div>

                <div style="text-align: center; padding: 1rem; background: rgba(0,212,255,0.1); border-radius: 10px;">
                    <div style="font-size: 1.2rem; font-weight: 600; margin-bottom: 0.5rem;">
                        Credits Used: <span id="creditsUsed" style="color: #00d4ff;">0</span>
                    </div>
                    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">
                        Remaining Balance: <span id="creditsRemaining"><?php echo number_format($user['credits']); ?></span>
                    </div>
                </div>
            </div>

            <div class="results-panel">
                <h2 class="panel-title"><i class="fas fa-list"></i> Results</h2>
                
                <div class="result-tabs">
                    <button class="tab-btn active" onclick="switchTab('all')">All Results</button>
                    <button class="tab-btn" onclick="switchTab('live')">Live Only</button>
                    <button class="tab-btn" onclick="switchTab('dead')">Dead Only</button>
                </div>

                <div class="results-container" id="resultsContainer">
                    <div class="loading" style="display: none;" id="loadingIndicator">
                        <div class="spinner"></div>
                        <p>Processing your cards...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        let checking = false;
        let currentTab = 'all';
        let results = [];
        let stats = {
            total: 0,
            live: 0,
            dead: 0,
            remaining: 0,
            creditsUsed: 0
        };
        let abortController = null;

        function updateStats() {
            document.getElementById('totalChecked').textContent = stats.total;
            document.getElementById('liveCount').textContent = stats.live;
            document.getElementById('deadCount').textContent = stats.dead;
            document.getElementById('remainingCount').textContent = stats.remaining;
            document.getElementById('creditsUsed').textContent = stats.creditsUsed;
            document.getElementById('creditsRemaining').textContent = 
                <?php echo $user['credits']; ?> - stats.creditsUsed;
        }

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            renderResults();
        }

        function renderResults() {
            const container = document.getElementById('resultsContainer');
            const filteredResults = results.filter(r => {
                if (currentTab === 'all') return true;
                if (currentTab === 'live') return r.status === 'live';
                if (currentTab === 'dead') return r.status === 'dead';
            });

            if (filteredResults.length === 0 && stats.total > 0) {
                container.innerHTML = '<div class="loading"><p>No results in this category</p></div>';
                return;
            }

            container.innerHTML = filteredResults.map(result => `
                <div class="result-card ${result.status}">
                    <div class="result-header">
                        <span class="result-status ${result.status}">
                            ${result.status === 'live' ? '✓ APPROVED' : '✗ DECLINED'}
                        </span>
                        <button class="copy-btn" onclick="copyResult(${results.indexOf(result)})">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <div class="result-details">
                        <div><strong>Card:</strong> <code>${result.card}</code></div>
                        <div><strong>Site:</strong> ${result.site}</div>
                        <div><strong>Response:</strong> ${result.message}</div>
                        ${result.cardInfo ? `
                        <div><strong>Bank:</strong> ${result.cardInfo.bank || 'Unknown'}</div>
                        <div><strong>Type:</strong> ${result.cardInfo.type || 'Unknown'}</div>
                        <div><strong>Country:</strong> ${result.cardInfo.country || 'Unknown'}</div>
                        ` : ''}
                        <div><strong>Time:</strong> ${result.time}s</div>
                    </div>
                </div>
            `).join('');
        }

        function copyResult(index) {
            const result = results[index];
            const text = `Card: ${result.card}\nStatus: ${result.status.toUpperCase()}\nSite: ${result.site}\nResponse: ${result.message}\nTime: ${result.time}s`;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Result copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }

        async function checkCard(card, proxy) {
            const formData = new FormData();
            formData.append('card', card);
            if (proxy) formData.append('proxy', proxy);

            const response = await fetch('check_stripe_ajax.php', {
                method: 'POST',
                body: formData,
                signal: abortController.signal
            });

            return await response.json();
        }

        async function startChecking() {
            const cardsInput = document.getElementById('cardInput').value.trim();
            const proxy = document.getElementById('proxyInput').value.trim();

            if (!cardsInput) {
                alert('Please enter at least one card!');
                return;
            }

            const cards = cardsInput.split('\n').filter(c => c.trim());
            
            if (cards.length === 0) {
                alert('No valid cards found!');
                return;
            }

            // Check if user has enough credits
            const requiredCredits = cards.length;
            if (requiredCredits > <?php echo $user['credits']; ?>) {
                alert(`Insufficient credits! You need ${requiredCredits} credits but only have <?php echo $user['credits']; ?>.`);
                return;
            }

            checking = true;
            abortController = new AbortController();
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            document.getElementById('loadingIndicator').style.display = 'block';

            stats = {
                total: 0,
                live: 0,
                dead: 0,
                remaining: cards.length,
                creditsUsed: 0
            };
            results = [];
            updateStats();

            for (let i = 0; i < cards.length && checking; i++) {
                const card = cards[i].trim();
                
                try {
                    const startTime = performance.now();
                    const result = await checkCard(card, proxy);
                    const endTime = performance.now();
                    const timeTaken = ((endTime - startTime) / 1000).toFixed(2);

                    const resultData = {
                        card: card,
                        status: result.success ? 'live' : 'dead',
                        message: result.message || 'No message',
                        site: result.site || 'Unknown',
                        cardInfo: result.cardInfo || null,
                        time: timeTaken
                    };

                    results.unshift(resultData);
                    stats.total++;
                    stats.creditsUsed++;
                    stats.remaining--;
                    
                    if (result.success) {
                        stats.live++;
                    } else {
                        stats.dead++;
                    }

                    updateStats();
                    renderResults();

                } catch (error) {
                    if (error.name === 'AbortError') {
                        break;
                    }
                    console.error('Check failed:', error);
                    
                    results.unshift({
                        card: card,
                        status: 'dead',
                        message: 'Error: ' + error.message,
                        site: 'N/A',
                        time: '0'
                    });
                    stats.total++;
                    stats.dead++;
                    stats.creditsUsed++;
                    stats.remaining--;
                    updateStats();
                    renderResults();
                }
            }

            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('loadingIndicator').style.display = 'none';
            checking = false;
        }

        function stopChecking() {
            checking = false;
            if (abortController) {
                abortController.abort();
            }
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled = true;
            document.getElementById('loadingIndicator').style.display = 'none';
        }

        function clearResults() {
            results = [];
            stats = {
                total: 0,
                live: 0,
                dead: 0,
                remaining: 0,
                creditsUsed: 0
            };
            updateStats();
            document.getElementById('resultsContainer').innerHTML = '';
            document.getElementById('cardInput').value = '';
        }

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
