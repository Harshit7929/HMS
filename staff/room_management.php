<?php
session_start();
include 'staff_db.php';
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_test_login.php");
    exit();}
$staffId = $_SESSION['staff_id'];
$staffName = $_SESSION['name'];
$staffPosition = $_SESSION['position'];
$hostel = $_SESSION['hostel'];
if ($staffPosition !== 'Warden') {
    header("Location: staff_dashboard.php");
    exit();}
$page_title = "Room Management - $hostel Hostel";
$room_query = "SELECT COUNT(*) as total_rooms,
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied_rooms
                FROM rooms WHERE hostel_name = ?";
$stmt = $conn->prepare($room_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_assoc();
$occupancy_percent = ($rooms['total_rooms'] > 0) ? 
    round(($rooms['occupied_rooms'] / $rooms['total_rooms']) * 100) : 0;
$detailed_room_query = "SELECT room_number, floor, 
                        sharing_type as capacity, 
                        (CASE 
                            WHEN sharing_type = '2-sharing' THEN 2
                            WHEN sharing_type = '3-sharing' THEN 3
                            WHEN sharing_type = '4-sharing' THEN 4
                            ELSE 0
                        END - available_beds) as occupied_beds,
                        available_beds,
                        status, 
                        CASE 
                            WHEN is_ac = 1 THEN CONCAT('AC ', sharing_type) 
                            ELSE CONCAT('Non-AC ', sharing_type) 
                        END as room_type
                        FROM rooms
                        WHERE hostel_name = ?
                        ORDER BY floor, room_number";
$stmt = $conn->prepare($detailed_room_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$detailed_rooms = $stmt->get_result();
$booking_query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings
                    FROM room_bookings WHERE hostel_name = ?";
$stmt = $conn->prepare($booking_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_assoc();
$floor_query = "SELECT floor, 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance
                FROM rooms WHERE hostel_name = ?
                GROUP BY floor ORDER BY floor";
$stmt = $conn->prepare($floor_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$floors = $stmt->get_result();
$bed_query = "SELECT 
                SUM(CASE 
                    WHEN sharing_type = '2-sharing' THEN 2
                    WHEN sharing_type = '3-sharing' THEN 3
                    WHEN sharing_type = '4-sharing' THEN 4
                    ELSE 0 END) as total_beds,
                SUM(available_beds) as available_beds,
                SUM(CASE 
                    WHEN sharing_type = '2-sharing' THEN 2
                    WHEN sharing_type = '3-sharing' THEN 3
                    WHEN sharing_type = '4-sharing' THEN 4
                    ELSE 0 END) - SUM(available_beds) as occupied_beds
                FROM rooms
                WHERE hostel_name = ?";
$stmt = $conn->prepare($bed_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$beds = $stmt->get_result()->fetch_assoc();
$room_type_query = "SELECT 
                    CASE 
                        WHEN is_ac = 1 THEN CONCAT('AC ', sharing_type) 
                        ELSE CONCAT('Non-AC ', sharing_type) 
                    END as room_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied
                    FROM rooms
                    WHERE hostel_name = ?
                    GROUP BY is_ac, sharing_type";
$stmt = $conn->prepare($room_type_query);
$stmt->bind_param("s", $hostel);
$stmt->execute();
$room_types = $stmt->get_result();
$student_details = array();
$room_requested = isset($_GET['room']) ? $_GET['room'] : null;
if ($room_requested) {
    $student_query = "SELECT s.regNo, s.firstName, s.lastName, s.gender, s.contact, s.email, sd.course, sd.year_of_study
                      FROM student_signup s
                      JOIN student_details sd ON s.regNo = sd.reg_no
                      JOIN room_bookings rb ON s.email = rb.user_email
                      WHERE rb.hostel_name = ? AND rb.room_number = ? AND rb.status = 'confirmed'";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("si", $hostel, $room_requested);
    $stmt->execute();
    $student_details = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title><?php echo $page_title; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f6fa; color: #333; line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: #fff; height: 100vh; position: fixed; overflow-y: auto; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-header h3 { font-size: 1.2rem; margin-bottom: 5px; }
        .sidebar-header p { font-size: 0.9rem; opacity: 0.7; }
        .sidebar-menu { padding: 15px 0; }
        .menu-item { padding: 12px 20px; cursor: pointer; display: flex; align-items: center; position: relative; transition: background 0.3s ease; }
        .menu-item:hover { background: rgba(255, 255, 255, 0.1); }
        .menu-item.active { background: #3498db; }
        .menu-item i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { flex: 1; margin-left: 250px; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
        .user-info { display: flex; align-items: center; }
        .user-info img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; }
        .user-info h4 { font-size: 0.9rem; margin-bottom: 2px; }
        .user-info p { font-size: 0.8rem; color: #777; }
        .dashboard-content { margin-top: 20px; }
        .tabs-container { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); overflow: hidden; }
        .tab-nav { display: flex; background: #f5f6fa; border-bottom: 1px solid #eee; }
        .tab-button { padding: 15px 20px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; 
            font-weight: 600; color: #555; position: relative; transition: all 0.3s ease; }
        .tab-button:hover { color: #3498db; }
        .tab-button.active { color: #3498db; border-bottom-color: #3498db; background: white; }
        .tab-content { display: none; padding: 20px; }
        .tab-content.active { display: block; }
        .tab-section { margin-bottom: 30px; }
        .tab-section:last-child { margin-bottom: 0; }
        .tab-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .tab-section-header h3 { font-size: 1.1rem; color: #333; }
        .view-all { color: #3498db; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .view-all:hover { text-decoration: underline; }
        .room-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .room-card { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); padding: 20px; }
        .room-card-title { font-size: 0.9rem; color: #555; font-weight: 600; margin-bottom: 10px; }
        .room-card-value { font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; }
        .room-card-subtitle { font-size: 0.8rem; color: #777; margin-bottom: 15px; }
        .progress-bar { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 5px; }
        .progress-value { height: 100%; background: #3498db; }
        .progress-label { display: flex; justify-content: space-between; font-size: 0.75rem; color: #777; }
        .table-container { overflow-x: auto; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; font-weight: 600; color: #555; font-size: 0.85rem; }
        table tr:last-child td { border-bottom: none; }
        table tr:hover { background-color: #f8f9fa; }
        .mini-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; }
        .mini-badge.available { background-color: #d4edda; color: #155724; }
        .mini-badge.occupied { background-color: #d1ecf1; color: #0c5460; }
        .mini-badge.under { background-color: #fff3cd; color: #856404; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; transition: background 0.3s ease; 
            display: inline-block; text-align: center; text-decoration: none; }
        .btn-sm { padding: 4px 8px; font-size: 0.8rem; }
        .btn-info { background-color: #17a2b8; color: white; }
        .btn-info:hover { opacity: 0.9; }
        select.filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background-color: white; margin-right: 10px; margin-bottom: 15px; font-size: 0.85rem; }
        .filter-controls { margin-bottom: 15px; display: flex; align-items: center; flex-wrap: wrap; }
        .filter-controls h4 { margin-right: 15px; font-size: 1rem; }
        .bed-status { display: flex; gap: 5px; }
        .bed-icon { width: 15px; height: 15px; border-radius: 3px; }
        .bed-icon.occupied { background-color: #17a2b8; }
        .bed-icon.available { background-color: #2ecc71; border: 1px solid #eee; }
        @media (max-width: 1200px) { .room-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 992px) { .sidebar { width: 200px; } .main-content { margin-left: 200px; } }
        @media (max-width: 768px) { .container { flex-direction: column; } .sidebar { width: 100%; height: auto; position: relative; } 
        .main-content { margin-left: 0; } .room-stats-grid { grid-template-columns: 1fr; } .header { flex-direction: column; align-items: flex-start; } .user-info { margin-top: 15px; } }
        @media (max-width: 480px) { .tab-nav { flex-direction: column; } .tab-button { width: 100%; text-align: left; } }
        .modal {display: none;position: fixed;z-index: 1000;left: 0;top: 0;width: 100%;height: 100%;overflow: auto;background-color: rgba(0,0,0,0.5);}
        .modal-content {background-color: #fff;margin: 50px auto;padding: 20px;border-radius: 8px;width: 80%;max-width: 900px;box-shadow: 0 5px 15px rgba(0,0,0,0.3);position: relative;}
        .close-modal {position: absolute;right: 20px;top: 15px;font-size: 1.5rem;font-weight: bold;cursor: pointer;}
        .modal-header {border-bottom: 1px solid #eee;padding-bottom: 15px;margin-bottom: 15px;}
        .student-table th, .student-table td {padding: 10px;text-align: left;border-bottom: 1px solid #eee;}
        .no-students {padding: 20px;text-align: center;color: #777;}
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Hostel Management</h3>
                <p><?php echo $hostel; ?></p>
            </div>
            <div class="sidebar-menu">
                <div class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </div>
                <div class="menu-item active">
                    <i class="fas fa-door-open"></i>
                    <span>Room Management</span>
                </div>
                <div class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Student Management</span>
                </div>
                <div class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Outpass Management</span>
                </div>
                <div class="menu-item">
                    <i class="fas fa-tools"></i>
                    <span>Service Requests</span>
                </div>
                <div class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </div>
                <div class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
        <div class="main-content">
            <div class="header">
                <h2>Room Management</h2>
                <div class="user-info">
                    <img src="user-avatar.png" alt="User Avatar">
                    <div>
                        <h4><?php echo $staffName; ?></h4>
                        <p><?php echo ucfirst($staffPosition); ?></p>
                    </div>
                </div>
            </div>
            <div class="dashboard-content">
                <div id="roomTab" class="tab-content active">
                    <div class="tab-section">
                        <div class="tab-section-header"><h3>Room Statistics</h3></div>
                        <div class="room-stats-grid">
                            <div class="room-card">
                                <div class="room-card-title">Room Occupancy</div>
                                <div class="room-card-value">
                                    <?php echo $rooms['occupied_rooms']; ?> / <?php echo $rooms['total_rooms']; ?>
                                </div>
                                <div class="room-card-subtitle">Occupied Rooms</div>
                                <div class="progress-bar">
                                    <div class="progress-value" style="width: <?php echo $occupancy_percent; ?>%;"></div>
                                </div>
                                <div class="progress-label">
                                    <span><?php echo $occupancy_percent; ?>% Occupied</span>
                                    <span><?php echo $rooms['total_rooms'] - $rooms['occupied_rooms']; ?> Available</span>
                                </div>
                            </div>
                            <div class="room-card">
                                <div class="room-card-title">Bed Status</div>
                                <div class="room-card-value">
                                    <?php echo isset($beds['available_beds']) ? $beds['available_beds'] : 0; ?> / <?php echo isset($beds['total_beds']) ? $beds['total_beds'] : 0; ?>
                                </div>
                                <div class="room-card-subtitle">Available Beds</div>
                                <div class="progress-bar">
                                    <?php 
                                    $bed_percent = (isset($beds['total_beds']) && $beds['total_beds'] > 0) ? 
                                        round((isset($beds['occupied_beds']) ? $beds['occupied_beds'] : 0) / $beds['total_beds'] * 100) : 0;?>
                                    <div class="progress-value" style="width: <?php echo $bed_percent; ?>%;"></div>
                                </div>
                                <div class="progress-label">
                                    <span><?php echo $bed_percent; ?>% Occupied</span>
                                    <span><?php echo isset($beds['occupied_beds']) ? $beds['occupied_beds'] : 0; ?> Occupied</span>
                                </div>
                            </div>
                            <div class="room-card">
                                <div class="room-card-title">Current Bookings</div>
                                <div class="room-card-value">
                                    <?php echo $bookings['confirmed_bookings']; ?> / <?php echo $bookings['total_bookings']; ?>
                                </div>
                                <div class="room-card-subtitle">Confirmed Bookings</div>
                                <div class="progress-bar">
                                    <?php 
                                    $booking_percent = ($bookings['total_bookings'] > 0) ? 
                                        round(($bookings['confirmed_bookings'] / $bookings['total_bookings']) * 100) : 0;
                                    ?>
                                    <div class="progress-value" style="width: <?php echo $booking_percent; ?>%;"></div>
                                </div>
                                <div class="progress-label">
                                    <span><?php echo $booking_percent; ?>% Confirmed</span>
                                    <span><?php echo $bookings['pending_bookings']; ?> Pending</span>
                                </div>
                            </div>
                        </div>
                        <div class="filter-controls"><h4>Floor-wise Room Status</h4></div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Floor</th> <th>Total Rooms</th> <th>Available</th>
                                        <th>Occupied</th> <th>Under Maintenance</th> <th>Occupancy Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $floors->data_seek(0);
                                    while ($floor = $floors->fetch_assoc()): 
                                        $floor_occupancy = ($floor['total'] > 0) ? 
                                            round(($floor['occupied'] / $floor['total']) * 100) : 0;?>
                                    <tr>
                                        <td>Floor <?php echo $floor['floor']; ?></td>
                                        <td><?php echo $floor['total']; ?></td>
                                        <td><?php echo $floor['available']; ?></td>
                                        <td><?php echo $floor['occupied']; ?></td>
                                        <td><?php echo $floor['maintenance']; ?></td>
                                        <td>
                                            <div class="progress-bar" style="width: 100%; margin: 0;">
                                                <div class="progress-value" style="width: <?php echo $floor_occupancy; ?>%;"></div>
                                            </div>
                                            <div style="text-align: center; font-size: 12px;"><?php echo $floor_occupancy; ?>%</div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="filter-controls" style="margin-top: 20px;"><h4>Room Type Distribution</h4></div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr> <th>Room Type</th> <th>Total Rooms</th> <th>Occupied</th> <th>Occupancy Rate</th> </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $room_types->data_seek(0);
                                    while ($type = $room_types->fetch_assoc()): 
                                        $type_occupancy = ($type['count'] > 0) ? 
                                            round(($type['occupied'] / $type['count']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $type['room_type']; ?></td>
                                        <td><?php echo $type['count']; ?></td>
                                        <td><?php echo $type['occupied']; ?></td>
                                        <td>
                                            <div class="progress-bar" style="width: 100%; margin: 0;">
                                                <div class="progress-value" style="width: <?php echo $type_occupancy; ?>%;"></div>
                                            </div>
                                            <div style="text-align: center; font-size: 12px;"><?php echo $type_occupancy; ?>%</div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-section">
                        <div class="tab-section-header">
                            <h3>Detailed Room Information</h3>
                            <a href="room_management.php" class="view-all">Manage Rooms</a>
                        </div>
                        <div class="filter-controls">
                            <select id="floorFilter" class="filter-select">
                                <option value="all">All Floors</option>
                                <?php 
                                $floors->data_seek(0);
                                while ($floor = $floors->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $floor['floor']; ?>">Floor <?php echo $floor['floor']; ?></option>
                                <?php endwhile; ?>
                            </select>
                            <select id="typeFilter" class="filter-select">
                                <option value="all">All Types</option>
                                <?php 
                                $room_types->data_seek(0);
                                while ($type = $room_types->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $type['room_type']; ?>"><?php echo $type['room_type']; ?></option>
                                <?php endwhile; ?>
                            </select>
                            <select id="statusFilter" class="filter-select">
                                <option value="all">All Statuses</option>
                                <option value="Available">Available</option>
                                <option value="Occupied">Occupied</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                            </select>
                        </div>
                        <div class="table-container room-detail-table">
                            <table id="roomDetailTable">
                                <thead>
                                    <tr>
                                        <th>Room Number</th> <th>Floor</th> <th>Type</th>
                                        <th>Capacity</th> <th>Bed Status</th> <th>Status</th> <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $detailed_rooms->data_seek(0);
                                    while ($room = $detailed_rooms->fetch_assoc()): 
                                    ?>
                                    <tr data-floor="<?php echo $room['floor']; ?>" data-type="<?php echo $room['room_type']; ?>" data-status="<?php echo $room['status']; ?>">
                                        <td><?php echo $room['room_number']; ?></td>
                                        <td><?php echo $room['floor']; ?></td>
                                        <td><?php echo $room['room_type']; ?></td>
                                        <td><?php echo $room['capacity']; ?></td>
                                        <td>
                                            <div class="bed-status">
                                                <?php 
                                                for ($i = 0; $i < $room['occupied_beds']; $i++) {
                                                    echo '<div class="bed-icon occupied" title="Occupied"></div>';}
                                                for ($i = 0; $i < $room['available_beds']; $i++) {
                                                    echo '<div class="bed-icon available" title="Available"></div>';}
                                                ?>
                                            </div>
                                            <div style="font-size: 11px; margin-top: 3px;">
                                                <?php echo $room['occupied_beds']; ?> Occupied, <?php echo $room['available_beds']; ?> Available
                                            </div>
                                        </td>
                                        <td>
                                            <span class="mini-badge <?php echo strtolower(str_replace(' ', '-', $room['status'])); ?>">
                                                <?php echo $room['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm view-room" data-room="<?php echo $room['room_number']; ?>">
                                                View
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
            <div id="studentModal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div class="modal-header"><h3>Student Details - Room <span id="modalRoomNumber"></span></h3></div>
                    <div id="studentTableContainer">
                        <table class="student-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Reg. No.</th> <th>Name</th> <th>Gender</th>
                                    <th>Contact</th> <th>Email</th> <th>Course</th> <th>Year</th>
                                </tr>
                            </thead>
                            <tbody id="studentTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        const floorFilter = document.getElementById('floorFilter');
        const typeFilter = document.getElementById('typeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const roomTable = document.getElementById('roomDetailTable');
        const studentModal = document.getElementById('studentModal');
        const modalRoomNumber = document.getElementById('modalRoomNumber');
        const studentTableBody = document.getElementById('studentTableBody');
        const closeModal = document.querySelector('.close-modal');
        function filterRooms() {
            const floor = floorFilter.value;
            const type = typeFilter.value;
            const status = statusFilter.value;
            const rows = roomTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const rowFloor = row.getAttribute('data-floor');
                const rowType = row.getAttribute('data-type');
                const rowStatus = row.getAttribute('data-status');
                const floorMatch = floor === 'all' || rowFloor === floor;
                const typeMatch = type === 'all' || rowType === type;
                const statusMatch = status === 'all' || rowStatus === status;
                if (floorMatch && typeMatch && statusMatch) {row.style.display = '';} 
                else {row.style.display = 'none';}
            }
        }
        closeModal.addEventListener('click', function() {studentModal.style.display = 'none';});
        window.addEventListener('click', function(event) {
            if (event.target === studentModal) {studentModal.style.display = 'none';}
        });
         function getStudentData(roomNumber) {
            modalRoomNumber.textContent = roomNumber;
            studentTableBody.innerHTML = '<tr><td colspan="7" class="no-students">Loading student data...</td></tr>';
            studentModal.style.display = 'block';
            fetch(`get_student_data.php?room=${roomNumber}&hostel=<?php echo $hostel; ?>`)
                .then(response => {
                    if (!response.ok) {throw new Error('Network response was not ok');}
                    return response.json();
                })
                .then(data => {
                    studentTableBody.innerHTML = '';
                    if (data.error) {
                        studentTableBody.innerHTML = `<tr><td colspan="7" class="no-students">Error: ${data.error}</td></tr>`;
                        return;
                    }
                    if (data.length === 0) {
                        studentTableBody.innerHTML = '<tr><td colspan="7" class="no-students">No students assigned to this room</td></tr>';
                    } else {
                        data.forEach(student => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${student.regNo}</td> <td>${student.firstName} ${student.lastName}</td>
                                <td>${student.gender}</td> <td>${student.contact}</td>
                                <td>${student.email}</td> <td>${student.course || 'N/A'}</td>
                                <td>${student.year_of_study || 'N/A'}</td>
                            `;
                            studentTableBody.appendChild(row);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching student data:', error);
                    studentTableBody.innerHTML = '<tr><td colspan="7" class="no-students">Error retrieving student data. Please try again.</td></tr>';
                });
        }
        floorFilter.addEventListener('change', filterRooms);
        typeFilter.addEventListener('change', filterRooms);
        statusFilter.addEventListener('change', filterRooms);
        document.querySelectorAll('.view-room').forEach(button => {
            button.addEventListener('click', function() {
                const roomNumber = this.getAttribute('data-room');
                getStudentData(roomNumber);
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            filterRooms();
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (this.querySelector('span').textContent === 'Logout') { window.location.href = 'logout.php';} 
                    else {
                        menuItems.forEach(i => i.classList.remove('active'));
                        this.classList.add('active');
                        const page = this.querySelector('span').textContent.toLowerCase().replace(' ', '_');
                        if (page !== 'room_management') {window.location.href = `${page}.php`;}
                    }
                });
            });
        });
    </script>
</body>
</html>