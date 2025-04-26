<?php
// Include necessary files
include('db.php');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid booking ID.";
    header("Location: room_details.php");
    exit;
}

$booking_id = $_GET['id'];
$user = $_SESSION['user'];
$email = $user['email'];

// Verify booking belongs to the current user and is confirmed with complete payment
$booking_sql = "SELECT rb.*, 
                rb.total_fee as booking_total,
                COALESCE(SUM(pd.amount), 0) as amount_paid
                FROM room_bookings rb
                LEFT JOIN payment_details pd ON rb.id = pd.booking_id AND rb.user_email = pd.user_email
                WHERE rb.id = ? AND rb.user_email = ?
                GROUP BY rb.id";

if ($stmt = $conn->prepare($booking_sql)) {
    $stmt->bind_param("is", $booking_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Booking not found or access denied.";
        header("Location: room_details.php");
        exit;
    }
    
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    // Check if booking is confirmed and fully paid
    $due_amount = $booking['booking_total'] - $booking['amount_paid'];
    $payment_complete = ($due_amount <= 0);
    
    if ($booking['status'] != 'confirmed' || !$payment_complete) {
        $_SESSION['error'] = "Receipt can only be generated for confirmed bookings with complete payment.";
        header("Location: room_details.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Database error: Unable to fetch booking details.";
    header("Location: room_details.php");
    exit;
}

// Fetch student details
$student_sql = "SELECT s.*, sd.* FROM student_signup s
                LEFT JOIN student_details sd ON s.regNo = sd.reg_no
                WHERE s.email = ?";

if ($stmt = $conn->prepare($student_sql)) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $student_result = $stmt->get_result();
    $student = $student_result->fetch_assoc();
    $stmt->close();
} else {
    $_SESSION['error'] = "Database error: Unable to fetch student details.";
    header("Location: room_details.php");
    exit;
}

// Fetch all payment details for this booking
$payment_sql = "SELECT * FROM payment_details 
                WHERE booking_id = ? AND user_email = ?
                ORDER BY created_at ASC";

if ($stmt = $conn->prepare($payment_sql)) {
    $stmt->bind_param("is", $booking_id, $email);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payments = [];
    
    while ($row = $payment_result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    $stmt->close();
} else {
    $_SESSION['error'] = "Database error: Unable to fetch payment details.";
    header("Location: room_details.php");
    exit;
}

// Generate unique receipt number
$receipt_number = 'RCPT-' . date('Ymd') . '-' . $booking_id;
$current_date = date('F j, Y');
$formatted_booking_date = date('F j, Y', strtotime($booking['booking_date']));
$total_amount = number_format($booking['amount_paid'], 2);

// Set headers for file download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="Hostel_Receipt_' . $booking_id . '.html"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Booking Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #0056b3;
            margin-bottom: 5px;
        }
        .receipt-details {
            overflow: hidden;
            margin-bottom: 20px;
        }
        .receipt-details .left {
            float: left;
            width: 50%;
        }
        .receipt-details .right {
            float: right;
            width: 50%;
            text-align: right;
        }
        h3 {
            color: #0056b3;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            padding: 8px;
            text-align: left;
        }
        table.info td {
            padding: 5px 10px;
        }
        table.payment {
            border: 1px solid #ddd;
        }
        table.payment th {
            background-color: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        table.payment td {
            border-bottom: 1px solid #eee;
        }
        .total-row td {
            font-weight: bold;
            border-top: 2px solid #ddd;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .signatures {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            margin-top: 10px;
            text-align: center;
        }
        .note {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-size: 12px;
        }
        @media print {
            body {
                padding: 0;
            }
            .container {
                border: none;
                box-shadow: none;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Hostel Accommodation Receipt</h1>
            <h3>Hostel Booking System</h3>
        </div>
        
        <div class="receipt-details">
            <div class="left">
                <strong>Receipt Number:</strong> <?php echo $receipt_number; ?>
            </div>
            <div class="right">
                <strong>Date:</strong> <?php echo $current_date; ?>
            </div>
        </div>
        
        <h3>Student Information</h3>
        <table class="info">
            <tr>
                <td width="50%"><strong>Name:</strong> <?php echo $student['firstName'] . ' ' . $student['lastName']; ?></td>
                <td width="50%"><strong>Registration Number:</strong> <?php echo $student['regNo']; ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong> <?php echo $student['email']; ?></td>
                <td><strong>Contact:</strong> <?php echo $student['contact']; ?></td>
            </tr>
            <tr>
                <td><strong>Course:</strong> <?php echo $student['course'] ?? 'Not specified'; ?></td>
                <td><strong>Year of Study:</strong> <?php echo $student['year_of_study'] ?? 'Not specified'; ?></td>
            </tr>
        </table>
        
        <h3>Booking Details</h3>
        <table class="info">
            <tr>
                <td width="50%"><strong>Hostel:</strong> <?php echo $booking['hostel_name']; ?></td>
                <td width="50%"><strong>Room Number:</strong> <?php echo $booking['room_number']; ?></td>
            </tr>
            <tr>
                <td><strong>Floor:</strong> <?php echo $booking['floor']; ?></td>
                <td><strong>Room Type:</strong> <?php echo $booking['sharing_type'] . ' ' . ($booking['is_ac'] ? '(AC)' : '(Non-AC)'); ?></td>
            </tr>
            <tr>
                <td><strong>Stay Period:</strong> <?php echo $booking['stay_period']; ?> months</td>
                <td><strong>Booking Date:</strong> <?php echo $formatted_booking_date; ?></td>
            </tr>
        </table>
        
        <h3>Payment Summary</h3>
        <table class="payment">
            <tr>
                <th width="5%">Sl No.</th>
                <th width="20%">Date</th>
                <th width="30%">Transaction ID</th>
                <th width="25%">Payment Method</th>
                <th width="20%" style="text-align: right;">Amount (₹)</th>
            </tr>
            <?php 
            $counter = 1;
            foreach ($payments as $payment): 
                $payment_date = date('F j, Y', strtotime($payment['created_at']));
                $payment_amount = number_format($payment['amount'], 2);
            ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo $payment_date; ?></td>
                <td><?php echo $payment['transaction_id']; ?></td>
                <td><?php echo $payment['payment_method']; ?></td>
                <td style="text-align: right;"><?php echo $payment_amount; ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Total Amount Paid:</td>
                <td style="text-align: right;">₹<?php echo $total_amount; ?></td>
            </tr>
        </table>
        
        <div class="signatures">
            <div>
                <p><strong>Student Signature</strong></p>
                <div class="signature-line"></div>
            </div>
            <div>
                <p><strong>Hostel Authority</strong></p>
                <div class="signature-line"></div>
            </div>
        </div>
        
        <div class="note">
            <p>Note: This receipt confirms that the above-mentioned student has successfully completed payment for the hostel accommodation as specified. 
            For any queries regarding this booking, please contact the hostel administration office with your Receipt Number.</p>
            <p>Email: hostel.admin@example.com | Phone: +91-1234567890</p>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated receipt and does not require a physical signature.</p>
            <button class="print-button" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
</body>
</html>
<?php
exit;
?>