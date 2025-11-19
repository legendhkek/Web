<?php
require_once 'admin_header.php';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)filter_var($_GET['page'], FILTER_SANITIZE_NUMBER_INT)) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Get audit logs
$audit_logs = $db->getAuditLogs($limit, $offset);

// Helper function to format timestamps
function formatTimestamp($timestamp) {
    if (is_object($timestamp) && method_exists($timestamp, 'toDateTime')) {
        // MongoDB UTCDateTime
        return $timestamp->toDateTime()->format('Y-m-d H:i:s');
    }
    // Unix timestamp
    return date('Y-m-d H:i:s', $timestamp);
}

// Helper function to get action badge class
function getActionBadgeClass($action) {
    switch ($action) {
        case 'user_banned':
            return 'bg-danger';
        case 'user_unbanned':
            return 'bg-success';
        case 'credit_adjusted':
            return 'bg-warning';
        case 'config_updated':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Audit Log</h5>
                    <p class="card-subtitle text-muted">Track all administrative actions for accountability.</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>IP Address</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($audit_logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No audit logs found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><?php echo formatTimestamp($log['timestamp']); ?></td>
                                    <td><?php echo htmlspecialchars($log['admin_id']); ?></td>
                                    <td>
                                        <span class="badge <?php echo getActionBadgeClass($log['action']); ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($log['action']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['target_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td>
                                        <?php if (!empty($log['details'])): ?>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(json_encode($log['details'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php endif; ?>
                            
                            <li class="page-item active">
                                <span class="page-link">Page <?php echo $page; ?></span>
                            </li>
                            
                            <?php if (count($audit_logs) === $limit): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
