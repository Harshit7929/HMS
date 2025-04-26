<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include('db.php');
$student_email = $_SESSION['user']['email'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'submit_complaint') {
            $complaint_subject = trim($_POST['subject']);
            $complaint_message = trim($_POST['message']);
            $category = trim($_POST['category']);
            $priority = trim($_POST['priority']);
            if (!empty($complaint_subject) && !empty($complaint_message)) {
                $sql = "INSERT INTO complaints (student_email, subject, description, category, priority) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $student_email, $complaint_subject, $complaint_message, $category, $priority);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Complaint submitted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error submitting complaint: " . $conn->error;
                }
                $stmt->close();
            } else {$_SESSION['error_message'] = "All fields are required!";}
        } elseif ($_POST['action'] === 'close_complaint') {
            $complaint_id = $_POST['complaint_id'];
            $check_sql = "SELECT id FROM complaints WHERE id = ? AND student_email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $complaint_id, $student_email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $sql = "UPDATE complaints SET status = 'closed' WHERE id = ? AND student_email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $complaint_id, $student_email);
                if ($stmt->execute()) {
                    $response_sql = "INSERT INTO complaint_responses (complaint_id, responder_email, response, action, performed_by) VALUES (?, ?, ?, ?, ?)";
                    $response_stmt = $conn->prepare($response_sql);
                    $action = "Complaint Closed";
                    $response = "The complaint has been closed by the student.";
                    $performed_by = "student";
                    $response_stmt->bind_param("issss", $complaint_id, $student_email, $response, $action, $performed_by);
                    $response_stmt->execute();
                    $response_stmt->close();
                    $_SESSION['success_message'] = "Complaint closed successfully!";
                } else {$_SESSION['error_message'] = "Error closing complaint!";}
                $stmt->close();
            } else {$_SESSION['error_message'] = "Complaint not found or you don't have permission to close it!";}
            $check_stmt->close();
        } elseif ($_POST['action'] === 'add_response') {
            $complaint_id = $_POST['complaint_id'];
            $response_message = trim($_POST['response_message']);
            $check_sql = "SELECT id FROM complaints WHERE id = ? AND student_email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $complaint_id, $student_email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0 && !empty($response_message)) {
                $sql = "INSERT INTO complaint_responses (complaint_id, responder_email, response, action, performed_by) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $action = "Response Added";
                $performed_by = "student";
                $stmt->bind_param("issss", $complaint_id, $student_email, $response_message, $action, $performed_by);
                if ($stmt->execute()) {$_SESSION['success_message'] = "Response added successfully!";} 
                else {$_SESSION['error_message'] = "Error adding response: " . $conn->error;}
                $stmt->close();
            } else {$_SESSION['error_message'] = "Invalid complaint or empty response!";}
            $check_stmt->close();
        }
        header("Location: complaints.php");
        exit();
    }
}
$success_message = "";
$error_message = "";
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);}
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM complaint_responses WHERE complaint_id = c.id) as response_count,
        COALESCE(st.name, '') as assigned_to_name,
        COALESCE(st.department, '') as assigned_to_department
        FROM complaints c 
        LEFT JOIN staff st ON c.assigned_to = st.email
        WHERE c.student_email = ? 
        ORDER BY c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_email);
$stmt->execute();
$result = $stmt->get_result();
function getComplaintResponses($conn, $complaint_id) {
    $responses_sql = "SELECT cr.*, 
                     COALESCE(s.name, u.name, 'Unknown') as responder_name,
                     COALESCE(s.department, '') as responder_department
                     FROM complaint_responses cr
                     LEFT JOIN staff s ON cr.responder_email = s.email
                     LEFT JOIN users u ON cr.responder_email = u.email
                     LEFT JOIN student_signup ss ON cr.responder_email = ss.email
                     WHERE cr.complaint_id = ?
                     ORDER BY cr.created_at ASC";
    $stmt = $conn->prepare($responses_sql);
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $responses;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Complaints Management</title>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
        <link href="css/complaints.css" rel="stylesheet">
        <style>
            .priority-high { background-color: #ffe6e6; }
            .priority-medium { background-color: #fff3e6; }
            .priority-low { background-color: #e6ffe6; }
            .status-badge {padding: 5px 10px;border-radius: 15px;font-size: 0.8em;}
            .status-pending { background-color: #ffd700; }
            .status-in_progress { background-color: #87ceeb; }
            .status-resolved { background-color: #90ee90; }
            .status-closed { background-color: #d3d3d3; }
            .header {height: 60px;background-color: #1a237e;color: white;position: fixed;width: 100%;top: 0;z-index: 1000;display: flex;align-items: center;padding: 0 20px;justify-content: space-between;}
            .header-right {display: flex;align-items: center;}
            .sidebar {width: 250px;background-color: #f8f9fa;height: 100vh;position: fixed;top: 60px;left: 0;padding-top: 20px;border-right: 1px solid #dee2e6;overflow-y: auto;}
            .logo-img {width: 200px;height: auto;margin: 10px auto;display: block;}
            .sidebar-menu {list-style: none;padding: 0;margin: 0;}
            .sidebar-menu li {padding: 10px 20px;}
            .sidebar-menu li a {color: #333;text-decoration: none;display: flex;align-items: center;}
            .sidebar-menu li a i {margin-right: 10px;width: 20px;}
            .sidebar-menu li:hover {background-color: #e9ecef;}
            .main-content {margin-left: 250px;margin-top: 60px;padding: 20px;}
            .active {background-color: #e9ecef;font-weight: bold;}
            .card {box-shadow: 0 2px 4px rgba(0,0,0,0.1);margin-bottom: 20px;}
            .card-header {background-color: #f8f9fa;border-bottom: 1px solid #dee2e6;}
            .form-control:focus {border-color: #1a237e;box-shadow: 0 0 0 0.2rem rgba(26,35,126,0.25);}
            .table th {background-color: #f8f9fa;border-top: none;}
            .table td {vertical-align: middle;}
            .btn-primary {background-color: #1a237e;border-color: #1a237e;}
            .btn-primary:hover {background-color: #151b58;border-color: #151b58;}
        </style>
    </head>
    <body>
        <header class="header">
            <h4 class="mb-0">Student Complaints Management System</h4>
            <!-- <div class="header-right">
                <div class="user-info">
                    Welcome, <?php echo isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'Student'; ?>
                </div>
                <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
            </div> -->
        </header>
        <div class="sidebar">
            <img src="images/srm.png" alt="SRMAP Logo" class="logo-img">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="complaints.php"><i class="fas fa-clipboard-list"></i> My Complaints</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <!-- <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li> -->
                <!-- <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li> -->
            </ul>
        </div>
        <div class="main-content">
            <div class="container-fluid">
                <!-- <h2 class="text-center mb-4">Complaint Management System</h2> -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Active Complaints</h5>
                                <p class="card-text">
                                    <?php
                                    $active_sql = "SELECT COUNT(*) as count FROM complaints 
                                                 WHERE student_email = ? AND status != 'closed'";
                                    $stmt = $conn->prepare($active_sql);
                                    $stmt->bind_param("s", $student_email);
                                    $stmt->execute();
                                    $active_count = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $active_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Resolved</h5>
                                <p class="card-text">
                                    <?php
                                    $resolved_sql = "SELECT COUNT(*) as count FROM complaints WHERE student_email = ? AND status = 'resolved'";
                                    $stmt = $conn->prepare($resolved_sql);
                                    $stmt->bind_param("s", $student_email);
                                    $stmt->execute();
                                    $resolved_count = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $resolved_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">In Progress</h5>
                                <p class="card-text">
                                    <?php
                                    $in_progress_sql = "SELECT COUNT(*) as count FROM complaints WHERE student_email = ? AND status = 'in_progress'";
                                    $stmt = $conn->prepare($in_progress_sql);
                                    $stmt->bind_param("s", $student_email);
                                    $stmt->execute();
                                    $in_progress_count = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $in_progress_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-secondary">
                            <div class="card-body">
                                <h5 class="card-title">Closed</h5>
                                <p class="card-text">
                                    <?php
                                    $closed_sql = "SELECT COUNT(*) as count FROM complaints WHERE student_email = ? AND status = 'closed'";
                                    $stmt = $conn->prepare($closed_sql);
                                    $stmt->bind_param("s", $student_email);
                                    $stmt->execute();
                                    $closed_count = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $closed_count;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Submit New Complaint</h4>
                    </div>
                    <div class="card-body">
                        <form action="complaints.php" method="post">
                            <input type="hidden" name="action" value="submit_complaint">
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="cleanliness">Cleanliness</option>
                                    <option value="security">Security</option>
                                    <option value="facilities">Facilities</option>
                                    <option value="other">Other</option>
                                    <!-- <option value="other">Other</option>
                                    <option value="other">Other</option>
                                    <option value="other">Other</option> -->
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select class="form-control" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="message">Description</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Complaint</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h4>Your Complaints</h4></div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Subject</th>
                                            <th>Category</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Responses</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        while ($row = $result->fetch_assoc()): ?>
                                            <tr class="priority-<?php echo $row['priority']; ?>">
                                                <td>#<?php echo $row['id']; ?></td>
                                                <td>
                                                    <a href="#" data-toggle="modal" data-target="#complaintModal<?php echo $row['id']; ?>">
                                                        <?php echo htmlspecialchars($row['subject']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo ucfirst($row['category']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $row['priority']; ?>">
                                                        <?php echo ucfirst($row['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['assigned_to_name'])): ?>
                                                        <?php echo htmlspecialchars($row['assigned_to_name']); ?>
                                                        <small class="d-block text-muted"><?php echo htmlspecialchars($row['assigned_to_department']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo $row['response_count']; ?> responses
                                                    </span>
                                                </td>
                                                <td><?php echo date("Y-m-d H:i", strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <?php if ($row['status'] != 'closed'): ?>
                                                            <form action="complaints.php" method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="close_complaint">
                                                                <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-secondary">Close</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-info" onclick="viewResponses(<?php echo $row['id']; ?>)">
                                                            <i class="fas fa-comments"></i> View (<?php echo $row['response_count']; ?>)
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <div class="modal fade" id="complaintModal<?php echo $row['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Complaint Details #<?php echo $row['id']; ?></h5>
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($row['subject']); ?></p>
                                                            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                                            <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></p>
                                                            <p><strong>Created:</strong> <?php echo date("Y-m-d H:i", strtotime($row['created_at'])); ?></p>
                                                            <?php if (!empty($row['assigned_to_name'])): ?>
                                                                <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($row['assigned_to_name']); ?> (<?php echo htmlspecialchars($row['assigned_to_department']); ?>)</p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">No complaints found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="responsesModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Responses</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" id="responsesContent">
                        
                    </div>
                </div>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <script>
            $(document).ready(function() {
                setTimeout(function() {
                    $('.alert').fadeOut('slow');
                }, 5000);
                $('<style>')
                    .prop('type', 'text/css')
                    .html(`
                        .badge-high { background-color: #dc3545; color: #fff; }
                        .badge-medium { background-color: #fd7e14; color: #fff; }
                        .badge-low { background-color: #20c997; color: #fff; }
                        
                        .status-pending { background-color: #ffc107; color: #212529; }
                        .status-in_progress { background-color: #17a2b8; color: #fff; }
                        .status-resolved { background-color: #28a745; color: #fff; }
                        .status-closed { background-color: #6c757d; color: #fff; }
                        
                        .status-badge {
                            display: inline-block;
                            padding: 0.25em 0.4em;
                            font-size: 75%;
                            font-weight: 700;
                            line-height: 1;
                            text-align: center;
                            white-space: nowrap;
                            vertical-align: baseline;
                            border-radius: 0.25rem;
                        }
                        
                        .priority-high { background-color: rgba(220, 53, 69, 0.1); }
                    `)
                    .appendTo('head');
            });
            function viewResponses(complaintId) {
                $.ajax({
                    url: 'get_responses.php',
                    type: 'GET',
                    data: { complaint_id: complaintId },
                    success: function(response) {
                        $('#responsesContent').html(response);
                        $('#responsesModal').modal('show');
                    },
                    error: function(xhr, status, error) {
                        alert('Error loading responses: ' + error);
                        console.error(xhr.responseText);
                    }
                });
            }
        </script>
    </body>
</html>