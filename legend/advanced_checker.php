<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

// Use secure session initialization
initSecureSession();

// Check if user is authenticated
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
$user = $db->getUserByTelegramId($telegram_id);

if (!$user) {
    header('Location: login.php');
    exit;
}

$credits = intval($user['credits'] ?? 0);
$user_role = $user['role'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Checker - LEGEND TOOLS</title>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-color: #000000;
            --panel-bg: #1a2b49;
            --input-bg: #223041;
            --text-color: #00ffea;
            --accent-color: #00e676;
            --danger-color: #ff073a;
            --warning-color: #e67e22;
            --border-color: #00bcd4;
            --font-mono: 'Share Tech Mono', monospace;
            --font-heading: 'Orbitron', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-mono);
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 25%, #16213e 50%, #0f3460 75%, #533483 100%);
            color: var(--text-color);
            min-height: 100vh;
            padding: 10px;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-family: var(--font-heading);
            font-size: 2.5em;
            color: var(--accent-color);
            text-shadow: 0 0 20px var(--accent-color);
            margin-bottom: 10px;
        }

        .header .credits-display {
            background: var(--panel-bg);
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .nav-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-tab {
            background: var(--panel-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-tab:hover,
        .nav-tab.active {
            background: var(--accent-color);
            color: var(--bg-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 230, 118, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .checker-panel {
            background: var(--panel-bg);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid var(--border-color);
            box-shadow: 0 0 30px rgba(0, 255, 234, 0.1);
            margin-bottom: 20px;
        }

        .checker-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .checker-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        .input-section {
            margin-bottom: 20px;
        }

        .input-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--accent-color);
        }

        .input-group {
            position: relative;
            margin-bottom: 15px;
        }

        .input-field {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 15px;
            color: var(--text-color);
            font-family: var(--font-mono);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 10px rgba(0, 230, 118, 0.3);
        }

        .textarea-field {
            min-height: 120px;
            resize: vertical;
        }

        .settings-section {
            background: var(--input-bg);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent-color);
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: var(--font-mono);
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--accent-color);
            color: var(--bg-color);
        }

        .btn-primary:hover {
            background: #69f0ae;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 230, 118, 0.4);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 7, 58, 0.4);
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .results-section {
            margin-top: 30px;
        }

        .stats-bar {
            background: var(--input-bg);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            border: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            background: var(--panel-bg);
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 0.8em;
            opacity: 0.8;
            margin-top: 5px;
        }

        .results-container {
            background: var(--input-bg);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid var(--border-color);
            max-height: 500px;
            overflow-y: auto;
        }

        .result-item {
            background: var(--panel-bg);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .result-item.live { border-left-color: #28a745; }
        .result-item.dead { border-left-color: #dc3545; }
        .result-item.unknown { border-left-color: #ffc107; }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .result-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-live { background: #28a745; color: white; }
        .status-dead { background: #dc3545; color: white; }
        .status-unknown { background: #ffc107; color: black; }

        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            font-size: 0.9em;
        }

        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--accent-color);
            color: var(--bg-color);
            border: none;
            border-radius: 4px;
            padding: 5px 8px;
            cursor: pointer;
            font-size: 0.8em;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--input-bg);
            border-radius: 3px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-color), #69f0ae);
            width: 0%;
            transition: width 0.3s ease;
        }

        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--panel-bg);
            color: var(--text-color);
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-link:hover {
            background: var(--accent-color);
            color: var(--bg-color);
        }

        .feature-badge {
            background: var(--accent-color);
            color: var(--bg-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Dashboard
    </a>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Advanced Checker</h1>
            <div class="credits-display">
                <i class="fas fa-coins"></i> Credits: <strong><?php echo $credits; ?></strong>
                <span class="feature-badge">PRO</span>
            </div>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="switchTab('card-checker')">
                <i class="fas fa-credit-card"></i> Card Checker
            </button>
            <button class="nav-tab" onclick="switchTab('site-checker')">
                <i class="fas fa-globe"></i> Site Checker
            </button>
            <button class="nav-tab" onclick="switchTab('bulk-checker')">
                <i class="fas fa-list"></i> Bulk Checker
            </button>
            <button class="nav-tab" onclick="switchTab('history')">
                <i class="fas fa-history"></i> History
            </button>
        </div>

        <!-- Card Checker Tab -->
        <div id="card-checker" class="tab-content active">
            <div class="checker-panel">
                <h3><i class="fas fa-credit-card"></i> Advanced Card Checker</h3>
                <div class="checker-grid">
                    <div>
                        <div class="input-section">
                            <label for="card-input">Cards (one per line)</label>
                            <textarea id="card-input" class="input-field textarea-field" 
                                placeholder="4111111111111111|12|25|123&#10;5555555555554444|01|26|456&#10;..."></textarea>
                        </div>
                        
                        <div class="input-section">
                            <label for="site-input">Test Sites (optional)</label>
                            <textarea id="site-input" class="input-field" rows="3"
                                placeholder="https://example.com&#10;https://shop.example.com"></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <div class="settings-section">
                            <h4>Advanced Settings</h4>
                            <div class="settings-grid">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="use-proxy" checked>
                                    <label for="use-proxy">Use Proxy</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="auto-stop" checked>
                                    <label for="auto-stop">Auto Stop on Error</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="save-results" checked>
                                    <label for="save-results">Save Results</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="telegram-notify">
                                    <label for="telegram-notify">Telegram Notify</label>
                                </div>
                            </div>
                            
                            <div class="input-group">
                                <label for="threads">Threads (1-10)</label>
                                <input type="number" id="threads" class="input-field" value="5" min="1" max="10">
                            </div>
                            
                            <div class="input-group">
                                <label for="delay">Delay (seconds)</label>
                                <input type="number" id="delay" class="input-field" value="1" min="0" max="10" step="0.1">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="button-group">
                    <button id="start-card-check" class="btn btn-primary">
                        <i class="fas fa-play"></i> Start Check
                    </button>
                    <button id="stop-card-check" class="btn btn-danger" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button id="clear-card-results" class="btn btn-warning">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Site Checker Tab -->
        <div id="site-checker" class="tab-content">
            <div class="checker-panel">
                <h3><i class="fas fa-globe"></i> Advanced Site Checker</h3>
                <div class="checker-grid">
                    <div>
                        <div class="input-section">
                            <label for="sites-input">Sites (one per line)</label>
                            <textarea id="sites-input" class="input-field textarea-field" 
                                placeholder="https://example.com&#10;https://shop.example.com&#10;..."></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <div class="settings-section">
                            <h4>Site Check Settings</h4>
                            <div class="checkbox-group">
                                <input type="checkbox" id="check-ssl" checked>
                                <label for="check-ssl">Check SSL</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="check-response-time" checked>
                                <label for="check-response-time">Response Time</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="check-redirects">
                                <label for="check-redirects">Follow Redirects</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="button-group">
                    <button id="start-site-check" class="btn btn-primary">
                        <i class="fas fa-play"></i> Start Check
                    </button>
                    <button id="stop-site-check" class="btn btn-danger" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button id="clear-site-results" class="btn btn-warning">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Checker Tab -->
        <div id="bulk-checker" class="tab-content">
            <div class="checker-panel">
                <h3><i class="fas fa-list"></i> Bulk Operations</h3>
                <p>Coming soon... Bulk file upload and processing capabilities.</p>
            </div>
        </div>

        <!-- History Tab -->
        <div id="history" class="tab-content">
            <div class="checker-panel">
                <h3><i class="fas fa-history"></i> Check History</h3>
                <p>Your recent checks will appear here...</p>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-value" id="total-checked">0</div>
                    <div class="stat-label">Total Checked</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="live-count">0</div>
                    <div class="stat-label">Live</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="dead-count">0</div>
                    <div class="stat-label">Dead</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="credits-used">0</div>
                    <div class="stat-label">Credits Used</div>
                </div>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            
            <div class="results-container" id="results-container">
                <div style="text-align: center; opacity: 0.5; padding: 40px;">
                    <i class="fas fa-search" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <p>Start a check to see results here...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isChecking = false;
        let currentCheck = null;
        let stats = {
            total: 0,
            live: 0,
            dead: 0,
            credits: 0
        };

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function updateStats() {
            document.getElementById('total-checked').textContent = stats.total;
            document.getElementById('live-count').textContent = stats.live;
            document.getElementById('dead-count').textContent = stats.dead;
            document.getElementById('credits-used').textContent = stats.credits;
        }

        function addResult(result) {
            const container = document.getElementById('results-container');
            
            if (stats.total === 0) {
                container.innerHTML = '';
            }
            
            const resultDiv = document.createElement('div');
            resultDiv.className = `result-item ${result.status.toLowerCase()}`;
            
            const statusClass = result.status === 'LIVE' ? 'status-live' : 
                               result.status === 'DEAD' ? 'status-dead' : 'status-unknown';
            
            resultDiv.innerHTML = `
                <button class="copy-btn" onclick="copyResult('${result.data}')">
                    <i class="fas fa-copy"></i>
                </button>
                <div class="result-header">
                    <strong>${result.data.substring(0, 20)}...</strong>
                    <span class="result-status ${statusClass}">${result.status}</span>
                </div>
                <div class="result-details">
                    <div><strong>Gateway:</strong> ${result.gateway || 'N/A'}</div>
                    <div><strong>Response:</strong> ${result.response || 'N/A'}</div>
                    <div><strong>Time:</strong> ${result.time || 'N/A'}</div>
                    <div><strong>Proxy:</strong> ${result.proxy || 'N/A'}</div>
                </div>
            `;
            
            container.appendChild(resultDiv);
            container.scrollTop = container.scrollHeight;
            
            // Update stats
            stats.total++;
            if (result.status === 'LIVE') stats.live++;
            else if (result.status === 'DEAD') stats.dead++;
            stats.credits++;
            
            updateStats();
        }

        function copyResult(data) {
            navigator.clipboard.writeText(data).then(() => {
                // Show temporary notification
                const notification = document.createElement('div');
                notification.textContent = 'Copied!';
                notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; background: var(--accent-color);
                    color: var(--bg-color); padding: 10px 20px; border-radius: 8px;
                    z-index: 10000; font-weight: bold;
                `;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            });
        }

        async function checkCard(card, site = '') {
            try {
                const params = new URLSearchParams({
                    cc: card,
                    site: site || 'https://shopify.com'
                });
                
                const response = await fetch(`check_card_ajax.php?${params}`);
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.message);
                }
                
                return {
                    data: card,
                    status: data.ui_status_type === 'APPROVED' ? 'LIVE' : 'DEAD',
                    gateway: data.gateway,
                    response: data.status,
                    time: data.time,
                    proxy: data.proxy_status
                };
            } catch (error) {
                return {
                    data: card,
                    status: 'ERROR',
                    gateway: 'N/A',
                    response: error.message,
                    time: 'N/A',
                    proxy: 'N/A'
                };
            }
        }

        async function checkSite(site) {
            try {
                const params = new URLSearchParams({ site });
                const response = await fetch(`check_site_ajax.php?${params}`);
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.message);
                }
                
                return {
                    data: site,
                    status: data.is_valid_site ? 'LIVE' : 'DEAD',
                    gateway: data.gateway || 'N/A',
                    response: data.status,
                    time: data.time,
                    proxy: data.proxy_status || 'N/A'
                };
            } catch (error) {
                return {
                    data: site,
                    status: 'ERROR',
                    gateway: 'N/A',
                    response: error.message,
                    time: 'N/A',
                    proxy: 'N/A'
                };
            }
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        async function startCardCheck() {
            if (isChecking) return;
            
            const input = document.getElementById('card-input').value.trim();
            if (!input) {
                alert('Please enter cards to check');
                return;
            }
            
            const cards = input.split('\n').filter(line => line.trim());
            const sites = document.getElementById('site-input').value.trim().split('\n').filter(line => line.trim());
            const delay = parseFloat(document.getElementById('delay').value) * 1000;
            
            isChecking = true;
            document.getElementById('start-card-check').disabled = true;
            document.getElementById('stop-card-check').disabled = false;
            
            // Reset stats
            stats = { total: 0, live: 0, dead: 0, credits: 0 };
            updateStats();
            
            const progressBar = document.getElementById('progress-fill');
            
            for (let i = 0; i < cards.length && isChecking; i++) {
                const card = cards[i].trim();
                const site = sites[i % sites.length] || '';
                
                const result = await checkCard(card, site);
                addResult(result);
                
                // Update progress
                const progress = ((i + 1) / cards.length) * 100;
                progressBar.style.width = progress + '%';
                
                if (delay > 0 && i < cards.length - 1) {
                    await sleep(delay);
                }
            }
            
            stopCheck();
        }

        async function startSiteCheck() {
            if (isChecking) return;
            
            const input = document.getElementById('sites-input').value.trim();
            if (!input) {
                alert('Please enter sites to check');
                return;
            }
            
            const sites = input.split('\n').filter(line => line.trim());
            
            isChecking = true;
            document.getElementById('start-site-check').disabled = true;
            document.getElementById('stop-site-check').disabled = false;
            
            // Reset stats
            stats = { total: 0, live: 0, dead: 0, credits: 0 };
            updateStats();
            
            const progressBar = document.getElementById('progress-fill');
            
            for (let i = 0; i < sites.length && isChecking; i++) {
                const site = sites[i].trim();
                
                const result = await checkSite(site);
                addResult(result);
                
                // Update progress
                const progress = ((i + 1) / sites.length) * 100;
                progressBar.style.width = progress + '%';
                
                await sleep(1000); // 1 second delay for sites
            }
            
            stopCheck();
        }

        function stopCheck() {
            isChecking = false;
            document.getElementById('start-card-check').disabled = false;
            document.getElementById('stop-card-check').disabled = true;
            document.getElementById('start-site-check').disabled = false;
            document.getElementById('stop-site-check').disabled = true;
        }

        function clearResults() {
            document.getElementById('results-container').innerHTML = `
                <div style="text-align: center; opacity: 0.5; padding: 40px;">
                    <i class="fas fa-search" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <p>Start a check to see results here...</p>
                </div>
            `;
            stats = { total: 0, live: 0, dead: 0, credits: 0 };
            updateStats();
            document.getElementById('progress-fill').style.width = '0%';
        }

        // Event listeners
        document.getElementById('start-card-check').addEventListener('click', startCardCheck);
        document.getElementById('stop-card-check').addEventListener('click', stopCheck);
        document.getElementById('clear-card-results').addEventListener('click', clearResults);
        document.getElementById('start-site-check').addEventListener('click', startSiteCheck);
        document.getElementById('stop-site-check').addEventListener('click', stopCheck);
        document.getElementById('clear-site-results').addEventListener('click', clearResults);

        // Tab switching for nav buttons
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.textContent.toLowerCase().replace(' checker', '-checker').replace(' ', '-');
                switchTab(tabName);
            });
        });
    </script>
</body>
</html>