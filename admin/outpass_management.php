<?php
include('admin_db.php');
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();}
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['outpass_id'])) {
        $outpass_id = $_POST['outpass_id'];
        $new_status = isset($_POST['approve']) ? 'Approved' : (isset($_POST['reject']) ? 'Rejected' : '');
        if (!empty($new_status)) {
            $conn->begin_transaction();            
            try {
                $check_sql = "SELECT status FROM outpass WHERE id = ? FOR UPDATE";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $outpass_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['status'] === 'Pending') {
                        $update_sql = "UPDATE outpass SET status = ?, approved_by = ?, approval_date = NOW() WHERE id = ? AND status = 'Pending'";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ssi", $new_status, $admin_name, $outpass_id);
                        if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                            $_SESSION['message'] = "Outpass #$outpass_id has been $new_status successfully.";
                            $conn->commit();
                        } else {
                            $_SESSION['error'] = "Outpass #$outpass_id could not be updated. It may have been processed by another administrator.";
                            $conn->rollback();
                        }
                    } else {
                        $_SESSION['error'] = "Outpass #$outpass_id is already {$row['status']}.";
                        $conn->rollback();
                    }
                } else {
                    $_SESSION['error'] = "Invalid Outpass ID.";
                    $conn->rollback();
                }
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "An error occurred while processing your request: " . $e->getMessage();
            }
        }
        header("Location: outpass_management.php");
        exit();
    }
}
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
function get_status_count($conn, $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM outpass WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}
$pending_count = get_status_count($conn, 'Pending');
$approved_count = get_status_count($conn, 'Approved');
$rejected_count = get_status_count($conn, 'Rejected');
$total_stmt = $conn->prepare("SELECT COUNT(*) as count FROM outpass");
$total_stmt->execute();
$total_count = $total_stmt->get_result()->fetch_assoc()['count'];
$query = "SELECT o.*, 
        CONCAT(s.firstName, ' ', s.lastName) as student_name, 
        s.regNo as student_reg_no,
        s.email as student_email,
        sd.course, sd.year_of_study
        FROM outpass o
        INNER JOIN student_signup s ON o.student_reg_no = s.regNo
        LEFT JOIN student_details sd ON s.regNo = sd.reg_no
        WHERE 1=1";
$params = [];
$types = "";
if ($status_filter != 'all') {
    $query .= " AND o.status = ?";
    $types .= "s";
    $params[] = $status_filter;
}
if ($type_filter != 'all') {
    $query .= " AND o.outpass_type = ?";
    $types .= "s";
    $params[] = $type_filter;
}
if (!empty($search_term)) {
    $query .= " AND (s.regNo LIKE ? OR CONCAT(s.firstName, ' ', s.lastName) LIKE ? OR o.destination LIKE ?)";
    $types .= "sss";
    $search_param = "%$search_term%";
    array_push($params, $search_param, $search_param, $search_param);
}
$query .= " ORDER BY o.applied_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {$stmt->bind_param($types, ...$params);}
$stmt->execute();
$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outpass Management - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/outpass_management.css"> -->
    <style>
        .card { margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .status-pending { color: #f39c12; font-weight: bold; }
        .status-approved { color: #27ae60; font-weight: bold; }
        .status-rejected { color: #e74c3c; font-weight: bold; }
        .dashboard-stats { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .stat-card { background-color: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); height: 100%; }
        .stat-card i { font-size: 24px; margin-bottom: 10px; }
        .admin-filters { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        body { padding-top: 56px; min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar { position: fixed; top: 56px; left: 0; width: 250px; height: calc(100vh - 56px); background-color: #343a40; 
            padding-top: 20px; z-index: 100; transition: all 0.3s; }
        .sidebar a { padding: 12px 20px; color: #f8f9fa; display: block; text-decoration: none; transition: 0.3s; }
        .sidebar a:hover { background-color: #495057; color: #fff; }
        .sidebar a.active { background-color: #0d6efd; color: white; }
        .sidebar a i { margin-right: 10px; }
        .sidebar-heading { padding: 10px 15px; color: #adb5bd; font-size: 0.8rem; text-transform: uppercase; font-weight: bold; }
        .sidebar-divider { border-top: 1px solid rgba(255, 255, 255, 0.15); margin: 15px 0; }
        .content-wrapper { margin-left: 250px; width: calc(100% - 250px); padding: 20px; transition: all 0.3s; }
        .header { position: fixed; top: 0; left: 0; right: 0; height: 56px; background-color: #0d6efd; color: white; display: flex; 
            align-items: center; padding: 0 20px; z-index: 1000; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .header-brand { font-size: 1.5rem; font-weight: bold; margin-right: auto; }
        .header-toggler { cursor: pointer; margin-right: 15px; background: none; border: none; color: white; font-size: 1.5rem; }
        .header-profile { display: flex; align-items: center; cursor: pointer; }
        .header-profile img { width: 32px; height: 32px; border-radius: 50%; margin-right: 10px; }
        .dropdown-menu { right: 0 !important; left: auto !important; }
        @media (max-width: 768px) { .sidebar { margin-left: -250px; } .content-wrapper { margin-left: 0; width: 100%; } .sidebar.active { margin-left: 0; } 
        .content-wrapper.active { margin-left: 250px; width: calc(100% - 250px); } }
    </style>
</head>
<body>
    <header class="header">
        <button class="header-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="header-brand">Outpass Management System</div>
        <div class="header-profile dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle fa-2x me-2"></i>
                <?php echo $admin_name; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="profileDropdown">
                <li><a class="dropdown-item" href="admin_profile.php">Profile</a></li>
                <!-- <li><a class="dropdown-item" href="admin_settings.php">Settings</a></li> -->
                <li><hr class="dropdown-divider"></li>
                <!-- <li><a class="dropdown-item" href="logout.php">Sign out</a></li> -->
            </ul>
        </div>
    </header>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-heading">
            ADMIN PANEL
        </div>
        <div class="sidebar-divider"></div>
        <a href="admin_dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="outpass_management.php" class="sidebar-item active"><i class="fas fa-file-alt"></i> Outpass Management</a>
        <a href="manage_student.php" class="sidebar-item"><i class="fas fa-users"></i> Student Management</a>
        <!-- <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar"></i> Reports</a> -->
        <div class="sidebar-divider"></div>
        <!-- <div class="sidebar-heading">SETTINGS</div>
        <a href="admin_profile.php" class="sidebar-item"><i class="fas fa-user-circle"></i> My Profile</a>
        <a href="system_settings.php" class="sidebar-item"><i class="fas fa-cog"></i> System Settings</a> -->
        <a href="admin_logout.php" class="sidebar-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="content-wrapper">
        <div class="container-fluid py-4">
            <h2 class="mb-4"><i class="fas fa-file-alt me-2"></i>Outpass Management</h2>
            <?php if(isset($_SESSION['message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="row dashboard-stats">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-clock text-warning"></i>
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-check-circle text-success"></i>
                        <h3><?php echo $approved_count; ?></h3>
                        <p>Approved Outpasses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-times-circle text-danger"></i>
                        <h3><?php echo $rejected_count; ?></h3>
                        <p>Rejected Outpasses</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-list text-primary"></i>
                        <h3><?php echo $total_count; ?></h3>
                        <p>Total Outpasses</p>
                    </div>
                </div>
            </div>
            <div class="admin-filters">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Outpass Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="General" <?php echo $type_filter == 'General' ? 'selected' : ''; ?>>General</option>
                            <option value="Home" <?php echo $type_filter == 'Home' ? 'selected' : ''; ?>>Home</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search by Reg No, Name, Destination" value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-header bg-primary text-white"><h3>Outpass Requests</h3></div>
                <div class="card-body">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th> <th>Student</th> <th>Reg No</th> <th>Type</th> <th>Out Date</th>
                                        <th>In Date</th> <th>Destination</th> <th>Status</th> <th>Applied On</th> <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($outpass = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $outpass['id']; ?></td>
                                            <td><?php echo $outpass['student_name']; ?></td>
                                            <td><?php echo $outpass['student_reg_no']; ?></td>
                                            <td><?php echo $outpass['outpass_type']; ?></td>
                                            <td><?php echo date('d-m-Y', strtotime($outpass['out_date'])); ?></td>
                                            <td><?php echo date('d-m-Y', strtotime($outpass['in_date'])); ?></td>
                                            <td><?php echo $outpass['destination']; ?></td>
                                            <td>
                                                <span class="status-<?php echo strtolower($outpass['status']); ?>">
                                                    <?php echo $outpass['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d-m-Y H:i', strtotime($outpass['applied_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $outpass['id']; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if($outpass['status'] == 'Pending'): ?>
                                                    <form method="post" action="" class="d-inline">
                                                        <input type="hidden" name="outpass_id" value="<?php echo $outpass['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $outpass['status']; ?>">
                                                        <button type="submit" name="approve" class="btn btn-sm btn-success mb-1" onclick="return confirm('Are you sure you want to approve this outpass?')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="post" action="" class="d-inline">
                                                        <input type="hidden" name="outpass_id" value="<?php echo $outpass['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $outpass['status']; ?>">
                                                        <button type="submit" name="reject" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Are you sure you want to reject this outpass?')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="detailsModal<?php echo $outpass['id']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $outpass['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="detailsModalLabel<?php echo $outpass['id']; ?>">Outpass Details #<?php echo $outpass['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h5>Student Information</h5>
                                                                <p><strong>Name:</strong> <?php echo $outpass['student_name']; ?></p>
                                                                <p><strong>Reg No:</strong> <?php echo $outpass['student_reg_no']; ?></p>
                                                                <p><strong>Year:</strong> <?php echo $outpass['year_of_study'] ?? 'Not specified'; ?></p>
                                                                <p><strong>Course:</strong> <?php echo $outpass['course'] ?? 'Not specified'; ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h5>Outpass Information</h5>
                                                                <p><strong>Outpass Type:</strong> <?php echo $outpass['outpass_type']; ?></p>
                                                                <p><strong>Out Date & Time:</strong> <?php echo date('d-m-Y', strtotime($outpass['out_date'])) . ' ' . $outpass['out_time']; ?></p>
                                                                <p><strong>In Date & Time:</strong> <?php echo date('d-m-Y', strtotime($outpass['in_date'])) . ' ' . $outpass['in_time']; ?></p>
                                                                <p><strong>Duration:</strong> <?php 
                                                                    $out = new DateTime($outpass['out_date'] . ' ' . $outpass['out_time']);
                                                                    $in = new DateTime($outpass['in_date'] . ' ' . $outpass['in_time']);
                                                                    $diff = $out->diff($in);
                                                                    echo $diff->days . ' days, ' . $diff->h . ' hours';
                                                                ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="row mt-3">
                                                            <div class="col-12">
                                                                <h5>Additional Information</h5>
                                                                <p><strong>Destination:</strong> <?php echo $outpass['destination']; ?></p>
                                                                <p><strong>Reason:</strong> <?php echo $outpass['reason']; ?></p>
                                                                <p><strong>Status:</strong> <span class="status-<?php echo strtolower($outpass['status']); ?>"><?php echo $outpass['status']; ?></span></p>
                                                                <p><strong>Applied On:</strong> <?php echo date('d-m-Y H:i', strtotime($outpass['applied_at'])); ?></p>
                                                                <?php if($outpass['status'] != 'Pending'): ?>
                                                                    <p><strong>Processed By:</strong> <?php echo $outpass['approved_by']; ?></p>
                                                                    <p><strong>Processed On:</strong> <?php echo date('d-m-Y H:i', strtotime($outpass['approval_date'])); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if($outpass['status'] == 'Pending'): ?>
                                                            <form method="post" action="" class="d-inline">
                                                                <input type="hidden" name="outpass_id" value="<?php echo $outpass['id']; ?>">
                                                                <input type="hidden" name="current_status" value="<?php echo $outpass['status']; ?>">
                                                                <button type="submit" name="approve" class="btn btn-success">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                            </form>
                                                            <form method="post" action="" class="d-inline">
                                                                <input type="hidden" name="outpass_id" value="<?php echo $outpass['id']; ?>">
                                                                <input type="hidden" name="current_status" value="<?php echo $outpass['status']; ?>">
                                                                <button type="submit" name="reject" class="btn btn-danger">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            </form>
                                                        <?php elseif($outpass['status'] == 'Approved'): ?>
                                                            <button type="button" class="btn btn-primary" onclick="printOutpass(<?php echo $outpass['id']; ?>)">
                                                                <i class="fas fa-print"></i> Print Outpass
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No outpass requests found matching your criteria.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printOutpass(id) {window.open('print_outpass.php?id=' + id, '_blank');}
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.content-wrapper').classList.toggle('active');
        });
        window.addEventListener('load', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>