<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'utils.php';

$nonce = setSecurityHeaders();
$telegramId = TelegramAuth::requireAuth();
$db = Database::getInstance();
$user = $db->getUserByTelegramId($telegramId) ?? [];
$db->updatePresence($telegramId);

$card_input_raw = isset($_POST['cards_input']) ? trim($_POST['cards_input']) : '';
$site_input_raw = isset($_POST['sites_input']) ? trim($_POST['sites_input']) : '';

$availableCredits = (int)($user['credits'] ?? 0);
?>

<!DOCTYPE html>
<html>
<head>
    <title>LEGEND CHECKER - Card Checker</title>
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
            
            --status-charge: #28a745;
            --status-live: #17a2b8;
            --status-dead: #dc3545;
            --status-checking: #f39c12;

            --font-mono: 'Share Tech Mono', monospace;
            --font-heading: 'Orbitron', sans-serif;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .credit-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 212, 255, 0.12);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 20px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.95em;
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .credit-chip i {
            color: #00d4ff;
        }

        .manage-proxies-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid rgba(124, 58, 237, 0.4);
            color: #c084fc;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .manage-proxies-link:hover {
            color: #e9d5ff;
            border-color: rgba(124, 58, 237, 0.7);
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.3);
        }

        .proxy-control {
            margin: 12px 0 18px;
            padding: 15px;
            border-radius: 12px;
            background: rgba(0, 255, 234, 0.06);
            border: 1px solid rgba(0, 255, 234, 0.2);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .proxy-control-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .proxy-control-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        select,
        .proxy-control input[type="text"] {
            background: var(--input-bg);
            border: 1px solid rgba(0, 255, 234, 0.3);
            color: var(--text-color);
            border-radius: 8px;
            padding: 10px 12px;
            font-family: var(--font-mono);
            font-size: 0.95rem;
            flex: 1;
            min-width: 200px;
        }

        select:focus,
        .proxy-control input[type="text"]:focus {
            border-color: var(--button-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.2);
        }

        .proxy-refresh-button {
            background: linear-gradient(135deg, #00bcd4, #7c3aed);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .proxy-refresh-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(124, 58, 237, 0.4);
        }

        .proxy-helper-text {
            font-size: 0.85rem;
            color: var(--placeholder-color);
        }

        .proxy-extra select {
            width: 100%;
        }

        .proxy-extra select option {
            font-size: 0.9rem;
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
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            flex-wrap: wrap;
            gap: 10px;
        }

        @media (max-width: 480px) {
            .header-links {
                margin-bottom: 10px;
                gap: 5px;
            }

            .header-meta {
                flex-direction: column;
                align-items: flex-start;
            }

            .proxy-control-row {
                flex-direction: column;
            }

            .proxy-refresh-button {
                width: 100%;
                justify-content: center;
            }
        }

        .back-to-dashboard, .history-button {
            text-decoration: none;
            color: var(--link-color);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease, text-shadow 0.3s ease;
            font-size: 1em;
            text-shadow: 0 0 8px rgba(52, 152, 219, 0.3);
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .back-to-dashboard, .history-button {
                font-size: 0.9em;
                gap: 4px;
            }
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
            font-size: 1.2em;
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
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .main-wrapper {
                gap: 15px;
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-wrapper {
                gap: 10px;
            }
        }

        .panel {
            background: var(--panel-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 30px var(--shadow-glow);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .panel {
                padding: 15px;
                border-radius: 8px;
            }
        }

        @media (max-width: 480px) {
            .panel {
                padding: 10px;
                border-radius: 6px;
            }
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
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .input-group {
                gap: 10px;
                font-size: 1em;
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .input-group {
                gap: 8px;
                font-size: 0.9em;
            }
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

        @media (max-width: 480px) {
            .input-group input[type="number"] {
                width: 60px;
                padding: 8px;
                font-size: 14px;
            }
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

        .file-upload-wrapper {
            margin: 15px 0;
            text-align: center;
        }
        .file-upload-label {
            display: inline-block;
            background: var(--link-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .file-upload-label:hover {
            background: #5dade2;
            transform: translateY(-2px);
        }
        #fileInput {
            display: none;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .button-group {
                gap: 10px;
                margin-top: 20px;
            }
        }

        @media (max-width: 480px) {
            .button-group {
                gap: 8px;
                margin-top: 15px;
                flex-direction: column;
            }
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
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            button {
                padding: 12px 24px;
                font-size: 1em;
                max-width: 150px;
            }
        }

        @media (max-width: 480px) {
            button {
                padding: 10px 20px;
                font-size: 0.9em;
                max-width: none;
                width: 100%;
            }
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
        .stat-item.charge .count { color: var(--status-charge); text-shadow: 0 0 10px var(--status-charge); }
        .stat-item.live .count { color: var(--status-live); text-shadow: 0 0 10px var(--status-live); }
        .stat-item.dead .count { color: var(--status-dead); text-shadow: 0 0 10px var(--status-dead); }
        .stat-item.yet-to-check .count { color: var(--status-checking); text-shadow: 0 0 10px var(--status-checking); }
        .stat-item.active-checks .count { color: #00e676; text-shadow: 0 0 10px #00e676; }
        .stat-item.active-checks .icon { animation: spin 2s linear infinite; }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

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
            background: var(--status-charge);
        }

        .result-cards-container {
            max-height: 450px;
            overflow-y: auto;
            padding-right: 15px;
            scrollbar-width: thin;
            scrollbar-color: var(--link-color) var(--input-bg);
        }
        .result-cards-container::-webkit-scrollbar {
            width: 8px;
        }
        .result-cards-container::-webkit-scrollbar-track {
            background: var(--input-bg);
            border-radius: 10px;
        }
        .result-cards-container::-webkit-scrollbar-thumb {
            background-color: var(--link-color);
            border-radius: 10px;
            border: 2px solid var(--input-bg);
        }

        .result-card {
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

        .result-card.charged { border-left-color: var(--status-charge); }
        .result-card.approved { border-left-color: var(--status-live); }
        .result-card.declined, .result-card.api_error { border-left-color: var(--status-dead); }
        .result-card.checking { border-left-color: var(--status-checking); }

        .result-card div strong {
            color: var(--placeholder-color);
            min-width: 70px;
            display: inline-block;
            text-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        .result-card div {
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
        .result-card:hover .copy-button {
            opacity: 1;
        }
        .copy-button:hover {
            background: rgba(0, 255, 234, 0.4);
        }
        .copy-button.copied {
            background: var(--status-charge);
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
        <a href="dashboard.php" class="back-to-dashboard"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <div class="header-meta">
            <a href="proxy_manager.php" class="manage-proxies-link">
                <i class="fas fa-network-wired"></i> Proxy Manager
            </a>
            <div class="credit-chip" id="creditChip" data-credits="<?= $availableCredits; ?>">
                <i class="fas fa-coins"></i>
                <span id="creditsDisplay"><?= number_format($availableCredits); ?></span> Credits
            </div>
            <button id="historyButton" class="history-button" title="View past checks">
                <i class="fas fa-history"></i> Check History
            </button>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="panel">
            <h2>Card Checker</h2>
            <form id="checkForm">
                <textarea name="cards_input" id="cardsInput" placeholder="Enter Cards (one per line, format: xxxx|xx|xxxx|xxx)"><?php echo htmlspecialchars($card_input_raw); ?></textarea>
                <div class="file-upload-wrapper">
                    <label for="fileInput" class="file-upload-label">
                        <i class="fas fa-file-import"></i> Import Cards from File
                    </label>
                    <input type="file" id="fileInput" accept=".txt">
                </div>
                <textarea name="sites_input" id="sitesInput" placeholder="Enter Sites (one per line, e.g., https://example.com)"><?php echo htmlspecialchars($site_input_raw); ?></textarea>
                
                <div class="proxy-control">
                    <div class="proxy-control-label">
                        <span><i class="fas fa-network-wired"></i> Proxy Strategy</span>
                        <button type="button" id="openProxyManagerButton" class="manage-proxies-link" style="padding:4px 10px;">
                            <i class="fas fa-external-link"></i> Manage
                        </button>
                    </div>
                    <div class="proxy-control-row">
                        <select id="proxyModeSelect">
                            <option value="auto">Auto rotate saved live proxies</option>
                            <option value="manual">Choose proxies manually</option>
                            <option value="custom">Use a custom one-off proxy</option>
                            <option value="none">Use my IP (no proxy)</option>
                        </select>
                        <button type="button" id="refreshProxyButton" class="proxy-refresh-button">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                    <div id="proxySummary" class="proxy-helper-text">Loading saved proxies…</div>
                    <div id="manualProxyContainer" class="proxy-extra" style="display: none;">
                        <select id="manualProxySelect" multiple size="5"></select>
                        <div class="proxy-helper-text">Select one or more saved proxies. They will rotate in order.</div>
                    </div>
                    <div id="customProxyContainer" class="proxy-extra" style="display: none;">
                        <input type="text" id="customProxyInput" placeholder="ip:port or ip:port:user:pass">
                        <div class="proxy-helper-text">Custom proxies are not saved. They apply only to this run.</div>
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="concurrencyLimit">Concurrent Checks:</label>
                    <input type="number" id="concurrencyLimit" value="3" min="1" max="20">
                </div>

                <div class="button-group">
                    <button type="submit" id="playButton" class="play-button">Start Check</button>
                    <button type="button" id="stopButton" class="stop-button" disabled>Stop Check</button>
                    <button type="button" id="clearButton" class="clear-button">Clear All</button>
                </div>
            </form>
        </div>

        <div class="panel stats-panel">
            <h3>Check Overview</h3>
            <div class="stats-grid">
                <div class="stat-item total"><span><span class="icon fas fa-list"></span> Total Cards</span><span id="totalCount" class="count">0</span></div>
                <div class="stat-item charge"><span><span class="icon fas fa-dollar-sign"></span> Charged</span><span id="chargeCount" class="count">0</span></div>
                <div class="stat-item live"><span><span class="icon fas fa-check-circle"></span> Live</span><span id="liveCount" class="count">0</span></div>
                <div class="stat-item dead"><span><span class="icon fas fa-times-circle"></span> Dead</span><span id="deadCount" class="count">0</span></div>
                <div class="stat-item yet-to-check"><span><span class="icon fas fa-hourglass-half"></span> Pending</span><span id="yetToCheckCount" class="count">0</span></div>
                <div class="stat-item active-checks"><span><span class="icon fas fa-sync fa-spin"></span> Active</span><span id="activeChecksCount" class="count">0</span></div>
            </div>
            
            <!-- Progress bar for concurrent checks -->
            <div style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 0.9em; color: var(--placeholder-color);">Progress</span>
                    <span id="progressPercent" style="font-size: 0.9em; color: var(--text-color);">0%</span>
                </div>
                <div style="width: 100%; height: 8px; background: var(--input-bg); border-radius: 10px; overflow: hidden; border: 1px solid rgba(0,255,234,0.3);">
                    <div id="progressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #00e676, #00bcd4); transition: width 0.3s ease;"></div>
                </div>
                <div style="margin-top: 8px; font-size: 0.85em; color: var(--placeholder-color); display: flex; justify-content: space-between;">
                    <span>Cards/sec: <span id="cardsPerSecond" style="color: var(--text-color);">0</span></span>
                    <span>Elapsed: <span id="elapsedTime" style="color: var(--text-color);">0s</span></span>
                    <span>ETA: <span id="estimatedTime" style="color: var(--text-color);">--</span></span>
                </div>
            </div>
        </div>

        <div class="panel results-panel">
            <h3>Check Results</h3>
            <div class="result-tabs">
                <button class="tab-button active" data-tab="charge">Charged</button>
                <button class="tab-button" data-tab="live">Live</button>
                <button class="tab-button" data-tab="dead">Dead</button>
            </div>
            
            <div id="chargeResultsTabContent" class="tab-content active">
                <div class="result-cards-header">
                    <h4>Charged Cards List</h4>
                    <button class="copy-all-button" data-target="chargeResultsContainer">Copy All Charged</button>
                    <button class="download-button" data-target="chargeResultsContainer" data-filename="charged_cards.txt">Download Charged</button>
                </div>
                <div id="chargeResultsContainer" class="result-cards-container">
                </div>
            </div>
            <div id="liveResultsTabContent" class="tab-content">
                <div class="result-cards-header">
                    <h4>Live Cards List</h4>
                    <button class="copy-all-button" data-target="liveResultsContainer">Copy All Live</button>
                    <button class="download-button" data-target="liveResultsContainer" data-filename="live_cards.txt">Download Live</button>
                </div>
                <div id="liveResultsContainer" class="result-cards-container">
                </div>
            </div>
            <div id="deadResultsTabContent" class="tab-content">
                <div class="result-cards-header">
                    <h4>Dead Cards List</h4>
                    <button class="copy-all-button" data-target="deadResultsContainer">Copy All Dead</button>
                    <button class="download-button" data-target="deadResultsContainer" data-filename="dead_cards.txt">Download Dead</button>
                </div>
                <div id="deadResultsContainer" class="result-cards-container">
                </div>
            </div>
        </div>
    </div>

    <div id="loadingMessage" class="loading-message">Checking cards... Please wait.</div>

    <div id="historyModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Checking History</h3>
            <div id="historyList" class="history-list">
            </div>
        </div>
    </div>

    <div class="footer-attribution">
        Developed by <a href="https://t.me/LEGEND_BL" target="_blank">@LEGEND_BL</a>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        const playButton = document.getElementById('playButton');
        const stopButton = document.getElementById('stopButton');
        const clearButton = document.getElementById('clearButton');
        const cardsInput = document.getElementById('cardsInput');
        const fileInput = document.getElementById('fileInput');
        const sitesInput = document.getElementById('sitesInput');
        const concurrencyLimitInput = document.getElementById('concurrencyLimit');
        const proxyModeSelect = document.getElementById('proxyModeSelect');
        const manualProxyContainer = document.getElementById('manualProxyContainer');
        const manualProxySelect = document.getElementById('manualProxySelect');
        const customProxyContainer = document.getElementById('customProxyContainer');
        const customProxyInput = document.getElementById('customProxyInput');
        const refreshProxyButton = document.getElementById('refreshProxyButton');
        const proxySummary = document.getElementById('proxySummary');
        const openProxyManagerButton = document.getElementById('openProxyManagerButton');
        const creditChip = document.getElementById('creditChip');
        const creditsDisplay = document.getElementById('creditsDisplay');
        const loadingMessage = document.getElementById('loadingMessage');
        const historyButton = document.getElementById('historyButton');
        const historyModal = document.getElementById('historyModal');
        const closeModalButton = historyModal.querySelector('.close-button');
        const historyList = document.getElementById('historyList');
        const totalCount = document.getElementById('totalCount');
        const chargeCount = document.getElementById('chargeCount');
        const liveCount = document.getElementById('liveCount');
        const deadCount = document.getElementById('deadCount');
        const yetToCheckCount = document.getElementById('yetToCheckCount');
        const activeChecksCount = document.getElementById('activeChecksCount');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        const cardsPerSecond = document.getElementById('cardsPerSecond');
        const elapsedTime = document.getElementById('elapsedTime');
        const estimatedTime = document.getElementById('estimatedTime');
        const chargeResultsContainer = document.getElementById('chargeResultsContainer');
        const liveResultsContainer = document.getElementById('liveResultsContainer');
        const deadResultsContainer = document.getElementById('deadResultsContainer');

        let cardQueue = [];
        let originalCardsInput = [];
        let processing = false;
        let activeChecks = 0;
        let stopRequested = false;
        let allCards = [];
        let startTime = 0;
        let completedCards = 0;
        let performanceTimer = null;
        let savedProxies = [];
        let manualSelectedIds = [];
        let proxyRotationIndex = 0;

        const HISTORY_KEY = 'cardCheckerHistory';
        function saveHistory(cards, sites, results) {
            let history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
            const timestamp = new Date().toLocaleString();
            history.unshift({
                timestamp: timestamp,
                cards: cards,
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
                historyList.innerHTML = '<p style="text-align: center; color: var(--placeholder-color);">No check history found. Start a check to create your first entry.</p>';
                return;
            }

            history.forEach((item, index) => {
                const historyItemDiv = document.createElement('div');
                historyItemDiv.className = 'history-item';
                const cardsPreview = item.cards.slice(0, 5).join('\n') + (item.cards.length > 5 ? '\n...(' + (item.cards.length - 5) + ' more cards)' : '');
                const sitesPreview = item.sites.slice(0, 2).join('\n') + (item.sites.length > 2 ? '\n...(' + (item.sites.length - 2) + ' more sites)' : '');

                historyItemDiv.innerHTML = `
                    <div class="timestamp"><strong>Check Initiated:</strong> ${item.timestamp}</div>
                    <div><strong>Input Cards:</strong><pre>${cardsPreview}</pre></div>
                    <div><strong>Input Sites:</strong><pre>${sitesPreview}</pre></div>
                    <div class="history-item-buttons">
                        <button class="load-button" data-index="${index}">Load Inputs</button>
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
                        cardsInput.value = selectedItem.cards.join('\n');
                        sitesInput.value = selectedItem.sites.join('\n');
                        alert('Input cards and sites loaded from history!');
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

        function updateCreditsDisplay(newCredits) {
            const numericCredits = Number(newCredits);
            if (Number.isNaN(numericCredits)) {
                return;
            }
            creditsDisplay.textContent = numericCredits.toLocaleString();
            creditChip.dataset.credits = numericCredits;

            if (numericCredits <= 0) {
                creditChip.style.borderColor = 'rgba(239, 68, 68, 0.45)';
                creditChip.style.color = '#f87171';
            } else if (numericCredits < 10) {
                creditChip.style.borderColor = 'rgba(250, 204, 21, 0.45)';
                creditChip.style.color = '#facc15';
            } else {
                creditChip.style.borderColor = 'rgba(0, 212, 255, 0.3)';
                creditChip.style.color = '#00d4ff';
            }
        }

        function handleProxyModeChange() {
            const mode = proxyModeSelect.value;
            manualProxyContainer.style.display = mode === 'manual' ? 'block' : 'none';
            customProxyContainer.style.display = mode === 'custom' ? 'block' : 'none';

            if (mode !== 'manual') {
                manualSelectedIds = [];
            } else {
                manualSelectedIds = Array.from(manualProxySelect.selectedOptions).map(option => option.value);
            }

            if (mode === 'custom') {
                customProxyInput.focus();
            }
        }

        function populateManualProxySelect() {
            if (!manualProxySelect) {
                return;
            }

            manualProxySelect.innerHTML = '';

            if (!savedProxies.length) {
                const option = document.createElement('option');
                option.textContent = 'No saved proxies available';
                option.disabled = true;
                manualProxySelect.appendChild(option);
                manualProxySelect.disabled = true;
                return;
            }

            manualProxySelect.disabled = false;
            savedProxies.forEach(proxy => {
                const option = document.createElement('option');
                option.value = proxy.id;
                const status = (proxy.status || 'unknown').toUpperCase();
                const latency = proxy.latency_ms != null ? `${Math.round(proxy.latency_ms)} ms` : '—';
                const label = proxy.label && proxy.label.trim().length ? proxy.label : proxy.proxy;
                option.textContent = `${label} (${status} · ${latency})`;
                manualProxySelect.appendChild(option);
            });
        }

        function summarizeProxies(successColor = false) {
            if (!proxySummary) {
                return;
            }

            if (!savedProxies.length) {
                proxySummary.textContent = 'No saved proxies. Checks will use your IP.';
                proxySummary.style.color = '#facc15';
                return;
            }

            const live = savedProxies.filter(proxy => (proxy.status || '').toLowerCase() === 'live').length;
            const dead = savedProxies.filter(proxy => (proxy.status || '').toLowerCase() === 'dead').length;
            const pending = savedProxies.length - live - dead;

            proxySummary.textContent = `${savedProxies.length} saved · ${live} live · ${dead} dead · ${pending} pending`;
            proxySummary.style.color = successColor ? '#00f0ff' : 'var(--placeholder-color)';
        }

        async function fetchProxies(showFeedback = false) {
            if (proxySummary) {
                proxySummary.textContent = 'Refreshing proxies…';
                proxySummary.style.color = 'var(--placeholder-color)';
            }

            try {
                const response = await fetch('api/proxies.php', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.error) {
                    throw new Error(data.message || 'Failed to load proxies');
                }
                savedProxies = Array.isArray(data.proxies) ? data.proxies : [];
                proxyRotationIndex = 0;
                populateManualProxySelect();
                summarizeProxies(showFeedback);

                if (proxyModeSelect.value === 'manual' && manualProxySelect && manualProxySelect.options.length) {
                    manualSelectedIds = Array.from(manualProxySelect.selectedOptions).map(option => option.value);
                    if (!manualSelectedIds.length) {
                        manualSelectedIds = savedProxies.map(proxy => proxy.id);
                    }
                }

                if (showFeedback && proxySummary) {
                    proxySummary.textContent = `Loaded ${savedProxies.length} proxies`;
                    proxySummary.style.color = '#00f0ff';
                }
            } catch (error) {
                savedProxies = [];
                populateManualProxySelect();
                if (proxySummary) {
                    proxySummary.textContent = `Unable to load proxies (${error.message || 'network error'})`;
                    proxySummary.style.color = '#f87171';
                }
            }
        }

        function getProxyForRequest() {
            const mode = proxyModeSelect.value;

            if (mode === 'none') {
                return { query: '&noproxy=1', id: null, type: 'none' };
            }

            if (mode === 'custom') {
                const customValue = customProxyInput.value.trim();
                if (!customValue) {
                    return { query: '', id: null, type: 'custom', error: 'Custom proxy required' };
                }
                return { query: `&proxy=${encodeURIComponent(customValue)}`, id: null, proxy: customValue, type: 'custom' };
            }

            if (!savedProxies.length) {
                return { query: '&noproxy=1', id: null, type: mode, fallbackToNone: true };
            }

            if (mode === 'manual') {
                const selected = manualSelectedIds.length
                    ? manualSelectedIds
                    : Array.from(manualProxySelect.options).map(option => option.value).filter(Boolean);

                if (!selected.length) {
                    return { query: '&noproxy=1', id: null, type: 'manual', fallbackToNone: true };
                }

                if (proxyRotationIndex >= selected.length) {
                    proxyRotationIndex = 0;
                }

                const proxyId = selected[proxyRotationIndex];
                proxyRotationIndex = (proxyRotationIndex + 1) % selected.length;
                const chosenProxy = savedProxies.find(proxy => proxy.id === proxyId);

                if (!chosenProxy) {
                    return { query: '&noproxy=1', id: null, type: 'manual', fallbackToNone: true };
                }

                return {
                    query: `&proxy=${encodeURIComponent(chosenProxy.proxy)}`,
                    id: chosenProxy.id,
                    proxy: chosenProxy.proxy,
                    type: 'manual'
                };
            }

            const livePool = savedProxies.filter(proxy => (proxy.status || '').toLowerCase() === 'live');
            const pool = livePool.length ? livePool : savedProxies;

            if (proxyRotationIndex >= pool.length) {
                proxyRotationIndex = 0;
            }

            const selectedProxy = pool[proxyRotationIndex];
            proxyRotationIndex = (proxyRotationIndex + 1) % pool.length;

            return {
                query: `&proxy=${encodeURIComponent(selectedProxy.proxy)}`,
                id: selectedProxy.id,
                proxy: selectedProxy.proxy,
                type: 'auto'
            };
        }

        async function recordProxyUsage(proxyId, success, latencyMs) {
            if (!proxyId) {
                return;
            }
            try {
                await fetch('api/proxies.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'record_usage',
                        proxy_id: proxyId,
                        success: success ? 1 : 0,
                        latency_ms: latencyMs != null ? Math.round(latencyMs) : undefined
                    })
                });
            } catch (error) {
                console.warn('Failed to record proxy usage', error);
            }
        }

        function updateCounts() {
            totalCount.textContent = allCards.length;
            yetToCheckCount.textContent = cardQueue.length + activeChecks;
            activeChecksCount.textContent = activeChecks;
            
            // Update progress bar
            if (allCards.length > 0) {
                const progress = ((completedCards / allCards.length) * 100).toFixed(1);
                progressBar.style.width = progress + '%';
                progressPercent.textContent = progress + '%';
            }
        }
        
        function updatePerformanceMetrics() {
            if (!processing || startTime === 0) return;
            
            const elapsed = (Date.now() - startTime) / 1000; // in seconds
            elapsedTime.textContent = elapsed.toFixed(1) + 's';
            
            if (completedCards > 0) {
                const rate = (completedCards / elapsed).toFixed(2);
                cardsPerSecond.textContent = rate;
                
                const remaining = allCards.length - completedCards;
                if (rate > 0) {
                    const eta = (remaining / rate).toFixed(0);
                    estimatedTime.textContent = eta + 's';
                } else {
                    estimatedTime.textContent = '--';
                }
            }
        }
        
        function startPerformanceTracking() {
            startTime = Date.now();
            completedCards = 0;
            performanceTimer = setInterval(updatePerformanceMetrics, 500);
        }
        
        function stopPerformanceTracking() {
            if (performanceTimer) {
                clearInterval(performanceTimer);
                performanceTimer = null;
            }
        }

        function createResultCardElement(data, statusType) {
            const cardDiv = document.createElement('div');
            cardDiv.className = `result-card ${statusType.toLowerCase()}`;
            cardDiv.innerHTML = `
                <div><strong>Card:</strong> ${data.card}</div>
                <div><strong>Site:</strong> ${data.site}</div>
                <div><strong>Gateway:</strong> ${data.gateway ? data.gateway : 'N/A'}</div>
                <div><strong>Status:</strong> ${data.status}</div>
                <div><strong>Price:</strong> $${data.price ? data.price : '0.00'}</div>
                <div><strong>Proxy Status:</strong> ${data.proxy_status ? data.proxy_status : 'N/A'}</div>
                <div><strong>Proxy IP:</strong> ${data.proxy_ip ? data.proxy_ip : 'N/A'}</div>
                <div><strong>Time:</strong> ${data.time ? data.time : 'N/A'}</div>
                <button class="copy-button" 
                    data-card="${encodeURIComponent(data.card)}"
                    data-site="${encodeURIComponent(data.site)}"
                    data-gateway="${encodeURIComponent(data.gateway || 'N/A')}"
                    data-status="${encodeURIComponent(data.status)}"
                    data-price="${encodeURIComponent(data.price || '0.00')}"
                    data-proxy-status="${encodeURIComponent(data.proxy_status || 'N/A')}"
                    data-proxy-ip="${encodeURIComponent(data.proxy_ip || 'N/A')}"
                    data-time="${encodeURIComponent(data.time || 'N/A')}">
                    <i class="far fa-copy"></i>
                </button>
            `;
            const copyBtn = cardDiv.querySelector('.copy-button');
            copyBtn.addEventListener('click', () => {
                copyCardDetails(copyBtn);
            });
            return cardDiv;
        }

        async function copyCardDetails(buttonElement) {
            const details = {
                card: decodeURIComponent(buttonElement.dataset.card),
                site: decodeURIComponent(buttonElement.dataset.site),
                gateway: decodeURIComponent(buttonElement.dataset.gateway),
                status: decodeURIComponent(buttonElement.dataset.status),
                price: decodeURIComponent(buttonElement.dataset.price),
                proxy_status: decodeURIComponent(buttonElement.dataset.proxyStatus),
                proxy_ip: decodeURIComponent(buttonElement.dataset.proxyIp),
                time: decodeURIComponent(buttonElement.dataset.time)
            };
            const textToCopy = `Card: ${details.card}\nSite: ${details.site}\nGateway: ${details.gateway}\nStatus: ${details.status}\nPrice: $${details.price}\nProxy Status: ${details.proxy_status}\nProxy IP: ${details.proxy_ip}\nTime: ${details.time}`;
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

        function copyAllCards(targetContainerId, buttonElement) {
            const container = document.getElementById(targetContainerId);
            const cards = Array.from(container.querySelectorAll('.result-card'));
            if (cards.length === 0) {
                alert('No cards to copy!');
                return;
            }

            let allTextToCopy = [];
            cards.forEach(cardDiv => {
                const copyBtn = cardDiv.querySelector('.copy-button');
                const details = {
                    card: decodeURIComponent(copyBtn.dataset.card),
                    site: decodeURIComponent(copyBtn.dataset.site),
                    gateway: decodeURIComponent(copyBtn.dataset.gateway),
                    status: decodeURIComponent(copyBtn.dataset.status),
                    price: decodeURIComponent(copyBtn.dataset.price),
                    proxy_status: decodeURIComponent(copyBtn.dataset.proxyStatus || 'N/A'),
                    proxy_ip: decodeURIComponent(copyBtn.dataset.proxyIp || 'N/A'),
                    time: decodeURIComponent(copyBtn.dataset.time)
                };
                allTextToCopy.push(`Card: ${details.card}\nSite: ${details.site}\nGateway: ${details.gateway}\nStatus: ${details.status}\nPrice: $${details.price}\nProxy Status: ${details.proxy_status}\nProxy IP: ${details.proxy_ip}\nTime: ${details.time}`);
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
            const cards = Array.from(container.querySelectorAll('.result-card'));
            if (cards.length === 0) {
                alert('No cards to download!');
                return;
            }

            let allTextToDownload = [];
            cards.forEach(cardDiv => {
                const copyBtn = cardDiv.querySelector('.copy-button');
                const details = {
                    card: decodeURIComponent(copyBtn.dataset.card),
                    site: decodeURIComponent(copyBtn.dataset.site),
                    gateway: decodeURIComponent(copyBtn.dataset.gateway),
                    status: decodeURIComponent(copyBtn.dataset.status),
                    price: decodeURIComponent(copyBtn.dataset.price),
                    proxy_status: decodeURIComponent(copyBtn.dataset.proxyStatus || 'N/A'),
                    proxy_ip: decodeURIComponent(copyBtn.dataset.proxyIp || 'N/A'),
                    time: decodeURIComponent(copyBtn.dataset.time)
                };
                allTextToDownload.push(`Card: ${details.card}\nSite: ${details.site}\nGateway: ${details.gateway}\nStatus: ${details.status}\nPrice: $${details.price}\nProxy Status: ${details.proxy_status}\nProxy IP: ${details.proxy_ip}\nTime: ${details.time}`);
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

        function removeCardFromInput(cardToRemove) {
            let currentCards = cardsInput.value.split('\n').map(card => card.trim());
            const filteredCards = [];
            let removed = false;
            for (const card of currentCards) {
                if (card === cardToRemove && !removed) {
                    removed = true;
                } else {
                    filteredCards.push(card);
                }
            }
            cardsInput.value = filteredCards.join('\n');
        }

        function clearResultsAndCounts() {
            chargeResultsContainer.innerHTML = '';
            liveResultsContainer.innerHTML = '';
            deadResultsContainer.innerHTML = '';
            chargeCount.textContent = '0';
            liveCount.textContent = '0';
            deadCount.textContent = '0';
            totalCount.textContent = '0';
            yetToCheckCount.textContent = '0';
            activeChecksCount.textContent = '0';
            progressBar.style.width = '0%';
            progressPercent.textContent = '0%';
            cardsPerSecond.textContent = '0';
            elapsedTime.textContent = '0s';
            estimatedTime.textContent = '--';
            completedCards = 0;
            startTime = 0;
            stopPerformanceTracking();
        }

        // Enhanced concurrent processing with batching
        async function processCard(card) {
            if (stopRequested) {
                activeChecks--;
                updateCounts();
                return;
            }

            activeChecks++;
            updateCounts();
            removeCardFromInput(card);

            const sites = sitesInput.value.split('\n').map(site => site.trim()).filter(site => site.length > 0);
            const currentSite = sites[Math.floor(Math.random() * sites.length)];
            const proxySelection = getProxyForRequest();
            let extraParam = '';
            let proxyIdUsed = proxySelection.id || null;

            if (proxySelection.error) {
                activeChecks--;
                updateCounts();
                alert(proxySelection.error);
                return;
            }

            if (proxySelection.fallbackToNone && proxySummary) {
                proxySummary.textContent = 'No valid proxies selected. Using your IP for checks.';
                proxySummary.style.color = '#facc15';
            }

            if (proxySelection.query) {
                extraParam = proxySelection.query;
            } else {
                extraParam = '&noproxy=1';
            }

            let latencyMs = null;
            const requestStart = performance.now();

            try {
                const response = await fetch(`check_card_ajax.php?cc=${encodeURIComponent(card)}&site=${encodeURIComponent(currentSite)}${extraParam}`, {
                    credentials: 'same-origin'
                });
                latencyMs = performance.now() - requestStart;

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const responseText = await response.text();
                console.log('Raw response:', responseText);
                const data = JSON.parse(responseText);

                if (typeof data.remaining_credits !== 'undefined') {
                    updateCreditsDisplay(data.remaining_credits);
                }

                let targetContainer;
                let newCountElement;
                const uiStatusType = (data.ui_status_type || 'DECLINED').toUpperCase();

                if (uiStatusType === 'CHARGED') {
                    targetContainer = chargeResultsContainer;
                    newCountElement = chargeCount;
                    currentSessionResults.charged.push(data);
                } else if (uiStatusType === 'APPROVED') {
                    targetContainer = liveResultsContainer;
                    newCountElement = liveCount;
                    currentSessionResults.approved.push(data);
                } else {
                    targetContainer = deadResultsContainer;
                    newCountElement = deadCount;
                    currentSessionResults.declined.push(data);
                }

                const finalCardElement = createResultCardElement(data, uiStatusType);
                targetContainer.prepend(finalCardElement);
                newCountElement.textContent = parseInt(newCountElement.textContent) + 1;

                if (proxyIdUsed) {
                    const successStatus = uiStatusType === 'CHARGED' || uiStatusType === 'APPROVED';
                    recordProxyUsage(proxyIdUsed, successStatus, latencyMs);
                }
            } catch (error) {
                latencyMs = latencyMs ?? (performance.now() - requestStart);
                console.error('Error checking card:', card, error);

                const errorData = {
                    card,
                    site: currentSite,
                    gateway: 'N/A',
                    status: `JS_ERROR: ${error.message || 'Network issue'}`,
                    price: '0.00',
                    time: 'N/A',
                    proxy_status: 'Error',
                    proxy_ip: 'N/A'
                };
                const errorCardElement = createResultCardElement(errorData, 'declined');
                deadResultsContainer.prepend(errorCardElement);
                deadCount.textContent = parseInt(deadCount.textContent) + 1;
                currentSessionResults.declined.push(errorData);

                if (proxyIdUsed) {
                    recordProxyUsage(proxyIdUsed, false, latencyMs);
                }
            } finally {
                completedCards++;
                activeChecks--;
                updateCounts();

                if (!stopRequested && cardQueue.length > 0) {
                    const nextCard = cardQueue.shift();
                    processCard(nextCard);
                } else if (activeChecks === 0) {
                    loadingMessage.style.display = 'none';
                    playButton.disabled = false;
                    stopButton.disabled = true;
                    processing = false;
                    stopRequested = false;
                    saveHistory(originalCardsInput, sitesInput.value.split('\n').map(s => s.trim()).filter(s => s.length > 0), currentSessionResults);
                    currentSessionResults = { charged: [], approved: [], declined: [] };
                }
            }
        }

        // New batch processing function for true concurrent checks
        async function processBatch(cards, batchSize = 10) {
            const chunks = [];
            for (let i = 0; i < cards.length; i += batchSize) {
                chunks.push(cards.slice(i, i + batchSize));
            }

            for (const chunk of chunks) {
                if (stopRequested) {
                    break;
                }
                await Promise.all(chunk.map(card => processCard(card)));
            }
        }

        let currentSessionResults = { charged: [], approved: [], declined: [] };

        document.getElementById('checkForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            if (processing) return;

            const cardsRaw = cardsInput.value;
            const sitesRaw = sitesInput.value;
            const concurrencyLimit = parseInt(concurrencyLimitInput.value, 10);

            const cards = cardsRaw.split('\n').map(card => card.trim()).filter(card => card.length > 0);
            const sites = sitesRaw.split('\n').map(site => site.trim()).filter(site => site.length > 0);

            if (cards.length === 0) {
                alert('Please enter at least one card.');
                return;
            }
            if (sites.length === 0) {
                alert('Please enter at least one site URL.');
                return;
            }

            handleProxyModeChange();

            if ((proxyModeSelect.value === 'auto' || proxyModeSelect.value === 'manual') && savedProxies.length === 0) {
                await fetchProxies();
            }

            if (proxyModeSelect.value === 'custom' && customProxyInput.value.trim() === '') {
                alert('Enter a custom proxy or choose a different proxy strategy.');
                return;
            }

            if (proxyModeSelect.value === 'manual') {
                if (!savedProxies.length) {
                    alert('No saved proxies available. Visit Proxy Manager to add some or choose another strategy.');
                    return;
                }
                manualSelectedIds = Array.from(manualProxySelect.selectedOptions).map(option => option.value);
                if (!manualSelectedIds.length) {
                    manualSelectedIds = savedProxies.map(proxy => proxy.id);
                }
            }

            proxyRotationIndex = 0;

            clearResultsAndCounts();

            originalCardsInput = [...cards];
            cardQueue = [...cards];
            stopRequested = false;
            processing = true;
            activeChecks = 0;
            allCards = [...cards];

            loadingMessage.style.display = 'block';
            loadingMessage.textContent = `Processing ${cards.length} cards with ${concurrencyLimit} concurrent checks...`;
            playButton.disabled = true;
            stopButton.disabled = false;
            updateCounts();
            startPerformanceTracking();

            // Use batch processing for better concurrency
            try {
                await processBatch(cards, concurrencyLimit);
            } catch (error) {
                console.error('Batch processing error:', error);
            }

            // Finalize
            stopPerformanceTracking();
            loadingMessage.style.display = 'none';
            playButton.disabled = false;
            stopButton.disabled = true;
            processing = false;
            stopRequested = false;
            saveHistory(originalCardsInput, sitesInput.value.split('\n').map(s => s.trim()).filter(s => s.length > 0), currentSessionResults);
            
            // Show completion message
            alert(`Check completed!\nCharged: ${currentSessionResults.charged.length}\nApproved: ${currentSessionResults.approved.length}\nDeclined: ${currentSessionResults.declined.length}`);
            
            currentSessionResults = { charged: [], approved: [], declined: [] };
        });
        stopButton.addEventListener('click', () => {
            stopRequested = true;
            loadingMessage.textContent = 'Stopping checks...';
            stopButton.disabled = true;
            playButton.disabled = false;
            stopPerformanceTracking();
        });
        clearButton.addEventListener('click', () => {
            cardsInput.value = '';
            sitesInput.value = '';
            clearResultsAndCounts();
            loadingMessage.style.display = 'none';
            playButton.disabled = false;
            stopButton.disabled = true;
            processing = false;
            stopRequested = false;
            cardQueue = [];
            allCards = [];
            currentSessionResults = { charged: [], approved: [], declined: [] };
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
                const targetTabId = this.dataset.tab + 'ResultsTabContent';
                document.getElementById(targetTabId).classList.add('active');
            });
        });
        document.querySelectorAll('.copy-all-button').forEach(button => {
            button.dataset.originalText = button.textContent;
            button.addEventListener('click', function() {
                const targetContainerId = this.dataset.target;
                copyAllCards(targetContainerId, this);
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

        refreshProxyButton.addEventListener('click', () => fetchProxies(true));
        proxyModeSelect.addEventListener('change', handleProxyModeChange);
        manualProxySelect.addEventListener('change', () => {
            manualSelectedIds = Array.from(manualProxySelect.selectedOptions).map(option => option.value);
        });
        openProxyManagerButton.addEventListener('click', () => {
            window.open('proxy_manager.php', '_blank');
        });

        updateCreditsDisplay(creditChip.dataset.credits || 0);
        handleProxyModeChange();
        fetchProxies();

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

        fileInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    cardsInput.value = e.target.result;
                    alert('Cards imported successfully!');
                };
                reader.onerror = (e) => {
                    alert('Error reading file: ' + e.target.error.name);
                };
                reader.readAsText(file);
            }
        });
    </script>
</body>
</html>
