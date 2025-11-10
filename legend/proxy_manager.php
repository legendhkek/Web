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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_proxy':
            $proxy = trim($_POST['proxy'] ?? '');
            if (empty($proxy)) {
                echo json_encode(['success' => false, 'message' => 'Proxy cannot be empty']);
                exit;
            }
            
            // Validate proxy format (host:port:user:pass)
            $parts = explode(':', $proxy);
            if (count($parts) !== 4) {
                echo json_encode(['success' => false, 'message' => 'Invalid format. Use: host:port:user:pass']);
                exit;
            }
            
            // Add proxy to database
            $result = $db->addProxy($userId, $proxy);
            echo json_encode(['success' => $result, 'message' => $result ? 'Proxy added successfully' : 'Failed to add proxy']);
            exit;
            
        case 'add_bulk_proxies':
            $proxies = $_POST['proxies'] ?? '';
            $lines = explode("\n", $proxies);
            $added = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($lines as $proxy) {
                $proxy = trim($proxy);
                if (empty($proxy)) continue;
                
                $parts = explode(':', $proxy);
                if (count($parts) !== 4) {
                    $failed++;
                    $errors[] = "Invalid format: $proxy";
                    continue;
                }
                
                if ($db->addProxy($userId, $proxy)) {
                    $added++;
                } else {
                    $failed++;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'added' => $added, 
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 5) // Limit to 5 error messages
            ]);
            exit;
            
        case 'remove_proxy':
            $proxyId = $_POST['proxy_id'] ?? '';
            $result = $db->removeProxy($userId, $proxyId);
            echo json_encode(['success' => $result, 'message' => $result ? 'Proxy removed' : 'Failed to remove proxy']);
            exit;
            
        case 'test_proxy':
            $proxyId = $_POST['proxy_id'] ?? '';
            $proxy = $db->getProxyById($userId, $proxyId);
            
            if (!$proxy) {
                echo json_encode(['success' => false, 'status' => 'dead', 'message' => 'Proxy not found']);
                exit;
            }
            
            // Test proxy using check_proxy.php logic
            $parts = explode(':', $proxy['proxy']);
            if (count($parts) === 4) {
                list($host, $port, $username, $password) = $parts;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org?format=json');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_PROXY, "$host:$port");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password");
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $startTime = microtime(true);
                $response = curl_exec($ch);
                $responseTime = round((microtime(true) - $startTime) * 1000);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($httpCode === 200 && !empty($response)) {
                    $data = json_decode($response, true);
                    $db->updateProxyStatus($userId, $proxyId, 'live', $responseTime);
                    echo json_encode([
                        'success' => true,
                        'status' => 'live',
                        'ip' => $data['ip'] ?? 'Unknown',
                        'response_time' => $responseTime,
                        'message' => 'Proxy is working'
                    ]);
                } else {
                    $db->updateProxyStatus($userId, $proxyId, 'dead', 0);
                    echo json_encode([
                        'success' => false,
                        'status' => 'dead',
                        'message' => $error ?: 'Proxy failed',
                        'http_code' => $httpCode
                    ]);
                }
            } else {
                echo json_encode(['success' => false, 'status' => 'dead', 'message' => 'Invalid proxy format']);
            }
            exit;
            
        case 'test_all_proxies':
            $proxies = $db->getUserProxies($userId);
            $results = [];
            
            foreach ($proxies as $proxy) {
                $parts = explode(':', $proxy['proxy']);
                if (count($parts) !== 4) continue;
                
                list($host, $port, $username, $password) = $parts;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org?format=json');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_PROXY, "$host:$port");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$username:$password");
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $startTime = microtime(true);
                $response = curl_exec($ch);
                $responseTime = round((microtime(true) - $startTime) * 1000);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $status = ($httpCode === 200 && !empty($response)) ? 'live' : 'dead';
                $db->updateProxyStatus($userId, $proxy['_id'], $status, $responseTime);
                
                $results[] = [
                    'proxy_id' => (string)$proxy['_id'],
                    'status' => $status,
                    'response_time' => $responseTime
                ];
            }
            
            echo json_encode(['success' => true, 'results' => $results]);
            exit;
            
        case 'get_proxies':
            $proxies = $db->getUserProxies($userId);
            echo json_encode(['success' => true, 'proxies' => $proxies]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}
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
            min-height: 100vh;
            padding-bottom: 80px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent-green);
        }

        .back-btn {
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .back-btn:hover {
            background: var(--accent-blue);
            color: white;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--accent-green);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-control {
            width: 100%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 3px rgba(0, 212, 170, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            font-family: 'Courier New', monospace;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent-green);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background: #00e6bb;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--accent-blue);
            color: white;
        }

        .btn-secondary:hover {
            background: #1a8cd8;
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .proxy-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .proxy-item {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .proxy-item:hover {
            background: var(--bg-card-hover);
            transform: translateX(4px);
        }

        .proxy-info {
            flex: 1;
        }

        .proxy-address {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .proxy-status {
            display: inline-block;
            padding: 4px 10px;
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

        .proxy-status.untested {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
        }

        .proxy-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .alert-info {
            background: rgba(29, 161, 242, 0.1);
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--text-muted);
            border-top: 2px solid var(--accent-green);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .proxy-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .proxy-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-server"></i> Proxy Manager</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div id="alert-container"></div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="total-proxies">0</div>
                <div class="stat-label">Total Proxies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="live-proxies">0</div>
                <div class="stat-label">Live Proxies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="dead-proxies">0</div>
                <div class="stat-label">Dead Proxies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="untested-proxies">0</div>
                <div class="stat-label">Untested</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Add Proxies</h2>
                
                <div class="form-group">
                    <label>Single Proxy</label>
                    <input type="text" id="single-proxy" class="form-control" placeholder="host:port:user:pass">
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                        Format: IP:PORT:USERNAME:PASSWORD
                    </small>
                </div>
                
                <button onclick="addSingleProxy()" class="btn btn-primary btn-block" style="width: 100%; margin-bottom: 24px;">
                    <i class="fas fa-plus"></i> Add Proxy
                </button>

                <div class="form-group">
                    <label>Bulk Import</label>
                    <textarea id="bulk-proxies" class="form-control" placeholder="host:port:user:pass&#10;host:port:user:pass&#10;..."></textarea>
                    <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                        One proxy per line, format: IP:PORT:USERNAME:PASSWORD
                    </small>
                </div>
                
                <button onclick="addBulkProxies()" class="btn btn-primary btn-block" style="width: 100%;">
                    <i class="fas fa-file-import"></i> Import All
                </button>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;"><i class="fas fa-list"></i> My Proxies</h2>
                    <button onclick="testAllProxies()" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sync"></i> Test All
                    </button>
                </div>
                
                <div id="proxy-list" class="proxy-list">
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-server" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>No proxies added yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        let proxies = [];

        function showAlert(message, type = 'info') {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        async function addSingleProxy() {
            const input = document.getElementById('single-proxy');
            const proxy = input.value.trim();
            
            if (!proxy) {
                showAlert('Please enter a proxy', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_proxy');
            formData.append('proxy', proxy);
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Proxy added successfully!', 'success');
                    input.value = '';
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Failed to add proxy: ' + error.message, 'error');
            }
        }

        async function addBulkProxies() {
            const textarea = document.getElementById('bulk-proxies');
            const proxies = textarea.value.trim();
            
            if (!proxies) {
                showAlert('Please enter proxies to import', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_bulk_proxies');
            formData.append('proxies', proxies);
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(`Successfully added ${data.added} proxies. Failed: ${data.failed}`, 'success');
                    if (data.errors && data.errors.length > 0) {
                        showAlert('Errors: ' + data.errors.join(', '), 'error');
                    }
                    textarea.value = '';
                    loadProxies();
                } else {
                    showAlert('Failed to import proxies', 'error');
                }
            } catch (error) {
                showAlert('Failed to import proxies: ' + error.message, 'error');
            }
        }

        async function removeProxy(proxyId) {
            if (!confirm('Are you sure you want to remove this proxy?')) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_proxy');
            formData.append('proxy_id', proxyId);
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Proxy removed', 'success');
                    loadProxies();
                } else {
                    showAlert(data.message, 'error');
                }
            } catch (error) {
                showAlert('Failed to remove proxy: ' + error.message, 'error');
            }
        }

        async function testProxy(proxyId, button) {
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="loading"></i>';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'test_proxy');
            formData.append('proxy_id', proxyId);
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(`Proxy is ${data.status.toUpperCase()} - IP: ${data.ip} (${data.response_time}ms)`, 'success');
                } else {
                    showAlert(`Proxy is ${data.status.toUpperCase()}: ${data.message}`, 'error');
                }
                
                loadProxies();
            } catch (error) {
                showAlert('Failed to test proxy: ' + error.message, 'error');
            } finally {
                button.innerHTML = originalContent;
                button.disabled = false;
            }
        }

        async function testAllProxies() {
            showAlert('Testing all proxies... This may take a while', 'info');
            
            const formData = new FormData();
            formData.append('action', 'test_all_proxies');
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const liveCount = data.results.filter(r => r.status === 'live').length;
                    const deadCount = data.results.filter(r => r.status === 'dead').length;
                    showAlert(`Testing complete! Live: ${liveCount}, Dead: ${deadCount}`, 'success');
                    loadProxies();
                }
            } catch (error) {
                showAlert('Failed to test proxies: ' + error.message, 'error');
            }
        }

        async function loadProxies() {
            const formData = new FormData();
            formData.append('action', 'get_proxies');
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    proxies = data.proxies;
                    renderProxies();
                    updateStats();
                }
            } catch (error) {
                console.error('Failed to load proxies:', error);
            }
        }

        function renderProxies() {
            const container = document.getElementById('proxy-list');
            
            if (proxies.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-server" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>No proxies added yet</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = proxies.map(proxy => {
                const status = proxy.status || 'untested';
                const responseTime = proxy.response_time ? `${proxy.response_time}ms` : '';
                const proxyId = proxy._id.$oid || proxy._id;
                
                return `
                    <div class="proxy-item">
                        <div class="proxy-info">
                            <div class="proxy-address">${proxy.proxy}</div>
                            <span class="proxy-status ${status}">${status} ${responseTime}</span>
                        </div>
                        <div class="proxy-actions">
                            <button onclick="testProxy('${proxyId}', this)" class="btn btn-secondary btn-sm">
                                <i class="fas fa-check"></i> Test
                            </button>
                            <button onclick="removeProxy('${proxyId}')" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function updateStats() {
            const total = proxies.length;
            const live = proxies.filter(p => p.status === 'live').length;
            const dead = proxies.filter(p => p.status === 'dead').length;
            const untested = proxies.filter(p => !p.status || p.status === 'untested').length;
            
            document.getElementById('total-proxies').textContent = total;
            document.getElementById('live-proxies').textContent = live;
            document.getElementById('dead-proxies').textContent = dead;
            document.getElementById('untested-proxies').textContent = untested;
        }

        // Initial load
        loadProxies();
        
        // Auto-refresh every 30 seconds
        setInterval(loadProxies, 30000);
    </script>
</body>
</html>
