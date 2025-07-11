<?php
session_start();
include('staff_db.php');
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['position']) !== 'warden') {
    header("Location: staff_test_login.php");
    exit;}
$warden_id = $_SESSION['staff_id'];
$warden_name = $_SESSION['name'];
$hostel = $_SESSION['hostel'];
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['assign_service']) && isset($_POST['request_id']) && isset($_POST['staff_id'])) {
        $request_id = $_POST['request_id'];
        $staff_id = $_POST['staff_id'];
        $check_query = "SELECT status, assigned_to FROM room_service_requests WHERE request_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_status = $result->fetch_assoc();
        if($current_status && $current_status['status'] === 'pending') {
            $update_query = "UPDATE room_service_requests SET status = 'in_progress', assigned_to = ? WHERE request_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $staff_id, $request_id);
            if($stmt->execute() && $stmt->affected_rows > 0) {$_SESSION['message'] = "Service request #$request_id has been assigned to staff successfully";} 
            else {$_SESSION['error'] = "Error assigning service request #$request_id";}
        } else {$_SESSION['error'] = "Service request #$request_id is already being processed or doesn't exist";}
        header("Location: service_requests.php");
        exit;
    }
}
$service_query = "SELECT DISTINCT r.request_id, r.reg_no, r.service_type, r.description, r.request_date, 
                  r.status, r.assigned_to, r.completion_date, s.firstName, s.lastName, s.email, s.gender, rb.room_number 
                  FROM room_service_requests r
                  JOIN student_signup s ON r.reg_no = s.regNo
                  JOIN room_bookings rb ON s.email = rb.user_email
                  WHERE rb.hostel_name = ? AND r.status = 'pending'";
$stmt = $conn->prepare($service_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$service_requests = $stmt->get_result();
$service_count = $service_requests->num_rows;
$staff_query = "SELECT s.staff_id, s.name, s.position, s.department 
                FROM staff s
                WHERE s.hostel = ? OR s.hostel LIKE ?
                ORDER BY s.department, s.position";
$hostel_pattern = "%$hostel%";
$stmt = $conn->prepare($staff_query);
$stmt->bind_param("ss", $hostel, $hostel_pattern);
$stmt->execute();
$available_staff = $stmt->get_result();
$service_history_query = "SELECT DISTINCT r.request_id, r.reg_no, r.service_type, r.description, r.request_date, 
                         r.status, r.assigned_to, r.completion_date, s.firstName, s.lastName, s.email, 
                         st.name as assigned_staff_name, rb.room_number 
                         FROM room_service_requests r
                         JOIN student_signup s ON r.reg_no = s.regNo
                         JOIN room_bookings rb ON s.email = rb.user_email
                         LEFT JOIN staff st ON r.assigned_to = st.staff_id
                         WHERE rb.hostel_name = ? AND r.status != 'pending'
                         ORDER BY r.request_date DESC LIMIT 10";
$stmt = $conn->prepare($service_history_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$service_history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Service Management - <?php echo $hostel; ?> Hostel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/service_requests.css"> -->
     <style>
        .service-status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; } 
        .pending { background-color: #ffcccb; color: #d32f2f; } 
        .in_progress { background-color: #fff0c2; color: #ff8f00; } 
        .completed { background-color: #d4edda; color: #155724; } 
        .cancelled { background-color: #e9ecef; color: #343a40; } 
        .assign-service-form { display: flex; gap: 5px; } 
        .filter-controls { margin-bottom: 15px; display: flex; gap: 10px; } 
        .filter-select { padding: 6px 12px; border-radius: 4px; border: 1px solid #ddd; } 
        .alert { padding: 12px 15px; margin-bottom: 15px; border-radius: 4px; } 
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; } 
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; } 
        .btn { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; } 
        .btn-primary { background-color: #007bff; color: white; } 
        .btn-sm { padding: 4px 8px; font-size: 12px; } 
        .table-container { overflow-x: auto; } 
        table { width: 100%; border-collapse: collapse; } 
        table th, table td { padding: 10px; border: 1px solid #dee2e6; } 
        table th { background-color: #f8f9fa; text-align: left; } 
        .tab-section { background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px; padding: 20px; } 
        .tab-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; } 
        .tab-section-header h3 { margin: 0; } 
        .container { display: flex; } 
        .sidebar { width: 250px; background-color: #343a40; color: white; height: 100vh; position: fixed; } 
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); } 
        .sidebar-menu { padding: 10px 0; } 
        .menu-item { padding: 12px 20px; display: flex; align-items: center; text-decoration: none; color: white; transition: all 0.3s; position: relative; } 
        .menu-item i { margin-right: 10px; } 
        .menu-item.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid #007bff; } 
        .notification-badge { position: absolute; right: 20px; } 
        .badge { background-color: #dc3545; color: white; padding: 3px 6px; border-radius: 50%; font-size: 10px; } 
        .main-content { flex: 1; margin-left: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; } 
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; } 
        .user-info { display: flex; align-items: center; } 
        .user-info img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; } 
        .user-info h4, .user-info p { margin: 0; } 
        .dashboard-content { margin-top: 20px; }
     </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Hostel Management</h3>
                <p><?php echo $hostel; ?> Hostel</p>
            </div>
            <div class="sidebar-menu">
                <a href="warden_test_dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="outpass_management.php" class="menu-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Outpass Requests</span>
                </a>
                <a href="room_management.php" class="menu-item">
                    <i class="fas fa-building"></i>
                    <span>Room Management</span>
                </a> 
                <a href="service_requests.php" class="menu-item active">
                    <i class="fas fa-wrench"></i>
                    <span>Service Requests</span>
                    <?php if($service_count > 0): ?>
                    <div class="notification-badge">
                        <span class="badge"><?php echo $service_count; ?></span>
                    </div>
                    <?php endif; ?>
                </a>
                <a href="staff_management.php" class="menu-item">
                    <i class="fas fa-users-cog"></i>
                    <span>Staff Management</span>
                </a>
                <a href="staff_test_login.php?logout=true" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        <div class="main-content">
            <div class="header">
                <h2>Room Service Management</h2>
                <div class="user-info">
                    <div>
                        <h4><?php echo $warden_name; ?></h4>
                        <p>Warden</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-content">
                <?php if(isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>
                <div class="tab-section">
                    <div class="tab-section-header">
                        <h3>Pending Service Requests</h3>
                        <div class="filter-controls">
                            <select id="serviceTypeFilter" class="filter-select">
                                <option value="all">All Types</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="serviceTable">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Student</th>
                                    <th>Reg No</th>
                                    <th>Room</th>
                                    <th>Service Type</th>
                                    <th>Description</th>
                                    <th>Request Date</th>
                                    <th>Assign To Staff</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($service_requests->num_rows > 0) {
                                    while ($request = $service_requests->fetch_assoc()):
                                ?>
                                <tr data-service-type="<?php echo $request['service_type']; ?>">
                                    <td><?php echo $request['request_id']; ?></td>
                                    <td><?php echo $request['firstName'] . ' ' . $request['lastName']; ?></td>
                                    <td><?php echo $request['reg_no']; ?></td>
                                    <td><?php echo $request['room_number']; ?></td>
                                    <td><?php echo $request['service_type']; ?></td>
                                    <td><?php echo $request['description']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($request['request_date'])); ?></td>
                                    <td>
                                        <form method="post" class="assign-service-form">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            <select name="staff_id" class="filter-select" required>
                                                <option value="">Select Staff</option>
                                                <?php 
                                                $available_staff->data_seek(0);
                                                while ($staff = $available_staff->fetch_assoc()): 
                                                    $show_staff = false;
                                                    if ($request['service_type'] == 'Cleaning' && $staff['position'] == 'Room Service') {$show_staff = true;} 
                                                    elseif ($request['service_type'] == 'Electrical' && $staff['position'] == 'Electrician') {$show_staff = true;} 
                                                    elseif ($request['service_type'] == 'Plumbing' && $staff['position'] == 'Plumber') {$show_staff = true;} 
                                                    elseif ($request['service_type'] == 'Maintenance' && $staff['position'] == 'Maintenance') {$show_staff = true;}
                                                    elseif ($request['service_type'] == 'Other') {
                                                        if ($staff['department'] == 'Maintenance') {$show_staff = true;}}
                                                    if ($show_staff):
                                                ?>
                                                <option value="<?php echo $staff['staff_id']; ?>">
                                                    <?php echo $staff['name']; ?> (<?php echo $staff['position']; ?> - <?php echo $staff['department']; ?>)
                                                </option>
                                                <?php 
                                                    endif;
                                                endwhile; 
                                                ?>
                                            </select>
                                            <button type="submit" name="assign_service" class="btn btn-primary btn-sm">Assign</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; 
                                } else { ?>
                                <tr><td colspan="8" style="text-align: center;">No pending service requests</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-section">
                    <div class="tab-section-header">
                        <h3>Recent Service History</h3>
                        <div class="filter-controls">
                            <select id="statusFilter" class="filter-select">
                                <option value="all">All Statuses</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="historyTable">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Student</th>
                                    <th>Room</th>
                                    <th>Service Type</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Request Date</th>
                                    <th>Completed Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($service_history->num_rows > 0) {
                                    while ($history = $service_history->fetch_assoc()): 
                                ?>
                                <tr data-status="<?php echo $history['status']; ?>">
                                    <td><?php echo $history['request_id']; ?></td>
                                    <td><?php echo $history['firstName'] . ' ' . $history['lastName']; ?></td>
                                    <td><?php echo $history['room_number']; ?></td>
                                    <td><?php echo $history['service_type']; ?></td>
                                    <td>
                                        <span class="service-status <?php echo $history['status']; ?>">
                                            <?php 
                                            $status_text = ucfirst(str_replace('_', ' ', $history['status']));
                                            echo $status_text; 
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $history['assigned_staff_name'] ? $history['assigned_staff_name'] : 'Not assigned'; ?></td>
                                    <td><?php echo date('d M Y', strtotime($history['request_date'])); ?></td>
                                    <td>
                                        <?php 
                                        echo ($history['completion_date']) ? date('d M Y', strtotime($history['completion_date'])) : 'Not completed';
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; 
                                } else { ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No service history available</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-section">
                    <div class="tab-section-header">
                        <h3>Service Request Statistics</h3>
                    </div>
                    <div class="row">
                        <?php
                        $stats_query = "SELECT 
                                          COUNT(DISTINCT CASE WHEN r.status = 'pending' THEN r.request_id END) as pending_count,
                                          COUNT(DISTINCT CASE WHEN r.status = 'in_progress' THEN r.request_id END) as in_progress_count,
                                          COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.request_id END) as completed_count,
                                          COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.request_id END) as cancelled_count
                                        FROM room_service_requests r
                                        JOIN student_signup s ON r.reg_no = s.regNo
                                        JOIN room_bookings rb ON s.email = rb.user_email
                                        WHERE rb.hostel_name = ?";
                        $stmt = $conn->prepare($stats_query);
                        $stmt->bind_param("s", $hostel);
                        $stmt->execute();
                        $stats = $stmt->get_result()->fetch_assoc();
                        ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                            <div style="flex: 1; min-width: 200px; background-color: #ffcccb; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>Pending</h4>
                                <h2><?php echo $stats['pending_count']; ?></h2>
                            </div>
                            <div style="flex: 1; min-width: 200px; background-color: #fff0c2; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>In Progress</h4>
                                <h2><?php echo $stats['in_progress_count']; ?></h2>
                            </div>
                            <div style="flex: 1; min-width: 200px; background-color: #d4edda; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>Completed</h4>
                                <h2><?php echo $stats['completed_count']; ?></h2>
                            </div>
                            <div style="flex: 1; min-width: 200px; background-color: #e9ecef; padding: 15px; border-radius: 5px; text-align: center;">
                                <h4>Cancelled</h4>
                                <h2><?php echo $stats['cancelled_count']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-section">
                    <div class="tab-section-header"><h3>Most Common Service Issues</h3></div>
                    <div class="table-container">
                        <?php
                        $common_issues_query = "SELECT service_type, COUNT(DISTINCT r.request_id) as count
                                               FROM room_service_requests r
                                               JOIN student_signup s ON r.reg_no = s.regNo
                                               JOIN room_bookings rb ON s.email = rb.user_email
                                               WHERE rb.hostel_name = ?
                                               GROUP BY service_type
                                               ORDER BY count DESC
                                               LIMIT 5";
                        $stmt = $conn->prepare($common_issues_query);
                        $stmt->bind_param("s", $hostel);
                        $stmt->execute();
                        $common_issues = $stmt->get_result();
                        ?>
                        <table>
                            <thead> 
                                <tr>
                                    <th>Service Type</th>
                                    <th>Number of Requests</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($common_issues->num_rows > 0) {
                                    while ($issue = $common_issues->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo $issue['service_type']; ?></td>
                                    <td><?php echo $issue['count']; ?></td>
                                </tr>
                                <?php endwhile; 
                                } else { ?>
                                <tr><td colspan="2" style="text-align: center;">No data available</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const serviceTypeFilter = document.getElementById('serviceTypeFilter');
        const serviceTable = document.getElementById('serviceTable');
        function filterServiceTable() {
            const selectedType = serviceTypeFilter.value;
            const rows = serviceTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr'); 
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const serviceType = row.getAttribute('data-service-type');
                if (selectedType === 'all' || serviceType === selectedType) {row.style.display = '';} 
                else {row.style.display = 'none';}
            }
        }
        serviceTypeFilter.addEventListener('change', filterServiceTable);
        const statusFilter = document.getElementById('statusFilter');
        const historyTable = document.getElementById('historyTable');
        function filterHistoryTable() {
            const selectedStatus = statusFilter.value;
            const rows = historyTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const status = row.getAttribute('data-status');
                if (selectedStatus === 'all' || status === selectedStatus) {row.style.display = '';} 
                else {row.style.display = 'none';}
            }
        }
        statusFilter.addEventListener('change', filterHistoryTable);
        filterServiceTable();
        filterHistoryTable();
        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href);}
    </script>
</body>
</html>