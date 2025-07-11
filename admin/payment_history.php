<?php 
include 'admin_db.php';
$sql = "SELECT pd.id, pd.user_email, pd.amount, pd.payment_method, pd.payment_status, 
               pd.transaction_id, pd.created_at, 
               pd.card_number, pd.card_expiry, pd.card_cvc, pd.upi_id, pd.bank_name, pd.account_number, pd.ifsc_code,
               ss.firstName, ss.lastName, ss.regNo as student_id         
        FROM payment_details pd         
        JOIN student_signup ss ON pd.user_email = ss.email";
$where_conditions = [];
$filter_applied = false;
if(isset($_GET['filter'])) {
    $filter_applied = true;
    if(!empty($_GET['payment_status'])) {
        $status = $conn->real_escape_string($_GET['payment_status']);
        $where_conditions[] = "pd.payment_status = '$status'";
    }
    if(!empty($_GET['payment_method'])) {
        $method = $conn->real_escape_string($_GET['payment_method']);
        $where_conditions[] = "pd.payment_method = '$method'";
    }
    if(!empty($_GET['date_from'])) {
        $date_from = $conn->real_escape_string($_GET['date_from']);
        $where_conditions[] = "pd.created_at >= '$date_from 00:00:00'";
    }
    if(!empty($_GET['date_to'])) {
        $date_to = $conn->real_escape_string($_GET['date_to']);
        $where_conditions[] = "pd.created_at <= '$date_to 23:59:59'";
    }
    if(!empty($_GET['search'])) {
        $search = $conn->real_escape_string($_GET['search']);
        $where_conditions[] = "(pd.transaction_id LIKE '%$search%' OR ss.firstName LIKE '%$search%' OR ss.lastName LIKE '%$search%' OR pd.user_email LIKE '%$search%' OR ss.regNo LIKE '%$search%')";
    }
}
if(!empty($where_conditions)) {$sql .= " WHERE " . implode(" AND ", $where_conditions);}
$sql .= " ORDER BY pd.created_at DESC";
$result = $conn->query($sql);
$methods_query = "SELECT DISTINCT payment_method FROM payment_details ORDER BY payment_method";
$methods_result = $conn->query($methods_query);
$status_query = "SELECT DISTINCT payment_status FROM payment_details ORDER BY payment_status";
$status_result = $conn->query($status_query);
$totals_sql = "SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN pd.payment_status = 'Completed' THEN pd.amount ELSE 0 END) as total_completed,
    SUM(CASE WHEN pd.payment_status = 'Pending' THEN pd.amount ELSE 0 END) as total_pending,
    SUM(CASE WHEN pd.payment_status = 'Failed' THEN pd.amount ELSE 0 END) as total_failed,
    SUM(CASE WHEN pd.payment_status = 'Refunded' THEN pd.amount ELSE 0 END) as total_refunded
FROM payment_details pd";
if(!empty($where_conditions)) {$totals_sql .= " WHERE " . implode(" AND ", $where_conditions);}
$totals_result = $conn->query($totals_sql);
$totals = $totals_result->fetch_assoc();
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="payment_history_export_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');    
    echo '
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Student Name</th>
                <th>Student ID</th>
                <th>Email</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Status</th>
                <th>Transaction ID</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>';
    if ($result->num_rows > 0) {
        $result->data_seek(0);
        while($row = $result->fetch_assoc()) {
            echo '
            <tr>
                <td>' . $row['id'] . '</td>
                <td>' . htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . '</td>
                <td>' . htmlspecialchars($row['student_id']) . '</td>
                <td>' . htmlspecialchars($row['user_email']) . '</td>
                <td>₹' . number_format($row['amount'], 2) . '</td>
                <td>' . htmlspecialchars($row['payment_method']) . '</td>
                <td>' . htmlspecialchars($row['payment_status']) . '</td>
                <td>' . htmlspecialchars($row['transaction_id']) . '</td>
                <td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>
            </tr>';
        }
    }
    echo '
        </tbody>
    </table>';
    exit; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/payment_history.css"> -->
     <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f8fa; color: #333; display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #2c3e50; color: #fff; position: fixed; height: 100%; overflow-y: auto; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); z-index: 100; transition: all 0.3s; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar a { display: flex; align-items: center; padding: 15px 20px; color: #ddd; text-decoration: none; transition: all 0.3s; border-left: 3px solid transparent; }
        .sidebar a i { margin-right: 10px; width: 20px; text-align: center; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.1); color: #fff; border-left-color: #3498db; }
        .sidebar a.active { background-color: rgba(255, 255, 255, 0.1); color: #fff; border-left-color: #3498db; font-weight: 600; }
        .container { flex: 1; margin-left: 250px; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0; }
        .page-title { font-size: 24px; color: #2c3e50; font-weight: 600; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; font-weight: 500; transition: all 0.3s; }
        .btn i { margin-right: 8px; }
        .btn-primary { background-color: #3498db; color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-secondary { background-color: #95a5a6; color: white; }
        .btn-secondary:hover { background-color: #7f8c8d; }
        .export-btn { background-color: #27ae60; color: white; }
        .export-btn:hover { background-color: #219653; }
        .btn-container { display: flex; gap: 10px; margin-top: 15px; }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background-color: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); text-align: center; border-top: 4px solid #3498db; }
        .stat-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; font-weight: 500; }
        .stat-card .value { font-size: 24px; font-weight: 600; color: #2c3e50; }
        .stat-card.completed { border-top-color: #27ae60; }
        .stat-card.pending { border-top-color: #f39c12; }
        .stat-card.failed { border-top-color: #e74c3c; }
        .stat-card.refunded { border-top-color: #9b59b6; }
        .card { background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); margin-bottom: 25px; overflow: hidden; }
        .filter-section { margin-bottom: 25px; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #e0e0e0; font-weight: 600; display: flex; align-items: center; color: #2c3e50; }
        .card-header i { margin-right: 10px; color: #3498db; }
        .card-body { padding: 20px; }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #555; font-weight: 500; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; transition: border-color 0.3s; }
        .form-control:focus { border-color: #3498db; outline: none; }
        .applied-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .filter-tag { background-color: #f0f5fa; padding: 6px 12px; border-radius: 4px; font-size: 13px; color: #2c3e50; display: flex; align-items: center; }
        .filter-tag .remove { margin-left: 8px; color: #7f8c8d; text-decoration: none; font-weight: bold; font-size: 16px; }
        .filter-tag .remove:hover { color: #e74c3c; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        table th { background-color: #f9fafb; font-weight: 600; color: #2c3e50; font-size: 14px; }
        table tr:hover { background-color: #f5f8fa; }
        .text-center { text-align: center; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-success { background-color: #e6f7ee; color: #27ae60; }
        .badge-pending { background-color: #fef4e6; color: #f39c12; }
        .badge-danger { background-color: #fdeeee; color: #e74c3c; }
        .badge-warning { background-color: #f3e5f9; color: #9b59b6; }
        .actions-col { width: 80px; }
        .action-btn { width: 32px; height: 32px; border-radius: 4px; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; }
        .view-btn { background-color: #3498db; color: white; }
        .view-btn:hover { background-color: #2980b9; }
        .pagination { display: flex; justify-content: center; margin-top: 20px; }
        .pagination a { color: #2c3e50; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin: 0 4px; transition: all 0.3s; }
        .pagination a:hover { background-color: #f1f1f1; }
        .pagination a.active { background-color: #3498db; color: white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.4); overflow: auto; }
        .modal-content { background-color: white; margin: 10% auto; padding: 0; width: 80%; max-width: 600px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); animation: modalFadeIn 0.3s; }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h2 { font-size: 18px; color: #2c3e50; margin: 0; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; }
        .close:hover { color: #555; }
        .transaction-details { padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .transaction-details p { margin-bottom: 12px; font-size: 14px; }
        .transaction-details strong { color: #555; }
        @media (max-width: 992px) {
        .sidebar { width: 70px; overflow: hidden; }
        .sidebar a span { display: none; }
        .sidebar a i { margin-right: 0; font-size: 18px; }
        .sidebar-header h2 { font-size: 0; }
        .sidebar-header h2::first-letter { font-size: 24px; }
        .container { margin-left: 70px; }
        .stats-container { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        .transaction-details { grid-template-columns: 1fr; }}
        @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        .filter-form { grid-template-columns: 1fr; }
        .modal-content { width: 95%; margin: 5% auto; }}
        @media (max-width: 576px) {
        .stats-container { grid-template-columns: 1fr; }
        table { font-size: 13px; }
        .container { padding: 15px 10px; }}
     </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Admin Panel</h2>
        </div>
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="manage_students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a>
        <a href="manage_rooms.php"><i class="fas fa-door-open"></i> <span>Rooms</span></a>
        <a href="booking_list.php"><i class="fas fa-calendar-check"></i> <span>Bookings</span></a>
        <a href="payment_history.php" class="active"><i class="fas fa-money-bill-wave"></i> <span>Payment History</span></a>
        <!-- <a href="refund_requests.php"><i class="fas fa-undo-alt"></i> <span>Refund Requests</span></a>
        <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a> -->
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Payment History</h1>
            <div>
                <button class="btn export-btn" onclick="exportToExcel()">
                    <i class="fas fa-file-export"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="value"><?php echo number_format($totals['total_transactions']); ?></div>
            </div>
            <div class="stat-card completed">
                <h3>Completed Payments</h3>
                <div class="value">₹<?php echo number_format($totals['total_completed'], 2); ?></div>
            </div>
            <div class="stat-card pending">
                <h3>Pending Payments</h3>
                <div class="value">₹<?php echo number_format($totals['total_pending'], 2); ?></div>
            </div>
            <div class="stat-card failed">
                <h3>Failed Payments</h3>
                <div class="value">₹<?php echo number_format($totals['total_failed'], 2); ?></div>
            </div>
            <!-- <div class="stat-card refunded">
                <h3>Refunded Payments</h3>
                <div class="value">₹<?php echo number_format($totals['total_refunded'], 2); ?></div>
            </div> -->
        </div>
        <div class="filter-section">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filter Transactions
            </div>
            <div class="card-body">
                <?php if($filter_applied): ?>
                <div class="applied-filters">
                    <div class="filter-tag">
                        <strong>Filters Applied</strong>
                        <a href="payment_history.php" class="remove" title="Clear all filters">×</a>
                    </div>
                    <?php if(!empty($_GET['payment_status'])): ?>
                    <div class="filter-tag">Status: <?php echo htmlspecialchars($_GET['payment_status']); ?></div>
                    <?php endif; ?>
                    <?php if(!empty($_GET['payment_method'])): ?>
                    <div class="filter-tag">Method: <?php echo htmlspecialchars($_GET['payment_method']); ?></div>
                    <?php endif; ?>
                    <?php if(!empty($_GET['date_from'])): ?>
                    <div class="filter-tag">From: <?php echo htmlspecialchars($_GET['date_from']); ?></div>
                    <?php endif; ?>
                    <?php if(!empty($_GET['date_to'])): ?>
                    <div class="filter-tag">To: <?php echo htmlspecialchars($_GET['date_to']); ?></div>
                    <?php endif; ?>
                    <?php if(!empty($_GET['search'])): ?>
                    <div class="filter-tag">Search: <?php echo htmlspecialchars($_GET['search']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <form action="" method="GET" class="filter-form">
                    <input type="hidden" name="filter" value="1">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="ID, Name, Email, Transaction ID..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_status">Payment Status</label>
                        <select class="form-control" id="payment_status" name="payment_status">
                            <option value="">All Statuses</option>
                            <?php while($status = $status_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($status['payment_status']); ?>" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == $status['payment_status']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['payment_status']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="">All Methods</option>
                            <?php while($method = $methods_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($method['payment_method']); ?>" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] == $method['payment_method']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($method['payment_method']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                    </div>
                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                        <?php if($filter_applied): ?>
                        <a href="payment_history.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> Transaction List</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="transactionsTable">
                        <thead>
                            <tr>
                                <th>ID</th> <th>Student</th> <th>Student ID</th> <th>Email</th>
                                <th>Amount</th> <th>Method</th> <th>Status</th> <th>Transaction ID</th> <th>Date</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    $status_class = '';
                                    switch($row['payment_status']) {
                                        case 'Completed':
                                            $status_class = 'badge-success';
                                            break;
                                        case 'Pending':
                                            $status_class = 'badge-pending';
                                            break;
                                        case 'Failed':
                                            $status_class = 'badge-danger';
                                            break;
                                        case 'Refunded':
                                            $status_class = 'badge-warning';
                                            break;
                                        default:
                                            $status_class = '';
                                    }
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                                <td>₹<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['transaction_id']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                <td class="actions-col">
                                    <button class="action-btn view-btn" onclick="viewTransaction(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['firstName'] . ' ' . $row['lastName'])); ?>', '<?php echo addslashes(htmlspecialchars($row['user_email'])); ?>', '<?php echo addslashes(htmlspecialchars($row['student_id'])); ?>', '<?php echo $row['amount']; ?>', '<?php echo addslashes(htmlspecialchars($row['payment_method'])); ?>', '<?php echo addslashes(htmlspecialchars($row['payment_status'])); ?>', '<?php echo addslashes(htmlspecialchars($row['transaction_id'])); ?>', '<?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {echo "<tr><td colspan='10' class='text-center'>No payment history found.</td></tr>";}
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <a href="#">&laquo;</a>
                    <a href="#" class="active">1</a>
                    <a href="#">2</a>
                    <a href="#">3</a>
                    <a href="#">4</a>
                    <a href="#">5</a>
                    <a href="#">&raquo;</a>
                </div>
            </div>
        </div>
    </div>
    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h2>Transaction Details</h2>
            </div>
            <div id="transactionDetails" class="transaction-details"></div>
        </div>
    </div>
    <script>
        function viewTransaction(id, name, email, studentId, amount, method, status, transactionId, date) {
            document.getElementById('transactionModal').style.display = 'block';            
            let statusClass = '';
            switch(status) {
                case 'Completed':
                    statusClass = 'badge-success';
                    break;
                case 'Pending':
                    statusClass = 'badge-pending';
                    break;
                case 'Failed':
                    statusClass = 'badge-danger';
                    break;
                case 'Refunded':
                    statusClass = 'badge-warning';
                    break;
                default:
                    statusClass = '';
            }
            document.getElementById('transactionDetails').innerHTML = `
                <div>
                    <p><strong>Transaction ID:</strong> #${id}</p>
                    <p><strong>Student:</strong> ${name}</p>
                    <p><strong>Email:</strong> ${email}</p>
                    <p><strong>Student ID:</strong> ${studentId}</p>
                    <p><strong>Amount:</strong> ₹${parseFloat(amount).toFixed(2)}</p>
                </div>
                <div>
                    <p><strong>Payment Method:</strong> ${method}</p>
                    <p><strong>Status:</strong> <span class="badge ${statusClass}">${status}</span></p>
                    <p><strong>Payment Date:</strong> ${date}</p>
                </div>
            `;
        }
        function closeModal() {document.getElementById('transactionModal').style.display = 'none';}
        function exportToExcel() {
            let url = 'payment_history.php?export=excel';
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('filter')) {
                if (urlParams.has('payment_status')) {url += '&payment_status=' + urlParams.get('payment_status');}
                if (urlParams.has('payment_method')) {url += '&payment_method=' + urlParams.get('payment_method');}
                if (urlParams.has('date_from')) {url += '&date_from=' + urlParams.get('date_from');}
                if (urlParams.has('date_to')) {url += '&date_to=' + urlParams.get('date_to');}
                if (urlParams.has('search')) {url += '&search=' + urlParams.get('search');}
            }
            window.location.href = url;
        }
    </script>
</body>
</html>