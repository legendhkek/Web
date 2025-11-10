<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'cc_generator.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();
$user = $db->getUserByTelegramId($userId);
$db->updatePresence($userId);

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bin'])) {
    $bin = trim($_POST['bin']);
    $count = isset($_POST['count']) ? (int)$_POST['count'] : 1;
    
    if ($count < 1 || $count > 100) {
        $error = 'Count must be between 1 and 100';
    } else {
        try {
            $result = CCGenerator::generateFullCard($bin, $count);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CC Generator - LEGEND CHECKER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        h1 { margin-bottom: 2rem; text-align: center; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; }
        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-family: inherit;
        }
        button {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .result { margin-top: 2rem; padding: 1rem; border-radius: 8px; background: rgba(0, 230, 118, 0.1); border: 1px solid rgba(0, 230, 118, 0.3); }
        .error { background: rgba(255, 107, 107, 0.1); border: 1px solid rgba(255, 107, 107, 0.3); }
        .back-btn { color: #00d4ff; text-decoration: none; margin-bottom: 1rem; display: inline-block; }
        .free-badge { display: inline-block; background: rgba(0, 230, 118, 0.2); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85rem; margin-left: 0.5rem; }
        .card-item { padding: 0.75rem; margin: 0.5rem 0; background: rgba(255, 255, 255, 0.05); border-radius: 8px; font-family: monospace; }
        .copy-btn { margin-top: 0.5rem; padding: 0.5rem 1rem; background: rgba(0, 212, 255, 0.2); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: 6px; color: #00d4ff; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <a href="tools.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Tools</a>
        <div class="card">
            <h1><i class="fas fa-magic"></i> CC Generator <span class="free-badge">FREE</span></h1>
            
            <form method="POST">
                <div class="form-group">
                    <label>BIN (Bank Identification Number - 6-8 digits)</label>
                    <input type="text" name="bin" required placeholder="411111" pattern="[0-9]{6,8}">
                </div>
                <div class="form-group">
                    <label>Count (1-100)</label>
                    <input type="number" name="count" value="1" min="1" max="100" required>
                </div>
                <button type="submit">Generate Cards</button>
            </form>
            
            <?php if ($error): ?>
                <div class="result error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <div class="result">
                    <h3>Generated Cards (<?php echo count($result); ?>):</h3>
                    <?php foreach ($result as $card_data): ?>
                        <div class="card-item">
                            <div><strong>Card:</strong> <?php echo htmlspecialchars($card_data['full']); ?></div>
                            <button class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($card_data['full']); ?>')">Copy</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
