<?php
session_start();
include('staff_db.php');
if (!isset($_SESSION['staff_id']) || !isset($_SESSION['position']) || strtolower($_SESSION['position']) !== 'warden') {
    header("Location: staff_test_login.php");
    exit();}
$warden_id = $_SESSION['staff_id'];
$warden_name = $_SESSION['name'];
$hostel = $_SESSION['hostel'];
if(isset($_POST['action']) && isset($_POST['outpass_id'])) {
    $action = $_POST['action'];
    $outpass_id = $_POST['outpass_id'];
    if($action == 'approve' || $action == 'reject') {
        $status = ($action == 'approve') ? 'Approved' : 'Rejected';
        $update_query = "UPDATE outpass SET status = ?, approved_by = ?, approval_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $status, $warden_id, $outpass_id); 
        if($stmt->execute()) {$_SESSION['message'] = "Outpass request has been " . strtolower($status);} 
        else {$_SESSION['error'] = "Error processing outpass request";}
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}
$outpass_query = "SELECT o.*, s.firstName, s.lastName, r.room_number 
                  FROM outpass o 
                  JOIN student_signup s ON o.student_reg_no = s.regNo
                  LEFT JOIN (
                      SELECT DISTINCT user_email, hostel_name, room_number
                      FROM room_bookings
                  ) r ON s.email = r.user_email AND r.hostel_name = ?
                  WHERE o.status = 'Pending'
                  ORDER BY o.applied_at DESC";
$stmt = $conn->prepare($outpass_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$pending_outpasses = $stmt->get_result();
$outpass_count = $pending_outpasses->num_rows;
$history_query = "SELECT o.*, s.firstName, s.lastName, r.room_number 
                  FROM outpass o 
                  JOIN student_signup s ON o.student_reg_no = s.regNo
                  LEFT JOIN (
                      SELECT DISTINCT user_email, hostel_name, room_number
                      FROM room_bookings
                  ) r ON s.email = r.user_email AND r.hostel_name = ?
                  WHERE o.status != 'Pending'
                  ORDER BY o.approval_date DESC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$outpass_history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outpass Management - <?php echo htmlspecialchars($hostel); ?> Hostel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/outpass_management.css"> -->
     <style>
        :root { --sidebar-width: 250px; --header-height: 60px; --primary-color: #4e73df; --secondary-color: #858796; 
            --success-color: #1cc88a; --danger-color: #e74a3b; --warning-color: #f6c23e; --sidebar-bg: #4e73df; --sidebar-color: #fff; } 
        body { padding: 0; margin: 0; font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f8f9fc; } 
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, var(--sidebar-bg) 0%, #224abe 100%); 
            color: var(--sidebar-color); overflow-y: auto; z-index: 1000; transition: all 0.3s; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); } 
        .sidebar-brand { padding: 1.5rem 1rem; display: flex; align-items: center; justify-content: center; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.2); } 
        .sidebar-brand h2 { margin: 0; font-size: 1.2rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; } 
        .sidebar-divider { border-top: 1px solid rgba(255, 255, 255, 0.2); margin: 1rem 1rem; } 
        .sidebar-heading { padding: 0 1rem; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.13rem; color: rgba(255, 255, 255, 0.6); } 
        .nav-item { position: relative; } 
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1rem; color: rgba(255, 255, 255, 0.8); font-weight: 500; transition: all 0.2s; } 
        .nav-link:hover, .nav-link.active { color: white; background-color: rgba(255, 255, 255, 0.1); border-radius: 0.35rem; } 
        .nav-link i { margin-right: 0.75rem; font-size: 0.85rem; width: 1.5rem; text-align: center; } 
        .main-header { position: fixed; top: 0; left: var(--sidebar-width); right: 0; height: var(--header-height); background-color: white; 
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); display: flex; justify-content: space-between; align-items: center; padding: 0 1.5rem; z-index: 900; } 
        .main-header .search-form { display: flex; align-items: center; background-color: #f8f9fc; border-radius: 2rem; padding: 0.2rem 1rem; border: 1px solid #e3e6f0; width: 20rem; } 
        .main-header .search-form input { background-color: transparent; border: none; padding: 0.375rem 0.5rem; font-size: 0.85rem; outline: none; width: 100%; } 
        .main-header .search-form button { background: none; border: none; color: var(--secondary-color); cursor: pointer; } 
        .header-right { display: flex; align-items: center; } 
        .header-right .divider-vertical { height: 2rem; border-right: 1px solid #e3e6f0; margin: 0 1rem; } 
        .header-right .user-info { display: flex; align-items: center; font-weight: 500; color: var(--secondary-color); } 
        .header-right .user-info img { width: 2rem; height: 2rem; border-radius: 50%; margin-right: 0.5rem; object-fit: cover; } 
        .main-content { margin-left: var(--sidebar-width); padding-top: var(--header-height); min-height: 100vh; padding-bottom: 2rem; } 
        .content-wrapper { padding: 1.5rem; } 
        .header-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; } 
        .filter-container { display: flex; gap: 10px; margin-bottom: 15px; } 
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: 500; } 
        .status-badge.approved { background-color: #d4edda; color: #155724; } 
        .status-badge.rejected { background-color: #f8d7da; color: #721c24; } 
        .status-badge.pending { background-color: #fff3cd; color: #856404; } 
        .table-responsive { margin-bottom: 30px; } 
        .outpass-type-day { background-color: #e7f5ff; } 
        .outpass-type-home { background-color: #fff9db; } 
        .outpass-type-emergency { background-color: #fff5f5; } 
        .nav-tabs { margin-bottom: 20px; } 
        .search-container { margin-bottom: 15px; } 
        .dashboard-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; } 
        .stat-card { border-left: 4px solid; border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); background-color: white; 
            padding: 1.25rem; position: relative; display: flex; flex-direction: column; } 
        .stat-card.primary { border-left-color: var(--primary-color); } 
        .stat-card.success { border-left-color: var(--success-color); } 
        .stat-card.warning { border-left-color: var(--warning-color); } 
        .stat-card.danger { border-left-color: var(--danger-color); } 
        .stat-card .stat-title { text-transform: uppercase; font-size: 0.7rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.25rem; } 
        .stat-card .stat-value { color: #5a5c69; font-size: 1.25rem; font-weight: 700; margin-bottom: 0; } 
        .stat-card .stat-icon { position: absolute; top: 50%; right: 1.25rem; transform: translateY(-50%); color: #dddfeb; font-size: 2rem; } 
        #sidebarToggle { background: none; border: none; color: var(--secondary-color); font-size: 1rem; cursor: pointer; padding: 0.5rem; 
            display: flex; align-items: center; justify-content: center; margin-right: 1rem; } 
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } .main-header, 
        .main-content { left: 0; width: 100%; } .content-wrapper { padding: 1rem; } .main-header .search-form { width: 12rem; } }
     </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand"><h2>Hostel Management</h2></div>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Main</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="warden_test_dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="outpass_management">
                    <i class="fas fa-fw fa-id-card"></i>
                    <span>Outpass Management</span>
                </a>
            </li>
            <!-- <li class="nav-item">
                <a class="nav-link" href="students.php">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Students</span>
                </a>
            </li> -->
            <li class="nav-item">
                <a class="nav-link" href="rooms.php">
                    <i class="fas fa-fw fa-bed"></i>
                    <span>Room Management</span>
                </a>
            </li>
        </ul>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Admin
        </div>
        <ul class="nav flex-column">
            <!-- <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-fw fa-user"></i>
                    <span>Profile</span>
                </a>
            </li> -->
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-fw fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
        <hr class="sidebar-divider">
        <div class="text-center my-3">
            <button class="rounded-circle border-0" id="sidebarToggle">
                <i class="fas fa-angle-left"></i>
            </button>
        </div>
    </div>
    <header class="main-header">
        <div class="d-flex align-items-center">
            <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3"><i class="fa fa-bars"></i></button>
            <div class="search-form">
                <input type="text" placeholder="Search for...">
                <button type="button"><i class="fas fa-search fa-sm"></i></button>
            </div>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <button class="btn btn-link position-relative" id="alertsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell fa-fw"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $outpass_count; ?>
                    </span>
                </button>
            </div>
            <!-- <div class="divider-vertical"></div>
            <div class="user-info">
                <img src="https://via.placeholder.com/150" alt="User">
                <span><?php echo htmlspecialchars($warden_name); ?></span>
            </div> -->
        </div>
    </header>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-sm-flex align-items-center justify-content-between mb-4"><h1 class="h3 mb-0 text-gray-800">Outpass Management</h1></div>
            <div class="dashboard-stats">
                <div class="stat-card primary">
                    <div class="stat-title">Pending Requests</div>
                    <div class="stat-value"><?php echo $outpass_count; ?></div>
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-title">Approved Today</div>
                    <div class="stat-value">
                        <?php 
                            $today_approved = 0;
                            $outpass_history_array = [];
                            while ($record = $outpass_history->fetch_assoc()) {
                                $outpass_history_array[] = $record;
                                if ($record['status'] == 'Approved' && date('Y-m-d', strtotime($record['approval_date'])) == date('Y-m-d')) {$today_approved++;}}
                            echo $today_approved;
                        ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-title">Students Out</div>
                    <div class="stat-value">
                        <?php 
                            $students_out = 0;
                            foreach ($outpass_history_array as $record) {
                                if ($record['status'] == 'Approved' && 
                                    strtotime($record['out_date']) <= time() && 
                                    strtotime($record['in_date']) >= time()) {
                                    $students_out++;
                                }
                            }echo $students_out;
                        ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-sign-out-alt"></i></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-title">Emergency Passes</div>
                    <div class="stat-value">
                        <?php 
                            $emergency = 0;
                            $pending_outpasses_array = [];
                            while ($pass = $pending_outpasses->fetch_assoc()) {
                                $pending_outpasses_array[] = $pass;
                                if ($pass['outpass_type'] == 'Emergency') {$emergency++;}
                            }echo $emergency;
                        ?>
                    </div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
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
            <ul class="nav nav-tabs" id="outpassTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                        Pending Requests <span class="badge bg-warning text-dark"><?php echo count($pending_outpasses_array); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                        Outpass History
                    </button>
                </li> 
            </ul>
            <div class="tab-content" id="outpassTabContent">
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="card">
                        <div class="card-header"><h5>Pending Outpass Requests</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="search-container">
                                        <input type="text" id="searchPending" class="form-control" placeholder="Search by name, registration number, room...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="filter-container">
                                        <select id="typeFilter" class="form-select">
                                            <option value="all">All Types</option>
                                            <option value="Day Pass">Day Pass</option>
                                            <option value="Home Pass">Home Pass</option>
                                            <option value="Emergency">Emergency</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th> <th>Reg No</th> <th>Room</th>
                                            <th>Type</th> <th>Out Date</th> <th>In Date</th>
                                            <th>Reason</th> <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingOutpassTable">
                                        <?php if (count($pending_outpasses_array) > 0): ?>
                                            <?php foreach ($pending_outpasses_array as $outpass): ?>
                                                <?php 
                                                    $rowClass = '';
                                                    if ($outpass['outpass_type'] === 'Day Pass') {$rowClass = 'outpass-type-day';} 
                                                    elseif ($outpass['outpass_type'] === 'Home Pass') {$rowClass = 'outpass-type-home';} 
                                                    elseif ($outpass['outpass_type'] === 'Emergency') {$rowClass = 'outpass-type-emergency';}
                                                ?>
                                                <tr class="<?php echo $rowClass; ?>" data-type="<?php echo htmlspecialchars($outpass['outpass_type']); ?>">
                                                    <td><?php echo htmlspecialchars($outpass['firstName'] . ' ' . $outpass['lastName']); ?></td>
                                                    <td><?php echo htmlspecialchars($outpass['student_reg_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($outpass['room_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($outpass['outpass_type']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($outpass['out_date'])); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($outpass['in_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($outpass['reason']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info view-outpass" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#outpassModal"
                                                                data-id="<?php echo $outpass['id']; ?>"
                                                                data-student="<?php echo htmlspecialchars($outpass['firstName'] . ' ' . $outpass['lastName']); ?>"
                                                                data-regno="<?php echo htmlspecialchars($outpass['student_reg_no']); ?>"
                                                                data-room="<?php echo htmlspecialchars($outpass['room_number']); ?>"
                                                                data-type="<?php echo htmlspecialchars($outpass['outpass_type']); ?>"
                                                                data-outdate="<?php echo date('d M Y', strtotime($outpass['out_date'])); ?>"
                                                                data-indate="<?php echo date('d M Y', strtotime($outpass['in_date'])); ?>"
                                                                data-reason="<?php echo htmlspecialchars($outpass['reason']); ?>"
                                                                data-applied="<?php echo date('d M Y H:i', strtotime($outpass['applied_at'])); ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="outpass_id" value="<?php echo $outpass['id']; ?>">
                                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check"></i> Approve</button>
                                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-times"></i> Reject</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" class="text-center">No pending outpass requests found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                    <div class="card">
                        <div class="card-header"><h5>Outpass History</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="search-container">
                                        <input type="text" id="searchHistory" class="form-control" placeholder="Search by name, registration number...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="filter-container">
                                        <select id="statusFilter" class="form-select">
                                            <option value="all">All Statuses</option>
                                            <option value="Approved">Approved</option>
                                            <option value="Rejected">Rejected</option>
                                        </select>
                                        <select id="historyTypeFilter" class="form-select">
                                            <option value="all">All Types</option>
                                            <option value="Day Pass">Day Pass</option>
                                            <option value="Home Pass">Home Pass</option>
                                            <option value="Emergency">Emergency</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th> <th>Reg No</th> <th>Room</th> <th>Type</th>
                                            <th>Out Date</th> <th>In Date</th> <th>Status</th> <th>Approved By</th> <th>Approval Date</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyOutpassTable">
                                        <?php if (count($outpass_history_array) > 0): ?>
                                            <?php foreach ($outpass_history_array as $history): ?>
                                                <tr data-status="<?php echo htmlspecialchars($history['status']); ?>" data-type="<?php echo htmlspecialchars($history['outpass_type']); ?>">
                                                    <td><?php echo htmlspecialchars($history['firstName'] . ' ' . $history['lastName']); ?></td>
                                                    <td><?php echo htmlspecialchars($history['student_reg_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($history['room_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($history['outpass_type']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($history['out_date'])); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($history['in_date'])); ?></td>
                                                    <td>
                                                        <span class="status-badge <?php echo strtolower($history['status']); ?>">
                                                            <?php echo htmlspecialchars($history['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($history['approved_by']); ?></td>
                                                    <td><?php echo date('d M Y H:i', strtotime($history['approval_date'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">No outpass history found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="outpassModal" tabindex="-1" aria-labelledby="outpassModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="outpassModalLabel">Outpass Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Student Information</h6>
                            <p><strong>Name:</strong> <span id="modal-student"></span></p>
                            <p><strong>Registration Number:</strong> <span id="modal-regno"></span></p>
                            <p><strong>Room Number:</strong> <span id="modal-room"></span></p>
                            <h6 class="card-subtitle mb-2 text-muted mt-3">Outpass Details</h6>
                            <p><strong>Type:</strong> <span id="modal-type"></span></p>
                            <p><strong>Out Date:</strong> <span id="modal-outdate"></span></p>
                            <p><strong>In Date:</strong> <span id="modal-indate"></span></p>
                            <p><strong>Reason:</strong> <span id="modal-reason"></span></p>
                            <p><strong>Applied At:</strong> <span id="modal-applied"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="outpass_id" id="modal-outpass-id" value="">
                        <button type="submit" name="action" value="approve" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.view-outpass').forEach(button => {
            button.addEventListener('click', function() {
                const outpassId = this.getAttribute('data-id');
                const student = this.getAttribute('data-student');
                const regno = this.getAttribute('data-regno');
                const room = this.getAttribute('data-room');
                const type = this.getAttribute('data-type');
                const outdate = this.getAttribute('data-outdate');
                const indate = this.getAttribute('data-indate');
                const reason = this.getAttribute('data-reason');
                const applied = this.getAttribute('data-applied');
                document.getElementById('modal-outpass-id').value = outpassId;
                document.getElementById('modal-student').textContent = student;
                document.getElementById('modal-regno').textContent = regno;
                document.getElementById('modal-room').textContent = room;
                document.getElementById('modal-type').textContent = type;
                document.getElementById('modal-outdate').textContent = outdate;
                document.getElementById('modal-indate').textContent = indate;
                document.getElementById('modal-reason').textContent = reason;
                document.getElementById('modal-applied').textContent = applied;
            });
        });
        document.getElementById('searchPending').addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#pendingOutpassTable tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
        document.getElementById('searchHistory').addEventListener('keyup', function() {filterHistoryTable();});
        document.getElementById('statusFilter').addEventListener('change', function() {filterHistoryTable();});
        document.getElementById('typeFilter').addEventListener('change', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#pendingOutpassTable tr');
            rows.forEach(row => {
                if (value === 'all') {row.style.display = '';} 
                else {
                    const type = row.getAttribute('data-type')?.toLowerCase();
                    row.style.display = type === value.toLowerCase() ? '' : 'none';
                }
            });
        });
        document.getElementById('historyTypeFilter').addEventListener('change', function() {filterHistoryTable();});
        function filterHistoryTable() {
            const searchValue = document.getElementById('searchHistory').value.toLowerCase();
            const statusValue = document.getElementById('statusFilter').value;
            const typeValue = document.getElementById('historyTypeFilter').value;
            const rows = document.querySelectorAll('#historyOutpassTable tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                const type = row.getAttribute('data-type');
                const matchesSearch = text.includes(searchValue);
                const matchesStatus = statusValue === 'all' || status === statusValue;
                const matchesType = typeValue === 'all' || type === typeValue;
                row.style.display = matchesSearch && matchesStatus && matchesType ? '' : 'none';
            });
        }
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-angle-left')) {
                icon.classList.remove('fa-angle-left');
                icon.classList.add('fa-angle-right');
            } else {
                icon.classList.remove('fa-angle-right');
                icon.classList.add('fa-angle-left');
            }
        });
        document.getElementById('sidebarToggleTop').addEventListener('click', function() {document.querySelector('.sidebar').classList.toggle('show');});
        function adjustLayout() {
            const width = window.innerWidth;
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const mainHeader = document.querySelector('.main-header');
            if (width <= 768) {
                sidebar.classList.remove('show');
                mainContent.style.marginLeft = '0';
                mainHeader.style.left = '0';
            } else {
                sidebar.classList.add('show');
                mainContent.style.marginLeft = 'var(--sidebar-width)';
                mainHeader.style.left = 'var(--sidebar-width)';
            }
        }
        window.addEventListener('load', adjustLayout);
        window.addEventListener('resize', adjustLayout);
    </script>
</body>
</html>