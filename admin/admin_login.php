<?php
session_start();
include('admin_db.php');
$message = '';
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}
function getBrowserInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $browser = 'Unknown';
    $version = 'Unknown'; 
    $device = 'Desktop';
    if (preg_match('/Edg\/([0-9\.]+)/', $userAgent, $matches)) {
        $browser = 'Edge';
        $version = $matches[1];
    } elseif (preg_match('/Edge\/([0-9\.]+)/', $userAgent, $matches)) {
        $browser = 'Edge';
        $version = $matches[1];
    } elseif (preg_match('/Chrome\/([0-9\.]+)/', $userAgent, $matches)) {
        $browser = 'Chrome';
        $version = $matches[1];
    } elseif (preg_match('/Firefox\/([0-9\.]+)/', $userAgent, $matches)) {
        $browser = 'Firefox';
        $version = $matches[1];
    } elseif (preg_match('/OPR\/([0-9\.]+)/', $userAgent, $matches)) {
        $browser = 'Opera';
        $version = $matches[1];
    } elseif (preg_match('/Safari\/([0-9\.]+)/', $userAgent, $matches)) {
        if (preg_match('/Version\/([0-9\.]+)/', $userAgent, $vmatches)) {
            $browser = 'Safari';
            $version = $vmatches[1];
        }
    }
    if (preg_match('/(Mobile|Android|iPhone|iPad|Windows Phone)/i', $userAgent)) {
        if (preg_match('/(tablet|ipad)/i', $userAgent)) {
            $device = 'Tablet';
        } else {$device = 'Mobile';}
    }
    return ['browser' => $browser,'version' => $version,'device' => $device];
}
$sessionID = session_id();
$ipAddress = getClientIP();
$browserInfo = getBrowserInfo();
$browser = $browserInfo['browser'];
$browserVersion = $browserInfo['version'];
$deviceType = $browserInfo['device'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        $username_or_email = mysqli_real_escape_string($conn, $_POST['username']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);
        $hashedPassword = hash('sha256', $password);
        $admin_query = "SELECT id, username, email FROM admin WHERE (username = ? OR email = ?) AND password = ?";
        if ($stmt = $conn->prepare($admin_query)) {
            $stmt->bind_param("sss", $username_or_email, $username_or_email, $hashedPassword);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $admin = $result->fetch_assoc();
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $log_query = "INSERT INTO admin_log (admin_id, email, ip_address, browser, browser_version, 
                             device_type, session_id, login_status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'Success')";
                if ($log_stmt = $conn->prepare($log_query)) {
                    $log_stmt->bind_param("issssss", 
                        $admin['id'],
                        $admin['email'],
                        $ipAddress,
                        $browser,
                        $browserVersion,
                        $deviceType,
                        $sessionID
                    );
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $check_query = "SELECT id, email FROM admin WHERE username = ? OR email = ?";
                if ($check_stmt = $conn->prepare($check_query)) {
                    $check_stmt->bind_param("ss", $username_or_email, $username_or_email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($admin_data = $check_result->fetch_assoc()) {
                        $failureReason = "Invalid credentials";
                        $log_query = "INSERT INTO admin_log (admin_id, email, ip_address, browser, 
                                    browser_version, device_type, session_id, login_status, failure_reason) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Failure', ?)";
                        if ($log_stmt = $conn->prepare($log_query)) {
                            $log_stmt->bind_param("isssssss",
                                $admin_data['id'],
                                $admin_data['email'],
                                $ipAddress,
                                $browser,
                                $browserVersion,
                                $deviceType,
                                $sessionID,
                                $failureReason
                            );
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    }
                    $check_stmt->close();
                }
                $message = "<div class='alert alert-danger'>Invalid username or password.</div>";
            }
            $stmt->close();
        } else {$message = "<div class='alert alert-danger'>Database error: Unable to process request.</div>";}
    } else {$message = "<div class='alert alert-danger'>Please fill in both fields.</div>";}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRM Admin Login</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {font-family: 'Roboto', sans-serif;margin: 0;padding: 0;height: 100vh;overflow: hidden;}
        .bg-image {position: fixed;top: 0;left: 0;width: 100%;height: 100%;z-index: -1;object-fit: cover;filter: brightness(0.8);}
        .login-wrapper {position: relative;min-height: 100vh;display: flex;align-items: center;
            justify-content: center;background-color: rgba(0, 0, 0, 0.05);}
        .container {background-color: rgba(255, 255, 255, 0.9);padding: 30px 40px;border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);max-width: 450px;backdrop-filter: blur(10px);
            margin: 20px;transition: transform 0.3s ease, box-shadow 0.3s ease;}
        .container:hover {transform: translateY(-5px);box-shadow: 0 12px 25px rgba(0, 0, 0, 0.4);}
        .logo-container {text-align: center;margin-bottom: 25px;}
        .logo {max-width: 150px;margin-bottom: 10px;}
        h2 {margin-bottom: 30px;color: #004080;font-weight: 700;text-align: center;text-transform: uppercase;
            letter-spacing: 1px;position: relative;padding-bottom: 10px;}
        h2:after {content: '';position: absolute;bottom: 0;left: 50%;transform: translateX(-50%);width: 80px;
            height: 3px;background: linear-gradient(to right, #007bff, #00bcd4);}
        .form-group {margin-bottom: 25px;position: relative;}
        label {font-weight: 500;color: #333;margin-bottom: 8px;display: block;}
        .form-control {height: 50px;padding: 12px 15px;border: 1px solid #ddd;border-radius: 6px;
            transition: all 0.3s;box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);}
        .form-control:focus {border-color: #007bff;box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);}
        .btn {background: linear-gradient(to right, #007bff, #0056b3);color: white;font-weight: 600;width: 100%;
            height: 50px;border-radius: 6px;text-transform: uppercase;letter-spacing: 1px;box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.3s;margin-top: 10px;border: none;}
        .btn:hover {background: linear-gradient(to right, #0056b3, #004494);
            box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);transform: translateY(-2px);}
        .btn:active {transform: translateY(1px);}
        .alert {border-radius: 6px;padding: 15px;margin-bottom: 25px;font-weight: 500;border-left: 4px solid;}
        .alert-danger {background-color: #fff2f2;border-color: #dc3545;color: #dc3545;}
        .footer-text {text-align: center;margin-top: 25px;color: #666;font-size: 0.9rem;}
        @media (max-width: 576px) {.container {padding: 25px;margin: 15px;}h2 {font-size: 1.5rem;}.form-control {height: 45px;}}
        .back-link {text-align: center;margin-top: 25px;color: #666;font-size: 0.9rem;}
        .back-link a {color: #007bff;text-decoration: none;font-weight: 500;transition: color 0.3s;}
    </style>
</head>
<body>
    <!-- <img src="http://localhost/hostel_info/images/s-block.jpg" alt="Background" class="bg-image"> -->
    <div class="login-wrapper">
        <div class="container">
            <div class="logo-container">
                <img src="http://localhost/hostel_info/images/srm.png" alt="SRM Logo" class="logo">
            </div>
            <h2>Admin Login</h2>
            <?php if ($message): ?>
                <?= $message ?>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user" style="margin-right: 8px; margin-left: 10px;"></i> Username or Email
                    </label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username or email" required>
                </div>
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock" style="margin-right: 8px; margin-left: 10px;"></i> Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
                <div class="back-link">
                    <a href="http://localhost/hostel_info/index.php">← Back to Home</a>
                </div>
            </form>
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> SRM Administration Portal. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>