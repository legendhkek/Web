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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools - LEGEND CHECKER</title>
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

        .credits-warning {
            background: rgba(255, 107, 107, 0.1);
            border-color: rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
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

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .tool-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .tool-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00d4ff, #7c3aed);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .tool-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 212, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.1);
        }

        .tool-card:hover::before {
            opacity: 1;
        }

        .tool-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .tool-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .tool-description {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .tool-cost {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 212, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .tool-btn {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .tool-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .tool-btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .stats-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .stats-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #00d4ff;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
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

            .tools-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .tool-card {
                padding: 1.5rem;
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
            <div class="user-credits <?php echo $user['credits'] < 10 ? 'credits-warning' : ''; ?>">
                <i class="fas fa-coins"></i>
                <span><?php echo number_format($user['credits']); ?> Credits</span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-tools"></i> Tools & Checkers</h1>
            <p>Professional tools for security testing and validation</p>
        </div>

        <div class="tools-grid">
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h3 class="tool-title">Card Checker</h3>
                <p class="tool-description">
                    Validate credit card numbers and check their status against payment gateways
                </p>
                <div class="tool-cost">
                    <i class="fas fa-coins"></i>
                    <?php echo AppConfig::CARD_CHECK_COST; ?> Credit per check
                </div>
                <a href="card_checker.php" class="tool-btn" <?php echo $user['credits'] < AppConfig::CARD_CHECK_COST ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                    Launch Tool
                </a>
            </div>

            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h3 class="tool-title">Site Checker</h3>
                <p class="tool-description">
                    Test website availability and response codes for multiple URLs simultaneously
                </p>
                <div class="tool-cost">
                    <i class="fas fa-coins"></i>
                    <?php echo AppConfig::SITE_CHECK_COST; ?> Credit per check
                </div>
                <a href="site_checker.php" class="tool-btn" <?php echo $user['credits'] < AppConfig::SITE_CHECK_COST ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                    Launch Tool
                </a>
            </div>

            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-stripe-s"></i>
                </div>
                <h3 class="tool-title">Stripe Auth Checker</h3>
                <p class="tool-description">
                    Test Stripe authentication with automatic site rotation across 280+ sites
                </p>
                <div class="tool-cost">
                    <i class="fas fa-coins"></i>
                    1 Credit per check
                </div>
                <a href="stripe_auth_tool.php" class="tool-btn" <?php echo $user['credits'] < 1 ? 'style="pointer-events:none;opacity:0.5;"' : ''; ?>>
                    Launch Tool
                </a>
            </div>

            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="tool-title">BIN Lookup</h3>
                <p class="tool-description">
                    Get detailed information about any BIN number - card type, bank, country
                </p>
                <div class="tool-cost" style="background: rgba(0, 230, 118, 0.1);">
                    <i class="fas fa-gift"></i>
                    FREE Tool
                </div>
                <a href="bin_lookup_tool.php" class="tool-btn">
                    Launch Tool
                </a>
            </div>

            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-magic"></i>
                </div>
                <h3 class="tool-title">BIN Generator</h3>
                <p class="tool-description">
                    Generate valid credit card numbers from BIN with Luhn algorithm validation
                </p>
                <div class="tool-cost" style="background: rgba(0, 230, 118, 0.1);">
                    <i class="fas fa-gift"></i>
                    FREE Tool
                </div>
                <a href="bin_generator_tool.php" class="tool-btn">
                    Launch Tool
                </a>
            </div>

            <div class="tool-card">
                <div class="tool-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="tool-title">Security Scanner</h3>
                <p class="tool-description">
                    Advanced security scanning and vulnerability assessment tools
                </p>
                <div class="tool-cost">
                    <i class="fas fa-coins"></i>
                    5 Credits per scan
                </div>
                <button class="tool-btn" disabled>
                    Coming Soon
                </button>
            </div>
        </div>

        <?php
        $userStats = $db->getUserStats($userId);
        ?>
        <div class="stats-section">
            <h2 class="stats-title">Your Usage Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($userStats['total_hits'] ?? 0); ?></div>
                    <div class="stat-label">Total Checks</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($userStats['total_charge_cards'] ?? 0); ?></div>
                    <div class="stat-label">Charged Cards</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($userStats['total_live_cards'] ?? 0); ?></div>
                    <div class="stat-label">Live Cards</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($user['credits']); ?></div>
                    <div class="stat-label">Available Credits</div>
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
            <a href="tools.php" class="nav-item active">
                <i class="fas fa-tools"></i>
                <span>Tools</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="wallet.php" class="nav-item">
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
