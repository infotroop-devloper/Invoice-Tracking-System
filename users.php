<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

if (!hasRole('Admin')) {
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Manage Users';
$database = new Database();
$db = $database->connect();

$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role_id = (int)$_POST['role_id'];
        
        // Check if username exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = 'Username already exists';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, email, phone, role_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role_id])) {
                $success = 'User added successfully';
            } else {
                $error = 'Error adding user';
            }
        }
    } elseif ($action == 'edit') {
        $user_id = (int)$_POST['user_id'];
        $username = sanitize($_POST['username']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role_id = (int)$_POST['role_id'];
        
        // Check if username exists for other users
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $user_id]);
        
        if ($stmt->fetch()) {
            $error = 'Username already exists';
        } else {
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?, phone = ?, role_id = ?
                WHERE user_id = ?
            ");
            
            if ($stmt->execute([$username, $full_name, $email, $phone, $role_id, $user_id])) {
                $success = 'User updated successfully';
            } else {
                $error = 'Error updating user';
            }
        }
    } elseif ($action == 'reset_password') {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = 'Password reset successfully';
        } else {
            $error = 'Error resetting password';
        }
    } elseif ($action == 'toggle_status') {
        $user_id = (int)$_POST['user_id'];
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $success = 'User status updated';
    } elseif ($action == 'delete') {
        $user_id = (int)$_POST['user_id'];
        
        // Check if user has invoices
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE current_holder_id = ? OR created_by = ?");
        $stmt->execute([$user_id, $user_id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $error = 'Cannot delete user with associated invoices. Deactivate instead.';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $success = 'User deleted successfully';
            } else {
                $error = 'Error deleting user';
            }
        }
    }
}

// Get all users
$users = $db->query("
    SELECT u.*, r.role_name 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.created_at DESC
")->fetchAll();

// Get roles
$roles = $db->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
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
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card animate__animated animate__fadeInLeft">
            <div class="card-header">
                <i class="fas fa-user-plus"></i> Add New User
            </div>
            <div class="card-body">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>">
                                <?php echo $role['role_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Add User
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card animate__animated animate__fadeInRight">
            <div class="card-header">
                <i class="fas fa-users"></i> All Users
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo $user['username']; ?></strong></td>
                                <td><?php echo $user['full_name']; ?></td>
                                <td><span class="badge bg-primary"><?php echo $user['role_name']; ?></span></td>
                                <td><?php echo $user['email'] ?: '-'; ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-info edit-user-btn" 
                                            data-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>"
                                            data-fullname="<?php echo $user['full_name']; ?>"
                                            data-email="<?php echo $user['email']; ?>"
                                            data-phone="<?php echo $user['phone']; ?>"
                                            data-role="<?php echo $user['role_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-warning reset-password-btn" 
                                            data-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-toggle-on"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-danger delete-user-btn" 
                                            data-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo $user['username']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="edit_fullname" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" id="edit_phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role_id" id="edit_role" required>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>">
                                <?php echo $role['role_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <p>Reset password for: <strong id="reset_username_display"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden delete form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
$(document).ready(function() {
    // Edit user
    $('.edit-user-btn').on('click', function() {
        $('#edit_user_id').val($(this).data('id'));
        $('#edit_username').val($(this).data('username'));
        $('#edit_fullname').val($(this).data('fullname'));
        $('#edit_email').val($(this).data('email'));
        $('#edit_phone').val($(this).data('phone'));
        $('#edit_role').val($(this).data('role'));
        $('#editUserModal').modal('show');
    });
    
    // Reset password
    $('.reset-password-btn').on('click', function() {
        $('#reset_user_id').val($(this).data('id'));
        $('#reset_username_display').text($(this).data('username'));
        $('#resetPasswordModal').modal('show');
    });
    
    // Delete user
    $('.delete-user-btn').on('click', function() {
        const userId = $(this).data('id');
        const username = $(this).data('username');
        
        Swal.fire({
            title: 'Delete User?',
            html: `Are you sure you want to delete user: <strong>${username}</strong>?<br><br>
                   <span class="text-danger">This action cannot be undone!</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#delete_user_id').val(userId);
                $('#deleteForm').submit();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
