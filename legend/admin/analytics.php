<?php
require_once 'admin_header.php';

// Get current user for display
$current_user = getCurrentUser();

// Fetch analytics data
$total_users = $db->getTotalUsersCount();
$total_credits_claimed = $db->getTotalCreditsClaimed();
$total_tool_uses = $db->getTotalToolUses();
$online_users = count($db->getOnlineUsers());

// Get recent users
$recent_users = $db->getAllUsers(5, 0);

// Get system stats
require_once '../admin_manager.php';
$adminManager = new AdminManager();
$allAdmins = $adminManager->getAllAdmins();
$admin_count = count(array_filter($allAdmins, function($a) { return ($a['type'] ?? '') === 'dynamic' || ($a['type'] ?? '') === 'static'; }));
$owner_count = count(array_filter($allAdmins, function($a) { return ($a['type'] ?? '') === 'owner'; }));

$system_stats = [
    'total_users' => $total_users,
    'total_credits_claimed' => $total_credits_claimed,
    'total_tool_uses' => $total_tool_uses,
    'online_users' => $online_users,
    'admin_count' => $admin_count,
    'owner_count' => $owner_count
];

?>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <h1 class="card-title">
                        <i class="bi bi-speedometer2"></i> Admin Dashboard
                    </h1>
                    <p class="card-text lead">
                        Welcome back, <strong><?php echo htmlspecialchars($current_user['display_name'] ?? 'Admin'); ?></strong>! 
                        Here's what's happening on your platform.
                    </p>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <strong>Role:</strong> <?php echo getRoleBadge($current_user['role'] ?? 'admin'); ?>
                        </div>
                        <div class="me-3">
                            <strong>Last Login:</strong> 
                            <?php 
                            $lastLogin = $current_user['last_login_at'] ?? $current_user['last_login'] ?? null;
                            if ($lastLogin instanceof MongoDB\BSON\UTCDateTime) {
                                echo $lastLogin->toDateTime()->format('M j, Y g:i A');
                            } elseif (is_numeric($lastLogin)) {
                                echo date('M j, Y g:i A', $lastLogin);
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><?php echo number_format($total_users); ?></h5>
                            <p class="card-text">Total Users</p>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><?php echo number_format($total_credits_claimed); ?></h5>
                            <p class="card-text">Credits Claimed</p>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><?php echo number_format($total_tool_uses); ?></h5>
                            <p class="card-text">Tool Uses</p>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-tools"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title"><?php echo number_format($online_users); ?></h5>
                            <p class="card-text">Online Users</p>
                        </div>
                        <div class="fs-1">
                            <i class="bi bi-activity"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear"></i> System Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <p><strong>Admin Users:</strong> <?php echo $system_stats['admin_count']; ?></p>
                            <p><strong>Owner Users:</strong> <?php echo $system_stats['owner_count']; ?></p>
                            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                        </div>
                        <div class="col-6">
                            <p><strong>Server Time:</strong> <?php echo date('M j, Y g:i A'); ?></p>
                            <p><strong>Session ID:</strong> <?php echo substr(session_id(), 0, 8) . '...'; ?></p>
                            <p><strong>Database:</strong> <?php echo DatabaseConfig::DATABASE_NAME; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history"></i> Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_users)): ?>
                        <h6>Recent User Registrations:</h6>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($recent_users, 0, 3) as $user): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['display_name'] ?? 'Unknown'); ?></strong>
                                        <br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username'] ?? 'no_username'); ?></small>
                                    </div>
                                    <span class="badge bg-secondary"><?php echo ucfirst($user['role'] ?? 'user'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent user activity.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="user_management.php" class="btn btn-primary">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                        <a href="credit_actions.php" class="btn btn-success">
                            <i class="bi bi-coin"></i> Credit Actions
                        </a>
                        <a href="tool_config.php" class="btn btn-info">
                            <i class="bi bi-tools"></i> Tool Config
                        </a>
                        <a href="audit_log.php" class="btn btn-warning">
                            <i class="bi bi-journal-text"></i> View Logs
                        </a>
                        <?php if (isOwner()): ?>
                        <a href="system_config.php" class="btn btn-danger">
                            <i class="bi bi-gear"></i> System Config
                        </a>
                        <a href="database_backup.php" class="btn btn-dark">
                            <i class="bi bi-database"></i> Backup DB
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
