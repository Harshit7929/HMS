<?php
session_start();
include('admin_db.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_status') {
            $complaint_id = $_POST['complaint_id'];
            $new_status = $_POST['status'];
            $check_sql = "SELECT status FROM complaints WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $complaint_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                if ($row['status'] !== $new_status) {
                    $update_sql = "UPDATE complaints SET status = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("si", $new_status, $complaint_id);
                    if ($stmt->execute()) {$_SESSION['message'] = "Complaint #$complaint_id status updated to $new_status successfully.";} 
                    else {$_SESSION['error'] = "Failed to update Complaint #$complaint_id status.";}
                    $stmt->close();
                } else {$_SESSION['info'] = "Complaint #$complaint_id status remains unchanged.";}
            } else {$_SESSION['error'] = "Invalid Complaint ID.";}
            $check_stmt->close();
            header('Location: admin_complaints.php');
            exit();
        }
        if ($_POST['action'] === 'assign_complaint') {
            $complaint_id = $_POST['complaint_id'];
            $assigned_to = $_POST['assigned_to'];
            $check_sql = "SELECT assigned_to FROM complaints WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $complaint_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                if ($row['assigned_to'] !== $assigned_to) {
                    $assign_sql = "UPDATE complaints SET assigned_to = ? WHERE id = ?";
                    $stmt = $conn->prepare($assign_sql);
                    $stmt->bind_param("si", $assigned_to, $complaint_id);
                    if ($stmt->execute()) {
                        $staff_name = $assigned_to ? (getStaffName($conn, $assigned_to) ?: $assigned_to) : 'nobody';
                        $_SESSION['message'] = "Complaint #$complaint_id assigned to $staff_name successfully.";
                    } else {$_SESSION['error'] = "Failed to assign Complaint #$complaint_id.";}
                    $stmt->close();
                } else {$_SESSION['info'] = "Complaint #$complaint_id assignment remains unchanged.";}
            } else {$_SESSION['error'] = "Invalid Complaint ID.";}
            $check_stmt->close();
            header('Location: admin_complaints.php');
            exit();
        }
    }
}
function getStaffName($conn, $email) {
    $sql = "SELECT name FROM staff WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { $result->fetch_assoc()['name'];}
    return null;
}
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
    SUM(CASE WHEN priority = 'high' AND status != 'closed' THEN 1 ELSE 0 END) as high_priority_count
FROM complaints";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$stats['total'] = $stats['total'] ?? 0;
$stats['pending'] = $stats['pending'] ?? 0;
$stats['in_progress'] = $stats['in_progress'] ?? 0;
$stats['resolved'] = $stats['resolved'] ?? 0;
$stats['closed'] = $stats['closed'] ?? 0;
$stats['high_priority_count'] = $stats['high_priority_count'] ?? 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM complaint_responses WHERE complaint_id = c.id) as response_count,
        CONCAT(s.firstName, ' ', s.lastName) as student_name,
        s.regNo as registration_number,
        s.contact as student_contact,
        st.name as assigned_staff_name
        FROM complaints c
        LEFT JOIN student_signup s ON c.student_email = s.email
        LEFT JOIN staff st ON c.assigned_to = st.email
        WHERE 1=1";
$params = [];
$types = "";
if ($status_filter) {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($priority_filter) {
    $sql .= " AND c.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}
if ($category_filter) {
    $sql .= " AND c.category = ?"; 
    $params[] = $category_filter;
    $types .= "s";
}
$sql .= " ORDER BY 
          CASE 
            WHEN c.status = 'pending' AND c.priority = 'high' THEN 1
            WHEN c.status = 'pending' THEN 2
            WHEN c.status = 'in_progress' THEN 3
            ELSE 4
          END,
          c.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {$stmt->bind_param($types, ...$params);}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Complaints Management</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="css/complaints.css" rel="stylesheet">
    <style>
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px; background-color: #343a40; padding-top: 60px; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); padding: 15px 20px; }
        .sidebar .nav-link:hover { color: white; background-color: rgba(255,255,255,.1); }
        .sidebar .nav-link.active { color: white; background-color: rgba(255,255,255,.2); }
        .main-content { margin-left: 250px; padding: 20px; padding-top: 60px; }
        .main-header { position: fixed; top: 0; right: 0; left: 250px; height: 56px; background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,.1); 
            z-index: 1000; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; }
        .stats-card { border-radius: 10px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .priority-high { background-color: #ffe6e6; }
        .priority-medium { background-color: #fff3e6; }
        .priority-low { background-color: #e6ffe6; }
        .response-section { max-height: 300px; overflow-y: auto; }
        .badge-high { background-color: #dc3545; color: white; }
        .badge-medium { background-color: #ffc107; color: black; }
        .badge-low { background-color: #28a745; color: white; }
        .logo-img { width: 80%; max-width: 180px; height: auto; margin: 10px auto; display: block; padding: 5px; }
    </style>
</head>
<body>
<nav class="sidebar">
    <img src="http://localhost/hostel_info/images/srmap.png" alt="SRMAP Logo" class="logo-img">
    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-clipboard-list mr-2"></i>Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_access_log.php">
                    <i class="fas fa-history mr-2"></i>Access Log</a>
            </li>
            <!-- <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog mr-2"></i>Settings</a>
            </li> -->
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </li>
        </ul>
    </div>
</nav>
<header class="main-header">
    <div><h4 class="mb-0">Complaints Management System</h4></div>
    <div class="d-flex align-items-center">
        <span class="mr-3"><?php echo isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'Admin'; ?></span>
        <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>
<div class="main-content">
    <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?php echo $_SESSION['info']; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stats-card bg-primary text-white">
                <h6>Total Complaints</h6>
                <h3><?php echo $stats['total']; ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-warning text-white">
                <h6>Pending</h6>
                <h3><?php echo $stats['pending']; ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-info text-white">
                <h6>In Progress</h6>
                <h3><?php echo $stats['in_progress']; ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-success text-white">
                <h6>Resolved</h6>
                <h3><?php echo $stats['resolved']; ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stats-card bg-danger text-white">
                <h6>High Priority</h6>
                <h3><?php echo $stats['high_priority_count']; ?></h3>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="priority" class="form-control">
                        <option value="">All Priorities</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="maintenance" <?php echo $category_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="cleanliness" <?php echo $category_filter === 'cleanliness' ? 'selected' : ''; ?>>Cleanliness</option>
                        <option value="security" <?php echo $category_filter === 'security' ? 'selected' : ''; ?>>Security</option>
                        <option value="facilities" <?php echo $category_filter === 'facilities' ? 'selected' : ''; ?>>Facilities</option>
                        <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="admin_complaints.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th> <th>Student</th> <th>Subject</th>
                            <th>Category</th> <th>Priority</th> <th>Status</th>
                            <th>Assigned To</th> <th>Created</th> <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="priority-<?php echo $row['priority']; ?>">
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td>
                                    <a href="#" data-toggle="modal" data-target="#complaintModal<?php echo $row['id']; ?>">
                                        <?php echo htmlspecialchars($row['subject']); ?></a>
                                </td>
                                <td><?php echo ucfirst($row['category']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['priority']; ?>">
                                        <?php echo ucfirst($row['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" id="statusForm<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                                        <select name="status" class="form-control form-control-sm status-select" data-form-id="statusForm<?php echo $row['id']; ?>" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $row['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $row['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $row['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="closed" <?php echo $row['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" id="assignForm<?php echo $row['id']; ?>">
                                        <input type="hidden" name="action" value="assign_complaint">
                                        <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                                        <select name="assigned_to" class="form-control form-control-sm assign-select" data-form-id="assignForm<?php echo $row['id']; ?>" onchange="this.form.submit()">
                                            <option value="">Unassigned</option>
                                            <?php
                                            $staff_sql = "SELECT email, name, department FROM staff ORDER BY department, name";
                                            $staff_result = $conn->query($staff_sql);
                                            $current_department = '';
                                            while ($staff = $staff_result->fetch_assoc()) {
                                                if ($current_department != $staff['department']) {
                                                    if ($current_department != '') {echo "</optgroup>";}
                                                    echo "<optgroup label='" . htmlspecialchars($staff['department']) . "'>";
                                                    $current_department = $staff['department'];
                                                }
                                                $selected = $row['assigned_to'] === $staff['email'] ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($staff['email']) . "' {$selected}>" . htmlspecialchars($staff['name']) . "</option>";
                                            }
                                            if ($current_department != '') {echo "</optgroup>";}
                                            ?>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo date("Y-m-d H:i", strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" onclick="viewResponses(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-comments"></i> View (<?php echo $row['response_count']; ?>)
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <div class="modal fade" id="complaintModal<?php echo $row['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Complaint Details #<?php echo $row['id']; ?></h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Student Information</h6>
                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($row['student_name']); ?></p>
                                                    <p><strong>Registration:</strong> <?php echo htmlspecialchars($row['registration_number']); ?></p>
                                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($row['student_contact']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Complaint Information</h6>
                                                    <p><strong>Category:</strong> <?php echo ucfirst($row['category']); ?></p>
                                                    <p><strong>Priority:</strong> <?php echo ucfirst($row['priority']); ?></p>
                                                    <p><strong>Status:</strong> <?php echo ucfirst($row['status']); ?></p>
                                                </div>
                                            </div>
                                            <hr>
                                            <h6>Complaint Description</h6>
                                            <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
function viewResponses(complaintId) {
    $.ajax({
        url: 'response.php',
        type: 'GET',
        data: { complaint_id: complaintId },
        success: function(response) {
            $('#responsesModal').remove();
            $('body').append(response);
            $('#responsesModal').modal('show');
        },
        error: function(xhr, status, error) {
            alert('Error loading responses: ' + error);
            console.error(xhr.responseText);
        }
    });
}
$(document).ready(function() {
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .badge-pending { background-color: #ffc107; color: #212529; }
            .badge-in_progress { background-color: #17a2b8; color: #fff; }
            .badge-resolved { background-color: #28a745; color: #fff; }
            .badge-closed { background-color: #6c757d; color: #fff; }
            .badge-high { background-color: #dc3545; color: #fff; }
            .badge-medium { background-color: #fd7e14; color: #fff; }
            .badge-low { background-color: #20c997; color: #fff; }
            
            .priority-high { background-color: rgba(220, 53, 69, 0.1); }
        `)
        .appendTo('head');
});
</script>
</body>
</html> 