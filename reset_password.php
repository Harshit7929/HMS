<?php
session_start();
include('db.php');

// Function to generate a random CAPTCHA
function generateCaptcha() {
    $captcha = chr(rand(65, 90)); // Random uppercase letter
    for ($i = 0; $i < 4; $i++) {
        $captcha .= rand(0, 9); // Add random digits
    }
    return $captcha;
}

// Regenerate CAPTCHA only if the form is NOT submitted
if (!isset($_POST['verifyCaptcha']) && !isset($_POST['resetPassword'])) {
    $_SESSION['captcha'] = generateCaptcha();
}

// Check for CAPTCHA verification form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verifyCaptcha'])) {
    $applicationNo = trim($_POST['applicationNo']);
    $gmail = trim($_POST['gmail']);
    $phone = trim($_POST['phone']);
    $enteredCaptcha = trim($_POST['captcha']);

    // Validate CAPTCHA input
    if ($enteredCaptcha !== $_SESSION['captcha']) {
        $_SESSION['error'] = "Incorrect CAPTCHA. Please try again.";
        header("Location: reset_password.php");
        exit;
    }

    // Validate user details in database
    $checkQuery = "SELECT * FROM student_signup WHERE regNo = ? AND email = ? AND contact = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("sss", $applicationNo, $gmail, $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User details are correct, proceed
        $_SESSION['captcha_verified'] = true;
        $_SESSION['applicationNo'] = $applicationNo;
        $_SESSION['gmail'] = $gmail;
        $_SESSION['phone'] = $phone;
        header("Location: reset_password.php");
        exit;
    } else {
        $_SESSION['error'] = "Application Number, Gmail, or Phone number not found.";
        header("Location: reset_password.php");
        exit;
    }
}

// Handle the password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resetPassword'])) {
    if (!isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
        $_SESSION['error'] = "Please complete CAPTCHA verification first.";
        header("Location: reset_password.php");
        exit;
    }

    $newPassword = trim($_POST['newPassword']);
    $confirmPassword = trim($_POST['confirmPassword']);

    // Validate passwords
    if ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php");
        exit;
    }

    // Hash the new password securely
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $applicationNo = $_SESSION['applicationNo'];

    // Update the password in the database
    $updateQuery = "UPDATE student_signup SET password = ?, password_updated_date = NOW() WHERE regNo = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $hashedPassword, $applicationNo);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Password updated successfully!";
        
        // Clear session variables
        unset($_SESSION['captcha_verified']);
        unset($_SESSION['applicationNo']);
        unset($_SESSION['gmail']);
        unset($_SESSION['phone']);
        
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['error'] = "Error updating password.";
        header("Location: reset_password.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f7fa;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.75);
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 100%;
            margin: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }

        h2, h3 {
            color: #004080;
            font-weight: 700;
            text-align: center;
        }

        h2 {
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            padding-bottom: 10px;
        }

        h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, #007bff, #00bcd4);
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        label i {
            margin-right: 8px;
            color: #000;  /* Changed icon color to black */
            width: 18px;
            text-align: center;
        }

        .form-control {
            height: 50px;
            width: 100%;
            padding: 12px 15px;  /* Removed left padding to eliminate overlap with icons */
            border: 1px solid #ddd;
            border-radius: 6px;
            transition: all 0.3s;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .btn {
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            font-weight: 600;
            width: 100%;
            height: 50px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.3s;
            margin-top: 10px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 8px;
            color: white;  /* Ensure button icon is white */
        }

        .btn:hover {
            background: linear-gradient(to right, #0056b3, #004494);
            box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .alert {
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
            font-weight: 500;
            border-left: 4px solid;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }

        .alert-danger {
            background-color: #fff2f2;
            border-color: #dc3545;
            color: #dc3545;
        }

        .alert-success {
            background-color: #f0fff4;
            border-color: #28a745;
            color: #28a745;
        }

        .text-center {
            text-align: center;
        }

        .mt-2 {
            margin-top: 15px;
        }

        a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
        }

        a i {
            margin-right: 5px;
            color: #007bff;  /* Link icon color */
        }

        a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 0.9rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .back-link i {
            margin-right: 5px;
        }

        .captcha-container {
            background: #f0f3f7;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .captcha-container:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.6) 50%, transparent 60%);
            background-size: 200% 200%;
            animation: shine 3s infinite linear;
        }

        @keyframes shine {
            to {background-position: 200% 200%;}
        }

        @media (max-width: 576px) {
            .container {
                padding: 25px;
                margin: 15px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .form-control, .btn {
                height: 45px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <!-- Add your logo here if you have one -->
            <!-- <img src="logo.png" alt="Logo" class="logo"> -->
        </div>
        <h2>Reset Password</h2>
        <?php
        if (isset($_SESSION['error'])) {
            echo "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i>" . $_SESSION['error'] . "</div>";
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['message'])) {
            echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i>" . $_SESSION['message'] . "</div>";
            unset($_SESSION['message']);
        }
        ?>
        <?php if (!isset($_SESSION['captcha_verified'])): ?>
        <form action="reset_password.php" method="post">
            <div class="form-group">
                <label for="applicationNo"><i class="fas fa-id-card"></i> Application Number</label>
                <input type="text" class="form-control" id="applicationNo" name="applicationNo" required>
            </div>
            <div class="form-group">
                <label for="gmail"><i class="fas fa-envelope"></i> Gmail</label>
                <input type="email" class="form-control" id="gmail" name="gmail" required>
            </div>
            <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" pattern="[0-9]{10}" required>
            </div>
            <div class="form-group">
                <label for="captcha"><i class="fas fa-shield-alt"></i> CAPTCHA</label>
                <div class="captcha-container">
                    <?php echo $_SESSION['captcha']; ?>
                </div>
                <input type="text" class="form-control" id="captcha" name="captcha" required>
            </div>
            <button type="submit" class="btn btn-primary" name="verifyCaptcha">
                <i class="fas fa-check-circle"></i> Verify CAPTCHA
            </button>
        </form>
        <?php else: ?>
        <form action="reset_password.php" method="post">
            <div class="form-group">
                <label for="newPassword"><i class="fas fa-lock"></i> New Password</label>
                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
            </div>
            <div class="form-group">
                <label for="confirmPassword"><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
            </div>
            <button type="submit" class="btn btn-success" name="resetPassword">
                <i class="fas fa-key"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>
        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>