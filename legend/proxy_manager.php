<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';

$nonce = setSecurityHeaders();
$telegramId = TelegramAuth::requireAuth();
$db = Database::getInstance();
$user = $db->getUserByTelegramId($telegramId) ?? [];

// Ensure defaults
$user['credits'] = $user['credits'] ?? 0;
$user['display_name'] = $user['display_name'] ?? ($user['username'] ?? 'Member');
$user['role'] = $user['role'] ?? 'free';

// Keep presence heartbeat alive
$db->updatePresence($telegramId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Manager - LEGEND CHECKER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #111827;
            --bg-card: #1f2937;
            --bg-card-hover: #273449;
            --border: rgba(255, 255, 255, 0.08);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.65);
            --accent-cyan: #06b6d4;
            --accent-purple: #8b5cf6;
            --accent-green: #22c55e;
            --accent-orange: #f97316;
            --accent-red: #ef4444;
            --accent-yellow: #facc15;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #facc15;
            --info: #38bdf8;
            --font: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: radial-gradient(circle at top left, rgba(8, 16, 40, 0.85), rgba(10,10,15,0.95)),
                        radial-gradient(circle at bottom right, rgba(8, 47, 73, 0.65), rgba(10,10,15,0.95));
            min-height: 100vh;
            font-family: var(--font);
            color: var(--text-primary);
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 18px 120px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
        }

        .back-link {
            color: var(--accent-cyan);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(6, 182, 212, 0.12);
            border: 1px solid rgba(6, 182, 212, 0.3);
            transition: all 0.25s ease;
        }

        .back-link:hover {
            background: rgba(6, 182, 212, 0.24);
            transform: translateY(-1px);
        }

        .headline {
            flex: 1;
            min-width: 260px;
        }

        .headline h1 {
            font-size: 2.2rem;
            margin: 0 0 8px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .headline p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .credits-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid rgba(139, 92, 246, 0.35);
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.18), rgba(6, 182, 212, 0.15));
            box-shadow: 0 12px 34px rgba(12, 74, 110, 0.25);
        }

        .credits-chip .value {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .section {
            background: rgba(17, 24, 39, 0.82);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 28px;
            backdrop-filter: blur(10px);
            box-shadow: 0 15px 45px rgba(2, 12, 29, 0.4);
        }

        .section h2 {
            margin: 0 0 18px;
            font-size: 1.35rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grid {
            display: grid;
            gap: 18px;
        }

        @media (min-width: 768px) {
            .grid--stats {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .stat-card {
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.05);
            background: linear-gradient(145deg, rgba(31,41,55,0.92), rgba(15,23,42,0.85));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }

        .stat-card .label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .stat-card .value {
            margin-top: 14px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .toolbar button,
        .toolbar a.primary-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 12px;
            border: 1px solid transparent;
            padding: 11px 18px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.25s ease;
            background: rgba(255,255,255,0.04);
            color: var(--text-primary);
            text-decoration: none;
        }

        .toolbar .primary {
            background: linear-gradient(135deg, rgba(8, 145, 178, 0.85), rgba(59, 130, 246, 0.85));
            border: 1px solid rgba(129, 140, 248, 0.5);
            box-shadow: 0 12px 30px rgba(56, 189, 248, 0.25);
        }

        .toolbar button:hover,
        .toolbar a.primary-btn:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,0.08);
        }

        .toolbar .primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        thead {
            background: rgba(15, 23, 42, 0.75);
        }

        th, td {
            padding: 15px 18px;
            text-align: left;
            font-size: 0.95rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: rgba(59, 130, 246, 0.08);
        }

        th {
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-live {
            background: rgba(34, 197, 94, 0.18);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.35);
        }

        .status-dead {
            background: rgba(239, 68, 68, 0.18);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .status-unknown {
            background: rgba(148, 163, 184, 0.18);
            color: #cbd5f5;
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .status-slow {
            background: rgba(250, 204, 21, 0.18);
            color: #fde68a;
            border: 1px solid rgba(250, 204, 21, 0.25);
        }

        .proxy-actions {
            display: flex;
            gap: 10px;
        }

        .link-btn {
            background: none;
            border: none;
            color: var(--accent-cyan);
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            transition: color 0.2s ease;
        }

        .link-btn:hover {
            color: #67e8f9;
        }

        .form-grid {
            display: grid;
            gap: 16px;
        }

        @media (min-width: 640px) {
            .form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        input[type="text"],
        textarea {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: var(--text-primary);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 48px;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        input:focus,
        textarea:focus {
            border-color: rgba(59, 130, 246, 0.55);
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }

        textarea {
            min-height: 140px;
        }

        .helper-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .checkbox-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .message {
            padding: 14px 16px;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 0.9rem;
            display: none;
        }

        .message.show {
            display: block;
        }

        .message.success {
            background: rgba(34,197,94,0.16);
            border: 1px solid rgba(34,197,94,0.3);
            color: #bbf7d0;
        }

        .message.error {
            background: rgba(239,68,68,0.16);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fecaca;
        }

        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: var(--text-secondary);
            border: 1px dashed rgba(148, 163, 184, 0.25);
            border-radius: 16px;
        }

        .empty-state strong {
            color: var(--text-primary);
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(10, 12, 25, 0.92);
            padding: 14px 0;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .footer-nav {
            display: flex;
            justify-content: center;
            gap: 36px;
        }

        .footer-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s ease;
        }

        .footer-nav a:hover,
        .footer-nav a.active {
            color: #38bdf8;
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <div class="headline">
                <h1>Proxy Manager</h1>
                <p>Manage, validate, and monitor proxies used across LEGEND CHECKER tools.</p>
            </div>
            <div class="credits-chip">
                <i class="fas fa-coins fa-lg"></i>
                <div>
                    <div class="value" id="currentCredits"><?= number_format((int)$user['credits']); ?></div>
                    <div class="helper-text">Credits Available</div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="grid grid--stats" id="statsGrid">
                <div class="stat-card">
                    <div class="label"><i class="fas fa-layer-group"></i> Total Proxies</div>
                    <div class="value" data-stat="total">0</div>
                </div>
                <div class="stat-card">
                    <div class="label"><i class="fas fa-bolt"></i> Live</div>
                    <div class="value" data-stat="live">0</div>
                </div>
                <div class="stat-card">
                    <div class="label"><i class="fas fa-skull"></i> Dead</div>
                    <div class="value" data-stat="dead">0</div>
                </div>
                <div class="stat-card">
                    <div class="label"><i class="fas fa-question-circle"></i> Untested</div>
                    <div class="value" data-stat="unknown">0</div>
                </div>
            </div>
        </section>

        <section class="section">
            <h2><i class="fas fa-plus-circle"></i> Add Proxies</h2>
            <form id="singleProxyForm" class="form-grid" autocomplete="off">
                <div class="field">
                    <label for="proxyInput">Proxy (ip:port OR ip:port:user:pass)</label>
                    <input type="text" id="proxyInput" name="proxy" placeholder="123.123.123.123:8080:user:pass" required>
                    <span class="helper-text">Supports HTTP proxies with optional authentication.</span>
                </div>
                <div class="field">
                    <label for="labelInput">Label (optional)</label>
                    <input type="text" id="labelInput" name="label" placeholder="Primary DC Proxy">
                    <div class="checkbox-group">
                        <input type="checkbox" id="autoTestCheckbox" name="auto_test" checked>
                        <label for="autoTestCheckbox">Test immediately after saving</label>
                    </div>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <button type="submit" class="toolbar primary" style="width: fit-content;">
                        <i class="fas fa-plus"></i> Save Proxy
                    </button>
                </div>
            </form>
            <div id="singleProxyMessage" class="message"></div>
        </section>

        <section class="section">
            <h2><i class="fas fa-layer-group"></i> Bulk Import</h2>
            <form id="bulkProxyForm" autocomplete="off">
                <div class="field">
                    <label for="bulkTextarea">Paste proxies (one per line)</label>
                    <textarea id="bulkTextarea" name="proxies" placeholder="ip:port:user:pass
ip:port
proxy.example.com:8888:user:pass"></textarea>
                </div>
                <div class="field">
                    <label for="bulkLabelPrefix">Label Prefix (optional)</label>
                    <input type="text" id="bulkLabelPrefix" name="label_prefix" placeholder="Batch Proxy">
                    <span class="helper-text">Labels will be suffixed with incremental numbers.</span>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <button type="submit" class="toolbar primary" style="width: fit-content;">
                        <i class="fas fa-file-import"></i> Add Proxies
                    </button>
                </div>
            </form>
            <div id="bulkProxyMessage" class="message"></div>
        </section>

        <section class="section">
            <h2><i class="fas fa-shield-alt"></i> Managed Proxies</h2>
            <div class="toolbar">
                <button id="refreshButton" class="primary"><i class="fas fa-rotate-right"></i> Refresh</button>
                <button id="testSelectedButton"><i class="fas fa-vial-circle-check"></i> Test Selected</button>
                <button id="deleteSelectedButton"><i class="fas fa-trash-can"></i> Delete Selected</button>
                <a href="card_checker.php" class="primary-btn"><i class="fas fa-credit-card"></i> Go to Card Checker</a>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="selectAllCheckbox"></th>
                            <th>Label</th>
                            <th>Proxy</th>
                            <th>Status</th>
                            <th>Latency</th>
                            <th>Last Checked</th>
                            <th>Usage</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="proxyTableBody">
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <strong>No proxies stored yet.</strong><br>
                                    Add proxies above to start testing with LEGEND CHECKER tools.
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <footer>
        <nav class="footer-nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="tools.php"><i class="fas fa-tools"></i> Tools</a>
            <a href="proxy_manager.php" class="active"><i class="fas fa-network-wired"></i> Proxies</a>
            <a href="wallet.php"><i class="fas fa-wallet"></i> Wallet</a>
        </nav>
    </footer>

    <script nonce="<?= $nonce; ?>">
        const state = {
            proxies: [],
            selectedIds: new Set(),
            loading: false
        };

        const proxyTableBody = document.getElementById('proxyTableBody');
        const statsElements = {
            total: document.querySelector('[data-stat="total"]'),
            live: document.querySelector('[data-stat="live"]'),
            dead: document.querySelector('[data-stat="dead"]'),
            unknown: document.querySelector('[data-stat="unknown"]')
        };
        const messageSingle = document.getElementById('singleProxyMessage');
        const messageBulk = document.getElementById('bulkProxyMessage');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');

        async function apiRequest(method = 'GET', payload = null, action = null) {
            const options = {
                method,
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            };

            if (payload) {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify({
                    ...(action ? { action } : {}),
                    ...payload
                });
            }

            const url = action && method === 'POST'
                ? `api/proxies.php`
                : `api/proxies.php${action ? `?action=${encodeURIComponent(action)}` : ''}`;

            const response = await fetch(url, options);
            const data = await response.json();

            if (!response.ok || data.error) {
                throw new Error(data.message || 'Request failed');
            }

            return data;
        }

        function renderMessage(element, text, type = 'success') {
            element.className = `message show ${type}`;
            element.textContent = text;
            setTimeout(() => {
                element.classList.remove('show');
            }, 4000);
        }

        function formatLatency(latency) {
            if (latency === null || latency === undefined) return '—';
            return `${latency.toFixed(0)} ms`;
        }

        function formatDate(dateString) {
            if (!dateString) return 'Never';
            try {
                const date = new Date(dateString);
                return date.toLocaleString();
            } catch (e) {
                return dateString;
            }
        }

        function updateStats() {
            const totals = {
                total: state.proxies.length,
                live: 0,
                dead: 0,
                unknown: 0
            };

            state.proxies.forEach(proxy => {
                const status = (proxy.status || 'unknown').toLowerCase();
                if (status === 'live') totals.live += 1;
                else if (status === 'dead') totals.dead += 1;
                else totals.unknown += 1;
            });

            Object.entries(totals).forEach(([key, value]) => {
                statsElements[key].textContent = value;
            });
        }

        function createStatusPill(status) {
            const normalized = (status || 'unknown').toLowerCase();
            const classes = {
                live: 'status-live',
                dead: 'status-dead',
                slow: 'status-slow',
                unknown: 'status-unknown'
            };
            const icons = {
                live: 'fa-bolt',
                dead: 'fa-times-circle',
                slow: 'fa-clock',
                unknown: 'fa-circle-question'
            };

            return `
                <span class="status-pill ${classes[normalized] || classes.unknown}">
                    <i class="fas ${icons[normalized] || icons.unknown}"></i>
                    ${normalized.toUpperCase()}
                </span>
            `;
        }

        function createUsageText(proxy) {
            const total = proxy?.usage?.total_checks ?? 0;
            const success = proxy?.usage?.successful_checks ?? 0;
            if (!total) {
                return 'Not used yet';
            }
            return `${success}/${total} successful`;
        }

        function renderProxies() {
            if (!state.proxies.length) {
                proxyTableBody.innerHTML = `
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <strong>No proxies stored yet.</strong><br>
                                Add proxies above to start testing with LEGEND CHECKER tools.
                            </div>
                        </td>
                    </tr>
                `;
                updateStats();
                return;
            }

            const rows = state.proxies.map(proxy => {
                const isSelected = state.selectedIds.has(proxy.id);
                const statusHtml = createStatusPill(proxy.status);
                const latency = proxy.latency_ms != null ? formatLatency(proxy.latency_ms) : '—';
                const lastChecked = formatDate(proxy.last_checked_at);
                const usageText = createUsageText(proxy);
                const label = proxy.label || 'Unnamed Proxy';

                return `
                    <tr data-id="${proxy.id}">
                        <td><input type="checkbox" class="row-checkbox" data-id="${proxy.id}" ${isSelected ? 'checked' : ''}></td>
                        <td>
                            <strong>${label}</strong><br>
                            <span class="helper-text">ID: ${proxy.id}</span>
                        </td>
                        <td>
                            <code>${proxy.proxy}</code><br>
                            <span class="helper-text">
                                ${proxy.ip_address ? `<i class="fas fa-location-dot"></i> ${proxy.ip_address}` : ''}
                                ${proxy.country ? ` &middot; ${proxy.country}` : ''}
                            </span>
                        </td>
                        <td>${statusHtml}</td>
                        <td>${latency}</td>
                        <td>${lastChecked}</td>
                        <td>${usageText}</td>
                        <td>
                            <div class="proxy-actions">
                                <button class="link-btn" data-action="test" data-id="${proxy.id}">Test</button>
                                <button class="link-btn" data-action="rename" data-id="${proxy.id}">Rename</button>
                                <button class="link-btn" data-action="delete" data-id="${proxy.id}" style="color: var(--accent-red);">Delete</button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            proxyTableBody.innerHTML = rows;
            updateStats();
        }

        async function loadProxies(showToast = false) {
            try {
                state.loading = true;
                const data = await apiRequest('GET');
                state.proxies = Array.isArray(data.proxies) ? data.proxies : [];
                // Clean selected IDs if proxies removed
                state.selectedIds.forEach(id => {
                    if (!state.proxies.some(proxy => proxy.id === id)) {
                        state.selectedIds.delete(id);
                    }
                });
                renderProxies();
                if (showToast) {
                    renderMessage(messageSingle, 'Proxy list refreshed', 'success');
                }
            } catch (error) {
                renderMessage(messageSingle, error.message, 'error');
            } finally {
                state.loading = false;
            }
        }

        document.getElementById('singleProxyForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            const payload = Object.fromEntries(formData.entries());
            payload.auto_test = formData.get('auto_test') === 'on';

            try {
                const result = await apiRequest('POST', payload, 'add');
                renderMessage(messageSingle, `Proxy ${result.status === 'created' ? 'added' : 'updated'} successfully`, 'success');
                event.target.reset();
                await loadProxies();
            } catch (error) {
                renderMessage(messageSingle, error.message, 'error');
            }
        });

        document.getElementById('bulkProxyForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            const payload = Object.fromEntries(formData.entries());

            try {
                const result = await apiRequest('POST', payload, 'bulk_add');
                const createdCount = result.summary.created.length;
                const duplicateCount = result.summary.duplicates.length;
                const invalidCount = result.summary.invalid.length;
                renderMessage(
                    messageBulk,
                    `Saved ${createdCount} proxies. Duplicates: ${duplicateCount}, Invalid: ${invalidCount}`,
                    'success'
                );
                event.target.reset();
                await loadProxies();
            } catch (error) {
                renderMessage(messageBulk, error.message, 'error');
            }
        });

        document.getElementById('refreshButton').addEventListener('click', () => loadProxies(true));

        document.getElementById('testSelectedButton').addEventListener('click', async () => {
            if (state.selectedIds.size === 0) {
                renderMessage(messageSingle, 'Select proxies to test', 'error');
                return;
            }
            try {
                await apiRequest('POST', { ids: Array.from(state.selectedIds) }, 'bulk_test');
                renderMessage(messageSingle, 'Proxy tests triggered', 'success');
                await loadProxies();
            } catch (error) {
                renderMessage(messageSingle, error.message, 'error');
            }
        });

        document.getElementById('deleteSelectedButton').addEventListener('click', async () => {
            if (state.selectedIds.size === 0) {
                renderMessage(messageSingle, 'Select proxies to delete', 'error');
                return;
            }
            if (!confirm('Delete selected proxies? This cannot be undone.')) {
                return;
            }
            try {
                await apiRequest('DELETE', { ids: Array.from(state.selectedIds) });
                renderMessage(messageSingle, 'Selected proxies deleted', 'success');
                state.selectedIds.clear();
                selectAllCheckbox.checked = false;
                await loadProxies();
            } catch (error) {
                renderMessage(messageSingle, error.message, 'error');
            }
        });

        proxyTableBody.addEventListener('click', async (event) => {
            const action = event.target.dataset.action;
            const proxyId = event.target.dataset.id;
            if (!action || !proxyId) return;

            if (action === 'test') {
                try {
                    renderMessage(messageSingle, 'Checking proxy...', 'success');
                    await apiRequest('POST', { proxy_id: proxyId }, 'test');
                    await loadProxies();
                } catch (error) {
                    renderMessage(messageSingle, error.message, 'error');
                }
            }

            if (action === 'rename') {
                const newLabel = prompt('Rename proxy label:');
                if (!newLabel) return;
                try {
                    await apiRequest('POST', { proxy_id: proxyId, label: newLabel }, 'rename');
                    renderMessage(messageSingle, 'Proxy renamed', 'success');
                    await loadProxies();
                } catch (error) {
                    renderMessage(messageSingle, error.message, 'error');
                }
            }

            if (action === 'delete') {
                if (!confirm('Delete this proxy?')) return;
                try {
                    await apiRequest('DELETE', { id: proxyId });
                    renderMessage(messageSingle, 'Proxy deleted', 'success');
                    await loadProxies();
                } catch (error) {
                    renderMessage(messageSingle, error.message, 'error');
                }
            }
        });

        proxyTableBody.addEventListener('change', (event) => {
            if (!event.target.classList.contains('row-checkbox')) {
                return;
            }
            const proxyId = event.target.dataset.id;
            if (event.target.checked) {
                state.selectedIds.add(proxyId);
            } else {
                state.selectedIds.delete(proxyId);
                selectAllCheckbox.checked = false;
            }
        });

        selectAllCheckbox.addEventListener('change', (event) => {
            const checked = event.target.checked;
            state.selectedIds.clear();
            if (checked) {
                state.proxies.forEach(proxy => state.selectedIds.add(proxy.id));
            }
            renderProxies();
        });

        // Initial load
        loadProxies();

        // Presence heartbeat (align with other pages)
        setInterval(() => {
            fetch('api/presence.php', { method: 'POST', credentials: 'same-origin' })
                .catch(() => {});
        }, 120000);
    </script>
</body>
</html>
