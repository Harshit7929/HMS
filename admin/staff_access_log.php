<?php
include 'admin_db.php';
$limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_hostel = isset($_GET['hostel']) ? $_GET['hostel'] : '';
$filter_position = isset($_GET['position']) ? $_GET['position'] : '';
$count_query = "SELECT COUNT(*) as total FROM staff_login sl
                JOIN staff s ON s.staff_id = sl.staff_id";
$filter_conditions = [];
if (!empty($filter_status)) { $filter_conditions[] = "sl.login_status = '$filter_status'";}
if (!empty($filter_hostel)) { $filter_conditions[] = "sl.hostel = '$filter_hostel'";}
if (!empty($filter_position)) { $filter_conditions[] = "sl.position = '$filter_position'";}
if (!empty($filter_conditions)) { $count_query .= " WHERE " . implode(' AND ', $filter_conditions);}
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$query = "SELECT 
            sl.id, 
            sl.staff_email, 
            sl.staff_id, 
            s.name as staff_name,
            sl.position, 
            sl.hostel, 
            sl.ip_address, 
            sl.login_status, 
            sl.login_time 
          FROM staff_login sl
          JOIN staff s ON s.staff_id = sl.staff_id";
if (!empty($filter_conditions)) {$query .= " WHERE " . implode(' AND ', $filter_conditions);}
$query .= " ORDER BY sl.login_time DESC LIMIT $start, $limit";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login Activities</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {margin: 0;padding: 0;box-sizing: border-box;font-family: 'Arial', sans-serif;}
        body {display: flex;background-color: #f5f5f5;min-height: 100vh;}
        .sidebar {width: 250px;background-color: #2c3e50;color: white;padding: 20px 0;height: 100vh;position: fixed;}
        .sidebar h2 {text-align: center;margin-bottom: 30px;padding-bottom: 15px;border-bottom: 1px solid #34495e;}
        .sidebar a {display: flex;align-items: center;padding: 15px 25px;color: white;text-decoration: none;transition: background-color 0.3s;}
        .sidebar a:hover {background-color: #34495e;}
        .sidebar a i {margin-right: 10px;width: 20px;text-align: center;}
        .content {flex: 1;margin-left: 250px;padding: 30px;}
        h1 {color: #2c3e50;margin-bottom: 30px;padding-bottom: 10px;border-bottom: 2px solid #3498db;}
        .filter-form {display: flex;flex-wrap: wrap;gap: 20px;margin-bottom: 30px;padding: 20px;
            background-color: white;border-radius: 8px;box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
        .filter-form div {display: flex;flex-direction: column;}
        .filter-form label {margin-bottom: 5px;font-weight: bold;color: #2c3e50;}
        .filter-form select {padding: 8px 12px;border: 1px solid #ddd;border-radius: 4px;min-width: 150px;}
        .button-group {display: flex;gap: 10px;margin-top: auto;flex-direction: row !important;}
        .filter-form button {padding: 8px 16px;background-color: #3498db;color: white;border: none;border-radius: 4px;cursor: pointer;
            transition: background-color 0.3s;}
        .filter-form button:hover {background-color: #2980b9;}
        .clear-filters {display: inline-block;padding: 8px 16px;background-color: #e74c3c;color: white;
            text-decoration: none;border-radius: 4px;transition: background-color 0.3s;}
        .clear-filters:hover {background-color: #c0392b;}
        table {width: 100%;border-collapse: collapse;margin-bottom: 30px;background-color: white;box-shadow: 0 2px 5px rgba(0,0,0,0.1);}
        th, td {padding: 12px 15px;text-align: left;border-bottom: 1px solid #ddd;}
        th {background-color: #2c3e50;color: white;}
        tr:hover {background-color: #f9f9f9;}
        tr:nth-child(even) {background-color: #f2f2f2;}
        tr:nth-child(even):hover {background-color: #e9e9e9;}
        .pagination {display: flex;justify-content: center;gap: 10px;margin-top: 20px;}
        .pagination a {display: inline-block;padding: 8px 12px;background-color: white;color: #2c3e50;
            text-decoration: none;border-radius: 4px;border: 1px solid #ddd;transition: all 0.3s;}
        .pagination a:hover {background-color: #f5f5f5;}
        .pagination a.active {background-color: #3498db;color: white;border-color: #3498db;}
        .success {color: #27ae60;font-weight: bold;}
        .failed {color: #e74c3c;font-weight: bold;}
        @media (max-width: 1024px) {
            .content {margin-left: 0;}
            .sidebar {width: 100%;height: auto;position: relative;margin-bottom: 20px;}
            body {flex-direction: column;}}
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Menu</h2>
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="update_profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="manage_staff.php"><i class="fas fa-users"></i> Staff</a>
        <!-- <a href="#"><i class="fas fa-cog"></i> Settings</a> -->
        <a href="#"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="content">
        <h1>Staff Login Activities</h1>
        <form method="GET" action="" class="filter-form">
            <div>
                <label for="status">Login Status:</label>
                <select name="status" id="status">
                    <option value="">All</option>
                    <option value="success" <?php if($filter_status == 'success') echo 'selected'; ?>>Success</option>
                    <option value="failed" <?php if($filter_status == 'failed') echo 'selected'; ?>>Failed</option>
                </select>
            </div>
            <div>
                <label for="hostel">Hostel:</label>
                <select name="hostel" id="hostel">
                    <option value="">All</option>
                    <option value="vedavathi" <?php if($filter_hostel == 'vedavathi') echo 'selected'; ?>>Vedavathi</option>
                    <option value="ganga" <?php if($filter_hostel == 'ganga') echo 'selected'; ?>>Ganga</option>
                    <option value="krishna" <?php if($filter_hostel == 'krishna') echo 'selected'; ?>>Krishna</option>
                    <option value="narmadha" <?php if($filter_hostel == 'narmadha') echo 'selected'; ?>>Narmadha</option>
                    <option value="yamuna" <?php if($filter_hostel == 'yamuna') echo 'selected'; ?>>Yamuna</option>
                </select>
            </div>
            <div>
                <label for="position">Position:</label>
                <select name="position" id="position">
                    <option value="">All</option>
                    <option value="warden" <?php if($filter_position == 'warden') echo 'selected'; ?>>Warden</option>
                    <option value="maintanence" <?php if($filter_position == 'maintanence') echo 'selected'; ?>>Maintenance</option>
                    <option value="laundry" <?php if($filter_position == 'laundry') echo 'selected'; ?>>Laundry</option>
                    <option value="electrocian" <?php if($filter_position == 'electrocian') echo 'selected'; ?>>Electrician</option>
                    <option value="room service" <?php if($filter_position == 'room service') echo 'selected'; ?>>Room Service</option>
                    <option value="plumber" <?php if($filter_position == 'plumber') echo 'selected'; ?>>Plumber</option>
                    <option value="security head" <?php if($filter_position == 'security head') echo 'selected'; ?>>Security Head</option>
                </select>
            </div>
            <div>
                <label for="limit">Show:</label>
                <select name="limit" id="limit">
                    <option value="10" <?php if($limit == 10) echo 'selected'; ?>>10 entries</option>
                    <option value="25" <?php if($limit == 25) echo 'selected'; ?>>25 entries</option>
                    <option value="50" <?php if($limit == 50) echo 'selected'; ?>>50 entries</option>
                </select>
            </div>
            <div class="button-group">
                <button type="submit">Apply Filters</button>
                <a href="staff_access_log.php" class="clear-filters">Clear Filters</a>
            </div>
        </form>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Staff ID</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Hostel</th>
                    <th>IP Address</th>
                    <th>Login Status</th>
                    <th>Login Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . $row['staff_email'] . "</td>";
                        echo "<td>" . $row['staff_id'] . "</td>";
                        echo "<td>" . $row['staff_name'] . "</td>";
                        echo "<td>" . $row['position'] . "</td>";
                        echo "<td>" . $row['hostel'] . "</td>";
                        echo "<td>" . $row['ip_address'] . "</td>";
                        if ($row['login_status'] == 'success') {echo "<td class='success'>" . $row['login_status'] . "</td>";} 
                        else {echo "<td class='failed'>" . $row['login_status'] . "</td>";}
                        echo "<td>" . $row['login_time'] . "</td>";
                        echo "</tr>";
                    }
                } else {echo "<tr><td colspan='9'>No login activities found</td></tr>";}
                ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php
            for ($i = 1; $i <= $total_pages; $i++) {
                echo "<a href='?page=$i&limit=$limit&status=$filter_status&hostel=$filter_hostel&position=$filter_position' class='" . ($page == $i ? 'active' : '') . "'>$i</a>";}
            ?>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>