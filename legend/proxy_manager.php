<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

$user = $db->getUserByTelegramId($userId);
$db->updatePresence($userId);
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
            padding-bottom: 80px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

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

        .back-btn {
            color: var(--accent-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .back-btn:hover {
            color: var(--accent-green);
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #1a8cd8;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--accent-green);
            color: white;
        }

        .btn-success:hover {
            background: #00b894;
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error-color);
        }

        .alert.show {
            display: block;
        }

        .proxy-list {
            margin-top: 24px;
        }

        .proxy-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .proxy-item:hover {
            background: var(--bg-card-hover);
            border-color: var(--accent-blue);
        }

        .proxy-info {
            flex: 1;
        }

        .proxy-string {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .proxy-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .proxy-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .proxy-status.live {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        .proxy-status.dead {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }

        .proxy-status.pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .proxy-actions {
            display: flex;
            gap: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent-blue);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--text-secondary);
            border-top-color: var(--accent-blue);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

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

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .proxy-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .proxy-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <div class="logo">
                <i class="fas fa-server"></i>
                Proxy Manager
            </div>
        </div>

        <h1 class="page-title">Proxy Management</h1>

        <div id="alertContainer"></div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="totalProxies">0</div>
                <div class="stat-label">Total Proxies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="liveProxies" style="color: var(--success-color);">0</div>
                <div class="stat-label">Live Proxies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="deadProxies" style="color: var(--error-color);">0</div>
                <div class="stat-label">Dead Proxies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="pendingProxies" style="color: var(--warning-color);">0</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- Add Single Proxy -->
        <div class="card">
            <h2 class="card-title">Add Single Proxy</h2>
            <div class="form-group">
                <label for="singleProxy">Proxy Format: host:port:username:password</label>
                <input type="text" id="singleProxy" placeholder="192.168.1.1:8080:user:pass">
                <small>Enter proxy in format: host:port:username:password</small>
            </div>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="addProxy()">
                    <i class="fas fa-plus"></i>
                    Add Proxy
                </button>
                <button class="btn btn-success" onclick="checkProxy()">
                    <i class="fas fa-check"></i>
                    Check Proxy
                </button>
            </div>
        </div>

        <!-- Bulk Add Proxies -->
        <div class="card">
            <h2 class="card-title">Bulk Add Proxies</h2>
            <div class="form-group">
                <label for="bulkProxies">Enter proxies (one per line)</label>
                <textarea id="bulkProxies" placeholder="192.168.1.1:8080:user:pass&#10;192.168.1.2:8080:user:pass&#10;192.168.1.3:8080:user:pass"></textarea>
                <small>Enter multiple proxies, one per line. Format: host:port:username:password</small>
            </div>
            <button class="btn btn-primary" onclick="bulkAddProxies()">
                <i class="fas fa-upload"></i>
                Bulk Add Proxies
            </button>
        </div>

        <!-- Proxy List -->
        <div class="card">
            <h2 class="card-title">Your Proxies</h2>
            <div class="btn-group" style="margin-bottom: 20px;">
                <button class="btn btn-success" onclick="loadProxies()">
                    <i class="fas fa-sync"></i>
                    Refresh
                </button>
                <button class="btn btn-warning" onclick="checkAllProxies()">
                    <i class="fas fa-check-double"></i>
                    Check All
                </button>
            </div>
            <div id="proxyList" class="proxy-list">
                <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i>
                    <p>Loading proxies...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="tools.php" class="nav-item">
                <i class="fas fa-tools"></i>
                <span>Tools</span>
            </a>
            <a href="proxy_manager.php" class="nav-item active">
                <i class="fas fa-server"></i>
                <span>Proxies</span>
            </a>
            <a href="wallet.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>Wallet</span>
            </a>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        function addProxy() {
            const proxy = document.getElementById('singleProxy').value.trim();
            if (!proxy) {
                showAlert('Please enter a proxy', 'error');
                return;
            }

            const parts = proxy.split(':');
            if (parts.length !== 4) {
                showAlert('Invalid format. Use: host:port:user:pass', 'error');
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> Adding...';
            btn.disabled = true;

            fetch('proxy_manager_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&proxy=${encodeURIComponent(proxy)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('singleProxy').value = '';
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Error adding proxy: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function checkProxy() {
            const proxy = document.getElementById('singleProxy').value.trim();
            if (!proxy) {
                showAlert('Please enter a proxy to check', 'error');
                return;
            }

            const parts = proxy.split(':');
            if (parts.length !== 4) {
                showAlert('Invalid format. Use: host:port:user:pass', 'error');
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> Checking...';
            btn.disabled = true;

            fetch('check_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proxy: proxy })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'live') {
                    showAlert(`Proxy is LIVE (${data.country || 'Unknown'})`, 'success');
                } else {
                    showAlert(`Proxy is DEAD: ${data.error || 'Unknown error'}`, 'error');
                }
            })
            .catch(error => {
                showAlert('Error checking proxy: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function bulkAddProxies() {
            const proxiesText = document.getElementById('bulkProxies').value.trim();
            if (!proxiesText) {
                showAlert('Please enter proxies', 'error');
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> Adding...';
            btn.disabled = true;

            fetch('proxy_manager_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=bulk_add&proxies=${encodeURIComponent(proxiesText)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('bulkProxies').value = '';
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Error adding proxies: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function loadProxies() {
            const proxyList = document.getElementById('proxyList');
            proxyList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 16px;"></i><p>Loading proxies...</p></div>';

            fetch('proxy_manager_api.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayProxies(data.proxies);
                    updateStats(data.proxies);
                } else {
                    proxyList.innerHTML = '<div style="text-align: center; color: var(--error-color); padding: 40px;">Error loading proxies</div>';
                }
            })
            .catch(error => {
                proxyList.innerHTML = '<div style="text-align: center; color: var(--error-color); padding: 40px;">Error: ' + error.message + '</div>';
            });
        }

        function displayProxies(proxies) {
            const proxyList = document.getElementById('proxyList');
            
            if (proxies.length === 0) {
                proxyList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">No proxies added yet. Add your first proxy above!</div>';
                return;
            }

            proxyList.innerHTML = proxies.map(proxy => `
                <div class="proxy-item">
                    <div class="proxy-info">
                        <div class="proxy-string">${proxy.proxy_string}</div>
                        <div class="proxy-meta">
                            <span><i class="fas fa-circle" style="color: ${proxy.status === 'live' ? 'var(--success-color)' : proxy.status === 'dead' ? 'var(--error-color)' : 'var(--warning-color)'};"></i> 
                            <span class="proxy-status ${proxy.status}">${proxy.status}</span></span>
                            ${proxy.country ? `<span><i class="fas fa-globe"></i> ${proxy.country}</span>` : ''}
                            ${proxy.ip ? `<span><i class="fas fa-network-wired"></i> ${proxy.ip}</span>` : ''}
                            ${proxy.response_time ? `<span><i class="fas fa-clock"></i> ${proxy.response_time}ms</span>` : ''}
                            ${proxy.last_checked ? `<span><i class="fas fa-calendar"></i> ${proxy.last_checked}</span>` : ''}
                        </div>
                    </div>
                    <div class="proxy-actions">
                        <button class="btn btn-success" onclick="checkSingleProxy('${proxy.id}')" style="padding: 8px 16px; font-size: 12px;">
                            <i class="fas fa-check"></i> Check
                        </button>
                        <button class="btn btn-danger" onclick="deleteProxy('${proxy.id}')" style="padding: 8px 16px; font-size: 12px;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            `).join('');
        }

        function updateStats(proxies) {
            const total = proxies.length;
            const live = proxies.filter(p => p.status === 'live').length;
            const dead = proxies.filter(p => p.status === 'dead').length;
            const pending = proxies.filter(p => p.status === 'pending').length;

            document.getElementById('totalProxies').textContent = total;
            document.getElementById('liveProxies').textContent = live;
            document.getElementById('deadProxies').textContent = dead;
            document.getElementById('pendingProxies').textContent = pending;
        }

        function checkSingleProxy(proxyId) {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span>';
            btn.disabled = true;

            fetch('proxy_manager_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=check&proxy_id=${proxyId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(`Proxy is ${data.status.toUpperCase()}${data.country ? ' (' + data.country + ')' : ''}`, data.status === 'live' ? 'success' : 'error');
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Error checking proxy: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function deleteProxy(proxyId) {
            if (!confirm('Are you sure you want to delete this proxy?')) {
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span>';
            btn.disabled = true;

            fetch('proxy_manager_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&proxy_id=${proxyId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Proxy deleted successfully', 'success');
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Error deleting proxy: ' + error.message, 'error');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function checkAllProxies() {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> Checking...';
            btn.disabled = true;

            fetch('proxy_manager_api.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.proxies.length > 0) {
                    let checked = 0;
                    const total = data.proxies.length;
                    
                    data.proxies.forEach((proxy, index) => {
                        setTimeout(() => {
                            fetch('proxy_manager_api.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=check&proxy_id=${proxy.id}`
                            })
                            .then(() => {
                                checked++;
                                if (checked === total) {
                                    showAlert(`Checked ${total} proxies`, 'success');
                                    loadProxies();
                                    btn.innerHTML = originalText;
                                    btn.disabled = false;
                                }
                            });
                        }, index * 500);
                    });
                } else {
                    showAlert('No proxies to check', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                showAlert('Error: ' + error.message, 'error');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // Load proxies on page load
        loadProxies();
    </script>
</body>
</html>
