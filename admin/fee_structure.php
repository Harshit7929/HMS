<?php
include 'admin_db.php';
$room_type = "";
$sharing_type = "";
$duration = "";
$fee = "";
$id = 0;
$update = false;
$error_msg = "";
$success_msg = "";
$filter_room_type = isset($_GET['filter_room_type']) ? $_GET['filter_room_type'] : '';
$filter_sharing_type = isset($_GET['filter_sharing_type']) ? $_GET['filter_sharing_type'] : '';
$filter_duration = isset($_GET['filter_duration']) ? $_GET['filter_duration'] : '';
if (isset($_POST['save']) || isset($_POST['update'])) {
    $room_type = $_POST['room_type'];
    $duration = $_POST['duration'];
    $fee = $_POST['fee'];
    if ($room_type == 'Mess') {$sharing_type = NULL;} 
    else {$sharing_type = $_POST['sharing_type'];}
    if (isset($_POST['update'])) {
        $id = $_POST['id'];
        if ($room_type == 'Mess') {
            $query = "UPDATE fee_structure SET room_type=?, sharing_type=NULL, duration=?, fee=? WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssdi", $room_type, $duration, $fee, $id);
        } else {
            $query = "UPDATE fee_structure SET room_type=?, sharing_type=?, duration=?, fee=? WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sisdi", $room_type, $sharing_type, $duration, $fee, $id);
        }
        if ($stmt->execute()) {
            $success_msg = "Fee structure updated successfully!";
            $update = false;
            $id = 0;
            $room_type = "";
            $sharing_type = "";
            $duration = "";
            $fee = "";
        } else {$error_msg = "Error: " . $stmt->error;}
    } else {
        if ($room_type == 'Mess') {
            $query = "INSERT INTO fee_structure (room_type, sharing_type, duration, fee) VALUES (?, NULL, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssd", $room_type, $duration, $fee);
        } else {
            $query = "INSERT INTO fee_structure (room_type, sharing_type, duration, fee) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sisd", $room_type, $sharing_type, $duration, $fee);
        }
        
        if ($stmt->execute()) {
            $success_msg = "Fee structure added successfully!";
            $room_type = "";
            $sharing_type = "";
            $duration = "";
            $fee = "";
        } else {$error_msg = "Error: " . $stmt->error;}
    }
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM fee_structure WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {$success_msg = "Fee structure deleted successfully!";} 
    else {$error_msg = "Error: " . $stmt->error;}
}

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $update = true;
    $query = "SELECT * FROM fee_structure WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $room_type = $row['room_type'];
        $sharing_type = $row['sharing_type'];
        $duration = $row['duration'];
        $fee = $row['fee'];
    }
}
$query = "SELECT * FROM fee_structure WHERE 1=1";
$params = [];
$types = "";
if (!empty($filter_room_type)) {
    $query .= " AND room_type = ?";
    $params[] = $filter_room_type;
    $types .= "s";
}
if (!empty($filter_sharing_type)) {
    if ($filter_sharing_type === 'null') {$query .= " AND sharing_type IS NULL";} 
    else {
        $query .= " AND sharing_type = ?";
        $params[] = $filter_sharing_type;
        $types .= "i";
    }
}
if (!empty($filter_duration)) {
    $query .= " AND duration = ?";
    $params[] = $filter_duration;
    $types .= "s";
}
$query .= " ORDER BY room_type, sharing_type, duration";
$stmt = $conn->prepare($query);
if (!empty($params)) {$stmt->bind_param($types, ...$params);}
$stmt->execute();
$result = $stmt->get_result();
$room_types = $conn->query("SELECT DISTINCT room_type FROM fee_structure ORDER BY room_type");
$sharing_types = $conn->query("SELECT DISTINCT sharing_type FROM fee_structure WHERE sharing_type IS NOT NULL ORDER BY sharing_type");
$durations = $conn->query("SELECT DISTINCT duration FROM fee_structure ORDER BY duration");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; padding-top: 56px; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 56px 0 0; box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1); 
            background-color: #343a40; width: 250px; transition: all 0.3s; }
        .sidebar-sticky { position: relative; top: 0; height: calc(100vh - 56px); padding-top: 1rem; overflow-x: hidden; overflow-y: auto; }
        .sidebar .nav-link { font-weight: 500; color: #fff; padding: 0.75rem 1.25rem; }
        .sidebar .nav-link i { margin-right: 10px; }
        .sidebar .nav-link.active { color: #fff; background-color: #007bff; }
        .main-content { margin-left: 250px; padding: 20px; }
        .form-section { background-color: #fff; padding: 20px; border-radius: 5px; margin-bottom: 30px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .filter-card { background-color: #fff; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .filter-title { font-size: 18px; margin-bottom: 15px; color: #495057; }
        .alert { margin-top: 20px; }
        .table-responsive { margin-top: 20px; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .table-title { margin-bottom: 20px; }
        .dashboard-title { margin-bottom: 20px; color: #343a40; }
        .nav-item { margin-bottom: 5px; }
        @media (max-width: 768px) { .sidebar { width: 0; } .main-content { margin-left: 0; } .sidebar.active { width: 250px; } .main-content.active { margin-left: 250px; } }
        .btn-toggle-sidebar { position: fixed; top: 10px; left: 10px; z-index: 1050; display: none; }
        @media (max-width: 768px) { .btn-toggle-sidebar { display: block; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark fixed-top">
        <a class="navbar-brand mr-0 mx-3" href="#">Hostel Management System</a>
        <button class="btn btn-link btn-sm text-white d-md-none btn-toggle-sidebar" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    </nav>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_students.php"><i class="fas fa-user-graduate"></i> Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_rooms.php"><i class="fas fa-door-open"></i> Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fee Structure</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_complaints.php"><i class="fas fa-exclamation-triangle"></i> Complaints</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_notice.php"><i class="fas fa-bullhorn"></i> Notices</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                </li>
                <li class="nav-item mt-5">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="main-content" id="mainContent">
        <h2 class="dashboard-title"><i class="fas fa-money-bill-wave mr-2"></i> Fee Structure Management</h2>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-4">
                <div class="form-section">
                    <h4 class="mb-3">
                        <i class="fas fa-<?php echo $update ? 'edit' : 'plus-circle'; ?> mr-2"></i>
                        <?php echo $update ? 'Update' : 'Add'; ?> Fee Structure
                    </h4>
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <div class="form-group">
                            <label for="room_type">Room Type:</label>
                            <select class="form-control" id="room_type" name="room_type" required onchange="toggleSharingType()">
                                <option value="">Select Room Type</option>
                                <option value="AC" <?php if ($room_type == 'AC') echo 'selected'; ?>>AC</option>
                                <option value="Non-AC" <?php if ($room_type == 'Non-AC') echo 'selected'; ?>>Non-AC</option>
                                <option value="Mess" <?php if ($room_type == 'Mess') echo 'selected'; ?>>Mess</option>
                            </select>
                        </div>
                        <div class="form-group" id="sharing_type_group" <?php if ($room_type == 'Mess') echo 'style="display:none;"'; ?>>
                            <label for="sharing_type">Sharing Type:</label>
                            <select class="form-control" id="sharing_type" name="sharing_type">
                                <option value="">Select Sharing Type</option>
                                <option value="2" <?php if ($sharing_type == '2') echo 'selected'; ?>>2 Person</option>
                                <option value="3" <?php if ($sharing_type == '3') echo 'selected'; ?>>3 Person</option>
                                <option value="4" <?php if ($sharing_type == '4') echo 'selected'; ?>>4 Person</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="duration">Duration:</label>
                            <select class="form-control" id="duration" name="duration" required>
                                <option value="">Select Duration</option>
                                <option value="6 months" <?php if ($duration == '6 months') echo 'selected'; ?>>6 months</option>
                                <option value="12 months" <?php if ($duration == '12 months') echo 'selected'; ?>>12 months</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fee">Fee (₹):</label>
                            <input type="number" class="form-control" id="fee" name="fee" step="0.01" required value="<?php echo $fee; ?>">
                        </div>
                        <?php if ($update): ?>
                            <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update</button>
                            <a href="fee_structure.php" class="btn btn-secondary"><i class="fas fa-times mr-1"></i> Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="save" class="btn btn-success"><i class="fas fa-plus-circle mr-1"></i> Add Fee</button>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="filter-card">
                    <h5 class="filter-title"><i class="fas fa-filter mr-2"></i> Filter Fee Structure</h5>
                    <form method="GET" action="">
                        <div class="form-group">
                            <label for="filter_room_type">Room Type:</label>
                            <select class="form-control" id="filter_room_type" name="filter_room_type">
                                <option value="">All Room Types</option>
                                <?php while ($row = $room_types->fetch_assoc()): ?>
                                    <option value="<?php echo $row['room_type']; ?>" <?php if ($filter_room_type == $row['room_type']) echo 'selected'; ?>>
                                        <?php echo $row['room_type']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_sharing_type">Sharing Type:</label>
                            <select class="form-control" id="filter_sharing_type" name="filter_sharing_type">
                                <option value="">All Sharing Types</option>
                                <option value="null" <?php if ($filter_sharing_type == 'null') echo 'selected'; ?>>N/A (Mess)</option>
                                <?php while ($row = $sharing_types->fetch_assoc()): ?>
                                    <option value="<?php echo $row['sharing_type']; ?>" <?php if ($filter_sharing_type == $row['sharing_type']) echo 'selected'; ?>>
                                        <?php echo $row['sharing_type']; ?> Person
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_duration">Duration:</label>
                            <select class="form-control" id="filter_duration" name="filter_duration">
                                <option value="">All Durations</option>
                                <?php while ($row = $durations->fetch_assoc()): ?>
                                    <option value="<?php echo $row['duration']; ?>" <?php if ($filter_duration == $row['duration']) echo 'selected'; ?>>
                                        <?php echo $row['duration']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i> Apply Filters</button>
                        <a href="fee_structure.php" class="btn btn-secondary"><i class="fas fa-redo mr-1"></i> Reset</a>
                    </form>
                </div>
            </div>
            <div class="col-md-8">
                <div class="table-responsive">
                    <div class="d-flex justify-content-between align-items-center table-title">
                        <h4 class="mb-0"><i class="fas fa-list mr-2"></i> Fee Structure List</h4>
                        <span class="badge badge-primary"><?php echo $result->num_rows; ?> Records</span>
                    </div>
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th> <th>Room Type</th> <th>Sharing Type</th>
                                <th>Duration</th> <th>Fee (₹)</th> <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td>
                                            <?php if ($row['room_type'] == 'AC'): ?>
                                                <span class="badge badge-info">AC</span>
                                            <?php elseif ($row['room_type'] == 'Non-AC'): ?>
                                                <span class="badge badge-secondary">Non-AC</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Mess</span>
                                            <?php endif; ?>
                                            <?php echo $row['room_type']; ?>
                                        </td>
                                        <td><?php echo $row['sharing_type'] ? $row['sharing_type'] . ' Person' : 'N/A'; ?></td>
                                        <td><?php echo $row['duration']; ?></td>
                                        <td>₹<?php echo number_format($row['fee'], 2); ?></td>
                                        <td>
                                            <a href="fee_structure.php?edit=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit</a>
                                            <!-- <a href="fee_structure.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this fee structure?')">
                                                <i class="fas fa-trash"></i> Delete</a> -->
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">No records found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleSharingType() {
            var roomType = document.getElementById('room_type').value;
            var sharingTypeGroup = document.getElementById('sharing_type_group');
            if (roomType === 'Mess') {sharingTypeGroup.style.display = 'none';} 
            else {sharingTypeGroup.style.display = 'block';}
        }
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });
        document.addEventListener('DOMContentLoaded', toggleSharingType);
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>