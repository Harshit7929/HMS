<?php
session_start();
require_once 'db.php';
$getLatestLoginQuery = "SELECT student_email FROM login_details 
                        WHERE login_status = 'success' 
                        ORDER BY login_time DESC LIMIT 1";
$latestLoginResult = $conn->query($getLatestLoginQuery);
if ($latestLoginResult && $latestLoginResult->num_rows > 0) {
    $userEmail = $latestLoginResult->fetch_assoc()['student_email'];
} else {
    $fallbackQuery = "SELECT email FROM student_signup LIMIT 1";
    $fallbackResult = $conn->query($fallbackQuery);
    if ($fallbackResult && $fallbackResult->num_rows > 0) {$userEmail = $fallbackResult->fetch_assoc()['email'];
    } else {die("No user accounts found in the system");}
}
$stmt = $conn->prepare("SELECT * FROM student_signup WHERE email = ?");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();
$bookingError = ''; 
$selectedHostel = '';
$selectedAcType = '';
$selectedSharingType = '';
$selectedStayPeriod = '';
$availableRooms = [];
$maleHostels = ['Ganga', 'Vedavathi'];
$femaleHostels = ['Narmadha', 'Krishna'];
$availableHostels = isset($studentData['gender']) && $studentData['gender'] === 'Male' ? $maleHostels : $femaleHostels;
$checkActiveBookingQuery = "SELECT rb.*, 
                           DATE_ADD(rb.booking_date, INTERVAL rb.stay_period MONTH) as end_date 
                           FROM room_bookings rb 
                           WHERE rb.user_email = ? 
                           AND (rb.status = 'confirmed' OR rb.status = 'pending')
                           AND CURRENT_DATE() < DATE_ADD(rb.booking_date, INTERVAL rb.stay_period MONTH)
                           ORDER BY rb.booking_date DESC LIMIT 1";
$checkActiveStmt = $conn->prepare($checkActiveBookingQuery);
$checkActiveStmt->bind_param("s", $userEmail);
$checkActiveStmt->execute();
$activeBookingResult = $checkActiveStmt->get_result();
$hasActiveBooking = $activeBookingResult->num_rows > 0;
$activeBooking = null;
if ($hasActiveBooking) {$activeBooking = $activeBookingResult->fetch_assoc();}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_rooms'])) {
        if ($hasActiveBooking) {
            $endDate = new DateTime($activeBooking['end_date']);
            $today = new DateTime();
            $bookingError = "You already have an active booking until " . $endDate->format('d-m-Y') . ". You can book again after this date.";
        } else {
            $selectedHostel = $_POST['hostel'];
            $selectedAcType = $_POST['ac_preference'];
            $selectedSharingType = $_POST['sharing_type'];
            $selectedStayPeriod = $_POST['stay_period'];
            if (empty($selectedHostel) || empty($selectedAcType) || empty($selectedSharingType) || empty($selectedStayPeriod)) {
                $bookingError = "Please fill all fields to find available rooms.";
            } else {
                $query = "SELECT * FROM rooms 
                         WHERE hostel_name = ? 
                         AND is_ac = ? 
                         AND sharing_type = ? 
                         AND available_beds > 0
                         AND status != 'Under Maintenance'";
                $stmt = $conn->prepare($query);
                $isAc = $selectedAcType === 'ac' ? 1 : 0;
                $stmt->bind_param("sis", $selectedHostel, $isAc, $selectedSharingType);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {$availableRooms[] = $row;}
                if (empty($availableRooms)) {$bookingError = "No available rooms found matching your criteria. Please try different options.";}
            }
        }
    }
    if (isset($_POST['select_room'])) {
        if ($hasActiveBooking) {
            $endDate = new DateTime($activeBooking['end_date']);
            $bookingError = "You already have an active booking until " . $endDate->format('d-m-Y') . ". You can book again after this date.";
        } else {
            $roomId = $_POST['room_id'];
            $stayPeriod = $_POST['stay_period'];
            $isAc = $_POST['is_ac'];
            $sharingType = $_POST['sharing_type'];
            $hostelName = $_POST['hostel_name'];
            $checkRoomQuery = "SELECT available_beds FROM rooms WHERE id = ?";
            $checkRoomStmt = $conn->prepare($checkRoomQuery);
            $checkRoomStmt->bind_param("i", $roomId);
            $checkRoomStmt->execute();
            $checkRoomResult = $checkRoomStmt->get_result();
            $roomData = $checkRoomResult->fetch_assoc();
            if ($roomData['available_beds'] <= 0) {$bookingError = "Sorry, this room is no longer available. Please select another room.";} 
            else {
                $feeQuery = "SELECT fee FROM fee_structure 
                             WHERE room_type = ? 
                             AND sharing_type = ? 
                             AND duration = ?";
                $roomType = ($isAc == 1) ? 'AC' : 'Non-AC';
                $feeStmt = $conn->prepare($feeQuery);
                $feeStmt->bind_param("sis", $roomType, $sharingType, $stayPeriod);
                $feeStmt->execute();
                $feeResult = $feeStmt->get_result();
                $feeData = $feeResult->fetch_assoc();
                $roomFee = $feeData['fee'];
                $messFeeQuery = "SELECT fee FROM fee_structure 
                                 WHERE room_type = 'Mess' 
                                 AND duration = ?";
                $messFeeStmt = $conn->prepare($messFeeQuery);
                $messFeeStmt->bind_param("s", $stayPeriod);
                $messFeeStmt->execute();
                $messFeeResult = $messFeeStmt->get_result();
                $messFeeData = $messFeeResult->fetch_assoc();
                $messFee = $messFeeData['fee'];
                $totalFee = $roomFee + $messFee;
                $_SESSION['selected_room'] = [
                    'room_id' => $roomId,
                    'stay_period' => $stayPeriod,
                    'total_fee' => $totalFee,
                    'room_type' => $roomType,
                    'sharing_type' => $sharingType,
                    'hostel_name' => $hostelName
                ];
                $roomQuery = "SELECT * FROM rooms WHERE id = ?";
                $roomStmt = $conn->prepare($roomQuery);
                $roomStmt->bind_param("i", $roomId);
                $roomStmt->execute();
                $roomResult = $roomStmt->get_result();
                $roomDetails = $roomResult->fetch_assoc();
                $_SESSION['selected_room']['room_number'] = $roomDetails['room_number'];
                $_SESSION['selected_room']['floor'] = $roomDetails['floor'];
                $insertBookingQuery = "INSERT INTO room_bookings (user_email, hostel_name, room_number, floor, is_ac, sharing_type, stay_period, total_fee, status, booking_date) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_DATE())";
                $insertBookingStmt = $conn->prepare($insertBookingQuery);
                $insertBookingStmt->bind_param("ssiisssd", $userEmail, $hostelName, $roomDetails['room_number'], $roomDetails['floor'], $isAc, $sharingType, $stayPeriod, $totalFee);
                $insertBookingStmt->execute();
                $selectedBookingId = $conn->insert_id;
                $_SESSION['selected_room']['booking_id'] = $selectedBookingId;
                header("Location: payment.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Room Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/room_booking.css"> -->
    <style>
        :root {--primary: #2c3e50;--secondary: #3498db;--success: #2ecc71;--danger: #e74c3c;--light: #f8f9fa;--dark: #343a40;}
        * {margin: 0;padding: 0;box-sizing: border-box;font-family: "Arial", sans-serif;}
        body { background-color: var(--light);}
        .wrapper {display: flex;min-height: 100vh;}
        .sidebar {width: 250px;background-color: var(--primary);padding: 20px 0;color: white;}
        .nav-item {padding: 15px 25px;cursor: pointer;transition: background-color 0.3s;}
        .nav-item:hover {background-color: rgba(255, 255, 255, 0.1);}
        .nav-item.active {background-color: var(--secondary);}
        .nav-item i {margin-right: 10px;}
        .main-content {flex: 1;padding: 30px;}
        .booking-container {background: white;border-radius: 8px;padding: 25px;box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);}
        .student-info {background-color: var(--light);padding: 20px;border-radius: 8px;margin-bottom: 30px;}
        .student-info h2 {color: var(--primary);margin-bottom: 15px;}
        .steps {display: flex;justify-content: space-between;margin-bottom: 40px;position: relative;max-width: 600px;margin: 0 auto 40px;}
        .step-item {position: relative;width: 30px;height: 30px;background-color: #ddd;border-radius: 50%;display: flex;
        align-items: center;justify-content: center;color: white;z-index: 2;}
        .step-item.active {background-color: var(--secondary);}
        .step-item.complete {background-color: var(--success);}
        .step-line {position: absolute;top: 15px;left: 50px;right: 50px;height: 2px;background-color: #ddd;z-index: 1;}
        .step-line-progress {height: 100%;background-color: var(--success);width: 0;transition: width 0.3s;}
        .form-group {margin-bottom: 20px;}
        .form-group label {display: block;margin-bottom: 8px;font-weight: 600;color: var(--dark);}
        .form-control {width: 100%;padding: 12px;border: 1px solid #ddd;border-radius: 4px;font-size: 16px;}
        .form-control:focus {outline: none;border-color: var(--secondary);box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);}
        .room-grid {display: grid;grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));gap: 20px;margin-top: 30px;}
        .room-card {background: white;border-radius: 8px;padding: 20px;box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);transition: transform 0.3s, box-shadow 0.3s;}
        .room-card h3 {color: var(--primary);margin-bottom: 15px;}
        .btn {padding: 12px 24px;border: none;border-radius: 4px;cursor: pointer;font-size: 16px;transition: all 0.3s;}
        .btn-primary {background-color: var(--secondary);color: white;}
        .btn-primary:hover {background-color: #2980b9;}
        .alert {padding: 15px;border-radius: 4px;margin-bottom: 20px;}
        .alert-success {background-color: #d4edda;color: #155724;}
        .alert-danger {background-color: #f8d7da;color: #721c24;}
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="nav-item"><i class="fas fa-home"></i>Dashboard</div>
            <div class="nav-item active"><i class="fas fa-bed"></i>Room Booking</div>
            <div class="nav-item"><i class="fas fa-history"></i>My Bookings</div>
            <div class="nav-item"><i class="fas fa-user"></i>Profile</div>
        </div>
        <div class="main-content">
            <div class="booking-container">
                <div class="student-info">
                    <h2>Welcome, <?php echo htmlspecialchars($studentData['firstName'] . ' ' . $studentData['lastName']); ?></h2>
                    <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($studentData['regNo']); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($studentData['gender']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($studentData['email']); ?></p>
                </div>
                <?php if ($hasActiveBooking): ?>
                <div class="active-booking-info">
                    <h3>Your Active Booking</h3>
                    <div class="alert alert-info">
                        <p>You have an active booking in <?php echo htmlspecialchars($activeBooking['hostel_name']); ?>, 
                        Room <?php echo htmlspecialchars($activeBooking['room_number']); ?> 
                        (<?php echo $activeBooking['is_ac'] ? 'AC' : 'Non-AC'; ?>, 
                        <?php echo htmlspecialchars($activeBooking['sharing_type']); ?>).</p>
                        <p>Stay Period: <?php echo htmlspecialchars($activeBooking['stay_period']); ?> months</p>
                        <p>Booking Date: <?php echo (new DateTime($activeBooking['booking_date']))->format('d-m-Y'); ?></p>
                        <p>End Date: <?php echo (new DateTime($activeBooking['end_date']))->format('d-m-Y'); ?></p>
                        <p>You can book a new room after your current booking period ends.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="steps">
                    <div class="step-line">
                        <div class="step-line-progress"></div>
                    </div>
                    <div class="step-item active">1</div>
                    <div class="step-item">2</div>
                    <div class="step-item">3</div>
                    <div class="step-item">4</div>
                </div>
                <?php if ($bookingError): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($bookingError); ?></div>
                <?php endif; ?>
                <form method="post" id="bookingForm">
                    <div class="form-group">
                        <label>Select Hostel:</label>
                        <select name="hostel" class="form-control" required>
                            <option value="">Choose a hostel</option>
                            <?php foreach ($availableHostels as $hostel): ?>
                                <option value="<?php echo htmlspecialchars($hostel); ?>" <?php echo $selectedHostel === $hostel ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hostel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Type:</label>
                        <select name="ac_preference" class="form-control" required>
                            <option value="">Select room type</option>
                            <option value="ac" <?php echo $selectedAcType === 'ac' ? 'selected' : ''; ?>>AC Room</option>
                            <option value="non_ac" <?php echo $selectedAcType === 'non_ac' ? 'selected' : ''; ?>>Non-AC Room</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sharing Type:</label>
                        <select name="sharing_type" class="form-control" required>
                            <option value="">Select sharing type</option>
                            <option value="2-sharing" <?php echo $selectedSharingType === '2-sharing' ? 'selected' : ''; ?>>2 Sharing</option>
                            <option value="3-sharing" <?php echo $selectedSharingType === '3-sharing' ? 'selected' : ''; ?>>3 Sharing</option>
                            <option value="4-sharing" <?php echo $selectedSharingType === '4-sharing' ? 'selected' : ''; ?>>4 Sharing</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stay Period:</label>
                        <select name="stay_period" class="form-control" required>
                            <option value="">Select stay period</option>
                            <option value="6" <?php echo $selectedStayPeriod === '6' ? 'selected' : ''; ?>>6 Months</option>
                            <option value="12" <?php echo $selectedStayPeriod === '12' ? 'selected' : ''; ?>>12 Months (1 Year)</option>
                        </select>
                    </div>
                    <button type="submit" name="find_rooms" class="btn btn-primary">Find Available Rooms</button>
                </form>
                <?php if (!empty($availableRooms)): ?>
                    <div class="room-grid">
                        <?php foreach ($availableRooms as $room): ?>
                            <div class="room-card">
                                <h3>Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                                <p><strong>Floor:</strong> <?php echo htmlspecialchars($room['floor']); ?></p>
                                <p><strong>Available Beds:</strong> <?php echo htmlspecialchars($room['available_beds']); ?></p>
                                <p><strong>Type:</strong> <?php echo $room['is_ac'] ? 'AC' : 'Non-AC'; ?></p>
                                <p><strong>Sharing:</strong> <?php echo htmlspecialchars($room['sharing_type']); ?></p>
                                <form method="post" style="margin-top: 15px;">
                                    <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room['id']); ?>">
                                    <input type="hidden" name="stay_period" value="<?php echo htmlspecialchars($_POST['stay_period']); ?>">
                                    <input type="hidden" name="is_ac" value="<?php echo htmlspecialchars($room['is_ac']); ?>">
                                    <input type="hidden" name="sharing_type" value="<?php echo htmlspecialchars($room['sharing_type']); ?>">
                                    <input type="hidden" name="hostel_name" value="<?php echo htmlspecialchars($selectedHostel); ?>">
                                    <button type="submit" name="select_room" class="btn btn-primary">Select Room</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (isset($_POST['find_rooms']) && empty($bookingError)): ?>
                    <div class="alert alert-danger">No rooms available matching your criteria. Please try different options.</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const form = document.getElementById('bookingForm');
        const progressLine = document.querySelector('.step-line-progress');
        const steps = document.querySelectorAll('.step-item');
        let currentStep = 1;
        if (form) {
            form.addEventListener('change', () => {
                const filledInputs = Array.from(form.elements)
                    .filter(element => element.required)
                    .filter(element => element.value !== '');
                currentStep = Math.min(Math.ceil((filledInputs.length / 4) * 4), 4);
                updateProgress();
            });
            window.addEventListener('load', () => {
                const filledInputs = Array.from(form.elements)
                    .filter(element => element.required)
                    .filter(element => element.value !== '');
                currentStep = Math.min(Math.ceil((filledInputs.length / 4) * 4), 4);
                updateProgress();
            });
        }
        function updateProgress() {
            progressLine.style.width = `${((currentStep - 1) / 3) * 100}%`;
            steps.forEach((step, index) => {
                if (index + 1 === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('complete');
                } else if (index + 1 < currentStep) {
                    step.classList.remove('active');
                    step.classList.add('complete');
                } else {step.classList.remove('active', 'complete');}
            });
        }
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                const allSelects = document.querySelectorAll('select');
                const allFilled = Array.from(allSelects).every(s => s.value !== '');
                const findRoomsBtn = document.querySelector('button[name="find_rooms"]');
                if (findRoomsBtn) {findRoomsBtn.disabled = !allFilled;}
            });
        });
        document.querySelectorAll('.room-card').forEach(card => {
            card.addEventListener('mouseover', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
            });
            card.addEventListener('mouseout', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
            });
        });
    </script>
</body>
</html>