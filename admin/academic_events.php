<?php
session_start();
include('admin_db.php');
// Check if the admin is logged in
// if (!isset($_SESSION['admin_user'])) {
//     header("Location: admin_login.php");
//     exit();
// }
if (isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    $event_name = $_POST['event_name'];
    $event_date = $_POST['event_date'];
    $academic_year = $_POST['academic_year'];
    $update_query = "UPDATE academic_events SET event_name=?, event_date=?, academic_year=? WHERE id=?";
    if ($stmt = $conn->prepare($update_query)) {
        $stmt->bind_param("sssi", $event_name, $event_date, $academic_year, $event_id);
        $stmt->execute();
        $stmt->close();
        header("Location: academic_events.php");
        exit();
    }}
if (isset($_POST['add_event'])) {
    $event_name = $_POST['event_name'];
    $event_date = $_POST['event_date'];
    $academic_year = $_POST['academic_year'];
    $query = "INSERT INTO academic_events (event_name, event_date, academic_year) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("sss", $event_name, $event_date, $academic_year);
        $stmt->execute();
        $stmt->close();
    }
}
$query = "SELECT * FROM academic_events ORDER BY event_date ASC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Academic Events</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- <link href="css/academic_events.css" rel="stylesheet"> -->
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-color: #f4f6f9; }
        header { background-color: #343a40; color: white; padding: 1rem; position: fixed; width: 100%; top: 0; z-index: 1000; height: 60px; 
            display: flex; align-items: center; font-size: 1.5rem; font-weight: bold; }
        .sidebar { height: 100%; width: 250px; position: fixed; top: 60px; left: 0; background-color: #343a40; padding-top: 20px; z-index: 999; }
        .sidebar a { padding: 15px 25px; text-decoration: none; font-size: 16px; color: #ffffff; display: block; transition: all 0.3s; }
        .sidebar a:hover { background-color: #495057; color: #fff; }
        .sidebar i { margin-right: 10px; width: 20px; }
        .main-content { margin-left: 250px; margin-top: 60px; padding: 20px; }
        .page-title { color: #343a40; margin-bottom: 30px; padding-bottom: 10px; border-bottom: 2px solid #343a40; }
        .card { margin-bottom: 20px; box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); border: none; }
        .card-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 15px; }
        .card-header h3 { margin: 0; color: #343a40; font-size: 1.25rem; }
        .card-body { padding: 20px; }
        .table-responsive { margin-top: 10px; }
        .table thead th { background-color: #343a40; color: white; border: none; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .btn-primary { background-color: #007bff; border: none; padding: 10px 20px; }
        .btn-primary:hover { background-color: #0056b3; }
        .form-group { margin-bottom: 20px; }
        .form-control { border-radius: 4px; border: 1px solid #ced4da; padding: 8px 12px; }
        .form-control:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }
        @media (max-width: 768px) { .sidebar { width: 60px; } .sidebar a span { display: none; } .main-content { margin-left: 60px; } .sidebar a i { margin-right: 0; } }
    </style>
</head>
<body>
    <header>Admin Panel</header>
    <div class="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
        <a href="update_profile.php"><i class="fas fa-user-edit"></i>Profile</a>
        <a href="access_log.php"><i class="fas fa-user-shield"></i>Admin Access Log</a>
        <a href="payments.php"><i class="fas fa-credit-card"></i>Payments</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="page-title">Manage Academic Events</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h3>Add New Event</h3></div>
                        <div class="card-body">
                            <form action="academic_events.php" method="POST">
                                <div class="form-group">
                                    <label for="event_name">Event Name</label>
                                    <input type="text" class="form-control" id="event_name" name="event_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="event_date">Event Date</label>
                                    <input type="date" class="form-control" id="event_date" name="event_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="academic_year">Academic Year</label>
                                    <input type="text" class="form-control" id="academic_year" name="academic_year" placeholder="e.g., 2024-2025" required>
                                </div>
                                <button type="submit" class="btn btn-primary" name="add_event">Add Event</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h3>Existing Events</h3></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Event Name</th> <th>Event Date</th>
                                            <th>Academic Year</th> <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($row['event_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['academic_year']); ?></td>
                                                <td>
                                                    <button class="btn btn-primary edit-btn" 
                                                            onclick="editEvent(
                                                                '<?php echo $row['id']; ?>', 
                                                                '<?php echo htmlspecialchars($row['event_name']); ?>', 
                                                                '<?php echo $row['event_date']; ?>', 
                                                                '<?php echo htmlspecialchars($row['academic_year']); ?>'
                                                            )">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Event</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" class="text-white">&times;</span>
                    </button>
                </div>
                <form action="academic_events.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_event_id" name="event_id">
                        <div class="form-group">
                            <label for="edit_event_name">Event Name</label>
                            <input type="text" class="form-control" id="edit_event_name" name="event_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_event_date">Event Date</label>
                            <input type="date" class="form-control" id="edit_event_date" name="event_date" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_academic_year">Academic Year</label>
                            <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="update_event">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function editEvent(id, name, date, year) {
            document.getElementById('edit_event_id').value = id;
            document.getElementById('edit_event_name').value = name;
            document.getElementById('edit_event_date').value = date;
            document.getElementById('edit_academic_year').value = year;
            $('#editModal').modal('show');
        }
    </script>
</body>
</html>