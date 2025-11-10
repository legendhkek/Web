<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

$onlineUsers = $db->getOnlineUsers(40);
$topUsers = $db->getTopUsers(20);
$db->updatePresence($userId);

$onlineCount = count($onlineUsers);
$topPreview = array_slice($topUsers, 0, 10);
$onlinePreview = array_slice($onlineUsers, 0, 20);

$themeDefault = 'dark';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legend Checker · Live Members</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/enhanced.css">
    <script nonce="<?php echo $nonce; ?>">
        try {
            const savedTheme = localStorage.getItem('legend_theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        } catch (e) {}
    </script>
</head>
<body class="page page-users" data-theme="<?php echo htmlspecialchars($themeDefault); ?>">
    <div class="page-shell">
        <header class="page-header">
            <div class="page-header__left">
                <div class="brand">
                    <div class="brand__icon"><i class="fas fa-users-line"></i></div>
                    <div class="brand__meta">
                        <span class="brand__name">Legend Network</span>
                        <span class="brand__tagline">Live presence & rankings</span>
                    </div>
                </div>
                <span class="badge"><i class="fas fa-satellite-dish"></i> Live feed</span>
            </div>
            <div class="page-header__actions">
                <div class="chip chip--success">
                    <i class="fas fa-circle"></i>
                    <span><?php echo formatNumber($onlineCount); ?> online</span>
                </div>
                <button class="btn btn--ghost" data-action="toggle-theme">
                    <i class="fas fa-moon" data-theme-icon></i>
                    <span data-theme-label>Dark</span>
                </button>
                <button class="btn btn--ghost menu-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <div class="dashboard-grid" style="margin-top:0;">
            <div class="dashboard-grid__main">
                <section class="card stagger">
                    <div class="card__head">
                        <div>
                            <h2 class="card__title">Active Members</h2>
                            <p class="card__subtitle">Users who have pinged presence in the last 5 minutes.</p>
                        </div>
                        <div class="card__icon"><i class="fas fa-signal"></i></div>
                    </div>
                    <?php if (empty($onlinePreview)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <h3>No active sessions</h3>
                            <p>When members come online they will appear here instantly.</p>
                        </div>
                    <?php else: ?>
                        <div class="widget__list scroller">
                            <?php foreach ($onlinePreview as $onlineUser): ?>
                                <?php
                                    $onlineData = $onlineUser['user'] ?? $onlineUser ?? [];
                                    $displayName = htmlspecialchars($onlineData['display_name'] ?? 'Unknown User');
                                    $username = $onlineData['username'] ?? null;
                                    $role = ucfirst($onlineData['role'] ?? 'free');
                                    $credits = formatNumber($onlineData['credits'] ?? 0);
                                    $lastSeenRaw = $onlineUser['last_seen_at'] ?? null;
                                    $lastSeen = $lastSeenRaw ? timeAgo($lastSeenRaw) : 'Active now';
                                ?>
                                <div class="list-item">
                                    <div class="list-item__avatar">
                                        <?php if (!empty($onlineData['avatar_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($onlineData['avatar_url']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($onlineData['display_name'] ?? 'U', 0, 1)); ?>
                                        <?php endif; ?>
                                        <span class="presence-indicator presence-indicator--online"></span>
                                    </div>
                                    <div class="list-item__body">
                                        <div class="list-item__title">
                                            <?php echo $displayName; ?>
                                            <span class="pill" style="font-size:0.7rem;"><?php echo htmlspecialchars($role); ?></span>
                                        </div>
                                        <div class="list-item__subtitle">
                                            <?php if ($username): ?>@<?php echo htmlspecialchars($username); ?> · <?php endif; ?>
                                            <?php echo htmlspecialchars($lastSeen); ?>
                                        </div>
                                    </div>
                                    <div class="list-item__value"><?php echo $credits; ?> cr</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="dashboard-grid__aside">
                <section class="widget stagger">
                    <div class="widget__header">
                        <h3 class="widget__title"><i class="fas fa-trophy"></i> Performance Leaderboard</h3>
                        <span class="widget__meta">Top 10 by total hits</span>
                    </div>
                    <?php if (empty($topPreview)): ?>
                        <div class="empty-state">
                            <i class="fas fa-ranking-star"></i>
                            <h3>No rankings yet</h3>
                            <p>Execute more checks to populate the leaderboard.</p>
                        </div>
                    <?php else: ?>
                        <div class="widget__list scroller">
                            <?php foreach ($topPreview as $index => $topUser): ?>
                                <?php
                                    $leaderData = $topUser['user'] ?? $topUser ?? [];
                                    $leaderName = htmlspecialchars($leaderData['display_name'] ?? 'Unknown User');
                                    $leaderUsername = $leaderData['username'] ?? null;
                                    $leaderRole = ucfirst($leaderData['role'] ?? 'free');
                                    $leaderHits = formatNumber($topUser['total_hits'] ?? 0);
                                    $indicatorClass = $index === 0 ? 'presence-indicator--gold' : 'presence-indicator--online';
                                ?>
                                <div class="list-item">
                                    <div class="list-item__avatar">
                                        <?php if (!empty($leaderData['avatar_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($leaderData['avatar_url']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($leaderData['display_name'] ?? 'U', 0, 1)); ?>
                                        <?php endif; ?>
                                        <span class="presence-indicator <?php echo $indicatorClass; ?>"></span>
                                    </div>
                                    <div class="list-item__body">
                                        <div class="list-item__title">
                                            <?php if ($index === 0): ?><i class="fas fa-crown" style="color:#facc15;"></i><?php endif; ?>
                                            <?php echo $leaderName; ?>
                                            <span class="pill" style="font-size:0.7rem;"><?php echo htmlspecialchars($leaderRole); ?></span>
                                        </div>
                                        <div class="list-item__subtitle">
                                            Rank #<?php echo $index + 1; ?>
                                            <?php if ($leaderUsername): ?> · @<?php echo htmlspecialchars($leaderUsername); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="list-item__value"><?php echo $leaderHits; ?> hits</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>

    <div class="bottom-nav">
        <nav class="bottom-nav__items">
            <a href="dashboard.php" class="bottom-nav__item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="tools.php" class="bottom-nav__item">
                <i class="fas fa-tools"></i>
                <span>Tools</span>
            </a>
            <a href="users.php" class="bottom-nav__item bottom-nav__item--active">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="wallet.php" class="bottom-nav__item">
                <i class="fas fa-wallet"></i>
                <span>Wallet</span>
            </a>
        </nav>
    </div>

    <div class="drawer-overlay"></div>
    <aside class="drawer">
        <div class="drawer-header">
            <div class="brand">
                <div class="brand__icon"><i class="fas fa-shield"></i></div>
                <div class="brand__meta">
                    <span class="brand__name">Legend</span>
                    <span class="brand__tagline">Command Center</span>
                </div>
            </div>
        </div>
        <nav class="drawer-menu">
            <a href="dashboard.php" class="drawer-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="wallet.php" class="drawer-item"><i class="fas fa-coins"></i> Deposit XCoin</a>
            <a href="credit_claim.php" class="drawer-item"><i class="fas fa-gift"></i> Claim Codes</a>
            <a href="premium.php" class="drawer-item"><i class="fas fa-crown"></i> Buy Premium</a>
            <a href="redeem.php" class="drawer-item"><i class="fas fa-ticket"></i> Redeem</a>
            <a href="card_checker.php" class="drawer-item"><i class="fas fa-credit-card"></i> Card Checker</a>
            <a href="site_checker.php" class="drawer-item"><i class="fas fa-globe"></i> Site Checker</a>
            <a href="tools.php" class="drawer-item"><i class="fas fa-tools"></i> Tools</a>
            <a href="settings.php" class="drawer-item"><i class="fas fa-gear"></i> Settings</a>
            <a href="logout.php" class="drawer-item"><i class="fas fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <script src="assets/js/main.js" nonce="<?php echo $nonce; ?>"></script>
</body>
</html>
