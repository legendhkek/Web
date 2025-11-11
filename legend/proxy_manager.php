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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_proxy') {
        $proxy = trim($_POST['proxy'] ?? '');
        
        if (empty($proxy)) {
            echo json_encode(['success' => false, 'message' => 'Proxy is required']);
            exit;
        }
        
        // Validate proxy format
        $parts = explode(':', $proxy);
        if (count($parts) < 2 || count($parts) > 4) {
            echo json_encode(['success' => false, 'message' => 'Invalid proxy format. Use: ip:port or ip:port:user:pass']);
            exit;
        }
        
        // Check if proxy already exists
        if ($db->proxyExists($proxy)) {
            echo json_encode(['success' => false, 'message' => 'Proxy already exists']);
            exit;
        }
        
        // Check proxy before adding
        $checkResult = checkProxy($proxy);
        
        if (!$checkResult['success']) {
            echo json_encode([
                'success' => false,
                'message' => 'Proxy check failed: ' . ($checkResult['error'] ?? 'Unknown error')
            ]);
            exit;
        }
        
        // Add proxy to database
        $proxyData = [
            'proxy' => $proxy,
            'status' => 'live',
            'ip' => $checkResult['ip'] ?? null,
            'country' => $checkResult['country'] ?? null,
            'city' => $checkResult['city'] ?? null,
            'added_by' => $userId,
            'response_time' => $checkResult['response_time'] ?? null
        ];
        
        if ($db->addProxy($proxyData)) {
            echo json_encode([
                'success' => true,
                'message' => 'Proxy added successfully',
                'proxy' => $proxyData
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add proxy']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'add_mass_proxies') {
        $proxiesText = trim($_POST['proxies'] ?? '');
        
        if (empty($proxiesText)) {
            echo json_encode(['success' => false, 'message' => 'Proxies are required']);
            exit;
        }
        
        // Parse proxies (one per line)
        $proxies = array_filter(array_map('trim', explode("\n", $proxiesText)));
        
        if (empty($proxies)) {
            echo json_encode(['success' => false, 'message' => 'No valid proxies found']);
            exit;
        }
        
        $added = 0;
        $failed = 0;
        $skipped = 0;
        $results = [];
        
        foreach ($proxies as $proxy) {
            $proxy = trim($proxy);
            if (empty($proxy)) continue;
            
            // Validate format
            $parts = explode(':', $proxy);
            if (count($parts) < 2 || count($parts) > 4) {
                $failed++;
                $results[] = ['proxy' => $proxy, 'status' => 'invalid_format'];
                continue;
            }
            
            // Check if exists
            if ($db->proxyExists($proxy)) {
                $skipped++;
                $results[] = ['proxy' => $proxy, 'status' => 'exists'];
                continue;
            }
            
            // Check proxy
            $checkResult = checkProxy($proxy);
            
            if (!$checkResult['success']) {
                $failed++;
                $results[] = ['proxy' => $proxy, 'status' => 'failed', 'error' => $checkResult['error'] ?? 'Unknown'];
                continue;
            }
            
            // Add proxy
            $proxyData = [
                'proxy' => $proxy,
                'status' => 'live',
                'ip' => $checkResult['ip'] ?? null,
                'country' => $checkResult['country'] ?? null,
                'city' => $checkResult['city'] ?? null,
                'added_by' => $userId,
                'response_time' => $checkResult['response_time'] ?? null
            ];
            
            if ($db->addProxy($proxyData)) {
                $added++;
                $results[] = ['proxy' => $proxy, 'status' => 'added'];
            } else {
                $failed++;
                $results[] = ['proxy' => $proxy, 'status' => 'db_error'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Added: $added, Failed: $failed, Skipped: $skipped",
            'added' => $added,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => $results
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'check_proxy') {
        $proxyId = $_POST['proxy_id'] ?? '';
        
        if (empty($proxyId)) {
            echo json_encode(['success' => false, 'message' => 'Proxy ID is required']);
            exit;
        }
        
        $proxy = $db->getProxy($proxyId);
        if (!$proxy) {
            echo json_encode(['success' => false, 'message' => 'Proxy not found']);
            exit;
        }
        
        $checkResult = checkProxy($proxy['proxy']);
        
        if ($checkResult['success']) {
            $db->updateProxyStatus($proxyId, 'live', [
                'ip' => $checkResult['ip'] ?? null,
                'country' => $checkResult['country'] ?? null,
                'city' => $checkResult['city'] ?? null,
                'response_time' => $checkResult['response_time'] ?? null
            ]);
        } else {
            $db->updateProxyStatus($proxyId, 'dead', [
                'last_error' => $checkResult['error'] ?? 'Unknown error'
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'status' => $checkResult['success'] ? 'live' : 'dead',
            'result' => $checkResult
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_proxy') {
        $proxyId = $_POST['proxy_id'] ?? '';
        
        if (empty($proxyId)) {
            echo json_encode(['success' => false, 'message' => 'Proxy ID is required']);
            exit;
        }
        
        if ($db->deleteProxy($proxyId)) {
            echo json_encode(['success' => true, 'message' => 'Proxy deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete proxy']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_dead_proxies') {
        $deleted = $db->deleteDeadProxies();
        echo json_encode([
            'success' => true,
            'message' => "Deleted $deleted dead proxy(ies)",
            'deleted' => $deleted
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'check_all_proxies') {
        // Get all proxies
        $proxies = $db->getAllProxies();
        
        $checked = 0;
        $live = 0;
        $dead = 0;
        
        foreach ($proxies as $proxy) {
            $checkResult = checkProxy($proxy['proxy']);
            // Handle MongoDB ObjectId
            if (is_object($proxy['_id']) && method_exists($proxy['_id'], '__toString')) {
                $proxyId = $proxy['_id']->__toString();
            } elseif (is_array($proxy['_id'])) {
                $proxyId = $proxy['_id']->__toString();
            } else {
                $proxyId = (string)$proxy['_id'];
            }
            
            if ($checkResult['success']) {
                $db->updateProxyStatus($proxyId, 'live', [
                    'ip' => $checkResult['ip'] ?? null,
                    'country' => $checkResult['country'] ?? null,
                    'city' => $checkResult['city'] ?? null,
                    'response_time' => $checkResult['response_time'] ?? null
                ]);
                $live++;
            } else {
                $db->updateProxyStatus($proxyId, 'dead', [
                    'last_error' => $checkResult['error'] ?? 'Unknown error'
                ]);
                $dead++;
            }
            $checked++;
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Checked $checked proxies. Live: $live, Dead: $dead",
            'checked' => $checked,
            'live' => $live,
            'dead' => $dead
        ]);
        exit;
    }
}

// Function to check proxy
function checkProxy($proxy) {
    $parts = explode(':', $proxy);
    if (count($parts) < 2) {
        return ['success' => false, 'error' => 'Invalid format'];
    }
    
    $host = trim($parts[0]);
    $port = (int)trim($parts[1]);
    $user = isset($parts[2]) ? trim($parts[2]) : '';
    $pass = isset($parts[3]) ? trim($parts[3]) : '';
    
    $testUrl = 'http://httpbin.org/ip';
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROXY => "$host:$port",
        CURLOPT_PROXYUSERPWD => !empty($user) ? "$user:$pass" : null,
        CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $response = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000);
    curl_close($ch);
    
    if ($curlError || $curlErrno) {
        return [
            'success' => false,
            'error' => $curlError ?: 'Connection failed',
            'response_time' => $responseTime
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $httpCode",
            'response_time' => $responseTime
        ];
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['origin'])) {
        return [
            'success' => false,
            'error' => 'Invalid response',
            'response_time' => $responseTime
        ];
    }
    
    // Get geo info
    $country = 'Unknown';
    $city = null;
    try {
        $geoUrl = "http://ip-api.com/json/{$data['origin']}?fields=status,country,city";
        $geoCh = curl_init();
        curl_setopt_array($geoCh, [
            CURLOPT_URL => $geoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        $geoResponse = @curl_exec($geoCh);
        $geoHttpCode = curl_getinfo($geoCh, CURLINFO_HTTP_CODE);
        curl_close($geoCh);
        
        if ($geoResponse && $geoHttpCode === 200) {
            $geoData = json_decode($geoResponse, true);
            if ($geoData && isset($geoData['country']) && $geoData['status'] === 'success') {
                $country = $geoData['country'];
                $city = isset($geoData['city']) ? $geoData['city'] : null;
            }
        }
    } catch (Exception $e) {
        // Ignore geo lookup errors
    }
    
    return [
        'success' => true,
        'ip' => $data['origin'],
        'country' => $country,
        'city' => $city,
        'response_time' => $responseTime
    ];
}

// Get proxy stats
$proxyStats = $db->getProxyStats();

// Get proxies list
$proxies = $db->getAllProxies(null, 100, 0);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #00d4ff;
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card h2 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
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

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            font-family: 'Courier New', monospace;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
            background: rgba(255, 255, 255, 0.15);
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
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .proxies-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .proxies-table th,
        .proxies-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .proxies-table th {
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .proxies-table td {
            color: rgba(255, 255, 255, 0.9);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-live {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-dead {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-unknown {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
        }

        .message.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
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

            .proxies-table {
                font-size: 0.85rem;
            }

            .proxies-table th,
            .proxies-table td {
                padding: 0.5rem;
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
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-server"></i> Proxy Manager</h1>
            <p>Manage and monitor your proxy collection</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-server"></i>
                <h3>Total Proxies</h3>
                <p id="statTotal"><?php echo $proxyStats['total']; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3>Live Proxies</h3>
                <p id="statLive" style="color: #10b981;"><?php echo $proxyStats['live']; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-times-circle"></i>
                <h3>Dead Proxies</h3>
                <p id="statDead" style="color: #ef4444;"><?php echo $proxyStats['dead']; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-question-circle"></i>
                <h3>Unknown</h3>
                <p id="statUnknown"><?php echo $proxyStats['unknown']; ?></p>
            </div>
        </div>

        <div id="message" class="message"></div>

        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add Proxy</h2>
            <form id="addProxyForm">
                <div class="form-group">
                    <label for="singleProxy">Single Proxy</label>
                    <input 
                        type="text" 
                        id="singleProxy" 
                        name="proxy" 
                        placeholder="ip:port or ip:port:user:pass"
                    >
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add & Check Proxy
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-layer-group"></i> Mass Add Proxies</h2>
            <form id="massAddForm">
                <div class="form-group">
                    <label for="massProxies">Proxies (one per line)</label>
                    <textarea 
                        id="massProxies" 
                        name="proxies" 
                        placeholder="ip:port&#10;ip:port:user:pass&#10;..."
                    ></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Add & Check All Proxies
                </button>
            </form>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2><i class="fas fa-list"></i> Proxy List</h2>
                <div class="btn-group">
                    <button onclick="checkAllProxies()" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Check All
                    </button>
                    <button onclick="deleteDeadProxies()" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Dead
                    </button>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="proxies-table">
                    <thead>
                        <tr>
                            <th>Proxy</th>
                            <th>Status</th>
                            <th>IP</th>
                            <th>Location</th>
                            <th>Response Time</th>
                            <th>Last Checked</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="proxiesTableBody">
                        <?php foreach ($proxies as $proxy): 
                            // Handle MongoDB ObjectId
                            if (is_object($proxy['_id']) && method_exists($proxy['_id'], '__toString')) {
                                $proxyId = $proxy['_id']->__toString();
                            } elseif (is_array($proxy['_id'])) {
                                $proxyId = $proxy['_id']->__toString();
                            } else {
                                $proxyId = (string)$proxy['_id'];
                            }
                            $status = $proxy['status'] ?? 'unknown';
                            $lastChecked = isset($proxy['last_checked']) ? 
                                (is_object($proxy['last_checked']) && method_exists($proxy['last_checked'], 'toDateTime') ? 
                                    date('Y-m-d H:i', $proxy['last_checked']->toDateTime()->getTimestamp()) : 
                                    (is_numeric($proxy['last_checked']) ? date('Y-m-d H:i', $proxy['last_checked']) : 'Never')) : 
                                'Never';
                        ?>
                        <tr data-proxy-id="<?php echo htmlspecialchars($proxyId); ?>">
                            <td><?php echo htmlspecialchars($proxy['proxy']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php echo strtoupper($status); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($proxy['ip'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                $location = [];
                                if (!empty($proxy['city'])) $location[] = $proxy['city'];
                                if (!empty($proxy['country'])) $location[] = $proxy['country'];
                                echo htmlspecialchars(implode(', ', $location) ?: 'N/A');
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (isset($proxy['response_time'])) {
                                    echo htmlspecialchars($proxy['response_time']) . 'ms';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($lastChecked); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="checkProxy('<?php echo htmlspecialchars($proxyId); ?>')" class="action-btn btn-secondary">
                                        <i class="fas fa-sync"></i> Check
                                    </button>
                                    <button onclick="deleteProxy('<?php echo htmlspecialchars($proxyId); ?>')" class="action-btn btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
            msg.style.display = 'block';
            setTimeout(() => {
                msg.style.display = 'none';
            }, 5000);
        }

        document.getElementById('addProxyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'add_proxy');
            formData.append('proxy', document.getElementById('singleProxy').value);
            
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('singleProxy').value = '';
                    updateStats();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus"></i> Add & Check Proxy';
            }
        });

        document.getElementById('massAddForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'add_mass_proxies');
            formData.append('proxies', document.getElementById('massProxies').value);
            
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('massProxies').value = '';
                    updateStats();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Add & Check All Proxies';
            }
        });

        async function checkProxy(proxyId) {
            const formData = new FormData();
            formData.append('action', 'check_proxy');
            formData.append('proxy_id', proxyId);
            
            const row = document.querySelector(`tr[data-proxy-id="${proxyId}"]`);
            const btn = row.querySelector('.action-btn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(`Proxy status: ${data.status}`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        async function deleteProxy(proxyId) {
            if (!confirm('Are you sure you want to delete this proxy?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_proxy');
            formData.append('proxy_id', proxyId);
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    updateStats();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            }
        }

        async function checkAllProxies() {
            if (!confirm('Check all proxies? This may take a while.')) return;
            
            const formData = new FormData();
            formData.append('action', 'check_all_proxies');
            
            showMessage('Checking all proxies...', 'success');
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    updateStats();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            }
        }

        async function deleteDeadProxies() {
            if (!confirm('Delete all dead proxies?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_dead_proxies');
            
            try {
                const response = await fetch('proxy_manager.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    updateStats();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error: ' + error.message, 'error');
            }
        }

        function updateStats() {
            // Stats will be updated on page reload
        }

        // Update presence every 2 minutes
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
