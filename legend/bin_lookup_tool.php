<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'bin_lookup.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();
$user = $db->getUserByTelegramId($userId);
$db->updatePresence($userId);

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cc_string'])) {
    $cc_string = trim($_POST['cc_string']);
    try {
        $result = BinLookup::getCardInfoFromCC($cc_string);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIN Lookup - LEGEND CHECKER</title>
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
        input, textarea {
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
    </style>
</head>
<body>
    <div class="container">
        <a href="tools.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Tools</a>
        <div class="card">
            <h1><i class="fas fa-search"></i> BIN Lookup <span class="free-badge">FREE</span></h1>
            
            <form method="POST">
                <div class="form-group">
                    <label>Card String or BIN (format: cc|mm|yyyy|cvv or just BIN)</label>
                    <input type="text" name="cc_string" required placeholder="4111111111111111|12|2025|123 or 411111">
                </div>
                <button type="submit">Lookup BIN</button>
            </form>
            
            <?php if ($error): ?>
                <div class="result error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <div class="result">
                    <h3>BIN Information:</h3>
                    <p><strong>Card Type:</strong> <?php echo htmlspecialchars($result['type'] ?? 'Unknown'); ?></p>
                    <p><strong>Bank:</strong> <?php echo htmlspecialchars($result['bank'] ?? 'Unknown'); ?></p>
                    <p><strong>Country:</strong> <?php echo htmlspecialchars($result['country'] ?? 'Unknown'); ?></p>
                    <?php if ($result['country_code']): ?>
                        <p><strong>Country Code:</strong> <?php echo htmlspecialchars($result['country_code']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
