<?php
session_start();
require 'admin_db.php'; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $recordsPerPage;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filterGender = isset($_GET['gender']) ? $conn->real_escape_string($_GET['gender']) : '';
$filterRegNo = isset($_GET['regNo']) ? $conn->real_escape_string($_GET['regNo']) : '';
$query = "SELECT * FROM student_signup WHERE 1=1";
if (!empty($search)) {
    $query .= " AND (firstName LIKE '%$search%' 
                OR lastName LIKE '%$search%' 
                OR email LIKE '%$search%' 
                OR contact LIKE '%$search%')";}
if (!empty($filterRegNo)) {$query .= " AND regNo LIKE '%$filterRegNo%'";}
if (!empty($filterGender)) {$query .= " AND gender = '$filterGender'"; }
$countQuery = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
$query .= " ORDER BY id LIMIT $offset, $recordsPerPage";
$result = $conn->query($query);
$studentDetails = null;
$studentLastLogin = null;
$studentRoomDetails = null;
$studentAttendance = null;
$studentOutpass = null;
if (isset($_GET['view_id'])) {
    $studentId = (int)$_GET['view_id'];
    $detailsQuery = "SELECT s.*, sd.* 
                    FROM student_signup s
                    LEFT JOIN student_details sd ON s.regNo = sd.reg_no
                    WHERE s.id = $studentId";
    $detailsResult = $conn->query($detailsQuery);
    $studentDetails = $detailsResult->fetch_assoc();
    $loginQuery = "SELECT * FROM login_details 
                  WHERE student_email = '{$studentDetails['email']}' 
                  AND login_status = 'success'
                  ORDER BY login_time DESC 
                  LIMIT 1";
    $loginResult = $conn->query($loginQuery);
    if ($loginResult && $loginResult->num_rows > 0) {$studentLastLogin = $loginResult->fetch_assoc();}
    $roomQuery = "SELECT rb.* 
                 FROM room_bookings rb 
                 WHERE rb.user_email = '{$studentDetails['email']}'
                 ORDER BY rb.booking_date DESC 
                 LIMIT 1";
    $roomResult = $conn->query($roomQuery);

    if ($roomResult && $roomResult->num_rows > 0) {$studentRoomDetails = $roomResult->fetch_assoc();}
    $attendanceQuery = "SELECT * FROM student_attendance 
                       WHERE regNo = '{$studentDetails['regNo']}'
                       ORDER BY attendance_date DESC, attendance_time DESC
                       LIMIT 5";
    $attendanceResult = $conn->query($attendanceQuery);
    if ($attendanceResult && $attendanceResult->num_rows > 0) {
        $studentAttendance = [];
        while ($row = $attendanceResult->fetch_assoc()) {$studentAttendance[] = $row;}
    }
    $outpassQuery = "SELECT * FROM outpass 
                    WHERE student_reg_no = '{$studentDetails['regNo']}'
                    ORDER BY applied_at DESC
                    LIMIT 3";
    $outpassResult = $conn->query($outpassQuery);
    if ($outpassResult && $outpassResult->num_rows > 0) {
        $studentOutpass = [];
        while ($row = $outpassResult->fetch_assoc()) {$studentOutpass[] = $row;}
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { font-family: 'Nunito', 'Segoe UI', sans-serif; background-color: #f8f9fa; color: #333; }
        .sidebar { background: linear-gradient(to bottom, #2c3e50, #1a2530); min-height: 100vh; padding-top: 30px; position: fixed; width: inherit; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar h4 { color: #ffffff; margin-bottom: 30px; font-weight: 700; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar a { display: block; color: rgba(255,255,255,0.8); padding: 12px 20px; margin-bottom: 5px; text-decoration: none; transition: all 0.3s ease; border-radius: 5px; margin-left: 8px; margin-right: 8px; }
        .sidebar a:hover, .sidebar a.active { background-color: rgba(255,255,255,0.1); color: #ffffff; border-left: 4px solid #3498db; transform: translateX(4px); }
        .sidebar a i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { padding: 30px; margin-left: 16.666667%; transition: all 0.3s; }
        .student-detail-container { background-color: #ffffff; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); display: none; transition: all 0.3s ease; overflow: hidden; }
        .student-detail-container.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .student-profile { text-align: center; margin-bottom: 25px; padding: 20px; background-color: #f8f9fa; border-radius: 10px; }
        .student-profile h4 { margin-top: 20px; font-weight: 700; color: #2c3e50; }
        .student-profile p { color: #7f8c8d; font-size: 0.9rem; }
        .detail-section { margin-bottom: 25px; border-bottom: 1px solid #eaeaea; padding-bottom: 20px; }
        .detail-section:last-child { border-bottom: none; margin-bottom: 0; }
        .detail-section h5 { color: #2c3e50; font-weight: 700; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #3498db; display: inline-block; }
        .detail-section h5 i { color: #3498db; }
        .detail-title { font-weight: 600; color: #34495e; font-size: 0.9rem; }
        .table { background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .table th { background-color: #f1f5f9; color: #2c3e50; border-top: none; font-weight: 600; padding: 12px 15px; }
        .table td { padding: 12px 15px; vertical-align: middle; }
        .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0,0,0,0.01); }
        .table-hover tbody tr:hover { background-color: rgba(52, 152, 219, 0.05); }
        .badge { padding: 6px 10px; font-weight: 500; font-size: 0.8rem; border-radius: 4px; }
        .badge-status-Approved, .badge-status-Present, .badge-status-confirmed { background-color: #2ecc71; color: white; }
        .badge-status-Pending, .badge-status-pending { background-color: #f39c12; color: white; }
        .badge-status-Rejected, .badge-status-Absent, .badge-status-rejected { background-color: #e74c3c; color: white; }
        .btn { border-radius: 5px; font-weight: 500; padding: 8px 15px; transition: all 0.3s ease; }
        .btn-primary { background-color: #3498db; border-color: #3498db; }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-secondary { background-color: #95a5a6; border-color: #95a5a6; }
        .btn-secondary:hover { background-color: #7f8c8d; border-color: #7f8c8d; }
        .btn-info { background-color: #3498db; border-color: #3498db; }
        .btn-info:hover { background-color: #2980b9; border-color: #2980b9; }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
        .btn-group .btn { margin-right: 5px; }
        .close-details { transition: all 0.3s ease; }
        .form-control { border-radius: 5px; padding: 10px 15px; border: 1px solid #dcdde1; transition: all 0.3s ease; }
        .form-control:focus { border-color: #3498db; box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25); }
        select.form-control { height: 43px; }
        .pagination { margin-top: 30px; }
        .page-link { color: #3498db; border: 1px solid #dee2e6; margin: 0 3px; border-radius: 5px; transition: all 0.3s ease; }
        .page-link:hover { background-color: #f1f5f9; color: #2980b9; transform: translateY(-2px); }
        .page-item.active .page-link { background-color: #3498db; border-color: #3498db; }
        .page-item.disabled .page-link { color: #95a5a6; background-color: #f8f9fa; }
        @media (max-width: 991px) { .sidebar { position: static; min-height: auto; margin-bottom: 20px; } .main-content { margin-left: 0; } }
        .fade-in { animation: fadeIn 0.5s; }
        .text-primary { color: #3498db !important; }
        .text-muted { color: #95a5a6 !important; }
        .lead { font-size: 1.1rem; font-weight: 300; }
        .card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .container-fluid, .row, .col-md-10, .col-md-2 { will-change: transform; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar">
                <h4 class="text-white text-center mb-4">Admin Panel</h4>
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
                <a href="manage_students.php" class="active"><i class="fas fa-user-graduate mr-2"></i>Manage Students</a>
                <a href="profile.php"><i class="fas fa-user-circle mr-2"></i>Profile</a>
                <a href="access_log.php"><i class="fas fa-clipboard-list mr-2"></i>Access Log</a>
                <a href="payments.php"><i class="fas fa-credit-card mr-2"></i>Payments</a>
                <!-- <a href="settings.php"><i class="fas fa-cog mr-2"></i>Settings</a> -->
                <a href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </div>
            <div class="col-md-10 main-content">
                <h2><i class="fas fa-user-graduate mr-2"></i>Manage Students</h2>
                <p class="lead">Total Students: <span class="badge badge-primary"><?php echo $totalRecords; ?></span></p>
                <div id="studentDetailContainer" class="student-detail-container <?php echo isset($_GET['view_id']) ? 'active' : ''; ?>">
                    <?php if ($studentDetails): ?>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3>Student Details</h3>
                                <a href="manage_students.php<?php echo !empty($_GET['page']) ? '?page=' . $_GET['page'] : ''; ?>" class="close-details btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times"></i> </a>
                            </div>
                            <hr>
                        </div>
                        <div class="col-md-3">
                            <div class="student-profile">
                                <!-- <img src="<?php echo !empty($studentDetails['profile_picture']) ? $studentDetails['profile_picture'] : 'assets/default-avatar.png'; ?>" alt="Profile Picture"> -->
                                <h4 class="mt-3"><?php echo htmlspecialchars($studentDetails['firstName'] . ' ' . $studentDetails['lastName']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($studentDetails['regNo']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="detail-section">
                                <h5><i class="fas fa-info-circle mr-2"></i>Basic Information</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Email:</span><br><?php echo htmlspecialchars($studentDetails['email']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Phone:</span><br><?php echo htmlspecialchars($studentDetails['contact']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Date of Birth:</span><br><?php echo date('F j, Y', strtotime($studentDetails['dob'])); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Gender:</span><br><?php echo htmlspecialchars($studentDetails['gender']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Nationality:</span><br><?php echo htmlspecialchars($studentDetails['nationality']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Registration Date:</span><br><?php echo date('F j, Y', strtotime($studentDetails['registration_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-section">
                                <h5><i class="fas fa-user-cog mr-2"></i>Additional Details</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Course:</span><br><?php echo !empty($studentDetails['course']) ? htmlspecialchars($studentDetails['course']) : 'Not specified'; ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Year of Study:</span><br><?php echo !empty($studentDetails['year_of_study']) ? htmlspecialchars($studentDetails['year_of_study']) : 'Not specified'; ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Emergency Contact:</span><br><?php echo !empty($studentDetails['emergency_phone']) ? htmlspecialchars($studentDetails['emergency_phone']) : 'Not specified'; ?></p>
                                    </div>
                                    <div class="col-md-12">
                                        <p><span class="detail-title">Address:</span><br><?php echo !empty($studentDetails['address']) ? htmlspecialchars($studentDetails['address']) : 'Not specified'; ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php if ($studentRoomDetails): ?>
                            <div class="detail-section">
                                <h5><i class="fas fa-home mr-2"></i>Hostel Information</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Hostel Name:</span><br><?php echo htmlspecialchars($studentRoomDetails['hostel_name']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Room Number:</span><br><?php echo htmlspecialchars($studentRoomDetails['room_number']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Floor:</span><br><?php echo htmlspecialchars($studentRoomDetails['floor']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Room Type:</span><br><?php echo htmlspecialchars($studentRoomDetails['is_ac'] ? 'AC' : 'Non-AC'); ?>, <?php echo htmlspecialchars($studentRoomDetails['sharing_type']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Booking Status:</span><br>
                                            <span class="badge badge-<?php echo $studentRoomDetails['status'] == 'confirmed' ? 'success' : ($studentRoomDetails['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($studentRoomDetails['status'])); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Booking Date:</span><br><?php echo date('F j, Y', strtotime($studentRoomDetails['booking_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="detail-section">
                                <h5><i class="fas fa-home mr-2"></i>Hostel Information</h5>
                                <p class="text-muted">No hostel booking information available.</p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-section">
                                <h5><i class="fas fa-calendar-check mr-2"></i>Recent Attendance</h5>
                                <?php if ($studentAttendance): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th> <th>Day</th> <th>Time</th> <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentAttendance as $attendance): ?>
                                            <tr>
                                                <td><?php echo date('d-m-Y', strtotime($attendance['attendance_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($attendance['attendance_day']); ?></td>
                                                <td><?php echo date('h:i A', strtotime($attendance['attendance_time'])); ?></td>
                                                <td>
                                                    <span class="badge badge-status-<?php echo htmlspecialchars($attendance['status']); ?>">
                                                        <?php echo htmlspecialchars($attendance['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No recent attendance records found.</p>
                                <?php endif; ?>
                            </div>
                            <div class="detail-section">
                                <h5><i class="fas fa-clipboard-list mr-2"></i>Recent Outpass Applications</h5>
                                <?php if ($studentOutpass): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Type</th> <th>Out Date/Time</th> <th>In Date/Time</th> <th>Destination</th> <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentOutpass as $outpass): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($outpass['outpass_type']); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($outpass['out_date'])) . ' ' . date('h:i A', strtotime($outpass['out_time'])); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($outpass['in_date'])) . ' ' . date('h:i A', strtotime($outpass['in_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($outpass['destination']); ?></td>
                                                <td>
                                                    <span class="badge badge-status-<?php echo htmlspecialchars($outpass['status']); ?>">
                                                        <?php echo htmlspecialchars($outpass['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No recent outpass applications found.</p>
                                <?php endif; ?>
                            </div>
                            <div class="detail-section">
                                <h5><i class="fas fa-sign-in-alt mr-2"></i>Last Login Activity</h5>
                                <?php if ($studentLastLogin): ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Last Login Time:</span><br><?php echo date('F j, Y h:i A', strtotime($studentLastLogin['login_time'])); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">IP Address:</span><br><?php echo htmlspecialchars($studentLastLogin['ip_address']); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p><span class="detail-title">Logout Time:</span><br>
                                            <?php 
                                            if ($studentLastLogin['logout_time']) { echo date('F j, Y h:i A', strtotime($studentLastLogin['logout_time']));} 
                                            else { echo 'Session still active or improper logout';}
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No login activity recorded.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search students" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="regNo" class="form-control" placeholder="Registration No." value="<?php echo htmlspecialchars(isset($_GET['regNo']) ? $_GET['regNo'] : ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="gender" class="form-control">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo $filterGender == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $filterGender == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary"> <i class="fas fa-search mr-1"></i> Filter </button>
                            <a href="manage_students.php" class="btn btn-secondary"> <i class="fas fa-redo mr-1"></i> Reset </a>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>ID</th> <th>Name</th> <th>Email</th> <th>Phone</th> <th>Reg No</th> <th>Gender</th> <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result->num_rows > 0) {
                                while ($student = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['firstName'] . ' ' . $student['lastName']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['contact']); ?></td>
                                    <td><?php echo htmlspecialchars($student['regNo']); ?></td>
                                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?view_id=<?php echo $student['id']; ?><?php echo !empty($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye mr-1"></i> View </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            } else {
                            ?>
                                <tr> <td colspan="7" class="text-center">No students found</td> </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&gender=<?php echo urlencode($filterGender); ?>&regNo=<?php echo urlencode(isset($_GET['regNo']) ? $_GET['regNo'] : ''); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span> </a>
                        </li>
                        <?php endif; ?>
                        <?php
                        $range = 2;
                        $startPage = max(1, $page - $range);
                        $endPage = min($totalPages, $page + $range);
                        if ($startPage > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&gender=' . urlencode($filterGender) . '&regNo=' . urlencode(isset($_GET['regNo']) ? $_GET['regNo'] : '') . '">1</a></li>';
                            if ($startPage > 2) {echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';}
                        }                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&gender=' . urlencode($filterGender) . '&regNo=' . urlencode(isset($_GET['regNo']) ? $_GET['regNo'] : '') . '">' . $i . '</a></li>';
                        }                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';}
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&search=' . urlencode($search) . '&gender=' . urlencode($filterGender) . '&regNo=' . urlencode(isset($_GET['regNo']) ? $_GET['regNo'] : '') . '">' . $totalPages . '</a></li>';
                        }?>
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&gender=<?php echo urlencode($filterGender); ?>&regNo=<?php echo urlencode(isset($_GET['regNo']) ? $_GET['regNo'] : ''); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            <?php if (isset($_GET['view_id'])): ?>
            $('html, body').animate({
                scrollTop: $("#studentDetailContainer").offset().top - 20
            }, 500);
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>