<?php
include('admin_db.php');
$success = $error = '';
$bookings_entries = isset($_GET['bookings_entries']) ? (int)$_GET['bookings_entries'] : 25;
$rooms_entries = isset($_GET['rooms_entries']) ? (int)$_GET['rooms_entries'] : 25;
$rooms_page = isset($_GET['rooms_page']) ? (int)$_GET['rooms_page'] : 1;
$rooms_ac_filter = isset($_GET['rooms_ac']) ? $_GET['rooms_ac'] : '';
$rooms_hostel_filter = isset($_GET['rooms_hostel']) ? $_GET['rooms_hostel'] : '';
$rooms_sharing_filter = isset($_GET['rooms_sharing']) ? (int)$_GET['rooms_sharing'] : 0;
$rooms_floor_filter = isset($_GET['rooms_floor']) ? (int)$_GET['rooms_floor'] : 0;
$bookings_ac_filter = isset($_GET['bookings_ac']) ? $_GET['bookings_ac'] : '';
$bookings_hostel_filter = isset($_GET['bookings_hostel']) ? $_GET['bookings_hostel'] : '';
$bookings_sharing_filter = isset($_GET['bookings_sharing']) ? (int)$_GET['bookings_sharing'] : 0;
$bookings_floor_filter = isset($_GET['bookings_floor']) ? (int)$_GET['bookings_floor'] : 0;
$bookings_status_filter = isset($_GET['bookings_status']) ? $_GET['bookings_status'] : '';
$bookings_gender_filter = isset($_GET['bookings_gender']) ? $_GET['bookings_gender'] : '';
$rooms_offset = ($rooms_page - 1) * $rooms_entries;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_room'])) {
        $hostelName = $_POST['hostel_name'];
        $floor = (int)$_POST['floor'];
        $roomNumber = (int)$_POST['room_number'];
        $isAc = (int)$_POST['is_ac'];
        $sharingType = $_POST['sharing_type'];        
        $availableBeds = (int)$sharingType;
        if ($floor < 1 || $floor > 5) {$error = "Floor must be between 1 and 5";}
        else if (floor($roomNumber/100) != $floor || 
                 ($roomNumber % 100) < 1 || 
                 ($roomNumber % 100) > 15) {
            $error = "Invalid room number format. Must be between " . $floor . "01-" . $floor . "15";
        }
        else {
            try {
                $conn->begin_transaction();
                $checkQuery = "SELECT COUNT(*) as count FROM rooms WHERE hostel_name = ? AND room_number = ?";
                $stmt = $conn->prepare($checkQuery);
                $stmt->bind_param("si", $hostelName, $roomNumber);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if ($result['count'] > 0) {throw new Exception("Room already exists in this hostel");}
                $sharingTypeStr = $sharingType . '-sharing';
                $query = "INSERT INTO rooms (hostel_name, room_number, floor, is_ac, sharing_type, status, available_beds) 
                         VALUES (?, ?, ?, ?, ?, 'Available', ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("siiisi", $hostelName, $roomNumber, $floor, $isAc, $sharingTypeStr, $availableBeds);
                if (!$stmt->execute()) {throw new Exception("Error executing query: " . $stmt->error);}
                $conn->commit();
                $success = "Room added successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error adding room: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_room'])) {
        $hostelName = $_POST['hostel_name'];
        $roomNumber = (int)$_POST['room_number'];
        $isAc = (int)$_POST['is_ac'];
        $sharingType = $_POST['sharing_type'] . '-sharing';
        $status = $_POST['status'];
        $availableBeds = (int)$_POST['available_beds'];
        try {
            $conn->begin_transaction();
            $query = "UPDATE rooms SET is_ac = ?, sharing_type = ?, status = ?, available_beds = ? 
                     WHERE hostel_name = ? AND room_number = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issisi", $isAc, $sharingType, $status, $availableBeds, $hostelName, $roomNumber);
            if (!$stmt->execute()) {throw new Exception("Error executing query: " . $stmt->error);}
            $conn->commit();
            $success = "Room updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating room: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_room'])) {
        $hostelName = $_POST['hostel_name'];
        $roomNumber = (int)$_POST['room_number'];
        try {
            $conn->begin_transaction();
            $checkQuery = "SELECT COUNT(*) as count FROM room_bookings 
                          WHERE hostel_name = ? AND room_number = ? AND status = 'confirmed'";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("si", $hostelName, $roomNumber);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result['count'] > 0) {throw new Exception("Cannot delete room with active bookings");}
            $deleteBookingsQuery = "DELETE FROM room_bookings WHERE hostel_name = ? AND room_number = ?";
            $stmt = $conn->prepare($deleteBookingsQuery);
            $stmt->bind_param("si", $hostelName, $roomNumber);
            if (!$stmt->execute()) {throw new Exception("Error deleting bookings: " . $stmt->error);}            
            $deleteRoomQuery = "DELETE FROM rooms WHERE hostel_name = ? AND room_number = ?";
            $stmt = $conn->prepare($deleteRoomQuery);
            $stmt->bind_param("si", $hostelName, $roomNumber);
            if (!$stmt->execute()) {throw new Exception("Error deleting room: " . $stmt->error);}
            $conn->commit();
            $success = "Room deleted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting room: " . $e->getMessage();
        }
    } elseif (isset($_POST['booking_action'])) {
        $bookingId = (int)$_POST['booking_id'];
        $action = $_POST['action'];
        try {
            $conn->begin_transaction();
            if ($action === 'confirm') {
                $bookingQuery = "SELECT hostel_name, room_number FROM room_bookings WHERE id = ?";
                $stmt = $conn->prepare($bookingQuery);
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                $booking = $stmt->get_result()->fetch_assoc();
                if (!$booking) {throw new Exception("Booking not found");}
                $roomQuery = "SELECT available_beds FROM rooms WHERE hostel_name = ? AND room_number = ?";
                $stmt = $conn->prepare($roomQuery);
                $stmt->bind_param("si", $booking['hostel_name'], $booking['room_number']);
                $stmt->execute();
                $room = $stmt->get_result()->fetch_assoc();
                if ($room['available_beds'] <= 0) {throw new Exception("Room has no available beds");}
                $updateBookingQuery = "UPDATE room_bookings SET status = 'confirmed' WHERE id = ?";
                $stmt = $conn->prepare($updateBookingQuery);
                $stmt->bind_param("i", $bookingId);
                if (!$stmt->execute()) {throw new Exception("Error confirming booking: " . $stmt->error);}                
                $updateRoomQuery = "UPDATE rooms SET available_beds = available_beds - 1 
                                   WHERE hostel_name = ? AND room_number = ?";
                $stmt = $conn->prepare($updateRoomQuery);
                $stmt->bind_param("si", $booking['hostel_name'], $booking['room_number']);
                if (!$stmt->execute()) {throw new Exception("Error updating room availability: " . $stmt->error);}
                $success = "Booking confirmed successfully!";
            } elseif ($action === 'cancel') {
                $bookingQuery = "SELECT hostel_name, room_number, status FROM room_bookings WHERE id = ?";
                $stmt = $conn->prepare($bookingQuery);
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
                $booking = $stmt->get_result()->fetch_assoc();
                if (!$booking) {throw new Exception("Booking not found");}
                $updateBookingQuery = "UPDATE room_bookings SET status = 'cancelled' WHERE id = ?";
                $stmt = $conn->prepare($updateBookingQuery);
                $stmt->bind_param("i", $bookingId);
                if (!$stmt->execute()) {throw new Exception("Error cancelling booking: " . $stmt->error);}
                if ($booking['status'] === 'confirmed') {
                    $updateRoomQuery = "UPDATE rooms SET available_beds = available_beds + 1 
                                       WHERE hostel_name = ? AND room_number = ?";
                    $stmt = $conn->prepare($updateRoomQuery);
                    $stmt->bind_param("si", $booking['hostel_name'], $booking['room_number']);
                    if (!$stmt->execute()) {throw new Exception("Error updating room availability: " . $stmt->error);}
                }
                
                $success = "Booking cancelled successfully!";
            } else {throw new Exception("Invalid action");}
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error processing booking: " . $e->getMessage();
        }
    }
}
$bookingsQuery = "SELECT rb.*, r.is_ac, r.sharing_type, r.floor, s.firstName, s.lastName, s.gender
                 FROM room_bookings rb
                 JOIN rooms r ON rb.hostel_name = r.hostel_name AND rb.room_number = r.room_number
                 JOIN student_signup s ON rb.user_email = s.email
                 WHERE 1=1";
if ($bookings_ac_filter !== '') {$bookingsQuery .= " AND r.is_ac = " . (int)$bookings_ac_filter;}
if ($bookings_hostel_filter !== '') {$bookingsQuery .= " AND rb.hostel_name = '" . $conn->real_escape_string($bookings_hostel_filter) . "'";}
if ($bookings_sharing_filter > 0) {$bookingsQuery .= " AND r.sharing_type = '" . $bookings_sharing_filter . "-sharing'";}
if ($bookings_floor_filter > 0) {$bookingsQuery .= " AND r.floor = " . $bookings_floor_filter;}
if ($bookings_status_filter !== '') {$bookingsQuery .= " AND rb.status = '" . $conn->real_escape_string($bookings_status_filter) . "'";}
if ($bookings_gender_filter !== '') {$bookingsQuery .= " AND s.gender = '" . $conn->real_escape_string($bookings_gender_filter) . "'";}
$bookingsQuery .= " ORDER BY rb.hostel_name, rb.room_number";
$bookedRooms = $conn->query($bookingsQuery);
if (!$bookedRooms) {
    $error = "Error fetching bookings: " . $conn->error;
    $bookedRooms = [];
} else {$bookedRooms = $bookedRooms->fetch_all(MYSQLI_ASSOC);}
$roomsQuery = "SELECT r.*, 
              (SELECT COUNT(*) 
               FROM room_bookings rb 
               WHERE rb.hostel_name = r.hostel_name 
               AND rb.room_number = r.room_number 
               AND rb.status = 'confirmed') as current_occupants
              FROM rooms r WHERE 1=1";
if ($rooms_ac_filter !== '') {$roomsQuery .= " AND r.is_ac = " . (int)$rooms_ac_filter;}
if ($rooms_hostel_filter !== '') {$roomsQuery .= " AND r.hostel_name = '" . $conn->real_escape_string($rooms_hostel_filter) . "'";}
if ($rooms_sharing_filter > 0) {$roomsQuery .= " AND r.sharing_type = '" . $rooms_sharing_filter . "-sharing'";}
if ($rooms_floor_filter > 0) {$roomsQuery .= " AND r.floor = " . $rooms_floor_filter;}
$total_records_query = "SELECT COUNT(*) as count FROM (" . $roomsQuery . ") as temp";
$total_records = $conn->query($total_records_query)->fetch_assoc()['count'];
$total_pages = ceil($total_records / $rooms_entries);
$roomsQuery .= " ORDER BY r.hostel_name, r.floor, r.room_number LIMIT $rooms_offset, $rooms_entries";
$rooms = $conn->query($roomsQuery);
if (!$rooms) {
    $error = "Error fetching rooms: " . $conn->error;
    $rooms = [];
} else {$rooms = $rooms->fetch_all(MYSQLI_ASSOC);}
$editRoom = null;
if (isset($_GET['edit_hostel']) && isset($_GET['edit_room'])) {
    $editHostel = $_GET['edit_hostel'];
    $editRoomNumber = (int)$_GET['edit_room'];
    $editQuery = "SELECT * FROM rooms WHERE hostel_name = ? AND room_number = ?";
    $stmt = $conn->prepare($editQuery);
    $stmt->bind_param("si", $editHostel, $editRoomNumber);
    $stmt->execute();
    $editRoom = $stmt->get_result()->fetch_assoc();
}

session_start();
if (isset($_GET['tab'])) {
    $_SESSION['active_tab'] = $_GET['tab'];
}
$activeTab = isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : 'addRoom';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Admin Panel</title>
    <!-- <link rel="stylesheet" href="css/manage_rooms.css"> -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Global Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background-color: #f5f6fa;
    color: #333;
    line-height: 1.6;
}

.container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background-color: #2c3e50;
    color: #fff;
    position: fixed;
    height: 100%;
    overflow-y: auto;
    transition: all 0.3s;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.logo {
    padding: 20px;
    font-size: 22px;
    font-weight: bold;
    text-align: center;
    border-bottom: 1px solid #34495e;
    margin-bottom: 10px;
}

.sidebar nav {
    padding: 10px 0;
}

.sidebar nav a {
    display: block;
    color: #ecf0f1;
    padding: 15px 20px;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 16px;
}

.sidebar nav a:hover {
    background-color: #34495e;
    color: #fff;
}

.sidebar nav a.active {
    background-color: #3498db;
    color: #fff;
    border-left: 4px solid #2980b9;
}

.sidebar nav a i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

/* Main Content Styles */
.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 30px;
    transition: all 0.3s;
}

h1 {
    color: #2c3e50;
    margin-bottom: 20px;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    font-size: 28px;
}

h2 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 22px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    font-weight: 500;
}

.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Tab Styles */
.tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.tab-btn {
    padding: 12px 20px;
    background-color: #f1f1f1;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    transition: background-color 0.3s;
    border-radius: 5px 5px 0 0;
    margin-right: 5px;
}

.tab-btn.active {
    background-color: #3498db;
    color: white;
}

.tab-btn:hover {
    background-color: #ddd;
}

.tab-btn.active:hover {
    background-color: #2980b9;
}

.tab-content {
    display: none;
    padding: 20px 0;
}

.tab-content.active {
    display: block;
}

/* Card Styles */
.compact-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 20px;
}

/* Form Styles */
.form-row {
    display: flex;
    margin-bottom: 15px;
    gap: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-group select,
.form-group input[type="text"],
.form-group input[type="number"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 15px;
    transition: border-color 0.3s;
}

.form-group select:focus,
.form-group input[type="text"]:focus,
.form-group input[type="number"]:focus {
    border-color: #3498db;
    outline: none;
}

.submit-btn {
    background-color: #3498db;
    color: white;
    border: none;
    padding: 12px 20px;
    font-size: 16px;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.submit-btn:hover {
    background-color: #2980b9;
}

.cancel-btn {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 12px 20px;
    font-size: 16px;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.cancel-btn:hover {
    background-color: #5a6268;
}

.submit-btn.delete {
    background-color: #dc3545;
}

.submit-btn.delete:hover {
    background-color: #c82333;
}

/* Filter Styles */
.filters {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 15px;
    margin-bottom: 20px;
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.filter-form select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.filter-btn {
    background-color: #3498db;
    color: white;
    border: none;
    padding: 8px 15px;
    font-size: 14px;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.filter-btn:hover {
    background-color: #2980b9;
}

.clear-filters {
    background-color: #6c757d;
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    font-size: 14px;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.clear-filters:hover {
    background-color: #5a6268;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    margin-bottom: 20px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background-color: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    overflow: hidden;
}

.data-table th,
.data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.data-table th {
    background-color: #f4f6f9;
    font-weight: 600;
    color: #2c3e50;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background-color: #f9f9f9;
}

.no-data {
    text-align: center;
    color: #6c757d;
    padding: 20px 0;
}

/* Status Badge Styles */
.status-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    display: inline-block;
}

.status-badge.pending {
    background-color: #ffeeba;
    color: #856404;
}

.status-badge.confirmed,
.status-badge.available {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.cancelled,
.status-badge.full {
    background-color: #f8d7da;
    color: #721c24;
}

.status-badge.maintenance {
    background-color: #e2e3e5;
    color: #383d41;
}

/* Action Button Styles */
.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    color: white;
    cursor: pointer;
    font-size: 13px;
    margin-right: 5px;
    transition: opacity 0.3s;
}

.action-btn:hover {
    opacity: 0.85;
}

.action-btn.edit {
    background-color: #17a2b8;
}

.action-btn.delete {
    background-color: #dc3545;
}

.action-btn.confirm {
    background-color: #28a745;
}

.action-btn.cancel {
    background-color: #dc3545;
}

.action-btn.disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 100;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    width: 500px;
    max-width: 90%;
    position: relative;
}

.close {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 24px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close:hover {
    color: #333;
}

/* Pagination Styles */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    gap: 5px;
}

.pagination a {
    color: #3498db;
    padding: 8px 16px;
    text-decoration: none;
    transition: background-color 0.3s;
    border-radius: 4px;
    background-color: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.pagination a.active {
    background-color: #3498db;
    color: white;
}

.pagination a:hover:not(.active) {
    background-color: #f1f1f1;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .filters .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .modal-content {
        width: 95%;
    }
}

    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">Admin Panel</div>
            <nav>
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="manage_rooms.php" class="active"><i class="fas fa-bed"></i> Manage Rooms</a>
                <a href="update_profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="admin_access_log.php"><i class="fas fa-history"></i> Access Log</a>
                <a href="payment_history.php"><i class="fas fa-money-bill"></i> Payments</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        <div class="main-content">
            <h1>Manage Rooms</h1>
            <?php if (isset($success) && !empty($success)): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="tabs">
                <button class="tab-btn <?php echo $activeTab == 'addRoom' ? 'active' : ''; ?>" 
                        onclick="openTab('addRoom')">Add Room</button>
                <button class="tab-btn <?php echo $activeTab == 'currentBookings' ? 'active' : ''; ?>" 
                        onclick="openTab('currentBookings')">Current Bookings</button>
                <button class="tab-btn <?php echo $activeTab == 'roomsDetails' ? 'active' : ''; ?>" 
                        onclick="openTab('roomsDetails')">Rooms Details</button>
            </div>
            <div id="addRoom" class="tab-content <?php echo $activeTab == 'addRoom' ? 'active' : ''; ?>">
                <div class="compact-card">
                    <h2>Add New Room</h2>
                    <form method="POST" class="add-room-form" onsubmit="return validateRoomNumber()">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Hostel Name:</label>
                                <select name="hostel_name" required>
                                    <option value="Ganga">Ganga</option>
                                    <option value="Vedavathi">Vedavathi</option>
                                    <option value="Krishna">Krishna</option>
                                    <option value="Narmadha">Narmadha</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Floor:</label>
                                <select name="floor" id="floor" required onchange="updateRoomNumbers()">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>">Floor <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Room Number:</label>
                                <select name="room_number" id="room_number" required>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>AC Room:</label>
                                <select name="is_ac" required>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sharing Type:</label>
                                <select name="sharing_type" required>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <button type="submit" name="add_room" class="submit-btn">Add Room</button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="currentBookings" class="tab-content <?php echo $activeTab == 'currentBookings' ? 'active' : ''; ?>">
                <div class="filters">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="tab" value="currentBookings">
                        <select name="bookings_entries">
                            <option value="25" <?php echo $bookings_entries == 25 ? 'selected' : ''; ?>>25 entries</option>
                            <option value="50" <?php echo $bookings_entries == 50 ? 'selected' : ''; ?>>50 entries</option>
                            <option value="100" <?php echo $bookings_entries == 100 ? 'selected' : ''; ?>>100 entries</option>
                            <option value="250" <?php echo $bookings_entries == 250 ? 'selected' : ''; ?>>250 entries</option>
                        </select>
                        <select name="bookings_ac">
                            <option value="">All Room Types</option>
                            <option value="1" <?php echo $bookings_ac_filter == '1' ? 'selected' : ''; ?>>AC Rooms</option>
                            <option value="0" <?php echo $bookings_ac_filter == '0' ? 'selected' : ''; ?>>Non-AC Rooms</option>
                        </select>
                        <select name="bookings_hostel">
                            <option value="">All Hostels</option>
                            <option value="Ganga" <?php echo $bookings_hostel_filter == 'Ganga' ? 'selected' : ''; ?>>Ganga</option>
                            <option value="Vedavathi" <?php echo $bookings_hostel_filter == 'Vedavathi' ? 'selected' : ''; ?>>Vedavathi</option>
                            <option value="Krishna" <?php echo $bookings_hostel_filter == 'Krishna' ? 'selected' : ''; ?>>Krishna</option>
                            <option value="Narmadha" <?php echo $bookings_hostel_filter == 'Narmadha' ? 'selected' : ''; ?>>Narmadha</option>
                        </select>
                        <select name="bookings_sharing">
                            <option value="0">All Sharing Types</option>
                            <option value="2" <?php echo $bookings_sharing_filter == 2 ? 'selected' : ''; ?>>2-sharing</option>
                            <option value="3" <?php echo $bookings_sharing_filter == 3 ? 'selected' : ''; ?>>3-sharing</option>
                            <option value="4" <?php echo $bookings_sharing_filter == 4 ? 'selected' : ''; ?>>4-sharing</option>
                        </select>
                        <select name="bookings_floor">
                            <option value="0">All Floors</option>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $bookings_floor_filter == $i ? 'selected' : ''; ?>>Floor <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="bookings_status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $bookings_status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $bookings_status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $bookings_status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <select name="bookings_gender">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo $bookings_gender_filter == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $bookings_gender_filter == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $bookings_gender_filter == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>         
                        <button type="submit" class="filter-btn">Apply Filters</button>
                        <a href="?tab=currentBookings" class="clear-filters">Clear Filters</a>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Name</th><th>Email</th><th>Gender</th><th>Hostel</th>
                                <th>Room No.</th><th>Room Type</th><th>Status</th><th>Booking Date</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($bookedRooms) > 0): ?>
                                <?php foreach($bookedRooms as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['firstName'] . ' ' . $booking['lastName']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['gender']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['hostel_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                        <td>
                                            <?php echo $booking['is_ac'] == 1 ? 'AC' : 'Non-AC'; ?>, 
                                            <?php echo htmlspecialchars($booking['sharing_type']); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                        <td>
                                            <?php if($booking['status'] == 'pending'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" name="booking_action" class="action-btn confirm">Confirm</button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" name="booking_action" class="action-btn cancel">Cancel</button>
                                                </form>
                                            <?php elseif($booking['status'] == 'confirmed'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" name="booking_action" class="action-btn cancel">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="action-btn disabled">No Actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="no-data">No bookings found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="roomsDetails" class="tab-content <?php echo $activeTab == 'roomsDetails' ? 'active' : ''; ?>">
                <div class="filters">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="tab" value="roomsDetails">
                        <select name="rooms_entries">
                            <option value="25" <?php echo $rooms_entries == 25 ? 'selected' : ''; ?>>25 entries</option>
                            <option value="50" <?php echo $rooms_entries == 50 ? 'selected' : ''; ?>>50 entries</option>
                            <option value="100" <?php echo $rooms_entries == 100 ? 'selected' : ''; ?>>100 entries</option>
                            <option value="250" <?php echo $rooms_entries == 250 ? 'selected' : ''; ?>>250 entries</option>
                        </select>
                        <select name="rooms_ac">
                            <option value="">All Room Types</option>
                            <option value="1" <?php echo $rooms_ac_filter == '1' ? 'selected' : ''; ?>>AC Rooms</option>
                            <option value="0" <?php echo $rooms_ac_filter == '0' ? 'selected' : ''; ?>>Non-AC Rooms</option>
                        </select>
                        <select name="rooms_hostel">
                            <option value="">All Hostels</option>
                            <option value="Ganga" <?php echo $rooms_hostel_filter == 'Ganga' ? 'selected' : ''; ?>>Ganga</option>
                            <option value="Vedavathi" <?php echo $rooms_hostel_filter == 'Vedavathi' ? 'selected' : ''; ?>>Vedavathi</option>
                            <option value="Krishna" <?php echo $rooms_hostel_filter == 'Krishna' ? 'selected' : ''; ?>>Krishna</option>
                            <option value="Narmadha" <?php echo $rooms_hostel_filter == 'Narmadha' ? 'selected' : ''; ?>>Narmadha</option>
                            <option value="Yamuna" <?php echo $rooms_hostel_filter == 'Yamuna' ? 'selected' : ''; ?>>Yamuna</option>
                        </select>
                        <select name="rooms_sharing">
                            <option value="0">All Sharing Types</option>
                            <option value="2" <?php echo $rooms_sharing_filter == 2 ? 'selected' : ''; ?>>2-sharing</option>
                            <option value="3" <?php echo $rooms_sharing_filter == 3 ? 'selected' : ''; ?>>3-sharing</option>
                            <option value="4" <?php echo $rooms_sharing_filter == 4 ? 'selected' : ''; ?>>4-sharing</option>
                        </select>
                        <select name="rooms_floor">
                            <option value="0">All Floors</option>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $rooms_floor_filter == $i ? 'selected' : ''; ?>>Floor <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="filter-btn">Apply Filters</button>
                        <a href="?tab=roomsDetails" class="clear-filters">Clear Filters</a>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hostel</th> <th>Room No.</th> <th>Floor</th> <th>Type</th> <th>Sharing</th>
                                <th>Status</th> <th>Occupancy</th> <th>Available Beds</th> <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($rooms) > 0): ?>
                                <?php foreach($rooms as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['hostel_name']); ?></td>
                                        <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                        <td><?php echo htmlspecialchars($room['floor']); ?></td>
                                        <td><?php echo $room['is_ac'] == 1 ? 'AC' : 'Non-AC'; ?></td>
                                        <td><?php echo htmlspecialchars($room['sharing_type']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($room['status']); ?>">
                                                <?php echo ucfirst($room['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $roomCapacity = (int)$room['sharing_type'][0];
                                                $occupancy = (int)$room['current_occupants'];
                                                echo $occupancy . '/' . $roomCapacity; 
                                            ?>
                                        </td>
                                        <td><?php echo $room['available_beds']; ?></td>
                                        <td>
                                            <button type="button" class="action-btn edit" 
                                                    onclick="openEditModal('<?php echo $room['hostel_name']; ?>', <?php echo $room['room_number']; ?>, 
                                                                           <?php echo $room['is_ac']; ?>, '<?php echo $room['sharing_type'][0]; ?>', 
                                                                           '<?php echo $room['status']; ?>', <?php echo $room['available_beds']; ?>)">
                                                Edit
                                            </button>
                                            <button type="button" class="action-btn delete" 
                                                    onclick="openDeleteModal('<?php echo $room['hostel_name']; ?>', <?php echo $room['room_number']; ?>)">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="no-data">No rooms found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <?php if($total_pages > 1): ?>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?rooms_page=<?php echo $i; ?>&tab=roomsDetails&rooms_entries=<?php echo $rooms_entries; ?>&rooms_ac=<?php echo $rooms_ac_filter; ?>&rooms_hostel=<?php echo $rooms_hostel_filter; ?>&rooms_sharing=<?php echo $rooms_sharing_filter; ?>&rooms_floor=<?php echo $rooms_floor_filter; ?>" class="<?php echo $i == $rooms_page ? 'active' : ''; ?>">
                                <?php echo $i; ?></a>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div id="editRoomModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Room</h2>
            <form method="POST" class="edit-room-form">
                <input type="hidden" id="edit_hostel_name" name="hostel_name">
                <input type="hidden" id="edit_room_number" name="room_number">
                <div class="form-row">
                    <div class="form-group">
                        <label>Hostel:</label>
                        <input type="text" id="display_hostel" disabled>
                    </div>
                    <div class="form-group">
                        <label>Room Number:</label>
                        <input type="text" id="display_room" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>AC Room:</label>
                        <select name="is_ac" id="edit_is_ac" required>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sharing Type:</label>
                        <select name="sharing_type" id="edit_sharing_type" required>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                </div>
                <div class="form-row"> 
                    <div class="form-group">
                        <label>Status:</label>
                        <select name="status" id="edit_status" required>
                            <option value="Available">Available</option>
                            <option value="Full">Occupied</option>
                            <option value="Maintenance">Under Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Available Beds:</label>
                        <input type="number" name="available_beds" id="edit_available_beds" min="0" max="4" required>
                    </div>
                </div>
                <div class="form-row"><button type="submit" name="edit_room" class="submit-btn">Update Room</button></div>
            </form>
        </div>
    </div>
    <div id="deleteRoomModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete room <span id="delete_room_info"></span>? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" id="delete_hostel_name" name="hostel_name">
                <input type="hidden" id="delete_room_number" name="room_number">
                <div class="form-row">
                    <button type="button" class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_room" class="submit-btn delete">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTab(tabName) {window.location.href = '?tab=' + tabName;}
        function updateRoomNumbers() {
            var floor = document.getElementById('floor').value;
            var roomNumberSelect = document.getElementById('room_number');
            roomNumberSelect.innerHTML = '';
            for(var i = 1; i <= 15; i++) {
                var roomNumber = floor * 100 + i;
                var option = document.createElement('option');
                option.value = roomNumber;
                option.textContent = roomNumber;
                roomNumberSelect.appendChild(option);
            }
        }
        function validateRoomNumber() {
            var floor = parseInt(document.getElementById('floor').value);
            var roomNumber = parseInt(document.getElementById('room_number').value);
            if(Math.floor(roomNumber/100) !== floor || (roomNumber % 100) < 1 || (roomNumber % 100) > 15) {
                alert('Invalid room number format. Must be between ' + floor + '01-' + floor + '15');
                return false;
            }
            return true;
        }
        function openEditModal(hostelName, roomNumber, isAc, sharingType, status, availableBeds) {
            document.getElementById('edit_hostel_name').value = hostelName;
            document.getElementById('edit_room_number').value = roomNumber;
            document.getElementById('display_hostel').value = hostelName;
            document.getElementById('display_room').value = roomNumber;
            document.getElementById('edit_is_ac').value = isAc;
            document.getElementById('edit_sharing_type').value = sharingType;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_available_beds').value = availableBeds;
            document.getElementById('editRoomModal').style.display = 'block';
        }
        function closeEditModal() {document.getElementById('editRoomModal').style.display = 'none';}
        function openDeleteModal(hostelName, roomNumber) {
            document.getElementById('delete_hostel_name').value = hostelName;
            document.getElementById('delete_room_number').value = roomNumber;
            document.getElementById('delete_room_info').textContent = hostelName + ' ' + roomNumber;
            document.getElementById('deleteRoomModal').style.display = 'block';
        }
        function closeDeleteModal() {document.getElementById('deleteRoomModal').style.display = 'none';}
        window.onclick = function(event) {
            if (event.target == document.getElementById('editRoomModal')) {closeEditModal();}
            if (event.target == document.getElementById('deleteRoomModal')) {closeDeleteModal();}
        }
        document.addEventListener('DOMContentLoaded', function() {updateRoomNumbers();});
    </script>
</body>
</html>