<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

// Update presence
$db->updatePresence($userId);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!TelegramAuth::validateCSRFToken($csrf_token)) {
        $message = "Security error: Invalid request token";
        $messageType = 'error';
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'deposit':
                $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_INT);
                if ($amount !== false && $amount > 0 && $amount <= 1000) {
                    // In a real implementation, integrate with payment gateway
                    $message = "Deposit request for " . htmlspecialchars($amount) . " XCoins submitted. Payment gateway integration required.";
                    $messageType = 'info';
                } else {
                    $message = "Invalid deposit amount. Must be between 1-1000 XCoins.";
                    $messageType = 'error';
                }
                break;
                
            case 'redeem':
                $credits = filter_var($_POST['credits'] ?? 0, FILTER_VALIDATE_INT);
                if ($credits === false || $credits <= 0) {
                    $message = "Invalid credit amount.";
                    $messageType = 'error';
                    break;
                }
                
                $xcoinCost = $credits * 2; // 1 credit = 2 XCoins
                
                if ($user['xcoin_balance'] >= $xcoinCost) {
                    $db->redeemCredits($userId, $credits, $xcoinCost);
                    $message = "Successfully redeemed " . htmlspecialchars($credits) . " credits for " . htmlspecialchars($xcoinCost) . " XCoins!";
                    $messageType = 'success';
                    $user = $db->getUserByTelegramId($userId); // Refresh user data
                } else {
                    $message = "Insufficient XCoin balance.";
                    $messageType = 'error';
                }
                break;
                
            case 'upgrade':
                $plan = $_POST['plan'] ?? '';
                $costs = [
                    'premium' => 100,
                    'vip' => 250
                ];
                
                // Validate plan is one of the allowed values
                if (!in_array($plan, ['premium', 'vip'], true)) {
                    $message = "Invalid membership plan.";
                    $messageType = 'error';
                    break;
                }
                
                if (isset($costs[$plan]) && $user['xcoin_balance'] >= $costs[$plan]) {
                    $db->upgradeMembership($userId, $plan, $costs[$plan]);
                    $message = "Successfully upgraded to " . htmlspecialchars(ucfirst($plan)) . " membership!";
                    $messageType = 'success';
                    $user = $db->getUserByTelegramId($userId); // Refresh user data
                } else {
                    $message = "Insufficient XCoin balance for this upgrade.";
                    $messageType = 'error';
                }
                break;
                
            default:
                $message = "Invalid action.";
                $messageType = 'error';
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet - LEGEND CHECKER</title>
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

        .balance-display {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .balance-item {
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
            margin-bottom: 3rem;
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

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .message.info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #3b82f6;
        }

        .wallet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .wallet-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .wallet-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 212, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .card-icon {
            font-size: 2rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 2px rgba(0, 212, 255, 0.2);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-size: 1rem;
        }

        .btn {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .pricing-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .pricing-card:hover {
            border-color: rgba(0, 212, 255, 0.3);
        }

        .pricing-card.featured {
            border-color: #00d4ff;
            position: relative;
        }

        .pricing-card.featured::before {
            content: 'POPULAR';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            padding: 0.25rem 1rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .plan-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: 700;
            color: #00d4ff;
            margin-bottom: 1rem;
        }

        .plan-features {
            list-style: none;
            margin-bottom: 1.5rem;
        }

        .plan-features li {
            padding: 0.25rem 0;
            color: rgba(255, 255, 255, 0.8);
        }

        .plan-features li i {
            color: #00d4ff;
            margin-right: 0.5rem;
        }

        .current-plan {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-weight: 600;
            margin-top: 1rem;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 15, 35, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
            z-index: 1000;
        }

        .nav-items {
            display: flex;
            justify-content: space-around;
            max-width: 600px;
            margin: 0 auto;
        }

        .nav-item {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .nav-item.active {
            color: #00d4ff;
        }

        .nav-item:hover {
            color: #ffffff;
        }

        .nav-item i {
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            .wallet-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .balance-display {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <div class="balance-display">
                <div class="balance-item">
                    <i class="fas fa-coins"></i>
                    <span><?php echo number_format($user['credits']); ?> Credits</span>
                </div>
                <div class="balance-item">
                    <i class="fas fa-gem"></i>
                    <span><?php echo number_format($user['xcoin_balance']); ?> XCoins</span>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-wallet"></i> Wallet & Premium</h1>
            <p>Manage your credits, XCoins, and upgrade your membership</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="wallet-grid">
            <div class="wallet-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3 class="card-title">Deposit XCoins</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="deposit">
                    <input type="hidden" name="csrf_token" value="<?php echo TelegramAuth::generateCSRFToken(); ?>">
                    <div class="form-group">
                        <label class="form-label">Amount (XCoins)</label>
                        <select name="amount" class="form-select" required>
                            <option value="">Select amount</option>
                            <option value="50">50 XCoins - $5.00</option>
                            <option value="100">100 XCoins - $10.00</option>
                            <option value="250">250 XCoins - $25.00</option>
                            <option value="500">500 XCoins - $50.00</option>
                            <option value="1000">1000 XCoins - $100.00</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-credit-card"></i> Deposit Now
                    </button>
                </form>
            </div>

            <div class="wallet-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3 class="card-title">Redeem Credits</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="redeem">
                    <input type="hidden" name="csrf_token" value="<?php echo TelegramAuth::generateCSRFToken(); ?>">
                    <div class="form-group">
                        <label class="form-label">Credits to Redeem</label>
                        <input type="number" name="credits" class="form-input" min="1" max="<?php echo floor($user['xcoin_balance'] / 2); ?>" placeholder="Enter credits amount" required>
                        <small style="color: rgba(255,255,255,0.6); font-size: 0.8rem;">Rate: 1 Credit = 2 XCoins</small>
                    </div>
                    <button type="submit" class="btn" <?php echo $user['xcoin_balance'] < 2 ? 'disabled' : ''; ?>>
                        <i class="fas fa-coins"></i> Redeem Credits
                    </button>
                </form>
            </div>
        </div>

        <div class="wallet-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h3 class="card-title">Premium Memberships</h3>
            </div>
            
            <div class="pricing-grid">
                <div class="pricing-card <?php echo $user['role'] === 'free' ? 'current' : ''; ?>">
                    <div class="plan-name">Free</div>
                    <div class="plan-price">$0</div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check"></i> 10 daily credits</li>
                        <li><i class="fas fa-check"></i> Basic tools access</li>
                        <li><i class="fas fa-check"></i> Community support</li>
                    </ul>
                    <?php if ($user['role'] === 'free'): ?>
                        <div class="current-plan">Current Plan</div>
                    <?php endif; ?>
                </div>

                <div class="pricing-card featured <?php echo $user['role'] === 'premium' ? 'current' : ''; ?>">
                    <div class="plan-name">Premium</div>
                    <div class="plan-price">100 XC</div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check"></i> 25 daily credits</li>
                        <li><i class="fas fa-check"></i> All tools access</li>
                        <li><i class="fas fa-check"></i> Priority support</li>
                        <li><i class="fas fa-check"></i> Advanced analytics</li>
                    </ul>
                    <?php if ($user['role'] === 'premium'): ?>
                        <div class="current-plan">Current Plan</div>
                    <?php else: ?>
                        <form method="POST" style="margin-top: 1rem;">
                            <input type="hidden" name="action" value="upgrade">
                            <input type="hidden" name="plan" value="premium">
                            <input type="hidden" name="csrf_token" value="<?php echo TelegramAuth::generateCSRFToken(); ?>">
                            <button type="submit" class="btn" <?php echo $user['xcoin_balance'] < 100 ? 'disabled' : ''; ?>>
                                Upgrade Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="pricing-card <?php echo $user['role'] === 'vip' ? 'current' : ''; ?>">
                    <div class="plan-name">VIP</div>
                    <div class="plan-price">250 XC</div>
                    <ul class="plan-features">
                        <li><i class="fas fa-check"></i> 50 daily credits</li>
                        <li><i class="fas fa-check"></i> Exclusive tools</li>
                        <li><i class="fas fa-check"></i> 24/7 support</li>
                        <li><i class="fas fa-check"></i> API access</li>
                        <li><i class="fas fa-check"></i> Custom limits</li>
                    </ul>
                    <?php if ($user['role'] === 'vip'): ?>
                        <div class="current-plan">Current Plan</div>
                    <?php else: ?>
                        <form method="POST" style="margin-top: 1rem;">
                            <input type="hidden" name="action" value="upgrade">
                            <input type="hidden" name="plan" value="vip">
                            <input type="hidden" name="csrf_token" value="<?php echo TelegramAuth::generateCSRFToken(); ?>">
                            <button type="submit" class="btn" <?php echo $user['xcoin_balance'] < 250 ? 'disabled' : ''; ?>>
                                Upgrade Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <div class="nav-items">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="tools.php" class="nav-item">
                <i class="fas fa-tools"></i>
                <span>Tools</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="wallet.php" class="nav-item active">
                <i class="fas fa-wallet"></i>
                <span>Wallet</span>
            </a>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
