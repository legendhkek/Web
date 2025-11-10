<?php
// Fix Session Variables for Admin Access
// This script will fix session issues and ensure proper admin access

require_once '../config.php';
require_once '../database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

try {
    // Check if user is logged in
    if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
        $error = 'You must be logged in to use this script.';
    } else {
        $telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
        
        // Get user data
        $db = Database::getInstance();
        $user = $db->getUserByTelegramId($telegram_id);
        
        if (!$user) {
            $error = 'User not found in database.';
        } else {
            // Check admin/owner status
            $admin_ids = AppConfig::ADMIN_IDS;
            $owner_ids = AppConfig::OWNER_IDS;
            
            $is_admin = in_array($telegram_id, $admin_ids);
            $is_owner = in_array($telegram_id, $owner_ids);
            
            // Also check database role
            if (!$is_owner && !empty($user['role']) && $user['role'] === 'owner') {
                $is_owner = true;
            }
            if (!$is_admin && !$is_owner && !empty($user['role']) && $user['role'] === 'admin') {
                $is_admin = true;
            }
            
            if ($is_admin || $is_owner) {
                // Fix session variables
                $_SESSION['telegram_id'] = $telegram_id;
                $_SESSION['is_admin'] = true;
                $_SESSION['is_owner'] = $is_owner;
                $_SESSION['user_role'] = $is_owner ? 'owner' : 'admin';
                
                // Ensure other session variables are set
                if (empty($_SESSION['username']) && isset($user['username'])) {
                    $_SESSION['username'] = $user['username'];
                }
                if (empty($_SESSION['display_name']) && isset($user['display_name'])) {
                    $_SESSION['display_name'] = $user['display_name'];
                }
                if (empty($_SESSION['credits']) && isset($user['credits'])) {
                    $_SESSION['credits'] = $user['credits'];
                }
                
                $message = "✅ Session fixed successfully! Your Telegram ID: {$telegram_id}";
                $message .= "<br><strong>Role:</strong> " . ($is_owner ? 'Owner' : 'Admin');
                $message .= "<br><strong>Status:</strong> All session variables updated";
                
            } else {
                $error = "❌ You don't have admin/owner privileges. Your Telegram ID: {$telegram_id}";
            }
        }
    }
} catch (Exception $e) {
    $error = "❌ Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Session - LEGEND CHECKER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fix-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .status-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .status-error {
            background: linear-gradient(45deg, #dc3545, #fd7e14);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="fix-card p-5 text-center">
            <?php if ($message): ?>
                <!-- Success -->
                <div class="status-icon status-success">
                    <i class="bi bi-check-circle text-white fs-1"></i>
                </div>
                <h2 class="text-success mb-3">Session Fixed!</h2>
                <div class="text-muted mb-4"><?php echo $message; ?></div>
                
                <div class="alert alert-success">
                    <i class="bi bi-info-circle"></i>
                    <strong>Next Steps:</strong> Try accessing the admin panel now.
                </div>
                
                <div class="d-grid gap-2">
                    <a href="admin_access.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-shield-check"></i> Access Admin Panel
                    </a>
                    <a href="analytics.php" class="btn btn-success">
                        <i class="bi bi-speedometer2"></i> Go to Analytics
                    </a>
                    <a href="../dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> Back to Dashboard
                    </a>
                </div>
                
            <?php elseif ($error): ?>
                <!-- Error -->
                <div class="status-icon status-error">
                    <i class="bi bi-x-circle text-white fs-1"></i>
                </div>
                <h2 class="text-danger mb-3">Session Fix Failed</h2>
                <p class="text-muted mb-4"><?php echo $error; ?></p>
                
                <div class="d-grid gap-2">
                    <a href="debug_access.php" class="btn btn-warning">
                        <i class="bi bi-bug"></i> Debug Access Issues
                    </a>
                    <a href="../dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> Back to Dashboard
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Info -->
                <div class="status-icon status-success">
                    <i class="bi bi-tools text-white fs-1"></i>
                </div>
                <h2 class="text-primary mb-3">Fix Session Variables</h2>
                <p class="text-muted mb-4">This script will fix session issues and ensure proper admin access.</p>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Current Status:</strong> Checking your session...
                </div>
                
                <div class="d-grid gap-2">
                    <a href="?fix=1" class="btn btn-primary btn-lg">
                        <i class="bi bi-wrench"></i> Fix My Session
                    </a>
                    <a href="debug_access.php" class="btn btn-outline-info">
                        <i class="bi bi-bug"></i> Debug First
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 p-3 bg-light rounded">
                <small class="text-muted">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Note:</strong> This script fixes session variables for admin access.
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
