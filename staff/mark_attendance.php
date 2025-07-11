
<?php
session_start();
include 'staff_db.php';

// Ensure only wardens can access this page
if (!isset($_SESSION['staff_id']) || $_SESSION['position'] !== 'Warden') {
    header("Location: staff_dashboard.php");
    exit();
}

$staffId = $_SESSION['staff_id'];
$hostel = $_SESSION['hostel'];
$message = "";

// Get current date and day
$currentDate = date("Y-m-d");
$currentDay = date("l");

// Fetch available floors
$floorsQuery = $conn->prepare("SELECT DISTINCT floor FROM rooms WHERE hostel_name = ? ORDER BY floor");
$floorsQuery->bind_param("s", $hostel);
$floorsQuery->execute();
$floorsResult = $floorsQuery->get_result();
$floors = $floorsResult->fetch_all(MYSQLI_ASSOC);

$selectedFloor = isset($_GET['floor']) ? intval($_GET['floor']) : null;
$selectedRoom = isset($_GET['room_number']) ? intval($_GET['room_number']) : null;
$rooms = [];
$students = [];

// Fetch rooms on selected floor
if ($selectedFloor) {
    $roomsQuery = $conn->prepare("
        SELECT room_number, sharing_type, is_ac 
        FROM rooms 
        WHERE hostel_name = ? AND floor = ? 
        ORDER BY room_number
    ");
    $roomsQuery->bind_param("si", $hostel, $selectedFloor);
    $roomsQuery->execute();
    $roomsResult = $roomsQuery->get_result();
    $rooms = $roomsResult->fetch_all(MYSQLI_ASSOC);
}

// Fetch students in selected room - KEY FIX: Convert room_number to string
if ($selectedRoom) {
    // Convert room number to string to match VARCHAR in database
    $roomNumberStr = (string)$selectedRoom;
    
    $studentsQuery = $conn->prepare("
        SELECT sd.reg_no, 
               s.firstName, 
               s.lastName, 
               sd.course, 
               sd.year_of_study
        FROM student_details sd
        JOIN student_signup s ON sd.reg_no = s.regNo
        WHERE sd.hostel = ? AND sd.room_number = ?
        ORDER BY s.firstName
    ");
    $studentsQuery->bind_param("ss", $hostel, $roomNumberStr); // Changed to "ss" for string, string
    $studentsQuery->execute();
    $studentsResult = $studentsQuery->get_result();
    $students = $studentsResult->fetch_all(MYSQLI_ASSOC);
}

// Handle attendance submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_attendance'])) {
    $reg_no = $_POST['reg_no'];
    $status = $_POST['status'];
    $currentTime = date("H:i:s");

    // Check if attendance already marked
    $checkAttendanceQuery = $conn->prepare("
        SELECT id 
        FROM student_attendance 
        WHERE regNo = ? AND attendance_date = ?
    ");
    $checkAttendanceQuery->bind_param("ss", $reg_no, $currentDate);
    $checkAttendanceQuery->execute();
    $checkAttendanceResult = $checkAttendanceQuery->get_result();

    if ($checkAttendanceResult->num_rows > 0) {
        $message = "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                        <i class='bi bi-exclamation-triangle-fill me-2'></i> Attendance already marked for <strong>$reg_no</strong> today.
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } else {
        // Fetch student name
        $studentQuery = $conn->prepare("SELECT firstName, lastName FROM student_signup WHERE regNo = ?");
        $studentQuery->bind_param("s", $reg_no);
        $studentQuery->execute();
        $result = $studentQuery->get_result();
        $studentData = $result->fetch_assoc();
        $studentName = $studentData ? $studentData['firstName'] . ' ' . $studentData['lastName'] : "Unknown";

        // Convert room number to string for consistency
        $roomNumberStr = (string)$selectedRoom;

        // Insert attendance record
        $attendanceQuery = $conn->prepare("
            INSERT INTO student_attendance 
            (serial_number, regNo, student_name, hostel_name, room_number, attendance_date, attendance_day, attendance_time, status, marked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $serialNumber = uniqid('ATT_'); // Unique serial number

        $attendanceQuery->bind_param(
            "ssssssssss",
            $serialNumber,
            $reg_no,
            $studentName,
            $hostel,
            $roomNumberStr,
            $currentDate,
            $currentDay,
            $currentTime,
            $status,
            $staffId
        );

        if ($attendanceQuery->execute()) {
            $statusIcon = ($status == 'Present') ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <i class='bi $statusIcon me-2'></i> Attendance marked as <strong>$status</strong> for <strong>$reg_no</strong>.
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                            <i class='bi bi-x-octagon-fill me-2'></i> Error marking attendance. Please try again.
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - <?php echo htmlspecialchars($hostel); ?> Hostel</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
    --primary-color: #3a5a78;
    --secondary-color: #f8f9fa;
    --accent-color: #4d94ff;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --dark-color: #343a40;
    --light-color: #f8f9fa;
    --gray-color: #6c757d;
    --border-radius: 8px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: #f5f7fa;
    display: flex;
    min-height: 100vh;
    margin: 0;
    padding: 0;
}

/* Sidebar Styles */
.sidebar {
    width: 270px;
    background-color: var(--primary-color);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    transition: var(--transition);
    z-index: 1000;
}

.sidebar-header {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background-color: rgba(0, 0, 0, 0.1);
}

.sidebar-header h4 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.sidebar-nav li {
    margin-bottom: 5px;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: var(--transition);
    border-left: 4px solid transparent;
}

.sidebar-nav a:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--accent-color);
}

.sidebar-nav a.active {
    background-color: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: var(--accent-color);
    font-weight: 500;
}

.sidebar-nav i {
    margin-right: 10px;
    font-size: 1.1rem;
}

/* Content Styles */
.content {
    flex: 1;
    margin-left: 270px;
    padding: 20px 30px;
    transition: var(--transition);
}

/* Page Header */
.page-header {
    background-color: white;
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    box-shadow: var(--box-shadow);
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    align-items: center;
}

.page-header h2 {
    margin: 0;
    font-size: 1.7rem;
    color: var(--primary-color);
    font-weight: 600;
}

.stats-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    background-color: var(--light-color);
    padding: 8px 15px;
    border-radius: var(--border-radius);
    min-width: 100px;
    text-align: center;
}

.time-label {
    font-size: 0.8rem;
    color: var(--gray-color);
    margin-bottom: 2px;
}

/* Forms */
.select-floor-form {
    background-color: white;
    padding: 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    box-shadow: var(--box-shadow);
}

.form-select, .form-control {
    border-radius: var(--border-radius);
    padding: 10px 15px;
    border: 1px solid #ced4da;
    transition: var(--transition);
}

.form-select:focus, .form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 0.2rem rgba(77, 148, 255, 0.25);
}

/* Room Grid */
.room-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.room-card {
    background-color: white;
    border: 1px solid #e0e0e0;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--box-shadow);
    transition: var(--transition);
    cursor: pointer;
}

.room-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    border-color: var(--accent-color);
}

.room-card.border-primary {
    border: 2px solid var(--accent-color);
}

.room-card-header {
    background-color: var(--primary-color);
    color: white;
    padding: 10px 15px;
    font-weight: 600;
    text-align: center;
}

.room-card-body {
    padding: 15px;
}

.room-card-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.room-card-info .label {
    color: var(--gray-color);
    font-size: 0.85rem;
}

.room-card-info .value {
    font-weight: 500;
}

.badge-ac {
    background-color: #e6f7ff;
    color: #0086ff;
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.badge-non-ac {
    background-color: #f5f5f5;
    color: #666;
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}

/* Student Card */
.student-card {
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--box-shadow);
    margin-bottom: 20px;
}

.student-card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.student-card-header h4 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.25rem;
    font-weight: 600;
}

.time-display {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--gray-color);
    font-size: 0.9rem;
}

.time-display div {
    display: flex;
    align-items: center;
    gap: 5px;
}

.time-value {
    font-weight: 500;
    color: var(--dark-color);
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    background-color: #f8f9fa;
    color: var(--dark-color);
    font-weight: 600;
    border-top: 0;
}

.table td {
    vertical-align: middle;
}

/* Attendance Form */
.attendance-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-select {
    max-width: 110px;
    font-weight: 500;
}

/* Footer */
.footer {
    text-align: center;
    padding: 20px 0;
    margin-top: 30px;
    color: var(--gray-color);
    font-size: 0.9rem;
    border-top: 1px solid #eaeaea;
}

/* Animation */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Alert Styles */
.alert {
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.alert-dismissible .btn-close {
    padding: 0.85rem 1rem;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 250px;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .content {
        margin-left: 0;
        padding: 15px;
    }
    
    .page-header {
        padding: 15px;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stats-row {
        width: 100%;
        justify-content: space-between;
        margin-top: 15px;
    }
    
    .room-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    
    .student-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .time-display {
        width: 100%;
        justify-content: flex-start;
    }
    
    .attendance-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .status-select {
        max-width: 100%;
        margin-bottom: 5px;
    }
    
    .toggle-sidebar {
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 1001;
        padding: 8px;
        border-radius: 4px;
        background-color: var(--primary-color);
        border: none;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .toggle-sidebar i {
        font-size: 1.2rem;
    }
}

/* Mobile optimizations */
@media (max-width: 480px) {
    .room-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .stat-item {
        min-width: auto;
        flex: 1;
    }
}

/* Utility Classes */
.btn-primary {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
}

.btn-primary:hover {
    background-color: #3383ff;
    border-color: #3383ff;
}

/* Print Styles */
@media print {
    .sidebar, .select-floor-form, .footer {
        display: none;
    }
    
    .content {
        margin-left: 0;
        padding: 0;
    }
    
    .page-header {
        box-shadow: none;
    }
    
    .student-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="bi bi-building"></i> Warden Panel</h4>
    </div>
    <ul class="sidebar-nav">
        <li>
            <a href="warden_test_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'staff_dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="warden_attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'warden_attendance.php' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i> Mark Attendance
            </a>
        </li>
        <li>
            <a href="attendance_reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance_reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> Attendance Reports
            </a>
        </li>
        <li>
            <a href="student_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_management.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Student Management
            </a>
        </li>
        <li>
            <a href="room_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'room_management.php' ? 'active' : ''; ?>">
                <i class="bi bi-house-door"></i> Room Management
            </a>
        </li>
        <li>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle"></i> My Profile
            </a>
        </li>
        <li>
            <a href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</div>

<!-- Content -->
<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="bi bi-calendar-check me-2"></i><?php echo htmlspecialchars($hostel); ?> Hostel - Attendance</h2>
        <div class="stats-row">
            <div class="stat-item">
                <span class="time-label">Date:</span>
                <strong><?php echo date("F j, Y", strtotime($currentDate)); ?></strong>
            </div>
            <div class="stat-item">
                <span class="time-label">Day:</span>
                <strong><?php echo $currentDay; ?></strong>
            </div>
            <div class="stat-item">
                <span class="time-label">Time:</span>
                <strong id="currentTime"><?php echo date("H:i:s"); ?></strong>
            </div>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if ($message) echo $message; ?>

    <!-- Select Floor Form -->
    <div class="select-floor-form">
        <form method="get" class="row align-items-end">
            <div class="col-md-6">
                <label for="floor" class="form-label">
                    <i class="bi bi-layers me-2"></i>Select Floor
                </label>
                <select name="floor" id="floor" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Select Floor --</option>
                    <?php foreach ($floors as $floor) { ?>
                        <option value="<?php echo $floor['floor']; ?>" <?php if ($selectedFloor == $floor['floor']) echo "selected"; ?>>
                            Floor <?php echo $floor['floor']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <?php if ($selectedFloor) { ?>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-info p-2">
                        <i class="bi bi-building me-1"></i> 
                        <?php echo count($rooms); ?> room<?php echo count($rooms) != 1 ? 's' : ''; ?> on Floor <?php echo $selectedFloor; ?>
                    </span>
                </div>
            <?php } ?>
        </form>
    </div>

    <!-- Display Rooms as Cards -->
    <?php if ($selectedFloor && !empty($rooms)) { ?>
        <div class="card fade-in">
            <div class="card-header">
                <i class="bi bi-door-open me-2"></i>Rooms on Floor <?php echo $selectedFloor; ?>
            </div>
            <div class="card-body">
                <div class="room-grid">
                    <?php foreach ($rooms as $room) { ?>
                        <a href="?floor=<?php echo $selectedFloor; ?>&room_number=<?php echo $room['room_number']; ?>" class="text-decoration-none">
                            <div class="room-card <?php echo ($selectedRoom == $room['room_number']) ? 'border-primary' : ''; ?>">
                                <div class="room-card-header">
                                    Room <?php echo $room['room_number']; ?>
                                </div>
                                <div class="room-card-body">
                                    <div class="room-card-info">
                                        <span class="label">Sharing Type</span>
                                        <span class="value"><?php echo $room['sharing_type']; ?></span>
                                    </div>
                                    <div class="room-card-info">
                                        <span class="label">Room Type</span>
                                        <span class="value">
                                            <?php if ($room['is_ac']) { ?>
                                                <span class="badge-ac">AC</span>
                                            <?php } else { ?>
                                                <span class="badge-non-ac">Non-AC</span>
                                            <?php } ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <!-- Display Students of the Selected Room -->
    <?php if ($selectedRoom && !empty($students)) { ?>
        <div class="student-card fade-in">
            <div class="student-card-header">
                <h4><i class="bi bi-people me-2"></i>Students in Room <?php echo $selectedRoom; ?></h4>
                <div class="time-display">
                    <div>
                        <i class="bi bi-calendar2-event"></i>
                        <span class="time-value"><?php echo date("F j, Y", strtotime($currentDate)); ?></span>
                    </div>
                    |
                    <div>
                        <i class="bi bi-clock"></i>
                        <span class="time-value" id="currentTimeDisplay"><?php echo date("H:i:s"); ?></span>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col" width="15%">Reg No</th>
                            <th scope="col" width="25%">Name</th>
                            <th scope="col" width="20%">Course</th>
                            <th scope="col" width="10%">Year</th>
                            <th scope="col" width="30%">Mark Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student) { ?>
                            <tr>
                                <td><strong><?php echo $student['reg_no']; ?></strong></td>
                                <td><?php echo $student['firstName'] . " " . $student['lastName']; ?></td>
                                <td><?php echo $student['course']; ?></td>
                                <td class="text-center"><?php echo $student['year_of_study']; ?></td>
                                <td>
                                    <form method="post" class="attendance-form">
                                        <input type="hidden" name="reg_no" value="<?php echo $student['reg_no']; ?>">
                                        <select name="status" class="form-select status-select">
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                        </select>
                                        <button type="submit" name="mark_attendance" class="btn btn-primary">
                                            <i class="bi bi-check2-circle me-1"></i> Submit
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php } elseif ($selectedRoom && empty($students)) { ?>
        <div class="alert alert-info fade-in" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> No students found in Room <?php echo $selectedRoom; ?>.
        </div>
    <?php } ?>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($hostel); ?> Hostel Management System. All rights reserved.</p>
    </div>
</div>

<!-- Bootstrap JS and Popper.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to dismiss alerts after 3 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 3000);
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Call updateTime immediately to avoid delay
        updateTime();
    });

    // Function to update the time every second
    function updateTime() {
        const now = new Date();
        const utcOffset = 5.5 * 60 * 60 * 1000; // IST is UTC+5:30
        const istTime = new Date(now.getTime() + utcOffset);

        const hours = istTime.getUTCHours().toString().padStart(2, '0');
        const minutes = istTime.getUTCMinutes().toString().padStart(2, '0');
        const seconds = istTime.getUTCSeconds().toString().padStart(2, '0');
        const timeString = hours + ':' + minutes + ':' + seconds;

        const timeElements = document.querySelectorAll('#currentTime, #currentTimeDisplay');
        timeElements.forEach(element => {
            if (element) element.textContent = timeString;
        });

        setTimeout(updateTime, 1000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Call updateTime immediately to avoid delay
        updateTime();
    });
    // Mobile sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        // For mobile view - add toggle button
        if (window.innerWidth <= 768) {
            const content = document.querySelector('.content');
            const toggleBtn = document.createElement('button');
            toggleBtn.classList.add('btn', 'btn-primary', 'position-fixed', 'toggle-sidebar');
            toggleBtn.style.cssText = 'top: 10px; left: 10px; z-index: 1000; padding: 0.5rem; border-radius: 8px;';
            toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
            document.body.appendChild(toggleBtn);
            
            toggleBtn.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const toggleBtn = document.querySelector('.toggle-sidebar');
                if (!sidebar.contains(event.target) && event.target !== toggleBtn && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            });
        }
        
        // Status select color change
        const statusSelects = document.querySelectorAll('.status-select');
        statusSelects.forEach(select => {
            select.addEventListener('change', function() {
                if (this.value === 'Present') {
                    this.style.color = 'var(--success-color)';
                    this.style.fontWeight = '600';
                } else if (this.value === 'Absent') {
                    this.style.color = 'var(--danger-color)';
                    this.style.fontWeight = '600';
                }
            });
            
            // Set initial color
            if (select.value === 'Present') {
                select.style.color = 'var(--success-color)';
                select.style.fontWeight = '600';
            } else if (select.value === 'Absent') {
                select.style.color = 'var(--danger-color)';
                select.style.fontWeight = '600';
            }
        });
        
        // Add animation to room cards
        const roomCards = document.querySelectorAll('.room-card');
        roomCards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.05) + 's';
        });
    });
</script>
</body>
</html>

