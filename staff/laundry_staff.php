<?php
session_start();
require_once 'staff_db.php'; 
if (!isset($_SESSION['staff_id'])) {
    header("Location: staff_test_login.php");
    exit();
}
$staffId = $_SESSION['staff_id'];
$staffName = $_SESSION['name'];
$message = "";
$error = "";
function getStaffHostels($conn, $staffId) {
    $sql = "SELECT hostel FROM staff WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return explode(', ', $row['hostel']);
    }
    return [];
}
$assignedHostels = getStaffHostels($conn, $staffId);
function getCurrentAcademicYear() {
    $month = date('n');
    $year = date('Y');
    if ($month >= 8) { return $year . "-" . ($year + 1);} 
    else {return ($year - 1) . "-" . $year;}
}
$academicYear = getCurrentAcademicYear();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $bookingId = $_POST['booking_id'];
    $newStatus = $_POST['new_status'];
    $sql = "SELECT * FROM laundry_bookings WHERE booking_id = ? AND staff_incharge_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $bookingId, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $sql = "UPDATE laundry_bookings SET status = ? WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $newStatus, $bookingId);
        
        if ($stmt->execute()) {$message = "Booking #$bookingId status updated to $newStatus successfully.";} 
        else {$error = "Failed to update booking status.";}
    } else {$error = "You don't have permission to update this booking.";}
}
$viewingBookingDetails = false;
$bookingDetails = null;
$bookingItems = [];
if (isset($_GET['view_booking']) && is_numeric($_GET['view_booking'])) {
    $bookingId = $_GET['view_booking'];
    $sql = "SELECT lb.*, ss.firstName, ss.lastName, ss.contact, ss.email, 
                  sd.room_number, sd.hostel
           FROM laundry_bookings lb
           JOIN student_signup ss ON lb.regNo = ss.regNo
           LEFT JOIN student_details sd ON lb.regNo = sd.reg_no
           WHERE lb.booking_id = ? AND lb.staff_incharge_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $bookingId, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $viewingBookingDetails = true;
        $bookingDetails = $result->fetch_assoc();
        $sql = "SELECT lbi.*, li.item_name, li.weight_grams
                FROM laundry_booking_items lbi
                JOIN laundry_items li ON lbi.item_id = li.item_id
                WHERE lbi.booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {$bookingItems[] = $row;}
    } else {$error = "Booking not found or you don't have permission to view it.";}
}
$bookings = [];
if (!empty($assignedHostels)) {
    $sql = "SELECT lb.*, lb.hostel,
                  ss.firstName, ss.lastName, 
                  COUNT(lbi.booking_item_id) as item_count, 
                  SUM(lbi.quantity) as total_items
           FROM laundry_bookings lb
           JOIN student_signup ss ON lb.regNo = ss.regNo
           LEFT JOIN laundry_booking_items lbi ON lb.booking_id = lbi.booking_id
           WHERE lb.staff_incharge_id = ?
           GROUP BY lb.booking_id
           ORDER BY 
               CASE 
                   WHEN lb.status = 'Pending' THEN 1
                   WHEN lb.status = 'Processing' THEN 2
                   WHEN lb.status = 'Completed' THEN 3
                   WHEN lb.status = 'Cancelled' THEN 4
               END, 
               lb.booking_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {$bookings[] = $row;}
}
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$filteredBookings = [];
if ($statusFilter !== 'all') {foreach ($bookings as $booking) {if ($booking['status'] === $statusFilter) {$filteredBookings[] = $booking;}}} 
else {$filteredBookings = $bookings;}
$pendingCount = 0;
$processingCount = 0;
$completedCount = 0;
$cancelledCount = 0;

foreach ($bookings as $booking) {
    switch ($booking['status']) {
        case 'Pending':
            $pendingCount++;
            break;
        case 'Processing':
            $processingCount++;
            break;
        case 'Completed':
            $completedCount++;
            break;
        case 'Cancelled':
            $cancelledCount++;
            break;
    }
}
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$searchedBookings = [];
if (!empty($searchQuery)) {
    foreach ($filteredBookings as $booking) {
        if (
            strpos($booking['booking_id'], $searchQuery) !== false ||
            strpos($booking['regNo'], $searchQuery) !== false ||
            strpos(strtolower($booking['firstName'] . ' ' . $booking['lastName']), strtolower($searchQuery)) !== false
        ) {$searchedBookings[] = $booking;}
    }
} else {$searchedBookings = $filteredBookings;}
if (isset($_GET['get_booking_items']) && is_numeric($_GET['get_booking_items'])) {
    $bookingId = $_GET['get_booking_items'];
    $sql = "SELECT lbi.*, li.item_name, li.weight_grams
            FROM laundry_booking_items lbi
            JOIN laundry_items li ON lbi.item_id = li.item_id
            WHERE lbi.booking_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {$items[] = $row;}
    header('Content-Type: application/json');
    echo json_encode($items);
    exit();
}
if (isset($_GET['get_booking_details']) && is_numeric($_GET['get_booking_details'])) {
    $bookingId = $_GET['get_booking_details'];
    $sql = "SELECT lb.*, ss.firstName, ss.lastName, ss.contact, ss.email, 
                  sd.room_number, sd.hostel
           FROM laundry_bookings lb
           JOIN student_signup ss ON lb.regNo = ss.regNo
           LEFT JOIN student_details sd ON lb.regNo = sd.reg_no
           WHERE lb.booking_id = ? AND lb.staff_incharge_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $bookingId, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $bookingDetails = $result->fetch_assoc();
                $sql = "SELECT lbi.*, li.item_name, li.weight_grams
                FROM laundry_booking_items lbi
                JOIN laundry_items li ON lbi.item_id = li.item_id
                WHERE lbi.booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bookingItems = [];
        while ($row = $result->fetch_assoc()) {$bookingItems[] = $row;}
        $response = [
            'success' => true,
            'booking' => $bookingDetails,
            'items' => $bookingItems
        ];
    } else {
        $response = [
            'success' => false,
            'message' => "Booking not found or you don't have permission to view it."
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Laundry Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="css/laundry.css"> -->
    <style>
        #bookingDetails {display: <?php echo $viewingBookingDetails ? 'block' : 'none'; ?>;margin-top: 30px;padding: 20px;
            border: 1px solid #ddd;border-radius: 5px;background-color: #f9f9f9;}
        #bookingsList {display: <?php echo $viewingBookingDetails ? 'none' : 'block'; ?>;}
        .status-badge {font-size: 0.9em;padding: 5px 10px;}
        .loading-spinner {text-align: center;padding: 20px;}
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Staff Laundry Management</h1>
            <div>
                <button onclick="return false;" class="btn btn-outline-secondary">Dashboard</button>
                <button onclick="return false;" class="btn btn-outline-danger">Logout</button>
            </div>
        </div>
        <div class="alert alert-info">
            <strong>Welcome, <?php echo htmlspecialchars($staffName); ?> (ID: <?php echo htmlspecialchars($staffId); ?>)</strong>
            <p>You are responsible for the following hostels: <?php echo implode(', ', $assignedHostels); ?></p>
        </div>
        <?php if(!empty($message)): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if(!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <div class="d-flex mb-3">
            <a href="?status=all" class="btn <?php echo $statusFilter == 'all' ? 'btn-dark' : 'btn-outline-dark'; ?> mr-2">
                All <span class="badge badge-secondary"><?php echo count($bookings); ?></span></a>
            <a href="?status=Pending" class="btn <?php echo $statusFilter == 'Pending' ? 'btn-warning' : 'btn-outline-warning'; ?> mr-2">
                Pending <span class="badge badge-secondary"><?php echo $pendingCount; ?></span></a>
            <a href="?status=Processing" class="btn <?php echo $statusFilter == 'Processing' ? 'btn-info' : 'btn-outline-info'; ?> mr-2">
                Processing <span class="badge badge-secondary"><?php echo $processingCount; ?></span></a>
            <a href="?status=Completed" class="btn <?php echo $statusFilter == 'Completed' ? 'btn-success' : 'btn-outline-success'; ?> mr-2">
                Completed <span class="badge badge-secondary"><?php echo $completedCount; ?></span></a>
            <a href="?status=Cancelled" class="btn <?php echo $statusFilter == 'Cancelled' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                Cancelled <span class="badge badge-secondary"><?php echo $cancelledCount; ?></span></a>
        </div>
        <div class="mb-4">
            <form action="" method="GET" id="searchForm">
                <?php if ($statusFilter != 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
                <?php endif; ?>
                <div class="d-flex">
                    <input type="text" id="searchInput" name="search" class="form-control mr-2" placeholder="Search by ID, Reg No, or Name" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn btn-primary mr-2">Search</button>
                    <a href="<?php echo $statusFilter != 'all' ? '?status=' . $statusFilter : ''; ?>" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
        <div id="bookingsList">
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header"><h3>Laundry Bookings Dashboard</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-warning text-dark mb-3">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Pending</h5>
                                            <p class="h3"><?php echo $pendingCount; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white mb-3">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Processing</h5>
                                            <p class="h3"><?php echo $processingCount; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white mb-3">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Completed</h5>
                                            <p class="h3"><?php echo $completedCount; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-secondary text-white mb-3">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Cancelled</h5>
                                            <p class="h3"><?php echo $cancelledCount; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (empty($searchedBookings)): ?><div class="alert alert-info">No bookings found.</div><?php else: ?>
                <div class="table-responsive">
                    <table id="bookingsTable" class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Booking ID</th> <th>Student</th> <th>Reg No</th> <th>Hostel</th>
                                <th>Booking Date</th> <th>Pickup Date</th> <th>Items</th>
                                <th>Weight</th> <th>Status</th> <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchedBookings as $booking): ?>
                                <tr data-booking-id="<?php echo $booking['booking_id']; ?>">
                                    <td><?php echo $booking['booking_id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['firstName'] . ' ' . $booking['lastName']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['regNo']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['hostel']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($booking['pickup_date'])); ?></td>
                                    <td><?php echo $booking['total_items']; ?> (<?php echo $booking['item_count']; ?> types)</td>
                                    <td><?php echo number_format($booking['total_weight_grams']/1000, 2); ?> kg</td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $booking['status'] == 'Pending' ? 'warning' : 
                                                ($booking['status'] == 'Processing' ? 'info' : 
                                                    ($booking['status'] == 'Completed' ? 'success' : 'secondary')); 
                                        ?>"><?php echo $booking['status']; ?></span>
                                    </td>
                                    <td>
                                        <button onclick="viewBookingDetails(<?php echo $booking['booking_id']; ?>)" class="btn btn-sm btn-primary">View</button>
                                        <?php if ($booking['status'] == 'Pending'): ?>
                                            <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'Processing')" class="btn btn-sm btn-info">Start Processing</button>
                                        <?php elseif ($booking['status'] == 'Processing'): ?>
                                            <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'Completed')" class="btn btn-sm btn-success">Mark Completed</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div id="bookingDetails">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Booking Details</h2>
                <button id="backToListBtn" class="btn btn-primary">Back to List</button>
            </div>
            <div id="bookingDetailsContent">
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Loading booking details...</p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateBookingStatus(bookingId, newStatus) {
            if (!confirm(`Are you sure you want to update booking #${bookingId} status to ${newStatus}?`)) {return;}
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const bookingIdInput = document.createElement('input');
            bookingIdInput.type = 'hidden';
            bookingIdInput.name = 'booking_id';
            bookingIdInput.value = bookingId;
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'new_status';
            statusInput.value = newStatus;
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'update_status';
            submitInput.value = '1';
            form.appendChild(bookingIdInput);
            form.appendChild(statusInput);
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        }
        function viewBookingDetails(bookingId) {
            $('#bookingsList').hide();
            $('#bookingDetails').show();
            $('#bookingDetailsContent').html(`
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Loading booking details...</p>
                </div>
            `);
            $.ajax({
                url: `?get_booking_details=${bookingId}`,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const booking = response.booking;
                        const items = response.items;
                        let detailsHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Booking Information</h4>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Booking ID</th>
                                            <td>${booking.booking_id}</td>
                                        </tr>
                                        <tr>
                                            <th>Booking Date</th>
                                            <td>${formatDate(booking.booking_date)}</td>
                                        </tr>
                                        <tr>
                                            <th>Pickup Date</th>
                                            <td>${formatDate(booking.pickup_date)}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                <span class="badge badge-${getStatusBadgeClass(booking.status)}">${booking.status}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Total Weight</th>
                                            <td>${(booking.total_weight_grams / 1000).toFixed(2)} kg</td>
                                        </tr>
                                        <tr>
                                            <th>Notes</th>
                                            <td>${booking.notes || 'No notes'}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h4>Student Information</h4>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Name</th>
                                            <td>${booking.firstName} ${booking.lastName}</td>
                                        </tr>
                                        <tr>
                                            <th>Registration No.</th>
                                            <td>${booking.regNo}</td>
                                        </tr>
                                        <tr>
                                            <th>Hostel</th>
                                            <td>${booking.hostel}</td>
                                        </tr>
                                        <tr>
                                            <th>Room Number</th>
                                            <td>${booking.room_number || 'Not specified'}</td>
                                        </tr>
                                        <tr>
                                            <th>Contact</th>
                                            <td>${booking.contact || 'Not provided'}</td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td>${booking.email || 'Not provided'}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <h4>Laundry Items</h4>
                        `;
                        if (items.length === 0) {
                            detailsHTML += `<div class="alert alert-info">No items found for this booking.</div>`;
                        } else {
                            detailsHTML += `
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Weight per Item</th>
                                            <th>Total Weight</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                            items.forEach(function(item) {
                                detailsHTML += `
                                    <tr>
                                        <td>${item.item_name}</td>
                                        <td>${item.quantity}</td>
                                        <td>${(item.weight_grams / 1000).toFixed(3)} kg</td>
                                        <td>${((item.weight_grams * item.quantity) / 1000).toFixed(3)} kg</td>
                                    </tr> `;
                            });
                            detailsHTML += `
                                    </tbody>
                                </table>
                            `;
                        }
                        if (booking.status !== 'Completed' && booking.status !== 'Cancelled') {
                            detailsHTML += `
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5>Update Booking Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <input type="hidden" name="booking_id" value="${booking.booking_id}">
                                            <div class="form-group mb-3">
                                                <label for="new_status">New Status:</label>
                                                <select class="form-control" id="new_status" name="new_status">
                                                    <option value="Pending" ${booking.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                    <option value="Processing" ${booking.status === 'Processing' ? 'selected' : ''}>Processing</option>
                                                    <option value="Completed">Completed</option>
                                                    <option value="Cancelled">Cancelled</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="update_status" value="1" class="btn btn-primary">Update Status</button>
                                        </form>
                                    </div>
                                </div>
                            `;
                        }
                        $('#bookingDetailsContent').html(detailsHTML);
                    } else {
                        $('#bookingDetailsContent').html(`
                            <div class="alert alert-danger">${response.message}</div>
                        `);
                    }
                },
                error: function() {
                    $('#bookingDetailsContent').html(`
                        <div class="alert alert-danger">Failed to load booking details. Please try again.</div>
                    `);
                }
            });
        }
        function getStatusBadgeClass(status) {
            switch (status) {
                case 'Pending':
                    return 'warning';
                case 'Processing':
                    return 'info';
                case 'Completed':
                    return 'success';
                case 'Cancelled':
                    return 'secondary';
                default:
                    return 'primary';
            }
        }
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { month: 'short', day: 'numeric', year: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
        $('#backToListBtn').on('click', function() {
            $('#bookingDetails').hide();
            $('#bookingsList').show();
        });
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {document.getElementById('searchForm').submit();}
        });
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {document.getElementById('searchForm').submit();}
        });
        const searchQuery = '<?php echo addslashes($searchQuery); ?>';
        if (searchQuery) {
            const tableRows = document.querySelectorAll('#bookingsTable tbody tr');
            tableRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    if (!cell.innerHTML.includes('button') && !cell.innerHTML.includes('span class="badge')) {
                        const content = cell.innerHTML;
                        const regex = new RegExp(searchQuery, 'gi');
                        cell.innerHTML = content.replace(regex, match => `<mark>${match}</mark>`);
                    }
                });
            });
        }
        $(function () {$('[data-toggle="tooltip"]').tooltip();});
        function refreshPendingBookings() {
            const statusFilter = '<?php echo $statusFilter; ?>';
            if (statusFilter === 'Pending' || statusFilter === 'Processing' || statusFilter === 'all') {
                setTimeout(function() {window.location.reload();}, 60000);
            }
        }
        $(document).ready(function() {refreshPendingBookings();});
    </script>
</body>
</html>