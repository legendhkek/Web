<?php
require_once '../config.php';
require_once 'admin_auth.php';
require_once '../error_handler.php';

// Check admin authorization
requireAdminAuth();

$nonce = setSecurityHeaders();
$current_user = getCurrentUser();

// Handle clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
        ErrorHandler::clearErrorLog();
        $success_message = "Error logs cleared successfully!";
    } else {
        $error_message = "Invalid CSRF token!";
    }
}

// Get recent errors
$limit = isset($_GET['limit']) ? max(1, (int)filter_var($_GET['limit'], FILTER_SANITIZE_NUMBER_INT)) : 100;
$recent_errors = ErrorHandler::getRecentErrors($limit);

// Parse errors for better display
$parsed_errors = [];
foreach ($recent_errors as $error_line) {
    if (preg_match('/\[(.*?)\] IP: (.*?) \| URI: (.*?) \| (.+)/', $error_line, $matches)) {
        $parsed_errors[] = [
            'timestamp' => $matches[1],
            'ip' => $matches[2],
            'uri' => $matches[3],
            'message' => $matches[4],
            'raw' => $error_line
        ];
    } else {
        $parsed_errors[] = [
            'timestamp' => 'Unknown',
            'ip' => 'Unknown',
            'uri' => 'Unknown',
            'message' => $error_line,
            'raw' => $error_line
        ];
    }
}

// Get error statistics
$error_stats = [
    'total' => count($parsed_errors),
    'by_type' => [],
    'by_hour' => []
];

foreach ($parsed_errors as $error) {
    // Count by error type
    if (preg_match('/\[(ERROR|WARNING|NOTICE|FATAL|CRITICAL)\]/', $error['message'], $type_match)) {
        $type = $type_match[1];
        $error_stats['by_type'][$type] = ($error_stats['by_type'][$type] ?? 0) + 1;
    }
    
    // Count by hour
    if ($error['timestamp'] !== 'Unknown') {
        $hour = substr($error['timestamp'], 11, 2);
        $error_stats['by_hour'][$hour] = ($error_stats['by_hour'][$hour] ?? 0) + 1;
    }
}

include 'admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-bug text-danger"></i> Error Logs
        </h1>
        <div>
            <a href="?limit=50" class="btn btn-sm btn-outline-primary <?php echo $limit === 50 ? 'active' : ''; ?>">50</a>
            <a href="?limit=100" class="btn btn-sm btn-outline-primary <?php echo $limit === 100 ? 'active' : ''; ?>">100</a>
            <a href="?limit=500" class="btn btn-sm btn-outline-primary <?php echo $limit === 500 ? 'active' : ''; ?>">500</a>
            <button type="button" class="btn btn-sm btn-danger" onclick="clearLogs()">
                <i class="bi bi-trash"></i> Clear Logs
            </button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Errors</h6>
                    <h2 class="mb-0"><?php echo number_format($error_stats['total']); ?></h2>
                </div>
            </div>
        </div>
        <?php foreach ($error_stats['by_type'] as $type => $count): ?>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2"><?php echo htmlspecialchars($type); ?></h6>
                    <h2 class="mb-0 text-<?php 
                        echo $type === 'ERROR' || $type === 'FATAL' ? 'danger' : 
                             ($type === 'WARNING' ? 'warning' : 'info');
                    ?>"><?php echo number_format($count); ?></h2>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Error List -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list-ul"></i> Recent Errors (Showing <?php echo count($parsed_errors); ?>)
        </div>
        <div class="card-body p-0">
            <?php if (empty($parsed_errors)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle display-1"></i>
                    <p class="mt-3">No errors logged! System is running smoothly.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 150px;">Timestamp</th>
                                <th style="width: 120px;">IP Address</th>
                                <th style="width: 200px;">URI</th>
                                <th>Error Message</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parsed_errors as $index => $error): ?>
                            <tr>
                                <td><small class="text-muted"><?php echo htmlspecialchars($error['timestamp']); ?></small></td>
                                <td><code class="small"><?php echo htmlspecialchars($error['ip']); ?></code></td>
                                <td><small class="text-truncate d-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($error['uri']); ?>"><?php echo htmlspecialchars($error['uri']); ?></small></td>
                                <td>
                                    <small class="<?php 
                                        echo strpos($error['message'], '[ERROR]') !== false || strpos($error['message'], '[FATAL]') !== false ? 'text-danger' : 
                                             (strpos($error['message'], '[WARNING]') !== false ? 'text-warning' : 'text-muted');
                                    ?>">
                                        <?php echo htmlspecialchars(substr($error['message'], 0, 150)); ?>
                                        <?php if (strlen($error['message']) > 150): ?>
                                            <a href="#" onclick="showFullError(<?php echo $index; ?>); return false;">...</a>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="showFullError(<?php echo $index; ?>)" title="View Full Error">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Error Detail Modal -->
<div class="modal fade" id="errorDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Error Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="errorDetailContent" class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Clear Logs Form -->
<form id="clearLogsForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="clear_logs">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
</form>

<script nonce="<?php echo $nonce; ?>">
    const errors = <?php echo json_encode($parsed_errors); ?>;
    
    function showFullError(index) {
        const error = errors[index];
        document.getElementById('errorDetailContent').textContent = error.raw;
        const modal = new bootstrap.Modal(document.getElementById('errorDetailModal'));
        modal.show();
    }
    
    function clearLogs() {
        if (confirm('Are you sure you want to clear all error logs? This action cannot be undone.')) {
            document.getElementById('clearLogsForm').submit();
        }
    }
    
    // Auto-refresh every 30 seconds
    setTimeout(() => {
        location.reload();
    }, 30000);
</script>

<?php include 'admin_footer.php'; ?>
