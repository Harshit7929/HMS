<?php
include 'admin_db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();}
$records_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$hostel_filter = isset($_GET['hostel']) ? $_GET['hostel'] : '';
$room_type = isset($_GET['room_type']) ? $_GET['room_type'] : '';
$ac_filter = isset($_GET['ac']) ? $_GET['ac'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;
$query = "SELECT rb.id as booking_id, ss.regNo, CONCAT(ss.firstName, ' ', ss.lastName) as student_name, 
          ss.email, rb.hostel_name, rb.room_number, rb.sharing_type, rb.is_ac, rb.booking_date,
          rb.total_fee, COALESCE(SUM(pd.amount), 0) as amount_paid, 
          (rb.total_fee - COALESCE(SUM(pd.amount), 0)) as amount_due, 
          rb.status as booking_status
          FROM room_bookings rb
          LEFT JOIN student_signup ss ON rb.user_email = ss.email
          LEFT JOIN payment_details pd ON rb.id = pd.booking_id AND pd.payment_status = 'completed'";
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(ss.regNo LIKE '%$search%' OR ss.firstName LIKE '%$search%' OR ss.lastName LIKE '%$search%' OR ss.email LIKE '%$search%')";}
if ($filter == 'paid') {$having_conditions[] = "amount_paid >= total_fee";} 
elseif ($filter == 'pending') {$having_conditions[] = "amount_paid < total_fee";} 
elseif ($filter == 'overdue') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM fee_dues fd WHERE fd.booking_id = rb.id AND fd.status = 'overdue')";}
if (!empty($hostel_filter)) {$where_conditions[] = "rb.hostel_name = '$hostel_filter'";}
if (!empty($room_type)) {$where_conditions[] = "rb.sharing_type = '$room_type'";}
if ($ac_filter !== '') {$where_conditions[] = "rb.is_ac = " . ($ac_filter == '1' ? '1' : '0');}
if (!empty($date_from)) {$where_conditions[] = "rb.booking_date >= '$date_from'";}
if (!empty($date_to)) {$where_conditions[] = "rb.booking_date <= '$date_to'";}
if (!empty($where_conditions)) {$query .= " WHERE " . implode(" AND ", $where_conditions);}
$query .= " GROUP BY rb.id, ss.regNo, ss.firstName, ss.lastName, ss.email, rb.hostel_name, 
           rb.room_number, rb.sharing_type, rb.is_ac, rb.total_fee, rb.status, rb.booking_date";
if (!empty($having_conditions)) {$query .= " HAVING " . implode(" AND ", $having_conditions);}
$count_query = "SELECT COUNT(*) as total FROM ($query) as subquery";
$count_result = mysqli_query($conn, $count_query);
if (!$count_result) {
    $error_message = mysqli_error($conn);
    echo "Error in count query: " . $error_message;
    exit();
}
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);
$query .= " LIMIT $offset, $records_per_page";
$result = mysqli_query($conn, $query);
if (!$result) {
    $error_message = mysqli_error($conn);
    echo "Error in main query: " . $error_message;
    exit();
}
$stats_query = "SELECT 
                SUM(total_fee) as total_fee,
                SUM(amount_paid) as total_collected,
                COUNT(*) as total_bookings,
                SUM(CASE WHEN total_fee <= amount_paid THEN 1 ELSE 0 END) as fully_paid,
                SUM(CASE WHEN total_fee > amount_paid THEN 1 ELSE 0 END) as pending_payment
                FROM (
                    SELECT rb.id, rb.total_fee, COALESCE(SUM(pd.amount), 0) as amount_paid
                    FROM room_bookings rb
                    LEFT JOIN payment_details pd ON rb.id = pd.booking_id AND pd.payment_status = 'completed'
                    GROUP BY rb.id, rb.total_fee
                ) as payment_summary";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
$hostel_query = "SELECT DISTINCT hostel_name FROM room_bookings ORDER BY hostel_name";
$hostel_result = mysqli_query($conn, $hostel_query);
$room_type_query = "SELECT DISTINCT sharing_type FROM room_bookings ORDER BY sharing_type";
$room_type_result = mysqli_query($conn, $room_type_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Collection - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="css/fee_collection.css">
    <style>
        body { margin: 0; padding: 0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; color: #333; }
        .admin-container { display: flex; min-height: 100vh; width: 100%; box-sizing: border-box; position: relative; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #fff; position: fixed; height: 100%; left: 0; top: 0; overflow-y: auto; transition: all 0.3s ease; z-index: 999; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .sidebar.collapsed { transform: translateX(-100%); }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; }
        .sidebar-header h2 { margin: 0; font-size: 1.2rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .menu-section { padding: 1rem 0; }
        .menu-title { padding: 0 1.5rem; margin-bottom: 0.5rem; font-size: 0.8rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); letter-spacing: 1px; }
        .menu-items { list-style: none; padding: 0; margin: 0; }
        .menu-items li { position: relative; }
        .menu-items li a { display: flex; align-items: center; padding: 0.8rem 1.5rem; color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: all 0.3s ease; font-size: 0.95rem; }
        .menu-items li a:hover { background-color: rgba(255, 255, 255, 0.1); color: #fff; }
        .menu-items li a.active { background-color: rgba(255, 255, 255, 0.15); color: #fff; border-left: 4px solid #4e73df; }
        .menu-items li a i { width: 20px; margin-right: 12px; text-align: center; }
        .main-content { flex: 1; margin-left: 260px; padding: 0; transition: all 0.3s ease; width: calc(100% - 260px); box-sizing: border-box; position: relative; }
        .main-content.expanded { margin-left: 0; width: 100%; }
        .sidebar-toggle { background-color: #2c3e50; color: white; border: none; border-radius: 4px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; position: fixed; left: 260px; top: 15px; z-index: 1000; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); transition: all 0.3s ease; transform: translateX(-50%); }
        .sidebar-toggle.active { left: 0; transform: translateX(0); }
        .admin-header { background-color: #fff; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        .header-left h1 { margin: 0; font-size: 1.4rem; color: #2c3e50; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification-icon { position: relative; cursor: pointer; }
        .notification-icon i { font-size: 1.2rem; color: #6c757d; }
        .notification-badge { position: absolute; top: -8px; right: -8px; background-color: #e74a3b; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; }
        .admin-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .admin-avatar img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .admin-info { display: flex; flex-direction: column; }
        .admin-name { font-size: 0.9rem; font-weight: 600; color: #2c3e50; }
        .admin-role { font-size: 0.75rem; color: #6c757d; }
        .content-wrapper { padding: 1.5rem; background-color: #f8f9fa; width: 100%; box-sizing: border-box; overflow-x: hidden; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef; flex-wrap: wrap; gap: 1rem; }
        .page-header h1 { color: #2c3e50; font-size: 1.75rem; margin: 0; word-break: break-word; }
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 0.5rem 1rem; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; white-space: nowrap; }
        .btn-primary { background-color: #4e73df; color: white; }
        .btn-primary:hover { background-color: #3a58c7; }
        .btn-secondary { background-color: #858796; color: white; }
        .btn-secondary:hover { background-color: #717380; }
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 2rem; width: 100%; }
        .stat-card { background-color: white; border-radius: 8px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); padding: 1.25rem; display: flex; align-items: center; transition: transform 0.3s ease; max-width: 100%; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { background-color: #f8f9fc; min-width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; flex-shrink: 0; }
        .stat-icon i { font-size: 1.5rem; color: #4e73df; }
        .stat-content { flex: 1; min-width: 0; }
        .stat-content h3 { margin: 0; font-size: 0.9rem; color: #858796; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .stat-content p { margin: 5px 0 0; font-size: 1.25rem; font-weight: 700; color: #2c3e50; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .filter-section { margin-bottom: 1.5rem; width: 100%; }
        .search-form { background-color: white; padding: 1rem; border-radius: 8px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); width: 100%; box-sizing: border-box; }
        .input-group { display: flex; gap: 10px; flex-wrap: wrap; width: 100%; }
        .input-group input, .input-group select { flex: 1; min-width: 0; max-width: 100%; padding: 0.6rem 1rem; border: 1px solid #d1d3e2; border-radius: 4px; font-size: 0.9rem; box-sizing: border-box; }
        .btn-search, .btn-reset { padding: 0.6rem 1rem; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; }
        .btn-search { background-color: #4e73df; color: white; }
        .btn-search:hover { background-color: #3a58c7; }
        .btn-reset { background-color: #f8f9fa; border: 1px solid #d1d3e2; color: #6c757d; }
        .btn-reset:hover { background-color: #e9ecef; }
        .table-responsive { overflow-x: auto; background-color: white; border-radius: 8px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); margin-bottom: 1.5rem; width: 100%; }
        .data-table { width: 100%; border-collapse: collapse; table-layout: auto; }
        .data-table th, .data-table td { padding: 0.9rem; text-align: left; border-bottom: 1px solid #e3e6f0; word-break: break-word; }
        .data-table th { background-color: #f8f9fc; font-weight: 600; color: #5a5c69; white-space: nowrap; }
        .data-table tr:hover { background-color: #f8f9fa; }
        .status-badge { padding: 0.4rem 0.8rem; border-radius: 30px; font-size: 0.8rem; font-weight: 500; display: inline-block; }
        .status-paid { background-color: rgba(28, 200, 138, 0.2); color: #1cc88a; }
        .status-pending { background-color: rgba(246, 194, 62, 0.2); color: #f6c23e; }
        .status-overdue { background-color: rgba(231, 74, 59, 0.2); color: #e74a3b; }
        .actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn-action { width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.3s ease; flex-shrink: 0; }
        .view { background-color: #4e73df; }
        .view:hover { background-color: #3a58c7; }
        .remind { background-color: #f6c23e; }
        .remind:hover { background-color: #dda83a; }
        .payment { background-color: #1cc88a; }
        .payment:hover { background-color: #18a878; }
        .no-data { text-align: center; padding: 2rem; color: #858796; }
        .pagination { display: flex; justify-content: center; margin-top: 1.5rem; width: 100%; }
        .pagination ul { display: flex; list-style: none; padding: 0; margin: 0; gap: 5px; flex-wrap: wrap; justify-content: center; }
        .pagination a { display: flex; align-items: center; justify-content: center; width: 35px; height: 35px; border-radius: 4px; text-decoration: none; color: #6c757d; border: 1px solid #e3e6f0; transition: all 0.3s ease; }
        .pagination a:hover { background-color: #4e73df; color: white; border-color: #4e73df; }
        .pagination a.active { background-color: #4e73df; color: white; border-color: #4e73df; }
        @media screen and (max-width: 992px) { .stats-cards { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); } .action-buttons { justify-content: flex-start; width: 100%; } .input-group input, .input-group select { min-width: calc(50% - 10px); } .sidebar:not(.collapsed) { width: 220px; } .main-content:not(.expanded) { margin-left: 220px; width: calc(100% - 220px); } .sidebar-toggle { left: 220px; } }
        @media screen and (max-width: 768px) { .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; } .stats-cards { grid-template-columns: 1fr; } .data-table { font-size: 0.85rem; min-width: 650px; } .input-group input, .input-group select { min-width: 100%; } .btn-search, .btn-reset { flex: 1; justify-content: center; } .sidebar:not(.collapsed) { width: 70px; } .sidebar-header h2, .menu-title, .menu-items li a span { opacity: 0; visibility: hidden; } .menu-items li a { padding: 0.8rem; justify-content: center; } .menu-items li a i { margin-right: 0; font-size: 1.2rem; } .main-content:not(.expanded) { margin-left: 70px; width: calc(100% - 70px); } .sidebar-toggle { left: 70px; } }
        @media screen and (max-width: 480px) { .content-wrapper { padding: 1rem; } .page-header h1 { font-size: 1.5rem; } .stat-card { padding: 1rem; } .stat-icon { min-width: 50px; height: 50px; } .stat-content p { font-size: 1.1rem; } .admin-header { padding: 0.75rem 1rem; } .header-left h1 { font-size: 1.2rem; } .admin-name { display: none; } .sidebar:not(.collapsed) { width: 230px; } .sidebar-header h2, .menu-title, .menu-items li a span { opacity: 1; visibility: visible; } .menu-items li a { padding: 0.8rem 1.5rem; justify-content: flex-start; } .menu-items li a i { margin-right: 12px; font-size: 1rem; } .sidebar-toggle { left: 230px; } }
        @media print { .sidebar, .admin-header, .action-buttons, .filter-section, .pagination, .sidebar-toggle { display: none !important; } .admin-container { display: block !important; } .main-content { margin-left: 0 !important; width: 100% !important; } .content-wrapper { padding: 0 !important; } .page-header h1 { font-size: 1.5rem !important; } .data-table th, .data-table td { padding: 0.5rem !important; } .btn-action { display: none !important; } }
        .advanced-filters { display: none; margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; border: 1px solid #e0e0e0; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .filter-group select, .filter-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .filter-buttons { display: flex; justify-content: flex-end; gap: 10px; }
        .entries-dropdown { margin-right: 15px; }
        .toggle-filters { background: #f0f0f0; border: 1px solid #ddd; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-left: 10px; }
        .export-options button { background: #4CAF50; border: none; color: white; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-left: 5px; }
        .export-options button:hover { background: #45a049; }
    </style>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="admin-container">        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="http://localhost/hostel_info/images/srmlogo.png" alt="Hostel Logo">
                <h2>Hostel Admin</h2>
            </div>
            <div class="menu-section">
                <div class="menu-title">Main Menu</div>
                <ul class="menu-items">
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="manage_students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
                    <li><a href="manage_rooms.php"><i class="fas fa-door-open"></i> <span>Rooms</span></a></li>
                    <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> <span>Bookings</span></a></li>
                    <li><a href="fee_collection.php" class="active"><i class="fas fa-money-bill-wave"></i> <span>Fee Collection</span></a></li>
                </ul>
            </div>
            <div class="menu-section">
                <div class="menu-title">Administration</div>
                <ul class="menu-items">
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
                    <!-- <li><a href="admin_settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li> -->
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>
        <div class="main-content" id="mainContent">
            <div class="admin-header">
                <div class="header-left">
                    <h1>Admin Dashboard</h1>
                </div>
                <!-- <div class="header-right">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                    <div class="admin-profile">
                        <div class="admin-avatar">
                            <img src="assets/images/admin-avatar.jpg" alt="Admin Avatar">
                        </div>
                        <div class="admin-info">
                            <div class="admin-name">Admin User</div>
                            <div class="admin-role">Administrator</div>
                        </div> 
                    </div>
                </div> -->
            </div>
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>Fee Collection Management</h1>
                    <div class="action-buttons">
                        <div class="export-options">
                            <button onclick="exportData('excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            <button onclick="exportData('csv')"><i class="fas fa-file-csv"></i> CSV</button>
                            <button onclick="exportData('pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                            <button onclick="printReport()"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                </div>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-content">
                            <h3>Total Fee</h3>
                            <p>₹<?php echo number_format($stats['total_fee'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="stat-content">
                            <h3>Collected</h3>
                            <p>₹<?php echo number_format($stats['total_collected'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                        <div class="stat-content">
                            <h3>Collection Rate</h3>
                            <p><?php echo ($stats['total_fee'] > 0) ? round(($stats['total_collected'] / $stats['total_fee']) * 100, 2) : 0; ?>%</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <h3>Total Bookings</h3>
                            <p><?php echo $stats['total_bookings'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="filter-section">
                    <form method="GET" action="" class="search-form" id="filterForm">
                        <div class="input-group">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Reg No, Name or Email">
                            <select name="filter">
                                <option value="all" <?php echo ($filter == 'all') ? 'selected' : ''; ?>>All Students</option>
                                <option value="paid" <?php echo ($filter == 'paid') ? 'selected' : ''; ?>>Fully Paid</option>
                                <option value="pending" <?php echo ($filter == 'pending') ? 'selected' : ''; ?>>Pending Payment</option>
                                <option value="overdue" <?php echo ($filter == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                            <div class="entries-dropdown">
                                <label>Show 
                                    <select name="entries" onchange="this.form.submit()">
                                        <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo ($records_per_page == 25) ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo ($records_per_page == 100) ? 'selected' : ''; ?>>100</option>
                                    </select>
                                entries</label>
                            </div>
                            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                            <a href="fee_collection.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
                            <button type="button" class="toggle-filters" id="toggleFilters"><i class="fas fa-filter"></i> Advanced Filters </button>
                        </div>
                        <div class="advanced-filters" id="advancedFilters">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="hostel">Hostel:</label>
                                    <select name="hostel" id="hostel">
                                        <option value="">All Hostels</option>
                                        <?php while ($hostel = mysqli_fetch_assoc($hostel_result)): ?>
                                            <option value="<?php echo htmlspecialchars($hostel['hostel_name']); ?>" 
                                                    <?php echo ($hostel_filter == $hostel['hostel_name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($hostel['hostel_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="room_type">Room Type:</label>
                                    <select name="room_type" id="room_type">
                                        <option value="">All Types</option>
                                        <?php while ($type = mysqli_fetch_assoc($room_type_result)): ?>
                                            <option value="<?php echo htmlspecialchars($type['sharing_type']); ?>"
                                                    <?php echo ($room_type == $type['sharing_type']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['sharing_type']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="ac">AC/Non-AC:</label>
                                    <select name="ac" id="ac">
                                        <option value="">All</option>
                                        <option value="1" <?php echo ($ac_filter === '1') ? 'selected' : ''; ?>>AC</option>
                                        <option value="0" <?php echo ($ac_filter === '0') ? 'selected' : ''; ?>>Non-AC</option>
                                    </select>
                                </div>
                            </div>
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="date_from">From Date:</label>
                                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="filter-group">
                                    <label for="date_to">To Date:</label>
                                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                                </div>
                            </div>
                            <div class="filter-buttons">
                                <button type="submit" class="btn-search">Apply Filters</button>
                                <button type="button" class="btn-reset" onclick="resetAdvancedFilters()">Reset Filters</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reg No</th> <th>Student Name</th> <th>Email</th> <th>Hostel</th> <th>Room</th>
                                <th>Room Type</th> <th>Total Fee</th> <th>Paid</th> <th>Due</th> <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $payment_status = "";
                                    $status_class = "";
                                    if ($row['amount_due'] <= 0) {
                                        $payment_status = "Paid";
                                        $status_class = "status-paid";
                                    } elseif (isset($row['overdue']) && $row['overdue']) {
                                        $payment_status = "Overdue";
                                        $status_class = "status-overdue";
                                    } else {
                                        $payment_status = "Pending";
                                        $status_class = "status-pending";
                                    }
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['regNo']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['hostel_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['room_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['sharing_type']) . ($row['is_ac'] ? ' (AC)' : ' (Non-AC)') . "</td>";
                                    echo "<td>₹" . number_format($row['total_fee'], 2) . "</td>";
                                    echo "<td>₹" . number_format($row['amount_paid'], 2) . "</td>";
                                    echo "<td>₹" . number_format($row['amount_due'], 2) . "</td>";
                                    echo "<td><span class='status-badge " . $status_class . "'>" . $payment_status . "</span></td>";
                                    echo "</tr>";
                                }
                            } else {echo "<tr><td colspan='10' class='no-data'>No records found</td></tr>";}
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <ul>
                        <?php if ($page > 1): ?>
                            <li><a href="?page=1&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&entries=<?php echo $records_per_page; ?>&hostel=<?php echo urlencode($hostel_filter); ?>&room_type=<?php echo urlencode($room_type); ?>&ac=<?php echo urlencode($ac_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><i class="fas fa-angle-double-left"></i></a></li>
                            <li><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&entries=<?php echo $records_per_page; ?>&hostel=<?php echo urlencode($hostel_filter); ?>&room_type=<?php echo urlencode($room_type); ?>&ac=<?php echo urlencode($ac_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><i class="fas fa-angle-left"></i></a></li>
                        <?php endif; ?>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo "<li><a href='?page=" . $i . "&search=" . urlencode($search) . "&filter=" . urlencode($filter) . "&entries=" . $records_per_page . "&hostel=" . urlencode($hostel_filter) . "&room_type=" . urlencode($room_type) . "&ac=" . urlencode($ac_filter) . "&date_from=" . urlencode($date_from) . "&date_to=" . urlencode($date_to) . "' " . 
                                (($i == $page) ? "class='active'" : "") . ">" . $i . "</a></li>";
                        }
                        ?>
                        <?php if ($page < $total_pages): ?>
                            <li><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&entries=<?php echo $records_per_page; ?>&hostel=<?php echo urlencode($hostel_filter); ?>&room_type=<?php echo urlencode($room_type); ?>&ac=<?php echo urlencode($ac_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><i class="fas fa-angle-right"></i></a></li>
                            <li><a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&entries=<?php echo $records_per_page; ?>&hostel=<?php echo urlencode($hostel_filter); ?>&room_type=<?php echo urlencode($room_type); ?>&ac=<?php echo urlencode($ac_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><i class="fas fa-angle-double-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
            this.classList.toggle('active');
        });
        document.getElementById('toggleFilters').addEventListener('click', function() {
            const filtersSection = document.getElementById('advancedFilters');
            if (filtersSection.style.display === 'block') {filtersSection.style.display = 'none';} 
            else {filtersSection.style.display = 'block';}
        });
        function resetAdvancedFilters() {
            document.getElementById('hostel').value = '';
            document.getElementById('room_type').value = '';
            document.getElementById('ac').value = '';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.getElementById('filterForm').submit();
        }
        function exportData(format) {
            const baseUrl = 'export_fee_data.php';
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.append('export_format', format);
            window.location.href = baseUrl + '?' + searchParams.toString();
        }
        function printReport() {window.print();}
    </script>
</body>
</html>