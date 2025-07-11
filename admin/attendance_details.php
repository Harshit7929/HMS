<?php
include('admin_db.php');
$query = "SELECT sa.*, ss.firstName, ss.lastName 
          FROM student_attendance sa
          LEFT JOIN student_signup ss ON sa.regNo = ss.regNo
          ORDER BY sa.attendance_date DESC, sa.attendance_time DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Attendance - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f5f8fa; overflow-x: hidden; }
        .header { background-color: #3c4b64; color: white; padding: 15px 20px; display: flex; justify-content: space-between; 
            align-items: center; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .header h3 { margin: 0; font-size: 1.4rem; }
        .sidebar { background-color: #2c3849; color: white; width: 250px; height: 100vh; position: fixed; top: 60px; left: 0; 
            padding-top: 20px; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); z-index: 900; }
        .sidebar a { padding: 15px 20px; text-decoration: none; color: #b3b8bd; display: block; transition: all 0.3s; font-size: 0.95rem; }
        .sidebar a:hover { background-color: #3c4b64; color: white; border-left: 4px solid #5bc0de; }
        .sidebar a.active { background-color: #3c4b64; color: white; border-left: 4px solid #5bc0de; }
        .sidebar i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { margin-left: 250px; margin-top: 60px; padding: 20px; transition: margin-left 0.3s; }
        .table-responsive { background-color: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0, 0, 0, 0.05); padding: 20px; margin-top: 20px; }
        .table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table th { background-color: #f8f9fa; padding: 12px 15px; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; text-transform: uppercase; font-size: 0.8rem; }
        .table td { padding: 12px 15px; vertical-align: middle; border-bottom: 1px solid #e9ecef; color: #495057; }
        .table tbody tr:hover { background-color: #f5f8fa; }
        .badge { padding: 6px 10px; border-radius: 4px; font-weight: 500; font-size: 0.75rem; }
        @media (max-width: 992px) { .sidebar { width: 200px; } .main-content { margin-left: 200px; } }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-left: 0; } .header { padding: 10px 15px; } .header h3 { font-size: 1.2rem; } }
        .dataTables_wrapper .dataTables_filter { margin-bottom: 15px; }
        .dataTables_wrapper .dataTables_filter input { border: 1px solid #ddd; border-radius: 4px; padding: 6px 10px; margin-left: 8px; }
        .dataTables_wrapper .dataTables_length select { border: 1px solid #ddd; border-radius: 4px; padding: 6px 10px; }
        .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { margin-top: 15px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 5px 10px; border-radius: 4px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background-color: #3c4b64; color: white !important; border: 1px solid #3c4b64; }
        h2 { color: #3c4b64; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eaeaea; }
    </style>
</head>
<body>
    <div class="header">
        <h3>Hostel Management System - Admin Panel</h3>
        <div>Welcome, Admin</div>
    </div>
    <div class="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a>
        <a href="manage_students.php"><i class="fas fa-users"></i> Manage Students</a>
        <a href="manage_rooms.php"><i class="fas fa-bed"></i> Manage Rooms</a>
        <a href="student_attendance.php" class="active"><i class="fas fa-clipboard-list"></i> Attendance</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="container-fluid mt-4">
            <h2>Student Attendance</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>S.No</th> <th>Serial Number</th> <th>Reg No</th> <th>Student Name</th>
                            <th>Hostel Name</th> <th>Room Number</th> <th>Attendance Date</th> <th>Day</th>
                            <th>Time</th> <th>Status</th> <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        while ($row = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($row['serial_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['regNo']); ?></td>
                                <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                                <td><?php echo htmlspecialchars($row['hostel_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['attendance_day']); ?></td>
                                <td><?php echo htmlspecialchars($row['attendance_time']); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['status'] == 'Present' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['marked_by']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#attendanceTable').DataTable();
        });
    </script>
</body>
</html>