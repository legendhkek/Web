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

        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
        }

        .theme-toggle:hover {
            background: var(--bg-card-hover);
            transform: rotate(15deg);
        }

        .timer-chip {
            background: var(--bg-card);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
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

        /* Stats Chart Container */
        .stats-chart-container {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 28px;
            border: 1px solid var(--border-color);
        }

        .chart-wrapper {
            position: relative;
            height: 200px;
            margin-top: 20px;
        }

        .chart-bar {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 100%;
            gap: 8px;
        }

        .chart-bar-item {
            flex: 1;
            background: linear-gradient(180deg, var(--accent-blue), var(--accent-purple));
            border-radius: 8px 8px 0 0;
            position: relative;
            transition: all 0.3s ease;
            min-height: 20px;
            cursor: pointer;
        }

        .chart-bar-item:hover {
            opacity: 0.8;
            transform: scaleY(1.05);
        }

        .chart-bar-item::after {
            content: attr(data-value);
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .chart-bar-item:hover::after {
            opacity: 1;
        }

        /* Live Stats Indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--accent-green);
            margin-left: 12px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--accent-green);
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        /* Skeleton Loader */
        .skeleton {
            background: linear-gradient(90deg, var(--bg-card) 25%, var(--bg-card-hover) 50%, var(--bg-card) 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 8px;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Keyboard Shortcuts Modal */
        .shortcuts-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .shortcuts-modal.active {
            display: flex;
        }

        .shortcuts-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }

        .shortcut-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .shortcut-key {
            background: var(--bg-primary);
            padding: 4px 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            border: 1px solid var(--border-color);
        }

        /* Light Mode Styles */
        body.light-mode {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-card: #ffffff;
            --bg-card-hover: #f0f0f0;
            --text-primary: #1a1a1a;
            --text-secondary: #4a4a4a;
            --text-muted: #8a8a8a;
            --border-color: #e0e0e0;
        }

        body.light-mode .card {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        body.light-mode .profile-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        /* Loading State */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--accent-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Improved Card Hover Effects */
        .card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2), 0 0 20px rgba(29, 161, 242, 0.3);
        }

        /* Better Button Feedback */
        .claim-button {
            position: relative;
            overflow: hidden;
        }

        .claim-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .claim-button:active::before {
            width: 300px;
            height: 300px;
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
                flex-wrap: wrap;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .header {
                padding: 16px 0;
            }

            .header-actions {
                gap: 8px;
            }

            body {
                padding-bottom: 80px;
            }

            .chart-wrapper {
                height: 150px;
            }

            .nav-container {
                padding: 0 10px;
            }

            .profile-name {
                font-size: 22px;
            }

            .section-title {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .profile-name {
                font-size: 18px;
            }

            .profile-stat-value {
                font-size: 20px;
            }

            .card-value {
                font-size: 24px;
            }

            .timer-chip {
                font-size: 12px;
                padding: 6px 12px;
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
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                <button class="menu-toggle" onclick="toggleDrawer()" aria-label="Toggle menu">
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
                        <div class="profile-stat">
                            <span class="profile-stat-value"><?php echo number_format($user['credits']); ?></span>
                            <span class="profile-stat-label">Credits</span>
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

        <!-- Personal Stats Cards -->
        <div class="cards-grid">
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
                <small class="countdown" id="countdownText">Claim free credits once per day</small>
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

        <!-- Stats Chart -->
        <div class="stats-chart-container">
            <h2 class="section-title" style="margin-bottom: 0;">
                <i class="fas fa-chart-line"></i> Statistics Overview
                <span class="live-indicator">
                    <span class="live-dot"></span>
                    Live
                </span>
            </h2>
            <div class="chart-wrapper">
                <div class="chart-bar" id="statsChart">
                    <div class="chart-bar-item skeleton" style="height: 60%;"></div>
                    <div class="chart-bar-item skeleton" style="height: 80%;"></div>
                    <div class="chart-bar-item skeleton" style="height: 45%;"></div>
                    <div class="chart-bar-item skeleton" style="height: 70%;"></div>
                </div>
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
            <div class="drawer-item" onclick="toggleProxySection()">
                <i class="fas fa-server"></i>
                <span>Proxy Manager</span>
                <i class="fas fa-chevron-down" id="proxyChevron" style="margin-left: auto; transition: transform 0.3s ease;"></i>
            </div>
            <div id="proxySection" style="display: none; background: var(--bg-secondary); margin: 0 16px; border-radius: 8px;">
                <div class="proxy-controls" style="padding: 16px;">
                    <div style="margin-bottom: 12px;">
                        <input type="text" id="proxyInput" placeholder="proxy:port:user:pass" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-card); color: var(--text-primary); font-size: 12px;">
                    </div>
                    <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                        <button onclick="addProxy()" style="flex: 1; padding: 8px; background: var(--accent-green); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">Add Proxy</button>
                        <button onclick="checkProxy()" style="flex: 1; padding: 8px; background: var(--accent-blue); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">Check Proxy</button>
                    </div>
                    <div id="proxyStatus" style="font-size: 11px; color: var(--text-secondary); margin-bottom: 8px;"></div>
                    <div style="max-height: 120px; overflow-y: auto;">
                        <div id="proxyList" style="font-size: 11px;"></div>
                    </div>
                </div>
            </div>
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

    <!-- Keyboard Shortcuts Modal -->
    <div class="shortcuts-modal" id="shortcutsModal">
        <div class="shortcuts-content">
            <h2 style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fas fa-keyboard"></i> Keyboard Shortcuts</span>
                <button onclick="closeShortcutsModal()" style="background: none; border: none; color: var(--text-primary); font-size: 24px; cursor: pointer;">&times;</button>
            </h2>
            <div class="shortcut-item">
                <span><i class="fas fa-home"></i> Go to Dashboard</span>
                <span class="shortcut-key">G + D</span>
            </div>
            <div class="shortcut-item">
                <span><i class="fas fa-tools"></i> Go to Tools</span>
                <span class="shortcut-key">G + T</span>
            </div>
            <div class="shortcut-item">
                <span><i class="fas fa-users"></i> Go to Users</span>
                <span class="shortcut-key">G + U</span>
            </div>
            <div class="shortcut-item">
                <span><i class="fas fa-moon"></i> Toggle Theme</span>
                <span class="shortcut-key">T</span>
            </div>
            <div class="shortcut-item">
                <span><i class="fas fa-bars"></i> Toggle Menu</span>
                <span class="shortcut-key">M</span>
            </div>
            <div class="shortcut-item">
                <span><i class="fas fa-question-circle"></i> Show Shortcuts</span>
                <span class="shortcut-key">?</span>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <script src="assets/js/main.js" nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
        // Theme Management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.body.classList.toggle('light-mode', savedTheme === 'light');
            updateThemeIcon(savedTheme);
        }

        function toggleTheme() {
            const isLight = document.body.classList.toggle('light-mode');
            const theme = isLight ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            updateThemeIcon(theme);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: false, 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            const timeEl = document.getElementById('currentTime');
            if (timeEl) {
                timeEl.textContent = timeString;
            }
        }

        // Stats Chart
        function updateStatsChart() {
            const chart = document.getElementById('statsChart');
            if (!chart) return;

            const stats = {
                hits: <?php echo $userStats['total_hits'] ?? 0; ?>,
                charges: <?php echo $userStats['total_charge_cards'] ?? 0; ?>,
                live: <?php echo $userStats['total_live_cards'] ?? 0; ?>,
                credits: <?php echo $user['credits'] ?? 0; ?>
            };

            const maxValue = Math.max(...Object.values(stats), 1);
            const labels = ['Hits', 'Charges', 'Live', 'Credits'];
            const values = [stats.hits, stats.charges, stats.live, stats.credits];

            chart.innerHTML = values.map((value, index) => {
                const height = (value / maxValue) * 100;
                return `<div class="chart-bar-item" style="height: ${height}%;" data-value="${value.toLocaleString()}"></div>`;
            }).join('');

            // Add labels
            const labelsContainer = document.createElement('div');
            labelsContainer.style.display = 'flex';
            labelsContainer.style.justifyContent = 'space-around';
            labelsContainer.style.marginTop = '10px';
            labelsContainer.style.fontSize = '12px';
            labelsContainer.style.color = 'var(--text-secondary)';
            labels.forEach(label => {
                const labelEl = document.createElement('div');
                labelEl.textContent = label;
                labelsContainer.appendChild(labelEl);
            });
            chart.parentElement.appendChild(labelsContainer);
        }

        // Real-time stats update
        function updateStats() {
            fetch('api/stats.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stats cards dynamically
                    updateStatsChart();
                }
            })
            .catch(err => console.warn('Stats update failed:', err));
        }

        // Keyboard Shortcuts
        function initKeyboardShortcuts() {
            let keysPressed = {};
            
            document.addEventListener('keydown', (e) => {
                keysPressed[e.key.toLowerCase()] = true;
                
                // Single key shortcuts
                if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    openShortcutsModal();
                }
                if (e.key === 't' && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    toggleTheme();
                }
                if (e.key === 'm' && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    toggleDrawer();
                }
                
                // Multi-key shortcuts (G + X)
                if (keysPressed['g']) {
                    if (keysPressed['d']) {
                        e.preventDefault();
                        window.location.href = 'dashboard.php';
                    } else if (keysPressed['t']) {
                        e.preventDefault();
                        window.location.href = 'tools.php';
                    } else if (keysPressed['u']) {
                        e.preventDefault();
                        window.location.href = 'users.php';
                    }
                }
            });
            
            document.addEventListener('keyup', (e) => {
                delete keysPressed[e.key.toLowerCase()];
            });
        }

        function openShortcutsModal() {
            document.getElementById('shortcutsModal').classList.add('active');
        }

        function closeShortcutsModal() {
            document.getElementById('shortcutsModal').classList.remove('active');
        }



        // Drawer toggle
        function toggleDrawer() {
            const overlay = document.querySelector('.drawer-overlay');
            const drawer = document.querySelector('.drawer');
            
            overlay.classList.toggle('active');
            drawer.classList.toggle('active');
        }

        // Initialize
        initTheme();
        updateTime();
        setInterval(updateTime, 1000);
        updateStatsChart();
        initKeyboardShortcuts();

        // Theme toggle event
        document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);

        // Update stats every 30 seconds
        setInterval(updateStats, 30000);

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);

        // Proxy management functions
        let proxies = JSON.parse(localStorage.getItem('proxies') || '[]');
        let currentProxyIndex = 0;

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

        function toggleProxySection() {
            const section = document.getElementById('proxySection');
            const chevron = document.getElementById('proxyChevron');
            
            if (section.style.display === 'none') {
                section.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
                loadProxyList();
            } else {
                section.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function addProxy() {
            const input = document.getElementById('proxyInput');
            const proxy = input.value.trim();
            
            if (!proxy) {
                updateProxyStatus('Please enter a proxy', 'error');
                return;
            }
            
            if (proxies.includes(proxy)) {
                updateProxyStatus('Proxy already exists', 'warning');
                return;
            }
            
            // Validate proxy format first
            const parts = proxy.split(':');
            if (parts.length !== 4) {
                updateProxyStatus('Invalid format. Use: host:port:user:pass', 'error');
                return;
            }
            
            updateProxyStatus('Validating proxy...', 'info');
            
            // Test proxy before adding
            fetch('check_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proxy: proxy })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'live') {
                    proxies.push(proxy);
                    localStorage.setItem('proxies', JSON.stringify(proxies));
                    input.value = '';
                    loadProxyList();
                    updateProxyStatus(`Proxy added successfully (${data.country || 'Unknown'})`, 'success');
                } else {
                    updateProxyStatus(`Cannot add dead proxy: ${data.error || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                updateProxyStatus('Error validating proxy', 'error');
            });
        }

        function checkProxy() {
            const input = document.getElementById('proxyInput');
            const proxy = input.value.trim();
            
            if (!proxy) {
                updateProxyStatus('Please enter a proxy to check', 'error');
                return;
            }
            
            updateProxyStatus('Checking proxy...', 'info');
            
            // Simple proxy format validation
            const parts = proxy.split(':');
            if (parts.length !== 4) {
                updateProxyStatus('Invalid format. Use: host:port:user:pass', 'error');
                return;
            }
            
            // Test proxy with a simple request
            fetch('check_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proxy: proxy })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'live') {
                    updateProxyStatus(`Proxy is LIVE (${data.country || 'Unknown'})`, 'success');
                } else {
                    updateProxyStatus(`Proxy is DEAD: ${data.error || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                updateProxyStatus('Error checking proxy', 'error');
            });
        }

        function removeProxy(index) {
            proxies.splice(index, 1);
            localStorage.setItem('proxies', JSON.stringify(proxies));
            loadProxyList();
            updateProxyStatus('Proxy removed', 'info');
        }

        function loadProxyList() {
            const list = document.getElementById('proxyList');
            if (proxies.length === 0) {
                list.innerHTML = '<div style="color: var(--text-muted); text-align: center; padding: 8px;">No proxies added</div>';
                return;
            }
            
            list.innerHTML = proxies.map((proxy, index) => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 8px; margin: 2px 0; background: var(--bg-card); border-radius: 4px;">
                    <span style="color: var(--text-primary); font-family: monospace; font-size: 10px;">${proxy}</span>
                    <div style="display: flex; gap: 4px;">
                        <button onclick="testSingleProxy(${index})" style="background: var(--accent-blue); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 9px; cursor: pointer;">Test</button>
                        <button onclick="removeProxy(${index})" style="background: var(--error-color); color: white; border: none; border-radius: 3px; padding: 2px 6px; font-size: 10px; cursor: pointer;">×</button>
                    </div>
                </div>
            `).join('');
        }

        function updateProxyStatus(message, type) {
            const status = document.getElementById('proxyStatus');
            const colors = {
                success: 'var(--success-color)',
                error: 'var(--error-color)',
                warning: 'var(--warning-color)',
                info: 'var(--accent-blue)'
            };
            
            status.textContent = message;
            status.style.color = colors[type] || 'var(--text-secondary)';
            
            setTimeout(() => {
                status.textContent = '';
            }, 3000);
        }

        function getNextProxy() {
            if (proxies.length === 0) return null;
            
            const proxy = proxies[currentProxyIndex];
            currentProxyIndex = (currentProxyIndex + 1) % proxies.length;
            return proxy;
        }

        function testSingleProxy(index) {
            const proxy = proxies[index];
            updateProxyStatus(`Testing ${proxy}...`, 'info');
            
            fetch('check_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proxy: proxy })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'live') {
                    updateProxyStatus(`Proxy ${index + 1} is LIVE (${data.country || 'Unknown'})`, 'success');
                } else {
                    updateProxyStatus(`Proxy ${index + 1} is DEAD: ${data.error || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                updateProxyStatus(`Error testing proxy ${index + 1}`, 'error');
            });
        }

        // Helper function to show notifications
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = 'success-message';
            
            if (type === 'error') {
                notification.style.background = 'rgba(239, 68, 68, 0.1)';
                notification.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                notification.style.color = 'var(--error-color)';
                notification.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
            } else if (type === 'success') {
                notification.style.background = 'rgba(16, 185, 129, 0.1)';
                notification.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                notification.style.color = 'var(--success-color)';
                notification.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
            } else {
                notification.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
            }
            
            const container = document.querySelector('.container');
            if (container) {
                container.insertBefore(notification, container.firstChild);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.3s ease';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            }
            
            return notification;
        }

        // Daily credit claim functionality
        const claimCreditsBtn = document.getElementById('claimCreditsBtn');
        if (claimCreditsBtn) {
            claimCreditsBtn.addEventListener('click', async function() {
                const originalText = this.innerHTML;
                const originalDisabled = this.disabled;
                
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';
                
                try {
                    const response = await fetch('api/claim_credits.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin'
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        showNotification(result.message, 'success');
                        
                        // Update countdown if next claim time provided
                        if (result.next_claim_time) {
                            updateCountdown(result.next_claim_time);
                        }
                        
                        // Reload page after 2 seconds to show updated credits
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        // Show error message
                        showNotification(result.message, 'error');
                        
                        // Update countdown if next claim time provided
                        if (result.next_claim_time) {
                            updateCountdown(result.next_claim_time);
                        }
                        
                        this.disabled = false;
                        this.innerHTML = originalText;
                    }
                } catch (error) {
                    console.error('Claim error:', error);
                    showNotification('Network error occurred. Please try again.', 'error');
                    
                    this.disabled = false;
                    this.innerHTML = originalText;
                }
            });
        }
        
        // Update countdown timer
        function updateCountdown(nextClaimTime) {
            const countdownText = document.getElementById('countdownText');
            if (!countdownText || !nextClaimTime) return;
            
            function update() {
                const now = Math.floor(Date.now() / 1000);
                const diff = nextClaimTime - now;
                
                if (diff <= 0) {
                    countdownText.textContent = 'Claim free credits once per day';
                    return;
                }
                
                const hours = Math.floor(diff / 3600);
                const minutes = Math.floor((diff % 3600) / 60);
                const seconds = diff % 60;
                
                if (hours > 0) {
                    countdownText.textContent = `Next claim available in ${hours}h ${minutes}m ${seconds}s`;
                } else if (minutes > 0) {
                    countdownText.textContent = `Next claim available in ${minutes}m ${seconds}s`;
                } else {
                    countdownText.textContent = `Next claim available in ${seconds}s`;
                }
            }
            
            update();
            setInterval(update, 1000);
        }
        
        // Make functions globally available
        window.toggleQuickTools = toggleQuickTools;
        window.toggleProxySection = toggleProxySection;
        window.addProxy = addProxy;
        window.checkProxy = checkProxy;
        window.removeProxy = removeProxy;
        window.getNextProxy = getNextProxy;
        window.testSingleProxy = testSingleProxy;
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
