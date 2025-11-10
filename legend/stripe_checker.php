<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'stripe_auth_checker.php';
require_once 'bin_lookup.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

// Update presence
$db->updatePresence($userId);

// Handle AJAX check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check') {
    header('Content-Type: application/json');
    
    $cc = $_POST['cc'] ?? '';
    $site = $_POST['site'] ?? '';
    $proxy = $_POST['proxy'] ?? null;
    
    // Check if user has enough credits
    $cost = AppConfig::CARD_CHECK_COST;
    if ($user['credits'] < $cost) {
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient credits. You need ' . $cost . ' credit to check a card.'
        ]);
        exit;
    }
    
    // Validate inputs
    if (empty($cc) || empty($site)) {
        echo json_encode(['success' => false, 'error' => 'Card and site are required']);
        exit;
    }
    
    // Deduct credits
    if (!$db->deductCredits($userId, $cost)) {
        echo json_encode(['success' => false, 'error' => 'Failed to deduct credits']);
        exit;
    }
    
    try {
        // Check card
        $checker = new StripeAuthChecker($site, $proxy);
        $result = $checker->checkCard($cc);
        
        // Get BIN info
        $binInfo = BINLookup::getCardInfoFromCC($cc);
        
        // Log tool usage
        $db->logToolUsage($userId, 'stripe_auth_checker', [
            'usage_count' => 1,
            'credits_used' => $cost,
            'result' => $result['status']
        ]);
        
        // Update user stats
        $db->updateUserStats($userId, ['total_hits' => ['$inc' => 1]]);
        
        // Format response
        $response = [
            'success' => true,
            'result' => $result,
            'bin_info' => $binInfo,
            'remaining_credits' => $user['credits'] - $cost
        ];
        
        echo json_encode($response);
    } catch (Exception $e) {
        // Refund credits on error
        $db->addCredits($userId, $cost);
        
        echo json_encode([
            'success' => false,
            'error' => 'Check failed: ' . $e->getMessage()
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
    <title>Stripe Auth Checker - LEGEND CHECKER</title>
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

        .credits-display {
            background: rgba(0, 212, 255, 0.1);
            padding: 10px 20px;
            border-radius: 25px;
            border: 1px solid rgba(0, 212, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
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
            min-height: 100px;
            resize: vertical;
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

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 15px;
            border: 1px solid;
        }

        .result.success {
            background: rgba(40, 167, 69, 0.1);
            border-color: rgba(40, 167, 69, 0.3);
        }

        .result.error {
            background: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.3);
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

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #00d4ff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            padding: 5px 0;
            color: rgba(255, 255, 255, 0.8);
        }

        .info-box li:before {
            content: '✓ ';
            color: #00d4ff;
            font-weight: bold;
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
            <div class="credits-display">
                <i class="fas fa-coins"></i>
                <span id="creditsDisplay"><?php echo number_format($user['credits']); ?> Credits</span>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title"><i class="fas fa-shield-alt"></i> Stripe Auth Checker</h2>
            
            <div class="info-box">
                <h3>How it works:</h3>
                <ul>
                    <li>Automatically creates an account on Stripe-powered sites</li>
                    <li>Adds payment method to verify the card</li>
                    <li>Cost: 1 credit per check</li>
                    <li>Supports WooCommerce + Stripe integration</li>
                </ul>
            </div>

            <form id="checkForm">
                <div class="form-group">
                    <label for="ccInput">Credit Card (Format: xxxx|xx|xxxx|xxx)</label>
                    <input type="text" id="ccInput" placeholder="4111111111111111|12|2025|123" required>
                </div>

                <div class="form-group">
                    <label for="siteInput">Website URL</label>
                    <input type="text" id="siteInput" placeholder="https://example.com" required>
                </div>

                <div class="form-group">
                    <label for="proxyInput">Proxy (Optional - Format: ip:port:user:pass)</label>
                    <input type="text" id="proxyInput" placeholder="123.45.67.89:8080:user:pass">
                </div>

                <button type="submit" class="btn btn-primary" id="checkBtn">
                    <i class="fas fa-play"></i> Check Card (1 Credit)
                </button>
            </form>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p style="margin-top: 10px;">Checking card... This may take 20-30 seconds</p>
            </div>

            <div id="result"></div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const checkForm = document.getElementById('checkForm');
        const checkBtn = document.getElementById('checkBtn');
        const loading = document.getElementById('loading');
        const resultDiv = document.getElementById('result');
        const creditsDisplay = document.getElementById('creditsDisplay');

        checkForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const cc = document.getElementById('ccInput').value;
            const site = document.getElementById('siteInput').value;
            const proxy = document.getElementById('proxyInput').value;

            // Disable button and show loading
            checkBtn.disabled = true;
            loading.style.display = 'block';
            resultDiv.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('action', 'check');
                formData.append('cc', cc);
                formData.append('site', site);
                if (proxy) formData.append('proxy', proxy);

                const response = await fetch('stripe_checker.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const result = data.result;
                    const binInfo = data.bin_info;
                    
                    resultDiv.className = `result ${result.success ? 'success' : 'error'}`;
                    resultDiv.innerHTML = `
                        <h3>${result.success ? '✅ SUCCESS' : '❌ FAILED'}</h3>
                        <div class="result-item"><strong>Card:</strong> ${cc}</div>
                        <div class="result-item"><strong>Site:</strong> ${site}</div>
                        <div class="result-item"><strong>Status:</strong> ${result.status}</div>
                        <div class="result-item"><strong>Message:</strong> ${result.message}</div>
                        ${result.account_email ? `<div class="result-item"><strong>Account Email:</strong> ${result.account_email}</div>` : ''}
                        ${result.pm_id ? `<div class="result-item"><strong>Payment Method:</strong> ${result.pm_id}</div>` : ''}
                        ${binInfo.bank ? `<div class="result-item"><strong>Bank:</strong> 🏦 ${binInfo.bank}</div>` : ''}
                        ${binInfo.type ? `<div class="result-item"><strong>Card Type:</strong> 💳 ${binInfo.type}</div>` : ''}
                        ${binInfo.country ? `<div class="result-item"><strong>Country:</strong> ${binInfo.country}</div>` : ''}
                    `;
                    
                    // Update credits display
                    creditsDisplay.textContent = data.remaining_credits.toLocaleString() + ' Credits';
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <h3>❌ ERROR</h3>
                        <div class="result-item">${data.error}</div>
                    `;
                }
            } catch (error) {
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <h3>❌ ERROR</h3>
                    <div class="result-item">Request failed: ${error.message}</div>
                `;
            } finally {
                checkBtn.disabled = false;
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
