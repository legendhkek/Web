<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

// Get user data
$user = $db->getUserByTelegramId($userId);
$proxyStats = $db->getProxyStats();

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

        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-card: #2a2a2a;
            --bg-card-hover: #333333;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-blue: #1da1f2;
            --accent-green: #00d4aa;
            --accent-purple: #8b5cf6;
            --border-color: #3a3a3a;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 80px;
            transition: background 0.3s ease;
        }

        body.light-mode {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-card: #ffffff;
            --bg-card-hover: #f0f0f0;
            --text-primary: #1a1a1a;
            --text-secondary: #4a4a4a;
            --border-color: #e0e0e0;
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

        /* Search and Filter Bar */
        .search-filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(29, 161, 242, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--accent-blue);
            color: white;
            border-color: var(--accent-blue);
        }

        .sort-select {
            padding: 8px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            cursor: pointer;
            font-size: 14px;
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

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .tool-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .tool-card.hidden {
            display: none;
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

        <!-- Search and Filter Bar -->
        <div class="search-filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="toolSearch" placeholder="Search tools..." autocomplete="off">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="free">Free</button>
                <button class="filter-btn" data-filter="paid">Paid</button>
            </div>
            <select class="sort-select" id="sortSelect">
                <option value="name">Sort by Name</option>
                <option value="cost">Sort by Cost</option>
                <option value="popularity">Sort by Popularity</option>
            </select>
        </div>

        <div class="tools-grid" id="toolsGrid">
            <div class="tool-card" data-name="card checker" data-cost="<?php echo AppConfig::CARD_CHECK_COST; ?>" data-type="paid" data-popularity="5">
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

            <div class="tool-card" data-name="site checker" data-cost="<?php echo AppConfig::SITE_CHECK_COST; ?>" data-type="paid" data-popularity="4">
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

            <div class="tool-card" data-name="stripe auth checker" data-cost="1" data-type="paid" data-popularity="5">
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

              <div class="tool-card" data-name="proxy manager" data-cost="0" data-type="free" data-popularity="4">
                  <div class="tool-icon">
                      <i class="fas fa-network-wired"></i>
                  </div>
                  <h3 class="tool-title">Proxy Manager</h3>
                  <p class="tool-description">
                      Centralized proxy pool with live validation, daily health checks, and automatic cleanup of dead proxies.
                  </p>
                  <div class="tool-cost" style="background: rgba(0, 230, 118, 0.1);">
                      <i class="fas fa-database"></i>
                      <?php echo number_format($proxyStats['live'] ?? 0); ?> Live / <?php echo number_format($proxyStats['total'] ?? 0); ?> Total
                  </div>
                  <a href="proxy_manager.php" class="tool-btn">
                      Open Manager
                  </a>
              </div>

            <div class="tool-card" data-name="bin lookup" data-cost="0" data-type="free" data-popularity="3">
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

            <div class="tool-card" data-name="bin generator" data-cost="0" data-type="free" data-popularity="3">
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

            <div class="tool-card" data-name="security scanner" data-cost="5" data-type="paid" data-popularity="2">
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

        <div id="noResults" style="display: none; text-align: center; padding: 3rem; color: var(--text-secondary);">
            <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No tools found matching your search criteria.</p>
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
        // Theme Management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.body.classList.toggle('light-mode', savedTheme === 'light');
        }
        initTheme();

        // Search and Filter Functionality
        const searchInput = document.getElementById('toolSearch');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const sortSelect = document.getElementById('sortSelect');
        const toolsGrid = document.getElementById('toolsGrid');
        const toolCards = document.querySelectorAll('.tool-card');
        const noResults = document.getElementById('noResults');

        let currentFilter = 'all';
        let currentSort = 'name';
        let searchQuery = '';

        function filterAndSortTools() {
            let visibleCount = 0;

            toolCards.forEach(card => {
                const name = card.dataset.name.toLowerCase();
                const type = card.dataset.type;
                const matchesSearch = name.includes(searchQuery.toLowerCase());
                const matchesFilter = currentFilter === 'all' || 
                    (currentFilter === 'free' && type === 'free') ||
                    (currentFilter === 'paid' && type === 'paid');

                if (matchesSearch && matchesFilter) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Sort visible cards
            const visibleCards = Array.from(toolCards).filter(card => !card.classList.contains('hidden'));
            visibleCards.sort((a, b) => {
                if (currentSort === 'name') {
                    return a.dataset.name.localeCompare(b.dataset.name);
                } else if (currentSort === 'cost') {
                    return parseInt(a.dataset.cost) - parseInt(b.dataset.cost);
                } else if (currentSort === 'popularity') {
                    return parseInt(b.dataset.popularity) - parseInt(a.dataset.popularity);
                }
                return 0;
            });

            // Reorder in DOM
            visibleCards.forEach(card => {
                toolsGrid.appendChild(card);
            });

            // Show/hide no results message
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        // Search input handler
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            filterAndSortTools();
        });

        // Filter button handlers
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                filterButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = btn.dataset.filter;
                filterAndSortTools();
            });
        });

        // Sort select handler
        sortSelect.addEventListener('change', (e) => {
            currentSort = e.target.value;
            filterAndSortTools();
        });

        // Keyboard shortcut for search
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
