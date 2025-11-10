<?php
require_once 'admin_header.php';

// Get user ID from URL
$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: user_management.php');
    exit;
}

// Get user details
$user = $db->getUserById($user_id);
if (!$user) {
    header('Location: user_management.php?error=user_not_found');
    exit;
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_role'])) {
    $new_role = $_POST['new_role'];
    $reason = $_POST['reason'] ?? '';
    
    // Validate role
    $valid_roles = ['free', 'premium', 'vip', 'admin', 'owner'];
    if (!in_array($new_role, $valid_roles)) {
        $error = 'Invalid role selected.';
    } else {
        $old_role = $user['role'];
        
        // Update user role
        if ($db->updateUserRole($user_id, $new_role)) {
            // Log the action
            $db->logAuditAction($_SESSION['user_id'], 'role_changed', $user_id, [
                'old_role' => $old_role,
                'new_role' => $new_role,
                'reason' => $reason
            ]);
            
            $success = "User role changed from {$old_role} to {$new_role} successfully.";
            $user['role'] = $new_role; // Update local user data
        } else {
            $error = 'Failed to update user role.';
        }
    }
}

// Role definitions
$role_definitions = [
    'free' => [
        'name' => 'Free User',
        'description' => 'Basic access with limited features',
        'color' => 'secondary',
        'permissions' => ['Basic tool access', 'Limited daily credits', 'Standard support']
    ],
    'premium' => [
        'name' => 'Premium User',
        'description' => 'Enhanced features and higher limits',
        'color' => 'warning',
        'permissions' => ['Enhanced tool access', 'Increased daily credits', 'Priority support', 'Advanced features']
    ],
    'vip' => [
        'name' => 'VIP User',
        'description' => 'Maximum user privileges',
        'color' => 'success',
        'permissions' => ['Full tool access', 'Maximum daily credits', 'VIP support', 'All premium features', 'Exclusive tools']
    ],
    'admin' => [
        'name' => 'Administrator',
        'description' => 'System administration privileges',
        'color' => 'info',
        'permissions' => ['User management', 'System configuration', 'Audit logs', 'All user features']
    ],
    'owner' => [
        'name' => 'Owner',
        'description' => 'Full system control',
        'color' => 'danger',
        'permissions' => ['Complete system access', 'Admin management', 'Financial controls', 'All features']
    ]
];

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Manage User Role</h1>
                    <p class="text-muted">Change user permissions and access level.</p>
                </div>
                <div>
                    <a href="user_management.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Users
                    </a>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- User Information -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">User Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center">
                                    <?php echo strtoupper(substr($user['display_name'], 0, 2)); ?>
                                </div>
                                <h5 class="mt-2 mb-0"><?php echo htmlspecialchars($user['display_name']); ?></h5>
                                <p class="text-muted">@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h6 class="text-muted mb-1">User ID</h6>
                                        <code><?php echo htmlspecialchars($user['telegram_id']); ?></code>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-muted mb-1">Credits</h6>
                                    <span class="fw-bold"><?php echo number_format($user['credits']); ?></span>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Role</label>
                                <div>
                                    <?php 
                                    $current_role = $role_definitions[$user['role']];
                                    ?>
                                    <span class="badge bg-<?php echo $current_role['color']; ?> fs-6">
                                        <?php echo $current_role['name']; ?>
                                    </span>
                                </div>
                                <small class="text-muted"><?php echo $current_role['description']; ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div>
                                    <?php 
                                    $status = $user['status'] ?? 'active';
                                    $status_class = $status === 'banned' ? 'danger' : 'success';
                                    $status_icon = $status === 'banned' ? 'x-circle' : 'check-circle';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <i class="bi bi-<?php echo $status_icon; ?>"></i> <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Joined</label>
                                <div class="text-muted">
                                    <?php echo formatDate($user['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Last Login</label>
                                <div class="text-muted">
                                    <?php echo isset($user['last_login_at']) ? formatDate($user['last_login_at']) : 'Never'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role Change Form -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Change User Role</h5>
                            <p class="card-subtitle text-muted">Select a new role for this user.</p>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label">Select New Role</label>
                                    <div class="row g-3">
                                        <?php foreach ($role_definitions as $role_key => $role_info): ?>
                                        <div class="col-md-6">
                                            <div class="card role-card <?php echo $user['role'] === $role_key ? 'border-primary current-role' : ''; ?>">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="new_role" 
                                                               id="role_<?php echo $role_key; ?>" value="<?php echo $role_key; ?>"
                                                               <?php echo $user['role'] === $role_key ? 'checked' : ''; ?>>
                                                        <label class="form-check-label w-100" for="role_<?php echo $role_key; ?>">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <span class="badge bg-<?php echo $role_info['color']; ?>">
                                                                    <?php echo $role_info['name']; ?>
                                                                </span>
                                                                <?php if ($user['role'] === $role_key): ?>
                                                                    <small class="text-primary fw-bold">CURRENT</small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="text-muted small mb-2"><?php echo $role_info['description']; ?></p>
                                                            <div class="permissions">
                                                                <?php foreach ($role_info['permissions'] as $permission): ?>
                                                                    <small class="badge bg-light text-dark me-1 mb-1">
                                                                        <i class="bi bi-check2"></i> <?php echo $permission; ?>
                                                                    </small>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Reason for Change (Optional)</label>
                                    <textarea class="form-control" name="reason" rows="3" 
                                              placeholder="Explain why you're changing this user's role..."></textarea>
                                    <div class="form-text">This will be logged in the audit trail.</div>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <strong>Important:</strong> Changing user roles will immediately affect their access permissions. 
                                    Admin and Owner roles have significant system privileges.
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="user_management.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary" onclick="return confirmRoleChange()">
                                        <i class="bi bi-person-gear"></i> Update Role
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Role Comparison -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title">Role Permissions Comparison</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Permission</th>
                                            <th class="text-center">Free</th>
                                            <th class="text-center">Premium</th>
                                            <th class="text-center">VIP</th>
                                            <th class="text-center">Admin</th>
                                            <th class="text-center">Owner</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Basic Tools</td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                        </tr>
                                        <tr>
                                            <td>Advanced Features</td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                        </tr>
                                        <tr>
                                            <td>Exclusive Tools</td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                        </tr>
                                        <tr>
                                            <td>User Management</td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                        </tr>
                                        <tr>
                                            <td>System Configuration</td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                        </tr>
                                        <tr>
                                            <td>Financial Controls</td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-x-circle text-danger"></i></td>
                                            <td class="text-center"><i class="bi bi-check-circle text-success"></i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmRoleChange() {
    const selectedRole = document.querySelector('input[name="new_role"]:checked');
    if (!selectedRole) {
        alert('Please select a role.');
        return false;
    }
    
    const currentRole = '<?php echo $user['role']; ?>';
    const newRole = selectedRole.value;
    
    if (currentRole === newRole) {
        alert('The selected role is the same as the current role.');
        return false;
    }
    
    const roleName = selectedRole.nextElementSibling.querySelector('.badge').textContent;
    return confirm(`Are you sure you want to change this user's role to ${roleName}?\n\nThis action will be logged and cannot be easily undone.`);
}

// Highlight selected role card
document.querySelectorAll('input[name="new_role"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove highlight from all cards
        document.querySelectorAll('.role-card').forEach(card => {
            card.classList.remove('border-primary', 'selected-role');
        });
        
        // Add highlight to selected card
        if (this.checked) {
            this.closest('.role-card').classList.add('border-primary', 'selected-role');
        }
    });
});
</script>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 24px;
    font-weight: 600;
}

.role-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.role-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.role-card.selected-role {
    background-color: rgba(13, 110, 253, 0.05);
}

.role-card.current-role {
    background-color: rgba(13, 110, 253, 0.1);
}

.permissions .badge {
    font-size: 0.7rem;
}

.form-check-input:checked ~ .form-check-label .role-card {
    border-color: var(--bs-primary) !important;
}
</style>

<?php require_once 'admin_footer.php'; ?>
