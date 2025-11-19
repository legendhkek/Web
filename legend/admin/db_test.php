<?php
/**
 * Database Connection Test
 * This page tests if the database connection is working properly
 */

require_once '../config.php';
require_once '../database.php';

// Initialize secure session
initSecureSession();

// Test database connection
try {
    $db = Database::getInstance();
    
    if ($db) {
        $db_status = "✅ Connected";
        $db_message = "Database connection successful";
    } else {
        $db_status = "❌ Failed";
        $db_message = "Database instance is null";
    }
} catch (Exception $e) {
    $db_status = "❌ Error";
    $db_message = "Exception: " . $e->getMessage();
}

// Test config constants
try {
    $admin_ids = AppConfig::ADMIN_IDS;
    $owner_ids = AppConfig::OWNER_IDS;
    $config_status = "✅ Loaded";
} catch (Exception $e) {
    $admin_ids = [];
    $owner_ids = [];
    $config_status = "❌ Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test - LEGEND CHECKER</title>
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
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="test-card text-center mb-4">
                    <div class="p-4">
                        <i class="bi bi-database fs-1 mb-3"></i>
                        <h1>Database Connection Test</h1>
                        <p class="lead mb-0">Testing database connectivity and configuration</p>
                    </div>
                </div>

                <!-- Database Status -->
                <div class="test-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-database"></i> Database Connection</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Status:</strong> 
                                    <span class="status-<?php echo strpos($db_status, '✅') !== false ? 'success' : 'error'; ?>">
                                        <?php echo $db_status; ?>
                                    </span>
                                </p>
                                <p><strong>Message:</strong> <?php echo $db_message; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Database Class:</strong> <?php echo get_class($db); ?></p>
                                <p><strong>Methods Available:</strong> 
                                    <?php if ($db): ?>
                                        <?php echo implode(', ', get_class_methods($db)); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuration Status -->
                <div class="test-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-gear"></i> Configuration</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Config Status:</strong> 
                                    <span class="status-<?php echo strpos($config_status, '✅') !== false ? 'success' : 'error'; ?>">
                                        <?php echo $config_status; ?>
                                    </span>
                                </p>
                                <p><strong>Admin IDs:</strong> <?php echo json_encode($admin_ids); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Owner IDs:</strong> <?php echo json_encode($owner_ids); ?></p>
                                <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Session Status -->
                <div class="test-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-gear"></i> Session Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                                <p><strong>Session Status:</strong> <?php echo session_status(); ?></p>
                                <p><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Telegram ID:</strong> <?php echo $_SESSION['telegram_id'] ?? 'Not set'; ?></p>
                                <p><strong>Is Admin:</strong> <?php echo $_SESSION['is_admin'] ?? 'Not set'; ?></p>
                                <p><strong>Is Owner:</strong> <?php echo $_SESSION['is_owner'] ?? 'Not set'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Database Methods -->
                <?php if ($db): ?>
                <div class="test-card">
                    <div class="card-body p-4">
                        <h4><i class="bi bi-play-circle"></i> Test Database Methods</h4>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="testGetUser()">
                                <i class="bi bi-person"></i> Test getUserByTelegramId
                            </button>
                            <button class="btn btn-success" onclick="testGetAllUsers()">
                                <i class="bi bi-people"></i> Test getAllUsers
                            </button>
                        </div>
                        <div id="testResults" class="mt-3"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="test-card text-center">
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <a href="restore_session.php" class="btn btn-success btn-lg">
                                <i class="bi bi-arrow-clockwise"></i> Restore Session
                            </a>
                            <a href="admin_access.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-shield-check"></i> Admin Access
                            </a>
                            <a href="../dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house"></i> Back to Main Site
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($db): ?>
    <script>
    function testGetUser() {
        const resultsDiv = document.getElementById('testResults');
        resultsDiv.innerHTML = '<div class="alert alert-info">Testing getUserByTelegramId...</div>';
        
        // Test with a sample ID
        fetch('db_test_ajax.php?action=test_get_user&telegram_id=7580639195')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultsDiv.innerHTML = '<div class="alert alert-success">✅ getUserByTelegramId working: ' + JSON.stringify(data.user) + '</div>';
                } else {
                    resultsDiv.innerHTML = '<div class="alert alert-danger">❌ getUserByTelegramId failed: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                resultsDiv.innerHTML = '<div class="alert alert-danger">❌ Error: ' + error.message + '</div>';
            });
    }
    
    function testGetAllUsers() {
        const resultsDiv = document.getElementById('testResults');
        resultsDiv.innerHTML = '<div class="alert alert-info">Testing getAllUsers...</div>';
        
        fetch('db_test_ajax.php?action=test_get_all_users')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultsDiv.innerHTML = '<div class="alert alert-success">✅ getAllUsers working: Found ' + data.count + ' users</div>';
                } else {
                    resultsDiv.innerHTML = '<div class="alert alert-danger">❌ getAllUsers failed: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                resultsDiv.innerHTML = '<div class="alert alert-danger">❌ Error: ' + error.message + '</div>';
            });
    }
    </script>
    <?php endif; ?>
</body>
</html>
