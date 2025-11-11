<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();

$user = $db->getUserByTelegramId($userId);
$db->updatePresence($userId);

$proxyStats = $db->getProxyStats();
$lastCheckedAt = null;
if (!empty($proxyStats['last_checked_at'])) {
    if ($proxyStats['last_checked_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $lastCheckedAt = $proxyStats['last_checked_at']->toDateTime()->format('Y-m-d H:i:s');
    } elseif (is_numeric($proxyStats['last_checked_at'])) {
        $lastCheckedAt = date('Y-m-d H:i:s', $proxyStats['last_checked_at']);
    } else {
        $lastCheckedAt = $proxyStats['last_checked_at'];
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #151533 50%, #1b2746 100%);
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

        .container {
            max-width: 1200px;
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
            background: linear-gradient(135deg, #00d4ff, #38e0b0);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.25rem;
        }

        .stat-card h3 {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #00d4ff;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 1.75rem;
        }

        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card p.description {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #ffffff;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.2);
        }

        textarea {
            min-height: 140px;
            font-family: 'Courier New', monospace;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #38e0b0);
            color: #0d1026;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ff6b6b;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
        }

        .message.success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .table-card {
            margin-top: 2rem;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: rgba(255, 255, 255, 0.08);
        }

        th, td {
            text-align: left;
            padding: 0.85rem;
            font-size: 0.9rem;
        }

        tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.02);
        }

        tbody tr:hover {
            background: rgba(0, 212, 255, 0.08);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-live {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }

        .status-dead {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .latency {
            font-weight: 600;
        }

        .latency.fast {
            color: #34d399;
        }

        .latency.medium {
            color: #f59e0b;
        }

        .latency.slow {
            color: #f87171;
        }

        .table-empty {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.6);
        }

        @media (max-width: 992px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            table {
                display: block;
                overflow-x: auto;
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
            <div class="user-credits">
                <i class="fas fa-coins"></i>
                <span id="userCredits"><?php echo number_format($user['credits']); ?></span> Credits
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-title">
            <h1><i class="fas fa-network-wired"></i> Proxy Manager</h1>
            <p>Manage and monitor working proxies for all tools. Only validated proxies are stored.</p>
        </div>

        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3>Total Proxies</h3>
                <div class="value" id="statTotal"><?php echo number_format($proxyStats['total'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Live Proxies</h3>
                <div class="value" id="statLive"><?php echo number_format($proxyStats['live'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Dead Proxies</h3>
                <div class="value" id="statDead"><?php echo number_format($proxyStats['dead'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Stale (Needs Check)</h3>
                <div class="value" id="statStale"><?php echo number_format($proxyStats['stale'] ?? 0); ?></div>
                <div style="margin-top: 0.5rem; font-size: 0.75rem; color: rgba(255,255,255,0.5);">
                    Last check: <span id="statLastChecked"><?php echo $lastCheckedAt ? htmlspecialchars($lastCheckedAt) : '—'; ?></span>
                </div>
            </div>
        </div>

        <div class="actions-grid">
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Add Single Proxy</h2>
                <p class="description">
                    Paste a proxy in <code>host:port:user:pass</code> format. We will validate and store only if it is working.
                </p>
                <form id="singleProxyForm">
                    <label for="singleProxyInput">Proxy</label>
                    <input type="text" id="singleProxyInput" placeholder="123.45.67.89:8080:username:password" autocomplete="off" required>
                    <button type="submit" class="btn btn-primary" id="singleProxyBtn" style="margin-top: 1rem;">
                        <i class="fas fa-check-circle"></i> Check & Save
                    </button>
                </form>
                <div class="message" id="singleProxyMessage" style="display:none;"></div>
            </div>

            <div class="card">
                <h2><i class="fas fa-layer-group"></i> Add Multiple Proxies</h2>
                <p class="description">
                    Paste proxies line by line. Each proxy will be validated, and only live proxies will be added. Duplicates are skipped automatically.
                </p>
                <form id="bulkProxyForm">
                    <label for="bulkProxyInput">Proxies (line separated)</label>
                    <textarea id="bulkProxyInput" placeholder="123.45.67.89:8080:user:pass&#10;98.76.54.32:8000:user:pass"></textarea>
                    <button type="submit" class="btn btn-primary" id="bulkProxyBtn" style="margin-top: 1rem;">
                        <i class="fas fa-cloud-upload-alt"></i> Validate & Import
                    </button>
                </form>
                <div class="message" id="bulkProxyMessage" style="display:none;"></div>
            </div>

            <div class="card">
                <h2><i class="fas fa-sync-alt"></i> Daily Health Check</h2>
                <p class="description">
                    Automatically re-check stale proxies (older than 24 hours). Dead proxies older than 48 hours will be pruned.
                </p>
                <button class="btn btn-secondary" id="refreshStaleBtn">
                    <i class="fas fa-rotate"></i> Run Health Check
                </button>
                <div class="message" id="refreshMessage" style="display:none;"></div>
            </div>
        </div>

        <div class="card table-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="margin-bottom: 0;"><i class="fas fa-server"></i> Stored Proxies</h2>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <input type="text" id="searchInput" placeholder="Search by proxy, country, or IP..." style="width: 220px;">
                    <select id="statusFilter" class="btn btn-secondary" style="padding: 0.7rem 1rem;">
                        <option value="">All Status</option>
                        <option value="live">Live</option>
                        <option value="dead">Dead</option>
                    </select>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table id="proxiesTable">
                    <thead>
                        <tr>
                            <th>Proxy</th>
                            <th>Location</th>
                            <th>IP</th>
                            <th>Latency</th>
                            <th>Status</th>
                            <th>Last Check</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="proxiesTbody">
                        <tr class="table-empty">
                            <td colspan="7">Loading proxies...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const apiEndpoint = 'api/proxy_manager.php';
        const singleProxyForm = document.getElementById('singleProxyForm');
        const bulkProxyForm = document.getElementById('bulkProxyForm');
        const singleProxyBtn = document.getElementById('singleProxyBtn');
        const bulkProxyBtn = document.getElementById('bulkProxyBtn');
        const refreshBtn = document.getElementById('refreshStaleBtn');
        const singleProxyMessage = document.getElementById('singleProxyMessage');
        const bulkProxyMessage = document.getElementById('bulkProxyMessage');
        const refreshMessage = document.getElementById('refreshMessage');
        const proxiesTbody = document.getElementById('proxiesTbody');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const statsElements = {
            total: document.getElementById('statTotal'),
            live: document.getElementById('statLive'),
            dead: document.getElementById('statDead'),
            stale: document.getElementById('statStale'),
            lastChecked: document.getElementById('statLastChecked')
        };

        function showMessage(element, message, type = 'success') {
            element.textContent = message;
            element.classList.remove('success', 'error');
            element.classList.add(type);
            element.style.display = 'block';
        }

        function clearMessage(element) {
            element.style.display = 'none';
        }

        function formatLatency(latency) {
            if (latency === null || latency === undefined) return '—';
            const value = parseInt(latency, 10);
            let cls = 'slow';
            if (value < 800) cls = 'fast';
            else if (value < 1500) cls = 'medium';
            return `<span class="latency ${cls}">${value} ms</span>`;
        }

        function formatStatus(status, message) {
            const normalized = (status || '').toLowerCase();
            const cls = normalized === 'live' ? 'status-live' : 'status-dead';
            const icon = normalized === 'live' ? 'fa-check-circle' : 'fa-times-circle';
            const text = normalized === 'live' ? 'Live' : 'Dead';
            const tooltip = message ? `title="${message.replace(/"/g, "'")}"` : '';
            return `<span class="status-pill ${cls}" ${tooltip}><i class="fas ${icon}"></i> ${text}</span>`;
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (isNaN(date.getTime())) return value;
            return date.toLocaleString();
        }

        function renderTable(proxies) {
            if (!Array.isArray(proxies) || proxies.length === 0) {
                proxiesTbody.innerHTML = '<tr class="table-empty"><td colspan="7">No proxies found. Add some proxies to get started.</td></tr>';
                return;
            }

            proxiesTbody.innerHTML = proxies.map(proxy => `
                <tr data-id="${proxy.id}" data-proxy="${proxy.proxy}">
                    <td>
                        <div style="font-weight: 600;">${proxy.proxy}</div>
                        <div style="font-size: 0.75rem; color: rgba(255,255,255,0.6);">
                            Total Checks: ${proxy.total_checks} • Live: ${proxy.live_checks} • Dead: ${proxy.dead_checks}
                        </div>
                    </td>
                    <td>${proxy.country || 'Unknown'}${proxy.city ? ' • ' + proxy.city : ''}</td>
                    <td>${proxy.ip || '—'}</td>
                    <td>${formatLatency(proxy.latency_ms)}</td>
                    <td>${formatStatus(proxy.status, proxy.last_check_message)}</td>
                    <td>${formatDate(proxy.last_check_at)}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-secondary btn-recheck" data-id="${proxy.id}">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-danger btn-remove" data-id="${proxy.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            document.querySelectorAll('.btn-recheck').forEach(btn => {
                btn.addEventListener('click', () => handleRecheck(btn.dataset.id));
            });

            document.querySelectorAll('.btn-remove').forEach(btn => {
                btn.addEventListener('click', () => handleRemove(btn.dataset.id));
            });
        }

        function updateStats(stats) {
            if (!stats) return;
            statsElements.total.textContent = Number(stats.total || 0).toLocaleString();
            statsElements.live.textContent = Number(stats.live || 0).toLocaleString();
            statsElements.dead.textContent = Number(stats.dead || 0).toLocaleString();
            statsElements.stale.textContent = Number(stats.stale || 0).toLocaleString();
            statsElements.lastChecked.textContent = stats.last_checked_at ? formatDate(stats.last_checked_at) : '—';
        }

        async function loadProxies() {
            const params = new URLSearchParams();
            const query = searchInput.value.trim();
            const status = statusFilter.value;

            if (query) params.append('search', query);
            if (status) params.append('status', status);
            params.append('limit', '200');

            proxiesTbody.innerHTML = '<tr class="table-empty"><td colspan="7">Loading proxies...</td></tr>';

            try {
                const res = await fetch(`${apiEndpoint}?${params.toString()}`);
                const data = await res.json();
                if (data.success) {
                    renderTable(data.proxies || []);
                    updateStats(data.stats || {});
                } else {
                    renderTable([]);
                    showMessage(refreshMessage, data.error || 'Failed to load proxies', 'error');
                }
            } catch (error) {
                renderTable([]);
                showMessage(refreshMessage, 'Network error: ' + error.message, 'error');
            }
        }

        singleProxyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage(singleProxyMessage);

            const proxy = singleProxyForm.querySelector('input').value.trim();
            if (!proxy) {
                showMessage(singleProxyMessage, 'Please provide a proxy.', 'error');
                return;
            }

            singleProxyBtn.disabled = true;
            singleProxyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';

            try {
                const formData = new FormData();
                formData.append('action', 'add_single');
                formData.append('proxy', proxy);

                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showMessage(singleProxyMessage, 'Proxy added successfully!', 'success');
                    singleProxyForm.reset();
                    loadProxies();
                } else {
                    showMessage(singleProxyMessage, data.error || 'Failed to add proxy', 'error');
                }
            } catch (error) {
                showMessage(singleProxyMessage, 'Network error: ' + error.message, 'error');
            } finally {
                singleProxyBtn.disabled = false;
                singleProxyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Check & Save';
            }
        });

        bulkProxyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearMessage(bulkProxyMessage);

            const proxies = bulkProxyForm.querySelector('textarea').value.trim();
            if (!proxies) {
                showMessage(bulkProxyMessage, 'Enter at least one proxy.', 'error');
                return;
            }

            bulkProxyBtn.disabled = true;
            bulkProxyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            try {
                const formData = new FormData();
                formData.append('action', 'add_bulk');
                formData.append('proxies', proxies);

                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    const added = data.added_count || 0;
                    const failed = data.failed_count || 0;
                    const message = `Imported ${added} live proxies.` + (failed ? ` ${failed} failed validation.` : '');
                    showMessage(bulkProxyMessage, message, added > 0 ? 'success' : 'error');
                    if (added > 0) {
                        bulkProxyForm.reset();
                        loadProxies();
                    }
                } else {
                    showMessage(bulkProxyMessage, data.error || 'Failed to import proxies', 'error');
                }
            } catch (error) {
                showMessage(bulkProxyMessage, 'Network error: ' + error.message, 'error');
            } finally {
                bulkProxyBtn.disabled = false;
                bulkProxyBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Validate & Import';
            }
        });

        async function handleRecheck(id) {
            if (!id) return;

            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.classList.add('rechecking');
            }

            try {
                const formData = new FormData();
                formData.append('action', 'recheck');
                formData.append('proxy_id', id);

                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    loadProxies();
                } else {
                    showMessage(refreshMessage, data.error || 'Recheck failed', 'error');
                }
            } catch (error) {
                showMessage(refreshMessage, 'Network error: ' + error.message, 'error');
            } finally {
                if (row) {
                    row.classList.remove('rechecking');
                }
            }
        }

        async function handleRemove(id) {
            if (!id) return;
            if (!confirm('Remove this proxy from the manager?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('proxy_id', id);

                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    loadProxies();
                    showMessage(refreshMessage, 'Proxy removed successfully.', 'success');
                } else {
                    showMessage(refreshMessage, data.error || 'Failed to remove proxy', 'error');
                }
            } catch (error) {
                showMessage(refreshMessage, 'Network error: ' + error.message, 'error');
            }
        }

        refreshBtn.addEventListener('click', async () => {
            clearMessage(refreshMessage);
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';

            try {
                const formData = new FormData();
                formData.append('action', 'refresh_stale');

                const res = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    const msg = `Checked ${data.processed || 0} proxies • Live: ${data.live || 0} • Dead: ${data.dead || 0} • Removed: ${data.removed_dead || 0}`;
                    showMessage(refreshMessage, msg, 'success');
                    loadProxies();
                } else {
                    showMessage(refreshMessage, data.error || 'Failed to refresh proxies', 'error');
                }
            } catch (error) {
                showMessage(refreshMessage, 'Network error: ' + error.message, 'error');
            } finally {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-rotate"></i> Run Health Check';
            }
        });

        searchInput.addEventListener('input', debounce(loadProxies, 400));
        statusFilter.addEventListener('change', loadProxies);

        function debounce(fn, delay = 300) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        loadProxies();

        setInterval(() => {
            fetch('api/presence.php', { method: 'POST' });
        }, 120000);
    </script>
</body>
</html>
