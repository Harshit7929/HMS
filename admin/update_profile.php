<?php
session_start();
include('admin_db.php'); // Database connection

// Check if admin is logged in
if (!isset($_SESSION['admin_user'])) {
    header("Location: admin_login.php");
    exit();
}

// Fetch admin details
$admin_user = $_SESSION['admin_user'];
$query = "SELECT * FROM admin WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admin_user);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Handle Profile Update
if (isset($_POST['update_profile'])) {
    $new_username = mysqli_real_escape_string($conn, $_POST['username']);
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);

    $update_query = "UPDATE admin SET username=?, email=? WHERE username=?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sss", $new_username, $new_email, $admin_user);
    
    if ($stmt->execute()) {
        $_SESSION['admin_user'] = $new_username;
        echo "<script>alert('Profile updated successfully!'); window.location.href='admin_profile.php';</script>";
    } else {
        echo "<script>alert('Error updating profile. Try again.');</script>";
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $old_password = hash('sha256', $_POST['old_password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if old password is correct
    if ($old_password != $admin['password']) {
        echo "<script>alert('Incorrect old password!');</script>";
    } elseif ($new_password !== $confirm_password) {
        echo "<script>alert('New passwords do not match!');</script>";
    } else {
        $hashed_new_password = hash('sha256', $new_password);
        $update_pass_query = "UPDATE admin SET password=? WHERE username=?";
        $stmt = $conn->prepare($update_pass_query);
        $stmt->bind_param("ss", $hashed_new_password, $admin_user);

        if ($stmt->execute()) {
            echo "<script>alert('Password changed successfully!'); window.location.href='admin_profile.php';</script>";
        } else {
            echo "<script>alert('Error updating password. Try again.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #f5f7fa;
            padding-top: 30px;
            padding-bottom: 30px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        h3, h4 {
            color: #004080;
            font-weight: 700;
            text-align: center;
        }

        h3 {
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            padding-bottom: 10px;
        }

        h3:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, #007bff, #00bcd4);
        }

        .profile-box {
            background-color: rgba(255, 255, 255, 0.75);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .profile-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
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
            color: #000;
            width: 18px;
            text-align: center;
        }

        .form-control {
            height: 50px;
            width: 100%;
            padding: 12px 15px;
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
            color: white;
        }

        .btn:hover {
            background: linear-gradient(to right, #0056b3, #004494);
            box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(1px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .profile-box {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h3 class="text-center mb-4">Admin Details</h3>
    
    <div class="row">
        <!-- Profile Details Form -->
        <div class="col-md-6">
            <div class="profile-box">
                <h4>Admin Profile</h4>
                <form action="" method="post">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= $admin['username']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= $admin['email']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="regDate"><i class="fas fa-calendar-alt"></i> Registered Date</label>
                        <input type="text" class="form-control" id="regDate" value="<?= $admin['registration_date']; ?>" disabled>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="col-md-6">
            <div class="profile-box">
                <h4>Change Password</h4>
                <form action="" method="post">
                    <div class="form-group">
                        <label for="old_password"><i class="fas fa-key"></i> Old Password</label>
                        <input type="password" class="form-control" id="old_password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock-open"></i> Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>