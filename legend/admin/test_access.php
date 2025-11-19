<?php
// Test Admin Access - Simple page to verify access works
require_once '../config.php';
require_once '../database.php';

// Initialize secure session
initSecureSession();

// Check if user is logged in
if (empty($_SESSION['user_id']) && empty($_SESSION['telegram_id'])) {
    header('Location: ../login.php?redirect=admin');
    exit;
}

$telegram_id = $_SESSION['telegram_id'] ?? $_SESSION['user_id'];
$db = Database::getInstance();
$user = $db->getUserByTelegramId($telegram_id);

if (!$user) {
    header('Location: ../login.php?error=invalid_session');
    exit;
}

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

$has_access = $is_admin || $is_owner;

if (!$has_access) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Set session variables
$_SESSION['telegram_id'] = $telegram_id;
$_SESSION['is_admin'] = true;
$_SESSION['is_owner'] = $is_owner;
$_SESSION['user_role'] = $is_owner ? 'owner' : 'admin';
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
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        .status-success {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .status-info {
            background: linear-gradient(45deg, #17a2b8, #6f42c1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Access Status -->
                <div class="test-card p-4 text-center mb-4">
                    <div class="status-icon status-success">
                        <i class="bi bi-shield-check text-white fs-2"></i>
                    </div>
                    <h2 class="text-success mb-3">✅ Admin Access Confirmed!</h2>
                    <p class="text-muted">Your session is working correctly and you have admin privileges.</p>
                </div>

                <!-- User Information -->
                <div class="test-card p-4 mb-4">
                    <h4><i class="bi bi-person-circle"></i> User Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Telegram ID:</strong> <?php echo htmlspecialchars($telegram_id); ?></p>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
                            <p><strong>Display Name:</strong> <?php echo htmlspecialchars($user['display_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Role:</strong> 
                                <?php if ($is_owner): ?>
                                    <span class="badge bg-danger">Owner</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Admin</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Credits:</strong> <?php echo htmlspecialchars($user['credits'] ?? 'N/A'); ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                        </div>
                    </div>
                </div>

                <!-- Session Information -->
                <div class="test-card p-4 mb-4">
                    <h4><i class="bi bi-gear"></i> Session Variables</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>is_admin:</strong> <?php echo $_SESSION['is_admin'] ? '✅ true' : '❌ false'; ?></p>
                            <p><strong>is_owner:</strong> <?php echo $_SESSION['is_owner'] ? '✅ true' : '❌ false'; ?></p>
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
                    <h4><i class="bi bi-link-45deg"></i> Access Test Links</h4>
                    <p class="text-muted mb-3">Test these links to verify admin access is working:</p>
                    
                    <div class="d-grid gap-2 d-md-block">
                        <a href="analytics.php" class="btn btn-primary">
                            <i class="bi bi-speedometer2"></i> Analytics Dashboard
                        </a>
                        <a href="user_management.php" class="btn btn-success">
                            <i class="bi bi-people"></i> User Management
                        </a>
                        <a href="system_config.php" class="btn btn-warning">
                            <i class="bi bi-gear"></i> System Config
                        </a>
                        <a href="admin_access.php" class="btn btn-info">
                            <i class="bi bi-shield-check"></i> Admin Access
                        </a>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="test-card p-4 text-center">
                    <div class="d-grid gap-2">
                        <a href="../dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Back to Main Dashboard
                        </a>
                        <a href="debug_access.php" class="btn btn-outline-warning">
                            <i class="bi bi-bug"></i> Debug Access Issues
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
