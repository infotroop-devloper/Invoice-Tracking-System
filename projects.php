<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

if (!hasRole('Admin')) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Manage Projects';
$database = new Database();
$db = $database->connect();

$error = '';
$success = '';

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $project_name = sanitize($_POST['project_name']);
        $project_code = sanitize($_POST['project_code']);
        $location = sanitize($_POST['location']);
        
        $stmt = $db->prepare("SELECT project_id FROM projects WHERE project_name = ?");
        $stmt->execute([$project_name]);
        
        if ($stmt->fetch()) {
            $error = 'Project name already exists';
        } else {
            $stmt = $db->prepare("
                INSERT INTO projects (project_name, project_code, location)
                VALUES (?, ?, ?)
            ");
            
            if ($stmt->execute([$project_name, $project_code, $location])) {
                $success = 'Project added successfully';
            } else {
                $error = 'Error adding project';
            }
        }
    } elseif ($action == 'toggle_status') {
        $project_id = (int)$_POST['project_id'];
        $stmt = $db->prepare("UPDATE projects SET is_active = NOT is_active WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $success = 'Project status updated';
    }
}

// Get all projects
$projects = $db->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i> Add New Project
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

                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Project Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="project_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Project Code</label>
                        <input type="text" class="form-control" name="project_code">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Add Project
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-project-diagram"></i> All Projects
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Code</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><strong><?php echo $project['project_name']; ?></strong></td>
                                <td><?php echo $project['project_code'] ?: '-'; ?></td>
                                <td><?php echo $project['location'] ?: '-'; ?></td>
                                <td>
                                    <?php if ($project['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($project['created_at']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" 
                                                onclick="return confirm('Change project status?')">
                                            <i class="fas fa-toggle-on"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
