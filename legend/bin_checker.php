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

// Handle AJAX lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
    header('Content-Type: application/json');
    
    $bin = $_POST['bin'] ?? '';
    
    if (empty($bin)) {
        echo json_encode(['success' => false, 'error' => 'BIN is required']);
        exit;
    }
    
    try {
        $binInfo = BINLookup::getBinInfo($bin);
        
        echo json_encode([
            'success' => true,
            'bin_info' => $binInfo
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Lookup failed: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Handle AJAX generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    header('Content-Type: application/json');
    
    $bin = $_POST['bin'] ?? '';
    $count = min((int)($_POST['count'] ?? 10), 100); // Max 100 cards
    
    if (empty($bin)) {
        echo json_encode(['success' => false, 'error' => 'BIN is required']);
        exit;
    }
    
    try {
        $cards = BINLookup::generateCC($bin, $count);
        
        // Add expiry and CVV to each card
        $fullCards = [];
        foreach ($cards as $cc) {
            $mm = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
            $yyyy = rand(date('Y'), date('Y') + 5);
            $cvv = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
            $fullCards[] = "{$cc}|{$mm}|{$yyyy}|{$cvv}";
        }
        
        echo json_encode([
            'success' => true,
            'cards' => $fullCards
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Generation failed: ' . $e->getMessage()
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
    <title>BIN Tools - LEGEND CHECKER</title>
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
            max-width: 1200px;
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

        .badge {
            background: rgba(40, 167, 69, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(40, 167, 69, 0.4);
            color: #28a745;
            font-weight: 600;
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

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #00d4ff;
            border-bottom-color: #00d4ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

        input, textarea {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus, textarea:focus {
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .result-item {
            margin-bottom: 10px;
            display: flex;
            gap: 10px;
        }

        .result-item strong {
            min-width: 120px;
            color: rgba(255, 255, 255, 0.7);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="tools.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Tools
            </a>
            <div class="badge">
                <i class="fas fa-gift"></i> FREE TOOLS
            </div>
        </div>

        <div class="card">
            <h2 class="card-title"><i class="fas fa-search"></i> BIN Tools</h2>
            
            <div class="tabs">
                <button class="tab active" onclick="switchTab('lookup')">
                    <i class="fas fa-search"></i> BIN Lookup
                </button>
                <button class="tab" onclick="switchTab('generate')">
                    <i class="fas fa-random"></i> Generate Cards
                </button>
            </div>

            <!-- BIN Lookup Tab -->
            <div id="lookup" class="tab-content active">
                <div class="info-box">
                    <h3>BIN Lookup - FREE</h3>
                    <p>Enter the first 6-8 digits of a card to get information about the bank, card type, and country.</p>
                </div>

                <form id="lookupForm">
                    <div class="form-group">
                        <label for="binInput">BIN Number (6-8 digits)</label>
                        <input type="text" id="binInput" placeholder="411111" required pattern="[0-9]{6,8}">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Lookup BIN
                    </button>
                </form>

                <div id="lookupResult"></div>
            </div>

            <!-- Generate Cards Tab -->
            <div id="generate" class="tab-content">
                <div class="info-box">
                    <h3>Generate Cards from BIN - FREE</h3>
                    <p>Generate valid credit card numbers from a BIN using the Luhn algorithm. These are for testing purposes only.</p>
                </div>

                <form id="generateForm">
                    <div class="form-group">
                        <label for="genBinInput">BIN Number (6-8 digits)</label>
                        <input type="text" id="genBinInput" placeholder="411111" required pattern="[0-9]{6,8}">
                    </div>

                    <div class="form-group">
                        <label for="countInput">Number of Cards (1-100)</label>
                        <input type="number" id="countInput" value="10" min="1" max="100" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-random"></i> Generate Cards
                    </button>
                </form>

                <div id="generateResult"></div>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        function switchTab(tabName) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.closest('.tab').classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        // BIN Lookup
        document.getElementById('lookupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const bin = document.getElementById('binInput').value;
            const resultDiv = document.getElementById('lookupResult');
            
            resultDiv.innerHTML = '<p>Loading...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'lookup');
                formData.append('bin', bin);

                const response = await fetch('bin_checker.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const info = data.bin_info;
                    resultDiv.className = 'result';
                    resultDiv.innerHTML = `
                        <h3>✅ BIN Information</h3>
                        <div class="result-item"><strong>BIN:</strong> ${bin}</div>
                        <div class="result-item"><strong>Brand:</strong> 💳 ${info.brand || 'Unknown'}</div>
                        <div class="result-item"><strong>Type:</strong> ${info.type || 'Unknown'}</div>
                        <div class="result-item"><strong>Level:</strong> ${info.level || 'Unknown'}</div>
                        <div class="result-item"><strong>Bank:</strong> 🏦 ${info.bank || 'Unknown'}</div>
                        <div class="result-item"><strong>Country:</strong> ${info.country || 'Unknown'}</div>
                    `;
                } else {
                    resultDiv.className = 'result';
                    resultDiv.innerHTML = `<h3>❌ ERROR</h3><p>${data.error}</p>`;
                }
            } catch (error) {
                resultDiv.className = 'result';
                resultDiv.innerHTML = `<h3>❌ ERROR</h3><p>Request failed: ${error.message}</p>`;
            }
        });

        // Generate Cards
        document.getElementById('generateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const bin = document.getElementById('genBinInput').value;
            const count = document.getElementById('countInput').value;
            const resultDiv = document.getElementById('generateResult');
            
            resultDiv.innerHTML = '<p>Generating...</p>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'generate');
                formData.append('bin', bin);
                formData.append('count', count);

                const response = await fetch('bin_checker.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const cards = data.cards.join('\n');
                    resultDiv.className = 'result';
                    resultDiv.innerHTML = `
                        <h3>✅ Generated ${data.cards.length} Cards</h3>
                        <textarea readonly style="width: 100%; min-height: 200px; margin-top: 10px; font-family: 'Courier New', monospace;">${cards}</textarea>
                        <div class="btn-group">
                            <button class="btn btn-success" onclick="copyToClipboard()">
                                <i class="fas fa-copy"></i> Copy All
                            </button>
                            <button class="btn btn-primary" onclick="downloadCards()">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'result';
                    resultDiv.innerHTML = `<h3>❌ ERROR</h3><p>${data.error}</p>`;
                }
            } catch (error) {
                resultDiv.className = 'result';
                resultDiv.innerHTML = `<h3>❌ ERROR</h3><p>Request failed: ${error.message}</p>`;
            }
        });

        function copyToClipboard() {
            const textarea = document.querySelector('#generateResult textarea');
            textarea.select();
            document.execCommand('copy');
            alert('Cards copied to clipboard!');
        }

        function downloadCards() {
            const textarea = document.querySelector('#generateResult textarea');
            const blob = new Blob([textarea.value], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'generated_cards.txt';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
