<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$regNo = $_SESSION['user']['regNo'];
$message = "";
$error = "";
function getCurrentAcademicYear() {
    $month = date('n');
    $year = date('Y');
    if ($month >= 8) { return $year . "-" . ($year + 1);} 
    else {return ($year - 1) . "-" . $year;}
}
$academicYear = getCurrentAcademicYear();
function getStudentHostel($conn, $regNo) {
    $sql = "SELECT hostel FROM student_details WHERE reg_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $regNo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['hostel'])) {return $row['hostel'];}}
    $sql = "SELECT rb.hostel_name 
            FROM room_bookings rb 
            JOIN student_signup ss ON rb.user_email = ss.email 
            WHERE ss.regNo = ? AND rb.status = 'confirmed' 
            ORDER BY rb.booking_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $regNo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['hostel_name'];
    }
    return "Unknown";
}
$studentHostel = getStudentHostel($conn, $regNo);
function getHostelIncharge($conn, $hostel) {
    if ($hostel == 'Vedavathi' || $hostel == 'Ganga') {
        $sql = "SELECT staff_id FROM staff 
                WHERE position = 'Laundry' 
                AND (hostel LIKE '%Vedavathi%' OR hostel LIKE '%Ganga%')
                LIMIT 1";
    } else if ($hostel == 'Krishna' || $hostel == 'Narmadha') {
        $sql = "SELECT staff_id FROM staff 
                WHERE position = 'Laundry' 
                AND (hostel LIKE '%Krishna%' OR hostel LIKE '%Narmadha%')
                LIMIT 1";
    } else {
        $sql = "SELECT staff_id FROM staff 
                WHERE position = 'Laundry'
                LIMIT 1";
    }
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['staff_id'];}
    return null; 
}
function checkQuota($conn, $regNo, $academicYear) {
    $sql = "SELECT * FROM student_laundry_quota WHERE regNo = ? AND academic_year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $regNo, $academicYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {return $result->fetch_assoc();} 
    else {
        $sql = "INSERT INTO student_laundry_quota (regNo, academic_year, bookings_used, max_bookings) VALUES (?, ?, 0, 50)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $regNo, $academicYear);
        $stmt->execute();
        return ['regNo' => $regNo, 'academic_year' => $academicYear, 'bookings_used' => 0, 'max_bookings' => 50];
    }
}
$quota = checkQuota($conn, $regNo, $academicYear);
$sql = "SELECT * FROM laundry_items ORDER BY item_name";
$result = $conn->query($sql);
$laundryItems = [];
if ($result->num_rows > 0) {while ($row = $result->fetch_assoc()) {$laundryItems[] = $row;}}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    if ($quota['bookings_used'] >= $quota['max_bookings']) {$error = "You have reached your maximum laundry bookings for this academic year.";} 
    else {
        $pickupDate = date('Y-m-d', strtotime('+1 day'));
        $totalWeight = 0;
        $validItems = false;
        $selectedItems = [];
        foreach ($laundryItems as $item) {
            $itemId = $item['item_id'];
            $quantity = isset($_POST['quantity'][$itemId]) ? intval($_POST['quantity'][$itemId]) : 0;
            if ($quantity > 0) {
                $validItems = true;
                $totalWeight += $quantity * $item['weight_grams'];
                $selectedItems[] = [
                    'item_id' => $itemId,
                    'quantity' => $quantity
                ];
            }
        }
        if (!$validItems) {$error = "Please select at least one item.";} 
        else {
            $maxWeightGrams = 5000;
            if ($totalWeight > $maxWeightGrams) {$error = "Your laundry exceeds the maximum weight of 5kg. Current weight: " . number_format($totalWeight/1000, 2) . "kg";} 
            else {
                $notes = isset($_POST['notes']) ? $_POST['notes'] : null;
                if ($studentHostel == "Unknown") {$error = "We couldn't determine your hostel. Please update your profile or contact the admin.";} 
                else {
                    $staffIncharge = getHostelIncharge($conn, $studentHostel);
                    $conn->begin_transaction();
                    try {
                        $sql = "INSERT INTO laundry_bookings (regNo, pickup_date, total_weight_grams, notes, academic_year, hostel, staff_incharge_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssissss", $regNo, $pickupDate, $totalWeight, $notes, $academicYear, $studentHostel, $staffIncharge);
                        $stmt->execute();
                        $bookingId = $conn->insert_id;
                        foreach ($selectedItems as $item) {
                            $sql = "INSERT INTO laundry_booking_items (booking_id, item_id, quantity) 
                                    VALUES (?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iii", $bookingId, $item['item_id'], $item['quantity']);
                            $stmt->execute();
                        }
                        $sql = "UPDATE student_laundry_quota SET bookings_used = bookings_used + 1 WHERE regNo = ? AND academic_year = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ss", $regNo, $academicYear);
                        $stmt->execute();
                        $conn->commit();
                        $message = "Laundry booking submitted successfully! Your booking ID is: " . $bookingId;
                        $quota['bookings_used']++;
                        header("Location: laundry.php?success=1&booking_id=" . $bookingId);
                        exit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "An error occurred: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['booking_id'])) {
    $message = "Laundry booking submitted successfully! Your booking ID is: " . $_GET['booking_id'];}
$sql = "SELECT lb.*, 
        COUNT(lbi.booking_item_id) as item_count,
        SUM(lbi.quantity) as total_items,
        s.name as staff_name
        FROM laundry_bookings lb
        LEFT JOIN laundry_booking_items lbi ON lb.booking_id = lbi.booking_id
        LEFT JOIN staff s ON lb.staff_incharge_id = s.staff_id
        WHERE lb.regNo = ?
        GROUP BY lb.booking_id
        ORDER BY lb.booking_date DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $regNo);
$stmt->execute();
$bookingsResult = $stmt->get_result();
$previousBookings = [];
if ($bookingsResult->num_rows > 0) {
    while ($row = $bookingsResult->fetch_assoc()) {$previousBookings[] = $row;}}
function getBookingDetails($conn, $bookingId) {
    $sql = "SELECT lbi.*, li.item_name, li.weight_grams
            FROM laundry_booking_items lbi
            JOIN laundry_items li ON lbi.item_id = li.item_id
            WHERE lbi.booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    if ($result->num_rows > 0) {while ($row = $result->fetch_assoc()) {$items[] = $row;}}
    return $items;
}
if (isset($_GET['view_booking']) && is_numeric($_GET['view_booking'])) {
    $bookingId = $_GET['view_booking'];
    $sql = "SELECT lb.*, s.name as staff_name 
            FROM laundry_bookings lb
            LEFT JOIN staff s ON lb.staff_incharge_id = s.staff_id
            WHERE lb.booking_id = ? AND lb.regNo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $bookingId, $regNo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        $bookingItems = getBookingDetails($conn, $bookingId);
    } else {$error = "Booking not found or you don't have permission to view it.";}
}
if (isset($_GET['cancel_booking']) && is_numeric($_GET['cancel_booking'])) {
    $bookingId = $_GET['cancel_booking'];
    $sql = "SELECT * FROM laundry_bookings WHERE booking_id = ? AND regNo = ? AND status = 'Pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $bookingId, $regNo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $sql = "UPDATE laundry_bookings SET status = 'Cancelled' WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $bookingId);
        if ($stmt->execute()) {
            $sql = "UPDATE student_laundry_quota SET bookings_used = bookings_used - 1 
                    WHERE regNo = ? AND academic_year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $regNo, $academicYear);
            $stmt->execute();
            $message = "Booking successfully cancelled.";
            $quota['bookings_used']--;
            header("Location: laundry.php?cancel_success=1");
            exit();
        } else {$error = "Failed to cancel booking.";}
    } else {$error = "Booking not found, already processed, or you don't have permission to cancel it.";}
}
if (isset($_GET['cancel_success']) && $_GET['cancel_success'] == 1) {$message = "Booking successfully cancelled.";} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laundry Services</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="css/laundry.css">  -->
     <style>
        :root { --primary: #4299e1; --primary-dark: #3182ce; --success: #38a169; --success-light: #9ae6b4; --danger: #e53e3e; 
            --danger-light: #feb2b2; --warning: #d69e2e; --warning-light: #fbd38d; --info: #319795; --info-light: #90cdf4; 
            --dark: #2c3e50; --gray-dark: #4a5568; --gray: #718096; --gray-light: #e2e8f0; --gray-lighter: #f8fafc; 
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08); --shadow: 0 2px 10px rgba(0, 0, 0, 0.05); --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05); 
            --radius-sm: 4px; --radius: 8px; --radius-lg: 1rem; --transition: all 0.2s ease; }
        body { font-family: "Open Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            background-color: #f7f9fc; color: var(--gray-dark); line-height: 1.6; font-size: 16px; }
        .container { max-width: 1140px; padding: 2rem 1rem; margin: 0 auto; }
        h1, h2, h3, h4, h5, h6 { color: var(--dark); font-weight: 600; margin-top: 0; line-height: 1.3; }
        h1 { font-size: 2rem; margin-bottom: 1.5rem; letter-spacing: -0.5px; }
        h3 { font-size: 1.5rem; margin-bottom: 1rem; font-weight: 500; }
        h4 { font-size: 1.25rem; margin-bottom: 0.75rem; }
        .card, .booking-form, .previous-bookings { background-color: #fff; border-radius: var(--radius); box-shadow: var(--shadow); 
            margin-bottom: 1.5rem; border: none; overflow: hidden; }
        .card-body, .booking-form { padding: 1.5rem; }
        .card-header { background-color: var(--gray-lighter); border-bottom: 1px solid var(--gray-light); padding: 1rem 1.5rem; font-weight: 600; }
        .quota-info { margin-bottom: 2rem; }
        .quota-info .card-title { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark); }
        .progress { height: 0.8rem; background-color: #e9ecef; border-radius: 0.5rem; overflow: hidden; margin-top: 0.75rem; position: relative; }
        .progress-bar { background-color: #007bff; border-radius: 0.5rem; transition: width 0.6s ease; height: 100%; display: flex; align-items: center; justify-content: center; }
        .progress-text { position: absolute; width: 100%; text-align: center; font-size: 0.5rem; font-weight: bold; color: #333; 
            mix-blend-mode: difference; left: 0; top: 0; height: 100%; display: flex; align-items: center; justify-content: center; }
        .form-control { border: 1px solid var(--gray-light); border-radius: var(--radius-sm); padding: 0.6rem 0.8rem; 
            width: 100%; transition: var(--transition); color: var(--gray-dark); font-size: 0.95rem; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15); outline: none; }
        .form-control::placeholder { color: var(--gray); opacity: 0.7; }
        .input-group { display: flex; position: relative; }
        .input-group-text { background-color: var(--gray-lighter); border: 1px solid var(--gray-light); border-right: none; 
            border-radius: var(--radius-sm) 0 0 var(--radius-sm); padding: 0.6rem 0.8rem; color: var(--gray-dark); font-size: 0.95rem; 
            display: flex; align-items: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%; }
        .input-group .form-control { border-radius: 0 var(--radius-sm) var(--radius-sm) 0; flex: 1; }
        label { font-weight: 500; color: var(--gray-dark); margin-bottom: 0.5rem; display: block; }
        .form-label { margin-bottom: 0.5rem; }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .weight-calculator { background-color: var(--gray-lighter); border-radius: var(--radius-sm); padding: 1rem 1.25rem; margin: 1.25rem 0; 
            border-left: 3px solid var(--primary); font-weight: 500; display: flex; align-items: center; justify-content: space-between; }
        .weight-calculator p { margin: 0; }
        #weightWarning { color: var(--danger); font-size: 0.9rem; font-weight: 500; margin-left: 0.5rem; }
        .btn { font-weight: 500; border-radius: var(--radius-sm); padding: 0.5rem 1rem; transition: var(--transition); cursor: pointer; 
            display: inline-block; text-align: center; text-decoration: none; line-height: 1.5; border: 1px solid transparent; user-select: none; }
        .btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.25); }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-dark); border-color: var(--primary-dark); }
        .btn-danger { background-color: var(--danger); border-color: var(--danger); color: white; }
        .btn-danger:hover { background-color: #c53030; border-color: #c53030; }
        .btn-info { background-color: var(--info); border-color: var(--info); color: white; }
        .btn-info:hover { background-color: #2c7a7b; border-color: #2c7a7b; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 1rem; }
        .table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
        .table th { font-weight: 600; background-color: var(--gray-lighter); color: var(--gray-dark); border-top: none; 
            border-bottom: 2px solid var(--gray-light); padding: 0.75rem; text-align: left; vertical-align: bottom; }
        .table td { padding: 0.75rem; vertical-align: middle; border-top: 1px solid var(--gray-light); }
        .table-striped tbody tr:nth-of-type(odd) { background-color: var(--gray-lighter); }
        .table-hover tbody tr:hover { background-color: rgba(66, 153, 225, 0.05); }
        .badge { padding: 0.4rem 0.6rem; font-weight: 500; border-radius: var(--radius-sm); font-size: 0.75rem; display: inline-block; 
            line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; }
        .bg-warning { background-color: var(--warning-light) !important; color: #744210 !important; }
        .bg-info { background-color: var(--info-light) !important; color: #2c5282 !important; }
        .bg-success { background-color: var(--success-light) !important; color: #22543d !important; }
        .bg-danger { background-color: var(--danger-light) !important; color: #822727 !important; }
        .alert { border-radius: var(--radius-sm); padding: 1rem 1.25rem; margin-bottom: 1.5rem; border: none; position: relative; }
        .alert-success { background-color: #c6f6d5; color: #22543d; }
        .alert-danger { background-color: #fed7d7; color: #822727; }
        .alert-warning { background-color: #feebc8; color: #744210; }
        .alert-info { background-color: #e6fffa; color: #234e52; }
        .row { display: flex; flex-wrap: wrap; margin-right: -0.75rem; margin-left: -0.75rem; }
        [class*="col-"] { position: relative; width: 100%; padding-right: 0.75rem; padding-left: 0.75rem; }
        .mb-3 { margin-bottom: 1rem; }
        .mt-4 { margin-top: 1.5rem; }
        @media (min-width: 576px) { .col-sm-6 { flex: 0 0 50%; max-width: 50%; } }
        @media (min-width: 768px) { .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; } .col-md-6 { flex: 0 0 50%; max-width: 50%; } }
        @media (max-width: 768px) { .container { padding: 1rem; } h1 { font-size: 1.75rem; } .card-body, .booking-form { padding: 1.25rem; } 
        .input-group-text { font-size: 0.85rem; padding: 0.5rem; } .col-md-4 { margin-bottom: 0.75rem; } .table th, .table td { padding: 0.5rem; } }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
        @media print { body { background-color: white; } .container { max-width: 100%; padding: 0; } 
        .card, .booking-form, .previous-bookings { box-shadow: none; border: 1px solid #ddd; } .btn { display: none; } }
     </style>
</head>
<body>
    <div class="container">
        <h1>Student Laundry Services</h1>
        <?php if(!empty($message)): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if(!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <div class="quota-info">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Your Laundry Quota</h5>
                    <p>Academic Year: <?php echo $academicYear; ?></p>
                    <p>Hostel: <?php echo $studentHostel; ?></p>
                    <p>Bookings Used: <?php echo $quota['bookings_used']; ?> of <?php echo $quota['max_bookings']; ?></p>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" 
                            style="width: <?php echo (($quota['bookings_used'] / $quota['max_bookings']) * 100) . '%'; ?>"
                            aria-valuenow="<?php echo $quota['bookings_used']; ?>" 
                            aria-valuemin="0" 
                            aria-valuemax="<?php echo $quota['max_bookings']; ?>">
                            <?php echo round(($quota['bookings_used'] / $quota['max_bookings']) * 100) . '%'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (isset($booking) && isset($bookingItems)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Booking #<?php echo $booking['booking_id']; ?> Details</h3>
                </div>
                <div class="card-body">
                    <p><strong>Date Booked:</strong> <?php echo date('F j, Y, g:i a', strtotime($booking['booking_date'])); ?></p>
                    <p><strong>Pickup Date:</strong> <?php echo date('F j, Y', strtotime($booking['pickup_date'])); ?></p>
                    <p><strong>Hostel:</strong> <?php echo $booking['hostel']; ?></p>
                    <p><strong>Staff Incharge:</strong> <?php echo $booking['staff_name'] ? $booking['staff_name'] : 'Not assigned'; ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-<?php 
                        echo $booking['status'] == 'Pending' ? 'warning' : 
                            ($booking['status'] == 'Processing' ? 'info' : 
                                ($booking['status'] == 'Completed' ? 'success' : 'danger')); 
                    ?>"><?php echo $booking['status']; ?></span></p>
                    <p><strong>Total Weight:</strong> <?php echo number_format($booking['total_weight_grams']/1000, 2); ?> kg</p>
                    <?php if (!empty($booking['notes'])): ?>
                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($booking['notes']); ?></p>
                    <?php endif; ?>
                    <h4>Items</h4>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Weight</th>
                                <th>Subtotal Weight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookingItems as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo number_format($item['weight_grams']/1000, 3); ?> kg</td>
                                    <td><?php echo number_format(($item['weight_grams'] * $item['quantity'])/1000, 3); ?> kg</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($booking['status'] == 'Pending'): ?>
                        <a href="laundry.php?cancel_booking=<?php echo $booking['booking_id']; ?>" class="btn btn-danger">Cancel Booking</a>
                    <?php endif; ?>
                    <a href="laundry.php" class="btn btn-primary">Back to Laundry</a>
                </div>
            </div>
        <?php else: ?>
            <div class="booking-form">
                <h3>Create New Laundry Booking</h3>
                <p class="alert alert-info">Your laundry will be scheduled for pickup tomorrow.</p>
                <?php if ($quota['bookings_used'] >= $quota['max_bookings']): ?>
                    <div class="alert alert-warning">You have reached your maximum laundry bookings for this academic year.</div>
                <?php elseif ($studentHostel == "Unknown"): ?>
                    <div class="alert alert-warning">We couldn't determine your hostel. Please update your profile or contact the admin.</div>
                <?php else: ?>
                    <form method="post" action="laundry.php" id="laundryForm">
                        <h4>Select Items</h4>
                        <div class="row">
                            <?php foreach ($laundryItems as $item): ?>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo number_format($item['weight_grams']/1000, 3); ?> kg)</span>
                                        <input type="number" class="form-control item-quantity" 
                                               name="quantity[<?php echo $item['item_id']; ?>]" 
                                               min="0" value="0"
                                               data-weight="<?php echo $item['weight_grams']; ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="weight-calculator">
                            <p>Total Weight: <span id="totalWeight">0</span> kg <span id="weightWarning" class="text-danger"></span></p>
                        </div>
                        <div class="form-group">
                            <label for="notes" class="form-label">Special Instructions/Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional: Any special requests or notes for your laundry"></textarea>
                        </div>
                        <button type="submit" name="submit_booking" class="btn btn-primary">Submit Booking</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="previous-bookings">
                <h3>Your Previous Bookings</h3>
                <?php if (empty($previousBookings)): ?>
                    <p>You have no previous bookings.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Date</th>
                                    <th>Pickup Date</th>
                                    <th>Status</th>
                                    <th>Hostel</th>
                                    <th>Staff Incharge</th>
                                    <th>Items</th>
                                    <th>Weight</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previousBookings as $booking): ?>
                                    <tr>
                                        <td><?php echo $booking['booking_id']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($booking['pickup_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['status'] == 'Pending' ? 'warning' : 
                                                    ($booking['status'] == 'Processing' ? 'info' : 
                                                        ($booking['status'] == 'Completed' ? 'success' : 'danger')); 
                                            ?>"><?php echo $booking['status']; ?></span>
                                        </td>
                                        <td><?php echo $booking['hostel']; ?></td>
                                        <td><?php echo $booking['staff_name'] ? $booking['staff_name'] : 'Not assigned'; ?></td>
                                        <td><?php echo $booking['total_items']; ?> (<?php echo $booking['item_count']; ?> types)</td>
                                        <td><?php echo number_format($booking['total_weight_grams']/1000, 2); ?> kg</td>
                                        <td>
                                            <a href="laundry.php?view_booking=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <?php if ($booking['status'] == 'Pending'): ?>
                                                <a href="laundry.php?cancel_booking=<?php echo $booking['booking_id']; ?>" class="btn btn-sm btn-danger">Cancel</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const itemQuantities = document.querySelectorAll('.item-quantity');
            const totalWeightElement = document.getElementById('totalWeight');
            const weightWarningElement = document.getElementById('weightWarning');
            const maxWeightGrams = 5000; 
            function updateTotalWeight() {
                let totalWeight = 0;
                itemQuantities.forEach(function(input) {
                    const quantity = parseInt(input.value) || 0;
                    const weight = parseInt(input.getAttribute('data-weight'));
                    totalWeight += quantity * weight;
                });
                const totalWeightKg = totalWeight / 1000;
                totalWeightElement.textContent = totalWeightKg.toFixed(2);
                if (totalWeight > maxWeightGrams) {weightWarningElement.textContent = '(Exceeds maximum of 5kg)';} 
                else {weightWarningElement.textContent = '';}
            }
            itemQuantities.forEach(function(input) {
                input.addEventListener('change', updateTotalWeight);
                input.addEventListener('input', updateTotalWeight);
            });
            updateTotalWeight();
        });
    </script>
</body>
</html>