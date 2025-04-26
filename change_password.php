<?php
include('db.php');
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $oldPassword = $_POST['old_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Get current user's data
    $stmt = $conn->prepare("SELECT password FROM student_signup WHERE regNo = ?");
    $stmt->bind_param("s", $_SESSION['user']['regNo']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // First verify if old password matches
        if (!password_verify($oldPassword, $user['password']) && $oldPassword !== $user['password']) {
            $error = "Current password is incorrect.";
        }
        // Then verify if new passwords match
        else if ($newPassword !== $confirmPassword) {
            $error = "New password and confirm password do not match.";
        }
        // Check if new password is same as old password
        else if (password_verify($newPassword, $user['password']) || $newPassword === $user['password']) {
            $error = "New password must be different from current password.";
        }
        else {
            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            // Update password in database
            $updateStmt = $conn->prepare("UPDATE student_signup SET password = ?, password_updated_date = CURRENT_TIMESTAMP WHERE regNo = ?");
            $updateStmt->bind_param("ss", $hashedPassword, $_SESSION['user']['regNo']);
            
            if ($updateStmt->execute()) {
                $message = "Password successfully updated!";
                // Update session password
                $_SESSION['user']['password'] = $hashedPassword;
            } else {
                $error = "Error updating password. Please try again.";
            }
            $updateStmt->close();
        }
    } else {
        $error = "User not found.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Hostel Management System</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="css/change_password.css" rel="stylesheet">
    <style>
        /* Global Styles */
:root {
  --primary-color: #3498db;
  --primary-dark: #2980b9;
  --success-color: #2ecc71;
  --danger-color: #e74c3c;
  --text-dark: #2c3e50;
  --text-light: #ecf0f1;
  --bg-light: #f8f9fa;
  --bg-dark: #343a40;
  --border-radius: 6px;
  --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s ease;
}

body {
  font-family: 'Segoe UI', Arial, sans-serif;
  background-color: var(--bg-light);
  margin: 0;
  padding: 0;
  color: var(--text-dark);
  line-height: 1.6;
}

/* Header */
header {
  background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
  color: var(--text-light);
  text-align: left;
  padding: 20px;
  font-size: 24px;
  font-weight: bold;
  padding-left: 250px;
  position: fixed;
  width: 100%;
  z-index: 100;
  top: 0;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

/* Sidebar */
.sidebar {
  height: 100%;
  width: 250px;
  position: fixed;
  top: 0;
  left: 0;
  background-color: var(--bg-dark);
  padding-top: 80px;
  z-index: 99;
  box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  transition: var(--transition);
}

.sidebar a {
  display: flex;
  align-items: center;
  padding: 15px 20px;
  color: var(--text-light);
  text-decoration: none;
  font-size: 16px;
  transition: var(--transition);
  border-left: 4px solid transparent;
}

.sidebar a:hover, 
.sidebar a.active {
  background-color: rgba(255, 255, 255, 0.1);
  border-left-color: var(--primary-color);
  color: var(--primary-color);
}

.sidebar a.active {
  background-color: rgba(52, 152, 219, 0.15);
  font-weight: 500;
}

.sidebar i {
  margin-right: 10px;
  width: 20px;
  text-align: center;
  font-size: 18px;
}

/* Main Content */
.main-content {
  margin-left: 250px;
  padding: 100px 30px 30px;
  min-height: calc(100vh - 60px);
  transition: var(--transition);
}

.container {
  background-color: #ffffff;
  padding: 35px;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  max-width: 600px;
  margin: 0 auto;
  transition: var(--transition);
}

h2 {
  color: var(--text-dark);
  text-align: center;
  margin-bottom: 30px;
  font-weight: 600;
  position: relative;
  padding-bottom: 10px;
}

h2:after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 3px;
  background-color: var(--primary-color);
  border-radius: 3px;
}

/* Form Styles */
.form-group {
  margin-bottom: 25px;
  position: relative;
}

.form-group label {
  font-weight: 500;
  color: var(--text-dark);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  transition: var(--transition);
}

.form-group label i {
  margin-right: 8px;
  color: #999;
}

.form-control {
  border: 2px solid #e9ecef;
  border-radius: var(--border-radius);
  padding: 14px;
  font-size: 16px;
  transition: var(--transition);
  width: 100%;
  box-sizing: border-box;
}

.form-control:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
  outline: none;
}

.form-control:hover {
  border-color: #ced4da;
}

/* Password Field with Toggle */
.password-container {
  position: relative;
}

.password-toggle {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  color: #6c757d;
  z-index: 10;
}

/* Button Styles */
.btn-primary {
  background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
  border: none;
  padding: 14px 30px;
  font-weight: 600;
  letter-spacing: 0.5px;
  border-radius: var(--border-radius);
  transition: var(--transition);
  width: 100%;
  color: var(--text-light);
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.btn-primary:hover {
  background: linear-gradient(to right, var(--primary-dark), var(--primary-color));
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
}

.btn-primary:active {
  transform: translateY(1px);
  box-shadow: 0 2px 5px rgba(0, 123, 255, 0.3);
}

.btn-primary:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.4);
}

/* Alert Styles */
.alert {
  border-radius: var(--border-radius);
  padding: 15px;
  margin-bottom: 25px;
  border: none;
  position: relative;
  padding-left: 45px;
  animation: fadeIn 0.5s ease-in-out;
}

.alert:before {
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
}

.alert-success {
  background-color: rgba(46, 204, 113, 0.15);
  color: var(--success-color);
  border-left: 4px solid var(--success-color);
}

.alert-success:before {
  content: '\f00c';
  color: var(--success-color);
}

.alert-danger {
  background-color: rgba(231, 76, 60, 0.15);
  color: var(--danger-color);
  border-left: 4px solid var(--danger-color);
}

.alert-danger:before {
  content: '\f071';
  color: var(--danger-color);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Footer */
.footer {
  text-align: center;
  padding: 20px;
  background-color: #f8f9fa;
  color: #6c757d;
  position: relative;
  margin-top: 40px;
  margin-left: 250px;
  border-top: 1px solid #e9ecef;
  transition: var(--transition);
}

/* Password Strength Indicator */
.password-strength {
  height: 5px;
  border-radius: 3px;
  margin-top: 8px;
  transition: var(--transition);
  background-color: #e9ecef;
}

.password-strength-label {
  font-size: 12px;
  font-weight: 500;
  margin-top: 5px;
  text-align: right;
}

/* Responsive Styles */
@media (max-width: 992px) {
  .container {
    max-width: 90%;
    padding: 25px;
  }
}

@media (max-width: 768px) {
  header {
    padding-left: 200px;
    font-size: 20px;
  }
  
  .sidebar {
    width: 200px;
  }
  
  .sidebar a {
    padding: 12px 15px;
    font-size: 14px;
  }
  
  .main-content {
    margin-left: 200px;
    padding: 90px 20px 20px;
  }
  
  .footer {
    margin-left: 200px;
  }
}

@media (max-width: 576px) {
  header {
    padding-left: 0;
    text-align: center;
  }
  
  .sidebar {
    width: 0;
    overflow: hidden;
  }
  
  .sidebar.active {
    width: 200px;
  }
  
  .main-content {
    margin-left: 0;
    padding: 80px 15px 15px;
  }
  
  .container {
    padding: 20px;
  }
  
  .footer {
    margin-left: 0;
  }
  
  .toggle-sidebar {
    display: block;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 101;
    cursor: pointer;
    color: white;
    font-size: 20px;
  }
  
  header.sidebar-active {
    padding-left: 200px;
  }
  
  .main-content.sidebar-active,
  .footer.sidebar-active {
    margin-left: 200px;
  }
}

/* Accessibility */
:focus {
  outline: 3px solid rgba(52, 152, 219, 0.4);
  outline-offset: 2px;
}

/* Additional UI Improvements */
.password-requirements {
  background-color: #f8f9fa;
  border-radius: var(--border-radius);
  padding: 10px 15px;
  margin-top: 10px;
  margin-bottom: 15px;
  font-size: 14px;
}

.requirement {
  margin: 5px 0;
  display: flex;
  align-items: center;
}

.requirement i {
  margin-right: 8px;
  font-size: 13px;
}

.requirement.valid i {
  color: var(--success-color);
}

.requirement.invalid i {
  color: var(--danger-color);
}

/* Animations */
@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.btn-primary:focus {
  animation: pulse 1s infinite;
}

/* Dark Mode Toggle (optional feature) */
.dark-mode-toggle {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background-color: var(--bg-dark);
  color: var(--text-light);
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: var(--box-shadow);
  z-index: 999;
  transition: var(--transition);
}

.dark-mode-toggle:hover {
  transform: rotate(30deg);
}

/* Toast notification for actions */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 15px 25px;
  background-color: var(--bg-dark);
  color: var(--text-light);
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  z-index: 1000;
  transform: translateX(150%);
  transition: transform 0.3s ease-out;
}

.toast.show {
  transform: translateX(0);
}

/* Progress indicator for password change */
.progress-indicator {
  height: 3px;
  width: 100%;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1001;
  background: linear-gradient(to right, var(--primary-color), var(--success-color));
  transform: scaleX(0);
  transform-origin: 0% 50%;
  transition: transform 0.5s ease-out;
}

.progress-indicator.active {
  transform: scaleX(1);
}
    </style>
</head>
<body>
    <header>
        Hostel Management System
    </header>
    
    <div class="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
        <a href="profile.php"><i class="fas fa-user"></i>My Profile</a>
        <a href="room_booking.php"><i class="fas fa-bed"></i>Book Room</a>
        <a href="payment_history.php"><i class="fas fa-money-bill-wave"></i>Payments</a>
        <a href="access_log.php"><i class="fas fa-history"></i>Access Log</a>
        <a href="change_password.php" class="active"><i class="fas fa-key"></i>Change Password</a>
        <a href="login.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>

    <div class="main-content">
        <div class="container">
            <h2>Change Password</h2>
             
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="change_password.php">
                <div class="form-group">
                    <label for="old_password"><i class="fas fa-lock"></i> Current Password</label>
                    <input type="password" class="form-control" id="old_password" name="old_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 Hostel Management System | All Rights Reserved</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>