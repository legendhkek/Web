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

// Check if user is owner
$is_owner = in_array((int)$userId, AppConfig::OWNER_IDS);

if (!$is_owner) {
    header('Location: dashboard.php');
    exit;
}

// Load proxy stats
$proxyFile = __DIR__ . '/data/proxies.json';
$proxyData = json_decode(file_get_contents($proxyFile), true);
$stats = $proxyData['stats'] ?? ['total' => 0, 'live' => 0, 'dead' => 0, 'last_check' => null];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Manager - LEGEND CHECKER</title>
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
            max-width: 1400px;
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-card.total i { color: #00d4ff; }
        .stat-card.live i { color: #10b981; }
        .stat-card.dead i { color: #ef4444; }
        .stat-card.last-check i { color: #8b5cf6; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .action-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .action-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .tab-btn.active {
            color: #00d4ff;
            border-bottom-color: #00d4ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
            background: rgba(255, 255, 255, 0.15);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            font-family: 'Courier New', monospace;
        }

        .btn {
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .proxy-table-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            overflow-x: auto;
        }

        .proxy-table {
            width: 100%;
            border-collapse: collapse;
        }

        .proxy-table th {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .proxy-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .proxy-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.live {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-badge.dead {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .btn-icon {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            transform: scale(1.2);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #10b981;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #ef4444;
        }

        .alert.info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.5);
            color: #3b82f6;
        }

        .loading {
            display: inline-block;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="tools.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Tools
            </a>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #ffd700;"><i class="fas fa-crown"></i> Owner</span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-network-wired"></i> Proxy Manager</h1>
            <p>Manage global proxies for all tools and checkers</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card total">
                <i class="fas fa-server"></i>
                <div class="stat-value" id="totalProxies"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Proxies</div>
            </div>
            <div class="stat-card live">
                <i class="fas fa-check-circle"></i>
                <div class="stat-value" id="liveProxies"><?php echo $stats['live']; ?></div>
                <div class="stat-label">Live Proxies</div>
            </div>
            <div class="stat-card dead">
                <i class="fas fa-times-circle"></i>
                <div class="stat-value" id="deadProxies"><?php echo $stats['dead']; ?></div>
                <div class="stat-label">Dead Proxies</div>
            </div>
            <div class="stat-card last-check">
                <i class="fas fa-clock"></i>
                <div class="stat-value" id="lastCheck"><?php echo $stats['last_check'] ? date('H:i', strtotime($stats['last_check'])) : 'Never'; ?></div>
                <div class="stat-label">Last Check</div>
            </div>
        </div>

        <div class="action-section">
            <div class="action-tabs">
                <button class="tab-btn active" data-tab="single">
                    <i class="fas fa-plus"></i> Add Single
                </button>
                <button class="tab-btn" data-tab="mass">
                    <i class="fas fa-list"></i> Add Mass
                </button>
                <button class="tab-btn" data-tab="manage">
                    <i class="fas fa-cog"></i> Manage
                </button>
            </div>

            <div id="alertContainer"></div>

            <div class="tab-content active" id="single">
                <h3 style="margin-bottom: 1.5rem;">Add Single Proxy</h3>
                <form id="singleProxyForm">
                    <div class="form-group">
                        <label for="singleProxy">
                            <i class="fas fa-server"></i> Proxy Address
                            <span style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">(Format: host:port:user:pass)</span>
                        </label>
                        <input 
                            type="text" 
                            id="singleProxy" 
                            name="proxy" 
                            placeholder="proxy.example.com:8080:username:password"
                            required
                        >
                    </div>
                    <button type="submit" class="btn btn-primary" id="addSingleBtn">
                        <i class="fas fa-plus"></i> Add & Check Proxy
                    </button>
                </form>
            </div>

            <div class="tab-content" id="mass">
                <h3 style="margin-bottom: 1.5rem;">Add Multiple Proxies</h3>
                <form id="massProxyForm">
                    <div class="form-group">
                        <label for="massProxies">
                            <i class="fas fa-list"></i> Proxy List
                            <span style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">(One proxy per line, Format: host:port:user:pass)</span>
                        </label>
                        <textarea 
                            id="massProxies" 
                            name="proxies" 
                            placeholder="proxy1.example.com:8080:user1:pass1
proxy2.example.com:8080:user2:pass2
proxy3.example.com:8080:user3:pass3"
                            required
                        ></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="addMassBtn">
                        <i class="fas fa-upload"></i> Add & Check All Proxies
                    </button>
                </form>
            </div>

            <div class="tab-content" id="manage">
                <h3 style="margin-bottom: 1.5rem;">Manage Proxies</h3>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="checkAllBtn">
                        <i class="fas fa-sync"></i> Check All Proxies
                    </button>
                    <button class="btn btn-danger" id="removeDeadBtn">
                        <i class="fas fa-trash"></i> Remove Dead Proxies
                    </button>
                    <button class="btn btn-secondary" id="refreshListBtn">
                        <i class="fas fa-refresh"></i> Refresh List
                    </button>
                </div>
            </div>
        </div>

        <div class="proxy-table-container">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fas fa-table"></i> Proxy List
                <span id="proxyCount" style="color: #00d4ff; font-size: 0.9rem;">(<?php echo $stats['total']; ?> proxies)</span>
            </h3>
            <table class="proxy-table" id="proxyTable">
                <thead>
                    <tr>
                        <th>Proxy</th>
                        <th>IP</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th>Last Check</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="proxyTableBody">
                    <tr>
                        <td colspan="6" style="text-align: center; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-spinner fa-spin"></i> Loading proxies...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        // Tab switching
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                document.getElementById(tab).classList.add('active');
            });
        });

        // Show alert
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert ${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i> ${message}`;
            alert.style.display = 'block';
            
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Update stats
        function updateStats(stats) {
            document.getElementById('totalProxies').textContent = stats.total;
            document.getElementById('liveProxies').textContent = stats.live;
            document.getElementById('deadProxies').textContent = stats.dead;
            document.getElementById('lastCheck').textContent = stats.last_check ? new Date(stats.last_check).toLocaleTimeString() : 'Never';
            document.getElementById('proxyCount').textContent = `(${stats.total} proxies)`;
        }

        // Load proxies
        async function loadProxies() {
            try {
                const response = await fetch('proxy_manager_api.php?action=get_proxies');
                const data = await response.json();
                
                if (data.success) {
                    const tbody = document.getElementById('proxyTableBody');
                    tbody.innerHTML = '';
                    
                    if (data.proxies.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: rgba(255,255,255,0.5);">No proxies added yet</td></tr>';
                        return;
                    }
                    
                    data.proxies.forEach(proxy => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><code style="background: rgba(255,255,255,0.1); padding: 0.25rem 0.5rem; border-radius: 5px;">${proxy.proxy}</code></td>
                            <td>${proxy.ip || 'N/A'}</td>
                            <td><i class="fas fa-globe"></i> ${proxy.country || 'Unknown'}${proxy.city ? ', ' + proxy.city : ''}</td>
                            <td><span class="status-badge ${proxy.status}">${proxy.status.toUpperCase()}</span></td>
                            <td>${new Date(proxy.last_check).toLocaleString()}</td>
                            <td>
                                <button class="btn-icon" onclick="removeProxy('${proxy.id}')" title="Remove">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                    
                    updateStats(data.stats);
                }
            } catch (error) {
                console.error('Error loading proxies:', error);
                showAlert('Failed to load proxies: ' + error.message, 'error');
            }
        }

        // Add single proxy
        document.getElementById('singleProxyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('addSingleBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            const formData = new FormData(e.target);
            formData.append('action', 'add_single');
            
            try {
                const response = await fetch('proxy_manager_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    e.target.reset();
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });

        // Add mass proxies
        document.getElementById('massProxyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('addMassBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            const formData = new FormData(e.target);
            formData.append('action', 'add_mass');
            
            try {
                const response = await fetch('proxy_manager_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    e.target.reset();
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });

        // Check all proxies
        document.getElementById('checkAllBtn').addEventListener('click', async () => {
            const btn = document.getElementById('checkAllBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            showAlert('Checking all proxies... This may take a while.', 'info');
            
            try {
                const response = await fetch('proxy_manager_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'check_all' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadProxies();
                } else {
                    showAlert('Check failed', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });

        // Remove dead proxies
        document.getElementById('removeDeadBtn').addEventListener('click', async () => {
            if (!confirm('Are you sure you want to remove all dead proxies?')) {
                return;
            }
            
            const btn = document.getElementById('removeDeadBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
            
            try {
                const response = await fetch('proxy_manager_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'remove_dead' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadProxies();
                } else {
                    showAlert('Failed to remove proxies', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });

        // Refresh list
        document.getElementById('refreshListBtn').addEventListener('click', () => {
            loadProxies();
            showAlert('Proxy list refreshed', 'success');
        });

        // Remove single proxy
        async function removeProxy(id) {
            if (!confirm('Are you sure you want to remove this proxy?')) {
                return;
            }
            
            try {
                const response = await fetch('proxy_manager_api.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'remove_proxy', id: id })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        }

        // Load proxies on page load
        loadProxies();

        // Auto refresh every 30 seconds
        setInterval(loadProxies, 30000);
    </script>
</body>
</html>
