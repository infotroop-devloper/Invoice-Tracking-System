<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$page_title = 'Dashboard';
$database = new Database();
$db = $database->connect();

// Get statistics
$user_id = $_SESSION['user_id'];
$role_name = $_SESSION['role_name'];

// My current invoices
$stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE current_holder_id = ? AND status = 'Active' AND is_acknowledged = 1");
$stmt->execute([$user_id]);
$my_invoices = $stmt->fetch()['count'];

// Pending acceptance
$stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE current_holder_id = ? AND status = 'Pending Acceptance' AND is_acknowledged = 0");
$stmt->execute([$user_id]);
$pending_acceptance = $stmt->fetch()['count'];

// Invoices I forwarded
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT invoice_id) as count 
    FROM invoice_movements 
    WHERE from_user_id = ? 
    AND movement_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$forwarded_invoices = $stmt->fetch()['count'];

// Pending invoices (with me for more than 2 days)
$stmt = $db->prepare("
    SELECT COUNT(*) AS count
    FROM invoices i

    JOIN invoice_movements im
        ON im.invoice_id = i.invoice_id
        AND im.movement_date = (
            SELECT MAX(m2.movement_date)
            FROM invoice_movements m2
            WHERE m2.invoice_id = i.invoice_id
        )

    WHERE i.current_holder_id = ?
      AND i.status = 'Active'
      AND i.is_acknowledged = 1
      AND DATEDIFF(NOW(), im.movement_date) >= 2
");

$stmt->execute([$user_id]);
$pending_invoices = $stmt->fetch()['count'];

// Total active invoices (for admin)
$stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'Active'");
$total_active = $stmt->fetch()['count'];

// Stage-wise count
$stage_stats = $db->query("
    SELECT s.stage_name, COUNT(i.invoice_id) as count
    FROM invoice_stages s
    LEFT JOIN invoices i ON s.stage_id = i.current_stage_id AND i.status = 'Active'
    GROUP BY s.stage_id, s.stage_name
    ORDER BY s.stage_order
")->fetchAll();

// Monthly trend data (last 6 months)
$monthly_trend = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        COUNT(*) as count
    FROM invoices
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at
")->fetchAll();

// Status distribution
$status_dist = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM invoices
    GROUP BY status
")->fetchAll();

// Average processing time by stage
$stage_processing = $db->query("
    SELECT 
        s.stage_name,
        ROUND(AVG(TIMESTAMPDIFF(HOUR, im.movement_date, 
            COALESCE((SELECT movement_date FROM invoice_movements 
                     WHERE invoice_id = im.invoice_id AND movement_id > im.movement_id 
                     ORDER BY movement_id LIMIT 1), NOW())
        )), 1) as avg_hours
    FROM invoice_stages s
    LEFT JOIN invoice_movements im ON s.stage_id = im.to_stage_id
    WHERE im.movement_id IS NOT NULL
    GROUP BY s.stage_id, s.stage_name
    ORDER BY s.stage_order
")->fetchAll();

// Recent invoices assigned to me
$recent_invoices = $db->prepare("
    SELECT 
        i.*,
        p.project_name,
        s.stage_name,
        u.full_name as current_holder_name,
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
    WHERE i.current_holder_id = ? AND i.status = 'Active' AND i.is_acknowledged = 1
    ORDER BY i.updated_at DESC
    LIMIT 5
");
$recent_invoices->execute([$user_id]);
$recent_invoices = $recent_invoices->fetchAll();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card primary animate__animated animate__fadeInUp">
            <i class="fas fa-inbox icon text-primary"></i>
            <div class="number" style="color: #667eea;"><?php echo $my_invoices; ?></div>
            <div class="label">My Active Invoices</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card warning animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <i class="fas fa-clock icon text-warning"></i>
            <div class="number" style="color: #ffc107;"><?php echo $pending_acceptance; ?></div>
            <div class="label">Pending Acceptance</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card success animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <i class="fas fa-paper-plane icon text-success"></i>
            <div class="number" style="color: green;"><?php echo $forwarded_invoices; ?></div>
            <div class="label">Forwarded (7 Days)</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card danger animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <i class="fas fa-exclamation-triangle icon text-danger"></i>
            <div class="number" style="color: red;"><?php echo $pending_invoices; ?></div>
            <div class="label">Pending > 2 Days</div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <span><i class="fas fa-chart-line"></i> Invoice Trend (Last 6 Months)</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="card-header">
                <span><i class="fas fa-chart-pie"></i> Status Distribution</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 300px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <span><i class="fas fa-chart-bar"></i> Stage-wise Distribution</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="stageChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="card-header">
                <span><i class="fas fa-hourglass-half"></i> Avg Processing Time (Hours)</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="processingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-header">
                <span><i class="fas fa-inbox"></i> My Current Invoices</span>
                <?php if ($pending_acceptance > 0): ?>
                <a href="pending_acceptance.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-clock"></i> <?php echo $pending_acceptance; ?> Awaiting Acceptance
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($recent_invoices) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Vendor</th>
                                <th>Project</th>
                                <th>Stage</th>
                                <th>Days</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_invoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                                <td><?php echo $inv['vendor_name']; ?></td>
                                <td><?php echo $inv['project_name']; ?></td>
                                <td><span class="badge badge-stage"><?php echo $inv['stage_name']; ?></span></td>
                                <td>
                                    <?php if ($inv['days_pending'] >= 2): ?>
                                        <span class="badge bg-danger"><?php echo $inv['days_pending']; ?> days</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?php echo $inv['days_pending']; ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_invoice.php?id=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="handover_invoice.php?id=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-hand-holding"></i> Handover
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="my_invoices.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> View All My Invoices
                    </a>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                    <p>No invoices currently assigned to you</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
// Trend Chart (Line Chart)
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthly_trend, 'month')); ?>,
        datasets: [{
            label: 'Invoices Created',
            data: <?php echo json_encode(array_column($monthly_trend, 'count')); ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#667eea',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(44, 62, 80, 0.9)',
                padding: 12,
                borderRadius: 8,
                titleFont: {
                    size: 14
                },
                bodyFont: {
                    size: 13
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [5, 5]
                },
                ticks: {
                    stepSize: 1
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Status Chart (Doughnut Chart)
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($status_dist, 'status')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($status_dist, 'count')); ?>,
            backgroundColor: [
                '#11998e',
                '#95a5a6',
                '#e74c3c',
                '#f39c12'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(44, 62, 80, 0.9)',
                padding: 12,
                borderRadius: 8
            }
        }
    }
});

// Stage Chart (Bar Chart)
const stageCtx = document.getElementById('stageChart').getContext('2d');
const stageChart = new Chart(stageCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($stage_stats, 'stage_name')); ?>,
        datasets: [{
            label: 'Invoice Count',
            data: <?php echo json_encode(array_column($stage_stats, 'count')); ?>,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
                '#4facfe',
                '#00f2fe',
                '#11998e',
                '#38ef7d'
            ],
            borderRadius: 8,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(44, 62, 80, 0.9)',
                padding: 12,
                borderRadius: 8
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [5, 5]
                },
                ticks: {
                    stepSize: 1
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    }
});

// Processing Time Chart (Horizontal Bar Chart)
const processingCtx = document.getElementById('processingChart').getContext('2d');
const processingChart = new Chart(processingCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($stage_processing, 'stage_name')); ?>,
        datasets: [{
            label: 'Avg Hours',
            data: <?php echo json_encode(array_column($stage_processing, 'avg_hours')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderRadius: 8,
            borderWidth: 0
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(44, 62, 80, 0.9)',
                padding: 12,
                borderRadius: 8,
                callbacks: {
                    label: function(context) {
                        return context.parsed.x + ' hours';
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: {
                    borderDash: [5, 5]
                }
            },
            y: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 10
                    }
                }
            }
        }
    }
});
</script>

