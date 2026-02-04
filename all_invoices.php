<?php require_once 'config/config.php'; 
require_once 'config/database.php'; 

requireLogin(); 

$page_title = 'All Invoices'; 
$database = new Database(); 
$db = $database->connect(); 

// Get filter parameters 
$filter_stage = $_GET['stage'] ?? ''; 
$filter_project = $_GET['project'] ?? ''; 
$filter_status = $_GET['status'] ?? '';
 $search = $_GET['search'] ?? ''; // Build query 
$query = " SELECT i.*, p.project_name, s.stage_name, u.full_name as current_holder_name, DATEDIFF(NOW(), ( SELECT movement_date FROM invoice_movements WHERE invoice_id = i.invoice_id ORDER BY movement_id DESC LIMIT 1 )) as days_pending FROM invoices i JOIN projects p ON i.project_id = p.project_id JOIN invoice_stages s ON i.current_stage_id = s.stage_id JOIN users u ON i.current_holder_id = u.user_id WHERE 1=1 ";
$params = []; 
if ($filter_stage) { $query .= " AND i.current_stage_id = ?"; $params[] = $filter_stage; } 
if ($filter_project) { $query .= " AND i.project_id = ?"; $params[] = $filter_project; } 
if ($filter_status) { $query .= " AND i.status = ?"; $params[] = $filter_status; } 
if ($search) { $query .= " AND (i.invoice_number LIKE ? OR i.vendor_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; } 

$query .= " ORDER BY i.updated_at DESC"; $stmt = 
$db->prepare($query); 
$stmt->execute($params); 
$invoices = $stmt->fetchAll(); // Get stages for filter 

$stages = $db->query("SELECT * FROM invoice_stages ORDER BY stage_order")->fetchAll(); 
// Get projects for filter 
$projects = $db->query("SELECT * FROM projects WHERE is_active = 1 ORDER BY project_name")->fetchAll(); include 'includes/header.php'; ?>
<div class="card">
    <div class="card-header"> <span><i class="fas fa-list"></i> All Invoices</span> </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3"> <input type="text" class="form-control" name="search"
                    placeholder="Search invoice # or vendor" value="<?php echo htmlspecialchars($search); ?>"> </div>
            <div class="col-md-2"> <select class="form-select" name="stage">
                    <option value="">All Stages</option> <?php foreach ($stages as $stage): ?> <option
                        value="<?php echo $stage['stage_id']; ?>"
                        <?php echo $filter_stage == $stage['stage_id'] ? 'selected' : ''; ?>>
                        <?php echo $stage['stage_name']; ?> </option> <?php endforeach; ?>
                </select> </div>
            <div class="col-md-2"> <select class="form-select" name="project">
                    <option value="">All Projects</option> <?php foreach ($projects as $project): ?> <option
                        value="<?php echo $project['project_id']; ?>"
                        <?php echo $filter_project == $project['project_id'] ? 'selected' : ''; ?>>
                        <?php echo $project['project_name']; ?> </option> <?php endforeach; ?>
                </select> </div>
            <div class="col-md-2"> <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Closed" <?php echo $filter_status == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="Pending Acceptance"
                        <?php echo $filter_status == 'Pending Acceptance' ? 'selected' : ''; ?>>Pending Acceptance
                    </option>
                    <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected
                    </option>
                </select> </div>
            <div class="col-md-3"> <button type="submit" class="btn btn-primary"> <i class="fas fa-filter"></i> Filter
                </button> <a href="all_invoices.php" class="btn btn-secondary"> <i class="fas fa-redo"></i> Reset </a>
            </div>
        </form> <?php if (count($invoices) > 0): ?> <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Vendor</th>
                        <th>Project</th>
                        <th>Stage</th>
                        <th>Current Holder</th>
                        <th>Amount</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody> <?php foreach ($invoices as $inv): ?> <tr>
                        <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                        <td><?php echo $inv['vendor_name']; ?></td>
                        <td><?php echo $inv['project_name']; ?></td>
                        <td><span class="badge badge-stage bg-info"><?php echo $inv['stage_name']; ?></span></td>
                        <td><?php echo $inv['current_holder_name']; ?></td>
                        <td> <?php if ($inv['invoice_amount']): ?>
                            ₹<?php echo number_format($inv['invoice_amount'], 2); ?> <?php else: ?> <span
                                class="text-muted">-</span> <?php endif; ?> </td>
                        <td> <?php if ($inv['days_pending'] >= 3): ?> <span
                                class="badge bg-danger"><?php echo $inv['days_pending']; ?></span>
                            <?php elseif ($inv['days_pending'] >= 2): ?> <span
                                class="badge bg-warning"><?php echo $inv['days_pending']; ?></span> <?php else: ?> <span
                                class="badge bg-success"><?php echo $inv['days_pending']; ?></span> <?php endif; ?>
                        </td>
                        <td> <?php if ($inv['status'] == 'Active'): ?> <span class="badge bg-success">Active</span>
                            <?php elseif ($inv['status'] == 'Closed'): ?> <span class="badge bg-secondary">Closed</span>
                            <?php elseif ($inv['status'] == 'Pending Acceptance'): ?> <span
                                class="badge bg-warning">Pending Acceptance</span> <?php else: ?> <span
                                class="badge bg-danger">Rejected</span> <?php endif; ?> </td>
                        <td class="table-actions"> <a href="view_invoice.php?id=<?php echo $inv['invoice_id']; ?>"
                                class="btn btn-sm btn-primary" title="View Details"> <i class="fas fa-eye"></i> </a>
                        </td>
                    </tr> <?php endforeach; ?> </tbody>
            </table>
        </div> <?php else: ?> <div class="text-center py-5 text-muted"> <i class="fas fa-search fa-3x mb-3"></i>
            <p>No invoices found</p>
        </div> <?php endif; ?>
    </div>
</div> <?php include 'includes/footer.php'; ?>