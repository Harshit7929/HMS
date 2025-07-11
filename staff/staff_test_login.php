<?php
session_start();
include 'staff_db.php';
$error = '';
$login_identifier = '';
$password = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier = $_POST['login_identifier'];
    $password = $_POST['password'];
    if (filter_var($login_identifier, FILTER_VALIDATE_EMAIL)) {$sql = "SELECT * FROM staff WHERE email = ?";}
    else {$sql = "SELECT * FROM staff WHERE staff_id = ?";}
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $login_identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $staff = $result->fetch_assoc();
        if ($password == $staff['password']) {
            $_SESSION['staff_id'] = $staff['staff_id'];
            $_SESSION['email'] = $staff['email'];
            $_SESSION['name'] = $staff['name'];
            $_SESSION['position'] = $staff['position'];
            $_SESSION['department'] = $staff['department'];
            $_SESSION['hostel'] = $staff['hostel'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $position = $staff['position'];
            $hostel = $staff['hostel'];
            $staff_email = $staff['email'];
            $staff_id = $staff['staff_id'];
            $log_sql = "INSERT INTO staff_login (staff_email, staff_id, position, hostel, ip_address, login_status) VALUES (?, ?, ?, ?, ?, 'success')";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("sssss", $staff_email, $staff_id, $position, $hostel, $ip_address);
            $log_stmt->execute();
            switch($staff['position']) {
                case 'Warden':
                    header("Location: warden_test_dashboard.php");
                    break;
                case 'Laundry':
                    header("Location: laundry_staff.php");
                    break;
                default:
                    header("Location: room_service_dashboard.php");
                    break;
            }
            exit();
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $position = $staff['position'];
            $hostel = $staff['hostel'];
            $staff_email = $staff['email'];
            $staff_id = $staff['staff_id'];
            $log_sql = "INSERT INTO staff_login (staff_email, staff_id, position, hostel, ip_address, login_status) VALUES (?, ?, ?, ?, ?, 'failed')";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("sssss", $staff_email, $staff_id, $position, $hostel, $ip_address);
            $log_stmt->execute();
            $error = "Invalid password!";
        }
    } else {$error = "User not found!";}
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {font-family: 'Roboto', sans-serif;margin: 0;padding: 0;height: 100vh;overflow: hidden;}
        .bg-image {position: fixed;top: 0;left: 0;width: 100%;height: 100%;z-index: -1;object-fit: cover;filter: brightness(0.8);}
        .login-wrapper {position: relative;min-height: 100vh; display: flex;align-items: center;
            justify-content: center;background-color: rgba(0, 0, 0, 0.06);}
        .container {background-color: rgba(255, 255, 255, 0.9);padding: 30px 40px;border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);max-width: 700px;backdrop-filter: blur(10px);
            margin: 20px;transition: transform 0.3s ease, box-shadow 0.3s ease;}
        .container:hover {transform: translateY(-5px);box-shadow: 0 12px 25px rgba(0, 0, 0, 0.4);}
        .logo-container {text-align: center;margin-bottom: 25px;}
        .logo {max-width: 150px;margin-bottom: 5px;text-align: center;}
        h2, h3 {color: #004080;font-weight: 700;text-align: center;}
        h2 {margin-bottom: 30px;text-transform: uppercase;letter-spacing: 1px;position: relative;padding-bottom: 10px;}
        h2:after {content: '';position: absolute;bottom: 0;left: 50%;transform: translateX(-50%);width: 80px;
            height: 3px;background: linear-gradient(to right, #007bff, #00bcd4);}
        .form-group {margin-bottom: 25px;position: relative;}
        label {font-weight: 500;color: #333;margin-bottom: 8px;display: block;}
        input[type="text"], 
        input[type="password"] {height: 40px;width: 100%;padding: 12px 70px;border: 1px solid #ddd;border-radius: 6px;
            transition: all 0.3s;box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);box-sizing: border-box;}
        input[type="text"]:focus, 
        input[type="password"]:focus {border-color: #007bff;box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);outline: none;}
        .btn-login {background: linear-gradient(to right, #007bff, #0056b3);color: white;font-weight: 600;width: 100%;
            height: 50px;border-radius: 6px;text-transform: uppercase;letter-spacing: 1px;box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.3s;margin-top: 10px;border: none;cursor: pointer;}
        .btn-login:hover {background: linear-gradient(to right, #0056b3, #004494);box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);
            transform: translateY(-2px);}
        .btn-login:active {transform: translateY(1px);}
        .error-msg {border-radius: 6px;padding: 15px;margin-bottom: 25px;
            font-weight: 500;border-left: 4px solid #dc3545;background-color: #fff2f2;color: #dc3545;}
        .back-link {text-align: center;margin-top: 25px;color: #666;font-size: 0.9rem;}
        .back-link a {color: #007bff;text-decoration: none;font-weight: 500;transition: color 0.3s;}
        @media (max-width: 576px) {
            .container {padding: 25px;margin: 15px;}
            h2 {font-size: 1.5rem;}
            input[type="text"],
            input[type="password"],
            .btn-login {height: 45px;}}
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- <img src="assets/images/campus.jpg" alt="Campus Background" class="bg-image"> -->
        <div class="container">
            <div class="logo-container">
                <img src="http://localhost/hostel_info/images/srm.png" alt="SRM Logo" class="logo">
            </div>
            <!-- <div class="logo-container">
                <h3>Hostel Management System</h3>
            </div> -->
            <h2>Staff Login</h2>
            <?php if($error != ''): ?>
            <div class="error-msg"> 
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="login_identifier">
                        <i class="fas fa-envelope" style="margin-right: 8px; margin-left: 8px;"></i> Email or Staff ID
                    </label>
                    <input type="text" id="login_identifier" name="login_identifier" value="<?php echo htmlspecialchars($login_identifier); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock" style="margin-right: 8px; margin-left: 8px;"></i> Password
                    </label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">Login</button>
                <div class="back-link">
                    <!--<i class="fas fa-arrow-left" style="margin-right: 8px;"></i>--><a href="http://localhost/hostel_info/index.php">← Back to Home</a> 
                </div>
            </form>
        </div>
    </div>
</body>
</html>