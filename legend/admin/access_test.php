<?php
/**
 * Admin Access Test Page
 * This page tests the new admin authentication system
 */

require_once '../config.php';
require_once '../database.php';
require_once 'admin_auth.php';

// Get current user
$current_user = getCurrentUser();

// Check if user has admin access
if (!$current_user || (!isAdmin() && !isOwner())) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Get database instance
$db = Database::getInstance();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access Test - LEGEND CHECKER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .test-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- Success Status -->
                <div class="test-card p-5 text-center mb-4">
                    <div class="status-icon status-success">
                        <i class="bi bi-shield-check text-white fs-1"></i>
                    </div>
                    <h1 class="text-success mb-3">🎉 Admin Access Working!</h1>
                    <p class="lead text-muted">The new admin authentication system is working perfectly!</p>
                </div>

                <!-- User Information -->
                <div class="test-card p-4 mb-4">
                    <h4><i class="bi bi-person-circle"></i> User Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Telegram ID:</strong> <?php echo htmlspecialchars($current_user['telegram_id']); ?></p>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($current_user['username'] ?? 'N/A'); ?></p>
                            <p><strong>Display Name:</strong> <?php echo htmlspecialchars($current_user['display_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Role:</strong> <?php echo getRoleBadge($current_user['role'] ?? 'admin'); ?></p>
                            <p><strong>Credits:</strong> <?php echo htmlspecialchars($current_user['credits'] ?? 'N/A'); ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                        </div>
                    </div>
                </div>

                <!-- Session Information -->
                <div class="test-card p-4 mb-4">
                    <h4><i class="bi bi-gear"></i> Session Variables</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>is_admin:</strong> <?php echo isAdmin() ? '✅ true' : '❌ false'; ?></p>
                            <p><strong>is_owner:</strong> <?php echo isOwner() ? '✅ true' : '❌ false'; ?></p>
                            <p><strong>user_role:</strong> <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Not set'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>telegram_id:</strong> <?php echo htmlspecialchars($_SESSION['telegram_id'] ?? 'Not set'); ?></p>
                            <p><strong>user_id:</strong> <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Not set'); ?></p>
                            <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Access Test -->
                <div class="test-card p-4 mb-4">
                    <h4><i class="bi bi-link-45deg"></i> Admin Panel Access Test</h4>
                    <p class="text-muted mb-3">Test these admin panel links to verify full access:</p>
                    
                    <div class="d-grid gap-2 d-md-block">
                        <a href="analytics.php" class="btn btn-primary">
                            <i class="bi bi-speedometer2"></i> Analytics Dashboard
                        </a>
                        <a href="user_management.php" class="btn btn-success">
                            <i class="bi bi-people"></i> User Management
                        </a>
                        <a href="tool_config.php" class="btn btn-info">
                            <i class="bi bi-tools"></i> Tool Configuration
                        </a>
                        <a href="audit_log.php" class="btn btn-warning">
                            <i class="bi bi-journal-text"></i> Audit Log
                        </a>
                        
                        <?php if (isOwner()): ?>
                        <a href="system_config.php" class="btn btn-danger">
                            <i class="bi bi-gear"></i> System Config (Owner Only)
                        </a>
                        <a href="payment_config.php" class="btn btn-dark">
                            <i class="bi bi-credit-card"></i> Payment Config (Owner Only)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Information -->
                <div class="test-card p-4 mb-4">
                    <h4><i class="bi bi-info-circle"></i> System Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Admin IDs:</strong> <?php echo json_encode(AppConfig::ADMIN_IDS); ?></p>
                            <p><strong>Owner IDs:</strong> <?php echo json_encode(AppConfig::OWNER_IDS); ?></p>
                            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Database:</strong> <?php echo DatabaseConfig::DATABASE_NAME; ?></p>
                            <p><strong>Server Time:</strong> <?php echo date('M j, Y g:i A'); ?></p>
                            <p><strong>Session Status:</strong> <span class="badge bg-success">Active</span></p>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="test-card p-4 text-center">
                    <div class="d-grid gap-2">
                        <a href="analytics.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-speedometer2"></i> Enter Admin Panel
                        </a>
                        <a href="../dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Back to Main Site
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
