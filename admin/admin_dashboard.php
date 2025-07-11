<?php
include('admin_db.php');
?>
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hostel Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        body {font-family: Arial, sans-serif;background-color: #f8f9fa;margin: 0;padding: 0;overflow-x: hidden;}
        .navbar {background-color: #343a40;padding: 1rem;position: fixed;width: calc(100% - 250px);top: 0;
            right: 0;z-index: 1030;box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);}
        .navbar-brand {color: white !important;font-size: 1.5rem;font-weight: bold;}
        .user-info {color: white;font-size: 0.9rem;}
        .sidebar {height: 100vh;width: 250px;position: fixed;top: 0;left: 0;background-color: #343a40;padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);z-index: 1000;transition: all 0.3s ease;}
        .sidebar-header {padding: 20px;text-align: center;border-bottom: 1px solid rgba(255, 255, 255, 0.1);}
        .sidebar-header img {max-width: 150px;margin-bottom: 20px;border-radius: 8px;box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);}
        .sidebar-header .logo-text {color: white;font-size: 1.2rem;font-weight: bold;margin-bottom: 10px;}
        .sidebar a {display: block;padding: 12px 25px;color: rgba(255, 255, 255, 0.8);text-decoration: none;
            transition: all 0.3s ease;margin: 8px 12px;border-radius: 5px;font-size: 0.95rem;}
        .sidebar a:hover {background-color: #007bff;color: white;transform: translateX(5px);}
        .sidebar a i {margin-right: 10px;width: 20px;text-align: center;}
        .sidebar a.active {background-color: #007bff;color: white;}
        .main-content {margin-left: 250px;padding: 80px 25px 25px;min-height: 100vh;background-color: #f8f9fa;
            transition: margin-left 0.3s ease;}
        .dashboard-card {border-radius: 12px;box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);text-align: center;padding: 25px;
            background: white;transition: all 0.3s ease;height: 100%;margin-bottom: 24px;position: relative;overflow: hidden;}
        .dashboard-card::before {content: '';position: absolute;top: 0;left: 0;right: 0;height: 4px;background: currentColor;opacity: 0.7;}
        .dashboard-card:hover {transform: translateY(-5px);box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);}
        .card-icon {font-size: 2.5rem;margin-bottom: 20px;transition: transform 0.3s ease;color: inherit;}
        .dashboard-card:hover .card-icon {transform: scale(1.1);}
        .card-title {font-size: 1.1rem;font-weight: bold;margin-bottom: 15px;color: #2c3e50;}
        .quick-stats {margin-bottom: 30px;}
        .stats-card {background: white;border-radius: 10px;padding: 15px;margin-bottom: 20px;box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);}
        .stats-card .stats-icon {font-size: 2rem;margin-bottom: 10px;}
        .stats-card .stats-number {font-size: 1.5rem;font-weight: bold;}
        .stats-card .stats-label {color: #6c757d;font-size: 0.9rem;}
        .section-title {font-size: 1.2rem;font-weight: bold;margin-bottom: 20px;padding-bottom: 10px;
            border-bottom: 2px solid #007bff;color: #2c3e50;}
        .btn {padding: 8px 20px;border-radius: 5px;font-weight: 500;transition: all 0.3s ease;text-transform: uppercase;
            font-size: 0.8rem;letter-spacing: 0.5px;}
        .btn:hover {transform: translateY(-2px);box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);}
        .text-primary { color: #4e73df !important; }
        .text-success { color: #1cc88a !important; }
        .text-warning { color: #f6c23e !important; }
        .text-danger { color: #e74a3b !important; }
        .text-info { color: #36b9cc !important; }
        .text-secondary { color: #858796 !important; }
        .text-purple { color: #6f42c1 !important; }
        .text-orange { color: #fd7e14 !important; }
        .finance-card {background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);transition: all 0.3s ease;height: 100%;margin-bottom: 24px;}
        .finance-card:hover {transform: translateY(-5px);box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);}
        .finance-icon {font-size: 2.5rem;margin-bottom: 20px;transition: transform 0.3s ease;}
        .finance-card:hover .finance-icon {transform: scale(1.1);}
        .footer {margin-top: 50px;padding: 20px 0;text-align: center;color: #6c757d;border-top: 1px solid #dee2e6;}
        @media (max-width: 1199.98px) {.col-md-3 {flex: 0 0 33.333333%;max-width: 33.333333%;}}
        @media (max-width: 991.98px) {
            .col-md-3 {flex: 0 0 50%;max-width: 50%;}
            .navbar {width: calc(100% - 200px);}
            .sidebar {width: 200px;}
            .main-content {margin-left: 200px;}}
        @media (max-width: 767.98px) {
            .navbar {width: calc(100% - 80px);}
            .sidebar {width: 80px;}
            .sidebar .nav-link span,
            .sidebar-header .logo-text {display: none;}
            .sidebar-header img {max-width: 40px;}
            .main-content {margin-left: 80px;padding: 80px 15px 15px;}
            .col-md-3 {flex: 0 0 100%;max-width: 100%;}}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <span class="navbar-brand">Admin Dashboard</span>
        <!-- <div class="ml-auto user-info">
            Welcome, <?php echo isset($user['name']) ? htmlspecialchars($user['name']) : 'Admin'; ?>
        </div> -->
    </nav><br>
    <nav class="sidebar"> 
        <div class="sidebar-header">
            <img src="http://localhost/hostel_info/images/srmap.png" alt="SRMAP Logo">
            <div class="logo-text">SRMAP Hostel</div>
        </div>
        <a href="dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="admin_access_log.php">
            <i class="fas fa-users"></i>
            <span>Access Log</span>
        </a>
        <a href="update_profile.php">
            <i class="fas fa-user-edit"></i>
            <span>Update Profile</span>
        </a>
        <!-- <a href="rooms.php">
            <i class="fas fa-bed"></i>
            <span>Rooms</span>
        </a>
        <a href="mess.php">
            <i class="fas fa-utensils"></i>
            <span>Mess</span>
        </a>
        <a href="finance.php">
            <i class="fas fa-wallet"></i>
            <span>Finance</span>
        </a>
        <a href="reports.php">
            <i class="fas fa-chart-bar"></i> 
            <span>Reports</span>
        </a> -->
        <!-- <a href="settings.php">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a> -->
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
    <div class="main-content">
        <div class="container-fluid">
            <div class="row quick-stats">
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-users stats-icon text-primary"></i>
                        <?php
                        try {
                            $studentCountQuery = "SELECT COUNT(*) as total FROM student_signup";
                            $stmt = $conn->query($studentCountQuery);
                            if ($stmt) {
                                $row = $stmt->fetch_assoc();
                                $total_students = $row['total'];
                                echo '<div class="stats-number">'.number_format($total_students).'</div>';
                                echo '<div class="stats-label">Total Students</div>';
                            } else {echo '<div class="stats-error text-danger">Failed to fetch student count</div>';}
                        } catch (Exception $e) {
                            echo '<div class="stats-error text-danger">Database error: '.htmlspecialchars($e->getMessage()).'</div>';
                        }
                        ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-bed stats-icon text-success"></i>
                        <div class="stats-number">
                            <?php
                            $totalQuery = "SELECT 
                                SUM(CASE 
                                    WHEN sharing_type = 'Single' THEN 1
                                    WHEN sharing_type = '2-sharing' THEN 2
                                    WHEN sharing_type = '3-sharing' THEN 3
                                    WHEN sharing_type = '4-sharing' THEN 4
                                END) as total_beds
                                FROM rooms";
                            $totalResult = $conn->query($totalQuery);
                            $totalRow = $totalResult->fetch_assoc();
                            $totalBeds = $totalRow['total_beds'];
                            $occupiedQuery = "SELECT SUM(
                                CASE 
                                    WHEN sharing_type = 'Single' THEN 1
                                    WHEN sharing_type = '2-sharing' THEN 2
                                    WHEN sharing_type = '3-sharing' THEN 3
                                    WHEN sharing_type = '4-sharing' THEN 4
                                END - available_beds) as occupied_beds
                                FROM rooms";
                            $occupiedResult = $conn->query($occupiedQuery);
                            $occupiedRow = $occupiedResult->fetch_assoc();
                            $occupiedBeds = $occupiedRow['occupied_beds'];
                            $occupancyPercentage = ($totalBeds > 0) ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;
                            echo $occupancyPercentage . '%';
                            ?>
                        </div>
                        <div class="stats-label">Room Occupancy</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-exclamation-circle stats-icon text-warning"></i>
                        <div class="stats-number">
                            <?php
                            $activeComplaintsQuery = "SELECT COUNT(*) as active_count 
                                                    FROM complaints 
                                                    WHERE status IN ('pending', 'in_progress')";
                            $result = $conn->query($activeComplaintsQuery);
                            if ($result) {
                                $row = $result->fetch_assoc();
                                echo $row['active_count'];
                            } else {echo "0";}
                            ?>
                        </div>
                        <div class="stats-label">Active Complaints</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-wallet stats-icon text-danger"></i>
                        <div class="stats-number">
                            <?php
                            $pendingDuesQuery = "SELECT SUM(amount_due) as total_pending 
                                                FROM fee_dues 
                                                WHERE status IN ('pending', 'overdue')";
                            $result = $conn->query($pendingDuesQuery);
                            if ($result && $row = $result->fetch_assoc()) {
                                $totalPending = $row['total_pending'];
                                if ($totalPending >= 100000) {
                                    $inLakhs = $totalPending / 100000;
                                    echo "₹" . number_format($inLakhs, 1) . "L";
                                } else {echo "₹" . number_format($totalPending, 0);}
                            } else {echo "₹0"; }
                            ?>
                        </div>
                        <div class="stats-label">Pending Dues</div>
                    </div>
                </div>
            </div>
            <h2 class="section-title">Student Management</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-user-plus card-icon text-primary"></i>
                        <div class="card-title">Student Registration</div>
                        <a href="registration.php" class="btn btn-primary btn-sm">Manage</a>
                    </div> 
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-users card-icon text-success"></i>
                        <div class="card-title">Manage Students</div>
                        <a href="manage_students.php" class="btn btn-success btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-clock card-icon text-warning"></i>
                        <div class="card-title">Student Attendance</div>
                        <a href="attendance_details.php" class="btn btn-warning btn-sm">Check</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-history card-icon text-info"></i>
                        <div class="card-title">Access Log</div>
                        <a href="student_access_log.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div>
            </div><br>
            <h2 class="section-title">Facility Management</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-exclamation-circle card-icon text-danger"></i>
                        <div class="card-title">Complaints</div>
                        <a href="admin_complaints.php" class="btn btn-danger btn-sm">Manage</a>
                    </div>
                </div> 
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-bullhorn card-icon text-primary"></i>
                        <div class="card-title">Notice Board</div>
                        <a href="add_notice.php" class="btn btn-primary btn-sm">Update</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-utensils card-icon text-success"></i>
                        <div class="card-title">Manage Mess</div>
                        <a href="manage_mess.php" class="btn btn-success btn-sm">Manage</a>
                    </div>
                </div>
            </div>
            <h2 class="section-title mt-4">Room Management</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-bed card-icon text-info"></i>
                        <div class="card-title">Manage Rooms</div>
                        <a href="manage_rooms.php" class="btn btn-info btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-building card-icon" style="color: #6f42c1;"></i>
                        <div class="card-title">Room Operations</div>
                        <a href="room_operations.php" class="btn btn-sm" style="background-color: #6f42c1; color: white;">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-chart-bar card-icon" style="color: #6f42c1;"></i>
                        <div class="card-title">Room Statistics</div>
                        <a href="room_stats.php" class="btn btn-sm" style="background-color: #6f42c1; color: white;">View</a>
                    </div>
                </div>
            </div><br>
            <h2 class="section-title">Additional Services</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-walking card-icon text-primary"></i>
                        <div class="card-title">Outpass Management</div>
                        <a href="outpass_management.php" class="btn btn-primary btn-sm">Manage</a>
                    </div> 
                </div>
                <!-- <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-tshirt card-icon text-success"></i>
                        <div class="card-title">Laundry Service</div>
                        <a href="laundry.php" class="btn btn-success btn-sm">Manage</a>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-user-friends card-icon text-warning"></i>
                        <div class="card-title">Guest Registration</div>
                        <a href="guest_reg.php" class="btn btn-warning btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-graduation-cap card-icon text-purple"></i>
                        <div class="card-title">Academic Events</div>
                        <a href="academic_events.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-calendar-alt card-icon text-info"></i>
                        <div class="card-title">Events Calendar</div>
                        <a href="events.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div> -->
            </div><br>            
            <!-- <h2 class="section-title">Academic Management</h2>
            <div class="row">
                 <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-book card-icon text-success"></i>
                    Academic Section    <div class="card-title">Study Resources</div>
                        <a href="study_resources.php" class="btn btn-success btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-chalkboard-teacher card-icon text-warning"></i>
                        <div class="card-title">Mentorship Program</div>
                        <a href="mentorship.php" class="btn btn-warning btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-chart-line card-icon text-info"></i>
                        <div class="card-title">Performance Tracking</div>
                        <a href="performance.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div> 
            </div><br>  -->
            <h2 class="section-title">Staff Management</h2>
            <div class="row">
                <!-- <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-user-plus card-icon text-primary"></i>
                        <div class="card-title">Staff Registration</div>
                        <a href="staff_registration.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-users card-icon text-success"></i>
                        <div class="card-title">Manage Staff</div>
                        <a href="manage_staff.php" class="btn btn-success btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-history card-icon text-info"></i>
                        <div class="card-title">Staff Access Log</div>
                        <a href="staff_access_log.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-clock card-icon text-warning"></i>
                        <div class="card-title">Staff Attendance</div>
                        <a href="staff_attendance.php" class="btn btn-warning btn-sm">Check</a>
                    </div>
                </div> -->
            </div>
            <br>
            <!-- <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-calendar-alt card-icon text-danger"></i>
                        <div class="card-title">Leave Requests</div>
                        <a href="staff_leave_requests.php" class="btn btn-danger btn-sm">Review</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-calendar-check card-icon text-secondary"></i>
                        <div class="card-title">Shift Management</div>
                        <a href="shift_management.php" class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                </div> 
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-chart-line card-icon text-dark"></i>
                        <div class="card-title">Performance Reviews</div>
                        <a href="performance_reviews.php" class="btn btn-dark btn-sm">Track</a>
                    </div>
                </div> 
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-tasks card-icon text-success"></i>
                        <div class="card-title">Task Assignments</div>
                        <a href="task_assignments.php" class="btn btn-success btn-sm">Assign</a>
                    </div>
                </div>
            </div>
            <br> -->

            <!-- <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-money-bill card-icon text-warning"></i>
                        <div class="card-title">Payroll & Salary</div>
                        <a href="payroll.php" class="btn btn-warning btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-exclamation-triangle card-icon text-danger"></i>
                        <div class="card-title">Complaint Handling</div>
                        <a href="complaints.php" class="btn btn-danger btn-sm">Resolve</a>
                    </div>
                </div>
            </div><br> -->

            <!-- <h2 class="section-title">Reports & Analytics</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-file-alt card-icon text-primary"></i>
                        <div class="card-title">Monthly Reports</div>
                        <a href="monthly_reports.php" class="btn btn-primary btn-sm">Generate</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-chart-pie card-icon text-success"></i>
                        <div class="card-title">Analytics Dashboard</div>
                        <a href="analytics.php" class="btn btn-success btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-coins card-icon text-warning"></i>
                        <div class="card-title">Financial Reports</div>
                        <a href="financial_reports.php" class="btn btn-warning btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-tasks card-icon text-info"></i>
                        <div class="card-title">Audit Reports</div>
                        <a href="audit_reports.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div>
            </div> <br> -->
            <h2 class="section-title">Finance Management</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-money-bill-wave card-icon text-success"></i>
                        <div class="card-title">Fee Collection</div>
                        <a href="fee_collection.php" class="btn btn-success btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-hand-holding-usd card-icon text-danger"></i>
                        <div class="card-title">Due Payments</div>
                        <a href="fees_dues.php" class="btn btn-danger btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-receipt card-icon text-info"></i>
                        <div class="card-title">Payment History</div>
                        <a href="payment_history.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-file-invoice-dollar card-icon text-warning"></i>
                        <div class="card-title">Generate Invoice</div>
                        <a href="generate_invoice.php" class="btn btn-warning btn-sm">Generate</a>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-percentage card-icon text-primary"></i>
                        <div class="card-title">Fee Structure</div>
                        <a href="fee_structure.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-money-check-alt card-icon text-success"></i>
                        <div class="card-title">Scholarships</div>
                        <a href="scholarships.php" class="btn btn-success btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-university card-icon text-info"></i>
                        <div class="card-title">Bank Details</div>
                        <a href="bank_details.php" class="btn btn-info btn-sm">Update</a>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <i class="fas fa-comments-dollar card-icon text-purple"></i>
                        <div class="card-title">Payment Queries</div>
                        <a href="payment_queries.php" class="btn btn-primary btn-sm">Resolve</a>
                    </div>
                </div>
            </div><br>
            <footer class="footer">
                <div class="container">
                    <span class="text-muted">© 2025 SRMAP Hostel Management System. All rights reserved.</span>
                </div>
            </footer>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {$('[data-toggle="tooltip"]').tooltip();});
        document.addEventListener('DOMContentLoaded', function() {
            const currentLocation = location.href;
            const menuItems = document.querySelectorAll('.sidebar a');
            menuItems.forEach(item => {
                if(item.href === currentLocation) {item.classList.add('active');}
            });
        });
    </script>
</body>
</html>