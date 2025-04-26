<?php
include('db.php');
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$user = $_SESSION['user'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sqlReg = "SELECT regNo FROM student_signup WHERE email = ?";
    $stmtReg = $conn->prepare($sqlReg);
    $stmtReg->bind_param("s", $user['email']);
    $stmtReg->execute();
    $resultReg = $stmtReg->get_result();
    $userData = $resultReg->fetch_assoc();
    $profile_picture = 'assets/default-avatar.png';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $upload_dir = 'uploads/profile_pictures/';
        if (!is_dir($upload_dir)) {mkdir($upload_dir, 0755, true);}
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $new_filename = $userData['regNo'] . '_profile_pic.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {$profile_picture = $upload_path;}}
    $bookingSql = "SELECT hostel_name, room_number FROM room_bookings 
                  WHERE user_email = ? AND status = 'confirmed' 
                  ORDER BY booking_date DESC LIMIT 1";
    $bookingStmt = $conn->prepare($bookingSql);
    $bookingStmt->bind_param("s", $user['email']);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $hostel = null;
    $room_number = null;
    if ($bookingResult->num_rows > 0) {
        $bookingData = $bookingResult->fetch_assoc();
        $hostel = $bookingData['hostel_name'];
        $room_number = $bookingData['room_number'];
    }
    $checkSql = "SELECT COUNT(*) as count FROM student_details WHERE reg_no = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $userData['regNo']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    if ($checkResult['count'] > 0) {
        $updateSql = "UPDATE student_details SET 
                        emergency_phone = ?, 
                        course = ?, 
                        address = ?, 
                        profile_picture = ?,
                        year_of_study = ?,
                        hostel = ?,
                        room_number = ?
                      WHERE reg_no = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssssss", 
            $_POST['emergency_phone'], 
            $_POST['course'], 
            $_POST['address'], 
            $profile_picture,
            $_POST['year_of_study'],
            $hostel,
            $room_number,
            $userData['regNo']
        );
    } else {
        $insertSql = "INSERT INTO student_details 
                      (reg_no, emergency_phone, course, address, profile_picture, year_of_study, hostel, room_number) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("ssssssss", 
            $userData['regNo'],
            $_POST['emergency_phone'], 
            $_POST['course'], 
            $_POST['address'], 
            $profile_picture,
            $_POST['year_of_study'],
            $hostel,
            $room_number
        );
    }
    if ($stmt->execute()) {$_SESSION['message'] = "Profile updated successfully!";} 
    else {$_SESSION['error'] = "Failed to update profile: " . $stmt->error;}
    header("Location: profile.php");
    exit();
}
$sql = "SELECT su.*, sd.emergency_phone, sd.course, sd.address, sd.profile_picture, 
        sd.year_of_study, sd.hostel, sd.room_number
        FROM student_signup su 
        LEFT JOIN student_details sd ON su.regNo = sd.reg_no 
        WHERE su.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['email']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
if (empty($userData['hostel']) || empty($userData['room_number'])) {
    $bookingSql = "SELECT hostel_name, room_number FROM room_bookings 
                  WHERE user_email = ? AND status = 'confirmed' 
                  ORDER BY booking_date DESC LIMIT 1";
    $bookingStmt = $conn->prepare($bookingSql);
    $bookingStmt->bind_param("s", $user['email']);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    if ($bookingResult->num_rows > 0) {
        $bookingData = $bookingResult->fetch_assoc();
        $userData['hostel'] = $bookingData['hostel_name'];
        $userData['room_number'] = $bookingData['room_number'];
        $updateSql = "UPDATE student_details SET 
                    hostel = ?, 
                    room_number = ? 
                    WHERE reg_no = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sss", 
            $bookingData['hostel_name'],
            $bookingData['room_number'],
            $userData['regNo']
        );
        $updateStmt->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- <link href="css/profile.css" rel="stylesheet"> -->
     <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; }
        header { background-color: #007bff; color: white; text-align: left; padding: 20px; font-size: 24px; font-weight: bold; padding-left: 250px; margin: 0; }
        .sidebar { height: 100%; width: 250px; position: fixed; top: 0; left: 0; background-color: #343a40; padding-top: 20px; z-index: 1; }
        .sidebar a { display: block; padding: 12px; color: white; text-decoration: none; font-size: 18px; transition: background-color 0.3s ease; }
        .sidebar a:hover { background-color: #007bff; color: white; }
        .sidebar a.active { background-color: #007bff; }
        .main-content { margin-left: 250px; padding: 20px; }
        .profile-container { background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin: 20px auto; }
        .profile-header { text-align: center; margin-bottom: 30px; }
        .profile-pic { width: 150px; height: 150px; border-radius: 50%; margin-bottom: 15px; border: 3px solid #007bff; object-fit: cover; }
        .detail-group { margin-bottom: 20px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; }
        .detail-label { font-weight: bold; color: #007bff; margin-bottom: 5px; }
        .footer { text-align: center; margin-top: 50px; color: #6c757d; margin-left: 250px; padding: 20px; background-color: #f8f9fa; }
        .btn-primary { background-color: #007bff; border-color: #007bff; padding: 10px 30px; font-weight: bold; transition: background-color 0.3s ease; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
        .alert { margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .alert-danger { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: relative; } .main-content { margin-left: 0; } header { padding-left: 20px; } .footer { margin-left: 0; } }
     </style>
</head>
<body>
    <header>Hostel Management System</header>
    <div class="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
        <a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a>
        <a href="room_booking.php"><i class="fas fa-bed"></i> Book Room</a>
        <a href="payments.php"><i class="fas fa-money-bill"></i> Payments</a>
        <a href="access-log.php"><i class="fas fa-history"></i> Access Log</a>
        <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
        <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="profile-container">
            <?php 
            if (isset($_SESSION['message'])) {
                echo "<div class='alert alert-success'>" . $_SESSION['message'] . "</div>";
                unset($_SESSION['message']);
            }
            if (isset($_SESSION['error'])) {
                echo "<div class='alert alert-danger'>" . $_SESSION['error'] . "</div>";
                unset($_SESSION['error']);
            }
            ?>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="profile-header">
                    <img src="<?php echo htmlspecialchars($userData['profile_picture'] ?? 'assets/default-avatar.png'); ?>" 
                         alt="Profile Picture" 
                         class="profile-pic" id="profilePreview">
                    <div class="custom-file mt-2">
                        <input type="file" class="custom-file-input" id="profilePicture" name="profile_picture" accept="image/*">
                        <label class="custom-file-label" for="profilePicture">Change Profile Picture</label>
                    </div>
                    <h2><?php echo htmlspecialchars($userData['firstName'] . ' ' . $userData['lastName']); ?></h2>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-group">
                            <div class="detail-label">Registration Number</div>
                            <div><?php echo htmlspecialchars($userData['regNo']); ?></div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Email</div>
                            <div><?php echo htmlspecialchars($userData['email']); ?></div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Phone Number</div>
                            <div><?php echo htmlspecialchars($userData['contact']); ?></div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Gender</div>
                            <div><?php echo htmlspecialchars($userData['gender']); ?></div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Date of Birth</div>
                            <div><?php echo htmlspecialchars($userData['dob']); ?></div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header bg-primary text-white"><i class="fas fa-home"></i> Hostel Accommodation</div>
                            <div class="card-body">
                                <?php if (!empty($userData['hostel']) && !empty($userData['room_number'])): ?>
                                <div class="detail-group">
                                    <div class="detail-label">Hostel</div>
                                    <div><?php echo htmlspecialchars($userData['hostel']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Room Number</div>
                                    <div><?php echo htmlspecialchars($userData['room_number']); ?></div>
                                </div>
                                <?php else: ?>
                                <div class="text-center">
                                    <p class="text-muted">No room currently booked</p>
                                    <a href="room_booking.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-bed"></i> Book a Room
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-group">
                            <div class="detail-label">Emergency Contact</div>
                            <input type="tel" class="form-control" name="emergency_phone" 
                                   value="<?php echo htmlspecialchars($userData['emergency_phone'] ?? ''); ?>" 
                                   pattern="[0-9]{10}">
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Course</div>
                            <select class="form-control" name="course">
                                <option value="">Select Course</option>
                                <option value="Computer Science" <?php echo ($userData['course'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Electrical Engineering" <?php echo ($userData['course'] == 'Electrical Engineering') ? 'selected' : ''; ?>>Electrical Engineering</option>
                                <option value="Mechanical Engineering" <?php echo ($userData['course'] == 'Mechanical Engineering') ? 'selected' : ''; ?>>Mechanical Engineering</option>
                                <option value="Civil Engineering" <?php echo ($userData['course'] == 'Civil Engineering') ? 'selected' : ''; ?>>Civil Engineering</option>
                                <option value="Business Administration" <?php echo ($userData['course'] == 'Business Administration') ? 'selected' : ''; ?>>Business Administration</option>
                            </select>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Year of Study</div>
                            <select class="form-control" name="year_of_study">
                                <option value="">Select Year</option>
                                <option value="1" <?php echo ($userData['year_of_study'] == '1') ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo ($userData['year_of_study'] == '2') ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo ($userData['year_of_study'] == '3') ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo ($userData['year_of_study'] == '4') ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Address</div>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($userData['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Profile</button>
                </div>
            </form>
        </div>
    </div>
    <div class="footer"><p>&copy; 2025 Hostel Management System | All Rights Reserved</p></div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('profilePicture').addEventListener('change', function(event) {
            var reader = new FileReader();
            reader.onload = function() {
                var preview = document.getElementById('profilePreview');
                preview.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        });
    });
    </script>
</body>
</html>