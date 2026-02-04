<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'Pending Acceptance';
$database = new Database();
$db = $database->connect();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle acceptance/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $action = $_POST['action'];
    
    // Verify invoice is assigned to current user and pending acceptance
    $stmt = $db->prepare("
        SELECT * FROM invoices 
        WHERE invoice_id = ? AND current_holder_id = ? AND status = 'Pending Acceptance'
    ");
    $stmt->execute([$invoice_id, $user_id]);
    $invoice = $stmt->fetch();
    
    if ($invoice) {
        try {
            $db->beginTransaction();
            
            if ($action == 'accept') {
                // Accept the invoice
                $stmt = $db->prepare("
                    UPDATE invoices 
                    SET status = 'Active', is_acknowledged = 1, updated_at = NOW()
                    WHERE invoice_id = ?
                ");
                $stmt->execute([$invoice_id]);
                
                // Update the latest movement record
                $stmt = $db->prepare("
                    UPDATE invoice_movements 
                    SET is_acknowledged = 1, acknowledged_at = NOW()
                    WHERE invoice_id = ? 
                    ORDER BY movement_id DESC 
                    LIMIT 1
                ");
                $stmt->execute([$invoice_id]);
                
                $success = 'Invoice accepted successfully!';
                
            } elseif ($action == 'reject') {
                // Reject and send back to previous user
                $stmt = $db->prepare("
                    SELECT from_user_id, from_stage_id 
                    FROM invoice_movements 
                    WHERE invoice_id = ? 
                    ORDER BY movement_id DESC 
                    LIMIT 1
                ");
                $stmt->execute([$invoice_id]);
                $previous = $stmt->fetch();
                
                if ($previous && $previous['from_user_id']) {
                    // Return to previous holder
                    $stmt = $db->prepare("
                        UPDATE invoices 
                        SET current_holder_id = ?, current_stage_id = ?, status = 'Active',is_acknowledged = 1, updated_at = NOW()
                        WHERE invoice_id = ?
                    ");
                    $stmt->execute([$previous['from_user_id'], $previous['from_stage_id'], $invoice_id]);
                    
                    // Log the rejection
                    $remarks = sanitize($_POST['remarks'] ?? 'Physical invoice not received - rejected acceptance');
                    $stmt = $db->prepare("
                        INSERT INTO invoice_movements (
                            invoice_id, from_stage_id, to_stage_id, from_user_id, to_user_id, remarks
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $invoice_id,
                        $invoice['current_stage_id'],
                        $previous['from_stage_id'],
                        $user_id,
                        $previous['from_user_id'],
                        $remarks
                    ]);
                    
                    $success = 'Invoice rejected and returned to previous holder.';
                } else {
                    throw new Exception('Cannot find previous holder');
                }
            }
            
            $db->commit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error processing request: ' . $e->getMessage();
        }
    } else {
        $error = 'Invoice not found or already processed';
    }
}

// Get pending invoices
$pending_invoices = $db->prepare("
    SELECT 
        i.*,
        p.project_name,
        s.stage_name,
        sender.full_name as sender_name,
        DATEDIFF(NOW(), i.updated_at) as days_waiting
    FROM invoices i
    JOIN projects p ON i.project_id = p.project_id
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    LEFT JOIN (
        SELECT invoice_id, from_user_id 
        FROM invoice_movements 
        WHERE invoice_id IN (SELECT invoice_id FROM invoices WHERE current_holder_id = ? AND status = 'Pending Acceptance')
        AND movement_id IN (
            SELECT MAX(movement_id) FROM invoice_movements GROUP BY invoice_id
        )
    ) last_movement ON i.invoice_id = last_movement.invoice_id
    LEFT JOIN users sender ON last_movement.from_user_id = sender.user_id
    WHERE i.current_holder_id = ? AND i.status = 'Pending Acceptance'
    ORDER BY i.updated_at DESC
");
$pending_invoices->execute([$user_id, $user_id]);
$pending_invoices = $pending_invoices->fetchAll();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <span><i class="fas fa-clock"></i> Invoices Awaiting Your Acceptance</span>
                <span class="badge bg-warning"><?php echo count($pending_invoices); ?> Pending</span>
            </div>
            <div class="card-body">
                <?php if (count($pending_invoices) > 0): ?>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Important:</strong> Please verify you have received the physical invoice before accepting. 
                    If you haven't received it, click "Reject" to return it to the sender.
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Vendor</th>
                                <th>Project</th>
                                <th>Stage</th>
                                <th>From</th>
                                <th>Waiting</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_invoices as $inv): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $inv['invoice_number']; ?></strong>
                                    <?php if ($inv['days_waiting'] >= 1): ?>
                                        <br><span class="badge bg-danger">Urgent!</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $inv['vendor_name']; ?></td>
                                <td><?php echo $inv['project_name']; ?></td>
                                <td><span class="badge badge-stage"><?php echo $inv['stage_name']; ?></span></td>
                                <td><?php echo $inv['sender_name'] ?? 'System'; ?></td>
                                <td>
                                    <?php if ($inv['days_waiting'] >= 1): ?>
                                        <span class="badge bg-danger"><?php echo $inv['days_waiting']; ?> days</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_invoice.php?id=<?php echo $inv['invoice_id']; ?>" 
                                       class="btn btn-sm btn-info" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-sm btn-success accept-btn" 
                                            data-id="<?php echo $inv['invoice_id']; ?>"
                                            data-number="<?php echo $inv['invoice_number']; ?>">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button class="btn btn-sm btn-danger reject-btn" 
                                            data-id="<?php echo $inv['invoice_id']; ?>"
                                            data-number="<?php echo $inv['invoice_number']; ?>">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                    <h5>All Caught Up!</h5>
                    <p>No invoices awaiting your acceptance</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="acceptForm" method="POST" style="display: none;">
    <input type="hidden" name="invoice_id" id="acceptInvoiceId">
    <input type="hidden" name="action" value="accept">
</form>

<form id="rejectForm" method="POST" style="display: none;">
    <input type="hidden" name="invoice_id" id="rejectInvoiceId">
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="remarks" id="rejectRemarks">
</form>
<?php include 'includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Accept button
    $('.accept-btn').on('click', function() {
        const invoiceId = $(this).data('id');
        const invoiceNumber = $(this).data('number');
        
        Swal.fire({
            title: 'Accept Invoice?',
            html: `
                <p>Invoice #: <strong>${invoiceNumber}</strong></p>
                <p>Have you received the physical invoice?</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#11998e',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: '<i class="fas fa-check"></i> Yes, Accept',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#acceptInvoiceId').val(invoiceId);
                $('#acceptForm').submit();
            }
        });
    });
    
    // Reject button
    $('.reject-btn').on('click', function() {
        const invoiceId = $(this).data('id');
        const invoiceNumber = $(this).data('number');
        
        Swal.fire({
            title: 'Reject Invoice?',
            html: `
                <p>Invoice #: <strong>${invoiceNumber}</strong></p>
                <p>This will return the invoice to the previous holder.</p>
                <textarea id="rejectReason" class="form-control mt-3" rows="3" 
                          placeholder="Reason for rejection (optional)"></textarea>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: '<i class="fas fa-times"></i> Yes, Reject',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                return document.getElementById('rejectReason').value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $('#rejectInvoiceId').val(invoiceId);
                $('#rejectRemarks').val(result.value || 'Physical invoice not received');
                $('#rejectForm').submit();
            }
        });
    });
});
</script>


