<?php
require_once 'admin_header.php';

// Handle backup creation
if (isset($_POST['create_backup'])) {
    $backup_data = [];
    
    // Get all collections data
    $backup_data['users'] = $db->getAllUsers(10000, 0);
    $backup_data['daily_credit_claims'] = [];
    $backup_data['audit_logs'] = $db->getAuditLogs(1000, 0);
    $backup_data['timestamp'] = time();
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
    $filepath = __DIR__ . '/../data/backups/' . $filename;
    
    // Create backups directory if it doesn't exist
    if (!is_dir(__DIR__ . '/../data/backups/')) {
        mkdir(__DIR__ . '/../data/backups/', 0755, true);
    }
    
    if (file_put_contents($filepath, json_encode($backup_data, JSON_PRETTY_PRINT))) {
        $db->logAuditAction($_SESSION['user_id'], 'backup_created', null, ['filename' => $filename]);
        $successMessage = "Backup created successfully: {$filename}";
    } else {
        $errorMessage = "Failed to create backup.";
    }
}

// Handle backup download
if (isset($_GET['download'])) {
    $filename = basename(sanitizeInput($_GET['download'], 'string'));
    $filepath = __DIR__ . '/../data/backups/' . $filename;
    
    if (file_exists($filepath) && strpos($filename, 'backup_') === 0) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($filepath);
        exit;
    }
}

// Get existing backups
$backups = [];
$backup_dir = __DIR__ . '/../data/backups/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (strpos($file, 'backup_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $filepath = $backup_dir . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'created' => filemtime($filepath)
            ];
        }
    }
    // Sort by creation time, newest first
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Database Backup</h5>
                    <p class="card-subtitle text-muted">Create and manage database backups.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="POST">
                                <button type="submit" name="create_backup" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Create New Backup
                                </button>
                            </form>
                        </div>
                    </div>

                    <h6>Existing Backups</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backups)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No backups found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                    <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                    <td><?php echo date('Y-m-d H:i:s', $backup['created']); ?></td>
                                    <td>
                                        <a href="?download=<?php echo urlencode($backup['filename']); ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Backups include user data, audit logs, and system configuration. 
                        Store backups securely and regularly create new ones.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
