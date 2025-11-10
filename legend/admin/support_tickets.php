<?php
require_once 'admin_header.php';

// Handle ticket status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = $_POST['ticket_id'];
    $new_status = $_POST['new_status'];
    $response = $_POST['response'] ?? '';
    
    // In a real implementation, this would update the ticket in database
    $db->logAuditAction($_SESSION['user_id'], 'ticket_updated', $ticket_id, [
        'new_status' => $new_status,
        'response_added' => !empty($response)
    ]);
    $successMessage = "Ticket updated successfully!";
}

// Mock support tickets data
$tickets = [
    [
        'id' => 'T001',
        'user_id' => '123456789',
        'user_name' => 'John Doe',
        'subject' => 'Credits not working',
        'message' => 'I tried to claim my daily credits but it says I already claimed them today.',
        'status' => 'open',
        'priority' => 'medium',
        'created_at' => time() - 3600,
        'updated_at' => time() - 1800
    ],
    [
        'id' => 'T002',
        'user_id' => '987654321',
        'user_name' => 'Jane Smith',
        'subject' => 'Payment issue',
        'message' => 'My premium upgrade payment went through but I still have free account.',
        'status' => 'in_progress',
        'priority' => 'high',
        'created_at' => time() - 7200,
        'updated_at' => time() - 900
    ],
    [
        'id' => 'T003',
        'user_id' => '456789123',
        'user_name' => 'Bob Wilson',
        'subject' => 'Feature request',
        'message' => 'Can you add support for checking multiple cards at once?',
        'status' => 'closed',
        'priority' => 'low',
        'created_at' => time() - 86400,
        'updated_at' => time() - 3600
    ]
];

function getStatusBadge($status) {
    switch ($status) {
        case 'open': return 'bg-warning';
        case 'in_progress': return 'bg-info';
        case 'closed': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getPriorityBadge($priority) {
    switch ($priority) {
        case 'high': return 'bg-danger';
        case 'medium': return 'bg-warning';
        case 'low': return 'bg-success';
        default: return 'bg-secondary';
    }
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Support Tickets</h5>
                    <p class="card-subtitle text-muted">Manage user support requests and issues.</p>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>User</th>
                                    <th>Subject</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ticket['id']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($ticket['user_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($ticket['user_id']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ticket['subject']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($ticket['message'], 0, 50)) . '...'; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($ticket['priority'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($ticket['status']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', $ticket['created_at']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#ticketModal<?php echo $ticket['id']; ?>">
                                            <i class="bi bi-eye-fill"></i> View
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
    </div>
</div>

<!-- Ticket Modals -->
<?php foreach ($tickets as $ticket): ?>
<div class="modal fade" id="ticketModal<?php echo $ticket['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ticket <?php echo htmlspecialchars($ticket['id']); ?> - <?php echo htmlspecialchars($ticket['subject']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>From:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?> (<?php echo htmlspecialchars($ticket['user_id']); ?>)
                </div>
                <div class="mb-3">
                    <strong>Message:</strong>
                    <div class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                    <div class="mb-3">
                        <label for="new_status<?php echo $ticket['id']; ?>" class="form-label">Status</label>
                        <select class="form-select" id="new_status<?php echo $ticket['id']; ?>" name="new_status">
                            <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="response<?php echo $ticket['id']; ?>" class="form-label">Response</label>
                        <textarea class="form-control" id="response<?php echo $ticket['id']; ?>" name="response" rows="4" placeholder="Enter your response to the user..."></textarea>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Ticket</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once 'admin_footer.php'; ?>
