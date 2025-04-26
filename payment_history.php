<?php
session_start();
include('db.php');
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "Please login to view your payment history.";
    header("Location: login.php");
    exit();
}
$user = $_SESSION['user'];
$userEmail = $user['email'];
$regNo = $user['regNo'];
function formatDate($date) {
    return date("F j, Y, g:i a", strtotime($date));
}
function getStatusBadge($status) {
    switch(strtolower($status)) {
        case 'success':
        case 'successful':
        case 'completed':
            return '<span class="badge bg-success">Success</span>';
        case 'pending':
            return '<span class="badge bg-warning">Pending</span>';
        case 'failed':
            return '<span class="badge bg-danger">Failed</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
$sql = "SELECT 
            pd.id,
            pd.booking_id,
            pd.amount,
            pd.payment_method,
            pd.payment_status,
            pd.transaction_id,
            pd.created_at,
            rb.hostel_name,
            rb.room_number,
            rb.sharing_type
        FROM payment_details pd
        LEFT JOIN room_bookings rb ON pd.booking_id = rb.id
        WHERE pd.user_email = ?
        ORDER BY pd.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- <link href="css/payment_history.css" rel="stylesheet"> -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; line-height: 1.6; }
        header { background-color: #2c3e50; color: white; padding: 15px; text-align: center; position: fixed; width: 100%; top: 0; z-index: 100; }
        .sidebar { position: fixed; width: 220px; height: 100%; background-color: #2c3e50; padding-top: 60px; left: 0; top: 0; }
        .sidebar-logo { width: 100px; display: block; margin: 0 auto 20px; }
        .sidebar a { display: block; padding: 10px 15px; color: white; text-decoration: none; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; border-left-color: #3498db; }
        .sidebar a i { margin-right: 10px; }
        .main-content { margin-left: 220px; margin-top: 60px; padding: 20px; }
        .container { width: 100%; padding: 0 15px; }
        h2.text-center { margin-bottom: 20px; }
        .nav-tabs { border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .nav-tabs .nav-link { border: none; color: #555; padding: 10px 15px; }
        .nav-tabs .nav-link.active { background-color: #f5f5f5; border-bottom: 2px solid #3498db; color: #3498db; }
        .table-responsive { background-color: white; border-radius: 4px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .table tbody tr:hover { background-color: #f9f9f9; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: normal; }
        .bg-success { background-color: #2ecc71; color: white; }
        .bg-warning { background-color: #f39c12; color: white; }
        .bg-danger { background-color: #e74c3c; color: white; }
        .bg-info { background-color: #3498db; color: white; }
        .bg-secondary { background-color: #95a5a6; color: white; }
        .payment-method-badge { display: inline-block; padding: 5px 8px; border-radius: 4px; font-size: 12px; color: white; }
        .btn { display: inline-block; padding: 6px 12px; text-align: center; text-decoration: none; border-radius: 4px; border: 1px solid transparent; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-outline-primary { color: #3498db; border-color: #3498db; }
        .btn-outline-primary:hover { background-color: #3498db; color: white; }
        .btn-outline-danger { color: #e74c3c; border-color: #e74c3c; }
        .btn-outline-danger:hover { background-color: #e74c3c; color: white; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-info { background-color: #d9edf7; border: 1px solid #bce8f1; color: #31708f; }
        .footer { margin-left: 220px; padding: 15px; text-align: center; background-color: #f5f5f5; border-top: 1px solid #ddd; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: relative; padding-top: 15px; } .main-content, .footer { margin-left: 0; } header { position: relative; } }
    </style>
</head>
<body>
    <header>
        Hostel Management System
    </header>
    <div class="sidebar">
        <img src="images/srmap.png" alt="SRM AP Logo" class="sidebar-logo">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="room_booking.php"><i class="fas fa-bed"></i> Room Booking</a>
        <a href="payment_history.php" class="active"><i class="fas fa-money-bill-wave"></i> Payment History</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="container mt-4">
            <h2 class="text-center mb-4">Payment History</h2>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="payments-tab" data-toggle="tab" href="#payments" role="tab" aria-controls="payments" aria-selected="true">
                                <i class="fas fa-credit-card mr-2"></i>Payments</a>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                            <?php if ($result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Transaction ID</th>
                                                <th>Room Details</th>
                                                <th>Amount</th>
                                                <th>Payment Method</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo formatDate($row['created_at']); ?></td>
                                                    <td><span class="text-muted">#<?php echo $row['transaction_id']; ?></span></td>
                                                    <td>
                                                        <?php if ($row['hostel_name']): ?>
                                                            <?php echo $row['hostel_name']; ?> Hostel, 
                                                            Room <?php echo $row['room_number']; ?>, 
                                                            <?php echo $row['sharing_type']; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong>₹<?php echo number_format($row['amount'], 2); ?></strong></td>
                                                    <td>
                                                        <?php
                                                        $methodClass = '';
                                                        $methodIcon = '';
                                                        switch(strtolower($row['payment_method'])) {
                                                            case 'card':
                                                            case 'credit card':
                                                            case 'debit card':
                                                                $methodClass = 'bg-info';
                                                                $methodIcon = 'fa-credit-card';
                                                                break;
                                                            case 'upi':
                                                                $methodClass = 'bg-success';
                                                                $methodIcon = 'fa-mobile-alt';
                                                                break;
                                                            case 'netbanking':
                                                            case 'net banking':
                                                                $methodClass = 'bg-primary';
                                                                $methodIcon = 'fa-university';
                                                                break;
                                                            case 'wallet':
                                                            case 'digital wallet':
                                                                $methodClass = 'bg-warning';
                                                                $methodIcon = 'fa-wallet';
                                                                break;
                                                            default:
                                                                $methodClass = 'bg-secondary';
                                                                $methodIcon = 'fa-money-bill-alt';
                                                        }
                                                        ?>
                                                        <span class="payment-method-badge <?php echo $methodClass; ?>">
                                                            <i class="fas <?php echo $methodIcon; ?> mr-1"></i>
                                                            <?php echo ucfirst($row['payment_method']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo getStatusBadge($row['payment_status']); ?></td>
                                                    <td>
                                                        <a href="payment_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            View Details
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> You have no payment history yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
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