<?php
require_once 'admin_header.php';
require_once 'admin_utils.php';

if (!isset($_GET['id'])) {
    header('Location: user_management.php');
    exit;
}

$user_id = $_GET['id'];
$user = $db->getUserById($user_id);

if (!$user) {
    header('Location: user_management.php?error=user_not_found');
    exit;
}

// Get user statistics
$user_stats = $db->getUserStats($user_id);

// Get recent audit logs for this user
$recent_logs = $db->getAuditLogs(10, 0, $user_id);

// Mock data for activity history (in real implementation, this would come from database)
$activity_history = [
    ['action' => 'Login', 'timestamp' => time() - 3600, 'details' => 'Logged in via Telegram'],
    ['action' => 'Credit Claim', 'timestamp' => time() - 7200, 'details' => 'Claimed daily credits: 100 XCoins'],
    ['action' => 'Tool Usage', 'timestamp' => time() - 10800, 'details' => 'Used Card Checker - 5 credits deducted'],
    ['action' => 'Profile Update', 'timestamp' => time() - 86400, 'details' => 'Updated display name'],
];

// Mock data for transaction history
$transaction_history = [
    ['type' => 'credit_purchase', 'amount' => 1000, 'timestamp' => time() - 172800, 'status' => 'completed'],
    ['type' => 'daily_claim', 'amount' => 100, 'timestamp' => time() - 259200, 'status' => 'completed'],
    ['type' => 'tool_usage', 'amount' => -5, 'timestamp' => time() - 345600, 'status' => 'completed'],
];

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>User Profile</h1>
                    <p class="text-muted">Detailed view of user information and activity</p>
                </div>
                <div>
                    <a href="user_management.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Users
                    </a>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="user_role_actions.php?id=<?php echo $user['telegram_id']; ?>">
                                <i class="bi bi-person-gear"></i> Change Role
                            </a></li>
                            <li><a class="dropdown-item" href="credit_actions.php?id=<?php echo $user['telegram_id']; ?>">
                                <i class="bi bi-coin"></i> Adjust Credits
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (($user['status'] ?? 'active') === 'banned'): ?>
                            <li><a class="dropdown-item text-success" href="user_actions.php?action=unban&id=<?php echo $user['telegram_id']; ?>">
                                <i class="bi bi-check-circle"></i> Unban User
                            </a></li>
                            <?php else: ?>
                            <li><a class="dropdown-item text-danger" href="user_actions.php?action=ban&id=<?php echo $user['telegram_id']; ?>">
                                <i class="bi bi-slash-circle"></i> Ban User
                            </a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- User Profile Card -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <div class="avatar-xl bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                <?php echo strtoupper(substr($user['display_name'], 0, 2)); ?>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($user['display_name']); ?></h4>
                            <p class="text-muted mb-2">@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
                            
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
                            <span class="badge bg-<?php echo $role_color; ?> fs-6 mb-3">
                                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                            </span>

                            <?php 
                            $status = $user['status'] ?? 'active';
                            $status_class = $status === 'banned' ? 'danger' : 'success';
                            $status_icon = $status === 'banned' ? 'x-circle' : 'check-circle';
                            ?>
                            <div class="mb-3">
                                <span class="badge bg-<?php echo $status_class; ?>">
                                    <i class="bi bi-<?php echo $status_icon; ?>"></i> <?php echo ucfirst($status); ?>
                                </span>
                            </div>

                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border-end">
                                        <h5 class="mb-0"><?php echo number_format($user['credits']); ?></h5>
                                        <small class="text-muted">Credits</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <h5 class="mb-0"><?php echo number_format($user['xcoin_balance'] ?? 0); ?></h5>
                                        <small class="text-muted">XCoins</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <h5 class="mb-0"><?php echo number_format($user_stats['total_hits'] ?? 0); ?></h5>
                                    <small class="text-muted">Total Hits</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title">Quick Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Live Cards</span>
                                <span class="fw-bold text-success"><?php echo number_format($user_stats['total_live_cards'] ?? 0); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Charge Cards</span>
                                <span class="fw-bold text-warning"><?php echo number_format($user_stats['total_charge_cards'] ?? 0); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Dead Cards</span>
                                <span class="fw-bold text-danger"><?php echo number_format($user_stats['total_dead_cards'] ?? 0); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Success Rate</span>
                                <span class="fw-bold text-info">
                                    <?php 
                                    $total_checks = ($user_stats['total_hits'] ?? 0);
                                    $success_rate = $total_checks > 0 ? round((($user_stats['total_live_cards'] ?? 0) / $total_checks) * 100, 1) : 0;
                                    echo $success_rate . '%';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- User Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title">User Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Telegram ID</label>
                                        <div><code><?php echo htmlspecialchars($user['telegram_id']); ?></code></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Display Name</label>
                                        <div><?php echo htmlspecialchars($user['display_name']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Username</label>
                                        <div>@<?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Joined Date</label>
                                        <div><?php echo formatDate($user['created_at']); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Last Login</label>
                                        <div><?php echo isset($user['last_login_at']) ? formatDate($user['last_login_at']) : 'Never'; ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Last Activity</label>
                                        <div><?php echo isset($user['last_activity_at']) ? formatDate($user['last_activity_at']) : 'Unknown'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs for Activity -->
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#activity" role="tab">
                                        <i class="bi bi-activity"></i> Recent Activity
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#transactions" role="tab">
                                        <i class="bi bi-credit-card"></i> Transactions
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#audit" role="tab">
                                        <i class="bi bi-shield-check"></i> Audit Log
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <!-- Activity Tab -->
                                <div class="tab-pane fade show active" id="activity" role="tabpanel">
                                    <div class="timeline">
                                        <?php foreach ($activity_history as $activity): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-primary"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                                <p class="timeline-text text-muted"><?php echo htmlspecialchars($activity['details']); ?></p>
                                                <small class="text-muted"><?php echo formatDate($activity['timestamp']); ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Transactions Tab -->
                                <div class="tab-pane fade" id="transactions" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($transaction_history as $transaction): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        $type_icons = [
                                                            'credit_purchase' => 'bi-cart-plus text-success',
                                                            'daily_claim' => 'bi-gift text-primary',
                                                            'tool_usage' => 'bi-tools text-warning'
                                                        ];
                                                        $icon = $type_icons[$transaction['type']] ?? 'bi-circle';
                                                        ?>
                                                        <i class="bi <?php echo $icon; ?>"></i>
                                                        <?php echo ucwords(str_replace('_', ' ', $transaction['type'])); ?>
                                                    </td>
                                                    <td>
                                                        <span class="<?php echo $transaction['amount'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            <?php echo ucfirst($transaction['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($transaction['timestamp']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Audit Log Tab -->
                                <div class="tab-pane fade" id="audit" role="tabpanel">
                                    <?php if (!empty($recent_logs)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Action</th>
                                                    <th>Admin</th>
                                                    <th>Details</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_logs as $log): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['admin_id']); ?></td>
                                                    <td>
                                                        <?php if (isset($log['details'])): ?>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars(json_encode($log['details'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatDate($log['timestamp']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-shield-check fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">No audit logs found for this user.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-xl {
    width: 100px;
    height: 100px;
    font-size: 32px;
    font-weight: 600;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -37px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid var(--bs-primary);
}

.timeline-title {
    margin-bottom: 5px;
    font-size: 14px;
    font-weight: 600;
}

.timeline-text {
    margin-bottom: 5px;
    font-size: 13px;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
}

.nav-tabs .nav-link.active {
    background-color: transparent;
    border-bottom: 2px solid var(--bs-primary);
    color: var(--bs-primary);
}
</style>
<?php require_once 'admin_footer.php'; ?>
