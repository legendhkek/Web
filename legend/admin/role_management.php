<?php
require_once 'admin_header.php';
require_once '../owner_logger.php';

// Only owners can access this page
$current_user = requireOwner();

$message = '';
$message_type = '';

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_role') {
            $user_id = $_POST['user_id'] ?? '';
            $new_role = $_POST['role'] ?? '';
            
            if (empty($user_id) || empty($new_role)) {
                throw new Exception('User ID and role are required');
            }
            
            $valid_roles = ['free', 'premium', 'vip', 'admin', 'owner'];
            if (!in_array($new_role, $valid_roles)) {
                throw new Exception('Invalid role');
            }
            
            $target_user = $db->getUserByTelegramId($user_id);
            if (!$target_user) {
                throw new Exception('User not found');
            }
            
            $old_role = $target_user['role'];
            $db->updateUserRole($user_id, $new_role);
            $db->logAuditAction($_SESSION['telegram_id'], 'role_changed', $user_id, [
                'old_role' => $old_role,
                'new_role' => $new_role
            ]);
            
            // Owner notification
            try {
                $ownerLogger = new OwnerLogger();
                $admin_user = $db->getUserByTelegramId($_SESSION['telegram_id']);
                $ownerLogger->sendAdminAlert(
                    $admin_user,
                    'Role Changed',
                    "🔄 Role updated:\n\n" .
                    "👤 User: {$target_user['display_name']}\n" .
                    "🆔 ID: {$user_id}\n" .
                    "📊 Old Role: {$old_role}\n" .
                    "📊 New Role: {$new_role}\n" .
                    "👮 By: {$admin_user['display_name']}"
                );
            } catch (Exception $e) {
                error_log("Owner logging failed: " . $e->getMessage());
            }
            
            $message = "Role updated successfully from {$old_role} to {$new_role}";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all users
$all_users = $db->getAllUsers(1000, 0);

// Get role counts
$role_counts = [
    'free' => 0,
    'premium' => 0,
    'vip' => 0,
    'admin' => 0,
    'owner' => 0
];

foreach ($all_users as $user) {
    $role = $user['role'] ?? 'free';
    if (isset($role_counts[$role])) {
        $role_counts[$role]++;
    }
}

?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="bi bi-person-badge"></i> Role Management</h1>
            <p class="text-muted">Manage user roles and permissions (Owner Only)</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Role Statistics -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-white bg-secondary">
                <div class="card-body text-center">
                    <h3><?php echo $role_counts['free']; ?></h3>
                    <small>Free Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-warning">
                <div class="card-body text-center">
                    <h3><?php echo $role_counts['premium']; ?></h3>
                    <small>Premium Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h3><?php echo $role_counts['vip']; ?></h3>
                    <small>VIP Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body text-center">
                    <h3><?php echo $role_counts['admin']; ?></h3>
                    <small>Admin Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body text-center">
                    <h3><?php echo $role_counts['owner']; ?></h3>
                    <small>Owner Users</small>
                </div>
            </div>
        </div>
    </div>

    <!-- User List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-people"></i> All Users</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Current Role</th>
                            <th>Credits</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                        <tr>
                            <td><code><?php echo $user['telegram_id']; ?></code></td>
                            <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                            <td>@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                            <td><?php echo getRoleBadge($user['role']); ?></td>
                            <td><?php echo number_format($user['credits']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" 
                                        onclick="showRoleModal('<?php echo $user['telegram_id']; ?>', '<?php echo htmlspecialchars($user['display_name']); ?>', '<?php echo $user['role']; ?>')">
                                    <i class="bi bi-pencil"></i> Change Role
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Role Change Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" id="modal_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Change User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>User:</strong></label>
                        <p id="modal_user_name" class="form-control-plaintext"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Current Role:</strong></label>
                        <p id="modal_current_role" class="form-control-plaintext"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_role" class="form-label"><strong>New Role:</strong></label>
                        <select class="form-select" name="role" id="new_role" required>
                            <option value="free">Free</option>
                            <option value="premium">Premium</option>
                            <option value="vip">VIP</option>
                            <option value="admin">Admin</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> Changing roles affects user permissions immediately.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRoleModal(userId, userName, currentRole) {
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_user_name').textContent = userName;
    document.getElementById('modal_current_role').textContent = currentRole.charAt(0).toUpperCase() + currentRole.slice(1);
    document.getElementById('new_role').value = currentRole;
    
    const modal = new bootstrap.Modal(document.getElementById('roleModal'));
    modal.show();
}
</script>

<?php require_once 'admin_footer.php'; ?>
