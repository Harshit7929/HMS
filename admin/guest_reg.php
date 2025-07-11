<?php
include 'admin_db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
$currentDate = date('Y-m-d');
$expiredBookingsQuery = $conn->prepare("
    SELECT gb.id, gb.room_number, gb.check_in_date, gb.stay_days 
    FROM guest_bookings gb 
    WHERE gb.status = 'approved'
");
$expiredBookingsQuery->execute();
$expiredBookings = $expiredBookingsQuery->get_result();
while($booking = $expiredBookings->fetch_assoc()) {
    $checkoutDate = date('Y-m-d', strtotime($booking['check_in_date'] . ' + ' . $booking['stay_days'] . ' days'));
    if($checkoutDate < $currentDate) {
        $updateBookingStmt = $conn->prepare("UPDATE guest_bookings SET status = 'completed' WHERE id = ?");
        $updateBookingStmt->bind_param("i", $booking['id']);
        $updateBookingStmt->execute();
        $updateRoomStmt = $conn->prepare("
            UPDATE rooms 
            SET available_beds = available_beds + 1,
                status = 'Available'
            WHERE room_number = ? AND hostel_name = 'Yamuna'
        ");
        $updateRoomStmt->bind_param("i", $booking['room_number']);
        $updateRoomStmt->execute();
    }
}
$message = '';
$errors = [];
$success = false;
$emailContent = null;
$sharingTypes = [
    'Single' => 'Single Room',
    '2-sharing' => '2-Sharing Room',
    '3-sharing' => '3-Sharing Room',
    '4-sharing' => '4-Sharing Room'
];
$roomTypeMapping = [
    'single' => 'Single',
    'double' => '2-sharing',
    'triple' => '3-sharing',
    'four_bed' => '4-sharing'
];
$selectedHostel = 'Yamuna';
$allocatedRoomsQuery = $conn->query("SELECT room_number FROM guest_bookings WHERE status = 'approved'");
$allocatedRooms = [];
while($room = $allocatedRoomsQuery->fetch_assoc()) {
    $roomKey = $room['room_number'];
    $allocatedRooms[$roomKey] = true;
}
$availableRooms = [];
$roomsQuery = $conn->query("SELECT id, hostel_name, room_number, floor, is_ac, sharing_type, available_beds, status 
                           FROM rooms 
                           WHERE status = 'Available' AND available_beds > 0 AND hostel_name = 'Yamuna'
                           ORDER BY floor, room_number");
while($room = $roomsQuery->fetch_assoc()) {
    $sharingType = $room['sharing_type'];
    if(!isset($availableRooms[$selectedHostel])) {$availableRooms[$selectedHostel] = [];}
    if(!isset($availableRooms[$selectedHostel][$sharingType])) {
        $availableRooms[$selectedHostel][$sharingType] = [];
    }
    $roomKey = $room['room_number'];
    if(!isset($allocatedRooms[$roomKey])) {
        $availableRooms[$selectedHostel][$sharingType][] = [
            'id' => $room['id'],
            'room_number' => $room['room_number'],
            'floor' => $room['floor'],
            'is_ac' => $room['is_ac'],
            'available_beds' => $room['available_beds']
        ];
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_booking'])) {
    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestPhone = trim($_POST['guest_phone'] ?? '');
    $hostelName = 'Yamuna'; 
    $roomType = trim($_POST['room_type'] ?? '');
    $roomNumber = trim($_POST['room_number'] ?? '');
    $stayDays = intval($_POST['stay_days'] ?? 0);
    $checkInDate = trim($_POST['check_in_date'] ?? '');
    $messOpted = isset($_POST['mess_opted']) ? 1 : 0;
    $specialRequests = trim($_POST['special_requests'] ?? '');
    $bookingRoomType = 'single'; 
    foreach($roomTypeMapping as $booking => $sharing) {
        if($sharing == $roomType) {
            $bookingRoomType = $booking;
            break;
        }
    }
    if (empty($guestName)) {$errors[] = "Guest name is required";}
    if (empty($guestEmail) || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {$errors[] = "Valid email address is required";}
    if (empty($guestPhone)) {$errors[] = "Guest phone number is required";}
    if (empty($roomNumber)) {$errors[] = "Room number is required";}
    if ($stayDays <= 0) {$errors[] = "Stay duration must be at least 1 day";}
    if (empty($checkInDate)) {$errors[] = "Check-in date is required";}
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $checkRoomQuery = $conn->prepare("SELECT available_beds FROM rooms WHERE hostel_name = ? AND room_number = ?");
            $checkRoomQuery->bind_param("si", $hostelName, $roomNumber);
            $checkRoomQuery->execute();
            $roomResult = $checkRoomQuery->get_result();
            if ($roomResult->num_rows == 0) {throw new Exception("Room not found");}
            $roomData = $roomResult->fetch_assoc();
            if ($roomData['available_beds'] <= 0) {throw new Exception("No beds available in this room");}
            $stmt = $conn->prepare("INSERT INTO guest_bookings 
                                  (guest_name, guest_email, guest_phone, room_type, 
                                   stay_days, check_in_date, mess_opted, special_requests, room_number, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            $stmt->bind_param("ssssisiss", 
                            $guestName, $guestEmail, $guestPhone, $bookingRoomType, 
                            $stayDays, $checkInDate, $messOpted, $specialRequests, $roomNumber);
            $stmt->execute();
            $bookingId = $conn->insert_id;
            $updateRoomStmt = $conn->prepare("UPDATE rooms 
                                          SET available_beds = available_beds - 1,
                                              status = CASE WHEN (available_beds - 1) <= 0 THEN 'Occupied' ELSE 'Available' END
                                          WHERE hostel_name = ? AND room_number = ? AND available_beds > 0");
            $updateRoomStmt->bind_param("si", $hostelName, $roomNumber);
            $updateRoomStmt->execute();
            
            if ($updateRoomStmt->affected_rows == 0) {throw new Exception("Failed to update room availability");}
            $conn->commit();
            $message = "Guest booking has been created and room has been allocated.";
            $success = true;
            $roomDetailsQuery = $conn->prepare("SELECT floor, is_ac FROM rooms WHERE hostel_name = ? AND room_number = ?");
            $roomDetailsQuery->bind_param("si", $hostelName, $roomNumber);
            $roomDetailsQuery->execute();
            $roomDetailsResult = $roomDetailsQuery->get_result();
            $roomDetails = $roomDetailsResult->fetch_assoc();
            $formattedCheckInDate = date('l, F j, Y', strtotime($checkInDate));
            $checkOutDate = date('l, F j, Y', strtotime($checkInDate . ' + ' . $stayDays . ' days'));
            $roomLabel = isset($sharingTypes[$roomType]) ? $sharingTypes[$roomType] : $roomType;
            $emailSubject = "Room Allocation Confirmation - Yamuna Hostel";
            $emailBody = "
Dear $guestName,
We are pleased to confirm your room allocation at Yamuna Hostel. Please find your reservation details below:
RESERVATION DETAILS
------------------
Guest Name: $guestName
Room Number: $roomNumber" . ($roomDetails['is_ac'] ? " (Air Conditioned)" : "") . "
Room Type: $roomLabel
Floor: " . $roomDetails['floor'] . "
Check-in Date: $formattedCheckInDate
Check-out Date: $checkOutDate
Stay Duration: $stayDays days
Mess Facility: " . ($messOpted ? "Included" : "Not included") . "
";
            if (!empty($specialRequests)) {$emailBody .= "Special Requests: $specialRequests\n\n";}
            
            $emailBody .= "
IMPORTANT INFORMATION
--------------------
- Check-in time begins at 12:00 PM
- Check-out time is before 11:00 AM
- Please bring your identification documents (ID card/passport)
- For any changes to your reservation, please contact us at least 24 hours in advance
";    
            if ($messOpted) {
                $emailBody .= "
MESS TIMINGS
-----------
Breakfast: 7:30 AM - 9:30 AM
Lunch: 12:30 PM - 2:30 PM
Dinner: 7:30 PM - 9:30 PM
";
            }
            $emailBody .= "
If you need any assistance or have questions, please don't hesitate to contact us:
Phone: +91-XXXXXXXXXX
Email: yamuna.hostel@example.com
We look forward to welcoming you to Yamuna Hostel.
Warm regards,
Hostel Administration
Yamuna Hostel
";
            $queueStmt = $conn->prepare("INSERT INTO email_queue 
                                     (booking_id, recipient_email, recipient_name, subject, message, status) 
                                     VALUES (?, ?, ?, ?, ?, 'pending')");
            $queueStmt->bind_param("issss", $bookingId, $guestEmail, $guestName, $emailSubject, $emailBody);
            $queueStmt->execute();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error creating booking: " . $e->getMessage();
        }
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_email'])) {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        $emailQuery = $conn->prepare("SELECT * FROM email_queue WHERE booking_id = ? AND status = 'pending' LIMIT 1");
        $emailQuery->bind_param("i", $bookingId);
        $emailQuery->execute();
        $emailResult = $emailQuery->get_result();
        if ($emailResult->num_rows > 0) {
            $emailData = $emailResult->fetch_assoc();
            $emailContent = $emailData;
            $message = "Email content displayed below. You can send it using your default email client.";
            $success = true;
        } else {$errors[] = "Email has already been sent or booking not found.";}
    } else {$errors[] = "Invalid booking ID.";}
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_email'])) {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $emailMessage = trim($_POST['message'] ?? '');
    if ($bookingId > 0 && !empty($subject) && !empty($emailMessage)) {
        $updateStmt = $conn->prepare("UPDATE email_queue SET subject = ?, message = ? WHERE booking_id = ? AND status = 'pending'");
        $updateStmt->bind_param("ssi", $subject, $emailMessage, $bookingId);
        $updateStmt->execute();
        if ($updateStmt->affected_rows > 0) {
            $message = "Email content has been updated.";
            $success = true;
            $emailQuery = $conn->prepare("SELECT * FROM email_queue WHERE booking_id = ? LIMIT 1");
            $emailQuery->bind_param("i", $bookingId);
            $emailQuery->execute();
            $emailResult = $emailQuery->get_result();
            if ($emailResult->num_rows > 0) {$emailContent = $emailResult->fetch_assoc();}
        } else {$errors[] = "Failed to update email or email already sent.";}
    } else {$errors[] = "Missing required fields for email update.";}
}
$viewBookingDetails = null;
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['view_booking'])) {
    $bookingId = intval($_GET['view_booking'] ?? 0);
    if ($bookingId > 0) {
        $bookingQuery = $conn->prepare("
            SELECT gb.*, r.floor, r.is_ac, r.hostel_name, r.sharing_type 
            FROM guest_bookings gb
            LEFT JOIN rooms r ON gb.room_number = r.room_number AND r.hostel_name = 'Yamuna'
            WHERE gb.id = ? LIMIT 1");
        $bookingQuery->bind_param("i", $bookingId);
        $bookingQuery->execute();
        $bookingResult = $bookingQuery->get_result();
        if ($bookingResult->num_rows > 0) {$viewBookingDetails = $bookingResult->fetch_assoc();}
    }
}
$filterRoomType = $_GET['filter_room_type'] ?? '';
$filterFloor = $_GET['filter_floor'] ?? '';
$filterDateFrom = $_GET['filter_date_from'] ?? '';
$filterDateTo = $_GET['filter_date_to'] ?? '';
$filterAC = isset($_GET['filter_ac']) ? 1 : '';
$filterMess = isset($_GET['filter_mess']) ? 1 : '';
$filterSort = $_GET['filter_sort'] ?? 'newest';
$query = "SELECT gb.*, r.floor, r.is_ac, r.hostel_name, r.sharing_type
          FROM guest_bookings gb
          LEFT JOIN rooms r ON gb.room_number = r.room_number AND r.hostel_name = 'Yamuna'
          WHERE gb.status = 'approved'";
$params = [];
$types = "";
if (!empty($filterRoomType)) {
    $query .= " AND gb.room_type = ?";
    $params[] = $filterRoomType;
    $types .= "s";
}
if (!empty($filterFloor)) {
    $query .= " AND r.floor = ?";
    $params[] = $filterFloor;
    $types .= "i";
}
if (!empty($filterDateFrom)) {
    $query .= " AND gb.check_in_date >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}
if (!empty($filterDateTo)) {
    $query .= " AND gb.check_in_date <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}
if ($filterAC !== '') {
    $query .= " AND r.is_ac = ?";
    $params[] = $filterAC;
    $types .= "i";
}
if ($filterMess !== '') {
    $query .= " AND gb.mess_opted = ?";
    $params[] = $filterMess;
    $types .= "i";
}
switch ($filterSort) {
    case 'oldest':
        $query .= " ORDER BY gb.created_at ASC";
        break;
    case 'check_in':
        $query .= " ORDER BY gb.check_in_date ASC";
        break;
    case 'room_asc':
        $query .= " ORDER BY gb.room_number ASC";
        break;
    case 'room_desc':
        $query .= " ORDER BY gb.room_number DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY gb.created_at DESC";
        break;
}
$roomStmt = $conn->prepare($query);
if (!empty($params)) {$roomStmt->bind_param($types, ...$params);}
$roomStmt->execute();
$roomResult = $roomStmt->get_result();
$allocatedRoomsList = [];
while ($room = $roomResult->fetch_assoc()) {$allocatedRoomsList[] = $room;}
$floorQuery = $conn->query("SELECT DISTINCT floor FROM rooms WHERE hostel_name = 'Yamuna' ORDER BY floor");
$floors = [];
while ($floor = $floorQuery->fetch_assoc()) {$floors[] = $floor['floor'];}
$conn->query("CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL
)");
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'allocation_list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Yamuna Hostel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- <link rel="stylesheet" href="css/guest_reg.css"> -->
    <style>
       :root { --primary-color: #3a5a78; --primary-dark: #2c4358; --secondary-color: #e9ecef; --accent-color: #3498db; 
        --success-color: #28a745; --danger-color: #dc3545; --warning-color: #ffc107; --info-color: #17a2b8; --gray-light: #f8f9fa; 
        --gray-medium: #e2e6ea; --gray-dark: #6c757d; --box-shadow: rgba(0, 0, 0, 0.05) 0px 1px 3px 0px, rgba(0, 0, 0, 0.1) 0px 1px 2px 0px; --border-radius: 0.25rem; --transition: all 0.3s ease; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7f9; color: #333; line-height: 1.6; position: relative; margin: 0; min-height: 100vh; }
        .main-header { background-color: var(--primary-color); color: #fff; padding: 0.75rem 0; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); position: fixed; width: 100%; top: 0; z-index: 1030; }
        .header-container { display: flex; justify-content: space-between; align-items: center; padding: 0 1.5rem; }
        .logo { display: flex; align-items: center; font-size: 1.25rem; font-weight: 600; }
        .logo i { font-size: 1.5rem; margin-right: 0.75rem; }
        .user-profile { display: flex; align-items: center; color: #fff; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background-color: rgba(255, 255, 255, 0.2); display: flex; 
            align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 0.5rem; }
        .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100%; background-color: #fff; padding-top: 4rem; box-shadow: var(--box-shadow); z-index: 1020; transition: var(--transition); }
        .sidebar-nav { list-style: none; padding: 0; margin: 1rem 0; }
        .nav-item { margin-bottom: 0.25rem; }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: #495057; text-decoration: none; transition: var(--transition); border-left: 3px solid transparent; }
        .nav-link i { margin-right: 0.75rem; font-size: 1.1rem; color: var(--gray-dark); }
        .nav-link:hover, .nav-link:focus { background-color: var(--gray-light); color: var(--primary-dark); border-left-color: var(--accent-color); }
        .nav-link.active { background-color: var(--gray-light); color: var(--primary-dark); font-weight: 500; border-left-color: var(--primary-color); }
        .nav-link.active i { color: var(--primary-color); }
        .main-content { padding: 5rem 1.5rem 1.5rem; margin-left: 250px; min-height: calc(100vh - 60px); transition: var(--transition); }
        .card { border: none; box-shadow: var(--box-shadow); margin-bottom: 1.5rem; border-radius: var(--border-radius); }
        .card-header { background-color: #fff; border-bottom: 1px solid var(--gray-medium); padding: 1rem 1.25rem; font-weight: 500; }
        .table { margin-bottom: 0; }
        .table th { font-weight: 500; border-top: none; background-color: var(--gray-light); }
        .table td, .table th { padding: 0.75rem 1rem; vertical-align: middle; }
        .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0, 0, 0, 0.02); }
        .table-hover tbody tr:hover { background-color: rgba(0, 0, 0, 0.04); }
        .form-label { font-weight: 500; margin-bottom: 0.375rem; color: #5a5a5a; }
        .form-control, .form-select { padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: var(--border-radius); transition: var(--transition); }
        .form-control:focus, .form-select:focus { border-color: var(--accent-color); box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.2); }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } 
        .main-content { margin-left: 0; padding-top: 4.5rem; } }
        @media (max-width: 576px) { .header-container { padding: 0 1rem; } .main-content { padding: 4.5rem 1rem 1rem; } .form-label { margin-bottom: 0.25rem; } }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <i class="bi bi-buildings"></i>
                <span>Yamuna Hostel Management</span>
            </div>
            <div class="d-flex">
                <button class="btn btn-outline-light me-2 d-md-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="user-profile">
                    <div class="avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="d-none d-sm-block">
                        <div class="fw-bold">Admin</div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            </li>
            <!-- <li class="nav-item">
                <a class="nav-link" href="room_allocation.php"><i class="bi bi-house-door"></i> Room Allocation</a>
            </li> -->
            <li class="nav-item">
                <a class="nav-link" href="manage_rooms.php"><i class="bi bi-grid"></i> Manage Rooms</a>
            </li>
            <!-- <li class="nav-item">
                <a class="nav-link" href="guest_records.php"><i class="bi bi-people"></i> Guest Records</a>
            </li> -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
            </li> -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="settings.php"><i class="bi bi-gear"></i> Settings</a>
            </li> -->
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </aside>
    <div class="main-content">
        <div class="container-fluid">
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($emailContent): ?>
                <div class="card mb-4">
                    <div class="email-preview-header">
                        <h5 class="card-title mb-0">Email Preview</h5>
                        <span class="close-button" onclick="closeEmailPreview()">×</span>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="booking_id" value="<?php echo $emailContent['booking_id']; ?>">
                            <div class="email-field">
                                <label class="form-label">To:</label>
                                <div><?php echo htmlspecialchars($emailContent['recipient_name']); ?> &lt;<?php echo htmlspecialchars($emailContent['recipient_email']); ?>&gt;</div>
                            </div>
                            <div class="email-field">
                                <label class="form-label">Subject:</label>
                                <input type="text" class="form-control" name="subject" value="<?php echo htmlspecialchars($emailContent['subject']); ?>">
                            </div>
                            <div class="email-field">
                                <label class="form-label">Message:</label>
                                <textarea class="form-control" name="message" rows="15"><?php echo htmlspecialchars($emailContent['message']); ?></textarea>
                            </div>
                            <div class="email-actions">
                                <button type="submit" name="update_email" class="btn btn-primary">Update Email</button>
                                <a href="mailto:<?php echo urlencode($emailContent['recipient_email']); ?>?subject=<?php echo urlencode($emailContent['subject']); ?>&body=<?php echo urlencode($emailContent['message']); ?>" class="btn btn-success">Send with Email Client</a>
                                <button type="button" class="btn btn-secondary" onclick="closeEmailPreview()">Close</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab == 'allocation_list' ? 'active' : ''; ?>" href="?tab=allocation_list">
                        <i class="bi bi-list-check"></i> Allocation List
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab == 'create_booking' ? 'active' : ''; ?>" href="?tab=create_booking">
                        <i class="bi bi-plus-circle"></i> Create Booking
                    </a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade <?php echo $activeTab == 'allocation_list' ? 'show active' : ''; ?>" id="allocation_list">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Room Allocations</h5>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                                <i class="bi bi-funnel"></i> Filters
                            </button>
                        </div>
                        <div class="collapse" id="filterCollapse">
                            <div class="card-body bg-light">
                                <form method="get" class="row g-3">
                                    <input type="hidden" name="tab" value="allocation_list">
                                    <div class="col-md-3">
                                        <label class="form-label">Room Type</label>
                                        <select name="filter_room_type" class="form-select">
                                            <option value="">All Types</option>
                                            <option value="single" <?php echo $filterRoomType == 'single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="double" <?php echo $filterRoomType == 'double' ? 'selected' : ''; ?>>Double</option>
                                            <option value="triple" <?php echo $filterRoomType == 'triple' ? 'selected' : ''; ?>>Triple</option>
                                            <option value="four_bed" <?php echo $filterRoomType == 'four_bed' ? 'selected' : ''; ?>>Four Bed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Floor</label>
                                        <select name="filter_floor" class="form-select">
                                            <option value="">All Floors</option>
                                            <?php foreach ($floors as $floor): ?>
                                                <option value="<?php echo $floor; ?>" <?php echo $filterFloor == $floor ? 'selected' : ''; ?>>
                                                    Floor <?php echo $floor; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-in From</label>
                                        <input type="date" name="filter_date_from" class="form-control" value="<?php echo $filterDateFrom; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-in To</label>
                                        <input type="date" name="filter_date_to" class="form-control" value="<?php echo $filterDateTo; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="filter_ac" id="filterAC" <?php echo $filterAC !== '' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="filterAC">AC Rooms Only</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="filter_mess" id="filterMess" <?php echo $filterMess !== '' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="filterMess">Mess Opted</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Sort By</label>
                                        <select name="filter_sort" class="form-select">
                                            <option value="newest" <?php echo $filterSort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                            <option value="oldest" <?php echo $filterSort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                            <option value="check_in" <?php echo $filterSort == 'check_in' ? 'selected' : ''; ?>>Check-in Date</option>
                                            <option value="room_asc" <?php echo $filterSort == 'room_asc' ? 'selected' : ''; ?>>Room Number (Asc)</option>
                                            <option value="room_desc" <?php echo $filterSort == 'room_desc' ? 'selected' : ''; ?>>Room Number (Desc)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                        <a href="?tab=allocation_list" class="btn btn-outline-secondary">Clear Filters</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Room</th> <th>Guest Name</th> <th>Check-in</th>
                                            <th>Duration</th> <th>Room Type</th> <th>Mess</th> <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($allocatedRoomsList)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-3">No room allocations found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($allocatedRoomsList as $booking): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo $booking['room_number']; ?>
                                                        <?php if ($booking['is_ac']): ?>
                                                            <span class="ac-badge">AC</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($booking['check_in_date'])); ?></td>
                                                    <td><?php echo $booking['stay_days']; ?> days</td>
                                                    <td>
                                                        <?php 
                                                            $sharingTypeKey = isset($roomTypeMapping[$booking['room_type']]) ? $roomTypeMapping[$booking['room_type']] : $booking['room_type'];
                                                            echo isset($sharingTypes[$sharingTypeKey]) ? $sharingTypes[$sharingTypeKey] : $sharingTypeKey;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($booking['mess_opted']): ?>
                                                            <span class="badge bg-success">Yes</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?tab=allocation_list&view_booking=<?php echo $booking['id']; ?>" class="btn btn-info">
                                                                <i class="bi bi-eye"></i></a>
                                                            <form method="post" style="display:inline;">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <button type="submit" name="send_email" class="btn btn-primary">
                                                                    <i class="bi bi-envelope"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php if ($viewBookingDetails): ?>
                        <div class="card booking-details-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Booking Details</h5>
                                <a href="?tab=allocation_list" class="btn-close" aria-label="Close"></a>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Guest Information</h6>
                                        <div class="booking-detail-row">
                                            <strong>Name:</strong> <?php echo htmlspecialchars($viewBookingDetails['guest_name']); ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($viewBookingDetails['guest_email']); ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($viewBookingDetails['guest_phone']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Room Information</h6>
                                        <div class="booking-detail-row">
                                            <strong>Room Number:</strong> <?php echo $viewBookingDetails['room_number']; ?>
                                            <?php if ($viewBookingDetails['is_ac']): ?>
                                                <span class="ac-badge">AC</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Floor:</strong> <?php echo $viewBookingDetails['floor']; ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Room Type:</strong>
                                            <?php 
                                                $sharingTypeKey = isset($roomTypeMapping[$viewBookingDetails['room_type']]) ? $roomTypeMapping[$viewBookingDetails['room_type']] : $viewBookingDetails['room_type'];
                                                echo isset($sharingTypes[$sharingTypeKey]) ? $sharingTypes[$sharingTypeKey] : $sharingTypeKey;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Stay Information</h6>
                                        <div class="booking-detail-row">
                                            <strong>Check-in Date:</strong> <?php echo date('d M Y', strtotime($viewBookingDetails['check_in_date'])); ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Duration:</strong> <?php echo $viewBookingDetails['stay_days']; ?> days
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Check-out Date:</strong> 
                                            <?php echo date('d M Y', strtotime($viewBookingDetails['check_in_date'] . ' + ' . $viewBookingDetails['stay_days'] . ' days')); ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Mess Opted:</strong>
                                            <?php echo $viewBookingDetails['mess_opted'] ? 'Yes' : 'No'; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Additional Information</h6>
                                        <div class="booking-detail-row">
                                            <strong>Booking Date:</strong> 
                                            <?php echo date('d M Y H:i', strtotime($viewBookingDetails['created_at'])); ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Status:</strong> <?php echo ucfirst($viewBookingDetails['status']); ?>
                                        </div>
                                        <div class="booking-detail-row">
                                            <strong>Special Requests:</strong><br>
                                            <?php echo !empty($viewBookingDetails['special_requests']) ? 
                                                nl2br(htmlspecialchars($viewBookingDetails['special_requests'])) : 
                                                '<em>None</em>'; ?>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-end">
                                    <form method="post" class="me-2">
                                        <input type="hidden" name="booking_id" value="<?php echo $viewBookingDetails['id']; ?>">
                                        <button type="submit" name="send_email" class="btn btn-primary">
                                            <i class="bi bi-envelope"></i> Email Details
                                        </button>
                                    </form>
                                    <a href="?tab=allocation_list" class="btn btn-secondary">Close</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade <?php echo $activeTab == 'create_booking' ? 'show active' : ''; ?>" id="create_booking">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">New Guest Booking</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="?tab=create_booking">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Guest Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="guest_name" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" name="guest_email" required>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" name="guest_phone" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Room Type <span class="text-danger">*</span></label>
                                                <select name="room_type" class="form-select" id="roomTypeSelect" required>
                                                    <option value="">Select Room Type</option>
                                                    <?php foreach ($sharingTypes as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Check-in Date <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="check_in_date" min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Stay Duration (days) <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="stay_days" min="1" max="30" required>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Room Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="room_number" id="selectedRoomInput" readonly required>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" name="mess_opted" id="messOpted">
                                                    <label class="form-check-label" for="messOpted">
                                                        Mess Facility Opted
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Special Requests</label>
                                            <textarea class="form-control" name="special_requests" rows="3"></textarea>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" name="create_booking" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Create Booking
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header"><h5 class="mb-0">Available Rooms</h5></div>
                                <div class="card-body p-0">
                                    <div class="available-rooms-list">
                                        <div class="list-group list-group-flush" id="availableRoomsList">
                                            <div class="text-center p-3">Please select a room type first</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {document.getElementById('sidebar').classList.toggle('show');});
        function closeEmailPreview() {window.location.href = window.location.pathname + '?tab=<?php echo $activeTab; ?>';}
        document.getElementById('roomTypeSelect')?.addEventListener('change', function() {
            const selectedType = this.value;
            const roomsList = document.getElementById('availableRoomsList');
            if (!selectedType) {
                roomsList.innerHTML = '<div class="text-center p-3">Please select a room type first</div>';
                return;
            }
            const availableRooms = <?php echo json_encode($availableRooms); ?>;
            const hostel = '<?php echo $selectedHostel; ?>';
            if (!availableRooms[hostel] || !availableRooms[hostel][selectedType] || availableRooms[hostel][selectedType].length === 0) {
                roomsList.innerHTML = '<div class="text-center p-3">No available rooms for this type</div>';
                return;
            }
            let roomsHtml = '';
            availableRooms[hostel][selectedType].forEach(room => {
                roomsHtml += `
                    <a href="#" class="list-group-item list-group-item-action available-room" 
                       data-room-number="${room.room_number}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Room ${room.room_number}</strong>
                                <div>Floor ${room.floor} ${room.is_ac ? '<span class="ac-badge">AC</span>' : ''}</div>
                            </div>
                            <div>
                                <span class="badge bg-info">${room.available_beds} bed(s) available</span>
                            </div>
                        </div>
                    </a>
                `;
            });
            roomsList.innerHTML = roomsHtml;
            document.querySelectorAll('.available-room').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const roomNumber = this.getAttribute('data-room-number');
                    document.getElementById('selectedRoomInput').value = roomNumber;
                    document.querySelectorAll('.available-room').forEach(el => {el.classList.remove('active');});
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>