<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

if (!hasRole('Admin')) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Reports';
$database = new Database();
$db = $database->connect();

// Get report type
$report_type = $_GET['type'] ?? 'stage_wise';
$export = $_GET['export'] ?? false;

$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'stage_wise':
        $report_title = 'Stage-wise Invoice Report';
        $report_data = $db->query("
            SELECT 
                s.stage_name,
                COUNT(i.invoice_id) as total_invoices,
                SUM(CASE WHEN i.status = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN i.status = 'Closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN i.status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                ROUND(AVG(DATEDIFF(NOW(), (
                    SELECT movement_date 
                    FROM invoice_movements 
                    WHERE invoice_id = i.invoice_id 
                    ORDER BY movement_id DESC LIMIT 1
                ))), 2) as avg_days_pending
            FROM invoice_stages s
            LEFT JOIN invoices i ON s.stage_id = i.current_stage_id
            GROUP BY s.stage_id, s.stage_name
            ORDER BY s.stage_order
        ")->fetchAll();
        break;
        
    case 'user_wise':
        $report_title = 'User-wise Handling Report';
        $report_data = $db->query("
            SELECT 
                u.full_name,
                r.role_name,
                COUNT(DISTINCT im.invoice_id) as total_handled,
                COUNT(CASE WHEN i.current_holder_id = u.user_id THEN 1 END) as current_holding,
                ROUND(AVG(TIMESTAMPDIFF(HOUR, im.movement_date, 
                    COALESCE((SELECT movement_date FROM invoice_movements 
                              WHERE invoice_id = im.invoice_id 
                              AND movement_id > im.movement_id 
                              ORDER BY movement_id LIMIT 1), NOW())
                )), 2) as avg_hours_held
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN invoice_movements im ON u.user_id = im.to_user_id
            LEFT JOIN invoices i ON im.invoice_id = i.invoice_id
            WHERE u.is_active = 1
            GROUP BY u.user_id, u.full_name, r.role_name
            ORDER BY total_handled DESC
        ")->fetchAll();
        break;
        
    case 'project_wise':
        $report_title = 'Project-wise Invoice Report';
        $report_data = $db->query("
            SELECT 
                p.project_name,
                p.location,
                COUNT(i.invoice_id) as total_invoices,
                SUM(CASE WHEN i.status = 'Active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN i.status = 'Closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN i.status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(i.invoice_amount) as total_amount
            FROM projects p
            LEFT JOIN invoices i ON p.project_id = i.project_id
            GROUP BY p.project_id, p.project_name, p.location
            ORDER BY total_invoices DESC
        ")->fetchAll();
        break;
        
    case 'pending':
        $report_title = 'Pending Invoices Report (> 2 Days)';
        $report_data = $db->query("
            SELECT 
                i.invoice_number,
                i.vendor_name,
                p.project_name,
                s.stage_name,
                u.full_name as current_holder,
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
            WHERE i.status = 'Active'
            HAVING days_pending >= 2
            ORDER BY days_pending DESC
        ")->fetchAll();
        break;
}

// Export to CSV
if ($export && count($report_data) > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, array_keys($report_data[0]));
    
    // Data
    foreach ($report_data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-bar"></i> Reports</span>
        <?php if (count($report_data) > 0): ?>
        <a href="?type=<?php echo $report_type; ?>&export=1" class="btn btn-sm btn-success">
            <i class="fas fa-download"></i> Export CSV
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <!-- Report Type Selection -->
        <div class="btn-group mb-4" role="group">
            <a href="?type=stage_wise" class="btn btn-outline-primary <?php echo $report_type == 'stage_wise' ? 'active' : ''; ?>">
                Stage-wise
            </a>
            <a href="?type=user_wise" class="btn btn-outline-primary <?php echo $report_type == 'user_wise' ? 'active' : ''; ?>">
                User-wise
            </a>
            <a href="?type=project_wise" class="btn btn-outline-primary <?php echo $report_type == 'project_wise' ? 'active' : ''; ?>">
                Project-wise
            </a>
            <a href="?type=pending" class="btn btn-outline-primary <?php echo $report_type == 'pending' ? 'active' : ''; ?>">
                Pending Invoices
            </a>
        </div>

        <h5><?php echo $report_title; ?></h5>
        <small class="text-muted">Generated on: <?php echo date('d-M-Y h:i A'); ?></small>
        
        <?php if (count($report_data) > 0): ?>
        <div class="table-responsive mt-3">
            <table class="table table-striped table-hover data-table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($report_data[0]) as $column): ?>
                        <th><?php echo ucwords(str_replace('_', ' ', $column)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                        <td><?php echo $value ?? '-'; ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-chart-bar fa-3x mb-3"></i>
            <p>No data available for this report</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
