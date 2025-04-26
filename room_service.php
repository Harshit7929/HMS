<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user']) || empty($_SESSION['user']['email'])) {
    header("Location: login.php");
    exit();
}
$message = '';
$success = false;
$user = $_SESSION['user'];
$sql = "SELECT su.*, sd.emergency_phone, sd.course, sd.address, sd.profile_picture,
         sd.year_of_study
        FROM student_signup su 
        LEFT JOIN student_details sd ON su.regNo = sd.reg_no
        WHERE su.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['email']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$roomSql = "SELECT rb.* FROM room_bookings rb 
           WHERE rb.user_email = ? AND rb.status = 'confirmed' 
           ORDER BY rb.booking_date DESC LIMIT 1";
$roomStmt = $conn->prepare($roomSql);
$roomStmt->bind_param("s", $user['email']);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();
$roomData = $roomResult->fetch_assoc();
$hasRoom = ($roomResult->num_rows > 0);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    $reg_no = $userData['regNo'];
    $service_type = $_POST['service_type'];
    $description = $_POST['description'];
    if (!$hasRoom) {
        $message = "You must have a confirmed room booking to request room service.";
        $success = false;
    } else {
        $room_number = $roomData['room_number'];
        if (empty($service_type) || empty($description)) {
            $message = "Please fill in all required fields!";
            $success = false;
        } else {
            $sql = "INSERT INTO room_service_requests (reg_no, service_type, description, room_number) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $reg_no, $service_type, $description, $room_number);
            if ($stmt->execute()) {
                header("Location: room_service.php?success=1");
                exit();
            } else {
                $message = "Error: " . $stmt->error;
                $success = false;
            }
            $stmt->close();
        }
    }
}
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Room service request submitted successfully!";
    $success = true;
}
$reg_no = $userData['regNo'];
$requests = array();
if (!empty($reg_no)) {
    $sql = "SELECT r.request_id, r.service_type, r.room_number, r.description, 
            r.request_date, r.status, r.completion_date, r.assigned_to, s.gender, staff.name as staff_name
            FROM room_service_requests r 
            JOIN student_signup s ON r.reg_no = s.regNo 
            LEFT JOIN staff ON r.assigned_to = staff.staff_id
            WHERE r.reg_no = ? 
            ORDER BY r.request_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reg_no);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {$requests[] = $row;}
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Service Request</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/room_service.css"> -->
    <style>
        body {background-color: #f8f9fa;font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;}
        .main-content {padding: 20px;}
        .sidebar {background-color: #343a40;color: #fff;min-height: 100vh;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);position: sticky;top: 0; height: 100vh;overflow-y: auto; }
        .sidebar-sticky {padding-top: 20px;}
        .sidebar .nav-link {color: rgba(255, 255, 255, 0.75);padding: 12px 20px;margin: 4px 0;border-radius: 4px;transition: all 0.3s;}
        .sidebar .nav-link:hover {color: #fff;background-color: rgba(255, 255, 255, 0.1);}
        .sidebar .nav-link.active {color: #fff;background-color: rgba(255, 255, 255, 0.2);font-weight: 600;}
        .sidebar .nav-link i {margin-right: 10px;width: 20px;text-align: center;}
        .student-info,
        .request-form,
        .request-list {background-color: #fff;border-radius: 8px;box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);padding: 20px;margin-bottom: 25px;}
        .student-info h4,
        .request-form h4,
        .request-list h4 {color: #343a40;font-weight: 600;margin-bottom: 15px;padding-bottom: 10px;border-bottom: 1px solid #e9ecef;}
        .form-control {border-radius: 4px;border: 1px solid #ced4da;padding: 10px 15px;transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;}
        .form-control:focus {border-color: #80bdff;box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);}
        label {font-weight: 500;color: #495057;}
        .btn-primary {background-color: #007bff;border-color: #007bff;padding: 8px 20px;font-weight: 500;transition: all 0.3s;}
        .btn-primary:hover {background-color: #0069d9;border-color: #0062cc;}
        .table {border-radius: 8px;overflow: hidden;box-shadow: 0 0 5px rgba(0, 0, 0, 0.03);}
        .table thead th {background-color: #f8f9fa;border-bottom: 2px solid #dee2e6;color: #495057;font-weight: 600;text-transform: uppercase;font-size: 0.85rem;}
        .table tbody tr:hover {background-color: rgba(0, 123, 255, 0.03);}
        .status-pending {color: #dc3545;font-weight: 600;}
        .status-in_progress {color: #fd7e14;font-weight: 600;}
        .status-completed {color: #28a745;font-weight: 600;}
        .status-cancelled {color: #6c757d;font-weight: 600;}
        .alert {border-radius: 4px;border-left: 4px solid;}
        .alert-success {border-left-color: #28a745;background-color: #d4edda;}
        .alert-danger {border-left-color: #dc3545;background-color: #f8d7da;}
        .alert-warning {border-left-color: #ffc107;background-color: #fff3cd;}
        .alert-info {border-left-color: #17a2b8;background-color: #d1ecf1;}
        @media (max-width: 767.98px) {
        .sidebar {position: static;height: auto;min-height: unset;margin-bottom: 20px;}
        .main-content {margin-left: 0;}
        .table {display: block;width: 100%;overflow-x: auto;}}
        .navbar-dark .navbar-brand {font-weight: 700;letter-spacing: 0.5px;}
        .dropdown-menu {border: none;box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);border-radius: 0.25rem;}
        .dropdown-item {padding: 8px 20px;}
        .dropdown-item i {margin-right: 8px;width: 20px;text-align: center;}
        @keyframes fadeInDown {
        from {opacity: 0;transform: translate3d(0, -20px, 0);}
        to {opacity: 1;transform: translate3d(0, 0, 0);}}
        .alert {animation: fadeInDown 0.4s ease-out;}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="#">Student Portal</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <!-- <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li> -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user"></i> <?php echo $userData['firstName']; ?></a>
                    <!-- <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="profile.php"><i class="fas fa-id-card"></i> Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div> -->
                </li>
            </ul>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="room_booking.php"><i class="fas fa-bed"></i> Room Booking</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="room_service.php"><i class="fas fa-concierge-bell"></i> Room Service</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payment_history.php"><i class="fas fa-money-bill"></i> Payments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="noticeboard.php">
                                <i class="fas fa-bullhorn"></i> Notices</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout</a>
                        </li>
                    </ul>
                </div>
            </nav>
            <main role="main" class="col-md-10 ml-sm-auto main-content">
                <h2 class="mb-4">Room Service Request</h2>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <div class="student-info">
                    <h4>Student Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo $userData['firstName'] . ' ' . $userData['lastName']; ?></p>
                            <p><strong>Registration Number:</strong> <?php echo $userData['regNo']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($hasRoom): ?>
                                <p><strong>Hostel:</strong> <?php echo $roomData['hostel_name']; ?></p>
                                <p><strong>Room Number:</strong> <?php echo $roomData['room_number']; ?></p>
                                <p><strong>Floor:</strong> <?php echo $roomData['floor']; ?></p>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    You don't have a confirmed room booking. Please book a room first to request room services.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($hasRoom): ?>
                <div class="request-form">
                    <h4>New Request</h4>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="service_type">Service Type</label>
                            <select class="form-control" id="service_type" name="service_type" required>
                                <option value="">Select Service Type</option>
                                <option value="Cleaning">Room Cleaning</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Electrical">Electrical Issue</option>
                                <option value="Plumbing">Plumbing Issue</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="submit_request" class="btn btn-primary">Submit Request</button>
                    </form>
                </div>
                <?php endif; ?>
                <div class="request-list">
                    <h4>Your Service Requests</h4>
                    <?php if (empty($requests)): ?>
                        <p>No service requests found. Please submit a new request.</p>
                    <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Service Type</th>
                                    <th>Room Number</th>
                                    <th>Description</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Completion Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo $request['request_id']; ?></td>
                                        <td><?php echo $request['service_type']; ?></td>
                                        <td><?php echo $request['room_number']; ?></td>
                                        <td><?php echo $request['description']; ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($request['request_date'])); ?></td>
                                        <td class="status-<?php echo strtolower($request['status']); ?>">
                                            <?php 
                                            $status = ucfirst($request['status']);
                                            if ($status == 'In_progress') {
                                                echo 'In Progress';
                                            } else {
                                                echo $status;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($request['staff_name']) ? $request['staff_name'] : 'Not assigned'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($request['completion_date']) && $request['completion_date'] != '0000-00-00 00:00:00') {
                                                echo date('M d, Y H:i', strtotime($request['completion_date']));
                                            } else {echo '-';}
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($requests) > 0): ?>
                            <div class="alert alert-info">
                                <p><strong>Note:</strong> Your service requests will be assigned to staff based on your hostel.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            setTimeout(function() {
                $(".alert").fadeOut("slow");
            }, 5000);
            $("#sidebarToggle").on("click", function() {$(".sidebar").toggleClass("d-none d-md-block");});
        });
    </script>
</body>
</html> 