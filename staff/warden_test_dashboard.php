<?php
session_start();
include 'staff_db.php';
// if (!isset($_SESSION['staff_id'])) {
//     header("Location: staff_test_login.php");
//     exit();
// }
$staffId = $_SESSION['staff_id'];
$staffName = $_SESSION['name'];
$staffPosition = $_SESSION['position'];
$hostel = $_SESSION['hostel'];
if ($staffPosition !== 'Warden') {
    header("Location: staff_dashboard.php");
    exit();
} 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warden Dashboard - <?php echo htmlspecialchars($hostel); ?> Hostel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;display: flex;margin: 0;padding: 0;background-color: #f8f9fa;}
        .sidebar {width: 250px;height: 100vh;background-color: #343a40;color: #fff;position: fixed;overflow-y: auto;transition: all 0.3s;}
        .sidebar-header {padding: 20px 15px;background: #212529;text-align: center;}
        .sidebar-header h3 {margin: 0;font-size: 18px;font-weight: 700;}
        .user-info {padding: 15px;display: flex;align-items: center;}
        .user-avatar {width: 50px;height: 50px;background-color: #6c757d;border-radius: 50%;
            display: flex;justify-content: center;align-items: center;margin-right: 15px;}
        .user-avatar i {font-size: 24px;}
        .user-details {flex-grow: 1;}
        .user-name {font-weight: bold;font-size: 15px;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;}
        .user-role, .user-id {font-size: 12px;color: #adb5bd;}
        .sidebar-divider {margin: 10px 15px;border-top: 1px solid #495057;}
        .sidebar-heading {padding: 0 15px;font-size: 12px;color: #adb5bd;text-transform: uppercase;margin-bottom: 10px;}
        .nav-item {margin-bottom: 5px;}
        .nav-link {color: #ced4da;padding: 10px 15px;display: flex;align-items: center;text-decoration: none;transition: all 0.2s;}
        .nav-link:hover {background-color: #495057;color: #fff;}
        .nav-link.active {background-color: #0d6efd;color: #fff;}
        .nav-link i {margin-right: 10px;width: 20px;text-align: center;}
        .main-content {flex-grow: 1;margin-left: 250px;padding: 20px;transition: all 0.3s;display: flex;flex-direction: column;min-height: 100vh;}
        .content-wrapper {flex: 1;}
        .page-header {display: flex;justify-content: space-between;align-items: center;
            margin-bottom: 30px;padding-bottom: 15px;border-bottom: 1px solid #dee2e6;}
        .page-header h1 {margin: 0;font-size: 24px;font-weight: 700;}
        .hostel-badge {background-color: #0d6efd;color: white;padding: 5px 15px;border-radius: 20px;font-size: 14px;}
        .dashboard-cards {display: flex;flex-wrap: wrap;margin: 0 -15px;}
        .dashboard-card {background-color: #fff;border-radius: 5px;box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;text-align: center;height: 100%;transition: transform 0.3s;}
        .dashboard-card:hover {transform: translateY(-5px);}
        .card-icon {font-size: 40px;margin-bottom: 15px;}
        .card-title {font-weight: 600;margin-bottom: 15px;}
        .text-primary { color: #0d6efd; }
        .text-success { color: #198754; }
        .text-info { color: #0dcaf0; }
        .text-warning { color: #ffc107; }
        .text-purple { color: #6f42c1; }
        .text-orange { color: #fd7e14; }
        .footer {background-color: #f8f9fa;color: #343a40;padding: 20px 0;margin-top: 40px;border-top: 1px solid #dee2e6;}
        .footer-content {display: flex;justify-content: space-between;align-items: center;flex-wrap: wrap;
            max-width: 1200px;margin: 0 auto;padding: 0 15px;}
        .footer-text {font-size: 16px;font-weight: 500;}
        .footer-links {display: flex;= gap: 20px;}
        .footer-links a {color: #0d6efd;text-decoration: none;font-size: 14px;transition: all 0.2s;display: flex;align-items: center;gap: 5px;}
        .footer-links a:hover { color: #0a58ca;transform: translateY(-2px);}
        .footer-links i {font-size: 16px;}
        .copyright { margin-top: 15px;font-size: 13px; color: #6c757d;text-align: center; width: 100%}
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>HOSTEL MANAGEMENT</h3>
        </div> 
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($staffName); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($staffPosition); ?></div>
                <div class="user-id">ID: <?php echo htmlspecialchars($staffId); ?></div>
            </div>
        </div>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Main</div>
        <div class="nav-item">
            <a class="nav-link active" href="warden_test_dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="outpass_management.php">
                <i class="fas fa-fw fa-sign-out-alt"></i>
                <span>Outpass Management</span>
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="room_management.php">
                <i class="fas fa-fw fa-door-open"></i>
                <span>Room Management</span>
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="service_requests.php">
                <i class="fas fa-fw fa-tools"></i>
                <span>Service Requests</span>
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="mark_attendance.php">
                <i class="fas fa-fw fa-clipboard-check"></i>
                <span>Student Attendance</span>
            </a>
        </div>
        <div class="nav-item">
            <a class="nav-link" href="staff_management.php">
                <i class="fas fa-fw fa-id-card"></i>
                <span>Staff Management</span>
            </a>
        </div>
        <hr class="sidebar-divider">
        <!-- <div class="sidebar-heading">Settings</div> -->
        <!-- <div class="nav-item">
            <a class="nav-link" href="profile.php">
                <i class="fas fa-fw fa-user-circle"></i>
                <span>Profile</span>
            </a>
        </div> -->
        <div class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-fw fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1>Warden Dashboard</h1>
                <span class="hostel-badge"><?php echo htmlspecialchars($hostel); ?> Hostel</span>
            </div>
            <div class="row dashboard-cards">
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-tools card-icon text-primary"></i>
                        <div class="card-title">Service Requests</div>
                        <a href="service_requests.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-clipboard-check card-icon text-success"></i>
                        <div class="card-title">Student Attendance</div>
                        <a href="mark_attendance.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-clipboard-list card-icon text-success"></i>
                        <div class="card-title">View Attendance</div>
                        <a href="attendance_reports.php" class="btn btn-success btn-sm">View</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-sign-out-alt card-icon text-info"></i>
                        <div class="card-title">Outpass Management</div>
                        <a href="outpass_management.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-door-open card-icon text-warning"></i>
                        <div class="card-title">Room Management</div>
                        <a href="room_management.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-id-card card-icon text-purple"></i>
                        <div class="card-title">Staff Details</div>
                        <a href="staff_management.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-users card-icon text-primary"></i>
                        <div class="card-title">Students</div>
                        <a href="manage_students.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>  
                <!-- <div class="col-md-3 mb-4">
                    <div class="dashboard-card">
                        <i class="fas fa-bell card-icon text-orange"></i>
                        <div class="card-title">Notifications</div>
                        <a href="notifications.php" class="btn btn-primary btn-sm">View</a>
                    </div>
                </div> -->
            </div>
        </div>
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-text">
                    <strong><?php echo htmlspecialchars($hostel); ?> Hostel Management System</strong>
                </div>
                <div class="footer-links">
                    <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                    <a href="contact.php"><i class="fas fa-envelope"></i> Contact Admin</a>
                    <a href="privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a>
                </div>
            </div>
            <div class="copyright">&copy; <?php echo date('Y'); ?> Hostel Management System. All rights reserved.</div>
        </footer>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>