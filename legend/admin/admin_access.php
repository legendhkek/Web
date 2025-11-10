<?php
/**
 * Admin Access Page
 * This page provides information about admin access and troubleshooting
 */

require_once '../config.php';
require_once '../database.php';

// Initialize session
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database instance
    $db = Database::getInstance();

// Check if user is already logged in
if (!empty($_SESSION['user_id']) || !empty($_SESSION['telegram_id'])) {
    // User is logged in, redirect to admin panel
        header('Location: analytics.php');
        exit;
    }
    
// Get current admin/owner IDs
$admin_ids = AppConfig::ADMIN_IDS;
$owner_ids = AppConfig::OWNER_IDS;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - LEGEND CHECKER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .access-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 25px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="access-card text-center mb-4">
                    <div class="card-header-custom">
                        <i class="bi bi-shield-check fs-1 mb-3"></i>
                        <h1>Admin Panel Access</h1>
                        <p class="lead mb-0">Manage your platform with secure admin tools</p>
                    </div>
                </div>

                <!-- Current Status -->
                <div class="access-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-info-circle"></i> Current Status</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Session Status:</strong> 
                                    <span class="badge bg-warning status-badge">Not Logged In</span>
                                </p>
                                <p><strong>Admin Users:</strong> <?php echo count($admin_ids); ?></p>
                                <p><strong>Owner Users:</strong> <?php echo count($owner_ids); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Database:</strong> 
                                    <span class="badge bg-success status-badge">Connected</span>
                                </p>
                                <p><strong>System:</strong> 
                                    <span class="badge bg-success status-badge">Online</span>
                                </p>
                                <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="access-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-lightning"></i> Quick Actions</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-grid gap-2">
                                    <a href="restore_session.php" class="btn btn-success btn-lg">
                                        <i class="bi bi-arrow-clockwise"></i> Restore Session
                                    </a>
                                    <a href="login_redirect.php" class="btn btn-primary btn-lg">
                                        <i class="bi bi-telegram"></i> Login with Telegram
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid gap-2">
                                    <a href="debug_access.php" class="btn btn-info btn-lg">
                                        <i class="bi bi-bug"></i> Debug Access
                                    </a>
                                    <a href="fix_session.php" class="btn btn-warning btn-lg">
                                        <i class="bi bi-tools"></i> Fix Session
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Users -->
                <div class="access-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-people"></i> Authorized Admin Users</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-shield-fill-check text-danger"></i> Owner Users</h6>
                                <?php if (!empty($owner_ids)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($owner_ids as $owner_id): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span class="badge bg-danger rounded-pill">Owner</span>
                                                <code><?php echo $owner_id; ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No owner users configured</p>
                                <?php endif; ?>
                            </div>
                    <div class="col-md-6">
                                <h6><i class="bi bi-shield-check text-info"></i> Admin Users</h6>
                                <?php if (!empty($admin_ids)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($admin_ids as $admin_id): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span class="badge bg-info rounded-pill">Admin</span>
                                                <code><?php echo $admin_id; ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php else: ?>
                                    <p class="text-muted">No admin users configured</p>
                                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <div class="access-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-question-circle"></i> Troubleshooting</h4>
                        <div class="accordion" id="troubleshootingAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                        <i class="bi bi-exclamation-triangle text-warning"></i> Can't Access Admin Panel?
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#troubleshootingAccordion">
                                    <div class="accordion-body">
                                        <ol>
                                            <li><strong>Check your Telegram ID</strong> - Make sure it's in the admin/owner list above</li>
                                            <li><strong>Try session restoration</strong> - Use the "Restore Session" button</li>
                                            <li><strong>Login again</strong> - Use "Login with Telegram" to re-authenticate</li>
                                            <li><strong>Check config</strong> - Verify your ID is in config.php</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                        <i class="bi bi-gear text-info"></i> Need to Add Admin Access?
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                    <div class="accordion-body">
                                        <p>To add admin access for a new user:</p>
                                        <ol>
                                            <li>Get their Telegram ID</li>
                                            <li>Edit <code>config.php</code> file</li>
                                            <li>Add their ID to <code>ADMIN_IDS</code> or <code>OWNER_IDS</code> array</li>
                                            <li>Save the file and refresh</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="access-card text-center">
                    <div class="card-body p-4">
                <div class="d-grid gap-2">
                            <a href="../dashboard.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-house"></i> Back to Main Site
                    </a>
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
