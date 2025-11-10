<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();
$user = $db->getUserByTelegramId($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Auth Checker - LEGEND CHECKER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --panel: rgba(15, 23, 42, 0.75);
            --accent: #7c3aed;
            --accent-second: #00e7ff;
            --border: rgba(148, 163, 184, 0.3);
            --text: #e2e8f0;
            --muted: rgba(226, 232, 240, 0.7);
            --live: #34d399;
            --dead: #f87171;
            --pending: #facc15;
            --font: 'Inter', sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: var(--font);
            background: radial-gradient(circle at top right, rgba(124, 58, 237, 0.25), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(14, 165, 233, 0.2), transparent 45%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            gap: 12px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            color: var(--muted);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: var(--text);
            border-color: rgba(124, 58, 237, 0.6);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
        }

        .credit-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.22), rgba(20, 184, 166, 0.22));
            border: 1px solid rgba(124, 58, 237, 0.35);
            font-weight: 600;
            color: var(--text);
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 24px;
            backdrop-filter: blur(12px);
            margin-bottom: 28px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.55);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        textarea {
            width: 100%;
            min-height: 200px;
            resize: vertical;
            background: rgba(15, 23, 42, 0.35);
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: var(--text);
            border-radius: 14px;
            padding: 16px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 15px;
            line-height: 1.5;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        textarea:focus {
            outline: none;
            border-color: rgba(124, 58, 237, 0.8);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.2);
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        label {
            font-weight: 600;
            color: var(--text);
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            background: rgba(15, 23, 42, 0.35);
            color: var(--text);
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: rgba(20, 184, 166, 0.7);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.2);
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 20px;
        }

        button {
            border: none;
            border-radius: 999px;
            padding: 12px 26px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #7c3aed, #06b6d4);
            color: #fff;
            box-shadow: 0 12px 30px rgba(124, 58, 237, 0.35);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 16px 40px rgba(124, 58, 237, 0.45);
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.2);
            color: var(--text);
            border: 1px solid rgba(148, 163, 184, 0.3);
        }

        .btn-danger {
            background: rgba(248, 113, 113, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(248, 113, 113, 0.5);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }

        .stat-card {
            padding: 18px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.22);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-live {
            color: var(--live);
        }

        .stat-dead {
            color: var(--dead);
        }

        .results-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .results-column {
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 16px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            max-height: 420px;
        }

        .results-column h3 {
            margin: 0 0 12px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .results-list {
            overflow-y: auto;
            padding-right: 6px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .result-card {
            border-radius: 14px;
            padding: 14px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.6);
            font-size: 14px;
            line-height: 1.5;
            position: relative;
        }

        .result-card.live {
            border-left: 4px solid var(--live);
        }

        .result-card.dead {
            border-left: 4px solid var(--dead);
        }

        .result-card .card-number {
            font-family: 'Fira Code', 'Courier New', monospace;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .result-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .progress-bar {
            height: 10px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.2);
            margin-top: 18px;
            overflow: hidden;
        }

        .progress-bar span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(135deg, #7c3aed, #06b6d4);
            width: 0%;
            transition: width 0.25s ease;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.22);
            font-size: 13px;
        }

        .status-badge.live {
            color: var(--live);
            border-color: rgba(52, 211, 153, 0.35);
        }

        .status-badge.dead {
            color: var(--dead);
            border-color: rgba(248, 113, 113, 0.35);
        }

        .current-site {
            margin-left: auto;
            font-size: 13px;
            color: var(--muted);
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: rgba(148, 163, 184, 0.7);
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            .current-site {
                display: block;
                margin: 10px 0 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <a href="tools.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Tools
            </a>
            <div class="credit-badge">
                <i class="fas fa-coins"></i>
                <span>Credits: <span id="creditCount"><?php echo number_format($user['credits'] ?? 0); ?></span></span>
            </div>
        </div>

        <div class="panel">
            <h1>Stripe Auth Checker</h1>
            <p class="subtitle">
                Rotate through a curated list of Stripe-authenticated Shopify style stores. One credit per check.
            </p>

            <form id="stripeAuthForm">
                <div class="form-grid">
                    <div class="input-group">
                        <label for="cardsInput">Cards (one per line, format: <code>number|MM|YYYY|CVV</code>)</label>
                        <textarea id="cardsInput" placeholder="4242424242424242|12|2026|123"></textarea>
                    </div>
                    <div class="input-group">
                        <label for="proxyInput">Proxy (optional, format: ip:port or user:pass@ip:port)</label>
                        <input type="text" id="proxyInput" placeholder="123.45.67.89:8080">
                        <div class="status-badge" id="statusSummary">
                            <i class="fas fa-spinner fa-spin"></i>
                            Waiting to start...
                        </div>
                        <div class="current-site" id="currentSiteLabel">Current site: —</div>
                    </div>
                </div>

                <div class="button-row">
                    <button type="submit" class="btn-primary" id="startButton">
                        <i class="fas fa-play"></i> Start Checking
                    </button>
                    <button type="button" class="btn-danger" id="stopButton" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button type="button" class="btn-secondary" id="clearButton">
                        <i class="fas fa-broom"></i> Clear
                    </button>
                </div>

                <div class="progress-bar">
                    <span id="progressBar"></span>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Cards</div>
                    <div class="stat-value" id="totalCount">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Processed</div>
                    <div class="stat-value" id="processedCount">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Live</div>
                    <div class="stat-value stat-live" id="liveCount">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Dead</div>
                    <div class="stat-value stat-dead" id="deadCount">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Elapsed</div>
                    <div class="stat-value" id="elapsedTime">0s</div>
                </div>
            </div>

            <div class="results-wrapper">
                <div class="results-column">
                    <h3><i class="fas fa-check-circle" style="color: var(--live);"></i> Live</h3>
                    <div class="results-list" id="liveResults"></div>
                </div>
                <div class="results-column">
                    <h3><i class="fas fa-xmark-circle" style="color: var(--dead);"></i> Dead</h3>
                    <div class="results-list" id="deadResults"></div>
                </div>
            </div>
        </div>

        <div class="footer">
            Stripe Auth list rotates automatically every 20 checks per site. Owners can manage the site pool from the admin panel.
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        (function () {
            const form = document.getElementById('stripeAuthForm');
            const cardsInput = document.getElementById('cardsInput');
            const proxyInput = document.getElementById('proxyInput');
            const startButton = document.getElementById('startButton');
            const stopButton = document.getElementById('stopButton');
            const clearButton = document.getElementById('clearButton');
            const statusSummary = document.getElementById('statusSummary');
            const currentSiteLabel = document.getElementById('currentSiteLabel');
            const progressBar = document.getElementById('progressBar');
            const liveResults = document.getElementById('liveResults');
            const deadResults = document.getElementById('deadResults');

            const totalCountEl = document.getElementById('totalCount');
            const processedCountEl = document.getElementById('processedCount');
            const liveCountEl = document.getElementById('liveCount');
            const deadCountEl = document.getElementById('deadCount');
            const elapsedTimeEl = document.getElementById('elapsedTime');
            const creditCountEl = document.getElementById('creditCount');

            let state = {
                queue: [],
                processed: 0,
                live: 0,
                dead: 0,
                processing: false,
                stopRequested: false,
                startTime: null,
            };

            function resetState() {
                state = {
                    queue: [],
                    processed: 0,
                    live: 0,
                    dead: 0,
                    processing: false,
                    stopRequested: false,
                    startTime: null,
                };
                totalCountEl.textContent = '0';
                processedCountEl.textContent = '0';
                liveCountEl.textContent = '0';
                deadCountEl.textContent = '0';
                elapsedTimeEl.textContent = '0s';
                progressBar.style.width = '0%';
                liveResults.innerHTML = '';
                deadResults.innerHTML = '';
                currentSiteLabel.textContent = 'Current site: —';
                statusSummary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Waiting to start...';
                statusSummary.classList.remove('live', 'dead');
            }

            function updateProgress() {
                const total = state.queue.length + state.processed;
                totalCountEl.textContent = String(total);
                processedCountEl.textContent = String(state.processed);
                liveCountEl.textContent = String(state.live);
                deadCountEl.textContent = String(state.dead);

                const ratio = total > 0 ? (state.processed / total) * 100 : 0;
                progressBar.style.width = `${ratio}%`;

                if (state.startTime) {
                    const elapsedSeconds = Math.floor((Date.now() - state.startTime) / 1000);
                    const minutes = Math.floor(elapsedSeconds / 60);
                    const seconds = elapsedSeconds % 60;
                    elapsedTimeEl.textContent = minutes > 0
                        ? `${minutes}m ${seconds}s`
                        : `${seconds}s`;
                }
            }

            function appendResult(container, data, type) {
                const card = document.createElement('div');
                card.className = `result-card ${type}`;
                const statusIcon = type === 'live' ? '✅' : '❌';
                const statusText = type === 'live' ? 'LIVE' : 'DEAD';

                card.innerHTML = `
                    <div class="card-number">${data.card}</div>
                    <div>${statusIcon} ${data.message || data.status || 'No response message'}</div>
                    <div class="result-meta">
                        <span><i class="fas fa-store"></i> ${data.site}</span>
                        <span><i class="fas fa-clock"></i> ${(data.duration_ms / 1000).toFixed(2)}s</span>
                        <span><i class="fas fa-shield"></i> ${statusText}</span>
                        ${data.account_email ? `<span><i class="fas fa-envelope"></i> ${data.account_email}</span>` : ''}
                    </div>
                `;
                container.prepend(card);
            }

            async function processQueue() {
                if (state.processing) return;
                state.processing = true;
                state.startTime = Date.now();
                updateProgress();

                for (let i = 0; i < state.queue.length; i++) {
                    if (state.stopRequested) break;
                    const card = state.queue[i];
                    statusSummary.innerHTML = `<i class="fas fa-gear fa-spin"></i> Checking ${card}`;
                    statusSummary.classList.remove('live', 'dead');

                    try {
                        const response = await fetch('stripe_auth_ajax.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                card,
                                proxy: proxyInput.value.trim() || undefined,
                            }),
                        });

                        const result = await response.json();
                        state.processed += 1;
                        if (result.success || result.status_label === 'LIVE') {
                            state.live += 1;
                            appendResult(liveResults, result, 'live');
                            statusSummary.innerHTML = `<i class="fas fa-check-circle"></i> LIVE - ${result.message || result.status}`;
                            statusSummary.classList.add('live');
                            statusSummary.classList.remove('dead');
                        } else {
                            state.dead += 1;
                            appendResult(deadResults, result, 'dead');
                            statusSummary.innerHTML = `<i class="fas fa-xmark-circle"></i> DEAD - ${result.message || result.status}`;
                            statusSummary.classList.add('dead');
                            statusSummary.classList.remove('live');
                        }

                        if (result.remaining_credits !== undefined && !isNaN(result.remaining_credits)) {
                            creditCountEl.textContent = Number(result.remaining_credits).toLocaleString();
                        }

                        if (result.site) {
                            currentSiteLabel.textContent = `Current site: ${result.site}`;
                        }
                    } catch (error) {
                        state.processed += 1;
                        state.dead += 1;
                        appendResult(deadResults, {
                            card,
                            message: 'Network error processing card.',
                            status: 'NETWORK_ERROR',
                            site: 'N/A',
                            duration_ms: 0,
                        }, 'dead');
                        statusSummary.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Error - ${error.message}`;
                        statusSummary.classList.add('dead');
                        statusSummary.classList.remove('live');
                    }

                    updateProgress();
                }

                statusSummary.innerHTML = state.stopRequested
                    ? '<i class="fas fa-stop-circle"></i> Stopped by user'
                    : '<i class="fas fa-flag-checkered"></i> Completed';
                statusSummary.classList.remove('live', 'dead');
                currentSiteLabel.textContent = 'Current site: —';

                state.processing = false;
                state.stopRequested = false;
                startButton.disabled = false;
                stopButton.disabled = true;
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                if (state.processing) return;

                const cards = cardsInput.value
                    .split('\n')
                    .map((item) => item.trim())
                    .filter((item) => item.length > 0);

                if (cards.length === 0) {
                    alert('Please enter at least one card.');
                    return;
                }

                if (!confirm(`Start checking ${cards.length} card(s)? This will use ${cards.length} credit(s).`)) {
                    return;
                }

                state.queue = cards;
                state.processed = 0;
                state.live = 0;
                state.dead = 0;
                state.stopRequested = false;

                totalCountEl.textContent = String(cards.length);
                processedCountEl.textContent = '0';
                liveCountEl.textContent = '0';
                deadCountEl.textContent = '0';
                elapsedTimeEl.textContent = '0s';
                progressBar.style.width = '0%';

                startButton.disabled = true;
                stopButton.disabled = false;
                statusSummary.innerHTML = '<i class="fas fa-gear fa-spin"></i> Starting checks...';
                statusSummary.classList.remove('live', 'dead');

                processQueue();
            });

            stopButton.addEventListener('click', () => {
                if (!state.processing) return;
                state.stopRequested = true;
                stopButton.disabled = true;
                statusSummary.innerHTML = '<i class="fas fa-stop-circle"></i> Stopping after current card...';
            });

            clearButton.addEventListener('click', () => {
                if (state.processing) {
                    alert('Stop the current run before clearing.');
                    return;
                }
                cardsInput.value = '';
                proxyInput.value = '';
                resetState();
            });

            resetState();

            setInterval(() => {
                fetch('api/presence.php', { method: 'POST' }).catch(() => {});
            }, 120000);
        })();
    </script>
</body>
</html>
