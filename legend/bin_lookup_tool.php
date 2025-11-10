<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
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
    <title>BIN Lookup - LEGEND CHECKER</title>
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
            max-width: 1000px;
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

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        input[type="text"] {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', monospace;
            font-size: 1rem;
        }

        input[type="text"]:focus {
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

        .result-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 2rem;
            border-left: 4px solid #00d4ff;
            animation: slideIn 0.3s ease;
        }

        .result-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .result-row:last-child {
            border-bottom: none;
        }

        .result-label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .result-value {
            font-weight: 600;
            color: #00d4ff;
            font-size: 1.1rem;
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

        @media (max-width: 768px) {
            .page-title h1 {
                font-size: 2rem;
            }

            .tool-panel {
                padding: 1.5rem;
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
            <h1><i class="fas fa-search"></i> BIN Lookup</h1>
            <p>Get detailed information about any credit card BIN</p>
        </div>

        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            <strong>FREE Tool</strong> - No credits required! Check unlimited BINs
        </div>

        <div class="tool-panel">
            <div class="input-group">
                <label for="binInput">
                    <i class="fas fa-credit-card"></i> Enter BIN or Full Card Number
                    <small style="color: rgba(255,255,255,0.5);">(First 6-8 digits or full card like 4111111111111111|12|2025|123)</small>
                </label>
                <input type="text" id="binInput" placeholder="411111 or 4111111111111111|12|2025|123">
            </div>

            <button class="btn" onclick="lookupBin()">
                <i class="fas fa-search"></i> Lookup BIN
            </button>
        </div>

        <div class="loading" id="loadingIndicator">
            <div class="spinner"></div>
            <p>Looking up BIN information...</p>
        </div>

        <div id="resultContainer"></div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        async function lookupBin() {
            const binInput = document.getElementById('binInput').value.trim();
            
            if (!binInput) {
                alert('Please enter a BIN or card number!');
                return;
            }

            const loadingIndicator = document.getElementById('loadingIndicator');
            const resultContainer = document.getElementById('resultContainer');
            
            loadingIndicator.style.display = 'block';
            resultContainer.innerHTML = '';

            try {
                const response = await fetch('bin_lookup_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'bin=' + encodeURIComponent(binInput)
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

                const cardType = data.type || 'Unknown';
                const brand = data.brand || 'Unknown';
                const bank = data.bank || 'Unknown';
                const country = data.country || 'Unknown';
                const countryCode = data.country_code || '';
                const level = data.level || 'Unknown';
                const bin = data.bin || binInput.substring(0, 8);

                resultContainer.innerHTML = `
                    <div class="result-card">
                        <h2 style="margin-bottom: 1.5rem; color: #00d4ff;">
                            <i class="fas fa-info-circle"></i> BIN Information
                        </h2>
                        <div class="result-row">
                            <span class="result-label">
                                <i class="fas fa-hashtag"></i> BIN
                            </span>
                            <span class="result-value">${bin}</span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">
                                <i class="fas fa-credit-card"></i> Card Type
                            </span>
                            <span class="result-value">${cardType}</span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">
                                <i class="fas fa-tag"></i> Brand
                            </span>
                            <span class="result-value">${brand}</span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">
                                <i class="fas fa-layer-group"></i> Level
                            </span>
                            <span class="result-value">${level}</span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">
                                <i class="fas fa-university"></i> Bank
                            </span>
                            <span class="result-value">${bank}</span>
                        </div>
                        <div class="result-row">
                            <span class="result-label">
                                <i class="fas fa-globe"></i> Country
                            </span>
                            <span class="result-value">${country} ${countryCode}</span>
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

        // Allow Enter key to submit
        document.getElementById('binInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                lookupBin();
            }
        });

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
