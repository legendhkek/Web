<?php
/**
 * Admin Login Redirect Page
 * This page helps users get back to the admin panel when their session expires
 */

require_once '../config.php';
require_once '../database.php';

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (!empty($_SESSION['user_id']) || !empty($_SESSION['telegram_id'])) {
    // User is logged in, redirect to admin panel
    header('Location: analytics.php');
    exit;
}

// Get error message if any
$error = $_GET['error'] ?? '';
$redirect = $_GET['redirect'] ?? 'admin';

// Get database instance
$db = Database::getInstance();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access Required - LEGEND CHECKER</title>
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
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 40px;
        }
        .btn-telegram {
            background: #0088cc;
            border-color: #0088cc;
            color: white;
        }
        .btn-telegram:hover {
            background: #006699;
            border-color: #006699;
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-shield-check fs-1 mb-3"></i>
            <h2>Admin Access Required</h2>
            <p class="mb-0">You need to log in to access the admin panel</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php 
                    switch($error) {
                        case 'session_expired':
                            echo 'Your session has expired. Please log in again.';
                            break;
                        case 'invalid_session':
                            echo 'Invalid session detected. Please log in again.';
                            break;
                        case 'access_denied':
                            echo 'Access denied. You need admin privileges.';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="text-center mb-4">
                <h5>How to Access Admin Panel</h5>
                <p class="text-muted">You need to log in through Telegram to access the admin panel.</p>
            </div>

            <div class="d-grid gap-3">
                <a href="../auth.php?redirect=admin" class="btn btn-telegram btn-lg">
                    <i class="bi bi-telegram"></i> Login with Telegram
                </a>
                
                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house"></i> Back to Main Site
                </a>
            </div>

            <hr class="my-4">

            <div class="text-center">
                <h6>Admin Access Requirements</h6>
                <div class="row text-start">
                    <div class="col-6">
                        <small class="text-muted">
                            <i class="bi bi-check-circle text-success"></i> Valid Telegram account<br>
                            <i class="bi bi-check-circle text-success"></i> Admin or Owner role<br>
                            <i class="bi bi-check-circle text-success"></i> Active session
                        </small>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">
                            <i class="bi bi-shield-check text-info"></i> Role-based access<br>
                            <i class="bi bi-gear text-warning"></i> Owner-only features<br>
                            <i class="bi bi-activity text-danger"></i> Activity monitoring
                        </small>
                    </div>
                </div>
            </div>

            <?php if (!empty(AppConfig::OWNER_IDS)): ?>
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
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
