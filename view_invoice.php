<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$invoice_id = (int)$_GET['id'];
$page_title = 'Invoice Details';
$database = new Database();
$db = $database->connect();

// Get invoice details
$stmt = $db->prepare("
    SELECT 
        i.*,
        p.project_name, p.location,
        s.stage_name,
        u.full_name as current_holder_name,
        creator.full_name as created_by_name
    FROM invoices i
    JOIN projects p ON i.project_id = p.project_id
    JOIN invoice_stages s ON i.current_stage_id = s.stage_id
    JOIN users u ON i.current_holder_id = u.user_id
    JOIN users creator ON i.created_by = creator.user_id
    WHERE i.invoice_id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: dashboard.php');
    exit();
}

// Get movement history
$movements = $db->prepare("
    SELECT 
        im.*,
        fs.stage_name as from_stage,
        ts.stage_name as to_stage,
        fu.full_name as from_user,
        tu.full_name as to_user,
        TIMESTAMPDIFF(HOUR, 
            LAG(im.movement_date) OVER (ORDER BY im.movement_id), 
            im.movement_date
        ) as hours_held
    FROM invoice_movements im
    LEFT JOIN invoice_stages fs ON im.from_stage_id = fs.stage_id
    JOIN invoice_stages ts ON im.to_stage_id = ts.stage_id
    LEFT JOIN users fu ON im.from_user_id = fu.user_id
    JOIN users tu ON im.to_user_id = tu.user_id
    WHERE im.invoice_id = ?
    ORDER BY im.movement_id DESC
");
$movements->execute([$invoice_id]);
$movements = $movements->fetchAll();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-invoice"></i> Invoice Details</span>
                <div>
                    <?php if ($invoice['status'] == 'Active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php elseif ($invoice['status'] == 'Closed'): ?>
                        <span class="badge bg-secondary">Closed</span>
                    <?php elseif ($invoice['status'] == 'Pending Acceptance'): ?>
                        <span class="badge bg-warning">Pending Acceptance</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Rejected</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Invoice Number:</strong>
                        <p class="text-primary fs-5"><?php echo $invoice['invoice_number']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Current Stage:</strong>
                        <p><span class="badge badge-stage bg-info fs-6"><?php echo $invoice['stage_name']; ?></span></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Vendor Name:</strong>
                        <p><?php echo $invoice['vendor_name']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Project / Site:</strong>
                        <p><?php echo $invoice['project_name']; ?>
                        <?php if ($invoice['location']): ?>
                            <br><small class="text-muted"><?php echo $invoice['location']; ?></small>
                        <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Invoice Date:</strong>
                        <p><?php echo formatDate($invoice['invoice_date']); ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>Received Date:</strong>
                        <p><?php echo formatDate($invoice['received_date']); ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>Amount:</strong>
                        <p class="text-success fs-5">
                            <?php if ($invoice['invoice_amount']): ?>
                                ₹<?php echo number_format($invoice['invoice_amount'], 2); ?>
                            <?php else: ?>
                                <span class="text-muted">Not specified</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Current Holder:</strong>
                        <p><?php echo $invoice['current_holder_name']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Created By:</strong>
                        <p><?php echo $invoice['created_by_name']; ?></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Created On:</strong>
                        <p><?php echo formatDateTime($invoice['created_at']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Last Updated:</strong>
                        <p><?php echo formatDateTime($invoice['updated_at']); ?></p>
                    </div>
                </div>

                <?php if ($invoice['document_path']): ?>
                <div class="mb-3">
                    <strong>Attached Document:</strong>
                    <p>
                        <a href="<?php echo $invoice['document_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-download"></i> View/Download Document
                        </a>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($invoice['current_holder_id'] == $_SESSION['user_id'] && $invoice['status'] == 'Active'): ?>
                <div class="mt-4 pt-3 border-top">
                    <a href="handover_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-success">
                        <i class="fas fa-hand-holding"></i> Hand Over Invoice
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-history"></i> Movement History</span>
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <div class="timeline">
                    <?php foreach ($movements as $index => $movement): ?>
                    <div class="timeline-item mb-4">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                     style="width: 40px; height: 40px;">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="card shadow-sm">
                                    <div class="card-body p-3">
                                        <?php if ($movement['from_stage']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-arrow-right"></i> 
                                            <?php echo $movement['from_stage']; ?> → <?php echo $movement['to_stage']; ?>
                                        </small>
                                        <?php else: ?>
                                        <small class="text-muted">
                                            <i class="fas fa-plus-circle"></i> 
                                            Invoice Created at <?php echo $movement['to_stage']; ?>
                                        </small>
                                        <?php endif; ?>
                                        
                                        <p class="mb-1 mt-2">
                                            <small class="text-muted"><?php echo $movement['from_user']; ?> to</small>
                                            <strong><?php echo $movement['to_user']; ?></strong>
                                            <?php if ($movement['from_user']): ?>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <small class="text-muted">
                                            <i class="far fa-clock"></i> <?php echo formatDateTime($movement['movement_date']); ?>
                                        </small>
                                        
                                        <?php if ($movement['hours_held']): ?>
                                        <small class="text-info d-block">
                                            <i class="fas fa-hourglass-half"></i> 
                                            Held for <?php echo $movement['hours_held']; ?> hours
                                        </small>
                                        <?php endif; ?>
                                        
                                        <?php if ($movement['remarks']): ?>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <small><?php echo nl2br(htmlspecialchars($movement['remarks'])); ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="javascript:history.back()" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php include 'includes/footer.php'; ?>
