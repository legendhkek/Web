<?php
require_once 'admin_header.php';

// Get date range from query params
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date'], 'string') : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date'], 'string') : date('Y-m-d'); // Today

// Mock financial data (in real implementation, this would come from payment processor)
$financial_data = [
    'total_revenue' => 1250.00,
    'total_transactions' => 45,
    'premium_upgrades' => 12,
    'xcoin_purchases' => 33,
    'avg_transaction' => 27.78,
    'top_countries' => [
        ['country' => 'United States', 'revenue' => 450.00, 'transactions' => 15],
        ['country' => 'India', 'revenue' => 320.00, 'transactions' => 18],
        ['country' => 'United Kingdom', 'revenue' => 280.00, 'transactions' => 8],
        ['country' => 'Canada', 'revenue' => 200.00, 'transactions' => 4]
    ]
];

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Financial Reports</h5>
                    <p class="card-subtitle text-muted">Revenue analytics and transaction reports.</p>
                </div>
                <div class="card-body">
                    <!-- Date Range Filter -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Update Report</button>
                            </div>
                        </div>
                    </form>

                    <!-- Financial Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Revenue</h5>
                                    <h3>$<?php echo number_format($financial_data['total_revenue'], 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Transactions</h5>
                                    <h3><?php echo number_format($financial_data['total_transactions']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Premium Upgrades</h5>
                                    <h3><?php echo number_format($financial_data['premium_upgrades']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Avg Transaction</h5>
                                    <h3>$<?php echo number_format($financial_data['avg_transaction'], 2); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue by Country -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title">Revenue by Country</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Country</th>
                                            <th>Revenue</th>
                                            <th>Transactions</th>
                                            <th>Avg per Transaction</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($financial_data['top_countries'] as $country): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($country['country']); ?></td>
                                            <td>$<?php echo number_format($country['revenue'], 2); ?></td>
                                            <td><?php echo number_format($country['transactions']); ?></td>
                                            <td>$<?php echo number_format($country['revenue'] / $country['transactions'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Financial data is currently simulated. Integrate with your payment processor API for real transaction data.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
