<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);
if (!$user) {
    header('Location: login.php');
    exit;
}

// Update presence
$db->updatePresence($userId);

// Get current site from rotation
$stripe_sites = SiteConfig::get('stripe_auth_sites', []);
$current_site_index = SiteConfig::get('stripe_auth_current_site_index', 0);
$current_site = !empty($stripe_sites) ? $stripe_sites[$current_site_index] : 'No sites configured';
?>

<!DOCTYPE html>
<html>
<head>
    <title>LEGEND CHECKER - Stripe Auth Checker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-color: #000000;
            --panel-bg: #1a2b49;
            --input-bg: #223041;
            --text-color: #00ffea;
            --placeholder-color: #95a5a6;
            
            --button-primary: #00e676;
            --button-danger: #ff073a;
            --button-warning: #e67e22;
            
            --button-hover-primary: #69f0ae;
            --button-hover-danger: #c0392b;
            --button-hover-warning: #f39c12;
            
            --link-color: #3498db;
            --border-color: #00bcd4;
            --gradient-border-light: #8e44ad;
            --gradient-border-dark: #3498db;
            --shadow-glow: rgba(0, 255, 234, 0.5);
            
            --status-live: #28a745;
            --status-dead: #dc3545;
            --status-checking: #f39c12;
            --status-error: #ffc107;

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
            position: relative;
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
            opacity: 0.6;
            pointer-events: none;
            z-index: 0;
        }

        .header-links {
            align-self: flex-start;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .back-to-dashboard {
            text-decoration: none;
            color: var(--link-color);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .back-to-dashboard:hover {
            color: var(--button-hover-primary);
        }

        .main-wrapper {
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .panel {
            background: var(--panel-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 255, 234, 0.2);
        }

        .panel-title {
            font-family: var(--font-heading);
            font-size: 1.5em;
            margin-bottom: 15px;
            color: var(--text-color);
            text-align: center;
        }

        .info-box {
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info-box strong {
            color: #00d4ff;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: bold;
        }

        textarea, input[type="text"] {
            width: 100%;
            padding: 12px;
            background: var(--input-bg);
            border: 2px solid var(--border-color);
            border-radius: 5px;
            color: var(--text-color);
            font-family: var(--font-mono);
            font-size: 14px;
            resize: vertical;
        }

        textarea:focus, input:focus {
            outline: none;
            border-color: var(--button-primary);
            box-shadow: 0 0 10px rgba(0, 230, 118, 0.3);
        }

        textarea::placeholder, input::placeholder {
            color: var(--placeholder-color);
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-family: var(--font-heading);
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-primary {
            background: var(--button-primary);
            color: #000;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--button-hover-primary);
            box-shadow: 0 0 15px rgba(0, 230, 118, 0.5);
        }

        .btn-danger {
            background: var(--button-danger);
            color: #fff;
        }

        .btn-danger:hover:not(:disabled) {
            background: var(--button-hover-danger);
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .results-section {
            margin-top: 30px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .counts {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .count-item {
            text-align: center;
        }

        .count-value {
            font-size: 2em;
            font-weight: bold;
            font-family: var(--font-heading);
        }

        .count-label {
            font-size: 0.9em;
            color: var(--placeholder-color);
        }

        .results-container {
            max-height: 600px;
            overflow-y: auto;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 5px;
        }

        .result-card {
            background: var(--input-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .result-card.live {
            border-color: var(--status-live);
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
        }

        .result-card.dead {
            border-color: var(--status-dead);
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.3);
        }

        .result-card.checking {
            border-color: var(--status-checking);
            animation: pulse 1.5s infinite;
        }

        .result-card.error {
            border-color: var(--status-error);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .result-status {
            font-weight: bold;
            font-size: 1.1em;
            padding: 5px 10px;
            border-radius: 5px;
        }

        .result-status.live {
            background: var(--status-live);
            color: #fff;
        }

        .result-status.dead {
            background: var(--status-dead);
            color: #fff;
        }

        .result-status.checking {
            background: var(--status-checking);
            color: #000;
        }

        .result-status.error {
            background: var(--status-error);
            color: #000;
        }

        .result-details {
            font-size: 0.9em;
            color: var(--placeholder-color);
            line-height: 1.6;
        }

        .loading-message {
            text-align: center;
            padding: 20px;
            color: var(--status-checking);
            font-weight: bold;
            display: none;
        }

        .credits-display {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.1em;
        }

        .credits-display strong {
            color: var(--button-primary);
        }

        @media (max-width: 768px) {
            .main-wrapper {
                padding: 5px;
            }
            
            .panel {
                padding: 15px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header-links">
        <a href="dashboard.php" class="back-to-dashboard">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <div class="credits-display">
            <i class="fas fa-coins"></i> Credits: <strong><?php echo number_format($user['credits']); ?></strong>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="panel">
            <h2 class="panel-title"><i class="fas fa-credit-card"></i> Stripe Auth Checker</h2>
            
            <div class="info-box">
                <strong>Current Site:</strong> <?php echo htmlspecialchars($current_site); ?><br>
                <small>Sites rotate automatically every 20 requests</small>
            </div>

            <form id="checkForm">
                <div class="form-group">
                    <label for="cardsInput">
                        <i class="fas fa-credit-card"></i> Cards (one per line)
                    </label>
                    <textarea 
                        id="cardsInput" 
                        rows="10" 
                        placeholder="4111111111111111|12|2025|123&#10;4111111111111112|01|2026|456"
                        required
                    ></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary" id="checkButton">
                        <i class="fas fa-play"></i> Check Cards
                    </button>
                    <button type="button" class="btn-danger" id="stopButton" disabled>
                        <i class="fas fa-stop"></i> Stop
                    </button>
                    <button type="button" class="btn-secondary" id="clearButton">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </form>

            <div class="loading-message" id="loadingMessage"></div>

            <div class="results-section">
                <div class="results-header">
                    <h3>Results</h3>
                    <div class="counts">
                        <div class="count-item">
                            <div class="count-value" id="liveCount" style="color: var(--status-live);">0</div>
                            <div class="count-label">Live</div>
                        </div>
                        <div class="count-item">
                            <div class="count-value" id="deadCount" style="color: var(--status-dead);">0</div>
                            <div class="count-label">Dead</div>
                        </div>
                        <div class="count-item">
                            <div class="count-value" id="errorCount" style="color: var(--status-error);">0</div>
                            <div class="count-label">Errors</div>
                        </div>
                    </div>
                </div>

                <div class="results-container" id="resultsContainer"></div>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const cardsInput = document.getElementById('cardsInput');
        const checkButton = document.getElementById('checkButton');
        const stopButton = document.getElementById('stopButton');
        const clearButton = document.getElementById('clearButton');
        const resultsContainer = document.getElementById('resultsContainer');
        const loadingMessage = document.getElementById('loadingMessage');
        const liveCount = document.getElementById('liveCount');
        const deadCount = document.getElementById('deadCount');
        const errorCount = document.getElementById('errorCount');

        let processing = false;
        let stopRequested = false;
        let activeChecks = 0;

        function createResultCard(data) {
            const card = document.createElement('div');
            card.className = `result-card ${data.status.toLowerCase()}`;
            
            const statusClass = data.status === 'LIVE' ? 'live' : 
                              data.status === 'DEAD' ? 'dead' : 
                              data.status === 'CHECKING' ? 'checking' : 'error';
            
            card.innerHTML = `
                <div class="result-header">
                    <div>
                        <strong>Card:</strong> <code>${data.card}</code>
                    </div>
                    <span class="result-status ${statusClass}">${data.status}</span>
                </div>
                <div class="result-details">
                    <div><strong>Site:</strong> ${data.site}</div>
                    <div><strong>Gateway:</strong> ${data.gateway}</div>
                    ${data.message ? `<div><strong>Message:</strong> ${data.message}</div>` : ''}
                    ${data.account_email ? `<div><strong>Account:</strong> ${data.account_email}</div>` : ''}
                    <div><strong>Time:</strong> ${data.time}</div>
                </div>
            `;
            
            return card;
        }

        async function checkCard(card) {
            if (stopRequested) return null;

            // Show checking status
            const checkingCard = createResultCard({
                card: card,
                site: 'Checking...',
                gateway: 'Stripe Auth',
                status: 'CHECKING',
                time: '...'
            });
            resultsContainer.prepend(checkingCard);

            try {
                const response = await fetch(`check_stripe_auth_ajax.php?cc=${encodeURIComponent(card)}`);
                const data = await response.json();

                // Remove checking card
                checkingCard.remove();

                if (data.error) {
                    const errorCard = createResultCard({
                        card: card,
                        site: data.site || 'N/A',
                        gateway: data.gateway || 'Stripe Auth',
                        status: 'ERROR',
                        message: data.message || 'Unknown error',
                        time: data.time || 'N/A'
                    });
                    resultsContainer.prepend(errorCard);
                    errorCount.textContent = parseInt(errorCount.textContent) + 1;
                    return data;
                }

                const resultCard = createResultCard(data);
                resultsContainer.prepend(resultCard);

                if (data.status === 'LIVE') {
                    liveCount.textContent = parseInt(liveCount.textContent) + 1;
                } else if (data.status === 'DEAD') {
                    deadCount.textContent = parseInt(deadCount.textContent) + 1;
                } else {
                    errorCount.textContent = parseInt(errorCount.textContent) + 1;
                }

                return data;
            } catch (error) {
                checkingCard.remove();
                const errorCard = createResultCard({
                    card: card,
                    site: 'N/A',
                    gateway: 'Stripe Auth',
                    status: 'ERROR',
                    message: error.message || 'Network error',
                    time: 'N/A'
                });
                resultsContainer.prepend(errorCard);
                errorCount.textContent = parseInt(errorCount.textContent) + 1;
                return null;
            }
        }

        async function processCards(cards) {
            for (const card of cards) {
                if (stopRequested) break;
                
                await checkCard(card.trim());
                
                // Small delay between checks
                await new Promise(resolve => setTimeout(resolve, 500));
            }
        }

        document.getElementById('checkForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            if (processing) return;

            const cardsRaw = cardsInput.value.trim();
            if (!cardsRaw) {
                alert('Please enter at least one card.');
                return;
            }

            const cards = cardsRaw.split('\n').map(c => c.trim()).filter(c => c.length > 0);

            // Clear previous results
            resultsContainer.innerHTML = '';
            liveCount.textContent = '0';
            deadCount.textContent = '0';
            errorCount.textContent = '0';

            processing = true;
            stopRequested = false;
            checkButton.disabled = true;
            stopButton.disabled = false;
            loadingMessage.style.display = 'block';
            loadingMessage.textContent = `Processing ${cards.length} card(s)...`;

            try {
                await processCards(cards);
            } catch (error) {
                console.error('Error processing cards:', error);
            }

            loadingMessage.style.display = 'none';
            checkButton.disabled = false;
            stopButton.disabled = true;
            processing = false;
            stopRequested = false;
        });

        stopButton.addEventListener('click', () => {
            stopRequested = true;
            loadingMessage.textContent = 'Stopping...';
            stopButton.disabled = true;
        });

        clearButton.addEventListener('click', () => {
            cardsInput.value = '';
            resultsContainer.innerHTML = '';
            liveCount.textContent = '0';
            deadCount.textContent = '0';
            errorCount.textContent = '0';
            loadingMessage.style.display = 'none';
        });
    </script>
</body>
</html>
