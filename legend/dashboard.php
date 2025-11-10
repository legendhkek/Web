<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);

// Ensure required fields exist with defaults
if (!isset($user['xcoin_balance'])) {
    $user['xcoin_balance'] = 0;
}
if (!isset($user['credits'])) {
    $user['credits'] = 0;
}
if (!isset($user['avatar_url'])) {
    $user['avatar_url'] = null;
}
if (!isset($user['display_name'])) {
    $user['display_name'] = $user['first_name'] ?? 'User';
}
if (!isset($user['role'])) {
    $user['role'] = 'free';
}

$userStats = $db->getUserStats($userId) ?? [
    'total_hits' => 0,
    'total_charge_cards' => 0,
    'total_live_cards' => 0,
    'expiry_date' => null
];
$globalStats = $db->getGlobalStats() ?? [
    'total_users' => 0,
    'total_hits' => 0,
    'total_charge_cards' => 0,
    'total_live_cards' => 0
];

// Get online users count
$onlineUsers = $db->getOnlineUsers(100);
$onlineCount = count($onlineUsers);

// Update presence
$db->updatePresence($userId);

// Initialize variables
$claimMessage = '';

// Handle AJAX credit refresh
if (isset($_GET['ajax']) && $_GET['ajax'] === 'credits') {
    header('Content-Type: application/json');
    $freshUser = $db->getUserByTelegramId($userId);
    echo json_encode([
        'success' => true,
        'credits' => intval($freshUser['credits'] ?? 0)
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEGEND CHECKER - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/enhanced.css">
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-card: #2a2a2a;
            --bg-card-hover: #333333;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #6b7280;
            --accent-blue: #1da1f2;
            --accent-green: #00d4aa;
            --accent-purple: #8b5cf6;
            --accent-orange: #f59e0b;
            --accent-pink: #ec4899;
            --border-color: #3a3a3a;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 700;
            color: var(--accent-blue);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .timer-chip {
            background: var(--bg-card);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .menu-toggle {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .menu-toggle:hover {
            background: var(--bg-card);
        }

        /* Profile Header Card */
        .profile-header {
            background: linear-gradient(135deg, var(--bg-card) 0%, #2d3748 100%);
            border-radius: 24px;
            padding: 36px;
            margin-bottom: 35px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(29, 161, 242, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .profile-content {
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            z-index: 1;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            font-weight: 700;
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .profile-role {
            display: inline-block;
            background: var(--accent-green);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .profile-role.premium {
            background: var(--accent-purple);
        }

        .profile-role.admin {
            background: var(--accent-orange);
        }

        .profile-role.owner {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }

        .admin-access-button {
            margin-top: 16px;
        }

        .admin-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
            color: white;
            text-decoration: none;
        }

        .admin-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .admin-badge.owner {
            background: rgba(255, 215, 0, 0.3);
            color: #ffd700;
        }

        .admin-badge.admin {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .profile-role.admin {
            background: var(--accent-orange);
        }

        .profile-role.owner {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }

        .profile-stats {
            display: flex;
            gap: 32px;
        }

        .profile-stat {
            text-align: center;
        }

        .profile-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent-blue);
            display: block;
        }

        .profile-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            margin-bottom: 35px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card:hover {
            background: var(--bg-card-hover);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .card-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* Card Colors */
        .card-purple .card-icon { background: var(--accent-purple); }
        .card-orange .card-icon { background: var(--accent-orange); }
        .card-green .card-icon { background: var(--accent-green); }
        .card-pink .card-icon { background: var(--accent-pink); }
        .card-blue .card-icon { background: var(--accent-blue); }

        /* User Stats Card */
        .user-stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .user-stats-card .card-title,
        .user-stats-card .card-subtitle,
        .user-stats-card .card-value {
            color: white;
        }

        .user-stats-card .account-info {
            margin-top: 12px;
        }

        /* Credit Claim Card */
        .credit-claim-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
        }

        .credit-claim-card .card-title,
        .credit-claim-card .card-subtitle,
        .credit-claim-card .card-value {
            color: white;
        }

        .claim-button {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 16px;
            width: 100%;
        }

        .claim-button:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        .claim-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .countdown {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 8px;
        }

        /* Global Stats Section */
        .section-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 28px;
            color: var(--text-primary);
            position: relative;
            padding-left: 20px;
        }

        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 30px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            border-radius: 3px;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 16px 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
            padding: 8px 16px;
            border-radius: 12px;
        }

        .nav-item.active,
        .nav-item:hover {
            color: var(--accent-blue);
            background: rgba(29, 161, 242, 0.1);
        }

        .nav-item i {
            font-size: 20px;
        }

        .nav-item span {
            font-size: 12px;
            font-weight: 500;
        }

        /* Side Drawer */
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 200;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .drawer-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .drawer {
            position: fixed;
            top: 0;
            left: -300px;
            width: 300px;
            height: 100vh;
            background: var(--bg-card);
            z-index: 201;
            transition: left 0.3s ease;
            overflow-y: auto;
        }

        .drawer.active {
            left: 0;
        }

        .drawer-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .drawer-menu {
            padding: 16px 0;
        }

        .drawer-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .drawer-item:hover {
            background: var(--bg-card-hover);
        }

        .drawer-item i {
            width: 20px;
            text-align: center;
        }

        /* Animations */
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced card icons */
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            transition: all 0.3s ease;
        }

        .card:hover .card-icon {
            transform: scale(1.1) rotate(5deg);
        }

        /* Success Message */
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .profile-content {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }

            .profile-stats {
                justify-content: center;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .header {
                padding: 16px 0;
            }

            body {
                padding-bottom: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                LEGEND CHECKER
            </div>
            <div class="header-actions">
                <div class="timer-chip">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"></span>
                </div>
                <button class="menu-toggle" onclick="toggleDrawer()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <?php if ($claimMessage): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($claimMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-content">
                <div class="profile-avatar">
                    <?php if ($user['avatar_url']): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 20px; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['display_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($user['display_name']); ?></h1>
                    <?php 
                    // Check if user has admin privileges
                    $user_telegram_id = (int)$user['telegram_id'];
                    $is_admin = in_array($user_telegram_id, AppConfig::ADMIN_IDS);
                    $is_owner = in_array($user_telegram_id, AppConfig::OWNER_IDS);
                    
                    // Display role badge
                    $display_role = $user['role'];
                    $role_class = strtolower($user['role']);
                    
                    if ($is_owner) {
                        $display_role = 'OWNER';
                        $role_class = 'owner';
                    } elseif ($is_admin) {
                        $display_role = 'ADMIN';
                        $role_class = 'admin';
                    }
                    ?>
                    <span class="profile-role <?php echo $role_class; ?>">
                        <?php if ($is_owner): ?>
                            <i class="fas fa-crown"></i>
                        <?php elseif ($is_admin): ?>
                            <i class="fas fa-shield-alt"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($display_role); ?>
                    </span>
                    <div class="profile-stats">
                        <div class="profile-stat" style="background: linear-gradient(135deg, rgba(29, 161, 242, 0.2), rgba(139, 92, 246, 0.2)); padding: 16px; border-radius: 16px; border: 2px solid var(--accent-blue);">
                            <span class="profile-stat-value" style="font-size: 36px; color: var(--accent-blue); text-shadow: 0 0 20px rgba(29, 161, 242, 0.5);">
                                <i class="fas fa-coins" style="font-size: 28px; margin-right: 8px;"></i>
                                <?php echo number_format($user['credits']); ?>
                            </span>
                            <span class="profile-stat-label" style="font-size: 14px; font-weight: 600; margin-top: 8px;">Live Credits</span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-stat-value"><?php echo number_format($user['xcoin_balance']); ?></span>
                            <span class="profile-stat-label">XCoin</span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-stat-value"><?php echo formatDate($user['last_login_at'] ?? null, 'M d'); ?></span>
                            <span class="profile-stat-label">Last Login</span>
                        </div>
                    </div>
                    
                    <?php if ($is_admin || $is_owner): ?>
                    <div class="admin-access-button">
                        <a href="admin/admin_access.php" class="admin-btn">
                            <i class="fas fa-shield-alt"></i>
                            <span>Admin Panel</span>
                            <?php if ($is_owner): ?>
                                <span class="admin-badge owner">OWNER</span>
                            <?php else: ?>
                                <span class="admin-badge admin">ADMIN</span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Website Messages -->
        <?php 
        require_once 'website_messages.php';
        if (hasActiveWebsiteMessages()): 
        ?>
        <div class="website-messages-section mb-4">
            <h2 class="section-title">
                <i class="fas fa-megaphone"></i> Website Announcements
            </h2>
            <?php includeWebsiteMessages(); ?>
        </div>
        <?php endif; ?>

        <!-- Credits Display Card -->
        <div class="cards-grid">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: 2px solid var(--accent-blue); box-shadow: 0 8px 32px rgba(29, 161, 242, 0.3);">
                <div class="card-header">
                    <h3 class="card-title" style="color: white;">Live Credits Balance</h3>
                    <div class="card-icon" style="background: rgba(255, 255, 255, 0.2);">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="card-value" id="liveCreditsDisplay" style="color: white; font-size: 48px; text-shadow: 0 0 20px rgba(255, 255, 255, 0.5);">
                    <?php echo number_format($user['credits']); ?>
                </div>
                <div class="card-subtitle" style="color: rgba(255, 255, 255, 0.9);">Available Now</div>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255, 255, 255, 0.2);">
                    <div style="display: flex; justify-content: space-between; color: rgba(255, 255, 255, 0.8); font-size: 12px;">
                        <span><i class="fas fa-wallet"></i> XCoin: <?php echo number_format($user['xcoin_balance']); ?></span>
                        <span><i class="fas fa-sync-alt" id="creditsRefreshIcon" style="cursor: pointer;" onclick="refreshCredits()"></i> Refresh</span>
                    </div>
                </div>
            </div>

            <div class="card card-purple">
                <div class="card-header">
                    <h3 class="card-title">Total Hits</h3>
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($userStats['total_hits'] ?? 0); ?></div>
                <div class="card-subtitle">All time</div>
            </div>

            <div class="card card-orange">
                <div class="card-header">
                    <h3 class="card-title">Charge Cards</h3>
                    <div class="card-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($userStats['total_charge_cards'] ?? 0); ?></div>
                <div class="card-subtitle">Successful charges</div>
            </div>

            <div class="card card-green">
                <div class="card-header">
                    <h3 class="card-title">Live Cards</h3>
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($userStats['total_live_cards'] ?? 0); ?></div>
                <div class="card-subtitle">Valid cards found</div>
            </div>

            <div class="card card-pink">
                <div class="card-header">
                    <h3 class="card-title">Expiry Date</h3>
                    <div class="card-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="card-value"><?php 
                $expiryDate = $userStats['expiry_date'] ?? null;
                if ($expiryDate) {
                    if (is_object($expiryDate) && method_exists($expiryDate, 'toDateTime')) {
                        echo $expiryDate->toDateTime()->format('M d, Y');
                    } elseif (is_numeric($expiryDate)) {
                        echo date('M d, Y', $expiryDate);
                    } else {
                        echo date('M d, Y', strtotime($expiryDate));
                    }
                } else {
                    echo 'N/A';
                }
                ?></div>
                <div class="card-subtitle">Premium expires</div>
            </div>
        </div>

        <!-- Credit System Cards -->
        <div class="cards-grid">
            <!-- Credit Claim Center Card -->
            <div class="card credit-claim-card">
                <div class="card-header">
                    <h3 class="card-title">Credit Claim Center</h3>
                    <div class="card-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="card-value">Claim Codes</div>
                <div class="card-subtitle">Premium & Credit Codes</div>
                <a href="credit_claim.php" class="claim-button">
                    <i class="fas fa-rocket"></i> Claim Now
                </a>
                <button id="claimCreditsBtn" class="claim-button" style="margin-top:10px;background:rgba(0,0,0,0.25)">
                    <i class="fas fa-gift"></i> Daily Claim
                </button>
                <small class="countdown">Claim free credits once per day</small>
            </div>

            <!-- User Stats Card -->
            <div class="card user-stats-card">
                <div class="card-header">
                    <h3 class="card-title">Account Status</h3>
                    <div class="card-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo ucfirst($user['role'] ?? 'free'); ?></div>
                <div class="card-subtitle">Account Type</div>
                <div class="account-info">
                    <small class="text-muted">
                        <i class="fas fa-clock"></i> Member since: <?php 
                        $createdAt = $user['created_at'] ?? time();
                        if (is_object($createdAt) && method_exists($createdAt, 'toDateTime')) {
                            echo $createdAt->toDateTime()->format('M Y');
                        } elseif (is_numeric($createdAt)) {
                            echo date('M Y', $createdAt);
                        } else {
                            echo date('M Y', strtotime($createdAt) ?: time());
                        }
                        ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Quick Tools Section -->
        <h2 class="section-title" style="cursor: pointer; user-select: none;" onclick="toggleQuickTools()">
            <i class="fas fa-tools"></i> Quick Tools
            <i class="fas fa-chevron-down" id="toolsChevron" style="float: right; transition: transform 0.3s ease; font-size: 16px;"></i>
        </h2>
        <div id="quickToolsSection" style="display: none;">
            <div class="cards-grid">
                <a href="card_checker.php" class="card card-blue" style="text-decoration: none; cursor: pointer;">
                    <div class="card-header">
                        <h3 class="card-title">Card Checker</h3>
                        <div class="card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                    <div class="card-subtitle">Check credit cards validity</div>
                </a>

                <a href="site_checker.php" class="card card-green" style="text-decoration: none; cursor: pointer;">
                    <div class="card-header">
                        <h3 class="card-title">Site Checker</h3>
                        <div class="card-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                    <div class="card-subtitle">Verify site availability</div>
                </a>

                <a href="tools.php" class="card card-purple" style="text-decoration: none; cursor: pointer;">
                    <div class="card-header">
                        <h3 class="card-title">All Tools</h3>
                        <div class="card-icon">
                            <i class="fas fa-toolbox"></i>
                        </div>
                    </div>
                    <div class="card-subtitle">View all available tools</div>
                </a>

                <a href="wallet.php" class="card card-orange" style="text-decoration: none; cursor: pointer;">
                    <div class="card-header">
                        <h3 class="card-title">Wallet</h3>
                        <div class="card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="card-subtitle">Manage your XCoin</div>
                </a>
            </div>
        </div>

        <!-- Global Statistics -->
        <h2 class="section-title">Global Statistics</h2>
        <div class="cards-grid">
            <div class="card card-blue">
                <div class="card-header">
                    <h3 class="card-title">Total Users</h3>
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($globalStats['total_users']); ?></div>
                <div class="card-subtitle">
                    <i class="fas fa-circle" style="color: #00ff88; font-size: 8px; margin-right: 4px;"></i>
                    <?php echo number_format($onlineCount); ?> Online Now
                </div>
            </div>

            <div class="card card-purple">
                <div class="card-header">
                    <h3 class="card-title">Total Hits</h3>
                    <div class="card-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($globalStats['total_hits']); ?></div>
                <div class="card-subtitle">All time checks</div>
            </div>

            <div class="card card-orange">
                <div class="card-header">
                    <h3 class="card-title">Charge Cards</h3>
                    <div class="card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($globalStats['total_charge_cards']); ?></div>
                <div class="card-subtitle">Global charges</div>
            </div>

            <div class="card card-green">
                <div class="card-header">
                    <h3 class="card-title">Live Cards</h3>
                    <div class="card-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
                <div class="card-value"><?php echo number_format($globalStats['total_live_cards']); ?></div>
                <div class="card-subtitle">Global live cards</div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="tools.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Tools</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </div>
    </div>

    <!-- Side Drawer -->
    <div class="drawer-overlay" onclick="toggleDrawer()"></div>
    <div class="drawer">
        <div class="drawer-header">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                LEGEND CHECKER
            </div>
        </div>
        <div class="drawer-menu">
            <a href="dashboard.php" class="drawer-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="wallet.php" class="drawer-item">
                <i class="fas fa-coins"></i>
                <span>Deposit XCoin</span>
            </a>
            <a href="credit_claim.php" class="drawer-item">
                <i class="fas fa-gift"></i>
                <span>Claim Codes</span>
            </a>
            <a href="premium.php" class="drawer-item">
                <i class="fas fa-crown"></i>
                <span>Buy Premium</span>
            </a>
            <a href="redeem.php" class="drawer-item">
                <i class="fas fa-gift"></i>
                <span>Redeem</span>
            </a>
            <a href="card_checker.php" class="drawer-item">
                <i class="fas fa-credit-card"></i>
                <span>Card Checker</span>
            </a>
            <a href="site_checker.php" class="drawer-item">
                <i class="fas fa-globe"></i>
                <span>Site Checker</span>
            </a>
            <a href="tools.php" class="drawer-item">
                <i class="fas fa-tools"></i>
                <span>Tools</span>
            </a>
            <a href="proxy_manager.php" class="drawer-item">
                <i class="fas fa-server"></i>
                <span>Proxy Manager</span>
            </a>
            <a href="settings.php" class="drawer-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="drawer-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <script src="assets/js/main.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: false, 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            document.getElementById('currentTime').textContent = timeString;
        }



        // Drawer toggle
        function toggleDrawer() {
            const overlay = document.querySelector('.drawer-overlay');
            const drawer = document.querySelector('.drawer');
            
            overlay.classList.toggle('active');
            drawer.classList.toggle('active');
        }

        // Initialize
        updateTime();
        setInterval(updateTime, 1000);

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);

        // Refresh credits function
        function refreshCredits() {
            const icon = document.getElementById('creditsRefreshIcon');
            if (icon) {
                icon.classList.add('fa-spin');
            }
            
            fetch('dashboard.php?ajax=credits')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const creditsDisplay = document.getElementById('liveCreditsDisplay');
                    if (creditsDisplay) {
                        creditsDisplay.textContent = parseInt(data.credits).toLocaleString();
                    }
                    // Also update profile stat
                    const profileStat = document.querySelector('.profile-stat-value');
                    if (profileStat) {
                        profileStat.innerHTML = '<i class="fas fa-coins" style="font-size: 28px; margin-right: 8px;"></i>' + parseInt(data.credits).toLocaleString();
                    }
                }
            })
            .catch(error => {
                console.error('Error refreshing credits:', error);
            })
            .finally(() => {
                if (icon) {
                    icon.classList.remove('fa-spin');
                }
            });
        }

        // Make refreshCredits globally available
        window.refreshCredits = refreshCredits;

        // Auto-refresh credits every 30 seconds
        setInterval(refreshCredits, 30000);

        function toggleQuickTools() {
            const section = document.getElementById('quickToolsSection');
            const chevron = document.getElementById('toolsChevron');
            
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                section.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Make functions globally available
        window.toggleQuickTools = toggleQuickTools;
    </script>
    <script nonce="<?php echo $nonce; ?>">
        // Initialize enhanced functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in class to main content
            document.querySelector('.container').classList.add('fade-in');
            
            // Add stagger animation to cards
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
            
            // Add pulse animation to profile header
            const profileHeader = document.querySelector('.profile-header');
            if (profileHeader) {
                profileHeader.style.animation = 'pulse 2s ease-in-out infinite';
            }
        });
    </script>
</body>
</html>
