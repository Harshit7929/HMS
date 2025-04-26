<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRM University AP - Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {--primary-color: #00447c;--secondary-color: #e94e1b;--light-color: #f8f9fa;--dark-color: #343a40;--hover-color: #002d5a;}
        * {margin: 0;padding: 0;box-sizing: border-box;font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
        body {background-color: #f5f5f5;min-height: 100vh;display: flex;align-items: center;justify-content: center;padding: 1rem;}
        .container {display: flex;justify-content: center;align-items: center;width: 100%;}
        .main-content {background: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), url('/api/placeholder/1200/800') center/cover no-repeat;
            border-radius: 10px;box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);position: relative;max-width: 900px;width: 100%;padding: 2rem;margin: 0 auto;}
        .details-icon {position: absolute;top: 2rem;right: 2rem;font-size: 2rem;color: var(--primary-color);cursor: pointer;transition: transform 0.3s ease;}
        .details-icon:hover {transform: scale(1.1);color: var(--secondary-color);}
        .portal-card {background-color: white;border-radius: 10px;box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);padding: 1.5rem;text-align: center;height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;display: flex;flex-direction: column;align-items: center;justify-content: center;cursor: pointer;}
        .portal-card:hover {transform: translateY(-5px);box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);}
        .portal-card i {font-size: 2.5rem;color: var(--secondary-color);margin-bottom: 1rem;}
        .portal-card h3 {color: var(--primary-color);margin-bottom: 0.5rem;}
        .portal-card p {color: var(--dark-color);margin-bottom: 1rem;}
        .portal-card .btn {background-color: var(--primary-color);color: white;border: none;transition: background-color 0.3s ease;}
        .portal-card .btn:hover {background-color: var(--hover-color);}
        .welcome-section {text-align: center;margin-bottom: 2rem;}
        .welcome-section h1 {color: var(--primary-color);font-size: 2rem;margin-bottom: 1rem;display: flex;align-items: center;justify-content: center;}
        .welcome-section p {color: var(--dark-color);font-size: 1.1rem;max-width: 700px;margin: 0 auto;}
        .srm-logo {height: 45px;margin-right: 15px;}
        @media (max-width: 768px) {.welcome-section h1 {font-size: 1.8rem;flex-direction: column;text-align: center;}.srm-logo {margin-right: 0;margin-bottom: 10px;}}
        @media (max-width: 576px) {.portal-card {padding: 1.2rem;}.portal-card i {font-size: 2rem;}.row-cols-1 {margin-bottom: 1rem;}}
    </style>
</head>
<body>
    <div class="container">
        <div class="main-content">
            <a href="http://localhost/hostel_info/details/hostel_info.html" title="Hostel Information">
                <i class="fas fa-info-circle details-icon"></i>
            </a>
            <div class="welcome-section">
                <h1>
                    <img src="http://localhost/hostel_info/images/srmlogo.png" alt="SRM Logo" class="srm-logo">
                    Welcome to SRM University AP
                </h1>
                <p>Access your university portal to manage academic records, course registrations, and campus resources.
                    Choose your login option below to continue.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 mb-4">
                    <div class="portal-card" onclick="window.location.href='signup.php'">
                        <i class="fas fa-user-plus"></i>
                        <h3>Student Signup</h3>
                        <p>New students can register for a portal account</p>
                        <a href="signup.php" class="btn">Sign Up</a>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="portal-card" onclick="window.location.href='login.php'">
                        <i class="fas fa-sign-in-alt"></i>
                        <h3>Student Login</h3>
                        <p>Current students can access their portal</p>
                        <a href="login.php" class="btn">Login</a>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="portal-card" onclick="window.location.href='admin/admin_login.php'">
                        <i class="fas fa-user-shield"></i>
                        <h3>Admin Login</h3>
                        <p>Administrative staff can access management tools</p>
                        <a href="admin/admin_login.php" class="btn">Admin Portal</a>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="portal-card" onclick="window.location.href='staff/staff_test_login.php'">
                        <i class="fas fa-user-cog"></i>
                        <h3>Staff Login</h3>
                        <p>Faculty and staff can access their portal</p>
                        <a href="staff/staff_test_login.php" class="btn">Staff Portal</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('SRM AP Portal loaded successfully');
        });
    </script>
</body>
</html>