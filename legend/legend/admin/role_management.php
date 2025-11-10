<?php
require_once 'admin_header.php';

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if ($db->updateUserRole($user_id, $new_role)) {
        $db->logAuditAction($_SESSION['user_id'], 'role_changed', $user_id, [
            'new_role' => $new_role,
            'changed_by' => $_SESSION['user_id']
        ]);
        $successMessage = "User role updated successfully!";
    } else {
        $errorMessage = "Failed to update user role.";
    }
}

// Get all users for role management
$all_users = $db->getAllUsers(100, 0);

// Role definitions
$roles = [
    'free' => 'Free User',
    'premium' => 'Premium User', 
    'vip' => 'VIP User',
    'admin' => 'Administrator',
    'owner' => 'Owner'
];

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Role Management</h5>
                    <p class="card-subtitle text-muted">Manage user roles and permissions.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    <?php if (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Current Role</th>
                                    <th>Status</th>
                                    <th>Credits</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['display_name']); ?></strong>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'owner' ? 'danger' : ($user['role'] === 'admin' ? 'warning' : 'secondary'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $user['status'] ?? 'active';
                                        $badge_class = $status === 'banned' ? 'bg-danger' : 'bg-success';
                                        echo "<span class='badge {$badge_class}'>" . htmlspecialchars(ucfirst($status)) . "</span>";
                                        ?>
                                    </td>
                                    <td><?php echo number_format($user['credits']); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['telegram_id']; ?>">
                                            <select name="new_role" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                                <?php foreach ($roles as $role_key => $role_name): ?>
                                                    <option value="<?php echo $role_key; ?>" <?php echo $user['role'] === $role_key ? 'selected' : ''; ?>>
                                                        <?php echo $role_name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="change_role" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <h6><i class="bi bi-info-circle"></i> Role Permissions</h6>
                        <ul class="mb-0">
                            <li><strong>Free:</strong> Basic access, limited credits</li>
                            <li><strong>Premium:</strong> Enhanced features, more credits</li>
                            <li><strong>VIP:</strong> All premium features plus priority support</li>
                            <li><strong>Admin:</strong> Access to admin panel, user management</li>
                            <li><strong>Owner:</strong> Full system access, all admin features</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
