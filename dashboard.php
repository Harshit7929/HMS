<?php 
session_start(); 
if (!isset($_SESSION['user'])) {     
    header("Location: login.php");     
    exit; 
} 
$user = $_SESSION['user']; 

// Include database connection
include('db.php');

// Get user profile data including profile picture
$sql = "SELECT sd.profile_picture FROM student_details sd 
        WHERE sd.reg_no = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['regNo']);
$stmt->execute();
$result = $stmt->get_result();
$profileData = $result->fetch_assoc();

// Set profile picture path or default
$profilePicture = !empty($profileData['profile_picture']) ? 
                  $profileData['profile_picture'] : 
                  'http://localhost/hostel_info/images/default_profile_picture.jpeg';
?>
<!doctype html>
<html lang="en" class="no-js">
<head> 
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="theme-color" content="#3e454c">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        :root {--primary-color: #1a237e;--secondary-color: #0d47a1;--background-color: #f5f6fa;}
        .room-management .card-icon { color: #2196F3; }
        .room-management .card-button { background-color: #2196F3; }
        .mess-management .card-icon { color: #4CAF50; }
        .mess-management .card-button { background-color: #4CAF50; }
        .attendance-leave .card-icon { color: #9C27B0; }
        .attendance-leave .card-button { background-color: #9C27B0; }
        .payments-bills .card-icon { color: #F44336; }
        .payments-bills .card-button { background-color: #F44336; }
        .complaints-services .card-icon { color: #FF9800; }
        .complaints-services .card-button { background-color: #FF9800; }
        .academic-notices .card-icon { color: #795548; }
        .academic-notices .card-button { background-color: #795548; }
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background-color: var(--background-color);margin: 0;padding: 0;}
        .logo-container {padding: 20px;text-align: center;background-color:#182c44;margin-bottom: 20px;}
        .logo-container img {max-width: 180px;height: auto;}
        header {background: #182c44;color: white;text-align: left;
            padding: 20px;font-size: 24px;font-weight: bold;padding-left: 270px;box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);}
        .sidebar {height: 100%;width: 250px;position: fixed;top: 0;left: 0;background-color:#182c44;padding-top: 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);z-index: 1000;}
        .sidebar a {display: flex;align-items: center;padding: 12px 20px;color: white;text-decoration: none;
            font-size: 15px;border-left: 4px solid transparent;transition: all 0.3s;}
        .sidebar a i {margin-right: 10px;width: 20px;text-align: center;}
        .sidebar a:hover {background-color: #f0f0f0;border-left-color: var(--primary-color);color: var(--primary-color);}
        .main-content {margin-left: 250px;padding: 30px;min-height: calc(100vh - 60px);}
        .dashboard-category {margin-bottom: 30px;}
        .category-title {font-size: 20px;font-weight: 600;margin-bottom: 20px;color: var(--primary-color);
            padding-bottom: 10px;border-bottom: 2px solid var(--primary-color);}
        .card {border-radius: 10px;box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;border: none;background: white;cursor: pointer;}
        .card:hover {transform: translateY(-5px);box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);}
        .card-body {padding: 20px;text-align: center;}
        .card-icon {font-size: 2.5rem;margin-bottom: 15px;transition: transform 0.3s;}
        .card:hover .card-icon {transform: scale(1.1);}
        .card-title {font-size: 1.1rem;font-weight: 600;margin-bottom: 15px;color: #333;}
        .card-link {text-decoration: none;color: inherit;}
        .card-button {color: white;border: none;padding: 8px 16px;border-radius: 5px;
            font-size: 0.9rem;transition: opacity 0.3s;width: 100%;margin-top: 10px;}
        .card-button:hover {opacity: 0.9;color: white;text-decoration: none;}
        .footer {margin-left: 250px;padding: 20px;background-color: white;text-align: center;box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);}
        @media (max-width: 768px) {
            .sidebar {width: 200px;}
            .main-content, .footer {margin-left: 200px;}
            header {padding-left: 220px;}}
        @media (max-width: 576px) {
            .sidebar {width: 100%;height: auto;position: relative;}
            .main-content, .footer {margin-left: 0;}
            header {padding-left: 20px;}}
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-container"><img src="images/srmap.png" alt="SRMAP Logo"></div>
        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
        <a href="access_log.php"><i class="fas fa-history"></i> Access Log</a>
        <!-- <a href="noticeboard.php"><i class="fas fa-bullhorn"></i> Notice Board</a> -->
        <a href="change_password.php"><i class="fas fa-lock"></i> Change Password</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <header style="background: #182c44; color: white; text-align: left; padding: 20px; font-size: 24px; font-weight: bold; padding-left: 270px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: flex; justify-content: space-between; align-items: center;">
        <div>Student Dashboard</div>
        <div style="display: flex; align-items: center; margin-right: 30px;">
            <span id="studentName" style="margin-right: 15px; font-size: 16px;">
                <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?>
            </span>
            <img id="studentProfilePic" src="<?php echo htmlspecialchars($profilePicture); ?>" 
                style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
        </div>
    </header>
    <div class="main-content">
        <div class="dashboard-category room-management">
            <h2 class="category-title">Room Management</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-bed card-icon"></i>
                            <h5 class="card-title">Book Room</h5>
                            <a href="room_booking.php" class="btn card-button">Book Now</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-door-open card-icon"></i>
                            <h5 class="card-title">Room Details</h5>
                            <a href="room_details.php" class="btn card-button">View Details</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-broom card-icon"></i>
                            <h5 class="card-title">Room Service</h5>
                            <a href="room_service.php" class="btn card-button">Request Service</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-tools card-icon"></i>
                            <h5 class="card-title">Maintenance</h5>
                            <a href="maintenance.php" class="btn card-button">Report Issue</a>
                        </div>
                    </div>
                </div> -->
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-exchange-alt card-icon"></i>
                            <h5 class="card-title">Room Change</h5>
                            <a href="room_change.php" class="btn card-button">Request Change</a>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
        <div class="dashboard-category mess-management">
            <h2 class="category-title">Mess Management</h2>
            <div class="row">
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-concierge-bell card-icon"></i>
                            <h5 class="card-title">Apply for Mess</h5>
                            <a href="apply_mess.php" class="btn card-button">Apply Now</a>
                        </div>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-calendar-alt card-icon"></i>
                            <h5 class="card-title">Mess Schedule</h5>
                            <a href="mess_schedule.php" class="btn card-button">View Schedule</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-utensils card-icon"></i>
                            <h5 class="card-title">Menu</h5>
                            <a href="mess_menu.php" class="btn card-button">View Menu</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-clock card-icon"></i>
                            <h5 class="card-title">Meal Timing</h5>
                            <a href="meal_timing.php" class="btn card-button">Check Timings</a>
                        </div>
                    </div>
                </div> --> 
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-comment-alt card-icon"></i>
                            <h5 class="card-title">Feedback</h5>
                            <a href="mess_feedback.php" class="btn card-button">Give Feedback</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="dashboard-category attendance-leave">
            <h2 class="category-title">Attendance & Leave</h2>
            <div class="row">
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-clipboard-check card-icon"></i>
                            <h5 class="card-title">Mark Attendance</h5>
                            <a href="attendance.php" class="btn card-button">Mark Now</a>
                        </div>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-calendar-check card-icon"></i>
                            <h5 class="card-title">Attendance History</h5>
                            <a href="attendance_history.php" class="btn card-button">View History</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-door-open card-icon"></i>
                            <h5 class="card-title">Apply Outpass</h5>
                            <a href="apply_outpass.php" class="btn card-button">Apply Now</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-print card-icon"></i>
                            <h5 class="card-title">Print Outpass</h5>
                            <a href="print_outpass.php" class="btn card-button">Print Now</a>
                        </div>
                    </div>
                </div> -->
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-history card-icon"></i>
                            <h5 class="card-title">Leave History</h5>
                            <a href="leave_history.php" class="btn card-button">View History</a>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
        <div class="dashboard-category payments-bills">
            <h2 class="category-title">Payments & Bills</h2>
            <div class="row">
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-file-invoice-dollar card-icon"></i>
                            <h5 class="card-title">Pay Fees</h5>
                            <a href="pay_fees.php" class="btn card-button">Pay Now</a>
                        </div>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-history card-icon"></i>
                            <h5 class="card-title">Payment Details</h5>
                            <a href="payment_history.php" class="btn card-button">View History</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-receipt card-icon"></i>
                            <h5 class="card-title">Generate Receipt</h5>
                            <a href="generate_receipt.php" class="btn card-button">Generate</a>
                        </div>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-wallet card-icon"></i>
                            <h5 class="card-title">Fee Dues</h5>
                            <a href="fee_dues.php" class="btn card-button">Check Dues</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-question-circle card-icon"></i>
                            <h5 class="card-title">Payment Queries</h5>
                            <a href="payment_queries.php" class="btn card-button">View Queries</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-undo-alt card-icon"></i>
                            <h5 class="card-title">Refund Request</h5>
                            <a href="refund.php" class="btn card-button">Request Refund</a>
                        </div>
                    </div> 
                </div> -->
            </div>
        </div>
        <div class="dashboard-category complaints-services">
            <h2 class="category-title">Complaints & Services</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-exclamation-circle card-icon"></i>
                            <h5 class="card-title">File Complaint</h5>
                            <a href="complaints.php" class="btn card-button">File Now</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-tasks card-icon"></i>
                            <h5 class="card-title">Track Complaints</h5>
                            <a href="track_complaints.php" class="btn card-button">Track Status</a>
                        </div>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-tshirt card-icon"></i>
                            <h5 class="card-title">Laundry Service</h5>
                            <a href="laundry.php" class="btn card-button">Request Service</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-shopping-basket card-icon"></i> 
                            <h5 class="card-title">Collect Laundry</h5>
                            <a href="collect_laundry.php" class="btn card-button">Collect Now</a>
                        </div>
                    </div>
                </div> -->
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-history card-icon"></i>
                            <h5 class="card-title">Service History</h5>
                            <a href="service_history.php" class="btn card-button">View History</a>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
        <div class="dashboard-category academic-notices">
            <h2 class="category-title">Academic & Notices</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-graduation-cap card-icon"></i>
                            <h5 class="card-title">Academic Events</h5>
                            <a href="academic_events.php" class="btn card-button">View Events</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-calendar-alt card-icon"></i>
                            <h5 class="card-title">Event Calendar</h5>
                            <a href="event_calendar.php" class="btn card-button">View Calendar</a>
                        </div>
                    </div>
                </div> -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-bullhorn card-icon"></i>
                            <h5 class="card-title">Latest Notices</h5>
                            <a href="noticeboard.php" class="btn card-button">View Notices</a>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <i class="fas fa-bell card-icon"></i>
                            <h5 class="card-title">Notifications</h5>
                            <a href="notifications.php" class="btn card-button">View All</a>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile picture preview functionality
            document.getElementById('profilePicture').addEventListener('change', function(event) {
                var reader = new FileReader();
                reader.onload = function() {
                    var preview = document.getElementById('profilePreview');
                    preview.src = reader.result;
                }
                reader.readAsDataURL(event.target.files[0]);
            });
            
            // Update student info in header
            function updateStudentInfo(studentData) {
                document.getElementById('studentName').textContent = studentData.firstName + ' ' + studentData.lastName;
                
                // Use the profile picture from student_details or default if not available
                const profilePicture = studentData.profile_picture || 'C:/xampp/htdocs/hostel_info/images/default_profile_picture.jpeg';
                document.getElementById('studentProfilePic').src = profilePicture;
            }
        });
    </script>
    <div class="footer"><p>&copy; 2025 SRM Hostel Management System | All Rights Reserved</p></div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>