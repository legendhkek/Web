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

// Cost per generation - FREE!
const BIN_GEN_COST = 0;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'generate_cards') {
        $bin = $_POST['bin'] ?? '';
        $month = $_POST['month'] ?? '';
        $year = $_POST['year'] ?? '';
        $cvv = $_POST['cvv'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 10);
        
        if (empty($bin)) {
            echo json_encode([
                'success' => false,
                'message' => 'BIN number is required'
            ]);
            exit;
        }
        
        // Clean the BIN
        $bin = preg_replace('/[^0-9]/', '', $bin);
        
        if (strlen($bin) < 6) {
            echo json_encode([
                'success' => false,
                'message' => 'BIN must be at least 6 digits'
            ]);
            exit;
        }
        
        // Limit quantity
        if ($quantity < 1) $quantity = 1;
        if ($quantity > 100) $quantity = 100;
        
        try {
            // Get BIN info for display
            $binInfo = BinLookup::getBinInfo(substr($bin, 0, 8));
            
            // Generate cards
            $cards = generateCards($bin, $month, $year, $cvv, $quantity);
            
            // Log the generation
            $db->logToolUsage($userId, 'bin_generator', [
                'bin' => substr($bin, 0, 8),
                'quantity' => $quantity,
                'card_type' => $binInfo['type'] ?? 'Unknown'
            ], BIN_GEN_COST);
            
            echo json_encode([
                'success' => true,
                'result' => [
                    'cards' => $cards,
                    'bin_info' => [
                        'bin' => substr($bin, 0, 8),
                        'type' => $binInfo['type'] ?? 'Unknown',
                        'brand' => $binInfo['brand'] ?? 'Unknown',
                        'bank' => $binInfo['bank'] ?? 'Unknown',
                        'country' => $binInfo['country'] ?? 'Unknown',
                        'card_emoji' => BinLookup::getCardTypeEmoji($binInfo['brand'] ?? ''),
                        'country_emoji' => BinLookup::getCountryEmoji($binInfo['country_code'] ?? '')
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

function generateCards($bin, $month, $year, $cvv, $quantity) {
    $cards = [];
    $binLength = strlen($bin);
    $remainingDigits = 16 - $binLength - 1; // -1 for Luhn check digit
    
    for ($i = 0; $i < $quantity; $i++) {
        // Generate random digits for the remaining positions
        $randomDigits = '';
        for ($j = 0; $j < $remainingDigits; $j++) {
            $randomDigits .= mt_rand(0, 9);
        }
        
        // Combine BIN with random digits
        $cardNumber = $bin . $randomDigits;
        
        // Calculate Luhn check digit
        $checkDigit = calculateLuhnCheckDigit($cardNumber);
        $cardNumber .= $checkDigit;
        
        // Generate expiry date if not provided
        $expMonth = $month;
        $expYear = $year;
        
        if (empty($expMonth)) {
            $expMonth = str_pad(mt_rand(1, 12), 2, '0', STR_PAD_LEFT);
        }
        
        if (empty($expYear)) {
            $currentYear = (int)date('Y');
            $expYear = mt_rand($currentYear, $currentYear + 5);
        }
        
        // Generate CVV if not provided
        $cardCvv = $cvv;
        if (empty($cardCvv)) {
            $cardCvv = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        }
        
        // Format card
        $cards[] = [
            'full' => $cardNumber . '|' . $expMonth . '|' . $expYear . '|' . $cardCvv,
            'number' => $cardNumber,
            'month' => $expMonth,
            'year' => $expYear,
            'cvv' => $cardCvv
        ];
    }
    
    return $cards;
}

function calculateLuhnCheckDigit($cardNumber) {
    $sum = 0;
    $numDigits = strlen($cardNumber);
    $parity = $numDigits % 2;
    
    for ($i = 0; $i < $numDigits; $i++) {
        $digit = (int)$cardNumber[$i];
        
        if ($i % 2 == $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
    }
    
    return (10 - ($sum % 10)) % 10;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIN Generator - LEGEND CHECKER</title>
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

        .generator-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
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
        }

        .form-group input:focus {
            outline: none;
            border-color: #00d4ff;
            background: rgba(255, 255, 255, 0.15);
        }

        .form-group small {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
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

        .result-header {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bin-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .bin-info .emoji {
            font-size: 2.5rem;
        }

        .bin-details {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-secondary {
            background: rgba(0, 212, 255, 0.2);
            color: #00d4ff;
            border: 1px solid rgba(0, 212, 255, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: rgba(0, 212, 255, 0.3);
        }

        .cards-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            max-height: 500px;
            overflow-y: auto;
        }

        .card-item {
            background: rgba(255, 255, 255, 0.05);
            border-left: 3px solid #00d4ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .card-text {
            flex: 1;
        }

        .copy-btn {
            background: rgba(0, 212, 255, 0.2);
            border: none;
            color: #00d4ff;
            padding: 0.5rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            background: rgba(0, 212, 255, 0.3);
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

            .form-row {
                grid-template-columns: 1fr;
            }

            .result-header {
                flex-direction: column;
                gap: 1rem;
            }

            .action-buttons {
                width: 100%;
            }

            .btn-secondary {
                flex: 1;
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
            <h1><i class="fas fa-magic"></i> BIN Generator</h1>
            <p>Generate valid credit card numbers from BIN</p>
        </div>

        <div style="text-align: center;">
            <div class="info-badge">
                <i class="fas fa-gift"></i> FREE - No Credits Required
            </div>
        </div>

        <div class="generator-card">
            <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-sliders-h"></i> Generator Settings</h2>
            
            <form id="generatorForm">
                <div class="form-group">
                    <label for="binInput">
                        <i class="fas fa-credit-card"></i> BIN Number *
                    </label>
                    <input 
                        type="text" 
                        id="binInput" 
                        name="bin" 
                        placeholder="Enter BIN: e.g., 453201"
                        required
                        minlength="6"
                    >
                    <small>First 6-16 digits of the card (required)</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="monthInput">
                            <i class="fas fa-calendar"></i> Month (Optional)
                        </label>
                        <input 
                            type="text" 
                            id="monthInput" 
                            name="month" 
                            placeholder="MM or leave empty for random"
                            maxlength="2"
                        >
                        <small>01-12 or leave empty</small>
                    </div>

                    <div class="form-group">
                        <label for="yearInput">
                            <i class="fas fa-calendar-alt"></i> Year (Optional)
                        </label>
                        <input 
                            type="text" 
                            id="yearInput" 
                            name="year" 
                            placeholder="YYYY or leave empty for random"
                            maxlength="4"
                        >
                        <small>e.g., 2025 or leave empty</small>
                    </div>

                    <div class="form-group">
                        <label for="cvvInput">
                            <i class="fas fa-lock"></i> CVV (Optional)
                        </label>
                        <input 
                            type="text" 
                            id="cvvInput" 
                            name="cvv" 
                            placeholder="CVV or leave empty for random"
                            maxlength="3"
                        >
                        <small>3 digits or leave empty</small>
                    </div>

                    <div class="form-group">
                        <label for="quantityInput">
                            <i class="fas fa-hashtag"></i> Quantity
                        </label>
                        <input 
                            type="number" 
                            id="quantityInput" 
                            name="quantity" 
                            value="10"
                            min="1"
                            max="100"
                        >
                        <small>1-100 cards</small>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="generateBtn">
                    <i class="fas fa-magic"></i> Generate Cards
                </button>
            </form>

            <div class="error-message" id="errorMessage"></div>
        </div>

        <div class="result-container" id="resultContainer">
            <div class="result-header" id="resultHeader">
                <!-- BIN info will be displayed here -->
            </div>
            <div class="cards-container" id="cardsContainer">
                <!-- Generated cards will be displayed here -->
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const generatorForm = document.getElementById('generatorForm');
        const generateBtn = document.getElementById('generateBtn');
        const resultContainer = document.getElementById('resultContainer');
        const resultHeader = document.getElementById('resultHeader');
        const cardsContainer = document.getElementById('cardsContainer');
        const errorMessage = document.getElementById('errorMessage');

        // Only allow numbers in numeric inputs
        ['binInput', 'monthInput', 'yearInput', 'cvvInput'].forEach(id => {
            document.getElementById(id).addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        generatorForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(generatorForm);
            formData.append('action', 'generate_cards');
            
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            errorMessage.style.display = 'none';
            resultContainer.style.display = 'none';
            
            try {
                const response = await fetch('bin_generator_tool.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayResults(data.result);
                } else {
                    showError(data.message || 'Generation failed');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Cards';
            }
        });

        function displayResults(result) {
            const binInfo = result.bin_info;
            
            // Display BIN info header
            resultHeader.innerHTML = `
                <div class="bin-info">
                    <div class="emoji">${binInfo.card_emoji}</div>
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 600; color: #00d4ff;">
                            BIN: ${binInfo.bin}
                        </div>
                        <div class="bin-details">
                            ${binInfo.brand} | ${binInfo.type} | ${binInfo.bank}<br>
                            ${binInfo.country_emoji} ${binInfo.country}
                        </div>
                    </div>
                </div>
                <div class="action-buttons">
                    <button class="btn-secondary" onclick="copyAllCards()">
                        <i class="fas fa-copy"></i> Copy All
                    </button>
                    <button class="btn-secondary" onclick="downloadCards()">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            `;
            
            // Display generated cards
            cardsContainer.innerHTML = '';
            result.cards.forEach((card, index) => {
                const cardDiv = document.createElement('div');
                cardDiv.className = 'card-item';
                cardDiv.innerHTML = `
                    <div class="card-text">${card.full}</div>
                    <button class="copy-btn" onclick="copyCard('${card.full}', this)">
                        <i class="fas fa-copy"></i>
                    </button>
                `;
                cardsContainer.appendChild(cardDiv);
            });
            
            resultContainer.style.display = 'block';
        }

        function copyCard(cardText, button) {
            navigator.clipboard.writeText(cardText).then(() => {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 1000);
            });
        }

        function copyAllCards() {
            const cards = Array.from(cardsContainer.querySelectorAll('.card-text'))
                .map(el => el.textContent)
                .join('\n');
            
            navigator.clipboard.writeText(cards).then(() => {
                alert('All cards copied to clipboard!');
            });
        }

        function downloadCards() {
            const cards = Array.from(cardsContainer.querySelectorAll('.card-text'))
                .map(el => el.textContent)
                .join('\n');
            
            const blob = new Blob([cards], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'generated_cards.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
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
