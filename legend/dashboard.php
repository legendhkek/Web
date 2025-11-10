<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';
require_once 'website_messages.php';

if (!function_exists('buildSparklinePoints')) {
    function buildSparklinePoints(int $total, int $segments = 6): array {
        $total = max(0, $total);
        $segments = max(1, $segments);

        if ($total <= 0) {
            $baseline = [];
            for ($i = 1; $i <= $segments; $i++) {
                $baseline[] = $i * 5;
            }
            return $baseline;
        }

        $step = max(1, (int)ceil($total / $segments));
        $points = [];
        for ($i = 1; $i <= $segments; $i++) {
            $value = $step * $i;
            $points[] = $value > $total ? $total : $value;
        }

        return $points;
    }
}

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

$user = $db->getUserByTelegramId($userId);
$user['xcoin_balance'] = $user['xcoin_balance'] ?? 0;
$user['credits'] = $user['credits'] ?? 0;
$user['avatar_url'] = $user['avatar_url'] ?? null;
$user['display_name'] = $user['display_name'] ?? ($user['first_name'] ?? 'User');
$user['role'] = $user['role'] ?? 'free';

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

$onlineUsers = $db->getOnlineUsers(100);
$onlineCount = count($onlineUsers);
$topUsers = $db->getTopUsers(5);
$db->updatePresence($userId);

$userHits = (int)($userStats['total_hits'] ?? 0);
$userCharge = (int)($userStats['total_charge_cards'] ?? 0);
$userLive = (int)($userStats['total_live_cards'] ?? 0);

$chargeRate = $userHits > 0 ? min(100, ($userCharge / $userHits) * 100) : 0.0;
$liveRate = $userHits > 0 ? min(100, ($userLive / $userHits) * 100) : 0.0;

$globalUsers = (int)($globalStats['total_users'] ?? 0);
$globalHits = (int)($globalStats['total_hits'] ?? 0);
$globalCharge = (int)($globalStats['total_charge_cards'] ?? 0);
$globalLive = (int)($globalStats['total_live_cards'] ?? 0);

$globalChargeRate = $globalHits > 0 ? min(100, ($globalCharge / $globalHits) * 100) : 0.0;
$globalLiveRate = $globalHits > 0 ? min(100, ($globalLive / $globalHits) * 100) : 0.0;

$userSparkline = buildSparklinePoints($userHits);
$globalSparkline = buildSparklinePoints($globalHits > 0 ? $globalHits : $globalUsers * 3);

$onlinePreview = array_slice($onlineUsers, 0, 7);
$topPreview = array_slice($topUsers, 0, 5);
$announcements = getWebsiteMessagesArray();
$hasAnnouncements = !empty($announcements);

$userTelegramId = (int)($user['telegram_id'] ?? 0);
$isAdmin = in_array($userTelegramId, AppConfig::ADMIN_IDS);
$isOwner = in_array($userTelegramId, AppConfig::OWNER_IDS);
$displayRole = $user['role'];
$roleClass = strtolower($user['role']);
if ($isOwner) {
    $displayRole = 'OWNER';
    $roleClass = 'owner';
} elseif ($isAdmin) {
    $displayRole = 'ADMIN';
    $roleClass = 'admin';
}

$memberSince = formatDate($user['created_at'] ?? time(), 'M Y');
$lastLogin = formatDate($user['last_login_at'] ?? null, 'M d, Y');
$expiryDateValue = $userStats['expiry_date'] ?? null;
$expiryLabel = 'N/A';
if ($expiryDateValue) {
    if (is_object($expiryDateValue) && method_exists($expiryDateValue, 'toDateTime')) {
        $expiryLabel = $expiryDateValue->toDateTime()->format('M d, Y');
    } elseif (is_numeric($expiryDateValue)) {
        $expiryLabel = date('M d, Y', (int)$expiryDateValue);
    } else {
        $expiryLabel = date('M d, Y', strtotime((string)$expiryDateValue));
    }
}

$themeDefault = 'dark';
$claimMessage = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEGEND CHECKER · Command Dashboard</title>
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
        } catch (e) {
        }
    </script>
</head>
<body class="page page-dashboard" data-theme="<?php echo htmlspecialchars($themeDefault); ?>">
    <div class="page-shell">
        <header class="page-header">
            <div class="page-header__left">
                <div class="brand">
                    <div class="brand__icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="brand__meta">
                        <span class="brand__name">Legend Checker</span>
                        <span class="brand__tagline">Advanced Intelligence</span>
                    </div>
                </div>
                <span class="badge"><i class="fas fa-bolt"></i> Real-time</span>
            </div>
            <div class="page-header__actions">
                <div class="chip chip--success">
                    <i class="fas fa-users"></i>
                    <span><?php echo formatNumber($globalUsers); ?> members</span>
                </div>
                <div class="chip">
                    <i class="fas fa-clock"></i>
                    <span class="js-current-time" data-format="HH:mm">--:--</span>
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

        <div class="stack stack--tight" data-inline-alerts>
            <?php if (!empty($claimMessage)): ?>
                <div class="alert">
                    <div class="alert__icon"><i class="fas fa-check-circle"></i></div>
                    <div class="alert__content">
                        <div class="alert__title">Success</div>
                        <div class="alert__text"><?php echo htmlspecialchars($claimMessage); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <section class="card profile-card stagger">
            <div class="profile-card__header">
                <div class="profile-card__avatar">
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:26px;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['display_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-card__details">
                    <h1 class="profile-card__name"><?php echo htmlspecialchars($user['display_name']); ?></h1>
                    <div class="inline" style="gap:10px;flex-wrap:wrap;">
                        <span class="role-chip role-<?php echo htmlspecialchars($roleClass); ?>">
                            <?php if ($isOwner): ?><i class="fas fa-crown"></i><?php elseif ($isAdmin): ?><i class="fas fa-shield"></i><?php endif; ?>
                            <?php echo htmlspecialchars($displayRole); ?>
                        </span>
                        <span class="pill"><i class="fas fa-calendar"></i> Member since <?php echo htmlspecialchars($memberSince); ?></span>
                        <span class="pill"><i class="fas fa-clock-rotate-left"></i> Last login <?php echo htmlspecialchars($lastLogin); ?></span>
                    </div>
                    <div class="metric-pulse" style="margin-top:18px;">
                        <div class="metric-pill">
                            <span class="metric-pill__value"><?php echo formatNumber($user['credits']); ?></span>
                            <span class="metric-pill__label">Credits</span>
                        </div>
                        <div class="metric-pill">
                            <span class="metric-pill__value"><?php echo formatNumber($user['xcoin_balance']); ?></span>
                            <span class="metric-pill__label">XCoin</span>
                        </div>
                        <div class="metric-pill">
                            <span class="metric-pill__value"><?php echo formatNumber($userHits); ?></span>
                            <span class="metric-pill__label">Total Hits</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="profile-card__footer">
                <div class="chip">
                    <i class="fas fa-signal"></i>
                    <span><?php echo formatNumber($onlineCount); ?> online now</span>
                </div>
                <?php if ($isAdmin || $isOwner): ?>
                    <a href="admin/admin_access.php" class="admin-link">
                        <i class="fas fa-shield-halved"></i>
                        <span>Open Admin Console</span>
                        <span class="admin-link__badge"><?php echo $isOwner ? 'OWNER' : 'ADMIN'; ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <div class="dashboard-grid">
            <div class="dashboard-grid__main">
                <section class="grid grid--cols-4">
                    <article class="card card--accent-purple stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Total Hits</h3>
                                <p class="card__subtitle">All-time intelligence checks</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-chart-line"></i></div>
                        </div>
                        <div class="card__value"><?php echo formatNumber($userHits); ?></div>
                        <div class="sparkline" data-points="<?php echo htmlspecialchars(implode(',', $userSparkline)); ?>"></div>
                    </article>

                    <article class="card card--accent-orange stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Charge Cards</h3>
                                <p class="card__subtitle">Successful premium validations</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-bolt"></i></div>
                        </div>
                        <div class="card__value"><?php echo formatNumber($userCharge); ?></div>
                        <div class="card__meta">
                            <span class="stat-delta stat-delta--up"><i class="fas fa-arrow-trend-up"></i><?php echo number_format($chargeRate, 1); ?>%</span>
                            <span class="muted">Success ratio</span>
                        </div>
                        <div class="progress-bar" data-progress="<?php echo number_format($chargeRate, 2, '.', ''); ?>">
                            <div class="progress-bar__fill"></div>
                        </div>
                    </article>

                    <article class="card card--accent-green stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Live Cards</h3>
                                <p class="card__subtitle">Verified active responses</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-heartbeat"></i></div>
                        </div>
                        <div class="card__value"><?php echo formatNumber($userLive); ?></div>
                        <div class="card__meta">
                            <span class="stat-delta stat-delta--up"><i class="fas fa-wave-square"></i><?php echo number_format($liveRate, 1); ?>%</span>
                            <span class="muted">Live match rate</span>
                        </div>
                        <div class="progress-bar" data-progress="<?php echo number_format($liveRate, 2, '.', ''); ?>">
                            <div class="progress-bar__fill"></div>
                        </div>
                    </article>

                    <article class="card card--accent-blue stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Premium Expiry</h3>
                                <p class="card__subtitle">Next billing checkpoint</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-calendar-day"></i></div>
                        </div>
                        <div class="card__value"><?php echo htmlspecialchars($expiryLabel); ?></div>
                        <div class="card__meta">
                            <span class="pill"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'free')); ?> tier</span>
                        </div>
                    </article>
                </section>

                <section class="grid grid--cols-2">
                    <article class="card card--glass stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Daily Credit Center</h3>
                                <p class="card__subtitle">Claim rewards without leaving the dashboard</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-gift"></i></div>
                        </div>
                        <div class="card__value">Boost your balance</div>
                        <p class="card__subtitle">Collect your daily drop of free credits to stay ahead.</p>
                        <button id="claimCreditsBtn" class="btn btn--primary" type="button">
                            <i class="fas fa-rocket"></i> Claim now
                        </button>
                        <p class="card__subtitle muted" data-default-text="Claim free credits once per day" data-ready-text="Claim is ready">
                            Claim free credits once per day
                        </p>
                    </article>

                    <article class="card card--accent-blue stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Account Status</h3>
                                <p class="card__subtitle">Stay on top of your membership</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-user-shield"></i></div>
                        </div>
                        <div class="card__value"><?php echo htmlspecialchars(ucfirst($user['role'] ?? 'free')); ?></div>
                        <div class="card__meta">
                            <span class="pill"><i class="fas fa-calendar"></i> Member since <?php echo htmlspecialchars($memberSince); ?></span>
                            <span class="pill"><i class="fas fa-coins"></i> <?php echo formatNumber($user['credits']); ?> credits</span>
                        </div>
                    </article>
                </section>

                <section class="card stagger">
                    <div class="card__head">
                        <div>
                            <h3 class="card__title">Quick Toolkit</h3>
                            <p class="card__subtitle">Launch your go-to utilities instantly</p>
                        </div>
                        <div class="card__icon"><i class="fas fa-toolbox"></i></div>
                    </div>
                    <div class="quick-links">
                        <a href="card_checker.php" class="quick-link">
                            <div class="quick-link__icon"><i class="fas fa-credit-card"></i></div>
                            <div class="quick-link__meta">
                                <span class="quick-link__title">Card Checker</span>
                                <span class="quick-link__subtitle">Validate cards with live feedback</span>
                            </div>
                        </a>
                        <a href="site_checker.php" class="quick-link">
                            <div class="quick-link__icon"><i class="fas fa-globe"></i></div>
                            <div class="quick-link__meta">
                                <span class="quick-link__title">Site Checker</span>
                                <span class="quick-link__subtitle">Monitor target availability</span>
                            </div>
                        </a>
                        <a href="tools.php" class="quick-link">
                            <div class="quick-link__icon"><i class="fas fa-screwdriver-wrench"></i></div>
                            <div class="quick-link__meta">
                                <span class="quick-link__title">All Tools</span>
                                <span class="quick-link__subtitle">Browse the complete toolbox</span>
                            </div>
                        </a>
                        <a href="wallet.php" class="quick-link">
                            <div class="quick-link__icon"><i class="fas fa-wallet"></i></div>
                            <div class="quick-link__meta">
                                <span class="quick-link__title">Wallet</span>
                                <span class="quick-link__subtitle">Manage XCoin deposits & usage</span>
                            </div>
                        </a>
                    </div>
                </section>

                <?php if ($hasAnnouncements): ?>
                    <section class="card stagger">
                        <div class="card__head">
                            <div>
                                <h3 class="card__title">Announcements</h3>
                                <p class="card__subtitle">System-wide broadcasts and updates</p>
                            </div>
                            <div class="card__icon"><i class="fas fa-megaphone"></i></div>
                        </div>
                        <div class="announcement-stack">
                            <?php foreach ($announcements as $message): ?>
                                <?php
                                    $priority = $message['priority'] ?? 'info';
                                    $priorityIcon = getPriorityIcon($priority);
                                    $timestamp = isset($message['created_at']) ? date('M j, g:i A', $message['created_at']) : 'Just now';
                                ?>
                                <div class="announcement">
                                    <div class="announcement__icon"><i class="fas <?php echo htmlspecialchars($priorityIcon); ?>"></i></div>
                                    <div class="announcement__content">
                                        <div class="announcement__title">Website Broadcast · <?php echo ucfirst(htmlspecialchars($priority)); ?></div>
                                        <div class="announcement__text"><?php echo htmlspecialchars($message['message'] ?? ''); ?></div>
                                        <div class="announcement__timestamp">ID <?php echo htmlspecialchars($message['message_id'] ?? 'N/A'); ?> · <?php echo htmlspecialchars($timestamp); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="dashboard-grid__aside">
                <section class="widget stagger">
                    <div class="widget__header">
                        <h3 class="widget__title"><i class="fas fa-wave-square"></i> Live Activity</h3>
                        <span class="widget__meta"><?php echo formatNumber($onlineCount); ?> online</span>
                    </div>
                    <?php if (empty($onlinePreview)): ?>
                        <div class="empty-state">
                            <i class="fas fa-circle-play"></i>
                            <h3>No active sessions</h3>
                            <p>Members will appear here as they come online.</p>
                        </div>
                    <?php else: ?>
                        <div class="widget__list scroller">
                            <?php foreach ($onlinePreview as $onlineUser): ?>
                                <?php
                                    $onlineData = $onlineUser['user'] ?? $onlineUser ?? [];
                                    $onlineName = htmlspecialchars($onlineData['display_name'] ?? 'Unknown User');
                                    $onlineRole = ucfirst($onlineData['role'] ?? 'free');
                                    $onlineCredits = formatNumber($onlineData['credits'] ?? 0);
                                    $lastSeenRaw = $onlineUser['last_seen_at'] ?? null;
                                    $lastSeen = $lastSeenRaw ? timeAgo($lastSeenRaw) : 'Active now';
                                    $onlineUsername = $onlineData['username'] ?? null;
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
                                            <?php echo $onlineName; ?>
                                            <span class="pill" style="font-size:0.7rem;"><?php echo htmlspecialchars($onlineRole); ?></span>
                                        </div>
                                        <div class="list-item__subtitle">
                                            <?php if ($onlineUsername): ?>@<?php echo htmlspecialchars($onlineUsername); ?> · <?php endif; ?>
                                            <?php echo htmlspecialchars($lastSeen); ?>
                                        </div>
                                    </div>
                                    <div class="list-item__value"><?php echo $onlineCredits; ?> cr</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="widget stagger">
                    <div class="widget__header">
                        <h3 class="widget__title"><i class="fas fa-trophy"></i> Leaderboard</h3>
                        <span class="widget__meta">Top performers</span>
                    </div>
                    <?php if (empty($topPreview)): ?>
                        <div class="empty-state">
                            <i class="fas fa-trophy"></i>
                            <h3>No rankings yet</h3>
                            <p>Complete checks to climb the leaderboard.</p>
                        </div>
                    <?php else: ?>
                        <div class="widget__list">
                            <?php foreach ($topPreview as $index => $topUser): ?>
                                <?php
                                    $leaderData = $topUser['user'] ?? $topUser ?? [];
                                    $leaderName = htmlspecialchars($leaderData['display_name'] ?? 'Unknown User');
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
                                        </div>
                                        <div class="list-item__subtitle">Rank #<?php echo $index + 1; ?></div>
                                    </div>
                                    <div class="list-item__value"><?php echo $leaderHits; ?> hits</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="widget stagger">
                    <div class="widget__header">
                        <h3 class="widget__title"><i class="fas fa-earth-americas"></i> Global Metrics</h3>
                        <span class="widget__meta">Community overview</span>
                    </div>
                    <div class="stack">
                        <div>
                            <div class="list-item">
                                <div class="list-item__body">
                                    <div class="list-item__title">Total Users</div>
                                    <div class="list-item__subtitle">Legend network footprint</div>
                                </div>
                                <div class="list-item__value"><?php echo formatNumber($globalUsers); ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="list-item">
                                <div class="list-item__body">
                                    <div class="list-item__title">Global Hits</div>
                                    <div class="list-item__subtitle">Checks processed</div>
                                </div>
                                <div class="list-item__value"><?php echo formatNumber($globalHits); ?></div>
                            </div>
                            <div class="progress-bar" data-progress="<?php echo number_format($globalChargeRate, 2, '.', ''); ?>">
                                <div class="progress-bar__fill"></div>
                            </div>
                        </div>
                        <div>
                            <div class="list-item">
                                <div class="list-item__body">
                                    <div class="list-item__title">Charge Success</div>
                                    <div class="list-item__subtitle">Across all members</div>
                                </div>
                                <div class="list-item__value"><?php echo number_format($globalChargeRate, 1); ?>%</div>
                            </div>
                            <div class="progress-bar" data-progress="<?php echo number_format($globalLiveRate, 2, '.', ''); ?>">
                                <div class="progress-bar__fill"></div>
                            </div>
                        </div>
                        <div class="sparkline" data-points="<?php echo htmlspecialchars(implode(',', $globalSparkline)); ?>"></div>
                    </div>
                    <div class="system-flags">
                        <div class="flag">
                            <div class="flag__icon"><i class="fas fa-chart-pie"></i></div>
                            <div class="flag__details">
                                <div class="flag__title"><?php echo formatNumber($globalLive); ?> live approvals</div>
                                <div class="flag__meta">Live validations</div>
                            </div>
                        </div>
                        <div class="flag">
                            <div class="flag__icon"><i class="fas fa-signal"></i></div>
                            <div class="flag__details">
                                <div class="flag__title"><?php echo formatNumber($onlineCount); ?> active sessions</div>
                                <div class="flag__meta">Current load</div>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <div class="bottom-nav">
        <nav class="bottom-nav__items">
            <a href="dashboard.php" class="bottom-nav__item bottom-nav__item--active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="tools.php" class="bottom-nav__item">
                <i class="fas fa-tools"></i>
                <span>Tools</span>
            </a>
            <a href="users.php" class="bottom-nav__item">
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
