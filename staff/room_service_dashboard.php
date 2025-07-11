<?php
session_start();
include 'staff_db.php';

// Remove session checking since we don't want to redirect to login
// We'll assume the staff is already authenticated

// Get staff details from database instead of relying on session
// This ensures we always have the correct staff info
if (isset($_SESSION['staff_id'])) {
    $staff_id = $_SESSION['staff_id'];
    
    // Use the correct table name 'staff' instead of 'staff_members'
    $staff_query = "SELECT staff_id, name, position, department, hostel 
                   FROM staff WHERE staff_id = ?";
    $staff_stmt = $conn->prepare($staff_query);
    $staff_stmt->bind_param("s", $staff_id);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    
    if ($staff_result->num_rows > 0) {
        $staff_data = $staff_result->fetch_assoc();
        // Update session variables with fresh data from database
        $_SESSION['staff_id'] = $staff_data['staff_id'];
        $_SESSION['name'] = $staff_data['name'];
        $_SESSION['position'] = $staff_data['position'];
        $_SESSION['department'] = $staff_data['department'];
        $_SESSION['hostel'] = $staff_data['hostel'];
    }
    $staff_stmt->close();
}

// Now get staff details from session
$staff_id = $_SESSION['staff_id'];
$staff_name = $_SESSION['name'];
$staff_position = $_SESSION['position'];
$staff_department = $_SESSION['department'];
$staff_hostel = $_SESSION['hostel'];

$message = '';
$success = false;

// Handle message/error from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $success = true;
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    $success = false;
    unset($_SESSION['error']);
}

// Handle updating a service request status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    
    // Verify this staff is assigned to this request
    $check_sql = "SELECT status, assigned_to FROM room_service_requests WHERE request_id = ? AND assigned_to = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $request_id, $staff_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        if ($row['status'] !== $new_status) {
            // Update the status of the existing record instead of inserting a new one
            $completion_date = null;
            if ($new_status == 'completed' || $new_status == 'cancelled') {
                $completion_date = date('Y-m-d H:i:s');
            }
            
            $sql = "UPDATE room_service_requests SET status = ?, completion_date = ? WHERE request_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $new_status, $completion_date, $request_id);
            
            if ($stmt->execute()) {
                $message = "Request status updated successfully!";
                $success = true;
            } else {
                $message = "Error updating request: " . $stmt->error;
                $success = false;
            }
            $stmt->close();
        } else {
            $message = "Request #$request_id status remains unchanged.";
            $success = true;
        }
    } else {
        $message = "You are not authorized to update this request.";
        $success = false;
    }
    $check_stmt->close();
    
    // Store message in session to display after redirect
    $_SESSION['message'] = $message;
    $_SESSION['success'] = $success;
    
    // Redirect to the same page to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Convert hostel string to array for comparison
$assigned_hostels = explode(',', $staff_hostel);
$assigned_hostels = array_map('trim', $assigned_hostels);

// Prepare the query for assigned tasks
// Remove any duplicate rows by using GROUP BY on request_id
$sql = "SELECT r.request_id, r.reg_no, r.room_number, r.service_type, r.description, 
               r.request_date, r.status, r.assigned_to, r.completion_date, 
               s.firstName, s.lastName, s.gender, s.regNo,
               rb.hostel_name
        FROM room_service_requests r 
        JOIN student_signup s ON r.reg_no = s.regNo 
        JOIN room_bookings rb ON s.email = rb.user_email
        WHERE r.assigned_to = ?
        GROUP BY r.request_id
        ORDER BY FIELD(r.status, 'in_progress', 'pending', 'completed', 'cancelled'), 
                 r.request_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all requests
$assigned_requests = [];

while ($row = $result->fetch_assoc()) {
    $assigned_requests[] = $row;
}

$stmt->close();

// Get service history (completed or cancelled requests by this staff)
// Add GROUP BY to prevent duplicates
$history_sql = "SELECT r.request_id, r.reg_no, r.room_number, r.service_type, r.description, 
                      r.request_date, r.status, r.assigned_to, r.completion_date,
                      s.firstName, s.lastName, s.gender, s.regNo,
                      rb.hostel_name
               FROM room_service_requests r 
               JOIN student_signup s ON r.reg_no = s.regNo 
               JOIN room_bookings rb ON s.email = rb.user_email
               WHERE r.assigned_to = ? AND (r.status = 'completed' OR r.status = 'cancelled')
               GROUP BY r.request_id
               ORDER BY r.completion_date DESC
               LIMIT 10";

$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("s", $staff_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

$service_history = [];
while ($row = $history_result->fetch_assoc()) {
    $service_history[] = $row;
}
$history_stmt->close();

// Calculate statistics
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$cancelled_count = 0;

foreach ($assigned_requests as $request) {
    if ($request['status'] == 'pending') $pending_count++;
    elseif ($request['status'] == 'in_progress') $in_progress_count++;
    elseif ($request['status'] == 'completed') $completed_count++;
    elseif ($request['status'] == 'cancelled') $cancelled_count++;
}

$total_count = count($assigned_requests);

// Get stats for service types for this staff
// Add GROUP BY to prevent duplicate counts
$stats_sql = "SELECT service_type, COUNT(DISTINCT request_id) as count
              FROM room_service_requests 
              WHERE assigned_to = ? AND status = 'completed'
              GROUP BY service_type
              ORDER BY count DESC";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $staff_id);
$stats_stmt->execute();
$service_stats_result = $stats_stmt->get_result();

$service_stats = [];
while ($row = $service_stats_result->fetch_assoc()) {
    $service_stats[] = $row;
}
$stats_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Service Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container {
            padding: 20px;
        }
        .staff-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .request-list {
            margin-top: 20px;
        }
        .status-pending {
            background-color: #fff3cd;
        }
        .status-in_progress {
            background-color: #d1ecf1;
        }
        .status-completed {
            background-color: #d4edda;
        }
        .status-cancelled {
            background-color: #f8d7da;
        }
        .filter-section {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .navbar {
            margin-bottom: 20px;
        }
        .summary-card {
            margin-bottom: 20px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .summary-card .card-body {
            padding: 15px;
        }
        .summary-card h2 {
            font-size: 2.5rem;
            margin-bottom: 0;
        }
        .nav-tabs {
            margin-bottom: 15px;
        }
        .tab-content {
            padding: 15px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .badge-status {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
        .description-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .table th, .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Hostel Management System</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="room_service_dashboard.php">Room Service</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Maintenance Reports</a>
                    </li>
                </ul>
                <span class="navbar-text text-white">
                    Welcome, <?php echo $staff_name; ?> | 
                    <a href="staff_test_login.php?logout=true" class="text-white">Logout</a>
                </span>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mb-4">Room Service Dashboard</h2>
        
        <?php 
        // Display message from direct processing or from session after redirect
        if (!empty($message)): ?>
            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php elseif (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo isset($_SESSION['success']) && $_SESSION['success'] ? 'success' : 'danger'; ?>" role="alert">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="staff-info">
            <h4>Staff Information</h4>
            <div class="row">
                <div class="col-md-4">
                    <p><strong>Name:</strong> <?php echo $staff_name; ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Position:</strong> <?php echo $staff_position; ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Department:</strong> <?php echo ucwords($staff_department); ?></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <p><strong>Assigned Hostels:</strong> <?php echo $staff_hostel; ?></p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card summary-card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Pending</h5>
                        <h2 class="card-text"><?php echo $pending_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">In Progress</h5>
                        <h2 class="card-text"><?php echo $in_progress_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Completed</h5>
                        <h2 class="card-text"><?php echo $completed_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Cancelled</h5>
                        <h2 class="card-text"><?php echo $cancelled_count; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <ul class="nav nav-tabs mt-4" id="requestTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="assigned-tab" data-toggle="tab" href="#assigned" role="tab">My Tasks (<?php echo count($assigned_requests); ?>)</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab">Service History</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="stats-tab" data-toggle="tab" href="#stats" role="tab">Statistics</a>
            </li>
        </ul>
        
        <div class="tab-content" id="requestTabsContent">
            <!-- My Tasks Tab -->
            <div class="tab-pane fade show active" id="assigned" role="tabpanel">
                <?php if (count($assigned_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Hostel</th>
                                    <th>Room</th>
                                    <th>Service Type</th>
                                    <th>Description</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_requests as $request): ?>
                                    <tr class="status-<?php echo $request['status']; ?>">
                                        <td><?php echo $request['request_id']; ?></td>
                                        <td><?php echo $request['firstName'] . " " . $request['lastName']; ?><br>
                                            <small class="text-muted"><?php echo $request['reg_no']; ?></small>
                                        </td>
                                        <td><?php echo $request['hostel_name']; ?></td>
                                        <td><?php echo $request['room_number']; ?></td>
                                        <td><?php echo $request['service_type']; ?></td>
                                        <td class="description-cell" title="<?php echo htmlspecialchars($request['description']); ?>"><?php echo htmlspecialchars($request['description']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                                        <td>
                                            <?php
                                                $status_class = '';
                                                $status_text = '';
                                                
                                                switch($request['status']) {
                                                    case 'pending':
                                                        $status_class = 'warning';
                                                        $status_text = 'Pending';
                                                        break;
                                                    case 'in_progress':
                                                        $status_class = 'info';
                                                        $status_text = 'In Progress';
                                                        break;
                                                    case 'completed':
                                                        $status_class = 'success';
                                                        $status_text = 'Completed';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'danger';
                                                        $status_text = 'Cancelled';
                                                        break;
                                                }
                                            ?>
                                            <span class="badge badge-<?php echo $status_class; ?> badge-status"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($request['status'] == 'in_progress'): ?>
                                                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="new_status" value="completed">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Mark Complete
                                                    </button>
                                                </form>
                                                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="d-inline mt-1">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="new_status" value="cancelled">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php elseif ($request['status'] == 'pending'): ?>
                                                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="d-inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="new_status" value="in_progress">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-tools"></i> Start Work
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">No actions available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">You have no assigned tasks at the moment.</div>
                <?php endif; ?>
            </div>
            
            <!-- Service History Tab -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <?php if (count($service_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Hostel</th>
                                    <th>Room</th>
                                    <th>Service Type</th>
                                    <th>Description</th>
                                    <th>Request Date</th>
                                    <th>Completion Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($service_history as $request): ?>
                                    <tr class="status-<?php echo $request['status']; ?>">
                                        <td><?php echo $request['request_id']; ?></td>
                                        <td><?php echo $request['firstName'] . " " . $request['lastName']; ?><br>
                                            <small class="text-muted"><?php echo $request['reg_no']; ?></small>
                                        </td>
                                        <td><?php echo $request['hostel_name']; ?></td>
                                        <td><?php echo $request['room_number']; ?></td>
                                        <td><?php echo $request['service_type']; ?></td>
                                        <td class="description-cell" title="<?php echo htmlspecialchars($request['description']); ?>"><?php echo htmlspecialchars($request['description']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                                        <td><?php echo $request['completion_date'] ? date('M d, Y H:i', strtotime($request['completion_date'])) : '-'; ?></td>
                                        <td>
                                            <?php
                                                $status_class = $request['status'] == 'completed' ? 'success' : 'danger';
                                                $status_text = $request['status'] == 'completed' ? 'Completed' : 'Cancelled';
                                            ?>
                                            <span class="badge badge-<?php echo $status_class; ?> badge-status"><?php echo $status_text; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">You have no service history yet.</div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics Tab -->
            <div class="tab-pane fade" id="stats" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                Service Type Breakdown
                            </div>
                            <div class="card-body">
                                <?php if (count($service_stats) > 0): ?>
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Service Type</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_completed = 0;
                                            foreach ($service_stats as $stat) {
                                                $total_completed += $stat['count'];
                                            }
                                            
                                            foreach ($service_stats as $stat): 
                                                $percentage = ($stat['count'] / $total_completed) * 100;
                                            ?>
                                                <tr>
                                                    <td><?php echo $stat['service_type']; ?></td>
                                                    <td><?php echo $stat['count']; ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" 
                                                                style="width: <?php echo $percentage; ?>%;" 
                                                                aria-valuenow="<?php echo $percentage; ?>" 
                                                                aria-valuemin="0" 
                                                                aria-valuemax="100"><?php echo round($percentage, 1); ?>%</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p>No statistics available yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                Overall Performance
                            </div>
                            <div class="card-body">
                                <p><strong>Total Requests Handled:</strong> <?php echo $completed_count + $cancelled_count; ?></p>
                                <p><strong>Completion Rate:</strong> 
                                    <?php 
                                    $total_handled = $completed_count + $cancelled_count;
                                    echo $total_handled > 0 ? round(($completed_count / $total_handled) * 100, 1) . '%' : '0%'; 
                                    ?>
                                </p>
                                <p><strong>Current Workload:</strong> <?php echo $in_progress_count; ?> tasks in progress</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light text-center text-muted py-3 mt-5">
        <div class="container">
            &copy; <?php echo date('Y'); ?> Hostel Management System
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Script to show description in modal when clicked
        $(document).ready(function() {
            // Enable tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Show description in modal when clicked
            $('.description-cell').click(function() {
                var description = $(this).attr('title');
                $('#descriptionText').text(description);
                $('#descriptionModal').modal('show');
            });
            
            // Confirm before cancelling a task
            $('button[name="update_status"][value="cancelled"]').click(function(e) {
                if(!confirm('Are you sure you want to cancel this service request?')) {
                    e.preventDefault();
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Save the active tab to localStorage
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                localStorage.setItem('activeTab', $(e.target).attr('href'));
            });
            
            // Check if there's a saved tab and switch to it
            var activeTab = localStorage.getItem('activeTab');
            if(activeTab){
                $('#requestTabs a[href="' + activeTab + '"]').tab('show');
            }
        });
    </script>
    
    <!-- Description Modal -->
    <div class="modal fade" id="descriptionModal" tabindex="-1" role="dialog" aria-labelledby="descriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descriptionModalLabel">Request Description</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="descriptionText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>