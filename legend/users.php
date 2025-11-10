<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get online users and leaderboard
$onlineUsers = $db->getOnlineUsers(20);
$topUsers = $db->getTopUsers(20);

// Update presence
$db->updatePresence($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEGEND CHECKER - Users</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            padding-bottom: 100px;
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

        .back-button {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: var(--bg-card-hover);
            transform: translateY(-1px);
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: var(--bg-card);
            border-radius: 16px;
            padding: 4px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .tab {
            flex: 1;
            background: none;
            border: none;
            color: var(--text-secondary);
            padding: 16px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: var(--accent-blue);
            color: white;
        }

        .tab:hover:not(.active) {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* User Lists */
        .user-list {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .user-list-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .user-list-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .user-list-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .user-item {
            display: flex;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .user-item:hover {
            background: var(--bg-card-hover);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            font-weight: 700;
            margin-right: 16px;
            position: relative;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            object-fit: cover;
        }

        .presence-dot {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 14px;
            height: 14px;
            background: var(--success-color);
            border: 2px solid var(--bg-card);
            border-radius: 50%;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .user-username {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .user-role {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
        }

        .user-role.free {
            background: rgba(107, 114, 128, 0.2);
            color: var(--text-secondary);
        }

        .user-role.premium {
            background: rgba(139, 92, 246, 0.2);
            color: var(--accent-purple);
        }

        .user-role.admin {
            background: rgba(245, 158, 11, 0.2);
            color: var(--accent-orange);
        }

        .user-stats {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-stat {
            text-align: right;
        }

        .user-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-blue);
            display: block;
        }

        .user-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text-primary);
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

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                padding: 16px 0;
            }

            .user-item {
                padding: 16px;
            }

            .user-stats {
                flex-direction: column;
                gap: 8px;
            }

            .user-stat {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <i class="fas fa-users"></i>
                Users
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('online')">
                <i class="fas fa-circle" style="color: var(--success-color);"></i>
                Online Users
            </button>
            <button class="tab" onclick="switchTab('leaderboard')">
                <i class="fas fa-trophy"></i>
                Top Users
            </button>
        </div>

        <!-- Online Users Tab -->
        <div id="online-tab" class="tab-content active">
            <div class="user-list">
                <div class="user-list-header">
                    <h2 class="user-list-title">Online Users</h2>
                    <p class="user-list-subtitle">Users active in the last 5 minutes</p>
                </div>
                <?php if (empty($onlineUsers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No users online</h3>
                        <p>Check back later to see who's active</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($onlineUsers as $onlineUser): ?>
                        <div class="user-item">
                            <div class="user-avatar">
                                <?php 
                                $user = $onlineUser['user'] ?? $onlineUser ?? [];
                                if (!empty($user['avatar_url'])): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['display_name'] ?? 'U', 0, 1)); ?>
                                <?php endif; ?>
                                <div class="presence-dot"></div>
                            </div>
                            <div class="user-info">
                                <div class="user-name">
                                    <?php 
                                    $user = $onlineUser['user'] ?? $onlineUser ?? [];
                                    echo htmlspecialchars($user['display_name'] ?? 'Unknown User'); 
                                    ?>
                                    <span class="user-role <?php echo strtolower($user['role'] ?? 'free'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'Free')); ?>
                                    </span>
                                </div>
                                <?php if (!empty($user['username'])): ?>
                                    <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-stats">
                                <div class="user-stat">
                                    <span class="user-stat-value"><?php echo formatNumber($user['credits'] ?? 0); ?></span>
                                    <span class="user-stat-label">Credits</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leaderboard Tab -->
        <div id="leaderboard-tab" class="tab-content">
            <div class="user-list">
                <div class="user-list-header">
                    <h2 class="user-list-title">Top Users</h2>
                    <p class="user-list-subtitle">Ranked by total hits</p>
                </div>
                <?php if (empty($topUsers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <h3>No rankings yet</h3>
                        <p>Start checking cards to appear on the leaderboard</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($topUsers as $index => $topUser): ?>
                        <div class="user-item">
                            <div class="user-avatar">
                                <?php 
                                $topUserData = $topUser['user'] ?? $topUser ?? [];
                                if (!empty($topUserData['avatar_url'])): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($topUserData['avatar_url']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($topUserData['display_name'] ?? 'U', 0, 1)); ?>
                                <?php endif; ?>
                                <?php if ($index < 3): ?>
                                    <div class="presence-dot" style="background: <?php echo $index === 0 ? '#ffd700' : ($index === 1 ? '#c0c0c0' : '#cd7f32'); ?>"></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name">
                                    <?php if ($index === 0): ?>
                                        <i class="fas fa-crown" style="color: #ffd700; margin-right: 8px;"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($topUserData['display_name'] ?? 'Unknown User'); ?>
                                    <span class="user-role <?php echo strtolower($topUserData['role'] ?? 'free'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($topUserData['role'] ?? 'Free')); ?>
                                    </span>
                                </div>
                                <?php if (!empty($topUserData['username'])): ?>
                                    <div class="user-username">@<?php echo htmlspecialchars($topUserData['username']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-stats">
                                <div class="user-stat">
                                    <span class="user-stat-value"><?php echo formatNumber($topUser['total_hits'] ?? 0); ?></span>
                                    <span class="user-stat-label">Hits</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="tools.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Tools</span>
            </a>
            <a href="users.php" class="nav-item active">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }

        // Auto-refresh online users every 30 seconds
        setInterval(() => {
            if (document.getElementById('online-tab').classList.contains('active')) {
                location.reload();
            }
        }, 30000);

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
