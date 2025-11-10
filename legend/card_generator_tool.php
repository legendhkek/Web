<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'card_generator.php';
require_once 'bin_lookup.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

// Update presence
$db->updatePresence($userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Generator - LEGEND CHECKER</title>
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

        .badge {
            background: linear-gradient(135deg, #00ff88, #00d4ff);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
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

        .info-banner {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            color: #00ff88;
        }

        .tool-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .input-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
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

        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
        }

        select option {
            background: #1a1a2e;
            color: #ffffff;
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

        .result-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 2rem;
            border-left: 4px solid #00d4ff;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .cards-grid {
            display: grid;
            gap: 0.5rem;
            max-height: 400px;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
        }

        .card-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .card-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .copy-btn-small {
            background: rgba(0, 212, 255, 0.2);
            border: 1px solid rgba(0, 212, 255, 0.3);
            color: #00d4ff;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .copy-btn-small:hover {
            background: rgba(0, 212, 255, 0.3);
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.6);
            display: none;
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

        @media (max-width: 768px) {
            .page-title h1 {
                font-size: 2rem;
            }

            .tool-panel {
                padding: 1.5rem;
            }

            .input-row {
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
            <div class="badge">
                <i class="fas fa-gift"></i> FREE TOOL
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-magic"></i> Card Generator</h1>
            <p>Generate valid credit card numbers for testing</p>
        </div>

        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            <strong>FREE Tool</strong> - Generate unlimited cards with Luhn algorithm validation
        </div>

        <div class="tool-panel">
            <div class="input-group">
                <label for="binInput">
                    <i class="fas fa-hashtag"></i> BIN (Optional - Leave empty for random)
                    <small style="color: rgba(255,255,255,0.5);">(First 6-8 digits like 411111 or 544422)</small>
                </label>
                <input type="text" id="binInput" placeholder="411111">
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label for="countSelect">
                        <i class="fas fa-list-ol"></i> Number of Cards
                    </label>
                    <select id="countSelect">
                        <option value="10">10 Cards</option>
                        <option value="20">20 Cards</option>
                        <option value="50">50 Cards</option>
                        <option value="100">100 Cards</option>
                        <option value="500">500 Cards</option>
                        <option value="1000">1000 Cards</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="monthInput">
                        <i class="fas fa-calendar"></i> Month (Optional)
                    </label>
                    <input type="number" id="monthInput" placeholder="Auto" min="1" max="12">
                </div>

                <div class="input-group">
                    <label for="yearInput">
                        <i class="fas fa-calendar-alt"></i> Year (Optional)
                    </label>
                    <input type="number" id="yearInput" placeholder="Auto" min="2025" max="2035">
                </div>

                <div class="input-group">
                    <label for="cvvInput">
                        <i class="fas fa-lock"></i> CVV (Optional)
                    </label>
                    <input type="number" id="cvvInput" placeholder="Auto" min="0" max="999">
                </div>
            </div>

            <button class="btn" onclick="generateCards()">
                <i class="fas fa-magic"></i> Generate Cards
            </button>
        </div>

        <div class="loading" id="loadingIndicator">
            <div class="spinner"></div>
            <p>Generating cards...</p>
        </div>

        <div id="resultContainer"></div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        let generatedCards = [];

        async function generateCards() {
            const bin = document.getElementById('binInput').value.trim();
            const count = parseInt(document.getElementById('countSelect').value);
            const month = document.getElementById('monthInput').value.trim();
            const year = document.getElementById('yearInput').value.trim();
            const cvv = document.getElementById('cvvInput').value.trim();

            const loadingIndicator = document.getElementById('loadingIndicator');
            const resultContainer = document.getElementById('resultContainer');
            
            loadingIndicator.style.display = 'block';
            resultContainer.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('count', count);
                if (bin) formData.append('bin', bin);
                if (month) formData.append('month', month);
                if (year) formData.append('year', year);
                if (cvv) formData.append('cvv', cvv);

                const response = await fetch('card_generator_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                loadingIndicator.style.display = 'none';

                if (data.error) {
                    resultContainer.innerHTML = `
                        <div class="tool-panel" style="border-left-color: #ff4444;">
                            <p style="color: #ff4444; text-align: center;">
                                <i class="fas fa-exclamation-circle"></i> ${data.error}
                            </p>
                        </div>
                    `;
                    return;
                }

                generatedCards = data.cards;
                const cardInfo = data.card_info || {};

                const cardsHtml = generatedCards.map(card => `
                    <div class="card-item">
                        <code>${card}</code>
                        <button class="copy-btn-small" onclick="copyCard('${card}')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                `).join('');

                resultContainer.innerHTML = `
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #00d4ff; margin: 0;">
                                <i class="fas fa-check-circle"></i> Generated ${generatedCards.length} Cards
                            </h2>
                            <div>
                                <button class="btn" style="width: auto; padding: 0.75rem 1.5rem; margin-right: 0.5rem;" onclick="copyAllCards()">
                                    <i class="fas fa-copy"></i> Copy All
                                </button>
                                <button class="btn" style="width: auto; padding: 0.75rem 1.5rem;" onclick="downloadCards()">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                        
                        ${cardInfo.bank ? `
                        <div style="padding: 1rem; background: rgba(0, 212, 255, 0.1); border-radius: 10px; margin-bottom: 1.5rem;">
                            <strong style="color: #00d4ff;">Card Information:</strong><br>
                            🏦 Bank: ${cardInfo.bank || 'Unknown'}<br>
                            💳 Type: ${cardInfo.type || 'Unknown'}<br>
                            🌍 Country: ${cardInfo.country || 'Unknown'}
                        </div>
                        ` : ''}
                        
                        <div class="cards-grid">
                            ${cardsHtml}
                        </div>
                    </div>
                `;

            } catch (error) {
                loadingIndicator.style.display = 'none';
                resultContainer.innerHTML = `
                    <div class="tool-panel" style="border-left-color: #ff4444;">
                        <p style="color: #ff4444; text-align: center;">
                            <i class="fas fa-exclamation-circle"></i> Error: ${error.message}
                        </p>
                    </div>
                `;
            }
        }

        function copyCard(card) {
            navigator.clipboard.writeText(card).then(() => {
                // Visual feedback
                event.target.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    event.target.innerHTML = '<i class="fas fa-copy"></i> Copy';
                }, 2000);
            });
        }

        function copyAllCards() {
            const allCards = generatedCards.join('\n');
            navigator.clipboard.writeText(allCards).then(() => {
                alert('All cards copied to clipboard!');
            });
        }

        function downloadCards() {
            const allCards = generatedCards.join('\n');
            const blob = new Blob([allCards], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'generated_cards.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
