<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'Add Invoice';
$database = new Database();
$db = $database->connect();

$success = '';
$error = '';

// Get projects
$projects = $db->query("SELECT * FROM projects WHERE is_active = 1 ORDER BY project_name")->fetchAll();

// Get first stage
$first_stage = $db->query("SELECT * FROM invoice_stages ORDER BY stage_order ASC LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $invoice_number = sanitize($_POST['invoice_number']);
    $vendor_name = sanitize($_POST['vendor_name']);
    $project_id = (int)$_POST['project_id'];
    $invoice_date = $_POST['invoice_date'];
    $received_date = $_POST['received_date'];
    $invoice_amount = $_POST['invoice_amount'];
    $remarks = sanitize($_POST['remarks']);
    
    // Check if invoice number already exists
    $stmt = $db->prepare("SELECT invoice_id FROM invoices WHERE invoice_number = ?");
    $stmt->execute([$invoice_number]);
    
    if ($stmt->fetch()) {
        $error = 'Invoice number already exists!';
    } else {
        // Handle file upload
        $document_path = null;
        if (isset($_FILES['invoice_document']) && $_FILES['invoice_document']['error'] == 0) {
            $file = $_FILES['invoice_document'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, ALLOWED_EXTENSIONS) && $file['size'] <= MAX_FILE_SIZE) {
                $new_filename = generateUniqueFilename($file['name']);
                $upload_path = UPLOAD_DIR . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $document_path = 'uploads/invoices/' . $new_filename;
                }
            } else {
                $error = 'Invalid file type or file too large (max 5MB)';
            }
        }
        
        if (!$error) {
            try {
                $db->beginTransaction();
                
                // Insert invoice
                $stmt = $db->prepare("
                    INSERT INTO invoices (
                        invoice_number, vendor_name, project_id, invoice_date, 
                        received_date, invoice_amount, document_path, 
                        current_stage_id, current_holder_id, created_by, is_acknowledged
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $invoice_number,
                    $vendor_name,
                    $project_id,
                    $invoice_date,
                    $received_date,
                    $invoice_amount,
                    $document_path,
                    $first_stage['stage_id'],
                    $_SESSION['user_id'],
                    $_SESSION['user_id']
                ]);
                
                $invoice_id = $db->lastInsertId();
                
                // Insert initial movement
                $stmt = $db->prepare("
                    INSERT INTO invoice_movements (
                        invoice_id, to_stage_id, to_user_id, remarks
                    ) VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $invoice_id,
                    $first_stage['stage_id'],
                    $_SESSION['user_id'],
                    $remarks ?: 'Invoice registered'
                ]);
                
                $db->commit();
                $success = 'Invoice added successfully!';
                
                // Clear form
                $_POST = array();
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error adding invoice: ' . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-plus-circle"></i> Add New Invoice</span>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="invoice_number" 
                                   value="<?php echo $_POST['invoice_number'] ?? ''; ?>" required>
                            <div class="invalid-feedback">Please enter invoice number</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="vendor_name" 
                                   value="<?php echo $_POST['vendor_name'] ?? ''; ?>" required>
                            <div class="invalid-feedback">Please enter vendor name</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project / Site <span class="text-danger">*</span></label>
                            <select class="form-select" name="project_id" required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['project_id']; ?>"
                                    <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
                                    <?php echo $project['project_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a project</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Amount</label>
                            <input type="number" step="0.01" class="form-control" name="invoice_amount" 
                                   value="<?php echo $_POST['invoice_amount'] ?? ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="invoice_date" 
                                   value="<?php echo $_POST['invoice_date'] ?? ''; ?>" required>
                            <div class="invalid-feedback">Please select invoice date</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Received Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="received_date" 
                                   value="<?php echo $_POST['received_date'] ?? date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select received date</div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Upload Invoice Document (PDF/JPG/PNG - Max 5MB)</label>
                            <input type="file" class="form-control" name="invoice_document" 
                                   accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Accepted formats: PDF, JPG, PNG (Max 5MB)</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"><?php echo $_POST['remarks'] ?? ''; ?></textarea>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
