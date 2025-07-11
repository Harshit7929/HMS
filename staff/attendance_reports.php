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

// Get current date
$currentDate = date('Y-m-d');

// Get filter values
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterRoom = isset($_GET['room_number']) ? intval($_GET['room_number']) : '';
$filterDate = isset($_GET['date']) ? $_GET['date'] : $currentDate;

// Fetch attendance records
$sql = "SELECT regNo, student_name, room_number, attendance_date, attendance_day, attendance_time, status 
        FROM student_attendance 
        WHERE hostel_name = ? AND attendance_date = ?";

if (!empty($searchQuery)) {
    $sql .= " AND (regNo LIKE ? OR student_name LIKE ?)";
}
if (!empty($filterRoom)) {
    $sql .= " AND room_number = ?";
}

$sql .= " ORDER BY room_number ASC, attendance_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($searchQuery) && !empty($filterRoom)) {
    $searchQueryWildcard = "%$searchQuery%";
    $stmt->bind_param("ssssi", $hostel, $filterDate, $searchQueryWildcard, $searchQueryWildcard, $filterRoom);
} elseif (!empty($searchQuery)) {
    $searchQueryWildcard = "%$searchQuery%";
    $stmt->bind_param("ssss", $hostel, $filterDate, $searchQueryWildcard, $searchQueryWildcard);
} elseif (!empty($filterRoom)) {
    $stmt->bind_param("ssi", $hostel, $filterDate, $filterRoom);
} else {
    $stmt->bind_param("ss", $hostel, $filterDate);
}

$stmt->execute();
$result = $stmt->get_result();
$attendanceRecords = $result->fetch_all(MYSQLI_ASSOC);

// Fetch all rooms
$roomsQuery = $conn->prepare("SELECT room_number, sharing_type, is_ac FROM rooms WHERE hostel_name = ? ORDER BY room_number");
$roomsQuery->bind_param("s", $hostel);
$roomsQuery->execute();
$roomsResult = $roomsQuery->get_result();
$rooms = $roomsResult->fetch_all(MYSQLI_ASSOC);

// Fetch all students
$studentsQuery = $conn->prepare("SELECT s.regNo, s.firstName, s.lastName, sd.course, sd.year_of_study, sd.room_number 
                                 FROM student_signup s 
                                 JOIN student_details sd ON s.regNo = sd.reg_no
                                 WHERE sd.hostel = ?
                                 ORDER BY sd.room_number, s.firstName");
$studentsQuery->bind_param("s", $hostel);
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();
$students = $studentsResult->fetch_all(MYSQLI_ASSOC);

// Calculate overall attendance
$totalStudents = count(array_unique(array_column($attendanceRecords, 'regNo')));
$presentStudents = count(array_unique(array_column(array_filter($attendanceRecords, function ($record) {
    return $record['status'] === 'Present';
}), 'regNo')));
$absentStudents = $totalStudents - $presentStudents;

// Calculate attendance percentage
$attendancePercentage = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - <?php echo htmlspecialchars($hostel); ?> Hostel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #16a085;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            padding-bottom: 40px;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: linear-gradient(135deg, var(--secondary-color), #34495e);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .sidebar-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 0;
            transition: var(--transition);
        }

        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Content Area */
        .content {
            margin-left: 280px;
            padding: 30px;
            transition: var(--transition);
        }

        /* Page Header */
        .page-header {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            border-left: 5px solid var(--primary-color);
        }

        .page-header h2 {
            margin: 0;
            color: var(--secondary-color);
            font-weight: 600;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 30px;
        }

        .card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            padding: 15px 20px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .card-body {
            padding: 20px;
        }

        /* Filter Section */
        .filters-card {
            background-color: white;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .filters-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-control, .btn {
            border-radius: var(--border-radius);
            padding: 10px 15px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: var(--transition);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-primary {
            border-top: 3px solid var(--primary-color);
        }

        .stat-success {
            border-top: 3px solid var(--success-color);
        }

        .stat-danger {
            border-top: 3px solid var(--danger-color);
        }

        .stat-warning {
            border-top: 3px solid var(--warning-color);
        }

        .stat-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.8rem;
            opacity: 0.1;
            color: var(--secondary-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
            color: var(--secondary-color);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .attendance-progress {
            height: 10px;
            margin-top: 10px;
            border-radius: 5px;
        }

        /* Table Styles */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: white;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: var(--secondary-color);
            padding: 12px 15px;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-top: 1px solid #e9ecef;
        }

        .status-present {
            color: var(--success-color);
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .status-absent {
            color: var(--danger-color);
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .status-present i, .status-absent i {
            margin-right: 5px;
        }

        /* Room Section Styles */
        .room-section {
            margin-bottom: 30px;
        }

        .room-header {
            background-color: var(--light-bg);
            padding: 15px 20px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-left: 5px solid var(--accent-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .room-header h4 {
            margin: 0;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .room-details {
            display: flex;
            gap: 15px;
        }

        .room-info {
            background-color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: var(--secondary-color);
        }

        /* Tab Navigation */
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            transition: var(--transition);
        }

        .nav-tabs .nav-link:hover {
            background-color: rgba(52, 152, 219, 0.05);
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: white;
            border-bottom: 3px solid var(--primary-color);
        }

        /* Badge Styles */
        .badge {
            border-radius: 30px;
            padding: 5px 10px;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar .nav-link span {
                display: none;
            }
            
            .sidebar .sidebar-brand {
                display: none;
            }
            
            .content {
                margin-left: 70px;
            }
            
            .sidebar:hover {
                width: 280px;
            }
            
            .sidebar:hover .nav-link span {
                display: inline;
            }
                        
            .sidebar:hover .sidebar-brand {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .filters-container {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #bbb;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
        
        /* Empty state */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #d1d1d1;
        }
    </style>
    <script>
        function removeAlert() {
            document.getElementById("alertMessage")?.remove();
        }
        setTimeout(removeAlert, 3000);
    </script>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="sidebar-brand">Hostel Management</h5>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a href="staff_dashboard.php" class="nav-link">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="warden_attendance.php" class="nav-link">
                <i class="fas fa-clipboard-check"></i> <span>Mark Attendance</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="attendance_reports.php" class="nav-link active">
                <i class="fas fa-chart-bar"></i> <span>Attendance Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<!-- Content -->
<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-chart-line me-2"></i><?php echo htmlspecialchars($hostel); ?> Hostel - Attendance Reports</h2>
    </div>

    <!-- Filters -->
    <div class="card filters-card">
        <div class="card-header">
            <i class="fas fa-filter me-2"></i>Filter Attendance Data
        </div>
        <div class="card-body">
            <form method="get">
                <div class="filters-container">
                    <div class="mb-2">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="Name or Reg No" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    <div class="mb-2">
                        <label for="room_number" class="form-label">Room Number</label>
                        <input type="number" id="room_number" name="room_number" class="form-control" placeholder="Enter room number" value="<?php echo htmlspecialchars($filterRoom); ?>">
                    </div>
                    <div class="mb-2">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
                    </div>
                    <div class="mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendance Stats -->
    <div class="stats-container">
        <div class="stat-card stat-primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Total Students</div>
            <div class="stat-number"><?php echo $totalStudents; ?></div>
        </div>
        <div class="stat-card stat-success">
            <i class="fas fa-user-check stat-icon"></i>
            <div class="stat-label">Present</div>
            <div class="stat-number"><?php echo $presentStudents; ?></div>
        </div>
        <div class="stat-card stat-danger">
            <i class="fas fa-user-times stat-icon"></i>
            <div class="stat-label">Absent</div>
            <div class="stat-number"><?php echo $absentStudents; ?></div>
        </div>
        <div class="stat-card stat-warning">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-label">Attendance Rate</div>
            <div class="stat-number"><?php echo $attendancePercentage; ?>%</div>
            <div class="progress attendance-progress">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendancePercentage; ?>%" aria-valuenow="<?php echo $attendancePercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
    </div>

    <!-- Tabs for Room-wise and All Students -->
    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="room-wise-tab" data-bs-toggle="tab" href="#room-wise" role="tab" aria-controls="room-wise" aria-selected="true">
                        <i class="fas fa-door-open me-2"></i>Room-wise Attendance
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="all-students-tab" data-bs-toggle="tab" href="#all-students" role="tab" aria-controls="all-students" aria-selected="false">
                        <i class="fas fa-user-graduate me-2"></i>All Students
                    </a>
                </li>
            </ul>
            <div class="tab-content pt-3" id="attendanceTabsContent">
                <!-- Room-wise Attendance -->
                <div class="tab-pane fade show active" id="room-wise" role="tabpanel" aria-labelledby="room-wise-tab">
                    <?php if (!empty($rooms)) { ?>
                        <?php
                        foreach ($rooms as $room) {
                            $roomRecords = array_filter($attendanceRecords, function ($record) use ($room) {
                                return $record['room_number'] == $room['room_number'];
                            });
                            
                            if (!empty($roomRecords) || (!$filterRoom || $filterRoom == $room['room_number'])) {
                                echo '<div class="room-section">';
                                echo '<div class="room-header">';
                                echo '<h4>Room ' . htmlspecialchars($room['room_number']) . '</h4>';
                                echo '<div class="room-details">';
                                echo '<span class="room-info"><i class="fas fa-users me-1"></i>' . htmlspecialchars($room['sharing_type']) . '</span>';
                                echo '<span class="room-info"><i class="' . ($room['is_ac'] == 1 ? 'fas fa-snowflake text-info' : 'fas fa-fan text-secondary') . ' me-1"></i>' . ($room['is_ac'] == 1 ? 'AC' : 'Non-AC') . '</span>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-hover">';
                                echo '<thead>
                                        <tr>
                                            <th><i class="fas fa-id-card me-1"></i>Reg No</th>
                                            <th><i class="fas fa-user me-1"></i>Student Name</th>
                                            <th><i class="fas fa-calendar me-1"></i>Date</th>
                                            <th><i class="fas fa-calendar-day me-1"></i>Day</th>
                                            <th><i class="fas fa-clock me-1"></i>Time</th>
                                            <th><i class="fas fa-check-circle me-1"></i>Status</th>
                                        </tr>
                                      </thead>
                                      <tbody>';
                                if (!empty($roomRecords)) {
                                    foreach ($roomRecords as $record) {
                                        echo '<tr>
                                                <td>' . htmlspecialchars($record['regNo']) . '</td>
                                                <td>' . htmlspecialchars($record['student_name']) . '</td>
                                                <td>' . htmlspecialchars($record['attendance_date']) . '</td>
                                                <td>' . htmlspecialchars($record['attendance_day']) . '</td>
                                                <td>' . htmlspecialchars($record['attendance_time']) . '</td>
                                                <td class="' . ($record['status'] === 'Present' ? 'status-present' : 'status-absent') . '">
                                                    <i class="fas ' . ($record['status'] === 'Present' ? 'fa-check-circle' : 'fa-times-circle') . '"></i> 
                                                    ' . htmlspecialchars($record['status']) . '
                                                </td>
                                              </tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No attendance records for this room.</td></tr>';
                                }
                                echo '</tbody></table>';
                                echo '</div></div>';
                            }
                        }
                        ?>
                    <?php } else { ?>
                        <div class="empty-state">
                            <i class="fas fa-door-closed"></i>
                            <h4>No rooms found</h4>
                            <p>There are no rooms assigned to this hostel.</p>
                        </div>
                    <?php } ?>
                </div>

                <!-- All Students -->
                <div class="tab-pane fade" id="all-students" role="tabpanel" aria-labelledby="all-students-tab">
                    <?php if (!empty($students)) { ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-id-card me-1"></i>Reg No</th>
                                        <th><i class="fas fa-user me-1"></i>Student Name</th>
                                        <th><i class="fas fa-graduation-cap me-1"></i>Course</th>
                                        <th><i class="fas fa-calendar-alt me-1"></i>Year</th>
                                        <th><i class="fas fa-door-open me-1"></i>Room No</th>
                                        <th><i class="fas fa-check-circle me-1"></i>Status (<?php echo $filterDate; ?>)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student) { 
                                        // Find attendance status for this student on the selected date
                                        $studentRegNo = $student['regNo'];
                                        $studentAttendance = array_filter($attendanceRecords, function($record) use ($studentRegNo) {
                                            return $record['regNo'] === $studentRegNo;
                                        });
                                        
                                        $status = !empty($studentAttendance) ? reset($studentAttendance)['status'] : 'Not Marked';
                                        $statusClass = '';
                                        $statusIcon = '';
                                        
                                        if ($status === 'Present') {
                                            $statusClass = 'status-present';
                                            $statusIcon = '<i class="fas fa-check-circle"></i>';
                                        } elseif ($status === 'Absent') {
                                            $statusClass = 'status-absent';
                                            $statusIcon = '<i class="fas fa-times-circle"></i>';
                                        } else {
                                            $statusClass = 'text-muted';
                                            $statusIcon = '<i class="fas fa-question-circle"></i>';
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['regNo']); ?></td>
                                            <td><?php echo htmlspecialchars($student['firstName']) . ' ' . htmlspecialchars($student['lastName']); ?></td>
                                            <td><?php echo htmlspecialchars($student['course']); ?></td>
                                            <td><?php echo htmlspecialchars($student['year_of_study']); ?></td>
                                            <td><?php echo htmlspecialchars($student['room_number']); ?></td>
                                            <td class="<?php echo $statusClass; ?>"><?php echo $statusIcon . ' ' . $status; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } else { ?>
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <h4>No students found</h4>
                            <p>There are no students assigned to this hostel.</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS and FontAwesome -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>