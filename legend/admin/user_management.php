<?php
require_once 'admin_header.php';
require_once 'admin_utils.php';

// Search and filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'desc';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query parameters for pagination links
$query_params = array_filter([
    'search' => $search,
    'role' => $role_filter,
    'status' => $status_filter,
    'sort' => $sort_by,
    'order' => $sort_order
]);

// Get filtered users (in real implementation, this would be done in database)
$all_users = $db->getAllUsers(1000, 0); // Get more users for filtering
$filtered_users = array_filter($all_users, function($user) use ($search, $role_filter, $status_filter) {
    if ($search && stripos($user['display_name'] . ' ' . $user['username'], $search) === false) {
        return false;
    }
    if ($role_filter && $user['role'] !== $role_filter) {
        return false;
    }
    if ($status_filter && ($user['status'] ?? 'active') !== $status_filter) {
        return false;
    }
    return true;
});

// Sort users
usort($filtered_users, function($a, $b) use ($sort_by, $sort_order) {
    $val_a = $a[$sort_by] ?? 0;
    $val_b = $b[$sort_by] ?? 0;
    
    if ($sort_by === 'display_name' || $sort_by === 'username') {
        $result = strcasecmp($val_a, $val_b);
    } else {
        $result = $val_a <=> $val_b;
    }
    
    return $sort_order === 'desc' ? -$result : $result;
});

$total_filtered = count($filtered_users);
$paginated_users = array_slice($filtered_users, $offset, $limit);
$total_pages = ceil($total_filtered / $limit);

// Helper function for sort links
function getSortLink($column, $current_sort, $current_order) {
    $new_order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    return '?' . http_build_query($params);
}

function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort !== $column) return '<i class="bi bi-arrow-down-up text-muted"></i>';
    return $current_order === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>User Management</h1>
                    <p class="text-muted">Search, view, and manage all registered users.</p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                        <i class="bi bi-gear-fill"></i> Bulk Actions
                    </button>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Users</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or username...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="">All Roles</option>
                                <option value="free" <?php echo $role_filter === 'free' ? 'selected' : ''; ?>>Free</option>
                                <option value="premium" <?php echo $role_filter === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                <option value="vip" <?php echo $role_filter === 'vip' ? 'selected' : ''; ?>>VIP</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="owner" <?php echo $role_filter === 'owner' ? 'selected' : ''; ?>>Owner</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select class="form-select" name="sort">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Join Date</option>
                                <option value="display_name" <?php echo $sort_by === 'display_name' ? 'selected' : ''; ?>>Name</option>
                                <option value="credits" <?php echo $sort_by === 'credits' ? 'selected' : ''; ?>>Credits</option>
                                <option value="last_login_at" <?php echo $sort_by === 'last_login_at' ? 'selected' : ''; ?>>Last Login</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <a href="user_management.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <strong><?php echo number_format($total_filtered); ?></strong> users found
                        <?php if ($search || $role_filter || $status_filter): ?>
                            <small class="text-muted">(filtered from <?php echo number_format(count($all_users)); ?> total)</small>
                        <?php endif; ?>
                    </span>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="selectAllUsers()">Select All</button>
                        <button class="btn btn-outline-secondary" onclick="clearSelection()">Clear</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" onchange="toggleAllUsers(this)">
                                    </th>
                                    <th>
                                        <a href="<?php echo getSortLink('telegram_id', $sort_by, $sort_order); ?>" class="text-decoration-none">
                                            User ID <?php echo getSortIcon('telegram_id', $sort_by, $sort_order); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo getSortLink('display_name', $sort_by, $sort_order); ?>" class="text-decoration-none">
                                            Name <?php echo getSortIcon('display_name', $sort_by, $sort_order); ?>
                                        </a>
                                    </th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>
                                        <a href="<?php echo getSortLink('credits', $sort_by, $sort_order); ?>" class="text-decoration-none">
                                            Credits <?php echo getSortIcon('credits', $sort_by, $sort_order); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo getSortLink('last_login_at', $sort_by, $sort_order); ?>" class="text-decoration-none">
                                            Last Login <?php echo getSortIcon('last_login_at', $sort_by, $sort_order); ?>
                                        </a>
                                    </th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paginated_users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-person-x fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">No users found matching your criteria.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($paginated_users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="user-checkbox" value="<?php echo $user['telegram_id']; ?>">
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($user['telegram_id']); ?></code>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <?php echo strtoupper(substr($user['display_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($user['display_name']); ?></div>
                                                <small class="text-muted">@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $role_colors = [
                                            'free' => 'secondary',
                                            'premium' => 'warning',
                                            'vip' => 'success',
                                            'admin' => 'info',
                                            'owner' => 'danger'
                                        ];
                                        $role_color = $role_colors[$user['role']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $role_color; ?>"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $user['status'] ?? 'active';
                                        $badge_class = $status === 'banned' ? 'bg-danger' : 'bg-success';
                                        $status_icon = $status === 'banned' ? 'bi-x-circle' : 'bi-check-circle';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <i class="bi <?php echo $status_icon; ?>"></i> <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?php echo number_format($user['credits']); ?></span>
                                        <small class="text-muted">XCoins</small>
                                    </td>
                                    <td>
                                        <?php if (isset($user['last_login_at'])): ?>
                                            <?php
                                            $lastLogin = $user['last_login_at'];
                                            $title = '';
                                            if ($lastLogin instanceof MongoDB\BSON\UTCDateTime) {
                                                $title = $lastLogin->toDateTime()->format('Y-m-d H:i:s');
                                            } elseif (is_numeric($lastLogin)) {
                                                $title = date('Y-m-d H:i:s', $lastLogin);
                                            }
                                            ?>
                                            <span title="<?php echo htmlspecialchars($title); ?>">
                                                <?php echo formatDate($user['last_login_at']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_user.php?id=<?php echo $user['telegram_id']; ?>" class="btn btn-outline-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="user_role_actions.php?id=<?php echo $user['telegram_id']; ?>" class="btn btn-outline-primary" title="Change Role">
                                                <i class="bi bi-person-gear"></i>
                                            </a>
                                            <a href="credit_actions.php?id=<?php echo $user['telegram_id']; ?>" class="btn btn-outline-warning" title="Adjust Credits">
                                                <i class="bi bi-coin"></i>
                                            </a>
                                            <?php if ($status === 'banned'): ?>
                                                <a href="user_actions.php?action=unban&id=<?php echo $user['telegram_id']; ?>" class="btn btn-outline-success" title="Unban User">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="user_actions.php?action=ban&id=<?php echo $user['telegram_id']; ?>" class="btn btn-outline-danger" title="Ban User">
                                                    <i class="bi bi-slash-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Enhanced Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $limit, $total_filtered)); ?> of <?php echo number_format($total_filtered); ?> users
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => 1])); ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $total_pages])); ?>">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Select users and choose an action to perform on multiple users at once.</p>
                <div class="mb-3">
                    <label class="form-label">Action</label>
                    <select class="form-select" id="bulkAction">
                        <option value="">Choose action...</option>
                        <option value="ban">Ban Selected Users</option>
                        <option value="unban">Unban Selected Users</option>
                        <option value="role_free">Set Role to Free</option>
                        <option value="role_premium">Set Role to Premium</option>
                        <option value="role_vip">Set Role to VIP</option>
                        <option value="add_credits">Add Credits</option>
                        <option value="remove_credits">Remove Credits</option>
                    </select>
                </div>
                <div class="mb-3" id="creditAmountDiv" style="display: none;">
                    <label class="form-label">Credit Amount</label>
                    <input type="number" class="form-control" id="creditAmount" min="1" value="100">
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> Bulk actions cannot be undone. Please review your selection carefully.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="performBulkAction()">Execute Action</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAllUsers(checkbox) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function selectAllUsers() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAll').checked = true;
}

function clearSelection() {
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
}

document.getElementById('bulkAction').addEventListener('change', function() {
    const creditDiv = document.getElementById('creditAmountDiv');
    if (this.value === 'add_credits' || this.value === 'remove_credits') {
        creditDiv.style.display = 'block';
    } else {
        creditDiv.style.display = 'none';
    }
});

function performBulkAction() {
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
    const action = document.getElementById('bulkAction').value;
    
    if (selectedUsers.length === 0) {
        alert('Please select at least one user.');
        return;
    }
    
    if (!action) {
        alert('Please select an action.');
        return;
    }
    
    if (confirm(`Are you sure you want to perform this action on ${selectedUsers.length} user(s)?`)) {
        // In real implementation, this would make an AJAX call
        alert('Bulk action would be performed on: ' + selectedUsers.join(', '));
    }
}

// Auto-submit search form on Enter
document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});
</script>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
    font-weight: 600;
}
</style>
<?php require_once 'admin_footer.php'; ?>
