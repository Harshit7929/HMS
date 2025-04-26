<?php
include('db.php');
session_start();
function logLoginTime($conn, $student_email, $user_id, $status = 'success') {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO login_details (student_email, user_id, ip_address, login_status) VALUES (?, ?, ?, ?)";
    if ($stmt = $conn->prepare($log_sql)) {
        $stmt->bind_param("ssss", $student_email, $user_id, $ip_address, $status);
        if (!$stmt->execute()) {error_log("Error logging login time: " . $stmt->error);}
        $stmt->close();
    } else {error_log("Error preparing statement: " . $conn->error);}
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emailOrRegNo = trim($_POST['emailOrRegNo']); 
    $password = $_POST['password'];
    $sql = "SELECT * FROM student_signup WHERE email = ? OR regNo = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $emailOrRegNo, $emailOrRegNo);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                logLoginTime($conn, $user['email'], $user['regNo'], 'success');
                $_SESSION['user'] = $user;
                header("Location: dashboard.php");
                exit;
            } elseif ($password === $user['password']) {
                logLoginTime($conn, $user['email'], $user['regNo'], 'success');
                $_SESSION['user'] = $user;
                header("Location: dashboard.php");
                exit;
            } else {
                logLoginTime($conn, $user['email'], $user['regNo'], 'failed');
                $_SESSION['error'] = "Invalid password.";
            }
        } else {$_SESSION['error'] = "Invalid email or registration number.";}
        $stmt->close();
    } else {$_SESSION['error'] = "Database error: Unable to prepare statement.";}
}
?>
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body {font-family: 'Roboto', sans-serif;margin: 0;padding: 0;height: 100vh;display: flex;
            align-items: center;justify-content: center;background-color: #f5f7fa;}
        .container {background-color: rgba(255, 255, 255, 0.75);padding: 30px 40px;border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);max-width: 450px;width: 100%;margin: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;}
        .container:hover {transform: translateY(-5px);box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);}
        .logo-container {text-align: center;margin-bottom: 25px;}
        h2, h3 {color: #004080;font-weight: 700;text-align: center;}
        h2 {margin-bottom: 30px;text-transform: uppercase;letter-spacing: 1px;position: relative;padding-bottom: 10px;}
        h2:after {content: '';position: absolute;bottom: 0;left: 50%;transform: translateX(-50%);
            width: 80px;   height: 3px;background: linear-gradient(to right, #007bff, #00bcd4);}
        .form-group {margin-bottom: 25px;position: relative;}
        label {font-weight: 500;color: #333;margin-bottom: 8px;display: block;}
        .form-control {height: 50px;width: 100%;padding: 12px 15px;border: 1px solid #ddd;border-radius: 6px;
            transition: all 0.3s;box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);box-sizing: border-box;}
        .form-control:focus {border-color: #007bff;box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);outline: none;}
        .btn {background: linear-gradient(to right, #007bff, #0056b3);color: white;font-weight: 600;width: 100%;
            height: 50px;border-radius: 6px;text-transform: uppercase;letter-spacing: 1px;box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.3s;margin-top: 10px;border: none;cursor: pointer;}
        .btn:hover {background: linear-gradient(to right, #0056b3, #004494);box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);
            transform: translateY(-2px);}
        .btn:active {transform: translateY(1px);}
        .alert {border-radius: 6px;padding: 15px;margin-bottom: 25px;font-weight: 500;border-left: 4px solid;}
        .alert-danger {background-color: #fff2f2;border-color: #dc3545;color: #dc3545;}
        .alert-success {background-color: #f0fff4;border-color: #28a745;color: #28a745;}
        .text-center {text-align: center;}
        .mt-2 {margin-top: 15px;}
        a {color: #007bff;text-decoration: none;font-weight: 500;transition: color 0.3s;}
        a:hover {color: #0056b3;text-decoration: underline;}
        .back-link {text-align: center;margin-top: 25px;color: #666;font-size: 0.9rem;}
        @media (max-width: 576px) {.container {padding: 25px;margin: 15px;}h2 {font-size: 1.5rem;}.form-control,.btn {height: 45px;}}
        .logo-container {text-align: center;margin-bottom: 25px;}
        .logo {max-width: 150px;margin-bottom: 10px;}
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="http://localhost/hostel_info/images/srm.png" alt="SRM Logo" class="logo">
        </div>
        <!-- <div class="logo-container">
            <h3>Hostel Management System</h3>
        </div> -->
        <h2>Student Login</h2> 
        <?php  
        if (isset($_SESSION['error'])) {
            echo "<div class='alert alert-danger'>" . $_SESSION['error'] . "</div>";
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['message'])) {
            echo "<div class='alert alert-success'>" . $_SESSION['message'] . "</div>";
            unset($_SESSION['message']);
        }
        ?>
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="emailOrRegNo">
                    <i class="fas fa-envelope" style="margin-right: 8px; margin-left: 8px;"></i> Email or Registration Number
                </label>
                <input type="text" class="form-control" id="emailOrRegNo" name="emailOrRegNo" required>
            </div>
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock" style="margin-right: 8px; margin-left: 8px;"></i> Password
                </label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
            <div class="text-center mt-2">
                <!-- <i class="fas fa-key" style="margin-right: 8px;"></i>--><a href="reset_password.php">Forgot Password?</a>
            </div>
            <div class="back-link">
                <!-- <i class="fas fa-arrow-left" style="margin-right: 8px;"></i>--><a href="index.php">← Back to Home</a> 
            </div>
        </form>
    </div>
</body>
</html>