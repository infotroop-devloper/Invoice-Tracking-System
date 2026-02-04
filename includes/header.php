<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; echo APP_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

    <!-- Custom CSS -->
    <link href="includes\style.css" rel="stylesheet">
</head>

<body>
    <?php 
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // user not logged in — avoid running queries
    $pending_acceptance = 0;
    $my_invoices = 0;
    return;
}
    // Pending acceptance
$stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE current_holder_id = ? AND status = 'Pending Acceptance' AND is_acknowledged = 0");
$stmt->execute([$user_id]);
$pending_acceptance = $stmt->fetch()['count'];
// My current invoices
$stmt = $db->prepare("
    SELECT COUNT(*) AS count 
    FROM invoices 
    WHERE current_holder_id = ? 
      AND status = 'Active' 
      AND is_acknowledged = 1
");
$stmt->execute([$user_id]);
$my_invoices = $stmt->fetch()['count'];
    ?>
    <?php if (isLoggedIn()): ?>
    <!-- Sidebar -->
    <div class="sidebar animate__animated animate__slideInLeft" id="sidebar">
        <div class="logo">
            <i class="fas fa-file-invoice"></i>
            <span>Invoice Tracker</span>
        </div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"
                href="dashboard.php">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>

            <?php if (hasRole('Admin') || hasRole('Store Manager')|| hasRole('HO Reception')): ?>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_invoice.php' ? 'active' : ''; ?>"
                href="add_invoice.php">
                <i class="fas fa-plus-circle"></i> Add Invoice
            </a>
            <?php endif; ?>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_invoices.php' ? 'active' : ''; ?>"
                href="my_invoices.php">
                <i class="fas fa-inbox"></i> My Invoices
                                <?php if ($my_invoices > 0): ?>
                <span class="notification-message">
                    <?php echo ($my_invoices > 99) ? '99+' : $my_invoices; ?>
                </span>
                <?php endif; ?>
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pending_acceptance.php' ? 'active' : ''; ?>"
                href="pending_acceptance.php">
                <i class="fas fa-clock"></i> Pending Acceptance
                <?php if ($pending_acceptance > 0): ?>
                <span class="notification-message">
                    <?php echo ($pending_acceptance > 99) ? '99+' : $pending_acceptance; ?>
                </span>
                <?php endif; ?>
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'all_invoices.php' ? 'active' : ''; ?>"
                href="all_invoices.php">
                <i class="fas fa-list"></i> All Invoices
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'forwarded_invoices.php' ? 'active' : ''; ?>"
                href="forwarded_invoices.php">
                <i class="fas fa-paper-plane"></i> Forwarded
            </a>

             <?php if (!hasRole('Accounts')): ?>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bulk_handover.php' ? 'active' : ''; ?>"
                href="bulk_handover.php">
                <i class="fas fa-layer-group"></i> Bulk Handover
            </a>
            <?php endif; ?>

            <?php if (hasRole('Admin')): ?>
            <div class="nav-section-title">Administration</div>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>"
                href="users.php">
                <i class="fas fa-users"></i> Manage Users
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>"
                href="projects.php">
                <i class="fas fa-project-diagram"></i> Manage Projects
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>"
                href="reports.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>

            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_handover.php' ? 'active' : ''; ?>"
                href="admin_handover.php">
                <i class="fas fa-user-shield"></i> Emergency Handover
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="mobile-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="page-title ms-3"><?php echo $page_title ?? 'Dashboard'; ?></h5>
            </div>
            <div class="topbar-actions">
                <div class="dropdown">
                    <button class="notification-btn" data-bs-toggle="dropdown">
                        <i class="far fa-bell fa-lg"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-3" style="width: 300px;">
                        <li>
                            <h6 class="dropdown-header">Notifications</h6>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-clock text-warning"></i> 3 invoices
                                pending > 2 days</a></li>
                        <li><a class="dropdown-item small" href="#"><i class="fas fa-inbox text-info"></i> 5 new
                                invoices assigned</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <div class="user-info" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <p class="user-name"><?php echo $_SESSION['full_name']; ?></p>
                            <p class="user-role"><?php echo $_SESSION['role_name']; ?></p>
                        </div>
                        <i class="fas fa-chevron-down ms-2"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i
                                    class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="content-wrapper">
            <?php endif; ?>