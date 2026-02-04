<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'My Invoices';
$database = new Database();
$db = $database->connect();

$user_id = $_SESSION['user_id'];

// Get invoices assigned to current user
$invoices = $db->prepare("
    SELECT 
        i.*,
        p.project_name,
        s.stage_name,
        u.full_name as current_holder_name,
        creator.full_name as created_by_name,
        DATEDIFF(NOW(), (
            SELECT movement_date 
            FROM invoice_movements 
            WHERE invoice_id = i.invoice_id 
            ORDER BY movement_id DESC LIMIT 1
        )) as days_pending
    FROM invoices i
    JOIN projects p ON i.project_id = p.project_id
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    JOIN users u ON i.current_holder_id = u.user_id
    JOIN users creator ON i.created_by = creator.user_id
    WHERE i.current_holder_id = ? AND i.status = 'Active'
    ORDER BY i.updated_at DESC
");
$invoices->execute([$user_id]);
$invoices = $invoices->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-inbox"></i> My Current Invoices</span>
        <span class="badge bg-primary"><?php echo count($invoices); ?> Invoices</span>
    </div>
    <div class="card-body">
        <?php if (count($invoices) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Vendor</th>
                        <th>Project</th>
                        <th>Stage</th>
                        <th>Amount</th>
                        <th>Received Date</th>
                        <th>Days Pending</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                        <td><?php echo $inv['vendor_name']; ?></td>
                        <td><?php echo $inv['project_name']; ?></td>
                        <td><span class="badge badge-stage bg-info"><?php echo $inv['stage_name']; ?></span></td>
                        <td>
                            <?php if ($inv['invoice_amount']): ?>
                                ₹<?php echo number_format($inv['invoice_amount'], 2); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatDate($inv['received_date']); ?></td>
                        <td>
                            <?php if ($inv['days_pending'] >= 3): ?>
                                <span class="badge bg-danger"><?php echo $inv['days_pending']; ?> days</span>
                            <?php elseif ($inv['days_pending'] >= 2): ?>
                                <span class="badge bg-warning"><?php echo $inv['days_pending']; ?> days</span>
                            <?php else: ?>
                                <span class="badge bg-success"><?php echo $inv['days_pending']; ?> days</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <a href="view_invoice.php?id=<?php echo $inv['invoice_id']; ?>" 
                               class="btn btn-sm btn-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="handover_invoice.php?id=<?php echo $inv['invoice_id']; ?>" 
                               class="btn btn-sm btn-success" title="Hand Over">
                                <i class="fas fa-hand-holding"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>No invoices currently assigned to you</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
