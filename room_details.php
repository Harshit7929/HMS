<?php
include('db.php');
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION['user'];
$email = $user['email'];
$booking_sql = "SELECT rb.*, 
                rb.total_fee as booking_total,
                COALESCE(SUM(pd.amount), 0) as amount_paid
                FROM room_bookings rb
                LEFT JOIN payment_details pd ON rb.id = pd.booking_id AND rb.user_email = pd.user_email
                WHERE rb.user_email = ?
                GROUP BY rb.id
                ORDER BY rb.booking_date DESC";
if ($stmt = $conn->prepare($booking_sql)) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = [];
    $active_bookings = [];
    while ($row = $result->fetch_assoc()) {
        $row['paid_amount'] = isset($row['amount_paid']) ? $row['amount_paid'] : 0;
        $row['due_amount'] = $row['booking_total'] - $row['paid_amount'];
        $row['payment_complete'] = ($row['due_amount'] <= 0);
        if ($row['status'] != 'cancelled') {$active_bookings[] = $row;}
        $bookings[] = $row;
    }
    $stmt->close();
} else {$_SESSION['error'] = "Database error: Unable to fetch booking details.";}
$payment_details = [];
$payment_sql = "SELECT pd.* FROM payment_details pd
                WHERE pd.user_email = ?
                ORDER BY pd.created_at DESC";
if ($stmt = $conn->prepare($payment_sql)) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    while ($row = $payment_result->fetch_assoc()) {
        if (!isset($payment_details[$row['booking_id']])) {$payment_details[$row['booking_id']] = $row;}}
    $stmt->close();
}
$student_sql = "SELECT s.*, sd.* FROM student_signup s LEFT JOIN student_details sd ON s.regNo = sd.reg_no WHERE s.email = ?";
if ($stmt = $conn->prepare($student_sql)) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student_details = $student_result->fetch_assoc();
    $stmt->close();
} else {$_SESSION['error'] = "Database error: Unable to fetch student details.";}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Booking Details</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- <link href="css/room_details.css" rel="stylesheet"> -->
    <style>
        .wrapper {display: flex;width: 100%;}
        .sidebar {width: 250px;background: #343a40;color: #fff;position: fixed;height: 100%;overflow-y: auto;transition: all 0.3s;}
        .sidebar .logo-container {padding: 20px;text-align: center;border-bottom: 1px solid rgba(255, 255, 255, 0.1);}
        .sidebar .logo {max-width: 100px;margin-bottom: 10px;}
        .sidebar .nav-menu {padding: 0;list-style: none;}
        .sidebar .nav-item {position: relative;}
        .sidebar .nav-item a {padding: 15px 20px;display: block;color: #fff;text-decoration: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);transition: all 0.3s;}
        .sidebar .nav-item a:hover,
        .sidebar .nav-item.active a {background: #007bff;}
        .sidebar .nav-item a i {margin-right: 10px;}
        .main-content {width: calc(100% - 250px);margin-left: 250px;transition: all 0.3s;}
        .header {background: #fff;padding: 15px 20px;box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);display: flex;justify-content: space-between;align-items: center;}
        .header .toggle-btn {background: none;border: none;font-size: 20px;cursor: pointer;}
        .header .user-menu {position: relative;}
        .header .user-menu .dropdown-toggle {background: none;border: none;display: flex;align-items: center;cursor: pointer;}
        .header .user-menu .dropdown-toggle img {width: 35px;height: 35px;border-radius: 50%;margin-right: 10px;}
        .booking-card {margin-bottom: 20px;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);}
        .booking-header {background-color: #f8f9fa;padding: 15px;border-bottom: 1px solid #dee2e6;}
        .booking-body {padding: 20px;}
        .payment-details {background-color: #f1f8ff;padding: 15px;border-radius: 5px;margin-top: 15px;}
        .status-pending {color: #fd7e14;}
        .status-confirmed {color: #28a745;}
        .status-cancelled {color: #dc3545;}
        @media (max-width: 768px) {
        .sidebar {margin-left: -250px;}
        .sidebar.active {margin-left: 0;}
        .main-content {width: 100%;margin-left: 0;}
        .main-content.active {margin-left: 250px;width: calc(100% - 250px);}}
    </style>
</head>
<body>
    <div class="wrapper"> 
        <nav class="sidebar">
            <div class="logo-container">
                <img src="http://localhost/hostel_info/images/srmap.png" alt="Logo" class="logo">
                <h3>Hostel Booking</h3>
            </div>
            <ul class="nav-menu">
                <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'room_booking.php') ? 'active' : ''; ?>">
                    <a href="room_booking.php"><i class="fas fa-bed"></i> Book a Room</a>
                </li>
                <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'room_details.php') ? 'active' : ''; ?>">
                    <a href="room_details.php"><i class="fas fa-info-circle"></i> Room Details</a>
                </li>
                <li class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                </li>
                <li class="nav-item"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
        <div class="main-content">
            <header class="header">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="user-menu dropdown">
                    <button class="dropdown-toggle" type="button" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <!-- <img src="images/avatar.png" alt="User Avatar"> -->
                        <span><?php echo $student_details['firstName'] . ' ' . $student_details['lastName']; ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                      <a class="dropdown-item" href="profile.php"><i class="fas fa-user mr-2"></i> My Profile</a>
                        <a class="dropdown-item" href="change_password.php"><i class="fas fa-key mr-2"></i> Change Password</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                    </div>
                </div>
            </header>
            <div class="container mt-4">
                <h2 class="mb-4"><i class="fas fa-bed mr-2"></i>Room Booking Details</h2>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($active_bookings)): ?>
                    <div class="alert alert-info">
                        <?php if (empty($bookings)): ?>
                            You haven't booked any rooms yet. <a href="room_booking.php">Book a room now</a>.
                        <?php else: ?>
                            You don't have any active room bookings. Your previous bookings have been cancelled. <a href="room_booking.php">Book a room now</a>.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Student Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Name:</strong> <?php echo $student_details['firstName'] . ' ' . $student_details['lastName']; ?></p>
                                            <p><strong>Registration Number:</strong> <?php echo $student_details['regNo']; ?></p>
                                            <p><strong>Email:</strong> <?php echo $student_details['email']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Contact:</strong> <?php echo $student_details['contact']; ?></p>
                                            <p><strong>Course:</strong> <?php echo $student_details['course'] ?? 'Not specified'; ?></p>
                                            <p><strong>Year of Study:</strong> <?php echo $student_details['year_of_study'] ?? 'Not specified'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <h3 class="mb-3">Your Active Bookings</h3>
                    <?php foreach ($active_bookings as $booking): ?>
                        <div class="card booking-card">
                            <div class="booking-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?php echo $booking['hostel_name']; ?> - Room <?php echo $booking['room_number']; ?>
                                    <?php if ($booking['status'] == 'pending'): ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php elseif ($booking['status'] == 'confirmed'): ?>
                                        <span class="badge badge-success">Confirmed</span>
                                    <?php endif; ?>
                                    <?php if (!$booking['payment_complete']): ?>
                                        <span class="badge badge-danger">Payment Due</span>
                                    <?php endif; ?>
                                </h5>
                                <span class="text-muted">Booked on: <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                            </div>
                            <div class="booking-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Room Details</h6>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th>Floor</th>
                                                <td><?php echo $booking['floor']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Room Type</th>
                                                <td>
                                                    <?php echo $booking['sharing_type']; ?> 
                                                    <?php echo $booking['is_ac'] ? '(AC)' : '(Non-AC)'; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Stay Period</th>
                                                <td><?php echo $booking['stay_period']; ?> months</td>
                                            </tr>
                                            <tr>
                                                <th>Total Fee</th>
                                                <td>₹<?php echo number_format($booking['booking_total'], 2); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Payment Details</h6>
                                        <div class="payment-details">
                                            <p><strong>Total Fee:</strong> ₹<?php echo number_format($booking['booking_total'], 2); ?></p>
                                            <p><strong>Amount Paid:</strong> ₹<?php echo number_format($booking['paid_amount'], 2); ?></p>
                                            <?php if ($booking['due_amount'] > 0): ?>
                                                <p><strong>Due Amount:</strong> <span class="text-danger">₹<?php echo number_format($booking['due_amount'], 2); ?></span></p>
                                                <p><strong>Payment Status:</strong> <span class="badge badge-warning">Payment Due</span></p>
                                                <a href="due_payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm mt-2">
                                                    <i class="fas fa-credit-card"></i> Pay Due Amount
                                                </a>
                                            <?php else: ?>
                                                <p><strong>Due Amount:</strong> <span class="text-success">₹0.00</span></p>
                                                <p><strong>Payment Status:</strong> <span class="badge badge-success">Fully Paid</span></p>
                                            <?php endif; ?>
                                            <?php 
                                            $payment_detail = isset($payment_details[$booking['id']]) ? $payment_details[$booking['id']] : null;
                                            if ($payment_detail): 
                                            ?>
                                                <hr>
                                                <p><strong>Latest Payment:</strong></p>
                                                <p><strong>Payment Method:</strong> <?php echo $payment_detail['payment_method']; ?></p>
                                                <p><strong>Transaction ID:</strong> <?php echo $payment_detail['transaction_id']; ?></p>
                                                <p><strong>Payment Date:</strong> <?php echo date('F j, Y', strtotime($payment_detail['created_at'])); ?></p>
                                                <?php if ($payment_detail['payment_method'] == 'credit_card' || $payment_detail['payment_method'] == 'debit_card'): ?>
                                                    <p><strong>Card Number:</strong> XXXX-XXXX-XXXX-<?php echo substr($payment_detail['card_number'], -4); ?></p>
                                                <?php elseif ($payment_detail['payment_method'] == 'upi'): ?>
                                                    <p><strong>UPI ID:</strong> <?php echo $payment_detail['upi_id']; ?></p>
                                                <?php elseif ($payment_detail['payment_method'] == 'bank_transfer'): ?>
                                                    <p><strong>Bank:</strong> <?php echo $payment_detail['bank_name']; ?></p>
                                                    <p><strong>Account Number:</strong> XXXXX<?php echo substr($payment_detail['account_number'], -4); ?></p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <?php if ($booking['status'] == 'pending'): ?>
                                        <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this booking?');">
                                            <i class="fas fa-times"></i> Cancel Booking</a>
                                    <?php endif; ?>
                                    <!-- <a href="view_booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View Full Details</a> -->
                                    <a href="payment_history.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-history"></i> Payment History</a>
                                    <?php if ($booking['status'] == 'confirmed' && $booking['payment_complete']): ?>
                                        <a href="generate_receipt.php?id=<?php echo $booking['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-file-invoice"></i> Download Receipt</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($bookings) && count($bookings) > count($active_bookings)): ?>
                        <h3 class="mb-3 mt-4">Cancelled Bookings History</h3>
                        <div class="alert alert-secondary">
                            You have <?php echo count($bookings) - count($active_bookings); ?> cancelled booking(s). These rooms are no longer booked.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarToggle').on('click', function () {
                $('.sidebar').toggleClass('active');
                $('.main-content').toggleClass('active');
            });
            $('.dropdown-toggle').dropdown();
        });
    </script>
</body>
</html>