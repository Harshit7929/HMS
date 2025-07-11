<?php
// Include the admin database connection
include 'admin_db.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Function to generate invoice HTML when booking ID is provided
function generateInvoiceHtml($conn, $booking_id) {
    // Fetch booking details
    $booking_query = "SELECT rb.*, ss.firstName, ss.lastName, ss.regNo, ss.contact, ss.email 
                     FROM room_bookings rb 
                     JOIN student_signup ss ON rb.user_email = ss.email
                     WHERE rb.id = ?";

    $stmt = $conn->prepare($booking_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking_result = $stmt->get_result();

    if ($booking_result->num_rows === 0) {
        return "<div class='alert alert-danger'>Error: Booking not found</div>";
    }

    $booking_data = $booking_result->fetch_assoc();

    // Fetch payment details
    $payment_query = "SELECT * FROM payment_details WHERE booking_id = ?";
    $stmt = $conn->prepare($payment_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $payment_result = $stmt->get_result();
    $payment_data = $payment_result->fetch_assoc();

    // Fetch fee dues
    $dues_query = "SELECT * FROM fee_dues WHERE booking_id = ?";
    $stmt = $conn->prepare($dues_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $dues_result = $stmt->get_result();
    $dues_data = $dues_result->fetch_assoc();

    // Calculate remaining balance
    $amount_paid = isset($payment_data['amount']) ? $payment_data['amount'] : 0;
    $total_fee = $booking_data['total_fee'];
    $balance_due = $total_fee - $amount_paid;

    // Generate invoice number (combination of booking ID and timestamp)
    $invoice_number = "INV-" . $booking_id . "-" . date("Ymd");

    // Generate invoice date
    $invoice_date = date("Y-m-d");

    // Format for PDF generation
    $room_type = $booking_data['is_ac'] ? "AC" : "Non-AC";
    $sharing = $booking_data['sharing_type'];
    $stay_period = $booking_data['stay_period'] . " months";

    // Generate the Invoice HTML
    $invoice_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice #' . $invoice_number . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            .invoice-container {
                max-width: 800px;
                margin: 0 auto;
                padding: 30px;
                border: 1px solid #eee;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            }
            .invoice-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #eee;
            }
            .invoice-title {
                font-size: 28px;
                color: #1a75ff;
            }
            .invoice-details {
                text-align: right;
                font-size: 14px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            table th, table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            table th {
                background-color: #f8f8f8;
            }
            .total-row {
                font-weight: bold;
                background-color: #f0f0f0;
            }
            .payment-info, .customer-info {
                margin-bottom: 20px;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 12px;
                color: #777;
            }
            .text-right {
                text-align: right;
            }
            .status-paid {
                color: green;
                font-weight: bold;
            }
            .status-pending {
                color: orange;
                font-weight: bold;
            }
            .hostel-info {
                margin-bottom: 20px;
            }
            .print-button {
                display: block;
                margin: 20px auto;
                padding: 10px 20px;
                background: #1a75ff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            @media print {
                .print-button {
                    display: none;
                }
                body {
                    padding: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="invoice-header">
                <div>
                    <div class="invoice-title">INVOICE</div>
                    <div>University Hostel Management</div>
                </div>
                <div class="invoice-details">
                    <div><strong>Invoice #:</strong> ' . $invoice_number . '</div>
                    <div><strong>Date:</strong> ' . $invoice_date . '</div>
                    <div><strong>Status:</strong> <span class="status-' . 
                        (isset($dues_data['status']) ? $dues_data['status'] : 'pending') . 
                    '">' . (isset($dues_data['status']) ? ucfirst($dues_data['status']) : 'Pending') . '</span></div>
                </div>
            </div>
            
            <div class="customer-info">
                <h3>Student Information</h3>
                <div><strong>Name:</strong> ' . $booking_data['firstName'] . ' ' . $booking_data['lastName'] . '</div>
                <div><strong>Registration Number:</strong> ' . $booking_data['regNo'] . '</div>
                <div><strong>Email:</strong> ' . $booking_data['email'] . '</div>
                <div><strong>Contact:</strong> ' . $booking_data['contact'] . '</div>
            </div>
            
            <div class="hostel-info">
                <h3>Hostel Information</h3>
                <div><strong>Hostel Name:</strong> ' . $booking_data['hostel_name'] . '</div>
                <div><strong>Room Number:</strong> ' . $booking_data['room_number'] . ' (Floor: ' . $booking_data['floor'] . ')</div>
                <div><strong>Room Type:</strong> ' . $room_type . ', ' . $sharing . '</div>
                <div><strong>Stay Period:</strong> ' . $stay_period . '</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Duration</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . $room_type . ' Room (' . $sharing . ')</td>
                        <td>' . $stay_period . '</td>
                        <td class="text-right">₹' . number_format($booking_data['total_fee'], 2) . '</td>
                    </tr>';

    if (isset($payment_data['amount']) && $payment_data['amount'] > 0) {
        $invoice_html .= '
                    <tr>
                        <td colspan="2">Payment Received (' . 
                            (isset($payment_data['payment_method']) ? $payment_data['payment_method'] : 'Online') . 
                            ', Transaction ID: ' . 
                            (isset($payment_data['transaction_id']) ? $payment_data['transaction_id'] : 'N/A') . 
                        ')</td>
                        <td class="text-right">-₹' . number_format($amount_paid, 2) . '</td>
                    </tr>';
    }

    $invoice_html .= '
                    <tr class="total-row">
                        <td colspan="2">Balance Due</td>
                        <td class="text-right">₹' . number_format($balance_due, 2) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="payment-info">
                <h3>Payment Information</h3>';

    if (isset($dues_data['due_date'])) {
        $invoice_html .= '
                <div><strong>Due Date:</strong> ' . $dues_data['due_date'] . '</div>';
    }

    $invoice_html .= '
                <div><strong>Payment Methods:</strong> Online Transfer, Card Payment, UPI</div>
                <div><strong>Account Details:</strong> University Hostel Management</div>
                <div><strong>Bank Account:</strong> XXXX-XXXX-1234</div>
                <div><strong>UPI ID:</strong> hostel@upibank</div>
            </div>
            
            <div class="footer">
                <p>Thank you for your business. For any queries regarding this invoice, please contact the hostel administration office.</p>
                <p>This is a computer-generated invoice and does not require a signature.</p>
            </div>
        </div>
        
        <button class="print-button" onclick="window.print()">Print Invoice</button>
        <button class="print-button" onclick="window.location.href=\'admin_dashboard.php\'">Back to Dashboard</button>
        
    </body>
    </html>
    ';

    // Update the database to record that the invoice was generated
    $update_query = "UPDATE fee_dues SET updated_at = NOW() WHERE booking_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();

    return $invoice_html;
}

// Main content logic
if (isset($_GET['booking_id']) && !empty($_GET['booking_id'])) {
    // If booking ID is provided, generate the invoice
    $booking_id = intval($_GET['booking_id']);
    echo generateInvoiceHtml($conn, $booking_id);
} else {
    // If no booking ID is provided, show a booking selection interface
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Generate Invoice</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
        <style>
            body {
                background-color: #f8f9fa;
                padding: 20px;
            }
            .container {
                max-width: 1200px;
                margin: 20px auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            }
            .header {
                margin-bottom: 30px;
                border-bottom: 1px solid #e3e3e3;
                padding-bottom: 15px;
            }
            .search-box {
                margin-bottom: 20px;
            }
            .table th {
                background-color: #f1f1f1;
            }
            .status-confirmed {
                color: green;
                font-weight: bold;
            }
            .status-pending {
                color: orange;
                font-weight: bold;
            }
            .status-cancelled {
                color: red;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Generate Invoice</h2>
                <p>Select a booking to generate an invoice</p>
            </div>
            
            <div class="search-box">
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, or reg. number...">
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="admin_dashboard.php" class="btn btn-secondary w-100">Back to Dashboard</a>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Student Name</th>
                            <th>Registration No.</th>
                            <th>Hostel & Room</th>
                            <th>Booking Date</th>
                            <th>Total Fee</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all bookings with student details
                        $query = "SELECT rb.*, ss.firstName, ss.lastName, ss.regNo 
                                 FROM room_bookings rb 
                                 JOIN student_signup ss ON rb.user_email = ss.email
                                 ORDER BY rb.booking_date DESC";
                        
                        $result = $conn->query($query);
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $statusClass = "";
                                switch ($row['status']) {
                                    case 'confirmed':
                                        $statusClass = "status-confirmed";
                                        break;
                                    case 'pending':
                                        $statusClass = "status-pending";
                                        break;
                                    case 'cancelled':
                                        $statusClass = "status-cancelled";
                                        break;
                                }
                                
                                echo '<tr class="booking-row">
                                    <td>' . $row['id'] . '</td>
                                    <td>' . $row['firstName'] . ' ' . $row['lastName'] . '</td>
                                    <td>' . $row['regNo'] . '</td>
                                    <td>' . $row['hostel_name'] . ' - Room ' . $row['room_number'] . '</td>
                                    <td>' . date('d M Y', strtotime($row['booking_date'])) . '</td>
                                    <td>₹' . number_format($row['total_fee'], 2) . '</td>
                                    <td class="' . $statusClass . '">' . ucfirst($row['status']) . '</td>
                                    <td>
                                        <a href="generate_invoice.php?booking_id=' . $row['id'] . '" class="btn btn-primary btn-sm">Generate Invoice</a>
                                    </td>
                                </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">No bookings found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            $(document).ready(function(){
                // Search functionality
                $("#searchInput").on("keyup", function() {
                    var value = $(this).val().toLowerCase();
                    $(".booking-row").filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                    });
                });
                
                // Status filter
                $("#statusFilter").on("change", function() {
                    var value = $(this).val().toLowerCase();
                    if (value === "") {
                        $(".booking-row").show();
                    } else {
                        $(".booking-row").filter(function() {
                            return $(this).children().eq(6).text().toLowerCase().indexOf(value) > -1;
                        }).show();
                        $(".booking-row").filter(function() {
                            return $(this).children().eq(6).text().toLowerCase().indexOf(value) === -1;
                        }).hide();
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}

// Close the database connection
$conn->close();
?>