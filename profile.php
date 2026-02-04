<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'My Profile';
$database = new Database();
$db = $database->connect();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            
            if ($stmt->execute([$hashed_password, $user_id])) {
                $success = 'Password changed successfully';
            } else {
                $error = 'Error updating password';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Get user details
$stmt = $db->prepare("
    SELECT u.*, r.role_name 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user"></i> Profile Information
            </div>
            <div class="card-body text-center">
                <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 40px;">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h4><?php echo $user['full_name']; ?></h4>
                <p class="text-muted"><?php echo $user['role_name']; ?></p>
                <hr>
                <div class="text-start">
                    <p><strong>Username:</strong> <?php echo $user['username']; ?></p>
                    <p><strong>Email:</strong> <?php echo $user['email'] ?: '-'; ?></p>
                    <p><strong>Phone:</strong> <?php echo $user['phone'] ?: '-'; ?></p>
                    <p><strong>Member Since:</strong> <?php echo formatDate($user['created_at']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-lock"></i> Change Password
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
                    <div class="mb-3">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> My Statistics
            </div>
            <div class="card-body">
                <?php
// Get user statistics
$stats = $db->prepare("
    SELECT 
        COUNT(DISTINCT im.invoice_id) AS total_handled,

        COUNT(DISTINCT CASE 
            WHEN i.current_holder_id = ? THEN i.invoice_id 
        END) AS current_holding,

        COUNT(DISTINCT CASE 
            WHEN i.status = 'Closed' THEN i.invoice_id 
        END) AS closed_invoices

    FROM invoice_movements im
    INNER JOIN invoices i ON im.invoice_id = i.invoice_id
    WHERE im.to_user_id = ?
");

$stats->execute([
    $user_id, // i.current_holder_id
    $user_id  // im.to_user_id
]);

$stats = $stats->fetch(PDO::FETCH_ASSOC);
?>

                
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-clipboard-list icon text-primary"></i>
                            <div class="number"><?php echo $stats['total_handled']; ?></div>
                            <div class="label">Total Handled</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-inbox icon text-warning"></i>
                            <div class="number"><?php echo $stats['current_holding']; ?></div>
                            <div class="label">Currently Holding</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-check-circle icon text-success"></i>
                            <div class="number"><?php echo $stats['closed_invoices']; ?></div>
                            <div class="label">Closed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
