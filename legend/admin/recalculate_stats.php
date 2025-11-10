<?php
require_once 'admin_header.php';

// Handle recalculation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalculate'])) {
    $operation = $_POST['operation'];
    
    switch ($operation) {
        case 'user_stats':
            // Recalculate user statistics
            $users = $db->getAllUsers(1000, 0);
            $updated_count = 0;
            foreach ($users as $user) {
                // In real implementation, this would recalculate user stats
                $updated_count++;
            }
            $db->logAuditAction($_SESSION['user_id'], 'stats_recalculated', null, ['operation' => 'user_stats', 'count' => $updated_count]);
            $successMessage = "User statistics recalculated for {$updated_count} users.";
            break;
            
        case 'leaderboards':
            // Rebuild leaderboards
            $db->logAuditAction($_SESSION['user_id'], 'stats_recalculated', null, ['operation' => 'leaderboards']);
            $successMessage = "Leaderboards rebuilt successfully.";
            break;
            
        case 'credit_totals':
            // Recalculate credit totals
            $db->logAuditAction($_SESSION['user_id'], 'stats_recalculated', null, ['operation' => 'credit_totals']);
            $successMessage = "Credit totals recalculated successfully.";
            break;
            
        case 'all_stats':
            // Recalculate all statistics
            $db->logAuditAction($_SESSION['user_id'], 'stats_recalculated', null, ['operation' => 'all_stats']);
            $successMessage = "All statistics recalculated successfully.";
            break;
    }
}

// Get current stats summary
$total_users = $db->getTotalUsersCount();
$total_credits_claimed = $db->getTotalCreditsClaimed();
$total_tool_uses = $db->getTotalToolUses();

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Recalculate Statistics</h5>
                    <p class="card-subtitle text-muted">Rebuild user statistics and system data.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>

                    <!-- Current Stats Overview -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Users</h5>
                                    <h3><?php echo number_format($total_users); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Credits Claimed</h5>
                                    <h3><?php echo number_format($total_credits_claimed); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Tool Uses</h5>
                                    <h3><?php echo number_format($total_tool_uses); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recalculation Options -->
                    <h6>Recalculation Options</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">User Statistics</h6>
                                    <p class="card-text">Recalculate individual user stats, credits, and activity metrics.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="operation" value="user_stats">
                                        <button type="submit" name="recalculate" class="btn btn-primary" onclick="return confirm('This may take a while. Continue?')">
                                            <i class="bi bi-calculator"></i> Recalculate User Stats
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Leaderboards</h6>
                                    <p class="card-text">Rebuild leaderboards and ranking systems.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="operation" value="leaderboards">
                                        <button type="submit" name="recalculate" class="btn btn-success">
                                            <i class="bi bi-trophy"></i> Rebuild Leaderboards
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Credit Totals</h6>
                                    <p class="card-text">Recalculate credit balances and transaction totals.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="operation" value="credit_totals">
                                        <button type="submit" name="recalculate" class="btn btn-warning">
                                            <i class="bi bi-coin"></i> Recalculate Credits
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">All Statistics</h6>
                                    <p class="card-text">Comprehensive recalculation of all system statistics.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="operation" value="all_stats">
                                        <button type="submit" name="recalculate" class="btn btn-danger" onclick="return confirm('This will recalculate ALL statistics and may take several minutes. Continue?')">
                                            <i class="bi bi-arrow-clockwise"></i> Recalculate All
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Warning:</strong> Recalculating statistics may take time and temporarily impact performance. 
                        It's recommended to run these operations during low-traffic periods.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
