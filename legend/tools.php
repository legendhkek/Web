<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

$user = $db->getUserByTelegramId($userId);
$user['credits'] = (int)($user['credits'] ?? 0);
$user['xcoin_balance'] = (int)($user['xcoin_balance'] ?? 0);
$user['display_name'] = $user['display_name'] ?? ($user['first_name'] ?? 'User');
$userStats = $db->getUserStats($userId) ?? [
    'total_hits' => 0,
    'total_charge_cards' => 0,
    'total_live_cards' => 0
];

$db->updatePresence($userId);

$tools = [
    [
        'title' => 'Card Checker',
        'icon' => 'fa-credit-card',
        'description' => 'Validate credit card numbers with live feedback from payment gateways.',
        'link' => 'card_checker.php',
        'cost' => AppConfig::CARD_CHECK_COST,
        'tag' => 'Core'
    ],
    [
        'title' => 'Site Checker',
        'icon' => 'fa-globe',
        'description' => 'Monitor website availability and response codes across multiple endpoints.',
        'link' => 'site_checker.php',
        'cost' => AppConfig::SITE_CHECK_COST,
        'tag' => 'Network'
    ],
    [
        'title' => 'Stripe Auth Checker',
        'icon' => 'fa-stripe-s',
        'description' => 'Test Stripe authentication with automatic rotation across 280+ sites.',
        'link' => 'stripe_auth_tool.php',
        'cost' => 1,
        'tag' => 'Automation'
    ],
    [
        'title' => 'BIN Lookup',
        'icon' => 'fa-search',
        'description' => 'Get detailed intelligence on BINs: issuer, card type, bank, and country.',
        'link' => 'bin_lookup_tool.php',
        'cost' => 0,
        'tag' => 'Free'
    ],
    [
        'title' => 'BIN Generator',
        'icon' => 'fa-magic',
        'description' => 'Generate valid card numbers from BINs with built-in Luhn algorithm validation.',
        'link' => 'bin_generator_tool.php',
        'cost' => 0,
        'tag' => 'Free'
    ],
    [
        'title' => 'Security Scanner',
        'icon' => 'fa-shield-halved',
        'description' => 'Advanced vulnerability assessment suite (in development).',
        'link' => null,
        'cost' => 5,
        'tag' => 'Coming soon'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legend Checker · Tools & Automations</title>
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
<body class="page page-tools" data-theme="dark">
    <div class="page-shell">
        <header class="page-header">
            <div class="page-header__left">
                <div class="brand">
                    <div class="brand__icon"><i class="fas fa-screwdriver-wrench"></i></div>
                    <div class="brand__meta">
                        <span class="brand__name">Legend Toolkit</span>
                        <span class="brand__tagline">Execute with precision</span>
                    </div>
                </div>
                <span class="badge"><i class="fas fa-terminal"></i> Operator Mode</span>
            </div>
            <div class="page-header__actions">
                <div class="chip">
                    <i class="fas fa-coins"></i>
                    <span><?php echo formatNumber($user['credits']); ?> credits</span>
                </div>
                <div class="chip">
                    <i class="fas fa-wallet"></i>
                    <span><?php echo formatNumber($user['xcoin_balance']); ?> XCoin</span>
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

        <section class="card card--glass stagger">
            <div class="card__head">
                <div>
                    <h1 class="card__title">Mission Control</h1>
                    <p class="card__subtitle">Select the right tool for the operation. Costs are debited on execution.</p>
                </div>
                <div class="card__icon"><i class="fas fa-compass"></i></div>
            </div>
            <div class="metric-pulse" style="margin-top:12px;">
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($userStats['total_hits'] ?? 0); ?></span>
                    <span class="metric-pill__label">Total Runs</span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($userStats['total_charge_cards'] ?? 0); ?></span>
                    <span class="metric-pill__label">Charge Cards</span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($userStats['total_live_cards'] ?? 0); ?></span>
                    <span class="metric-pill__label">Live Cards</span>
                </div>
            </div>
        </section>

        <section class="grid grid--cols-4" style="margin-top:28px;">
            <?php foreach ($tools as $tool): ?>
                <?php
                    $isComingSoon = empty($tool['link']);
                    $insufficientCredits = !$isComingSoon && $tool['cost'] > 0 && $user['credits'] < $tool['cost'];
                    $buttonLabel = $isComingSoon ? 'Coming soon' : ($insufficientCredits ? 'Add credits' : 'Launch tool');
                    $buttonClass = $isComingSoon || $insufficientCredits ? 'btn btn--ghost' : 'btn btn--primary';
                ?>
                <article class="card stagger">
                    <div class="card__head">
                        <div>
                            <h3 class="card__title"><?php echo htmlspecialchars($tool['title']); ?></h3>
                            <p class="card__subtitle"><?php echo htmlspecialchars($tool['description']); ?></p>
                        </div>
                        <div class="card__icon"><i class="fas <?php echo htmlspecialchars($tool['icon']); ?>"></i></div>
                    </div>
                    <div class="card__meta">
                        <?php if ($tool['cost'] > 0): ?>
                            <span class="pill"><i class="fas fa-coins"></i> <?php echo $tool['cost']; ?> credits per run</span>
                        <?php else: ?>
                            <span class="pill"><i class="fas fa-gift"></i> Free access</span>
                        <?php endif; ?>
                        <span class="pill"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tool['tag']); ?></span>
                    </div>
                    <?php if ($isComingSoon): ?>
                        <button class="<?php echo $buttonClass; ?>" type="button" disabled style="margin-top:18px;">
                            <i class="fas fa-sparkles"></i> <?php echo $buttonLabel; ?>
                        </button>
                    <?php elseif ($insufficientCredits): ?>
                        <a class="<?php echo $buttonClass; ?>" href="wallet.php" style="margin-top:18px;">
                            <i class="fas fa-arrow-up-right-from-square"></i> <?php echo $buttonLabel; ?>
                        </a>
                    <?php else: ?>
                        <a class="<?php echo $buttonClass; ?>" href="<?php echo htmlspecialchars($tool['link']); ?>" style="margin-top:18px;">
                            <i class="fas fa-arrow-up-right-from-square"></i> <?php echo $buttonLabel; ?>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="card stagger" style="margin-top:28px;">
            <div class="card__head">
                <div>
                    <h3 class="card__title">Execution Stats</h3>
                    <p class="card__subtitle">Monitor how your credits convert into successful runs.</p>
                </div>
                <div class="card__icon"><i class="fas fa-chart-simple"></i></div>
            </div>
            <div class="grid grid--cols-4">
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($userStats['total_hits'] ?? 0); ?></span>
                    <span class="metric-pill__label">Total Checks</span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($userStats['total_charge_cards'] ?? 0); ?></span>
                    <span class="metric-pill__label">Charge Cards</span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($userStats['total_live_cards'] ?? 0); ?></span>
                    <span class="metric-pill__label">Live Cards</span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__value"><?php echo formatNumber($user['credits']); ?></span>
                    <span class="metric-pill__label">Available Credits</span>
                </div>
            </div>
        </section>
    </div>

    <div class="bottom-nav">
        <nav class="bottom-nav__items">
            <a href="dashboard.php" class="bottom-nav__item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="tools.php" class="bottom-nav__item bottom-nav__item--active">
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
