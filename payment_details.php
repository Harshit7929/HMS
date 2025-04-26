<?php
// Start the session to access user data
session_start();
include('db.php');

// Check if the user is logged in
if (!isset($_SESSION['user'])) {
    // Redirect to login page if not logged in
    $_SESSION['error'] = "Please login to view payment details.";
    header("Location: login.php");
    exit();
}

// Get current user's data from session
$user = $_SESSION['user'];
$userEmail = $user['email'];
$regNo = $user['regNo'];

// Check if payment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID.";
    header("Location: payment_history.php");
    exit();
}

$paymentId = intval($_GET['id']);

// Function to get formatted date
function formatDate($date) {
    return date("F j, Y, g:i a", strtotime($date));
}

// Function to get payment status badge
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

// SQL query to fetch specific payment details and ensure it belongs to the current user
// Updated to match the actual schema
$sql = "SELECT 
            pd.*,
            rb.hostel_name,
            rb.room_number,
            rb.sharing_type,
            rb.booking_date,
            rb.floor,
            rb.is_ac,
            rb.stay_period,
            rb.total_fee,
            rb.status as booking_status,
            ss.email,
            ss.regNo as reg_no,
            CONCAT(ss.firstName, ' ', ss.lastName) as full_name,
            ss.contact as phone
        FROM 
            payment_details pd
        LEFT JOIN 
            room_bookings rb ON pd.booking_id = rb.id
        LEFT JOIN
            student_signup ss ON pd.user_email = ss.email
        WHERE 
            pd.id = ? AND pd.user_email = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $paymentId, $userEmail);
$stmt->execute();
$result = $stmt->get_result();

// Check if payment exists and belongs to the user
if ($result->num_rows == 0) {
    $_SESSION['error'] = "Payment not found or you don't have permission to view it.";
    header("Location: payment_history.php");
    exit();
}

$payment = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="css/payment_history.css" rel="stylesheet">
    <style>
        .payment-detail-card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .payment-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row {
            padding: 12px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .receipt-container {
            background-color: #fff;
            padding: 30px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .receipt-container {
                padding: 0;
                margin: 0;
            }
            
            body {
                padding: 0;
                margin: 0;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <header class="no-print">
        Hostel Management System
    </header>
    <div class="sidebar no-print">
        <img src="images/srmap.png" alt="SRM AP Logo" class="sidebar-logo">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="room_booking.php"><i class="fas fa-bed"></i> Room Booking</a>
        <a href="payment_history.php" class="active"><i class="fas fa-money-bill-wave"></i> Payment History</a>
        <!-- <a href="refund_request.php"><i class="fas fa-undo-alt"></i> Refund Request</a> -->
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="main-content">
        <div class="container mt-4">
            <div class="row mb-4">
                <div class="col-md-12">
                    <nav aria-label="breadcrumb" class="no-print">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="payment_history.php">Payment History</a></li>
                            <li class="breadcrumb-item active">Payment Details</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <div class="receipt-container" id="receipt">
                <div class="row">
                    <div class="col-md-8 offset-md-2">
                        <div class="payment-detail-card">
                            <div class="payment-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-1">Payment Receipt</h2>
                                    <p class="text-muted mb-0">Transaction ID: #<?php echo $payment['transaction_id']; ?></p>
                                </div>
                                <div class="text-right">
                                    <img src="images/srm.png" alt="SRM AP Logo" style="height: 60px;">
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Student Information</h5>
                                        <p class="mb-1"><strong>Name:</strong> <?php echo $payment['full_name']; ?></p>
                                        <p class="mb-1"><strong>Registration No:</strong> <?php echo $payment['reg_no']; ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo $payment['email']; ?></p>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo $payment['phone']; ?></p>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <h5 class="mb-3">Payment Information</h5>
                                        <p class="mb-1"><strong>Date:</strong> <?php echo formatDate($payment['created_at']); ?></p>
                                        <p class="mb-1">
                                            <strong>Status:</strong> 
                                            <?php echo getStatusBadge($payment['payment_status']); ?>
                                        </p>
                                        <p class="mb-1"><strong>Method:</strong> <?php echo ucfirst($payment['payment_method']); ?></p>
                                        <h4 class="mt-3">Amount: ₹<?php echo number_format($payment['amount'], 2); ?></h4>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="mb-3">Room Booking Details</h5>
                                        <?php if ($payment['hostel_name']): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1"><strong>Hostel:</strong> <?php echo $payment['hostel_name']; ?></p>
                                                    <p class="mb-1"><strong>Room Number:</strong> <?php echo $payment['room_number']; ?></p>
                                                    <p class="mb-1"><strong>Floor:</strong> <?php echo $payment['floor']; ?></p>
                                                    <p class="mb-1"><strong>Room Type:</strong> <?php echo $payment['is_ac'] ? 'AC' : 'Non-AC'; ?></p>
                                                    <p class="mb-1"><strong>Sharing Type:</strong> <?php echo $payment['sharing_type']; ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1"><strong>Booking Date:</strong> <?php echo formatDate($payment['booking_date']); ?></p>
                                                    <p class="mb-1"><strong>Booking Status:</strong> <?php echo ucfirst($payment['booking_status']); ?></p>
                                                    <p class="mb-1"><strong>Stay Period:</strong> <?php echo $payment['stay_period']; ?> months</p>
                                                    <p class="mb-1"><strong>Total Fee:</strong> ₹<?php echo number_format($payment['total_fee'], 2); ?></p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No room booking details available for this payment.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (isset($payment['payment_details']) && !empty($payment['payment_details'])): ?>
                                <hr>
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h5 class="mb-3">Additional Payment Details</h5>
                                        <p><?php echo nl2br($payment['payment_details']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-12 text-center">
                                        <p class="mb-1">Thank you for your payment.</p>
                                        <p class="text-muted mb-0">If you have any questions, please contact the hostel administration.</p>
                                    </div>
                                </div>
                                
                                <div class="mt-4 text-center d-print-none no-print">
                                    <p class="small text-muted">This is a computer-generated receipt and doesn't require a signature.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4 mb-5 no-print">
                            <button class="btn btn-primary mr-2" onclick="printReceipt()">
                                <i class="fas fa-print mr-1"></i> Print Receipt
                            </button>
                            <a href="payment_history.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Payment History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer no-print">
        <p>&copy; 2025 Hostel Management System | All Rights Reserved</p>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function printReceipt() {
            window.print();
        }
    </script>
</body>
</html>