<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

if (!isset($_GET['id'])) {
    header('Location: my_invoices.php');
    exit();
}

$invoice_id = (int)$_GET['id'];
$page_title = 'Hand Over Invoice';
$database = new Database();
$db = $database->connect();

$error = '';
$success = '';

// Get invoice details with current stage information
$stmt = $db->prepare("
    SELECT i.*, s.stage_name, s.next_role_id, s.stage_order
    FROM invoices i
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    WHERE i.invoice_id = ? AND i.current_holder_id = ? AND i.status = 'Active' AND i.is_acknowledged = 1
");
$stmt->execute([$invoice_id, $_SESSION['user_id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: my_invoices.php');
    exit();
}

// Check if this is the last stage - next_role_id is NULL means final stage
$is_final_stage = is_null($invoice['next_role_id']);

// Get next stage if not final
$next_stage = null;
$next_users = [];

if (!$is_final_stage) {
    $stmt = $db->prepare("
        SELECT * FROM invoice_stages 
        WHERE stage_order > ? 
        ORDER BY stage_order ASC 
        LIMIT 1
    ");
    $stmt->execute([$invoice['stage_order']]);
    $next_stage = $stmt->fetch();
    
    // Get users for next role
    if ($next_stage && $next_stage['next_role_id']) {
        $stmt = $db->prepare("
            SELECT user_id, full_name, role_id 
            FROM users 
            WHERE role_id = ? AND is_active = 1 AND user_id != ?
            ORDER BY full_name
        ");
        $stmt->execute([$next_stage['next_role_id'], $_SESSION['user_id']]);
        $next_users = $stmt->fetchAll();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action_type = $_POST['action_type'] ?? 'forward';
    $to_user_id = null;

    if ($action_type === 'forward') {
        $to_user_id = (int)($_POST['to_user_id'] ?? 0);
    }

    $remarks = sanitize($_POST['remarks']);
    $action_type = $_POST['action_type'] ?? 'forward';
    
    if (!$remarks) {
        $error = 'Remarks are mandatory for handover';
    } elseif ($action_type == 'forward' && !$to_user_id) {
        $error = 'Please select a user to hand over the invoice';
    } else {
        try {
            $db->beginTransaction();
            
            if ($action_type == 'close' || $action_type == 'reject') {
                // Close or reject invoice
                $new_status = ($action_type == 'close') ? 'Closed' : 'Rejected';
                
                // Get the appropriate final stage
                $final_stage_name = ($action_type == 'close') ? 'Approved/Cleared' : 'Rejected';
                $stmt = $db->prepare("
                    SELECT stage_id FROM invoice_stages 
                    WHERE stage_name = ?
                    LIMIT 1
                ");
                $stmt->execute([$final_stage_name]);
                $final_stage = $stmt->fetch();
                
                if (!$final_stage) {
                    throw new Exception('Final stage "' . $final_stage_name . '" not found in system');
                }
                
                // Update invoice with new status and final stage
                $stmt = $db->prepare("
                    UPDATE invoices 
                    SET status = ?, current_stage_id = ?, updated_at = NOW()
                    WHERE invoice_id = ?
                ");
                $stmt->execute([$new_status, $final_stage['stage_id'], $invoice_id]);
                
                // Log movement to final stage
                $stmt = $db->prepare("
                    INSERT INTO invoice_movements (
                        invoice_id, from_stage_id, to_stage_id, 
                        from_user_id, to_user_id, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $invoice_id,
                    $invoice['current_stage_id'],
                    $final_stage['stage_id'],
                    $_SESSION['user_id'],
                    $_SESSION['user_id'],
                    $remarks
                ]);
                
                $success = 'Invoice ' . ($action_type == 'close' ? 'closed' : 'rejected') . ' successfully';
                
            } else {
                // Forward to next stage/user
                if (!$next_stage) {
                    throw new Exception('No next stage available');
                }
                
                // Update invoice
                                $stmt = $db->prepare("
                    UPDATE invoices 
                    SET current_stage_id = ?, current_holder_id = ?, 
                        status = 'Pending Acceptance', is_acknowledged = 0, updated_at = NOW()
                    WHERE invoice_id = ?
                ");
                $stmt->execute([
                    $next_stage['stage_id'],
                    $to_user_id,
                    $invoice_id
                ]);
                
                // Log movement
                $stmt = $db->prepare("
                    INSERT INTO invoice_movements (
                        invoice_id, from_stage_id, to_stage_id, 
                        from_user_id, to_user_id, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $invoice_id,
                    $invoice['current_stage_id'],
                    $next_stage['stage_id'],
                    $_SESSION['user_id'],
                    $to_user_id,
                    $remarks
                ]);
                
                // Create notification
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, invoice_id, notification_type, message)
                    VALUES (?, ?, 'Assignment', ?)
                ");
                $message = "Invoice #{$invoice['invoice_number']} has been assigned to you by {$_SESSION['full_name']}";
                $stmt->execute([$to_user_id, $invoice_id, $message]);
                
                $success = 'Invoice handed over successfully';
            }
            
            $db->commit();
            
            // Redirect after 2 seconds
            header("Refresh: 2; URL=my_invoices.php");
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error processing handover: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>
<style>
.btn-outline-danger {
    border: 1px solid #dc3545 !important;
    color: #dc3545 !important;
}
.btn-check:checked + .btn-outline-danger {
    background-color: #dc3545 !important;
    color: #fff !important;
}
.btn-outline-success {
    border: 1px solid #198754 !important;
    color: #198754 !important;
}
.btn-check:checked + .btn-outline-success {
    color: #fff !important;
}
</style>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <span><i class="fas fa-hand-holding"></i> Hand Over Invoice</span>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success alert-permanent">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <br><small>Redirecting to My Invoices...</small>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <!-- Invoice Summary -->
                <div class="card bg-light mb-4">
                    <div class="card-body">
                        <h5>Invoice: <?php echo $invoice['invoice_number']; ?></h5>
                        <p class="mb-1"><strong>Vendor:</strong> <?php echo $invoice['vendor_name']; ?></p>
                        <p class="mb-0"><strong>Current Stage:</strong>
                            <span class="badge badge-stage"><?php echo $invoice['stage_name']; ?></span>
                        </p>
                    </div>
                </div>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Action <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">

                            <?php if (!empty($next_stage) && !empty($next_stage['next_role_id'])): ?>
                            <input type="radio" class="btn-check" name="action_type" id="action_forward" value="forward"
                                checked onchange="toggleUserSelect()">
                            <label class="btn btn-outline-primary" for="action_forward">
                                <i class="fas fa-arrow-right"></i> Forward to Next Stage
                            </label>
                            <?php endif; ?>

                            <?php if (!empty($next_stage) && empty($next_stage['next_role_id'])): ?>
                            <input type="radio" class="btn-check" name="action_type" id="action_close" value="close"
                                checked onchange="toggleUserSelect()">
                            <label class="btn btn-outline-success" for="action_close">
                                <i class="fas fa-check-circle"></i> Approve & Close
                            </label>
                            <?php endif; ?>

                            <input type="radio" class="btn-check" name="action_type" id="action_reject" value="reject"
                                onchange="toggleUserSelect()">
                            <label class="btn btn-outline-danger" for="action_reject">
                                <i class="fas fa-times-circle"></i> Reject
                            </label>

                        </div>
                    </div>
                    <?php if (!empty($next_stage) && !empty($next_stage['next_role_id'])): ?>
                    <!-- Show user selection only if not final stage -->
                    <div class="mb-3" id="user_select_group">
                        <label class="form-label">Hand Over To <span class="text-danger">*</span></label>
                        <?php if ($next_stage): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Next Stage: <strong><?php echo $next_stage['stage_name']; ?></strong>
                        </div>
                        <?php endif; ?>

                        <?php if (count($next_users) > 0): ?>
                        <select class="form-select select2" name="to_user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($next_users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo $user['full_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No active users found for next stage
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <!-- Final stage message -->
                    <div class="alert alert-warning">
                        <i class="fas fa-flag-checkered"></i>
                        <strong>Final Stage:</strong> This is the last stage
                        (<?php echo htmlspecialchars($invoice['stage_name']); ?>).
                        You can only Approve & Close or Reject the invoice.
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="remarks" rows="4"
                            placeholder="Enter mandatory remarks for this action" required></textarea>
                        <div class="invalid-feedback">Remarks are mandatory</div>
                    </div>

                    <div class="text-end">
                        <a href="my_invoices.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Submit Action
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleUserSelect() {
    const actionType = document.querySelector('input[name="action_type"]:checked');
    const userSelectGroup = document.getElementById('user_select_group');
    const userSelect = document.querySelector('select[name="to_user_id"]');

    if (userSelectGroup && actionType) {
        if (actionType.value === 'forward') {
            userSelectGroup.style.display = 'block';
            if (userSelect) userSelect.required = true;
        } else {
            userSelectGroup.style.display = 'none';
            if (userSelect) userSelect.required = false;
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleUserSelect();
});
</script>

<?php include 'includes/footer.php'; ?>