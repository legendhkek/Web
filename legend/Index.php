<?php
// Redirect to login page for authentication
header('Location: login.php');
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEGEND CHECKER - Security Tools</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #000000;
            --container-bg: #1a2b49;
            --text-color: #00ffea;
            --button-primary: #00e676;
            --button-hover: #69f0ae;
            --button-secondary: #e67e22;
            --button-secondary-hover: #f39c12;
            --shadow-glow: rgba(0, 255, 234, 0.5);
            --font-mono: 'Share Tech Mono', monospace;
            --font-heading: 'Orbitron', sans-serif;
            --border-glow: #00bcd4;
        }

        body {
            font-family: var(--font-mono);
            background: var(--bg-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(to right, rgba(0,255,234,0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0,255,234,0.05) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            background: var(--container-bg);
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            width: 400px;
            max-width: 90%;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 30px var(--shadow-glow);
            border: 1px solid var(--border-glow);
        }

        h2 {
            font-family: var(--font-heading);
            color: var(--text-color);
            margin-bottom: 35px;
            font-weight: 700;
            font-size: 2.8em;
            letter-spacing: 2px;
            text-shadow: 0 0 15px var(--shadow-glow);
            text-transform: uppercase;
        }

        .option-button {
            display: block;
            width: 100%;
            padding: 18px 25px;
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            font-size: 1.3em;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            color: var(--bg-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .option-button:last-child {
            margin-bottom: 0;
        }

        .option-button.primary {
            background: var(--button-primary);
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.5);
        }
        .option-button.primary:hover {
            background: var(--button-hover);
            transform: translateY(-3px);
            box-shadow: 0 0 30px var(--button-hover);
        }

        .option-button.secondary {
            background: var(--button-secondary);
            color: white;
            box-shadow: 0 0 20px rgba(230, 126, 34, 0.5);
        }
        .option-button.secondary:hover {
            background: var(--button-secondary-hover);
            transform: translateY(-3px);
            box-shadow: 0 0 30px var(--button-secondary-hover);
        }

        .footer-attribution {
            margin-top: 40px;
            font-size: 0.9em;
            color: rgba(0, 255, 234, 0.6);
            position: relative;
            z-index: 1;
            text-shadow: 0 0 5px rgba(0, 255, 234, 0.2);
        }
        .footer-attribution a {
            color: var(--text-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }
        .footer-attribution a:hover {
            color: var(--button-primary);
            text-shadow: 0 0 10px var(--button-primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>LEGEND CHECKER Web</h2>
        <a href="card_checker.php" class="option-button primary">Card Checker</a>
        <a href="site_checker.php" class="option-button secondary">Site Checker</a>
    </div>

    <div class="footer-attribution">
        Made by <a href="https://t.me/LEGEND_BL" target="_blank">@LEGEND_BL</a>
    </div>
</body>
</html>
