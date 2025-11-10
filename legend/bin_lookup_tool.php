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

// Cost per lookup - FREE!
const BIN_LOOKUP_COST = 0;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'lookup_bin') {
        $bin = $_POST['bin'] ?? '';
        
        if (empty($bin)) {
            echo json_encode([
                'success' => false,
                'message' => 'BIN number is required'
            ]);
            exit;
        }
        
        // Clean the BIN (remove any non-numeric characters)
        $bin = preg_replace('/[^0-9]/', '', $bin);
        
        if (strlen($bin) < 6) {
            echo json_encode([
                'success' => false,
                'message' => 'BIN must be at least 6 digits'
            ]);
            exit;
        }
        
        // Get first 6-8 digits as BIN
        $binNumber = substr($bin, 0, min(8, strlen($bin)));
        
        try {
            // Perform BIN lookup
            $binInfo = BinLookup::getBinInfo($binNumber);
            
            // Log the lookup
            $db->logToolUsage($userId, 'bin_lookup', [
                'bin' => $binNumber,
                'card_type' => $binInfo['type'] ?? 'Unknown',
                'bank' => $binInfo['bank'] ?? 'Unknown',
                'country' => $binInfo['country'] ?? 'Unknown'
            ], BIN_LOOKUP_COST);
            
            echo json_encode([
                'success' => true,
                'result' => [
                    'bin' => $binNumber,
                    'type' => $binInfo['type'] ?? 'Unknown',
                    'brand' => $binInfo['brand'] ?? 'Unknown',
                    'level' => $binInfo['level'] ?? 'Unknown',
                    'bank' => $binInfo['bank'] ?? 'Unknown',
                    'country' => $binInfo['country'] ?? 'Unknown',
                    'country_code' => $binInfo['country_code'] ?? 'Unknown',
                    'card_emoji' => BinLookup::getCardTypeEmoji($binInfo['brand'] ?? ''),
                    'country_emoji' => BinLookup::getCountryEmoji($binInfo['country_code'] ?? '')
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Lookup failed: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
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

        .info-badge {
            display: inline-block;
            background: rgba(0, 230, 118, 0.1);
            border: 1px solid rgba(0, 230, 118, 0.3);
            color: #00e676;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            margin-bottom: 2rem;
        }

        .lookup-card {
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

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: #00d4ff;
            background: rgba(255, 255, 255, 0.15);
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

        .result-container {
            display: none;
            margin-top: 2rem;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            padding: 2rem;
            animation: slideIn 0.3s ease;
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

        .result-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .result-header .emoji {
            font-size: 3rem;
        }

        .result-header .bin-number {
            font-size: 2rem;
            font-weight: 700;
            color: #00d4ff;
        }

        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .result-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 10px;
            border-left: 3px solid #00d4ff;
        }

        .result-item .label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .result-item .value {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #ef4444;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            .result-details {
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
                <span><?php echo number_format($user['credits']); ?></span> Credits
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-search"></i> BIN Lookup</h1>
            <p>Get detailed information about any BIN number</p>
        </div>

        <div style="text-align: center;">
            <div class="info-badge">
                <i class="fas fa-gift"></i> FREE - No Credits Required
            </div>
        </div>

        <div class="lookup-card">
            <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-credit-card"></i> Enter BIN Number</h2>
            
            <form id="lookupForm">
                <div class="form-group">
                    <label for="binInput">
                        <i class="fas fa-keyboard"></i> BIN Number (First 6-8 digits)
                    </label>
                    <input 
                        type="text" 
                        id="binInput" 
                        name="bin" 
                        placeholder="Enter BIN: e.g., 453201"
                        required
                        maxlength="8"
                        pattern="[0-9]{6,8}"
                    >
                    <small style="color: rgba(255,255,255,0.6); margin-top: 0.5rem; display: block;">
                        Enter the first 6-8 digits of a credit card number
                    </small>
                </div>

                <button type="submit" class="btn-primary" id="lookupBtn">
                    <i class="fas fa-search"></i> Lookup BIN
                </button>
            </form>

            <div class="error-message" id="errorMessage"></div>
        </div>

        <div class="result-container" id="resultContainer">
            <div class="result-card" id="resultCard">
                <!-- Results will be displayed here -->
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const lookupForm = document.getElementById('lookupForm');
        const lookupBtn = document.getElementById('lookupBtn');
        const resultContainer = document.getElementById('resultContainer');
        const resultCard = document.getElementById('resultCard');
        const errorMessage = document.getElementById('errorMessage');
        const binInput = document.getElementById('binInput');

        // Only allow numbers in BIN input
        binInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        lookupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(lookupForm);
            formData.append('action', 'lookup_bin');
            
            lookupBtn.disabled = true;
            lookupBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Looking up...';
            errorMessage.style.display = 'none';
            resultContainer.style.display = 'none';
            
            try {
                const response = await fetch('bin_lookup_tool.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayResult(data.result);
                } else {
                    showError(data.message || 'Lookup failed');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                lookupBtn.disabled = false;
                lookupBtn.innerHTML = '<i class="fas fa-search"></i> Lookup BIN';
            }
        });

        function displayResult(result) {
            resultCard.innerHTML = `
                <div class="result-header">
                    <div class="emoji">${result.card_emoji}</div>
                    <div>
                        <div class="bin-number">${result.bin}</div>
                        <div style="color: rgba(255,255,255,0.6);">BIN Information</div>
                    </div>
                </div>
                <div class="result-details">
                    <div class="result-item">
                        <div class="label">Card Brand</div>
                        <div class="value">${result.brand}</div>
                    </div>
                    <div class="result-item">
                        <div class="label">Card Type</div>
                        <div class="value">${result.type}</div>
                    </div>
                    <div class="result-item">
                        <div class="label">Card Level</div>
                        <div class="value">${result.level}</div>
                    </div>
                    <div class="result-item">
                        <div class="label">Bank Name</div>
                        <div class="value">${result.bank}</div>
                    </div>
                    <div class="result-item">
                        <div class="label">Country</div>
                        <div class="value">${result.country_emoji} ${result.country}</div>
                    </div>
                    <div class="result-item">
                        <div class="label">Country Code</div>
                        <div class="value">${result.country_code}</div>
                    </div>
                </div>
            `;
            
            resultContainer.style.display = 'block';
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
        }

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
