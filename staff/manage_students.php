<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_login.php");
    exit();}
include_once 'staff_db.php';
$staff_id = $_SESSION['staff_id'];
$query = "SELECT * FROM staff WHERE staff_id = '$staff_id'";
$result = mysqli_query($conn, $query);
$staff = mysqli_fetch_assoc($result);
$hostel = $staff['hostel'];
$where_clause = "WHERE d.hostel = '$hostel'";
if (isset($_GET['room']) && !empty($_GET['room'])) {
    $room = mysqli_real_escape_string($conn, $_GET['room']);
    $where_clause .= " AND d.room_number = '$room'";}
if (isset($_GET['department']) && !empty($_GET['department'])) {
    $department = mysqli_real_escape_string($conn, $_GET['department']);
    $where_clause .= " AND d.course = '$department'";}
$student_query = "SELECT s.regNo as student_id, CONCAT(s.firstName, ' ', s.lastName) as name, 
                 d.room_number, d.course as department, s.contact as phone, s.email 
                 FROM student_signup s 
                 LEFT JOIN student_details d ON s.regNo = d.reg_no 
                 $where_clause
                 ORDER BY d.room_number";
$student_result = mysqli_query($conn, $student_query);
function getTotalStudents($conn, $hostel) {
    $query = "SELECT COUNT(*) as total FROM student_details WHERE hostel = '$hostel'";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data['total'];}
function getDepartments($conn, $hostel) {
    $query = "SELECT DISTINCT d.course FROM student_details d WHERE d.hostel = '$hostel' ORDER BY d.course";
    $result = mysqli_query($conn, $query);
    $departments = [];
    while($row = mysqli_fetch_assoc($result)) {$departments[] = $row['course'];}
    return $departments;}
function getRooms($conn, $hostel) {
    $query = "SELECT DISTINCT d.room_number FROM student_details d WHERE d.hostel = '$hostel' ORDER BY d.room_number";
    $result = mysqli_query($conn, $query);
    $rooms = [];
    while($row = mysqli_fetch_assoc($result)) {$rooms[] = $row['room_number'];}
    return $rooms;
}
$total_students = getTotalStudents($conn, $hostel);
$departments = getDepartments($conn, $hostel);
$rooms = getRooms($conn, $hostel);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Hostel Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f5f5; display: flex; flex-direction: column; min-height: 100vh; }
        .header { background-color: #3a3a3a; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .header h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; }
        .user-info img { width: 35px; height: 35px; border-radius: 50%; margin-right: 10px; }
        .container { display: flex; flex: 1; }
        .sidebar { width: 250px; background-color: #2c3e50; color: white; height: calc(100vh - 60px); position: fixed; left: 0; top: 60px; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #34495e; }
        .sidebar-header h3 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { color: #ecf0f1; font-size: 14px; }
        .sidebar-menu { padding: 10px 0; }
        .menu-item { padding: 15px 20px; display: flex; align-items: center; cursor: pointer; transition: all 0.3s; text-decoration: none; color: white; }
        .menu-item:hover { background-color: #34495e; }
        .menu-item.active { background-color: #2980b9; border-left: 4px solid #3498db; }
        .menu-item i { margin-right: 15px; font-size: 16px; width: 20px; text-align: center; }
        .content { flex: 1; padding: 20px; margin-left: 250px; }
        .page-title { margin-bottom: 20px; font-size: 24px; color: #2c3e50; }
        .stats-container { display: flex; margin-bottom: 30px; gap: 20px; }
        .stat-card { background-color: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); flex: 1; display: flex; align-items: center; }
        .stat-icon { background-color: #e3f2fd; color: #1976d2; width: 60px; height: 60px; border-radius: 50%; display: flex; 
            align-items: center; justify-content: center; margin-right: 20px; font-size: 24px; }
        .stat-info h3 { font-size: 28px; margin-bottom: 5px; color: #2c3e50; }
        .stat-info p { color: #7f8c8d; font-size: 14px; }
        .filter-container { display: flex; gap: 15px; margin-bottom: 20px; background-color: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .filter-group { display: flex; flex-direction: column; min-width: 180px; }
        .filter-group label { font-size: 14px; margin-bottom: 5px; color: #2c3e50; }
        .filter-group select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; outline: none; }
        .filter-buttons { display: flex; align-items: flex-end; gap: 10px; }
        .btn-filter { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
        .btn-apply { background-color: #3498db; color: white; }
        .btn-reset { background-color: #e74c3c; color: white; }
        .data-table { width: 100%; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .table-header { padding: 15px 20px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 18px; color: #2c3e50; }
        .search-bar { display: flex; align-items: center; background-color: white; border: 1px solid #ddd; border-radius: 4px; padding: 5px 10px; width: 300px; }
        .search-bar input { border: none; outline: none; padding: 5px; width: 100%; }
        .search-bar i { color: #7f8c8d; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #e9ecef; }
        table th { background-color: #f8f9fa; color: #2c3e50; font-weight: 600; }
        table td { color: #2c3e50; }
        table tr:hover { background-color: #f5f5f5; }
        .pagination { display: flex; justify-content: flex-end; margin-top: 20px; gap: 5px; }
        .pagination button { padding: 8px 12px; border: 1px solid #ddd; background-color: white; cursor: pointer; }
        .pagination button.active { background-color: #3498db; color: white; border-color: #3498db; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Hostel Management System</h1>
        <div class="user-info">
            <i class="fas fa-user-circle" style="font-size: 24px; margin-right: 10px;"></i>
            <span><?php echo $staff['name']; ?> (<?php echo $staff['position']; ?>)</span>
        </div>
    </div>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Hostel Management</h3>
                <p><?php echo $hostel; ?></p>
            </div>
            <div class="sidebar-menu">
                <a href="warden_test_dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="room_management.php" class="menu-item">
                    <i class="fas fa-door-open"></i>
                    <span>Room Management</span>
                </a>
                <a href="manage_students.php" class="menu-item active">
                    <i class="fas fa-users"></i>
                    <span>Student Management</span>
                </a>
                <a href="outpass_management.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Outpass Management</span>
                </a>
                <a href="service_requests.php" class="menu-item">
                    <i class="fas fa-tools"></i>
                    <span>Service Requests</span>
                </a>
                <!-- <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a> -->
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        <div class="content">
            <h1 class="page-title">Student Management</h1>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $total_students; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
            </div>
            <form method="GET" action="" class="filter-container">
                <div class="filter-group">
                    <label for="room">Room Number</label>
                    <select name="room" id="room">
                        <option value="">All Rooms</option>
                        <?php foreach($rooms as $room): ?>
                            <option value="<?php echo $room; ?>" <?php echo (isset($_GET['room']) && $_GET['room'] == $room) ? 'selected' : ''; ?>><?php echo $room; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="department">Department</label>
                    <select name="department" id="department">
                        <option value="">All Departments</option>
                        <?php foreach($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo (isset($_GET['department']) && $_GET['department'] == $dept) ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter btn-apply">Apply Filters</button>
                    <button type="button" id="resetBtn" class="btn-filter btn-reset">Reset</button>
                </div>
            </form>
            <div class="data-table">
                <div class="table-header">
                    <h2>Students List</h2>
                    <div class="search-bar">
                        <input type="text" id="studentSearch" placeholder="Search students...">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Room No</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($student_result) > 0) {
                            while ($student = mysqli_fetch_assoc($student_result)) {
                        ?>
                        <tr>
                            <td><?php echo $student['student_id']; ?></td>
                            <td><?php echo $student['name']; ?></td>
                            <td><?php echo $student['room_number']; ?></td>
                            <td><?php echo $student['department']; ?></td>
                            <td><?php echo $student['phone']; ?></td>
                            <td><?php echo $student['email']; ?></td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr><td colspan="6" style="text-align: center;">No students found in this hostel.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <button>Prev</button>
                    <button class="active">1</button>
                    <button>2</button>
                    <button>3</button>
                    <button>Next</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('studentSearch').addEventListener('keyup', function() {
            let input = this.value.toLowerCase();
            let rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                if(text.includes(input)) {row.style.display = '';} 
                else {row.style.display = 'none';}
            });
        });
        document.getElementById('resetBtn').addEventListener('click', function() {window.location.href = window.location.pathname;});
    </script>
</body>
</html>