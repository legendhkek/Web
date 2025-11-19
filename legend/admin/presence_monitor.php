<?php
require_once 'admin_header.php';

// Get online users (active in last 5 minutes)
$online_users = $db->getOnlineUsers();

// Get recent activity (last 24 hours)
$recent_activity = [];
if (method_exists($db, 'getRecentActivity')) {
    $recent_activity = $db->getRecentActivity(50);
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Presence Monitor</h5>
                    <p class="card-subtitle text-muted">Real-time user activity and online status.</p>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Users Online</h5>
                                    <h3><?php echo count($online_users); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Users</h5>
                                    <h3><?php echo $db->getTotalUsersCount(); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6>Currently Online Users</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Last Seen</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($online_users)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No users currently online.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($online_users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['display_name']); ?></strong>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                    <td>
                                        <?php
                                        $lastSeen = $user['last_seen'] ?? null;
                                        if ($lastSeen instanceof MongoDB\BSON\UTCDateTime) {
                                            echo $lastSeen->toDateTime()->format('H:i:s');
                                        } elseif (is_numeric($lastSeen)) {
                                            echo date('H:i:s', $lastSeen);
                                        } else {
                                            echo date('H:i:s', time());
                                        }
                                        ?>
                                    </td>
                                    <td><span class="badge bg-success">Online</span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($recent_activity)): ?>
                    <h6 class="mt-4">Recent Activity</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $timestamp = $activity['timestamp'] ?? null;
                                        if ($timestamp instanceof MongoDB\BSON\UTCDateTime) {
                                            echo $timestamp->toDateTime()->format('H:i:s');
                                        } elseif (is_numeric($timestamp)) {
                                            echo date('H:i:s', $timestamp);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Users are considered online if they've been active within the last 5 minutes.
                        This page auto-refreshes every 30 seconds.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php require_once 'admin_footer.php'; ?>
