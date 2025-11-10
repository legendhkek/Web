<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'stripe_auth_checker.php';

$nonce = setSecurityHeaders();
$userId = TelegramAuth::requireAuth();
$db = Database::getInstance();
$user = $db->getUserByTelegramId($userId);
$db->updatePresence($userId);

// Check credits
$credit_cost = 1;
$has_credits = ($user['credits'] ?? 0) >= $credit_cost || in_array($userId, AppConfig::OWNER_IDS);

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain']) && isset($_POST['cc_string'])) {
    if (!$has_credits) {
        $error = 'Insufficient credits. You need at least ' . $credit_cost . ' credit.';
    } else {
        $domain = trim($_POST['domain']);
        $cc_string = trim($_POST['cc_string']);
        $proxy = isset($_POST['proxy']) ? trim($_POST['proxy']) : null;
        
        try {
            $result = stripeAuth($domain, $cc_string, $proxy);
            
            // Deduct credit
            if ($result['success'] || isset($result['pm_id'])) {
                $db->deductCredits($userId, $credit_cost);
                $user = $db->getUserByTelegramId($userId); // Refresh user data
            }
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
    <title>Stripe Auth Checker - LEGEND CHECKER</title>
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
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .result { margin-top: 2rem; padding: 1rem; border-radius: 8px; }
        .success { background: rgba(0, 230, 118, 0.1); border: 1px solid rgba(0, 230, 118, 0.3); }
        .error { background: rgba(255, 107, 107, 0.1); border: 1px solid rgba(255, 107, 107, 0.3); }
        .back-btn { color: #00d4ff; text-decoration: none; margin-bottom: 1rem; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <a href="tools.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Tools</a>
        <div class="card">
            <h1><i class="fas fa-key"></i> Stripe Auth Checker</h1>
            <p style="margin-bottom: 2rem; opacity: 0.7;">Cost: <?php echo $credit_cost; ?> Credit per check</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Domain (e.g., example.com)</label>
                    <input type="text" name="domain" required placeholder="example.com">
                </div>
                <div class="form-group">
                    <label>Card String (format: cc|mm|yyyy|cvv)</label>
                    <input type="text" name="cc_string" required placeholder="4111111111111111|12|2025|123">
                </div>
                <div class="form-group">
                    <label>Proxy (Optional - format: ip:port:user:pass)</label>
                    <input type="text" name="proxy" placeholder="Leave empty to use your IP">
                </div>
                <button type="submit" <?php echo !$has_credits ? 'disabled' : ''; ?>>
                    Check Card
                </button>
            </form>
            
            <?php if ($error): ?>
                <div class="result error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <h3>Result:</h3>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($result['status']); ?></p>
                    <p><strong>Message:</strong> <?php echo htmlspecialchars($result['message']); ?></p>
                    <?php if (isset($result['account_email'])): ?>
                        <p><strong>Account Email:</strong> <?php echo htmlspecialchars($result['account_email']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($result['pm_id'])): ?>
                        <p><strong>Payment Method ID:</strong> <?php echo htmlspecialchars($result['pm_id']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
