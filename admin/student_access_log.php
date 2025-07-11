<?php
include('admin_db.php');

// Default values
$entries_per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'login_time';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Validate entries per page
$valid_entries = [25, 50, 75, 100, 'all'];
if (!in_array($entries_per_page, $valid_entries)) {
    $entries_per_page = 25;
}

// Build the WHERE clause for search and filters
$where_clause = "";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where_clause .= " WHERE (ld.student_email LIKE '%$search%' 
                      OR ss.regNo LIKE '%$search%'
                      OR ss.firstName LIKE '%$search%'
                      OR ss.lastName LIKE '%$search%'
                      OR ld.ip_address LIKE '%$search%')";
}

if (!empty($start_date) && !empty($end_date)) {
    $where_clause .= (empty($where_clause) ? " WHERE " : " AND ") . "ld.login_time BETWEEN '$start_date' AND '$end_date'";
}

// Calculate total records and pages
$count_query = "SELECT COUNT(*) as total FROM login_details ld
                LEFT JOIN student_signup ss ON ld.student_email = ss.email" . $where_clause;
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = $entries_per_page == 'all' ? 1 : ceil($total_records / $entries_per_page);

// Ensure page is within valid range
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $entries_per_page;

// Build the main query with JOIN to get student details
$query = "SELECT ld.*, ss.regNo, ss.firstName, ss.lastName 
          FROM login_details ld
          LEFT JOIN student_signup ss ON ld.student_email = ss.email
          $where_clause
          ORDER BY $sort_column $sort_order";

if ($entries_per_page != 'all') {
    $query .= " LIMIT $offset, $entries_per_page";
}

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Access Log - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Main styles */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
        }

        /* Header styling */
        .header {
            background-color: #343a40;
            color: white;
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
            height: 62px;
        }

        .header h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        /* Sidebar styling */
        .sidebar {
            position: fixed;
            top: 62px;
            left: 0;
            height: calc(100vh - 62px);
            width: 250px;
            background-color: #343a40;
            padding-top: 20px;
            overflow-y: auto;
            z-index: 999;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: width 0.3s ease;
        }

        .sidebar a {
            padding: 12px 20px;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar a:hover {
            background-color: #495057;
        }

        .sidebar a.active {
            background-color: #007bff;
        }

        .sidebar i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main content area */
        .main-content {
            margin-left: 250px;
            margin-top: 62px;
            padding: 20px;
            box-sizing: border-box;
            min-height: calc(100vh - 62px);
            transition: margin-left 0.3s ease;
        }

        /* Controls section */
        .controls-wrapper {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .search-form .form-group {
            display: flex;
            align-items: center;
            margin-right: 10px;
            margin-bottom: 0;
        }

        .search-form .form-control {
            width: auto;
        }

        .entries-selector {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }

        .entries-selector select {
            margin: 0 8px;
            width: auto;
        }

        /* Table styling */
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .table {
            width: 100%;
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            vertical-align: middle;
            padding: 12px 8px;
        }

        .table th a {
            color: #212529;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table th a:hover {
            color: #007bff;
        }

        .table td {
            vertical-align: middle;
            padding: 12px 8px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 500;
        }

        /* Pagination */
        .pagination {
            margin-top: 20px;
        }

        .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }

        .page-link {
            color: #007bff;
        }

        .page-link:hover {
            color: #0056b3;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .search-form {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form .form-group {
                margin-bottom: 10px;
                width: 100%;
            }
            
            .search-form .form-control {
                width: 100%;
            }
            
            .search-form .btn {
                margin-top: 5px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            
            .sidebar a span {
                display: none;
            }
            
            .main-content {
                margin-left: 60px;
            }
            
            .header h3 {
                font-size: 1rem;
            }
            
            .entries-selector {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 1rem;
            }
            
            .main-content {
                padding: 10px;
            }
            
            .controls-wrapper {
                padding: 10px;
            }
            
            .table th, .table td {
                padding: 8px 4px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h3>Hostel Management System - Admin Panel</h3>
        <div>Welcome, Admin</div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="manage_students.php"><i class="fas fa-users"></i> <span>Manage Students</span></a>
        <a href="manage_rooms.php"><i class="fas fa-bed"></i> <span>Manage Rooms</span></a>
        <a href="student_access_log.php" class="active"><i class="fas fa-history"></i> <span>Access Log</span></a>
        <!-- <a href="admin_settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a> -->
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid mt-4">
            <h2>Student Access Log</h2>
 
            <!-- Search and Entries Per Page Controls -->
            <div class="controls-wrapper mb-3">
                <form class="search-form" method="GET">
                    <div class="entries-selector">
                        <label>Show</label>
                        <select class="form-select form-select-sm" name="entries" onchange="this.form.submit()">
                            <?php foreach ($valid_entries as $value): ?>
                                <option value="<?php echo $value; ?>" 
                                        <?php echo $entries_per_page == $value ? 'selected' : ''; ?>>
                                    <?php echo $value == 'all' ? 'All' : $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>entries</label>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by email, reg. no, name, or IP...">
                    </div>
                    
                    <div class="form-group">
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>" placeholder="Start Date">
                    </div>
                    
                    <div class="form-group">
                        <input type="date" class="form-control" name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>" placeholder="End Date">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="student_access_log.php" class="btn btn-secondary ms-2">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Access Log Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>
                                <a href="?sort=regNo&order=<?php echo $sort_column == 'regNo' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    Registration Number
                                    <?php if ($sort_column == 'regNo'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Name</th>
                            <th>
                                <a href="?sort=student_email&order=<?php echo $sort_column == 'student_email' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    Email
                                    <?php if ($sort_column == 'student_email'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>IP Address</th>
                            <th>
                                <a href="?sort=login_time&order=<?php echo $sort_column == 'login_time' && $sort_order == 'ASC' ? 'DESC' : 'ASC'; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                    Login Time
                                    <?php if ($sort_column == 'login_time'): ?>
                                        <i class="fas fa-sort-<?php echo $sort_order == 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = ($page - 1) * ($entries_per_page == 'all' ? 0 : $entries_per_page) + 1;
                        if($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($row['regNo'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($row['student_email']); ?></td>
                                <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($row['login_time'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['login_status'] == 'success' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($row['login_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr>
                                <td colspan="7" class="text-center">No records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1 && $entries_per_page != 'all'): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo $sort_column; ?>&order=<?php echo $sort_order; ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo $sort_column; ?>&order=<?php echo $sort_order; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&entries=<?php echo $entries_per_page; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&sort=<?php echo $sort_column; ?>&order=<?php echo $sort_order; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>