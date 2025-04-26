<?php
include('db.php');
session_start();
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
        $_SESSION['error'] = "Password and Confirm Password do not match.";
        header("Location: signup.php");
        exit;
    }
    $checkQuery = "SELECT * FROM student_signup WHERE regNo = ? OR email = ? OR contact = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("sss", $regNo, $email, $contact);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Registration Number, Email, or Contact already registered.";
        $stmt->close();
        header("Location: signup.php");
        exit;
    }
    $stmt->close();
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO student_signup (regNo, firstName, lastName, dob, gender, nationality, contact, email, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssssssss", $regNo, $firstName, $lastName, $dob, $gender, $nationality, $contact, $email, $hashedPassword);
        if ($stmt->execute()) {
            $_SESSION['gender'] = $gender;
            $accountSql = "INSERT INTO account (regNo, balance) VALUES (?, 500000)";
            $accountStmt = $conn->prepare($accountSql);
            $accountStmt->bind_param("s", $regNo);
            $accountStmt->execute();
            $accountStmt->close();
            $_SESSION['message'] = "Registration successful!";
            $stmt->close();
            header("Location: login.php");
            exit;
        } else {$_SESSION['error'] = "Error registering user.";}
        $stmt->close();
    } else {$_SESSION['error'] = "Database error.";}
    header("Location: signup.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        body {font-family: 'Roboto', sans-serif;margin: 0;padding: 0;min-height: 100vh;background-color: #f5f7fa;
            display: flex;align-items: center;justify-content: center;padding: 30px 0;}
        .container {background-color: rgba(255, 255, 255, 0.75);padding: 30px 40px;border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);max-width: 600px;width: 100%;margin: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;}
        .container:hover {transform: translateY(-5px);box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);}
        .logo-container {text-align: center;margin-bottom: 25px;}
        h2, h3 {color: #004080;font-weight: 700;text-align: center;}
        h2 {margin-bottom: 30px;text-transform: uppercase;letter-spacing: 1px;position: relative;padding-bottom: 10px;}
        h2:after {content: '';position: absolute;bottom: 0;left: 50%;transform: translateX(-50%);
            width: 80px;height: 3px;background: linear-gradient(to right, #007bff, #00bcd4);}
        .form-group {margin-bottom: 25px;position: relative;}
        label {font-weight: 500;color: #333;margin-bottom: 8px;display: flex;align-items: center;}
        label i {color: #000;margin-right: 8px;font-size: 16px;width: 20px;text-align: center;}
        .form-control {height: 50px;width: 100%;padding: 12px 15px;border: 1px solid #ddd;border-radius: 6px;
            transition: all 0.3s;box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);box-sizing: border-box;}
        .form-control:focus {border-color: #007bff;box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);outline: none;}
        .btn {background: linear-gradient(to right, #007bff, #0056b3);color: white;font-weight: 600;width: 100%;
            height: 50px;border-radius: 6px;text-transform: uppercase;letter-spacing: 1px;box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            transition: all 0.3s;margin-top: 10px;border: none;cursor: pointer/;}
        .btn:hover {background: linear-gradient(to right, #0056b3, #004494);
            box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);transform: translateY(-2px);}
        .btn:active {transform: translateY(1px);}
        .alert {border-radius: 6px;padding: 15px;margin-bottom: 25px;font-weight: 500;border-left: 4px solid;}
        .alert-danger {background-color: #fff2f2;border-color: #dc3545;color: #dc3545;}
        .alert-success {background-color: #f0fff4;border-color: #28a745;color: #28a745;}
        .back-link {text-align: center;margin-top: 25px;color: #666;font-size: 0.9rem;}
        .text-center {text-align: center;}
        .mt-2 {margin-top: 15px;}
        a {color: #007bff;text-decoration: none;font-weight: 500;transition: color 0.3s;}
        a:hover {color: #0056b3;text-decoration: underline;}
        .row {display: flex;flex-wrap: wrap;margin-right: -10px;margin-left: -10px;}
        .col-6 {flex: 0 0 50%;max-width: 50%;padding: 0 10px;box-sizing: border-box;}
        .gender-options {display: flex;gap: 20px;padding-top: 10px;}
        .gender-option {display: flex;align-items: center;}
        .gender-option input {margin-right: 5px;}
        .button-group {display: flex;flex-direction: column;gap: 15px;margin-top: 20px;}
        .btn-secondary {background: linear-gradient(to right, #6c757d, #495057);text-align: center;display: flex;
            align-items: center;justify-content: center;text-decoration: none;}
        .btn-secondary:hover {background: linear-gradient(to right, #5a6268, #343a40);text-decoration: none;}
        .btn-secondary i {color: #e0e0e0;margin-right: 8px;}
        .btn-primary {background: linear-gradient(to right, #007bff, #0056b3);}
        .btn-primary i {color: white;margin-right: 8px;}
        .logo {max-width: 150px;margin-bottom: 10px;}
        @media (max-width: 768px) {.container {padding: 25px;margin: 15px;max-width: 90%;}h2 {font-size: 1.5rem;}
            .form-control,.btn {height: 45px;}}
        @media (max-width: 576px) {.col-6 {flex: 0 0 100%;max-width: 100%;}}
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="http://localhost/hostel_info/images/srm.png" alt="SRM Logo" class="logo">
        </div>
        <h2>Student Registration</h2>
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
        <form method="POST" action="signup.php">
            <div class="row">
                <div class="form-group col-6">
                    <label for="regNo"><i class="fas fa-id-card"></i> Registration Number</label>
                    <input type="text" class="form-control" id="regNo" name="regNo" required>
                </div>
                <div class="form-group col-6">
                    <label for="dob"><i class="fas fa-calendar-alt"></i> Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" required>
                </div>
                <div class="form-group col-6">
                    <label for="firstName"><i class="fas fa-user"></i> First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                </div>
                <div class="form-group col-6">
                    <label for="lastName"><i class="fas fa-user"></i> Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" required>
                </div>
                <div class="form-group col-6">
                    <label><i class="fas fa-venus-mars"></i> Gender</label>
                    <div class="gender-options">
                        <div class="gender-option">
                            <input type="radio" id="male" name="gender" value="Male" required>
                            <label for="male">Male</label>
                        </div>
                        <div class="gender-option">
                            <input type="radio" id="female" name="gender" value="Female" required>
                            <label for="female">Female</label>
                        </div>
                    </div>
                </div>
                <div class="form-group col-6">
                    <label for="nationality"><i class="fas fa-globe"></i> Nationality</label>
                    <input type="text" class="form-control" id="nationality" name="nationality" value="Indian" required>
                </div>
                <div class="form-group col-6">
                    <label for="contact"><i class="fas fa-phone"></i> Contact Number</label>
                    <input type="tel" class="form-control" id="contact" name="contact" pattern="[0-9]{10}" required>
                </div>
                <div class="form-group col-6">
                    <label for="email"><i class="fas fa-envelope"></i> Email ID</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group col-6">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group col-6">
                    <label for="confirmPassword"><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                </div>
            </div>
            <div class="button-group">
                <button type="submit" name="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</button>
            </div>
            <div class="back-link"><a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a></div>
        </form>
    </div>
</body>
</html>