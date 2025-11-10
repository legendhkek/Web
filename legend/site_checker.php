<?php
$site_input_raw = '';
if (isset($_POST['sites_input'])) {
    $site_input_raw = trim($_POST['sites_input']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SiteScope: Real-time Site Checker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-color: #000000;
            --panel-bg: #1a2b49;
            --input-bg: #223041;
            --text-color: #00ffea;
            --placeholder-color: #95a5a6;
            
            --button-primary: #00e676;
            --button-danger: #ff073a;
            --button-warning: #e67e22;
            
            --button-hover-primary: #69f0ae;
            --button-hover-danger: #c0392b;
            --button-hover-warning: #f39c12;
            
            --link-color: #3498db;
            --border-color: #00bcd4;
            --gradient-border-light: #8e44ad;
            --gradient-border-dark: #3498db;
            --shadow-glow: rgba(0, 255, 234, 0.5);
            
            --status-valid: #28a745;
            --status-invalid: #dc3545;
            --status-checking: #f39c12;

            --font-mono: 'Share Tech Mono', monospace;
            --font-heading: 'Orbitron', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-mono);
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 25%, #16213e 50%, #0f3460 75%, #533483 100%);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 10px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            position: relative;
            margin: 0;
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            body {
                padding: 5px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 3px;
                font-size: 12px;
            }
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(0, 255, 234, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(83, 52, 131, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(15, 52, 96, 0.1) 0%, transparent 50%),
                linear-gradient(to right, rgba(0,255,234,0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0,255,234,0.03) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 100% 100%, 40px 40px, 40px 40px;
            opacity: 0.6;
            pointer-events: none;
            z-index: 0;
        }

        .header-links {
            align-self: flex-start;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .back-to-dashboard, .history-button {
            text-decoration: none;
            color: var(--link-color);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease, text-shadow 0.3s ease;
            font-size: 1.1em;
            text-shadow: 0 0 8px rgba(52, 152, 219, 0.3);
        }
        .back-to-dashboard:hover, .history-button:hover {
            color: var(--button-hover-primary);
            text-shadow: 0 0 15px var(--button-hover-primary);
        }
        .history-button {
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-family: var(--font-mono);
        }
        .history-button i {
            font-size: 1.3em;
            color: var(--link-color);
        }
        .history-button:hover i {
            color: var(--button-hover-primary);
        }

        .main-wrapper {
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-direction: column;
            gap: 25px;
            position: relative;
            z-index: 1;
        }

        .panel {
            background: var(--panel-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 30px var(--shadow-glow);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 17px;
            background: linear-gradient(45deg, var(--gradient-border-light), var(--gradient-border-dark));
            z-index: -1;
            filter: blur(8px);
            opacity: 0.2;
            transition: opacity 0.3s ease;
        }
        .panel:hover::before {
            opacity: 0.4;
        }

        h2 {
            font-family: var(--font-heading);
            color: var(--text-color);
            margin-bottom: 25px;
            font-weight: 700;
            font-size: 2.2em;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-shadow: 0 0 15px var(--shadow-glow);
        }

        textarea {
            padding: 15px;
            margin: 10px 0;
            width: 100%;
            box-sizing: border-box;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-color);
            font-family: var(--font-mono);
            font-size: 16px;
            min-height: 150px;
            resize: vertical;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        textarea:focus {
            border-color: var(--button-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.3), 0 0 15px rgba(0, 230, 118, 0.5);
        }
        textarea::placeholder {
            color: var(--placeholder-color);
            opacity: 0.7;
        }

        .input-group {
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            color: var(--text-color);
            font-size: 1.1em;
            text-shadow: 0 0 8px rgba(0, 255, 234, 0.3);
        }
        .input-group label {
            white-space: nowrap;
        }
        .input-group input[type="number"] {
            width: 80px;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-color);
            font-family: var(--font-mono);
            font-size: 16px;
            text-align: center;
            -moz-appearance: textfield;
        }
        .input-group input[type="number"]::-webkit-outer-spin-button,
        .input-group input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .input-group input[type="number"]:focus {
            border-color: var(--button-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.3);
        }

        .button-group {
            display: flex;
            gap: 20px;
            margin-top: 25px;
            justify-content: center;
        }
        button {
            padding: 15px 30px;
            flex-grow: 1;
            max-width: 180px;
            color: var(--bg-color);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 700;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        button.play-button { 
            background: var(--button-primary);
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.5);
        }
        button.stop-button { 
            background: var(--button-danger);
            box-shadow: 0 0 20px rgba(255, 7, 58, 0.5);
            color: white;
        }
        button.clear-button { 
            background: var(--button-warning);
            box-shadow: 0 0 20px rgba(230, 126, 34, 0.5);
            color: white;
        }

        button:hover {
            transform: translateY(-3px);
        }
        button.play-button:hover { 
            background: var(--button-hover-primary);
            box-shadow: 0 0 30px var(--button-hover-primary);
        }
        button.stop-button:hover { 
            background: var(--button-hover-danger);
            box-shadow: 0 0 30px var(--button-hover-danger);
        }
        button.clear-button:hover { 
            background: var(--button-hover-warning);
            box-shadow: 0 0 30px var(--button-hover-warning);
        }

        button:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.6;
        }

        .stats-panel {
            padding: 25px;
        }
        .stats-panel h3 {
            font-family: var(--font-heading);
            text-align: center;
            margin-bottom: 20px;
            color: var(--text-color);
            font-size: 1.8em;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 0 10px var(--shadow-glow);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--input-bg);
            padding: 15px 20px;
            border-radius: 10px;
            font-size: 1.2em;
            font-weight: bold;
            border: 1px solid rgba(0, 255, 234, 0.3);
            box-shadow: 0 0 10px rgba(0, 255, 234, 0.2);
        }
        .stat-item span:first-child {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stat-item .icon {
            font-size: 1.4em;
            text-shadow: 0 0 8px var(--text-color);
        }
        .stat-item .count {
            color: var(--link-color);
            font-family: var(--font-heading);
            font-size: 1.3em;
            text-shadow: 0 0 10px var(--link-color);
        }

        .stat-item.total .count { color: var(--text-color); text-shadow: 0 0 10px var(--text-color); }
        .stat-item.valid .count { color: var(--status-valid); text-shadow: 0 0 10px var(--status-valid); }
        .stat-item.invalid .count { color: var(--status-invalid); text-shadow: 0 0 10px var(--status-invalid); }
        .stat-item.yet-to-check .count { color: var(--status-checking); text-shadow: 0 0 10px var(--status-checking); }

        .results-panel {
            padding: 25px;
            margin-top: 25px;
        }
        .results-panel h3 {
            font-family: var(--font-heading);
            text-align: left;
            margin-bottom: 15px;
            color: var(--text-color);
            font-size: 1.8em;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 0 10px var(--shadow-glow);
        }
        .result-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        .tab-button {
            background: var(--input-bg);
            color: var(--text-color);
            padding: 12px 22px;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background 0.3s ease, color 0.3s ease, border-bottom-color 0.3s ease;
            margin-right: 5px;
            border-bottom: 2px solid transparent;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tab-button:hover {
            background: #4a6fa5;
            color: white;
        }
        .tab-button.active {
            background: var(--border-color);
            color: white;
            border-bottom: 2px solid var(--button-primary);
        }

        .tab-content {
            display: none;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            position: relative;
        }
        .tab-content.active {
            display: block;
        }

        .result-cards-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .result-cards-header h4 {
            color: var(--text-color);
            font-size: 1.2em;
            text-shadow: 0 0 5px var(--shadow-glow);
        }
        .copy-all-button, .download-button {
            background: var(--link-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 0.9em;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 10px;
        }
        .copy-all-button:hover, .download-button:hover {
            background: var(--button-hover-primary);
            transform: translateY(-2px);
            box-shadow: 0 0 10px var(--button-hover-primary);
        }
        .copy-all-button.copied, .download-button.copied {
            background: var(--status-valid);
        }

        .result-sites-container {
            max-height: 450px;
            overflow-y: auto;
            padding-right: 15px;
            scrollbar-width: thin;
            scrollbar-color: var(--link-color) var(--input-bg);
        }
        .result-sites-container::-webkit-scrollbar {
            width: 8px;
        }
        .result-sites-container::-webkit-scrollbar-track {
            background: var(--input-bg);
            border-radius: 10px;
        }
        .result-sites-container::-webkit-scrollbar-thumb {
            background-color: var(--link-color);
            border-radius: 10px;
            border: 2px solid var(--input-bg);
        }

        .result-site-card {
            background: var(--input-bg);
            border-left: 5px solid;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 8px;
            font-size: 14px;
            word-wrap: break-word;
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInSlideUp 0.3s forwards;
            border: 1px solid rgba(0, 255, 234, 0.1);
        }

        .result-site-card.valid { border-left-color: var(--status-valid); }
        .result-site-card.invalid { border-left-color: var(--status-invalid); }

        .result-site-card div strong {
            color: var(--placeholder-color);
            min-width: 90px;
            display: inline-block;
            text-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        .result-site-card div {
            color: var(--text-color);
        }

        .copy-button {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 255, 234, 0.2);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 8px;
            font-size: 12px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s ease, background 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .result-site-card:hover .copy-button {
            opacity: 1;
        }
        .copy-button:hover {
            background: rgba(0, 255, 234, 0.4);
        }
        .copy-button.copied {
            background: var(--status-valid);
        }

        @keyframes fadeInSlideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .loading-message {
            text-align: center;
            color: var(--link-color);
            font-size: 1.2em;
            margin-top: 25px;
            padding: 15px;
            background: var(--panel-bg);
            border-radius: 8px;
            box-shadow: 0 0 15px var(--shadow-glow);
            width: 300px;
            max-width: 80%;
            margin-left: auto;
            margin-right: auto;
            display: none;
            border: 1px solid var(--border-color);
            font-family: var(--font-heading);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--panel-bg);
            margin: auto;
            padding: 30px;
            border: 1px solid var(--border-color);
            border-radius: 15px;
            width: 85%;
            max-width: 800px;
            box-shadow: 0 0 40px var(--shadow-glow);
            animation: fadeIn 0.3s ease-out;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .modal-content h3 {
            font-family: var(--font-heading);
            color: var(--text-color);
            margin-bottom: 25px;
            text-align: center;
            font-size: 2em;
            text-shadow: 0 0 10px var(--shadow-glow);
        }

        .close-button {
            color: var(--text-color);
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }
        .close-button:hover,
        .close-button:focus {
            color: var(--button-danger);
            text-decoration: none;
            text-shadow: 0 0 15px var(--button-danger);
        }

        .history-list {
            max-height: 60vh;
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 15px;
            scrollbar-width: thin;
            scrollbar-color: var(--link-color) var(--input-bg);
        }
        .history-list::-webkit-scrollbar {
            width: 8px;
        }
        .history-list::-webkit-scrollbar-track {
            background: var(--input-bg);
            border-radius: 10px;
        }
        .history-list::-webkit-scrollbar-thumb {
            background-color: var(--link-color);
            border-radius: 10px;
            border: 2px solid var(--input-bg);
        }

        .history-item {
            background: var(--input-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
            border: 1px solid rgba(0, 255, 234, 0.2);
            box-shadow: 0 0 15px rgba(0, 255, 234, 0.1);
        }
        .history-item .timestamp {
            font-size: 1em;
            color: var(--placeholder-color);
            margin-bottom: 5px;
            text-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        .history-item pre {
            background: #1a2b49;
            padding: 12px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.9em;
            color: #ccc;
            border: 1px dashed rgba(0, 255, 234, 0.1);
        }
        .history-item-buttons {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            justify-content: flex-end;
        }
        .history-item-buttons button {
            flex-grow: 0;
            max-width: 150px;
            padding: 10px 20px;
            font-size: 0.95em;
            border-radius: 8px;
            color: white;
        }
        .history-item-buttons .load-button { 
            background: var(--link-color);
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
        }
        .history-item-buttons .load-button:hover { 
            background: #5dade2;
            box-shadow: 0 0 15px #5dade2;
        }
        .history-item-buttons .delete-button { 
            background: var(--button-danger);
            box-shadow: 0 0 10px rgba(255, 7, 58, 0.5);
        }
        .history-item-buttons .delete-button:hover { 
            background: var(--button-hover-danger);
            box-shadow: 0 0 15px var(--button-hover-danger);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .footer-attribution {
            margin-top: 40px;
            font-size: 0.95em;
            color: rgba(0, 255, 234, 0.7);
            position: relative;
            z-index: 1;
            text-shadow: 0 0 8px rgba(0, 255, 234, 0.3);
            text-align: center;
            padding-bottom: 20px;
        }
        .footer-attribution a {
            color: var(--text-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }
        .footer-attribution a:hover {
            color: var(--button-primary);
            text-shadow: 0 0 15px var(--button-primary);
        }

        @media (max-width: 768px) {
            .panel {
                padding: 15px;
            }
            h2 {
                font-size: 1.8em;
            }
            .button-group {
                flex-direction: column;
                gap: 12px;
            }
            button {
                max-width: 100%;
                padding: 12px 20px;
                font-size: 1em;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stat-item {
                font-size: 1.1em;
                padding: 10px 15px;
            }
            .tab-button {
                padding: 10px 15px;
                font-size: 0.9em;
            }
            .modal-content {
                width: 95%;
                padding: 20px;
            }
            .close-button {
                font-size: 24px;
                top: 10px;
                right: 15px;
            }
            .history-item {
                padding: 15px;
            }
            .history-item-buttons {
                flex-direction: column;
                gap: 8px;
            }
            .history-item-buttons button {
                max-width: 100%;
            }
            .loading-message {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="header-links">
        <a href="index.php" class="back-to-dashboard"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <button id="historyButton" class="history-button" title="View past scans">
            <i class="fas fa-history"></i> Scan History
        </button>
    </div>

    <div class="main-wrapper">
        <div class="panel">
            <h2>Site Status Analyzer</h2>
            <form id="checkForm">
                <textarea name="sites_input" id="sitesInput" placeholder="Enter URLs (one per line, e.g., https://example.com)"><?php echo htmlspecialchars($site_input_raw); ?></textarea>
                
                <textarea name="proxy_input" id="proxyInput" placeholder="Enter Proxy (format: ip:port:user:pass) - Optional" rows="2"></textarea>
                
                <div class="input-group">
                    <label for="concurrencyLimit">Concurrent Checks:</label>
                    <input type="number" id="concurrencyLimit" value="3" min="1" max="20">
                </div>

                <div class="button-group">
                    <button type="submit" id="playButton" class="play-button">Start Scan</button>
                    <button type="button" id="stopButton" class="stop-button" disabled>Stop Scan</button>
                    <button type="button" id="clearButton" class="clear-button">Clear All</button>
                </div>
            </form>
        </div>

        <div class="panel stats-panel">
            <h3>Scan Overview</h3>
            <div class="stats-grid">
                <div class="stat-item total"><span><span class="icon fas fa-list"></span> Total Sites</span><span id="totalCount" class="count">0</span></div>
                <div class="stat-item valid"><span><span class="icon fas fa-check-circle"></span> Active Sites</span><span id="validCount" class="count">0</span></div>
                <div class="stat-item invalid"><span><span class="icon fas fa-times-circle"></span> Inactive Sites</span><span id="invalidCount" class="count">0</span></div>
                <div class="stat-item yet-to-check"><span><span class="icon fas fa-hourglass-half"></span> Pending Checks</span><span id="yetToCheckCount" class="count">0</span></div>
            </div>
        </div>

        <div class="panel results-panel">
            <h3>Scan Results</h3>
            <div class="result-tabs">
                <button class="tab-button active" data-tab="valid">Active Sites</button>
                <button class="tab-button" data-tab="invalid">Inactive Sites</button>
            </div>
            
            <div id="validSitesTabContent" class="tab-content active">
                <div class="result-cards-header">
                    <h4>Active Sites List</h4>
                    <button class="copy-all-button" data-target="validSitesContainer">Copy All Active</button>
                    <button class="download-button" data-target="validSitesContainer" data-filename="active_sites.txt">Download Active</button>
                </div>
                <div id="validSitesContainer" class="result-sites-container">
                </div>
            </div>
            <div id="invalidSitesTabContent" class="tab-content">
                <div class="result-cards-header">
                    <h4>Inactive Sites List</h4>
                    <button class="copy-all-button" data-target="invalidSitesContainer">Copy All Inactive</button>
                    <button class="download-button" data-target="invalidSitesContainer" data-filename="inactive_sites.txt">Download Inactive</button>
                </div>
                <div id="invalidSitesContainer" class="result-sites-container">
                </div>
            </div>
        </div>
    </div>

    <div id="loadingMessage" class="loading-message">Scanning... Please wait.</div>

    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Previous Scans</h3>
            <div id="historyList" class="history-list">
            </div>
        </div>
    </div>

    <div class="footer-attribution">
        Developed by <a href="https://t.me/Extriem_Chkbot" target="_blank">@amanpandey1212</a>
    </div>

    <script>
        const playButton = document.getElementById('playButton');
        const stopButton = document.getElementById('stopButton');
        const clearButton = document.getElementById('clearButton');
        const sitesInput = document.getElementById('sitesInput');
        const concurrencyLimitInput = document.getElementById('concurrencyLimit');
        const loadingMessage = document.getElementById('loadingMessage');
        const historyButton = document.getElementById('historyButton');
        const historyModal = document.getElementById('historyModal');
        const closeModalButton = historyModal.querySelector('.close-button');
        const historyList = document.getElementById('historyList');
        const totalCount = document.getElementById('totalCount');
        const validCount = document.getElementById('validCount');
        const invalidCount = document.getElementById('invalidCount');
        const yetToCheckCount = document.getElementById('yetToCheckCount');

        const validSitesContainer = document.getElementById('validSitesContainer');
        const invalidSitesContainer = document.getElementById('invalidSitesContainer');

        let siteQueue = [];
        let originalSitesInput = [];
        let processing = false;
        let activeChecks = 0;
        let stopRequested = false;
        let allSites = [];

        const HISTORY_KEY = 'siteCheckerHistory';
        function saveHistory(sites, results) {
            let history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
            const timestamp = new Date().toLocaleString();
            history.unshift({
                timestamp: timestamp,
                sites: sites,
                results: results
            });
            history = history.slice(0, 15);
            localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        }

        function loadHistory() {
            const history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
            historyList.innerHTML = '';

            if (history.length === 0) {
                historyList.innerHTML = '<p style="text-align: center; color: var(--placeholder-color);">No scan history found. Start a scan to create your first entry.</p>';
                return;
            }

            history.forEach((item, index) => {
                const historyItemDiv = document.createElement('div');
                historyItemDiv.className = 'history-item';
                const sitesPreview = item.sites.slice(0, 5).join('\n') + (item.sites.length > 5 ? '\n...(' + (item.sites.length - 5) + ' more URLs)' : '');

                historyItemDiv.innerHTML = `
                    <div class="timestamp"><strong>Scan Initiated:</strong> ${item.timestamp}</div>
                    <div><strong>Input URLs:</strong><pre>${sitesPreview}</pre></div>
                    <div><strong>Active Results:</strong> ${item.results.valid.length}</div>
                    <div><strong>Inactive Results:</strong> ${item.results.invalid.length}</div>
                    <div class="history-item-buttons">
                        <button class="load-button" data-index="${index}">Load Input</button>
                        <button class="delete-button" data-index="${index}">Delete Log</button>
                    </div>
                `;
                historyList.appendChild(historyItemDiv);
            });

            historyList.querySelectorAll('.load-button').forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    const selectedItem = history[index];
                    if (selectedItem) {
                        sitesInput.value = selectedItem.sites.join('\n');
                        alert('Input URLs loaded from history!');
                        closeModalButton.click();
                        clearResultsAndCounts();
                    }
                });
            });
            historyList.querySelectorAll('.delete-button').forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this history entry?')) {
                        const indexToDelete = parseInt(this.dataset.index);
                        let currentHistory = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
                        currentHistory.splice(indexToDelete, 1);
                        localStorage.setItem(HISTORY_KEY, JSON.stringify(currentHistory));
                        loadHistory();
                    }
                });
            });
        }

        function updateCounts() {
            totalCount.textContent = allSites.length;
            yetToCheckCount.textContent = siteQueue.length + activeChecks;
        }

        function createResultSiteCardElement(data, statusType) {
            const siteDiv = document.createElement('div');
            siteDiv.className = `result-site-card ${statusType.toLowerCase()}`;
            siteDiv.innerHTML = `
                <div><strong>URL:</strong> ${data.site}</div>
                <div><strong>Status:</strong> ${data.status}</div>
                <div><strong>API Response:</strong> ${data.api_response ? data.api_response : 'N/A'}</div>
                <div><strong>HTTP Code (Autog):</strong> ${data.response_code}</div>
                <div><strong>Time:</strong> ${data.time}</div>
                <button class="copy-button" 
                    data-site="${encodeURIComponent(data.site)}"
                    data-status="${encodeURIComponent(data.status)}"
                    data-api-response="${encodeURIComponent(data.api_response || 'N/A')}"
                    data-response-code="${encodeURIComponent(data.response_code)}"
                    data-time="${encodeURIComponent(data.time)}">
                    <i class="far fa-copy"></i>
                </button>
            `;
            const copyBtn = siteDiv.querySelector('.copy-button');
            copyBtn.addEventListener('click', () => {
                const details = {
                    site: decodeURIComponent(copyBtn.dataset.site),
                    status: decodeURIComponent(copyBtn.dataset.status),
                    api_response: decodeURIComponent(copyBtn.dataset.apiResponse),
                    response_code: decodeURIComponent(copyBtn.dataset.responseCode),
                    time: decodeURIComponent(copyBtn.dataset.time)
                };
                copySiteDetails(copyBtn, details);
            });
            return siteDiv;
        }

        async function copySiteDetails(buttonElement, details) {
            const textToCopy = `URL: ${details.site}\nStatus: ${details.status}\nAPI Response: ${details.api_response}\nHTTP Code (Autog): ${details.response_code}\nTime: ${details.time}`;
            try {
                await navigator.clipboard.writeText(textToCopy);
                buttonElement.innerHTML = '<i class="fas fa-check"></i> Copied!';
                buttonElement.classList.add('copied');
                setTimeout(() => {
                    buttonElement.innerHTML = '<i class="far fa-copy"></i>';
                    buttonElement.classList.remove('copied');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy text: ', err);
                alert('Failed to copy details. Your browser might block automatic copying. Please copy manually.');
            }
        }

        function copyAllSites(targetContainerId, buttonElement) {
            const container = document.getElementById(targetContainerId);
            const sites = Array.from(container.querySelectorAll('.result-site-card'));
            if (sites.length === 0) {
                alert('No URLs to copy!');
                return;
            }

            let allTextToCopy = [];
            sites.forEach(siteDiv => {
                const details = {
                    site: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.site),
                    status: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.status),
                    api_response: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.apiResponse),
                    response_code: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.responseCode),
                    time: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.time)
                };
                allTextToCopy.push(`URL: ${details.site}\nStatus: ${details.status}\nAPI Response: ${details.api_response}\nHTTP Code (Autog): ${details.response_code}\nTime: ${details.time}`);
            });
            try {
                navigator.clipboard.writeText(allTextToCopy.join('\n\n'));
                buttonElement.textContent = 'Copied All!';
                buttonElement.classList.add('copied');
                setTimeout(() => {
                    buttonElement.textContent = buttonElement.dataset.originalText;
                    buttonElement.classList.remove('copied');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy all text: ', err);
                alert('Failed to copy all details. Your browser might block automatic copying. Please copy manually.');
            }
        }

        function downloadResults(targetContainerId, filename) {
            const container = document.getElementById(targetContainerId);
            const sites = Array.from(container.querySelectorAll('.result-site-card'));
            if (sites.length === 0) {
                alert('No URLs to download!');
                return;
            }

            let allTextToDownload = [];
            sites.forEach(siteDiv => {
                const details = {
                    site: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.site),
                    status: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.status),
                    api_response: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.apiResponse),
                    response_code: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.responseCode),
                    time: decodeURIComponent(siteDiv.querySelector('.copy-button').dataset.time)
                };
                allTextToDownload.push(`URL: ${details.site}\nStatus: ${details.status}\nAPI Response: ${details.api_response}\nHTTP Code (Autog): ${details.response_code}\nTime: ${details.time}`);
            });

            const blob = new Blob([allTextToDownload.join('\n\n')], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }

        function removeSiteFromInput(siteToRemove) {
            let currentSites = sitesInput.value.split('\n').map(site => site.trim());
            const filteredSites = [];
            let removed = false;
            for (const site of currentSites) {
                if (site === siteToRemove && !removed) {
                    removed = true;
                } else {
                    filteredSites.push(site);
                }
            }
            sitesInput.value = filteredSites.join('\n');
        }

        function clearResultsAndCounts() {
            validSitesContainer.innerHTML = '';
            invalidSitesContainer.innerHTML = '';
            validCount.textContent = '0';
            invalidCount.textContent = '0';
            totalCount.textContent = '0';
            yetToCheckCount.textContent = '0';
        }

        async function processSite(site) {
            if (stopRequested) {
                activeChecks--;
                updateCounts();
                return;
            }

            activeChecks++;
            updateCounts();
            removeSiteFromInput(site);

            try {
                // Get proxy from input field (single proxy for entire session)
                const proxyInput = document.getElementById('proxyInput').value.trim();
                let proxyParam = '';
                if (proxyInput) {
                    // Validate proxy format: ip:port:user:pass
                    const proxyParts = proxyInput.split(':');
                    if (proxyParts.length === 4) {
                        proxyParam = `&proxy=${encodeURIComponent(proxyInput)}`;
                    } else {
                        console.warn('Invalid proxy format. Expected: ip:port:user:pass');
                    }
                }
                
                const response = await fetch(`check_site_ajax.php?site=${encodeURIComponent(site)}${proxyParam}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                let targetContainer;
                let newCountElement;
                let uiStatusType = data.is_valid_site ? 'VALID' : 'INVALID';
                if (uiStatusType === 'VALID') {
                    targetContainer = validSitesContainer;
                    newCountElement = validCount;
                } else {
                    targetContainer = invalidSitesContainer;
                    newCountElement = invalidCount;
                }

                const finalSiteElement = createResultSiteCardElement(data, uiStatusType);
                targetContainer.prepend(finalSiteElement);
                newCountElement.textContent = parseInt(newCountElement.textContent) + 1;
                if (uiStatusType === 'VALID') {
                    currentSessionResults.valid.push(data);
                } else {
                    currentSessionResults.invalid.push(data);
                }

            } catch (error) {
                console.error('Error checking URL:', site, error);
                const errorData = {
                    site: site,
                    status: `ERROR: ${error.message || 'Check failed'}`,
                    response_code: 'N/A',
                    api_response: 'Client-side network error or server not responding.',
                    time: 'N/A'
                };
                const errorSiteElement = createResultSiteCardElement(errorData, 'invalid');
                invalidSitesContainer.prepend(errorSiteElement);
                invalidCount.textContent = parseInt(invalidCount.textContent) + 1;
                currentSessionResults.invalid.push(errorData);
            } finally {
                activeChecks--;
                updateCounts();
                if (!stopRequested && siteQueue.length > 0) {
                    const nextSite = siteQueue.shift();
                    processSite(nextSite);
                } else if (activeChecks === 0) {
                    loadingMessage.style.display = 'none';
                    playButton.disabled = false;
                    stopButton.disabled = true;
                    processing = false;
                    stopRequested = false;
                    saveHistory(originalSitesInput, currentSessionResults);
                    currentSessionResults = { valid: [], invalid: [] };
                }
            }
        }

        let currentSessionResults = { valid: [], invalid: [] };

        document.getElementById('checkForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            if (processing) return;

            const sitesRaw = sitesInput.value;
            const concurrencyLimit = parseInt(concurrencyLimitInput.value, 10);

            const sites = sitesRaw.split('\n').map(site => site.trim()).filter(site => site.length > 0);

            if (sites.length === 0) {
                alert('Please enter at least one URL to scan.');
                return;
            }
            if (isNaN(concurrencyLimit) || concurrencyLimit < 1 || concurrencyLimit > 20) {
                alert('Please enter a concurrency limit between 1 and 20.');
                return;
            }

            clearResultsAndCounts();
            originalSitesInput = [...sites];
            siteQueue = [...sites];
            stopRequested = false;
            processing = true;
            activeChecks = 0;
            allSites = [...sites];

            loadingMessage.style.display = 'block';
            playButton.disabled = true;
            stopButton.disabled = false;
            updateCounts(); 

            for (let i = 0; i < concurrencyLimit && siteQueue.length > 0; i++) {
                const site = siteQueue.shift();
                processSite(site);
            }
        });
        stopButton.addEventListener('click', () => {
            stopRequested = true;
            loadingMessage.textContent = 'Scan stopping...';
            stopButton.disabled = true;
            playButton.disabled = false;
        });
        clearButton.addEventListener('click', () => {
            sitesInput.value = '';
            clearResultsAndCounts();
            loadingMessage.style.display = 'none';
            playButton.disabled = false;
            stopButton.disabled = true;
            processing = false;
            stopRequested = false;
            siteQueue = [];
            allSites = [];
            currentSessionResults = { valid: [], invalid: [] };
            document.querySelectorAll('.copy-all-button').forEach(btn => {
                btn.textContent = btn.dataset.originalText;
                btn.classList.remove('copied');
            });
            document.querySelectorAll('.download-button').forEach(btn => {
                btn.textContent = btn.dataset.originalText;
                btn.classList.remove('copied');
            });
        });
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                const targetTabId = this.dataset.tab + 'SitesTabContent';
                document.getElementById(targetTabId).classList.add('active');
            });
        });
        document.querySelectorAll('.copy-all-button').forEach(button => {
            button.dataset.originalText = button.textContent;
            button.addEventListener('click', function() {
                const targetContainerId = this.dataset.target;
                copyAllSites(targetContainerId, this);
            });
        });
        document.querySelectorAll('.download-button').forEach(button => {
            button.dataset.originalText = button.textContent;
            button.addEventListener('click', function() {
                const targetContainerId = this.dataset.target;
                const filename = this.dataset.filename;
                downloadResults(targetContainerId, filename);
            });
        });

        historyButton.addEventListener('click', () => {
            loadHistory();
            historyModal.style.display = 'flex';
        });
        closeModalButton.addEventListener('click', () => {
            historyModal.style.display = 'none';
        });
        window.addEventListener('click', (event) => {
            if (event.target === historyModal) {
                historyModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
