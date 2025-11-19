<?php
/**
 * Session Restoration Script
 * This script helps restore admin access when sessions are lost
 */

require_once '../config.php';
require_once '../database.php';

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database instance
$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telegram_id = isset($_POST['telegram_id']) ? trim(sanitizeInput($_POST['telegram_id'], 'string')) : '';
    $action = isset($_POST['action']) ? sanitizeInput($_POST['action'], 'string') : '';
    
    if ($action === 'restore' && !empty($telegram_id)) {
        // Check if this Telegram ID exists in config
        $admin_ids = AppConfig::ADMIN_IDS;
        $owner_ids = AppConfig::OWNER_IDS;
        
        $is_admin = in_array($telegram_id, $admin_ids);
        $is_owner = in_array($telegram_id, $owner_ids);
        
        if ($is_admin || $is_owner) {
            // Get user data
            $user = $db->getUserByTelegramId($telegram_id);
            
            if ($user) {
                // Restore session
                $_SESSION['user_id'] = $telegram_id;
                $_SESSION['telegram_id'] = $telegram_id;
                $_SESSION['is_admin'] = true;
                $_SESSION['is_owner'] = $is_owner;
                $_SESSION['user_role'] = $is_owner ? 'owner' : 'admin';
                $_SESSION['username'] = $user['username'] ?? '';
                $_SESSION['display_name'] = $user['display_name'] ?? '';
                
                $success_message = "Session restored successfully! Redirecting to admin panel...";
                header("Refresh: 2; URL=analytics.php");
            } else {
                $error_message = "User not found in database. Please check the Telegram ID.";
            }
        } else {
            $error_message = "This Telegram ID is not authorized for admin access.";
        }
    }
}

// Check if user is already logged in
if (!empty($_SESSION['user_id']) || !empty($_SESSION['telegram_id'])) {
    header('Location: analytics.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Admin Session - LEGEND CHECKER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .restore-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        .restore-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 30px;
            text-align: center;
        }
        .restore-body {
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="restore-card">
        <div class="restore-header">
            <i class="bi bi-arrow-clockwise fs-1 mb-3"></i>
            <h2>Restore Admin Session</h2>
            <p class="mb-0">Recover your admin access when sessions are lost</p>
        </div>
        
        <div class="restore-body">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="text-center mb-4">
                <h5>Session Recovery</h5>
                <p class="text-muted">Enter your Telegram ID to restore your admin session.</p>
            </div>

            <form method="POST">
                <div class="mb-3">
                    <label for="telegram_id" class="form-label">Your Telegram ID</label>
                    <input type="text" class="form-control form-control-lg" id="telegram_id" 
                           name="telegram_id" placeholder="e.g., 7580639195" required
                           value="<?php echo htmlspecialchars($_POST['telegram_id'] ?? ''); ?>">
                    <div class="form-text">Enter the Telegram ID that has admin access</div>
                </div>

                <input type="hidden" name="action" value="restore">
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-arrow-clockwise"></i> Restore Session
                    </button>
                </div>
            </form>

            <hr class="my-4">

            <div class="text-center">
                <h6>Current Admin Users</h6>
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">
                            <strong>Owner IDs:</strong><br>
                            <?php echo implode(', ', AppConfig::OWNER_IDS); ?>
                        </small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">
                            <strong>Admin IDs:</strong><br>
                            <?php echo implode(', ', AppConfig::ADMIN_IDS); ?>
                        </small>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-grid gap-2">
                <a href="login_redirect.php" class="btn btn-outline-primary">
                    <i class="bi bi-telegram"></i> Login with Telegram
                </a>
                
                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house"></i> Back to Main Site
                </a>
            </div>

            <div class="mt-4 text-center">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    This tool is for authorized admin users only. 
                    If you're not an admin, please contact the system administrator.
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
