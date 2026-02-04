<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'Bulk Handover';
$database = new Database();
$db = $database->connect();

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$success = '';
$error = '';

// Handle bulk handover
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['invoice_ids'])) {
    $invoice_ids = $_POST['invoice_ids'];
    $to_user_id = (int)$_POST['to_user_id'];
    $remarks = sanitize($_POST['remarks']);
    
    if (empty($invoice_ids)) {
        $error = 'No invoices selected';
    } elseif (!$to_user_id) {
        $error = 'Please select a user to hand over to';
    } elseif (!$remarks) {
        $error = 'Remarks are mandatory';
    } else {
        try {
            $db->beginTransaction();
            
            $success_count = 0;
            $errors = [];
            
            foreach ($invoice_ids as $invoice_id) {
                $invoice_id = (int)$invoice_id;
                
                // Get invoice details with current stage
                $stmt = $db->prepare("
                    SELECT i.*, s.stage_name, s.next_role_id, s.stage_order
                    FROM invoices i
                    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
                    WHERE i.invoice_id = ? AND i.current_holder_id = ? AND i.status = 'Active' AND i.is_acknowledged = 1
                ");
                $stmt->execute([$invoice_id, $user_id]);
                $invoice = $stmt->fetch();
                
                if (!$invoice) {
                    $errors[] = "Invoice ID {$invoice_id} not found or not available for handover";
                    continue;
                }
                
                // Get next stage
                $stmt = $db->prepare("
                    SELECT stage_id, stage_name, next_role_id
                    FROM invoice_stages
                    WHERE stage_order > ?
                    ORDER BY stage_order ASC
                    LIMIT 1
                ");
                $stmt->execute([$invoice['stage_order']]);
                $next_stage = $stmt->fetch();
                
                if (!$next_stage) {
                    $errors[] = "Invoice #{$invoice['invoice_number']} - No next stage available";
                    continue;
                }
                
                // Verify the selected user belongs to the next stage role
                $stmt = $db->prepare("
                    SELECT 1
                    FROM users
                    WHERE user_id = ?
                      AND role_id = ?
                      AND is_active = 1
                ");
                $stmt->execute([$to_user_id, $next_stage['next_role_id']]);
                
                if (!$stmt->fetch()) {
                    $errors[] = "Invalid user selected for next stage";
                    continue;
                }
                
                // Update invoice - set to Pending Acceptance
                $stmt = $db->prepare("
                    UPDATE invoices 
                    SET current_stage_id = ?, current_holder_id = ?, 
                        status = 'Pending Acceptance', is_acknowledged = 0, updated_at = NOW()
                    WHERE invoice_id = ?
                ");
                $stmt->execute([$next_stage['stage_id'], $to_user_id, $invoice_id]);
                
                // Log movement
                $stmt = $db->prepare("
                    INSERT INTO invoice_movements (
                        invoice_id, from_stage_id, to_stage_id, 
                        from_user_id, to_user_id, remarks, is_acknowledged
                    ) VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $invoice_id,
                    $invoice['current_stage_id'],
                    $next_stage['stage_id'],
                    $user_id,
                    $to_user_id,
                    $remarks
                ]);
                
                // Create notification
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, invoice_id, notification_type, message)
                    VALUES (?, ?, 'Assignment', ?)
                ");
                $message = "Invoice #{$invoice['invoice_number']} has been assigned to you by {$_SESSION['full_name']} - Awaiting your acceptance";
                $stmt->execute([$to_user_id, $invoice_id, $message]);
                
                $success_count++;
            }
            
            $db->commit();
            
            if ($success_count > 0) {
                $success = "$success_count invoice(s) handed over successfully!";
            }
            
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error processing bulk handover: ' . $e->getMessage();
        }
    }
}

// Get current stage for user's invoices
$stmt = $db->prepare("
    SELECT s.stage_id, s.stage_order
    FROM invoice_stages s
    JOIN invoices i ON i.current_stage_id = s.stage_id
    WHERE i.current_holder_id = ?
      AND i.status = 'Active'
      AND i.is_acknowledged = 1
    GROUP BY s.stage_id, s.stage_order
    ORDER BY s.stage_order
    LIMIT 1
");
$stmt->execute([$user_id]);
$current_stage = $stmt->fetch();

// Get next stage and users
$next_stage = null;
$next_users = [];

if ($current_stage) {
    $stmt = $db->prepare("
        SELECT stage_id, stage_name, next_role_id
        FROM invoice_stages
        WHERE stage_order > ?
        ORDER BY stage_order ASC
        LIMIT 1
    ");
    $stmt->execute([$current_stage['stage_order']]);
    $next_stage = $stmt->fetch();
    
    if ($next_stage && !empty($next_stage['next_role_id'])) {
        $stmt = $db->prepare("
            SELECT user_id, full_name
            FROM users
            WHERE role_id = ?
              AND is_active = 1
              AND user_id != ?
            ORDER BY full_name
        ");
        $stmt->execute([
            $next_stage['next_role_id'],
            $user_id
        ]);
        $next_users = $stmt->fetchAll();
    }
}

// Get my invoices for selection
$stmt = $db->prepare("
    SELECT 
        i.*,
        p.project_name,
        s.stage_name,
        DATEDIFF(NOW(), (
            SELECT movement_date 
            FROM invoice_movements 
            WHERE invoice_id = i.invoice_id 
            ORDER BY movement_id DESC LIMIT 1
        )) as days_pending
    FROM invoices i
    JOIN projects p ON i.project_id = p.project_id
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    WHERE i.current_holder_id = ? AND i.status = 'Active' AND i.is_acknowledged = 1
    ORDER BY i.updated_at DESC
");
$stmt->execute([$user_id]);
$my_invoices1 = $stmt->fetchAll();
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
            <i class="fas fa-exclamation-circle"></i> <?php echo nl2br($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <span><i class="fas fa-layer-group"></i> Bulk Handover Invoices</span>
                <button class="btn btn-sm btn-success d-none" id="bulkActionsBtn">
                    <i class="fas fa-hand-holding"></i> Handover Selected (0)
                </button>
            </div>
            <div class="card-body">
               <?php if (!empty($my_invoices1)): ?>

                <?php if ($next_stage): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Next Stage:</strong> <?php echo $next_stage['stage_name']; ?>
                    <br>
                    <strong>Tip:</strong> Select multiple invoices and hand them over together.
                    All selected invoices will be sent to the same user with the same remarks.
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No next stage available. You cannot bulk handover from this stage.
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th>Invoice #</th>
                                <th>Vendor</th>
                                <th>Project</th>
                                <th>Stage</th>
                                <th>Days</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_invoices1 as $inv): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input invoice-checkbox" type="checkbox"
                                            value="<?php echo $inv['invoice_id']; ?>">
                                    </div>
                                </td>
                                <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                                <td><?php echo $inv['vendor_name']; ?></td>
                                <td><?php echo $inv['project_name']; ?></td>
                                <td><span class="badge badge-stage"><?php echo $inv['stage_name']; ?></span></td>
                                <td>
                                    <?php if ($inv['days_pending'] >= 2): ?>
                                    <span class="badge bg-danger"><?php echo $inv['days_pending']; ?> days</span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><?php echo $inv['days_pending']; ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_invoice.php?id=<?php echo $inv['invoice_id']; ?>"
                                        class="btn btn-sm btn-info" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Handover Form -->
                <?php if ($next_stage && count($next_users) > 0): ?>
                <div class="card mt-4 d-none" id="handoverForm">
                    <div class="card-header bg-success text-white">
                        <span><i class="fas fa-hand-holding" style="color:white"></i> Bulk Handover Details</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div id="selectedInvoicesInput"></div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Hand Over To <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="to_user_id" required>
                                        <option value="">Select User</option>
                                        <?php foreach ($next_users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo $user['full_name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Selected Invoices</label>
                                    <div id="selectedInvoicesList" class="form-control"
                                        style="height: auto; min-height: 38px;">
                                        <span class="text-muted">No invoices selected</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="remarks" rows="4"
                                    placeholder="Enter remarks for all selected invoices" required></textarea>
                                <div class="invalid-feedback">Remarks are mandatory</div>
                            </div>

                            <div class="text-end">
                                <button type="button" class="btn btn-secondary"
                                    onclick="$('#handoverForm').addClass('d-none'); $('.invoice-checkbox').prop('checked', false); $('#selectAll').prop('checked', false); updateBulkActions();">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane"></i> Handover Selected Invoices
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                    <p>No invoices available for bulk handover</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Select all checkbox
    $('#selectAll').on('change', function() {
        $('.invoice-checkbox').prop('checked', this.checked);
        updateBulkActions();
    });

    // Individual checkbox
    $('.invoice-checkbox').on('change', function() {
        updateBulkActions();

        // Update select all state
        const total = $('.invoice-checkbox').length;
        const checked = $('.invoice-checkbox:checked').length;
        $('#selectAll').prop('checked', total === checked);
    });

    // Bulk actions button
    $('#bulkActionsBtn').on('click', function() {
        $('#handoverForm').removeClass('d-none');
        $('html, body').animate({
            scrollTop: $("#handoverForm").offset().top - 100
        }, 500);
    });

    function updateBulkActions() {
        const checked = $('.invoice-checkbox:checked').length;

        if (checked > 0) {
            $('#bulkActionsBtn').removeClass('d-none').text(`Handover Selected (${checked})`);

            // Update selected invoices input
            let html = '';
            let list = [];
            $('.invoice-checkbox:checked').each(function() {
                html += `<input type="hidden" name="invoice_ids[]" value="${$(this).val()}">`;
                list.push($(this).closest('tr').find('strong').text());
            });
            $('#selectedInvoicesInput').html(html);
            $('#selectedInvoicesList').html(list.map(n =>
                `<span class="badge bg-primary me-1 mb-1">${n}</span>`).join(''));
        } else {
            $('#bulkActionsBtn').addClass('d-none');
            $('#selectedInvoicesInput').html('');
            $('#selectedInvoicesList').html('<span class="text-muted">No invoices selected</span>');
        }
    }
});
</script>

