<?php
include('db.php');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Get user information from session
$user = $_SESSION['user'];
$userEmail = $user['email'];

// Function to get all fee dues for a user
function getUserFeeDues($conn, $userEmail) {
    $sql = "SELECT fd.*, rb.hostel_name, rb.room_number, rb.sharing_type, rb.is_ac 
            FROM fee_dues fd 
            JOIN room_bookings rb ON fd.booking_id = rb.id 
            WHERE fd.user_email = ? AND fd.amount_due > 0
            ORDER BY fd.due_date ASC";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $dues = [];
        
        while ($row = $result->fetch_assoc()) {
            $dues[] = $row;
        }
        
        $stmt->close();
        return $dues;
    } else {
        return false;
    }
}

// Get user's fee dues
$feeDues = getUserFeeDues($conn, $userEmail);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Dues Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: Arial, sans-serif; }
        .container { padding-top: 20px; padding-bottom: 30px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 5px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { padding: 15px 20px; font-weight: bold; background-color: #007bff; color: white; }
        .table th, .table td { padding: 12px; vertical-align: middle; }
        .badge { padding: 5px 10px; font-weight: normal; }
        .btn-primary { background-color: #007bff; border: none; padding: 5px 10px; }
        .btn-primary:hover { background-color: #0056b3; }
        .sidebar { background-color: #343a40; min-height: 100vh; color: white; padding: 20px 0; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; margin: 5px 0; border-radius: 5px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: white; background-color: rgba(255,255,255,0.1); }
        .sidebar .nav-link i { margin-right: 10px; }
        .content { padding-left: 30px; padding-right: 30px; }
        .page-title { margin-bottom: 20px; font-weight: bold; color: #343a40; display: flex; align-items: center; }
        .page-title i { background-color: #007bff; color: white; padding: 8px; border-radius: 5px; margin-right: 10px; }
        .no-dues-container { text-align: center; padding: 40px 20px; }
        .no-dues-icon { font-size: 4rem; color: #28a745; margin-bottom: 15px; }
        .no-dues-message { font-size: 1.3rem; font-weight: bold; color: #28a745; margin-bottom: 10px; }
        .no-dues-subtext { color: #6c757d; font-size: 1rem; }
        .table-striped > tbody > tr:nth-of-type(odd) { background-color: rgba(0, 123, 255, 0.1); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="room_booking.php"><i class="fas fa-bed"></i> Room Booking</a></li>
                    <li class="nav-item"><a class="nav-link active" href="fee_dues.php"><i class="fas fa-rupee-sign"></i> Fee Dues</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="complaints.php"><i class="fas fa-ticket-alt"></i> Complaints</a></li>
                    <li class="nav-item"><a class="nav-link" href="noticeboard.php"><i class="fas fa-bell"></i> Notices</a></li>
                    <li class="nav-item"><a class="nav-link" href="facilities.php"><i class="fas fa-building"></i> Facilities</a></li>
                </ul>
            </div>
            
            <!-- Main content -->
            <div class="col-md-9 col-lg-10 content">
                <!-- Show success or error messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <h2 class="page-title mt-4"><i class="fas fa-rupee-sign"></i> Fee Dues Management</h2>
                
                <div class="card">
                    <div class="card-header">Fee Dues Status</div>
                    <div class="card-body">
                        <?php if ($feeDues && count($feeDues) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Hostel</th>
                                            <th>Room</th>
                                            <th>Due Date</th>
                                            <th>Total Fee</th>
                                            <th>Paid Amount</th>
                                            <th>Due Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feeDues as $due): ?>
                                            <tr class="<?php echo $due['status'] == 'overdue' ? 'table-danger' : ($due['status'] == 'paid' ? 'table-success' : ''); ?>">
                                                <td><?php echo htmlspecialchars($due['hostel_name']); ?></td>
                                                <td><?php echo htmlspecialchars($due['room_number']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($due['due_date'])); ?></td>
                                                <td>₹<?php echo number_format($due['total_fee'], 2); ?></td>
                                                <td>₹<?php echo number_format($due['amount_paid'], 2); ?></td>
                                                <td>₹<?php echo number_format($due['amount_due'], 2); ?></td>
                                                <td>
                                                    <?php if ($due['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($due['status'] == 'paid'): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php elseif ($due['status'] == 'overdue'): ?>
                                                        <span class="badge bg-danger">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($due['status'] != 'paid'): ?>
                                                        <a href="due_payment.php?due_id=<?php echo $due['id']; ?>" class="btn btn-primary btn-sm">Pay Now</a>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" disabled>Paid</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-dues-container">
                                <div class="no-dues-icon"><i class="fas fa-check-circle"></i></div>
                                <div class="no-dues-message">No Pending Dues</div>
                                <div class="no-dues-subtext">You're all caught up! There are no pending fee payments.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>