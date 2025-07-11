<?php
session_start();
include('admin_db.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();}
$entries_options = [10, 25, 50, 100];
$records_per_page = isset($_GET['entries']) && in_array((int)$_GET['entries'], $entries_options) ? (int)$_GET['entries'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$browser = $_GET['browser'] ?? '';
$device = $_GET['device'] ?? '';
$ip_address = $_GET['ip_address'] ?? '';
$query = "SELECT al.*, a.username FROM admin_log al 
          JOIN admin a ON al.admin_id = a.id WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM admin_log al 
                JOIN admin a ON al.admin_id = a.id WHERE 1=1";
$params = [];
$types = ''; 
if ($date_from) {
    $query .= " AND DATE(al.login_time) >= ?";
    $count_query .= " AND DATE(al.login_time) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to) {
    $query .= " AND DATE(al.login_time) <= ?";
    $count_query .= " AND DATE(al.login_time) <= ?";
    $params[] = $date_to;
    $types .= 's';
}
if ($status) {
    $query .= " AND al.login_status = ?";
    $count_query .= " AND al.login_status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($browser) {
    $query .= " AND al.browser = ?";
    $count_query .= " AND al.browser = ?";
    $params[] = $browser;
    $types .= 's';
}
if ($device) {
    $query .= " AND al.device_type = ?";
    $count_query .= " AND al.device_type = ?";
    $params[] = $device;
    $types .= 's';
}
if ($ip_address) {
    $query .= " AND al.ip_address = ?";
    $count_query .= " AND al.ip_address = ?";
    $params[] = $ip_address;
    $types .= 's';
}
$query .= " ORDER BY al.login_time DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($query);
if ($types) {$stmt->bind_param($types, ...$params);}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$count_stmt = $conn->prepare($count_query);
if ($params && count($params) > 2) {
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    if ($count_types) {$count_stmt->bind_param($count_types, ...$count_params);}
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();
$browsers_query = "SELECT DISTINCT browser FROM admin_log ORDER BY browser";
$browsers_result = $conn->query($browsers_query);
$browsers = [];
while ($row = $browsers_result->fetch_assoc()) {$browsers[] = $row['browser'];}
$devices_query = "SELECT DISTINCT device_type FROM admin_log ORDER BY device_type";
$devices_result = $conn->query($devices_query);
$devices = [];
while ($row = $devices_result->fetch_assoc()) {$devices[] = $row['device_type'];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access Log</title>    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        body { display: flex; background-color: #f5f5f5; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #2c3e50; color: #ecf0f1; padding: 20px 0; height: 100vh; position: fixed; transition: all 0.3s; }
        .sidebar h2 { text-align: center; padding: 15px 0; margin-bottom: 20px; border-bottom: 1px solid #34495e; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { margin-bottom: 5px; }
        .sidebar ul li a { display: block; color: #ecf0f1; text-decoration: none; padding: 12px 25px; transition: all 0.3s; }
        .sidebar ul li a:hover { background-color: #34495e; padding-left: 30px; }
        .sidebar ul li a.active { background-color: #3498db; color: white; }
        .sidebar ul li a i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { flex: 1; margin-left: 250px; padding: 30px; }
        h2 { margin-bottom: 20px; color: #2c3e50; }
        .filters { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        .filters form { display: flex; flex-wrap: wrap; gap: 15px; }
        .filters input, .filters select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; flex-grow: 1; max-width: 200px; }
        .filters button { background-color: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; }
        .filters button:hover { background-color: #2980b9; }
        table { width: 100%; border-collapse: collapse; background-color: white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); border-radius: 8px; overflow: hidden; }
        thead { background-color: #3498db; color: white; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        tbody tr:hover { background-color: #f1f1f1; }
        .pagination-entries { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; }
        .entries-selector { display: flex; align-items: center; gap: 10px; }
        .entries-selector select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .pagination { display: flex; gap: 5px; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; border-radius: 4px; text-decoration: none; color: #333; background-color: #fff; border: 1px solid #ddd; }
        .pagination a:hover { background-color: #f5f5f5; }
        .pagination .active { background-color: #3498db; color: white; border-color: #3498db; }
        .pagination .disabled { color: #aaa; pointer-events: none; }
        .table-info { margin-top: 10px; font-size: 14px; color: #666; }
        @media (max-width: 1024px) { .sidebar { width: 200px; } .main-content { margin-left: 200px; } }
        @media (max-width: 768px) { 
        body { flex-direction: column; } 
        .sidebar { width: 100%; height: auto; position: relative; } 
        .main-content { margin-left: 0; } 
        .filters form { flex-direction: column; } 
        .filters input, .filters select { max-width: 100%; } 
        .pagination-entries { flex-direction: column; gap: 15px; } 
        }
        .status-success { color: #27ae60; font-weight: bold; }
        .status-failure { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admin_access_log.php" class="active"><i class="fas fa-history"></i> Access Logs</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h2><i class="fas fa-history"></i> Admin Access Log</h2>
        <div class="filters">
            <form method="GET">
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From Date">
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To Date">
                <input type="text" name="ip_address" value="<?php echo $ip_address; ?>" placeholder="IP Address">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="Success" <?php echo $status == 'Success' ? 'selected' : ''; ?>>Success</option>
                    <option value="Failure" <?php echo $status == 'Failure' ? 'selected' : ''; ?>>Failure</option>
                </select>
                <select name="browser">
                    <option value="">All Browsers</option>
                    <?php foreach ($browsers as $b): ?>
                        <option value="<?php echo $b; ?>" <?php echo $browser == $b ? 'selected' : ''; ?>><?php echo $b; ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="device">
                    <option value="">All Devices</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?php echo $d; ?>" <?php echo $device == $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="entries" value="<?php echo $records_per_page; ?>">
                <input type="hidden" name="page" value="1">
                <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
            </form>
        </div>
        <div class="pagination-entries">
            <div class="entries-selector">
                <span>Show</span>
                <form id="entriesForm" method="GET" style="display: inline;">
                    <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
                    <input type="hidden" name="date_to" value="<?php echo $date_to; ?>">
                    <input type="hidden" name="ip_address" value="<?php echo $ip_address; ?>">
                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                    <input type="hidden" name="browser" value="<?php echo $browser; ?>">
                    <input type="hidden" name="device" value="<?php echo $device; ?>">
                    <input type="hidden" name="page" value="1">
                    <select name="entries" onchange="document.getElementById('entriesForm').submit();">
                        <?php foreach ($entries_options as $option): ?>
                            <option value="<?php echo $option; ?>" <?php echo $records_per_page == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <span>entries</span>
            </div>
            <div class="table-info">
                Showing <?php echo min(($page - 1) * $records_per_page + 1, $total_records); ?> to 
                <?php echo min($page * $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                    <span class="disabled"><i class="fas fa-angle-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                if ($end_page - $start_page < 4 && $total_pages > 5) {$start_page = max(1, $end_page - 4);}
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-right"></i></span>
                    <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th> <th>Admin ID</th> <th>Username</th>
                    <th>Login Time</th> <th>Logout Time</th> <th>Status</th>
                    <th>Browser</th> <th>Browser Version</th>
                    <th>Device</th> <th>IP Address</th> <th>Session ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $index => $log): ?>
                    <tr>
                        <td><?php echo ($page - 1) * $records_per_page + $index + 1; ?></td>
                        <td><?php echo $log['admin_id']; ?></td>
                        <td><?php echo $log['username']; ?></td>
                        <td><?php echo $log['login_time']; ?></td>
                        <td><?php echo $log['logout_time'] ? $log['logout_time'] : '-'; ?></td>
                        <td class="<?php echo strtolower($log['login_status']) == 'success' ? 'status-success' : 'status-failure'; ?>">
                            <?php echo $log['login_status']; ?>
                        </td>
                        <td><?php echo $log['browser']; ?></td>
                        <td><?php echo $log['browser_version']; ?></td>
                        <td><?php echo $log['device_type']; ?></td>
                        <td><?php echo $log['ip_address']; ?></td>
                        <td><?php echo $log['session_id']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align: center;">No records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination-entries" style="justify-content: flex-end;">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                    <span class="disabled"><i class="fas fa-angle-left"></i></span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                if ($end_page - $start_page < 4 && $total_pages > 5) {
                    $start_page = max(1, $end_page - 4);
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?>&entries=<?php echo $records_per_page; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status; ?>&browser=<?php echo $browser; ?>&device=<?php echo $device; ?>&ip_address=<?php echo $ip_address; ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-right"></i></span>
                    <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>