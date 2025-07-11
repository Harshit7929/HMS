<?php
session_start();
require_once 'admin_db.php';
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();}
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'due_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
if (isset($_POST['update_payment'])) {
    $fee_id = $_POST['fee_id'];
    $amount_paid = $_POST['amount_paid'];
    $total_fee = $_POST['total_fee'];
    $amount_due = $total_fee - $amount_paid;    
    $status = 'pending';
    if ($amount_due <= 0) {
        $status = 'paid';
        $amount_due = 0; // Ensure no negative dues
    } else {
        $due_date = $_POST['due_date'];
        if (strtotime($due_date) < strtotime(date('Y-m-d'))) {$status = 'overdue';}
    }
    $update_query = "UPDATE fee_dues SET amount_paid = ?, amount_due = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ddsi", $amount_paid, $amount_due, $status, $fee_id);
    if ($stmt->execute()) {$success_message = "Payment updated successfully!";} 
    else {$error_message = "Error updating payment: " . $conn->error;}
}
$query = "SELECT fd.*, rb.hostel_name, rb.room_number,
          CONCAT(ss.firstName, ' ', ss.lastName) AS student_name,
          ss.contact
          FROM fee_dues fd
          INNER JOIN room_bookings rb ON fd.booking_id = rb.id
          INNER JOIN student_signup ss ON fd.user_email = ss.email
          WHERE 1=1";
if ($status_filter !== 'all') {$query .= " AND fd.status = '$status_filter'";}
if (!empty($search_term)) {
    $query .= " AND (fd.user_email LIKE '%$search_term%' OR ss.firstName LIKE '%$search_term%'
                OR ss.lastName LIKE '%$search_term%' OR CONCAT(ss.firstName, ' ', ss.lastName) LIKE '%$search_term%'
                OR rb.hostel_name LIKE '%$search_term%' OR rb.room_number LIKE '%$search_term%')";
}
$query .= " ORDER BY $sort_by $sort_order";
$result = $conn->query($query);
$all_emails = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {$all_emails[] = $row['user_email'];}
    $result->data_seek(0);
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees & Dues Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/fees_dues.css"> -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #2c3e50; color: #fff; padding-top: 20px; position: fixed; height: 100vh; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 5px; }
        .sidebar ul li a { color: #ecf0f1; text-decoration: none; display: block; padding: 12px 20px; transition: all 0.3s ease; }
        .sidebar ul li a:hover { background-color: #34495e; border-left: 4px solid #3498db; }
        .sidebar ul li a.active { background-color: #34495e; border-left: 4px solid #3498db; font-weight: 600; }
        .sidebar ul li a i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { flex: 1; padding: 20px; margin-left: 250px; }
        h1 { color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        h1 i { margin-right: 10px; color: #3498db; }
        .alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .summary-cards { display: flex; gap: 20px; margin-bottom: 30px; }
        .summary-card { flex: 1; background-color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); text-align: center; transition: transform 0.3s ease; }
        .summary-card:hover { transform: translateY(-5px); }
        .summary-card h3 { color: #7f8c8d; font-size: 16px; margin-bottom: 10px; }
        .summary-card .value { font-size: 24px; font-weight: 600; }
        .total-due { color: #e74c3c; }
        .total-paid { color: #27ae60; }
        .total-pending { color: #f39c12; }
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background-color: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); }
        .search-box input { padding: 10px 15px; width: 300px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .filter-options select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff; font-size: 14px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: #fff; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); border-radius: 8px; overflow: hidden; }
        thead { background-color: #f8f9fa; }
        th { padding: 15px 10px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; cursor: pointer; }
        th i { margin-left: 5px; color: #95a5a6; }
        td { padding: 12px 10px; border-bottom: 1px solid #e9ecef; }
        tr:hover { background-color: #f8f9fa; }
        .status-pill { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background-color: #fef9e7; color: #f39c12; }
        .status-paid { background-color: #eafaf1; color: #27ae60; }
        .status-overdue { background-color: #fdedec; color: #e74c3c; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-warning { background-color: #f39c12; color: white; }
        .btn-warning:hover { background-color: #e67e22; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons .btn { padding: 5px 10px; font-size: 12px; }
        .pagination { display: flex; justify-content: center; margin-top: 20px; }
        .pagination a { color: #2c3e50; padding: 8px 16px; text-decoration: none; border: 1px solid #ddd; margin: 0 4px; transition: all 0.3s ease; }
        .pagination a.active { background-color: #3498db; color: white; border: 1px solid #3498db; }
        .pagination a:hover:not(.active) { background-color: #f1f1f1; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); overflow: auto; }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 25px; border-radius: 8px; width: 50%; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); animation: modalopen 0.5s; }
        @keyframes modalopen { from {opacity: 0; transform: translateY(-50px);} to {opacity: 1; transform: translateY(0);} }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; }
        .close:hover { color: #2c3e50; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #2c3e50; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group input:disabled { background-color: #f8f9fa; cursor: not-allowed; }
        .modal h2 { color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        @media (max-width: 768px) {
        .container { flex-direction: column; }
        .sidebar { width: 100%; height: auto; position: relative; padding-bottom: 10px; }
        .main-content { margin-left: 0; }
        .summary-cards { flex-direction: column; }
        .filter-bar { flex-direction: column; gap: 10px; }
        .search-box input, .filter-options select { width: 100%; }
        .modal-content { width: 90%; }
        table { display: block; overflow-x: auto; }
        }
    </style>
    <script>
        function openOutlook(emails) {
            const subject = "Payment Reminder";
            const body = `Dear Student,
This is a friendly reminder that your payment for the hostel fee is due. Please ensure that the payment is made by the due date to avoid any penalties.
If you have already made the payment, please ignore this reminder. If you have any questions or need assistance, feel free to contact the administration office.
Thank you for your attention to this matter.
Best regards,
Hostel Administration`;
            const mailtoLink = `mailto:${emails.join(';')}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            window.location.href = mailtoLink;
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_students.php"><i class="fas fa-user-graduate"></i> Students</a></li>
                <li><a href="manage_rooms.php"><i class="fas fa-bed"></i> Rooms</a></li>
                <li><a href="fees_dues.php" class="active"><i class="fas fa-money-bill-wave"></i> Fees & Dues</a></li>
                <!-- <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li> -->
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        <div class="main-content">
            <h1><i class="fas fa-money-bill-wave"></i> Fees & Dues Management</h1>
            <?php if(isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php
            $stats_query = "SELECT 
                            SUM(amount_due) as total_due,
                            SUM(amount_paid) as total_paid,
                            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                            FROM fee_dues";
            $stats_result = $conn->query($stats_query);
            $stats = $stats_result->fetch_assoc();
            ?>
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Due</h3>
                    <div class="value total-due">₹<?php echo number_format($stats['total_due'], 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Paid</h3>
                    <div class="value total-paid">₹<?php echo number_format($stats['total_paid'], 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Pending Payments</h3>
                    <div class="value total-pending"><?php echo $stats['pending_count']; ?></div>
                </div>
            </div>
            <div class="filter-bar">
                <div class="search-box">
                    <form action="" method="GET">
                        <input type="text" name="search" placeholder="Search by name, email, hostel or room..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </form>
                </div>
                <div class="filter-options">
                    <select name="status" onchange="this.form.submit()" form="filter-form">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                    <form id="filter-form" action="" method="GET">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                    </form>
                </div>
            </div>
            <button class="btn btn-primary" onclick="openOutlook(<?php echo json_encode($all_emails); ?>)">Remind All</button>
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable('student_name')">Student Name <?php echo getSortIcon('student_name'); ?></th>
                        <th onclick="sortTable('user_email')">Email <?php echo getSortIcon('user_email'); ?></th>
                        <th onclick="sortTable('hostel_name')">Hostel/Room <?php echo getSortIcon('hostel_name'); ?></th>
                        <th onclick="sortTable('total_fee')">Total Fee <?php echo getSortIcon('total_fee'); ?></th>
                        <th onclick="sortTable('amount_paid')">Paid <?php echo getSortIcon('amount_paid'); ?></th>
                        <th onclick="sortTable('amount_due')">Due <?php echo getSortIcon('amount_due'); ?></th>
                        <th onclick="sortTable('due_date')">Due Date <?php echo getSortIcon('due_date'); ?></th>
                        <th onclick="sortTable('status')">Status <?php echo getSortIcon('status'); ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($row['hostel_name'] . ' - Room ' . $row['room_number']); ?></td>
                                <td>₹<?php echo number_format($row['total_fee'], 2); ?></td>
                                <td>₹<?php echo number_format($row['amount_paid'], 2); ?></td>
                                <td>₹<?php echo number_format($row['amount_due'], 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['due_date'])); ?></td>
                                <td>
                                    <span class="status-pill status-<?php echo $row['status']; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary" onclick="openUpdateModal(<?php 
                                        echo htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT)); 
                                    ?>)">Update</button>
                                    <button class="btn btn-warning" onclick="openOutlook(['<?php echo $row['user_email']; ?>'])">
                                        <i class="fas fa-bell"></i> Remind</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align: center;">No fee records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <!-- Simple pagination - in a real app, implement proper pagination -->
            <!-- <div class="pagination">
                <a href="#">&laquo;</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#">&raquo;</a>
            </div> -->
        </div>
    </div>
    <!-- Update Payment Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Update Payment</h2>
            <form method="POST" action="">
                <input type="hidden" id="fee_id" name="fee_id">
                <input type="hidden" id="due_date" name="due_date">
                <input type="hidden" id="total_fee" name="total_fee">
                <div class="form-group">
                    <label for="student_name">Student:</label>
                    <input type="text" id="student_name" disabled>
                </div>
                <div class="form-group">
                    <label for="hostel_room">Hostel/Room:</label>
                    <input type="text" id="hostel_room" disabled>
                </div>
                <div class="form-group">
                    <label for="total_fee_display">Total Fee:</label>
                    <input type="text" id="total_fee_display" disabled>
                </div>
                <div class="form-group">
                    <label for="current_paid">Current Amount Paid:</label>
                    <input type="text" id="current_paid" disabled>
                </div>
                <div class="form-group">
                    <label for="amount_paid">Update Amount Paid:</label>
                    <input type="number" step="0.01" id="amount_paid" name="amount_paid" required>
                </div>
                <button type="submit" name="update_payment" class="btn btn-primary">Update Payment</button>
            </form>
        </div>
    </div>
    <script>
        // Function to open the update modal
        function openUpdateModal(data) {
            document.getElementById("fee_id").value = data.id;
            document.getElementById("student_name").value = data.student_name;
            document.getElementById("hostel_room").value = data.hostel_name + " - Room " + data.room_number;
            document.getElementById("total_fee").value = data.total_fee;
            document.getElementById("total_fee_display").value = "₹" + parseFloat(data.total_fee).toFixed(2);
            document.getElementById("current_paid").value = "₹" + parseFloat(data.amount_paid).toFixed(2);
            document.getElementById("amount_paid").value = data.amount_paid;
            document.getElementById("due_date").value = data.due_date;
            document.getElementById("updateModal").style.display = "block";
        }
        function closeModal() {document.getElementById("updateModal").style.display = "none";}
        window.onclick = function(event) {if (event.target == document.getElementById("updateModal")) {closeModal();}}
        function sortTable(column) {
            let currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.get('sort') === column) {
                const currentOrder = currentUrl.searchParams.get('order');
                currentUrl.searchParams.set('order', currentOrder === 'ASC' ? 'DESC' : 'ASC');
            } else {
                currentUrl.searchParams.set('sort', column);
                currentUrl.searchParams.set('order', 'ASC');
            }
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>
<?php
function getSortIcon($column) {
    global $sort_by, $sort_order;
    if ($sort_by === $column) {return $sort_order === 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';}
    return '<i class="fas fa-sort"></i>';
}
?>