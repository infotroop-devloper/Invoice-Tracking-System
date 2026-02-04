<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'Forwarded Invoices';
$database = new Database();
$db = $database->connect();

$user_id = $_SESSION['user_id'];

// Get invoices forwarded by current user
$invoices = $db->prepare("
    SELECT DISTINCT
        i.*,
        p.project_name,
        s.stage_name,
        u.full_name as current_holder_name,
        im.movement_date as forwarded_date,
        im.remarks as forward_remarks
    FROM invoices i
    JOIN projects p ON i.project_id = p.project_id
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    JOIN users u ON i.current_holder_id = u.user_id
    JOIN invoice_movements im ON i.invoice_id = im.invoice_id
    WHERE im.from_user_id = ?
    ORDER BY im.movement_date DESC
    LIMIT 50
");
$invoices->execute([$user_id]);
$invoices = $invoices->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-paper-plane"></i> Forwarded Invoices</span>
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
                        <th>Current Stage</th>
                        <th>Current Holder</th>
                        <th>Forwarded Date</th>
                        <th>Status</th>
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
                        <td><?php echo $inv['current_holder_name']; ?></td>
                        <td><?php echo formatDateTime($inv['forwarded_date']); ?></td>
                        <td>
                            <?php if ($inv['status'] == 'Active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($inv['status'] == 'Closed'): ?>
                                <span class="badge bg-secondary">Closed</span>
                            <?php elseif ($inv['status'] == 'Pending Acceptance'): ?>
                                <span class="badge bg-warning">Pending Acceptance</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_invoice.php?id=<?php echo $inv['invoice_id']; ?>" 
                               class="btn btn-sm btn-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-paper-plane fa-3x mb-3"></i>
            <p>No forwarded invoices</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
