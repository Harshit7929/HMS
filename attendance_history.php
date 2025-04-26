<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['regNo'])) {
    header("Location: student_login.php");
    exit();
}
$regNo = $_SESSION['user']['regNo'];
$studentName = ''; 
$course = '';
$yearOfStudy = '';
$hostel = '';
$roomNumber = '';
$studentQuery = $conn->prepare("
    SELECT ss.firstName, ss.lastName, sd.course, sd.year_of_study, sd.hostel, sd.room_number
    FROM student_signup ss
    LEFT JOIN student_details sd ON ss.regNo = sd.reg_no
    WHERE ss.regNo = ?
");
$studentQuery->bind_param("s", $regNo);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();
if ($studentResult && $studentResult->num_rows > 0) {
    $student = $studentResult->fetch_assoc();
    $studentName = $student['firstName'] . ' ' . $student['lastName'];
    $course = $student['course'] ?? '';
    $yearOfStudy = $student['year_of_study'] ?? '';
    $hostel = $student['hostel'] ?? '';
    $roomNumber = $student['room_number'] ?? '';
} else {
    $basicQuery = $conn->prepare("SELECT firstName, lastName FROM student_signup WHERE regNo = ?");
    $basicQuery->bind_param("s", $regNo);
    $basicQuery->execute();
    $basicResult = $basicQuery->get_result();
    if ($basicResult && $basicResult->num_rows > 0) {
        $basicData = $basicResult->fetch_assoc();
        $studentName = $basicData['firstName'] . ' ' . $basicData['lastName'];
    }
}

$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$entriesPerPage = isset($_GET['entries']) ? intval($_GET['entries']) : 10;
$sql = "
    SELECT attendance_date, attendance_day, attendance_time, status 
    FROM student_attendance 
    WHERE regNo = ?
";
if (!empty($startDate)) {$sql .= " AND attendance_date >= ?";}
if (!empty($endDate)) {$sql .= " AND attendance_date <= ?";}
if (!empty($status)) {$sql .= " AND status = ?";}
$sql .= " ORDER BY attendance_date DESC, attendance_time DESC";
$countSql = str_replace("SELECT attendance_date, attendance_day, attendance_time, status", "SELECT COUNT(*) as total", $sql);
$countQuery = $conn->prepare($countSql);
$sql .= " LIMIT ?";
$attendanceQuery = $conn->prepare($sql);
function bindParametersToQuery($query, $regNo, $startDate, $endDate, $status, $limitParam = null) {
    $types = "s"; 
    $params = [$regNo];
    if (!empty($startDate)) {
        $types .= "s";
        $params[] = $startDate;}
    if (!empty($endDate)) {
        $types .= "s";
        $params[] = $endDate;}
    if (!empty($status)) {
        $types .= "s";
        $params[] = $status;}
    if ($limitParam !== null) {
        $types .= "i";
        $params[] = $limitParam;}
    $query->bind_param($types, ...$params);
}
bindParametersToQuery($countQuery, $regNo, $startDate, $endDate, $status);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'] ?? 0;
bindParametersToQuery($attendanceQuery, $regNo, $startDate, $endDate, $status, $entriesPerPage);
$attendanceQuery->execute();
$attendanceResult = $attendanceQuery->get_result();
$attendanceRecords = [];
if ($attendanceResult) {$attendanceRecords = $attendanceResult->fetch_all(MYSQLI_ASSOC);}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - <?php echo htmlspecialchars($studentName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { display: flex; min-height: 100vh; flex-direction: column; font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        header { background-color: #343a40; color: white; padding: 10px 0; text-align: center; font-size: 24px; }
        .sidebar { position: fixed; height: 100%; width: 250px; background: #343a40; color: white; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .sidebar a { color: white; text-decoration: none; padding: 10px; border-radius: 5px; transition: background-color 0.2s; }
        .sidebar a:hover { background-color: #495057; }
        .sidebar-logo { width: 80%; margin-bottom: 20px; }
        .content { margin-left: 260px; padding: 20px; flex: 1; }
        .status-present { color: green; font-weight: bold; }
        .status-absent { color: red; font-weight: bold; }
        .form-control, .btn { border-radius: 5px; }
        .footer { background-color: #343a40; color: white; text-align: center; padding: 10px 0; margin-top: auto; }
        .container { width: 70%; margin: auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); border-radius: 8px; margin-top: 20px; }
        h1, h2 { text-align: center; color: #333; }
        .student-info, .room-info, .attendance-form, .attendance-history { background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px; }
        p { font-size: 16px; color: #555; }
        strong { color: #222; }
        button { display: block; width: 100%; padding: 10px; font-size: 18px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #218838; }
        .message { text-align: center; padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #007bff; color: white; }
        td { background: white; }
        @media (max-width: 768px) { .container { width: 90%; } button { font-size: 16px; } }
    </style>
    <script>
        function removeAlert() {document.getElementById("alertMessage")?.remove();}
        setTimeout(removeAlert, 3000);
    </script>
</head>
<body>
<header>Hostel Management System</header>
<div class="sidebar">
    <img src="images/srmap.png" alt="SRM AP Logo" class="sidebar-logo">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="attendance_history.php"><i class="fas fa-calendar-alt"></i> Attendance History</a>
    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="content">
    <div class="container">
        <h2 class="text-center mb-4">Attendance History - <?php echo htmlspecialchars($studentName); ?></h2>
        <div class="mb-4">
            <h4>Student Details</h4>
            <table class="table table-bordered">
                <thead>
                    <tr><th>Reg No</th> <th>Name</th> <th>Course</th> <th>Year</th> <th>Hostel</th> <th>Room No</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($regNo); ?></td>
                        <td><?php echo htmlspecialchars($studentName); ?></td>
                        <td><?php echo htmlspecialchars($course); ?></td>
                        <td><?php echo htmlspecialchars($yearOfStudy); ?></td>
                        <td><?php echo htmlspecialchars($hostel); ?></td>
                        <td><?php echo htmlspecialchars($roomNumber); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php if (empty($course) && empty($hostel)): ?>
            <div class="alert alert-warning">
                Your profile information is incomplete. Please update your details in the profile section.
            </div>
            <?php endif; ?>
        </div>
        <div class="mb-4">
            <h4>Filters</h4>
            <form method="get">
                <input type="hidden" name="entries" value="<?php echo htmlspecialchars($entriesPerPage); ?>">
                <div class="row">
                    <div class="col-md-3">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">-- All --</option>
                            <option value="Present" <?php if ($status === 'Present') echo 'selected'; ?>>Present</option>
                            <option value="Absent" <?php if ($status === 'Absent') echo 'selected'; ?>>Absent</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="mb-4">
            <form method="get" class="row align-items-end">
                <div class="col-md-3">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                    <label for="entries">Entries per Page</label>
                    <select id="entries" name="entries" class="form-control" onchange="this.form.submit()">
                        <option value="10" <?php if ($entriesPerPage === 10) echo 'selected'; ?>>10</option>
                        <option value="25" <?php if ($entriesPerPage === 25) echo 'selected'; ?>>25</option>
                        <option value="50" <?php if ($entriesPerPage === 50) echo 'selected'; ?>>50</option>
                        <option value="100" <?php if ($entriesPerPage === 100) echo 'selected'; ?>>100</option>
                    </select>
                </div>
                <div class="col-md-9">
                    <p class="text-end mb-0">Showing <?php echo count($attendanceRecords); ?> of <?php echo $totalRecords; ?> records</p>
                </div>
            </form>
        </div>
        <div class="mb-4">
            <h4>Daily Attendance</h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attendanceRecords)) { ?>
                        <?php foreach ($attendanceRecords as $record) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['attendance_date']); ?></td>
                                <td><?php echo htmlspecialchars($record['attendance_day']); ?></td>
                                <td><?php echo htmlspecialchars($record['attendance_time']); ?></td>
                                <td class="<?php echo $record['status'] === 'Present' ? 'status-present' : 'status-absent'; ?>">
                                    <?php echo htmlspecialchars($record['status']); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="4" class="text-center">No attendance records found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="footer"> <p>&copy; 2025 Hostel Management System | All Rights Reserved</p></div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 