<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);
$credits = intval($user['credits'] ?? 0);

// Update presence
$db->updatePresence($userId);

// Get user proxies count
$userProxies = $db->getUserProxies($userId);
$liveProxiesCount = count(array_filter($userProxies, fn($p) => ($p['status'] ?? 'untested') === 'live'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Card Checker - LEGEND CHECKER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-card: #2a2a2a;
            --bg-card-hover: #333333;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #6b7280;
            --accent-blue: #1da1f2;
            --accent-green: #00d4aa;
            --accent-purple: #8b5cf6;
            --accent-orange: #f59e0b;
            --accent-pink: #ec4899;
            --accent-red: #ef4444;
            --border-color: #3a3a3a;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            padding-bottom: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Top Stats Bar */
        .top-stats-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .stat-value {
            font-weight: 700;
            font-size: 18px;
        }

        .stat-value.credits {
            color: var(--accent-green);
        }

        .stat-value.proxies {
            color: var(--accent-blue);
        }

        .stat-value.checking {
            color: var(--warning-color);
        }

        .stat-value.live {
            color: var(--success-color);
        }

        .stat-value.dead {
            color: var(--error-color);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--accent-green);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background: #00e6bb;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-card-hover);
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--accent-green);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-control {
            width: 100%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent-green);
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .results-section {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            max-height: 600px;
            overflow-y: auto;
        }

        .result-item {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            transition: all 0.3s ease;
        }

        .result-item:hover {
            background: var(--bg-card-hover);
            transform: translateX(4px);
        }

        .result-item.live {
            border-left-color: var(--success-color);
        }

        .result-item.dead {
            border-left-color: var(--error-color);
        }

        .result-item.checking {
            border-left-color: var(--warning-color);
        }

        .result-icon {
            font-size: 24px;
        }

        .result-info {
            flex: 1;
        }

        .result-card {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .result-details {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .result-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-live {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        .status-dead {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }

        .status-checking {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--bg-secondary);
            border-radius: 3px;
            overflow: hidden;
            margin: 16px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-green), var(--accent-blue));
            width: 0%;
            transition: width 0.3s ease;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--text-muted);
            border-top: 2px solid var(--accent-green);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .result-item {
                grid-template-columns: 1fr;
            }
        }

        .settings-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .settings-card h3 {
            font-size: 16px;
            margin-bottom: 12px;
            color: var(--accent-blue);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Live Stats Bar -->
        <div class="top-stats-bar">
            <div class="stat-item">
                <i class="fas fa-coins" style="color: var(--accent-green);"></i>
                <div>
                    <div class="stat-label">Credits</div>
                    <div class="stat-value credits" id="live-credits"><?php echo number_format($credits); ?></div>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-server" style="color: var(--accent-blue);"></i>
                <div>
                    <div class="stat-label">Live Proxies</div>
                    <div class="stat-value proxies" id="live-proxies-count"><?php echo $liveProxiesCount; ?></div>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-spinner" style="color: var(--warning-color);"></i>
                <div>
                    <div class="stat-label">Checking</div>
                    <div class="stat-value checking" id="checking-count">0</div>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                <div>
                    <div class="stat-label">Live</div>
                    <div class="stat-value live" id="live-count">0</div>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-times-circle" style="color: var(--error-color);"></i>
                <div>
                    <div class="stat-label">Dead</div>
                    <div class="stat-value dead" id="dead-count">0</div>
                </div>
            </div>
        </div>

        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Enhanced Card Checker</h1>
            <div class="header-actions">
                <a href="proxy_manager.php" class="btn btn-secondary">
                    <i class="fas fa-server"></i> Manage Proxies
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid-2">
            <!-- Input Section -->
            <div class="card">
                <h2><i class="fas fa-upload"></i> Input</h2>
                
                <div class="form-group">
                    <label>Cards (one per line)</label>
                    <textarea id="cards-input" class="form-control" placeholder="4111111111111111|12|25|123&#10;5555555555554444|01|26|456&#10;..."></textarea>
                </div>

                <div class="form-group">
                    <label>Test Site (optional)</label>
                    <input type="text" id="site-input" class="form-control" placeholder="https://example.com" value="https://shopify.com">
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-cog"></i> Settings</h3>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="use-user-proxies" checked>
                        <label for="use-user-proxies">Use My Proxies (<?php echo $liveProxiesCount; ?> live)</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="save-results" checked>
                        <label for="save-results">Save Results</label>
                    </div>

                    <div class="form-group">
                        <label>Delay (seconds)</label>
                        <input type="number" id="delay" class="form-control" value="1" min="0" max="10" step="0.5">
                    </div>
                </div>

                <div class="button-group">
                    <button id="start-check" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-play"></i> Start Check
                    </button>
                    <button id="stop-check" class="btn btn-danger" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                </div>

                <div class="progress-bar" style="margin-top: 16px;">
                    <div class="progress-fill" id="progress-bar"></div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="card">
                <h2><i class="fas fa-list"></i> Results</h2>
                <div class="results-section" id="results-container">
                    <div class="empty-state">
                        <i class="fas fa-credit-card"></i>
                        <p>No results yet. Start checking cards to see results here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        let isChecking = false;
        let stats = {
            checking: 0,
            live: 0,
            dead: 0,
            total: 0
        };
        let currentCredits = <?php echo $credits; ?>;

        function updateStats() {
            document.getElementById('checking-count').textContent = stats.checking;
            document.getElementById('live-count').textContent = stats.live;
            document.getElementById('dead-count').textContent = stats.dead;
            document.getElementById('live-credits').textContent = currentCredits.toLocaleString();
        }

        function addResult(result) {
            const container = document.getElementById('results-container');
            
            // Clear empty state on first result
            if (stats.total === 0) {
                container.innerHTML = '';
            }
            
            const resultDiv = document.createElement('div');
            resultDiv.className = `result-item ${result.status}`;
            
            const iconMap = {
                live: '<i class="fas fa-check-circle" style="color: var(--success-color);"></i>',
                dead: '<i class="fas fa-times-circle" style="color: var(--error-color);"></i>',
                checking: '<div class="loading-spinner"></div>'
            };
            
            resultDiv.innerHTML = `
                <div class="result-icon">${iconMap[result.status] || iconMap.checking}</div>
                <div class="result-info">
                    <div class="result-card">${result.card}</div>
                    <div class="result-details">
                        <span><i class="fas fa-globe"></i> ${result.gateway || 'N/A'}</span>
                        <span><i class="fas fa-clock"></i> ${result.time || 'N/A'}</span>
                        <span><i class="fas fa-server"></i> ${result.proxy_status || 'No Proxy'}</span>
                    </div>
                </div>
                <div class="result-status status-${result.status}">${result.message || result.status}</div>
            `;
            
            container.insertBefore(resultDiv, container.firstChild);
            
            // Update result if it was checking
            if (result.id) {
                const oldResult = document.querySelector(`[data-id="${result.id}"]`);
                if (oldResult) oldResult.remove();
            }
            
            if (result.id) {
                resultDiv.setAttribute('data-id', result.id);
            }
            
            // Limit results display to 50 items
            const results = container.querySelectorAll('.result-item');
            if (results.length > 50) {
                results[results.length - 1].remove();
            }
        }

        async function checkCard(card, site, cardId) {
            stats.checking++;
            stats.total++;
            updateStats();
            
            // Add checking result
            addResult({
                id: cardId,
                card: card.substring(0, 20) + '...',
                status: 'checking',
                message: 'Checking...'
            });
            
            try {
                const useUserProxies = document.getElementById('use-user-proxies').checked;
                const params = new URLSearchParams({
                    cc: card,
                    site: site || 'https://shopify.com',
                    use_user_proxies: useUserProxies ? '1' : '0'
                });
                
                const response = await fetch(`check_card_ajax.php?${params}`);
                const data = await response.json();
                
                stats.checking--;
                
                if (data.error) {
                    stats.dead++;
                    addResult({
                        id: cardId,
                        card: card,
                        status: 'dead',
                        message: data.message || 'Error',
                        gateway: 'N/A',
                        time: 'N/A',
                        proxy_status: 'N/A'
                    });
                } else {
                    const isLive = data.ui_status_type === 'APPROVED' || data.ui_status_type === 'CHARGED';
                    if (isLive) {
                        stats.live++;
                    } else {
                        stats.dead++;
                    }
                    
                    addResult({
                        id: cardId,
                        card: card,
                        status: isLive ? 'live' : 'dead',
                        message: data.status,
                        gateway: data.gateway,
                        time: data.time,
                        proxy_status: data.proxy_status
                    });
                    
                    // Update credits from response
                    if (data.remaining_credits !== undefined) {
                        currentCredits = data.remaining_credits;
                    } else if (data.credits_deducted) {
                        currentCredits -= data.credits_deducted;
                    }
                }
                
                updateStats();
            } catch (error) {
                stats.checking--;
                stats.dead++;
                addResult({
                    id: cardId,
                    card: card,
                    status: 'dead',
                    message: 'Network Error',
                    gateway: 'N/A',
                    time: 'N/A',
                    proxy_status: 'N/A'
                });
                updateStats();
            }
        }

        async function startChecking() {
            if (isChecking) return;
            
            const cardsInput = document.getElementById('cards-input').value.trim();
            const siteInput = document.getElementById('site-input').value.trim();
            const delay = parseFloat(document.getElementById('delay').value) * 1000;
            
            if (!cardsInput) {
                alert('Please enter at least one card');
                return;
            }
            
            const cards = cardsInput.split('\n').filter(line => line.trim());
            
            if (currentCredits < cards.length) {
                if (!confirm(`You have ${currentCredits} credits but ${cards.length} cards to check. Continue anyway?`)) {
                    return;
                }
            }
            
            isChecking = true;
            document.getElementById('start-check').disabled = true;
            document.getElementById('stop-check').disabled = false;
            
            // Reset stats
            stats = { checking: 0, live: 0, dead: 0, total: 0 };
            updateStats();
            
            const progressBar = document.getElementById('progress-bar');
            
            for (let i = 0; i < cards.length && isChecking; i++) {
                const card = cards[i].trim();
                if (!card) continue;
                
                const cardId = 'card_' + Date.now() + '_' + i;
                await checkCard(card, siteInput, cardId);
                
                // Update progress
                const progress = ((i + 1) / cards.length) * 100;
                progressBar.style.width = progress + '%';
                
                // Add delay between checks
                if (delay > 0 && i < cards.length - 1 && isChecking) {
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
            
            stopChecking();
        }

        function stopChecking() {
            isChecking = false;
            document.getElementById('start-check').disabled = false;
            document.getElementById('stop-check').disabled = true;
        }

        // Event listeners
        document.getElementById('start-check').addEventListener('click', startChecking);
        document.getElementById('stop-check').addEventListener('click', stopChecking);
        
        // Update live credits every 5 seconds
        setInterval(async () => {
            try {
                const response = await fetch('api/get_credits.php');
                const data = await response.json();
                if (data.credits !== undefined) {
                    currentCredits = data.credits;
                    updateStats();
                }
            } catch (error) {
                console.error('Failed to update credits:', error);
            }
        }, 5000);
    </script>
</body>
</html>
