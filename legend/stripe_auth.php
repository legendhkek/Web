<?php
$cards_input_raw = isset($_POST['cards_input']) ? trim($_POST['cards_input']) : '';
$proxy_input_raw = isset($_POST['proxy_input']) ? trim($_POST['proxy_input']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEGEND CHECKER - Stripe Auth</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #000000;
            --panel-bg: #1a2b49;
            --input-bg: #223041;
            --text-color: #00ffea;
            --placeholder-color: #95a5a6;
            --accent: #00e676;
            --accent-secondary: #7c3aed;
            --danger: #ff073a;
            --warning: #f39c12;
            --border-color: #00bcd4;
            --shadow-glow: rgba(0, 255, 234, 0.35);
            --status-live: #17a2b8;
            --status-dead: #dc3545;
            --status-error: #f39c12;
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
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 10px;
            overflow-y: auto;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(0, 255, 234, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(83, 52, 131, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(15, 52, 96, 0.1) 0%, transparent 50%),
                linear-gradient(to right, rgba(0,255,234,0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0,255,234,0.03) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 100% 100%, 40px 40px, 40px 40px;
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }

        .header-links {
            align-self: flex-start;
            width: 100%;
            max-width: 1100px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .header-links a {
            text-decoration: none;
            color: #3498db;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }

        .header-links a:hover {
            color: var(--accent);
            text-shadow: 0 0 15px var(--accent);
        }

        .main-wrapper {
            width: 100%;
            max-width: 1100px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .panel {
            background: var(--panel-bg);
            padding: 24px;
            border-radius: 14px;
            border: 1px solid rgba(0, 255, 234, 0.25);
            box-shadow: 0 0 25px var(--shadow-glow);
            position: relative;
        }

        .panel::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(0, 221, 255, 0.35), rgba(124, 58, 237, 0.35));
            filter: blur(18px);
            opacity: 0.2;
            z-index: -1;
        }

        h2 {
            font-family: var(--font-heading);
            font-size: 2.2rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
            text-align: center;
            text-shadow: 0 0 15px var(--shadow-glow);
        }

        textarea {
            width: 100%;
            background: var(--input-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-color);
            font-family: var(--font-mono);
            padding: 14px;
            margin: 10px 0 16px;
            min-height: 140px;
            resize: vertical;
            font-size: 15px;
            transition: border 0.3s ease, box-shadow 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.3), 0 0 20px rgba(0, 230, 118, 0.35);
        }

        textarea::placeholder {
            color: var(--placeholder-color);
            opacity: 0.7;
        }

        .input-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 12px;
        }

        .input-row label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.85);
        }

        input[type="number"] {
            width: 80px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            color: var(--text-color);
            font-family: var(--font-mono);
            font-size: 1rem;
            text-align: center;
        }

        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        button {
            flex: 1;
            min-width: 150px;
            border: none;
            padding: 14px 18px;
            border-radius: 10px;
            font-family: var(--font-heading);
            font-size: 1rem;
            text-transform: uppercase;
            color: var(--bg-color);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.3s ease, background 0.3s ease;
        }

        button.start-button {
            background: var(--accent);
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.4);
        }

        button.stop-button {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 0 20px rgba(255, 7, 58, 0.4);
        }

        button.clear-button {
            background: var(--warning);
            color: #fff;
            box-shadow: 0 0 20px rgba(243, 156, 18, 0.4);
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        button:disabled {
            background: #555;
            color: rgba(255, 255, 255, 0.6);
            cursor: not-allowed;
            box-shadow: none;
        }

        .stats-panel h3,
        .results-panel h3 {
            font-family: var(--font-heading);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 20px;
            text-align: left;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
        }

        .stat-item {
            background: var(--input-bg);
            border: 1px solid rgba(0, 255, 234, 0.25);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            box-shadow: 0 0 15px rgba(0, 255, 234, 0.15);
        }

        .stat-item span.label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.8);
            letter-spacing: 0.5px;
        }

        .stat-item span.value {
            font-size: 1.8rem;
            font-family: var(--font-heading);
            text-shadow: 0 0 12px rgba(0, 255, 234, 0.4);
        }

        .stat-live .value { color: var(--status-live); text-shadow: 0 0 12px rgba(23, 162, 184, 0.5); }
        .stat-dead .value { color: var(--status-dead); text-shadow: 0 0 12px rgba(220, 53, 69, 0.5); }
        .stat-error .value { color: var(--status-error); text-shadow: 0 0 12px rgba(243, 156, 18, 0.5); }

        .progress-bar {
            margin-top: 18px;
        }

        .progress-bar-track {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(0,255,234,0.25);
        }

        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent), #00bcd4);
            transition: width 0.3s ease;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-top: 6px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .result-tabs {
            display: flex;
            border-bottom: 2px solid rgba(0, 255, 234, 0.2);
            margin-bottom: 18px;
        }

        .tab-button {
            background: var(--input-bg);
            border: none;
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            font-family: var(--font-heading);
            text-transform: uppercase;
            letter-spacing: 0.7px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            transition: background 0.3s ease, color 0.3s ease;
            margin-right: 5px;
        }

        .tab-button.active {
            background: var(--border-color);
            color: #fff;
            border-bottom: 2px solid var(--accent);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .results-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .results-toolbar button {
            flex: none;
            min-width: auto;
            padding: 8px 14px;
            font-size: 0.85rem;
            border-radius: 8px;
            background: rgba(0, 221, 255, 0.2);
            color: #fff;
        }

        .results-container {
            max-height: 420px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .results-container::-webkit-scrollbar {
            width: 8px;
        }

        .results-container::-webkit-scrollbar-track {
            background: var(--input-bg);
            border-radius: 10px;
        }

        .results-container::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        .result-card {
            background: var(--input-bg);
            border-radius: 10px;
            border-left: 5px solid var(--border-color);
            padding: 14px 16px;
            margin-bottom: 12px;
            position: relative;
            box-shadow: 0 0 12px rgba(0, 255, 234, 0.1);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .result-card.live { border-left-color: var(--status-live); }
        .result-card.dead { border-left-color: var(--status-dead); }
        .result-card.error { border-left-color: var(--status-error); }

        .result-card strong {
            color: rgba(255, 255, 255, 0.75);
            display: inline-block;
            min-width: 110px;
        }

        .copy-entry {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 255, 234, 0.25);
            border: none;
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.3s ease;
        }

        .copy-entry:hover {
            background: rgba(0, 255, 234, 0.4);
        }

        .copy-entry.copied {
            background: var(--accent);
            color: #000;
        }

        .loading-banner {
            display: none;
            margin: 0 auto;
            margin-top: 24px;
            padding: 14px 20px;
            background: rgba(0, 255, 234, 0.15);
            border: 1px solid rgba(0, 255, 234, 0.35);
            border-radius: 12px;
            width: 320px;
            max-width: 90%;
            text-align: center;
            font-family: var(--font-heading);
            letter-spacing: 1px;
            box-shadow: 0 0 18px rgba(0, 255, 234, 0.25);
        }

        @media (max-width: 768px) {
            body {
                padding: 6px;
            }
            h2 {
                font-size: 1.8rem;
            }
            .button-group {
                flex-direction: column;
            }
            button {
                width: 100%;
            }
            .results-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .copy-entry {
                position: static;
                align-self: flex-end;
                margin-top: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="header-links">
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <a href="tools.php"><i class="fas fa-tools"></i> All Tools</a>
    </div>

    <div class="main-wrapper">
        <div class="panel">
            <h2>Stripe Auth Checker</h2>
            <form id="stripeAuthForm">
                <textarea id="cardsInput" name="cards_input" placeholder="Enter cards (one per line, format: 4111111111111111|12|2025|123)"><?php echo htmlspecialchars($cards_input_raw); ?></textarea>
                <textarea id="proxyInput" name="proxy_input" placeholder="Enter proxy (ip:port:user:pass) - optional"><?php echo htmlspecialchars($proxy_input_raw); ?></textarea>
                <div class="input-row">
                    <label>
                        <input type="checkbox" id="useMyIpCheckbox">
                        Use my IP (ignore proxy)
                    </label>
                    <label>
                        Concurrency:
                        <input type="number" id="concurrencyLimit" value="3" min="1" max="10">
                    </label>
                </div>
                <div class="button-group">
                    <button type="submit" class="start-button" id="startButton">Start</button>
                    <button type="button" class="stop-button" id="stopButton" disabled>Stop</button>
                    <button type="button" class="clear-button" id="clearButton">Clear</button>
                </div>
            </form>
        </div>

        <div class="panel stats-panel">
            <h3>Session Overview</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="label">Total Cards</span>
                    <span class="value" id="totalCount">0</span>
                </div>
                <div class="stat-item stat-live">
                    <span class="label">Live</span>
                    <span class="value" id="liveCount">0</span>
                </div>
                <div class="stat-item stat-dead">
                    <span class="label">Dead</span>
                    <span class="value" id="deadCount">0</span>
                </div>
                <div class="stat-item stat-error">
                    <span class="label">Errors</span>
                    <span class="value" id="errorCount">0</span>
                </div>
                <div class="stat-item">
                    <span class="label">Active Checks</span>
                    <span class="value" id="activeChecksCount">0</span>
                </div>
                <div class="stat-item">
                    <span class="label">Pending</span>
                    <span class="value" id="pendingCount">0</span>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="progressBar"></div>
                </div>
                <div class="progress-info">
                    <span>Progress: <span id="progressPercent">0%</span></span>
                    <span>Elapsed: <span id="elapsedTime">0s</span></span>
                    <span>ETA: <span id="estimatedTime">--</span></span>
                </div>
            </div>
        </div>

        <div class="panel results-panel">
            <h3>Results</h3>
            <div class="result-tabs">
                <button class="tab-button active" data-tab="live">Live</button>
                <button class="tab-button" data-tab="dead">Dead</button>
                <button class="tab-button" data-tab="error">Errors</button>
            </div>

            <div id="liveTab" class="tab-content active">
                <div class="results-toolbar">
                    <button type="button" class="copy-category" data-target="liveResults">Copy All Live</button>
                    <button type="button" class="download-category" data-target="liveResults" data-filename="stripe_auth_live.txt">Download</button>
                </div>
                <div id="liveResults" class="results-container"></div>
            </div>

            <div id="deadTab" class="tab-content">
                <div class="results-toolbar">
                    <button type="button" class="copy-category" data-target="deadResults">Copy All Dead</button>
                    <button type="button" class="download-category" data-target="deadResults" data-filename="stripe_auth_dead.txt">Download</button>
                </div>
                <div id="deadResults" class="results-container"></div>
            </div>

            <div id="errorTab" class="tab-content">
                <div class="results-toolbar">
                    <button type="button" class="copy-category" data-target="errorResults">Copy All Errors</button>
                    <button type="button" class="download-category" data-target="errorResults" data-filename="stripe_auth_errors.txt">Download</button>
                </div>
                <div id="errorResults" class="results-container"></div>
            </div>
        </div>
    </div>

    <div class="loading-banner" id="loadingBanner">
        Processing...
    </div>

    <script>
        const form = document.getElementById('stripeAuthForm');
        const cardsInput = document.getElementById('cardsInput');
        const proxyInput = document.getElementById('proxyInput');
        const useMyIpCheckbox = document.getElementById('useMyIpCheckbox');
        const concurrencyInput = document.getElementById('concurrencyLimit');
        const startButton = document.getElementById('startButton');
        const stopButton = document.getElementById('stopButton');
        const clearButton = document.getElementById('clearButton');
        const loadingBanner = document.getElementById('loadingBanner');

        const totalCountEl = document.getElementById('totalCount');
        const liveCountEl = document.getElementById('liveCount');
        const deadCountEl = document.getElementById('deadCount');
        const errorCountEl = document.getElementById('errorCount');
        const activeChecksEl = document.getElementById('activeChecksCount');
        const pendingCountEl = document.getElementById('pendingCount');
        const progressBarEl = document.getElementById('progressBar');
        const progressPercentEl = document.getElementById('progressPercent');
        const elapsedTimeEl = document.getElementById('elapsedTime');
        const estimatedTimeEl = document.getElementById('estimatedTime');

        const liveResultsContainer = document.getElementById('liveResults');
        const deadResultsContainer = document.getElementById('deadResults');
        const errorResultsContainer = document.getElementById('errorResults');

        let cardQueue = [];
        let allCards = [];
        let processing = false;
        let stopRequested = false;
        let activeChecks = 0;
        let completedCards = 0;
        let startTimestamp = 0;
        let performanceTimer = null;

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return str
                .toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateCounts() {
            totalCountEl.textContent = allCards.length;
            pendingCountEl.textContent = cardQueue.length + activeChecks;
            activeChecksEl.textContent = activeChecks;

            if (allCards.length > 0) {
                const progress = ((completedCards / allCards.length) * 100).toFixed(1);
                progressBarEl.style.width = `${progress}%`;
                progressPercentEl.textContent = `${progress}%`;
            } else {
                progressBarEl.style.width = '0%';
                progressPercentEl.textContent = '0%';
            }
        }

        function resetStats() {
            liveCountEl.textContent = '0';
            deadCountEl.textContent = '0';
            errorCountEl.textContent = '0';
            totalCountEl.textContent = '0';
            pendingCountEl.textContent = '0';
            activeChecksEl.textContent = '0';
            progressBarEl.style.width = '0%';
            progressPercentEl.textContent = '0%';
            elapsedTimeEl.textContent = '0s';
            estimatedTimeEl.textContent = '--';
            completedCards = 0;
            startTimestamp = 0;
        }

        function createResultCard(data, statusType) {
            const div = document.createElement('div');
            div.className = `result-card ${statusType.toLowerCase()}`;

            const message = data.message || data.status || 'No response';
            const entry = `
                <div><strong>Card:</strong> ${escapeHtml(data.card)}</div>
                <div><strong>Site:</strong> ${escapeHtml(data.site || 'N/A')}</div>
                <div><strong>Status:</strong> ${escapeHtml(data.status || 'N/A')}</div>
                <div><strong>Message:</strong> ${escapeHtml(message)}</div>
                <div><strong>Email:</strong> ${escapeHtml(data.account_email || 'N/A')}</div>
                <div><strong>PM ID:</strong> ${escapeHtml(data.pm_id || 'N/A')}</div>
                <div><strong>Duration:</strong> ${escapeHtml(data.duration || 'N/A')}</div>
                <div><strong>Credits:</strong> ${escapeHtml(data.credits_deducted != null ? data.credits_deducted : 0)}</div>
                <div><strong>Remaining:</strong> ${escapeHtml(data.remaining_credits != null ? data.remaining_credits : '')}</div>
            `;
            div.innerHTML = entry;

            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-entry';
            copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
            copyBtn.addEventListener('click', async () => {
                const lines = [
                    `Card: ${data.card}`,
                    `Site: ${data.site || 'N/A'}`,
                    `Status: ${data.status || 'N/A'}`,
                    `Message: ${message}`,
                    `Email: ${data.account_email || 'N/A'}`,
                    `PM ID: ${data.pm_id || 'N/A'}`,
                    `Duration: ${data.duration || 'N/A'}`,
                    `Credits: ${data.credits_deducted != null ? data.credits_deducted : 0}`,
                    `Remaining: ${data.remaining_credits != null ? data.remaining_credits : 'N/A'}`
                ];
                try {
                    await navigator.clipboard.writeText(lines.join('\n'));
                    copyBtn.classList.add('copied');
                    copyBtn.textContent = 'Copied!';
                    setTimeout(() => {
                        copyBtn.classList.remove('copied');
                        copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
                    }, 1800);
                } catch (err) {
                    alert('Clipboard copy failed. Please copy manually.');
                }
            });
            div.appendChild(copyBtn);

            return div;
        }

        function removeCardFromInput(card) {
            const lines = cardsInput.value.split('\n').map(line => line.trim());
            const index = lines.indexOf(card);
            if (index !== -1) {
                lines.splice(index, 1);
                cardsInput.value = lines.join('\n');
            }
        }

        async function processCard(card) {
            if (stopRequested) {
                activeChecks--;
                updateCounts();
                return;
            }

            activeChecks++;
            updateCounts();
            removeCardFromInput(card);

            const payload = new URLSearchParams();
            payload.append('cc', card);

            if (useMyIpCheckbox.checked) {
                payload.append('use_my_ip', '1');
            } else if (proxyInput.value.trim() !== '') {
                payload.append('proxy', proxyInput.value.trim());
            }

            try {
                const response = await fetch('stripe_auth_ajax.php', {
                    method: 'POST',
                    body: payload
                });

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (jsonError) {
                    data = {
                        success: false,
                        status: 'INVALID_JSON',
                        message: 'Unexpected response from server.',
                        raw: text
                    };
                }

                const statusType = (data.ui_status_type || (data.success ? 'LIVE' : 'ERROR')).toUpperCase();
                const container = statusType === 'LIVE'
                    ? liveResultsContainer
                    : (statusType === 'DEAD' ? deadResultsContainer : errorResultsContainer);

                if (statusType === 'LIVE') {
                    liveCountEl.textContent = (parseInt(liveCountEl.textContent, 10) + 1).toString();
                } else if (statusType === 'DEAD') {
                    deadCountEl.textContent = (parseInt(deadCountEl.textContent, 10) + 1).toString();
                } else {
                    errorCountEl.textContent = (parseInt(errorCountEl.textContent, 10) + 1).toString();
                }

                const resultData = {
                    card,
                    site: data.site || data.domain || 'N/A',
                    status: data.status || statusType,
                    message: data.message || '',
                    account_email: data.account_email || null,
                    pm_id: data.pm_id || null,
                    duration: data.duration || null,
                    credits_deducted: data.credits_deducted ?? null,
                    remaining_credits: data.remaining_credits ?? null
                };

                container.prepend(createResultCard(resultData, statusType));
            } catch (error) {
                errorResultsContainer.prepend(createResultCard({
                    card,
                    site: 'N/A',
                    status: 'REQUEST_FAILED',
                    message: error.message || 'Failed to fetch',
                    account_email: null,
                    pm_id: null,
                    duration: null,
                    credits_deducted: null,
                    remaining_credits: null
                }, 'ERROR'));

                errorCountEl.textContent = (parseInt(errorCountEl.textContent, 10) + 1).toString();
            } finally {
                activeChecks--;
                completedCards++;
                updateCounts();

                if (!stopRequested && cardQueue.length > 0) {
                    const nextCard = cardQueue.shift();
                    processCard(nextCard);
                } else if (activeChecks === 0) {
                    finishProcessing();
                }
            }
        }

        function startPerformanceTimer() {
            startTimestamp = Date.now();
            performanceTimer = setInterval(() => {
                const elapsedSeconds = (Date.now() - startTimestamp) / 1000;
                elapsedTimeEl.textContent = `${elapsedSeconds.toFixed(1)}s`;

                if (completedCards > 0 && allCards.length > 0) {
                    const rate = completedCards / elapsedSeconds;
                    const remaining = allCards.length - completedCards;
                    if (rate > 0) {
                        const eta = remaining / rate;
                        estimatedTimeEl.textContent = `${eta.toFixed(1)}s`;
                    } else {
                        estimatedTimeEl.textContent = '--';
                    }
                }
            }, 500);
        }

        function stopPerformanceTimer() {
            if (performanceTimer) {
                clearInterval(performanceTimer);
                performanceTimer = null;
            }
        }

        function finishProcessing() {
            processing = false;
            stopRequested = false;
            startButton.disabled = false;
            stopButton.disabled = true;
            loadingBanner.style.display = 'none';
            stopPerformanceTimer();
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (processing) return;

            const cards = cardsInput.value
                .split('\n')
                .map(line => line.trim())
                .filter(line => line.length > 0);

            if (cards.length === 0) {
                alert('Please enter at least one card.');
                return;
            }

            const concurrency = Math.min(
                Math.max(parseInt(concurrencyInput.value, 10) || 1, 1),
                10
            );

            processing = true;
            stopRequested = false;
            completedCards = 0;
            activeChecks = 0;
            cardQueue = [...cards];
            allCards = [...cards];

            liveResultsContainer.innerHTML = '';
            deadResultsContainer.innerHTML = '';
            errorResultsContainer.innerHTML = '';

            resetStats();
            updateCounts();

            startButton.disabled = true;
            stopButton.disabled = false;
            loadingBanner.style.display = 'block';
            loadingBanner.textContent = `Processing ${cards.length} cards...`;

            startPerformanceTimer();

            for (let i = 0; i < concurrency && cardQueue.length > 0; i++) {
                const nextCard = cardQueue.shift();
                processCard(nextCard);
            }
        });

        stopButton.addEventListener('click', () => {
            if (!processing) return;
            stopRequested = true;
            stopButton.disabled = true;
            loadingBanner.textContent = 'Stopping...';
        });

        clearButton.addEventListener('click', () => {
            if (processing) {
                alert('Please stop processing before clearing.');
                return;
            }
            cardsInput.value = '';
            liveResultsContainer.innerHTML = '';
            deadResultsContainer.innerHTML = '';
            errorResultsContainer.innerHTML = '';
            resetStats();
            updateCounts();
            loadingBanner.style.display = 'none';
        });

        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                if (button.classList.contains('active')) return;
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const tab = button.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                if (tab === 'live') document.getElementById('liveTab').classList.add('active');
                if (tab === 'dead') document.getElementById('deadTab').classList.add('active');
                if (tab === 'error') document.getElementById('errorTab').classList.add('active');
            });
        });

        document.querySelectorAll('.copy-category').forEach(button => {
            button.addEventListener('click', async () => {
                const targetId = button.dataset.target;
                const container = document.getElementById(targetId);
                const cards = Array.from(container.querySelectorAll('.result-card'));
                if (cards.length === 0) {
                    alert('No entries to copy.');
                    return;
                }
                const textBlocks = cards.map(card => card.innerText.replace(/\n+/g, '\n'));
                try {
                    await navigator.clipboard.writeText(textBlocks.join('\n\n'));
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    setTimeout(() => button.textContent = originalText, 1500);
                } catch (err) {
                    alert('Clipboard copy failed. Please copy manually.');
                }
            });
        });

        document.querySelectorAll('.download-category').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.target;
                const container = document.getElementById(targetId);
                const cards = Array.from(container.querySelectorAll('.result-card'));
                if (cards.length === 0) {
                    alert('No entries to download.');
                    return;
                }
                const textBlocks = cards.map(card => card.innerText.replace(/\n+/g, '\n'));
                const blob = new Blob([textBlocks.join('\n\n')], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = button.dataset.filename || 'results.txt';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });
        });
    </script>
</body>
</html>
