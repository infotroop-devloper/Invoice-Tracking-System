<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

// Only admin can access
if (!hasRole('Admin')) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Emergency Handover (Admin)';
$database = new Database();
$db = $database->connect();

$success = '';
$error = '';

// Handle emergency handover
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $invoice_id = (int)$_POST['invoice_id'];
    $to_user_id = (int)$_POST['to_user_id'];
    $to_stage_id = (int)$_POST['to_stage_id'];
    $remarks = sanitize($_POST['remarks']);
    
    if (!$invoice_id) {
        $error = 'Please select an invoice';
    } elseif (!$to_user_id) {
        $error = 'Please select a target user';
    } elseif (!$to_stage_id) {
        $error = 'Please select a target stage';
    } elseif (!$remarks) {
        $error = 'Emergency remarks are mandatory';
    } else {
        try {
            $db->beginTransaction();
            
            // Get current invoice details
            $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();
            
            if (!$invoice) {
                throw new Exception('Invoice not found');
            }
            
            // Admin override - Update invoice directly (bypass normal workflow)
            $stmt = $db->prepare("
                UPDATE invoices 
                SET current_stage_id = ?, current_holder_id = ?, 
                    status = 'Pending Acceptance', is_acknowledged = 0, updated_at = NOW()
                WHERE invoice_id = ?
            ");
            $stmt->execute([$to_stage_id, $to_user_id, $invoice_id]);
            
            // Log emergency movement
            $stmt = $db->prepare("
                INSERT INTO invoice_movements (
                    invoice_id, from_stage_id, to_stage_id, 
                    from_user_id, to_user_id, remarks, is_acknowledged
                ) VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            
            $emergency_remarks = "ADMIN EMERGENCY HANDOVER⚠️- " . $remarks . " - Performed by: " . $_SESSION['full_name'];
            
            $stmt->execute([
                $invoice_id,
                $invoice['current_stage_id'],
                $to_stage_id,
                $invoice['current_holder_id'],
                $to_user_id,
                $emergency_remarks
            ]);
            
            // Create notification
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, invoice_id, notification_type, message)
                VALUES (?, ?, 'Assignment', ?)
            ");
            $message = "[EMERGENCY] Invoice #{$invoice['invoice_number']} has been assigned to you by Admin - Please accept immediately";
            $stmt->execute([$to_user_id, $invoice_id, $message]);
            
            // Notify previous holder
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, invoice_id, notification_type, message)
                VALUES (?, ?, 'Escalation', ?)
            ");
            $message = "Invoice #{$invoice['invoice_number']} has been reassigned by Admin for emergency handling";
            $stmt->execute([$invoice['current_holder_id'], $invoice_id, $message]);
            
            $db->commit();
            $success = 'Emergency handover completed successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error processing emergency handover: ' . $e->getMessage();
        }
    }
}

// Get all active invoices
$all_invoices = $db->query("
    SELECT 
        i.invoice_id,
        i.invoice_number,
        i.vendor_name,
        p.project_name,
        s.stage_name,
        u.full_name as current_holder,
        i.status
    FROM invoices i
    JOIN projects p ON i.project_id = p.project_id
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    JOIN users u ON i.current_holder_id = u.user_id
    WHERE i.status IN ('Active', 'Pending Acceptance')
    ORDER BY i.updated_at DESC
")->fetchAll();

// Get all stages
$stages = $db->query("SELECT * FROM invoice_stages ORDER BY stage_order")->fetchAll();

// Get all active users
$all_users = $db->query("
    SELECT u.user_id, u.full_name, r.role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    WHERE u.is_active = 1 
    ORDER BY u.full_name
")->fetchAll();

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
    <div class="col-lg-4 mb-4">
        <div class="card animate__animated animate__fadeInLeft">
            <div class="card-header bg-danger text-white">
                <span><i class="fas fa-exclamation-triangle"  style="color: white;"></i> Emergency Handover Control</span>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-shield-alt"></i> 
                    <strong>Admin Override:</strong><br>
                    Use this feature ONLY for emergencies (absence, urgent situations, system corrections).
                    This bypasses normal workflow rules.
                </div>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Select Invoice <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="invoice_id" id="invoiceSelect" required>
                            <option value="">Choose invoice...</option>
                            <?php foreach ($all_invoices as $inv): ?>
                            <option value="<?php echo $inv['invoice_id']; ?>"
                                    data-current-holder="<?php echo $inv['current_holder']; ?>"
                                    data-current-stage="<?php echo $inv['stage_name']; ?>"
                                    data-status="<?php echo $inv['status']; ?>">
                                <?php echo $inv['invoice_number']; ?> - <?php echo $inv['vendor_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="currentInfo" class="alert alert-info d-none">
                        <strong>Current Status:</strong><br>
                        <span id="currentDetails"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target Stage <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="to_stage_id" required>
                            <option value="">Choose stage...</option>
                            <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo $stage['stage_id']; ?>">
                                <?php echo $stage['stage_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target User <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="to_user_id" required>
                            <option value="">Choose user...</option>
                            <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo $user['full_name']; ?> (<?php echo $user['role_name']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Emergency Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="remarks" rows="4" 
                                  placeholder="Explain the reason for this emergency handover (e.g., User on leave, Critical deadline, System error correction)" 
                                  required></textarea>
                        <div class="invalid-feedback">Please provide emergency reason</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-exclamation-triangle"></i> Execute Emergency Handover
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card animate__animated animate__fadeInRight">
            <div class="card-header">
                <span><i class="fas fa-list"></i> All Active Invoices</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Vendor</th>
                                <th>Project</th>
                                <th>Current Stage</th>
                                <th>Current Holder</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_invoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                                <td><?php echo $inv['vendor_name']; ?></td>
                                <td><?php echo $inv['project_name']; ?></td>
                                <td><span class="badge badge-stage"><?php echo $inv['stage_name']; ?></span></td>
                                <td><?php echo $inv['current_holder']; ?></td>
                                <td>
                                    <?php if ($inv['status'] == 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($inv['status'] == 'Pending Acceptance'): ?>
                                        <span class="badge bg-warning">Pending Acceptance</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $inv['status']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4 animate__animated animate__fadeInRight" style="animation-delay: 0.2s;">
            <div class="card-header bg-info text-white">
               <span><i class="fas fa-info-circle" style="color: white;"></i> Usage Guidelines</span>
            </div>
            <div class="card-body">
                <h6>When to Use Emergency Handover:</h6>
                <ul>
                    <li><strong>User Absence:</strong> Employee on leave/sick and urgent action needed</li>
                    <li><strong>Critical Deadlines:</strong> Invoice must move immediately to meet deadline</li>
                    <li><strong>System Corrections:</strong> Fix incorrect assignments or stuck invoices</li>
                    <li><strong>Workflow Bypasses:</strong> Skip stages in exceptional circumstances</li>
                </ul>

                <h6 class="mt-3">Important Notes:</h6>
                <ul class="mb-0">
                    <li>All emergency handovers are logged with [ADMIN EMERGENCY HANDOVER] tag</li>
                    <li>Your admin name is recorded in the audit trail</li>
                    <li>Both old and new holders receive notifications</li>
                    <li>Invoice will be in "Pending Acceptance" status</li>
                    <li>Use responsibly - this bypasses normal workflow controls</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Show current invoice details when selected
    $('#invoiceSelect').on('change', function() {
        const selected = $(this).find('option:selected');
        if ($(this).val()) {
            const holder = selected.data('current-holder');
            const stage = selected.data('current-stage');
            const status = selected.data('current-status');
            
            $('#currentDetails').html(`
                <i class="fas fa-user"></i> <strong>Holder:</strong> ${holder}<br>
                <i class="fas fa-layer-group"></i> <strong>Stage:</strong> ${stage}<br>
                <i class="fas fa-info-circle"></i> <strong>Status:</strong> ${status}
            `);
            $('#currentInfo').removeClass('d-none');
        } else {
            $('#currentInfo').addClass('d-none');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
