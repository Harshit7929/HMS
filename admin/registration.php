<?php
include('admin_db.php');
session_start();
$registrationMessage = "";
$messageType = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $regNo = $_POST['regNo'];
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $nationality = $_POST['nationality']; 
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    if ($password !== $confirmPassword) {
        $registrationMessage = "Password and Confirm Password do not match.";
        $messageType = "danger";
    } else {
        $checkQuery = "SELECT * FROM student_signup WHERE regNo = ? OR email = ? OR contact = ?";
        if ($stmt = $conn->prepare($checkQuery)) {
            $stmt->bind_param("sss", $regNo, $email, $contact);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $registrationMessage = "Registration Number, Email, or Contact already registered.";
                $messageType = "danger";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $sql = "INSERT INTO student_signup (regNo, firstName, lastName, dob, gender, nationality, contact, email, password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("sssssssss", $regNo, $firstName, $lastName, $dob, $gender, $nationality, $contact, $email, $hashedPassword);
                    if ($stmt->execute()) {
                        $accountSql = "INSERT INTO account (regNo, balance) VALUES (?, 500000)";
                        $accountStmt = $conn->prepare($accountSql);
                        $accountStmt->bind_param("s", $regNo);
                        $accountStmt->execute();
                        $accountStmt->close();
                        $registrationMessage = "Registration successful!";
                        $messageType = "success";
                    } else {
                        $registrationMessage = "Error registering user.";
                        $messageType = "danger";
                    }
                } else {
                    $registrationMessage = "Database error.";
                    $messageType = "danger";
                }
            }
            $stmt->close(); 
        } else {
            $registrationMessage = "Database error.";
            $messageType = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Management System</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        header { background-color: #007bff; color: white; text-align: left; padding: 20px; font-size: 24px; font-weight: bold; padding-left: 250px; }
        .sidebar { height: 100%; width: 250px; position: fixed; top: 0; left: 0; background-color: #343a40; padding-top: 20px; }
        .sidebar a { display: block; padding: 12px; color: white; text-decoration: none; font-size: 18px; }
        .sidebar a:hover { background-color: #007bff; color: white; }
        .sidebar i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); max-width: 600px; margin: 0 auto; }
        h2 { text-align: center; margin-bottom: 20px; }
        .form-group label { font-weight: bold; }
        .form-control { border-radius: 5px; padding: 10px; font-size: 16px; }
        .btn { background-color: #007bff; color: white; font-weight: bold; width: 100%; padding: 10px; }
        .btn:hover { background-color: #0056b3; }
        .footer { text-align: center; margin-top: 50px; color: #6c757d; }
        .navbar-brand { color: white; font-size: 20px; }
    </style>
</head>
<body>
    <header>Hostel Management System</header>
    <div class="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="update_profile.php"><i class="fas fa-user-edit"></i>Profile</a>
        <a href="access_log.php"><i class="fas fa-user-shield"></i> Admin Access Log</a>
        <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="container mt-5">
            <div class="row">
                <div class="col-md-12">
                    <h2>Student Registration</h2>
                    <?php if (!empty($registrationMessage)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo htmlspecialchars($registrationMessage); ?>
                        </div>
                    <?php endif; ?>
                    <form action="registration.php" method="post">
                        <div class="form-group">
                            <label for="regNo">Registration Number</label>
                            <input type="text" class="form-control" id="regNo" name="regNo" required>
                        </div>
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" required>
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <div>
                                <input type="radio" id="male" name="gender" value="Male" required> <label for="male">Male</label>
                                <input type="radio" id="female" name="gender" value="Female" required> <label for="female">Female</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="nationality">Nationality</label>
                            <input type="text" class="form-control" id="nationality" name="nationality" value="Indian" required>
                        </div>
                        <div class="form-group">
                            <label for="contact">Contact Number</label>
                            <input type="tel" class="form-control" id="contact" name="contact" pattern="[0-9]{10}" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email ID</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                        </div>
                        <button type="submit" class="btn btn-primary" name="submit">Register</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="footer"><p>&copy; 2025 Hostel Management System | All Rights Reserved</p></div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>