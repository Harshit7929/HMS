<?php
include 'admin_db.php';
session_start();
function preventRoomNumberReset($regno, &$postData) {
    global $conn;
    $sql = "SELECT rb.room_number, rb.hostel_name 
            FROM room_bookings rb 
            JOIN student_signup ss ON rb.user_email = ss.email 
            WHERE ss.regno = ? AND rb.status = 'confirmed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $regno);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $postData['old_room'] = $row['room_number'];
        $postData['old_hostel'] = $row['hostel_name'];
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['exchange_submit'])) { 
        if (isset($_POST['regno']) && !empty($_POST['regno'])) {preventRoomNumberReset($_POST['regno'], $_POST);}
    }
    if (isset($_POST['maintenance_submit'])) {
        $hostel = $_POST['hostel'];
        $floor = $_POST['floor'];
        $room_number = $_POST['room_number'];
        $sql = "SELECT s.firstName, s.lastName, s.regNo, rb.sharing_type, rb.hostel_name, rb.floor, rb.room_number 
                FROM room_bookings rb 
                JOIN student_signup s ON rb.user_email = s.email 
                WHERE rb.hostel_name = ? AND rb.room_number = ? AND rb.status = 'confirmed'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hostel, $room_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $students = array();
            while ($row = $result->fetch_assoc()) {$students[] = $row;}
            $_SESSION['booked_room_students'] = $students;
            $_SESSION['maintenance_error'] = true;
        } else {
            $sql = "UPDATE rooms SET status='Under Maintenance' WHERE hostel_name=? AND floor=? AND room_number=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $hostel, $floor, $room_number);
            $stmt->execute();
            $_SESSION['maintenance_success'] = true;
        }
    }
    if (isset($_POST['make_available_submit'])) {
        $hostel = $_POST['hostel'];
        $floor = $_POST['floor'];
        $room_number = $_POST['room_number'];
        $sql = "UPDATE rooms SET status='Available' WHERE hostel_name=? AND floor=? AND room_number=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $hostel, $floor, $room_number);
        $stmt->execute();
        $_SESSION['available_success'] = true;
    }
    if (isset($_POST['exchange_submit'])) {
        $regno = $_POST['regno'];
        $new_room = $_POST['new_room'];
        $new_hostel = $_POST['new_hostel'];
        $old_room = $_POST['old_room'];
        $old_hostel = $_POST['old_hostel'];
        $conn->begin_transaction();
        try {
            $sql = "UPDATE room_bookings SET room_number=?, hostel_name=? WHERE user_email=(SELECT email FROM student_signup WHERE regno=?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $new_room, $new_hostel, $regno);
            $stmt->execute();
            $sql = "UPDATE rooms SET available_beds = available_beds + 1 WHERE room_number=? AND hostel_name=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $old_room, $old_hostel);
            $stmt->execute();
            $sql = "UPDATE rooms SET available_beds = available_beds - 1 WHERE room_number=? AND hostel_name=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $new_room, $new_hostel);
            $stmt->execute();
            $conn->commit();
            $_SESSION['exchange_success'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['exchange_error'] = true;
        }
    }
}
function getRoomNumbers($conn, $hostel, $floor, $status = '') {
    $sql = "SELECT room_number FROM rooms WHERE hostel_name = ? AND floor = ?";
    if ($status === 'maintenance') {$sql .= " AND status = 'Under Maintenance'";} 
    elseif ($status === 'available') {$sql .= " AND status != 'Under Maintenance'";}
    $sql .= " ORDER BY room_number";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hostel, $floor);
    $stmt->execute();
    $result = $stmt->get_result();
    $rooms = array();
    while ($row = $result->fetch_assoc()) {$rooms[] = $row['room_number'];}
    return $rooms;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_rooms') {
    $hostel = $_GET['hostel'];
    $floor = $_GET['floor'];
    $status = isset($_GET['status']) ? $_GET['status'] : 'available';
    $rooms = getRoomNumbers($conn, $hostel, $floor, $status);
    header('Content-Type: application/json');
    echo json_encode($rooms);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_student_details' && isset($_GET['regno'])) {
    $regno = $_GET['regno'];
    $sql = "SELECT rb.hostel_name, rb.room_number, rb.floor, rb.sharing_type, rb.is_ac 
            FROM room_bookings rb 
            JOIN student_signup ss ON rb.user_email = ss.email 
            WHERE ss.regno = ? AND rb.status = 'confirmed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $regno);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found or no active room booking']);
        exit;
    }
}
if (isset($_GET['action']) && $_GET['action'] == 'get_available_rooms') {
    $sharing = $_GET['sharing'];
    $is_ac = $_GET['ac'] === 'Yes' ? 1 : 0;
    $current_hostel = $_GET['current_hostel'];
    $current_room = $_GET['current_room'];
    $hostels = in_array($current_hostel, ['Narmadha', 'Krishna']) 
        ? ['Narmadha', 'Krishna'] 
        : ['Ganga', 'Vedavathi'];
    $sql = "SELECT hostel_name, room_number, floor, available_beds, is_ac 
            FROM rooms 
            WHERE sharing_type = ? 
            AND is_ac = ? 
            AND status = 'Available' 
            AND available_beds > 0 
            AND hostel_name IN ('" . implode("','", $hostels) . "')
            AND NOT (hostel_name = ? AND room_number = ?)
            ORDER BY hostel_name, floor, room_number";   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siis", $sharing, $is_ac, $current_hostel, $current_room);
    $stmt->execute();
    $result = $stmt->get_result();
    $available_rooms = [];
    while ($row = $result->fetch_assoc()) {$available_rooms[] = $row;}
    header('Content-Type: application/json');
    echo json_encode($available_rooms);
    exit;
} 
?>

<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="css/room_operations.css"> -->
    <title>Room Operations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px 0; position: fixed; height: 100vh; }
        .sidebar h2 { text-align: center; padding: 20px; border-bottom: 1px solid #34495e; margin-bottom: 20px; }
        .sidebar ul { list-style: none; padding: 0 15px; }
        .sidebar ul li { margin: 8px 0; }
        .sidebar ul li a { display: block; padding: 12px 15px; color: white; text-decoration: none; border-radius: 5px; transition: background 0.3s; }
        .sidebar ul li a:hover { background: #34495e; }
        .main-content { flex: 1; margin-left: 250px; padding: 30px; background: #f5f6fa; }
        .tabs { margin-bottom: 30px; border-bottom: 2px solid #ddd; display: flex; gap: 5px; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; color: #666; font-weight: 500; cursor: pointer; }
        .tab-btn.active { color: #3498db; border-bottom-color: #3498db; background-color: #f5f6fa; }
        .tab-content { display: none; padding: 20px 0; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background 0.3s; }
        button:hover { background: #2980b9; }
        .room-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .room-details p { margin: 10px 0; color: #2c3e50; }
        .room-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .room-option { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .room-option p { margin: 5px 0; color: #2c3e50; }
        .room-option button { margin-top: 10px; width: 100%; }
        .room-option.selected { border: 2px solid #3498db; background-color: #f7f9fc; }
        .maintenance-badge { display: inline-block; padding: 4px 8px; background-color: #e74c3c; color: white; border-radius: 4px; font-size: 12px; margin-left: 8px; }
        @media (max-width: 768px) { 
        .container { flex-direction: column; }
        .sidebar { width: 100%; height: auto; position: relative; }
        .main-content { margin-left: 0; padding: 20px; }
        .tabs { flex-direction: column; border-bottom: none; }
        .tab-btn { width: 100%; border: 1px solid #ddd; border-radius: 4px; margin: 0; }
        .tab-btn.active { border-color: #3498db; }
        .room-list { grid-template-columns: 1fr; }}
        .success-message { background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px; display: none; }
        .student-details { margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px; border: 1px solid #ddd; }
        .student-card { background: white; border: 1px solid #eee; border-radius: 4px; padding: 15px; margin-bottom: 10px; }
        .student-card p { margin: 5px 0; color: #666; }
        .student-card strong { color: #333; }
        .maintenance-actions { display: flex; gap: 20px; margin-bottom: 20px; }
        .maintenance-form { display: none; }
        .maintenance-form.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Admin Panel</h2>
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_rooms.php"><i class="fas fa-door-open"></i> Manage Rooms</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="access_log.php"><i class="fas fa-clipboard-list"></i> Access Log</a></li>
                <li><a href="payments.php"><i class="fas fa-wallet"></i> Payments</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1>Room Operations</h1>
            <div class="tabs">
                <button class="tab-btn active" data-tab="maintenance">Room Maintenance</button>
                <button class="tab-btn" data-tab="exchange">Room Exchange</button>
                <button class="tab-btn" data-tab="bookings">Open Bookings</button>
            </div>
            <div id="maintenance" class="tab-content active">
                <section class="maintenance-section">
                    <h2>Room Maintenance</h2>
                    <div id="maintenanceSuccess" class="success-message"></div>
                    <div id="availableSuccess" class="success-message"></div>
                    <div class="maintenance-actions">
                        <button onclick="showMaintenanceForm('set-maintenance')" class="action-btn">Set Under Maintenance</button>
                        <button onclick="showMaintenanceForm('make-available')" class="action-btn">Make Room Available</button>
                    </div>
                    <form method="POST" action="" id="setMaintenanceForm" class="maintenance-form">
                        <h3>Set Room Under Maintenance</h3>
                        <div class="form-group">
                            <label for="hostel">Hostel:</label>
                            <select name="hostel" id="hostel" required onchange="loadRoomNumbers('available')">
                                <option value="">Select Hostel</option>
                                <option value="Narmadha">Narmadha (Girls)</option>
                                <option value="Krishna">Krishna (Girls)</option>
                                <option value="Ganga">Ganga (Boys)</option>
                                <option value="Vedavathi">Vedavathi (Boys)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="floor">Floor:</label>
                            <select name="floor" id="floor" required onchange="loadRoomNumbers('available')">
                                <option value="">Select Floor</option>
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>">Floor <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="room_number">Room Number:</label>
                            <select name="room_number" id="room_number" required>
                                <option value="">Select Room</option>
                            </select>
                        </div>
                        <button type="submit" name="maintenance_submit">Set Under Maintenance</button>
                    </form>
                    <form method="POST" action="" id="makeAvailableForm" class="maintenance-form">
                        <h3>Make Room Available</h3>
                        <div class="form-group">
                            <label for="hostel_available">Hostel:</label>
                            <select name="hostel" id="hostel_available" required onchange="loadRoomNumbers('maintenance')">
                                <option value="">Select Hostel</option>
                                <option value="Narmadha">Narmadha (Girls)</option>
                                <option value="Krishna">Krishna (Girls)</option>
                                <option value="Ganga">Ganga (Boys)</option>
                                <option value="Vedavathi">Vedavathi (Boys)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="floor_available">Floor:</label>
                            <select name="floor" id="floor_available" required onchange="loadRoomNumbers('maintenance')">
                                <option value="">Select Floor</option>
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>">Floor <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="room_number_available">Room Number:</label>
                            <select name="room_number" id="room_number_available" required>
                                <option value="">Select Room</option>
                            </select>
                        </div>
                        <button type="submit" name="make_available_submit">Make Room Available</button>
                    </form>
                    <div id="maintenanceStudentDetails" class="student-details" style="display: none;">
                        <h3>Current Room Occupants</h3>
                        <div id="maintenanceStudentList"></div>
                    </div>
                </section>
            </div>
            <div id="exchange" class="tab-content">
                <section class="exchange-section">
                    <h2>Room Exchange</h2>
                    <form method="POST" action="" id="exchangeForm">
                        <div class="form-group">
                            <label for="regno">Student Registration Number:</label>
                            <input type="text" name="regno" id="regno" required>
                            <button type="button" onclick="fetchStudentDetails()">Fetch Details</button>
                        </div>
                        <div id="studentDetails" style="display: none;">
                            <h3>Current Room Details</h3>
                            <div class="room-details">
                                <p>Hostel: <span id="currentHostel"></span></p>
                                <p>Room Number: <span id="currentRoom"></span></p>
                                <p>Floor: <span id="currentFloor"></span></p>
                                <p>Sharing Type: <span id="sharingType"></span></p>
                                <p>AC: <span id="isAC"></span></p>
                            </div>
                            <h3>Available Rooms for Exchange</h3>
                            <div id="availableRooms" class="room-list">
                            </div>
                            <input type="hidden" name="old_room" id="old_room">
                            <input type="hidden" name="old_hostel" id="old_hostel">
                            <input type="hidden" name="new_room" id="new_room">
                            <input type="hidden" name="new_hostel" id="new_hostel">
                            <button type="submit" name="exchange_submit" id="exchangeSubmit" style="display: none;">Confirm Room Exchange</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });
        function showMaintenanceForm(formType) {
            document.querySelectorAll('.maintenance-form').forEach(form => {form.classList.remove('active');});
            if (formType === 'set-maintenance') {document.getElementById('setMaintenanceForm').classList.add('active');} 
            else if (formType === 'make-available') {document.getElementById('makeAvailableForm').classList.add('active');}
        }
        function loadRoomNumbers(status) {
            const isAvailableForm = status === 'available';
            const hostel = document.getElementById(isAvailableForm ? 'hostel' : 'hostel_available').value;
            const floor = document.getElementById(isAvailableForm ? 'floor' : 'floor_available').value;
            if (!hostel || !floor) return;
            fetch(`?action=get_rooms&hostel=${hostel}&floor=${floor}&status=${status}`)
                .then(response => response.json())
                .then(rooms => {
                    const roomSelect = document.getElementById(isAvailableForm ? 'room_number' : 'room_number_available');
                    roomSelect.innerHTML = '<option value="">Select Room</option>';   
                    rooms.forEach(roomNumber => {
                        const option = document.createElement('option');
                        option.value = roomNumber;
                        option.textContent = `Room ${roomNumber}`;
                        roomSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading room numbers');
                });
        }
        function fetchStudentDetails() {
            const regno = document.getElementById('regno').value;
            if (!regno) return;
            fetch(`?action=get_student_details&regno=${regno}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    document.getElementById('studentDetails').style.display = 'block';
                    document.getElementById('currentHostel').textContent = data.hostel_name;
                    document.getElementById('currentRoom').textContent = data.room_number;
                    document.getElementById('currentFloor').textContent = data.floor;
                    document.getElementById('sharingType').textContent = data.sharing_type;
                    document.getElementById('isAC').textContent = data.is_ac ? 'Yes' : 'No';

                    document.getElementById('old_room').value = data.room_number;
                    document.getElementById('old_hostel').value = data.hostel_name;

                    fetchAvailableRooms(data.sharing_type, data.is_ac ? 'Yes' : 'No', data.hostel_name, data.room_number);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching student details');
                });
        }
        function fetchAvailableRooms(sharing, ac, currentHostel, currentRoom) {
            fetch(`?action=get_available_rooms&sharing=${sharing}&ac=${ac}&current_hostel=${currentHostel}&current_room=${currentRoom}`)
                .then(response => response.json())
                .then(rooms => {
                    const container = document.getElementById('availableRooms');
                    container.innerHTML = '';
                    rooms.forEach(room => {
                        const roomElement = document.createElement('div');
                        roomElement.className = 'room-option';
                        roomElement.innerHTML = `
                            <p><strong>Hostel:</strong> ${room.hostel_name}</p>
                            <p><strong>Room:</strong> ${room.room_number}</p>
                            <p><strong>Floor:</strong> ${room.floor}</p>
                            <p><strong>Available Beds:</strong> ${room.available_beds}</p>
                            <p><strong>AC:</strong> ${room.is_ac ? 'Yes' : 'No'}</p>
                            <button type="button" onclick="selectRoom('${room.hostel_name}', ${room.room_number})">
                                Select Room
                            </button>
                        `;
                        container.appendChild(roomElement);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching available rooms');
                });
        }
        function selectRoom(hostelName, roomNumber) {
            document.querySelectorAll('.room-option').forEach(option => {option.classList.remove('selected');});
            const selectedRoom = Array.from(document.querySelectorAll('.room-option')).find(option => 
                option.textContent.includes(hostelName) && option.textContent.includes(`Room: ${roomNumber}`));
            if (selectedRoom) {selectedRoom.classList.add('selected');}
            document.getElementById('new_room').value = roomNumber;
            document.getElementById('new_hostel').value = hostelName;
            document.getElementById('exchangeSubmit').style.display = 'block';
        }
        function displayMaintenanceStudentDetails(students) {
            const studentList = document.getElementById('maintenanceStudentList');
            studentList.innerHTML = '';
            students.forEach(student => {
                const studentCard = document.createElement('div');
                studentCard.className = 'student-card';
                studentCard.innerHTML = `
                    <p><strong>Name:</strong> ${student.firstName} ${student.lastName}</p>
                    <p><strong>Registration Number:</strong> ${student.regNo}</p>
                    <p><strong>Room Number:</strong> ${student.room_number}</p>
                    <p><strong>Hostel:</strong> ${student.hostel_name}</p>
                    <p><strong>Floor:</strong> ${student.floor}</p>
                    <p><strong>Sharing Type:</strong> ${student.sharing_type}</p>
                `;
                studentList.appendChild(studentCard);
            });
            document.getElementById('maintenanceStudentDetails').style.display = 'block';
        }
        document.addEventListener('DOMContentLoaded', () => {
            showMaintenanceForm('set-maintenance');
            <?php if (isset($_SESSION['maintenance_success'])): ?>
                document.getElementById('maintenanceSuccess').style.display = 'block';
                document.getElementById('maintenanceSuccess').textContent = 'Room has been successfully set under maintenance.';
                <?php unset($_SESSION['maintenance_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['available_success'])): ?>
                document.getElementById('availableSuccess').style.display = 'block';
                document.getElementById('availableSuccess').textContent = 'Room has been successfully made available.';
                <?php unset($_SESSION['available_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['maintenance_error']) && isset($_SESSION['booked_room_students'])): ?>
                displayMaintenanceStudentDetails(<?php echo json_encode($_SESSION['booked_room_students']); ?>);
                <?php 
                unset($_SESSION['maintenance_error']);
                unset($_SESSION['booked_room_students']);
                ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['exchange_success'])): ?>
                alert('Room exchange completed successfully!');
                <?php unset($_SESSION['exchange_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['exchange_error'])): ?>
                alert('Error: Room exchange failed.');
                <?php unset($_SESSION['exchange_error']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>