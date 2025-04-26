<?php
// Include database connection
include 'db.php';

// Fetch the last logged-in student's email and user ID dynamically
$sql = "SELECT student_email, user_id FROM login_details ORDER BY login_time DESC LIMIT 1";
$result = $conn->query($sql);
$student_email = '';
$user_id = '';

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $student_email = $row['student_email'];
    $user_id = $row['user_id'];
}

// If no email is found, show an error
if (!$student_email) {
    echo "<p style='color: red; text-align: center; font-size: 18px;'>No student login records found.</p>";
    exit;
}

// Initialize filter variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Prepare the SQL query with conditional filtering
$sql = "SELECT user_id, student_email, ip_address, login_time, logout_time, login_status 
        FROM login_details 
        WHERE student_email = ?";

// Add status filter if specified
if ($status_filter !== 'all') {
    $sql .= " AND login_status = ?";
}

// Add date filter if specified
if ($date_filter) {
    $sql .= " AND DATE(login_time) = ?";
}

$sql .= " ORDER BY login_time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind parameters based on filters
if ($status_filter !== 'all' && $date_filter) {
    $stmt->bind_param("sss", $student_email, $status_filter, $date_filter);
} elseif ($status_filter !== 'all') {
    $stmt->bind_param("ss", $student_email, $status_filter);
} elseif ($date_filter) {
    $stmt->bind_param("ss", $student_email, $date_filter);
} else {
    $stmt->bind_param("s", $student_email);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Log</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background-color: #f8f9fa;
            color: #333;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(to bottom, #2c3e50, #1a252f);
            color: white;
            padding: 0;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 10;
        }

        .sidebar-header {
            padding: 20px 15px;
            background: rgba(0,0,0,0.1);
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }

        .sidebar ul {
            list-style-type: none;
            padding: 0;
            margin: 15px 0;
        }

        .sidebar ul li {
            margin: 8px 0;
        }

        .sidebar ul li a {
            color: #ecf0f1;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 20px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar ul li a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .sidebar ul li a.active {
            background: rgba(52, 152, 219, 0.2);
            border-left: 3px solid #3498db;
            color: #3498db;
        }

        .sidebar ul li a:hover {
            background: rgba(255,255,255,0.05);
            color: #3498db;
            border-left: 3px solid #3498db;
        }

        /* Content Section */
        .content {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 15px;
        }

        .page-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
        }

        /* Filters and Controls */
        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            color: #555;
        }

        .controls select,
        .controls input {
            padding: 10px 15px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: #fff;
            color: #333;
            transition: border 0.3s;
            min-width: 150px;
        }

        .controls select:focus,
        .controls input:focus {
            border-color: #3498db;
            outline: none;
        }

        .controls button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }

        .controls button:hover {
            background: #2980b9;
        }

        /* Reset Button */
        .reset-button {
            background: #e74c3c;
            margin-left: auto;
        }

        .reset-button:hover {
            background: #c0392b;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        th {
            background: #3498db;
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f9f9f9;
        }

        /* Status Styling */
        .status {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
        }

        .status.failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status.active {
            background: #cce5ff;
            color: #004085;
        }

        /* Logout Time Styling */
        .logout-pending {
            color: #0275d8;
            font-weight: 500;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            font-weight: 500;
            color: #6c757d;
            font-size: 16px;
        }

        /* Responsive */
        @media screen and (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }

            .sidebar-header h2 {
                display: none;
            }

            .sidebar ul li a span {
                display: none;
            }

            .sidebar ul li a i {
                margin-right: 0;
                font-size: 18px;
            }

            .content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }

            .controls {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media screen and (max-width: 576px) {
            .content {
                padding: 15px;
            }

            th, td {
                padding: 10px;
                font-size: 14px;
            }
        }
        .logo-container {text-align: center;margin-bottom: 25px;}
        .logo {max-width: 150px;margin-bottom: 10px;}
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="http://localhost/hostel_info/images/srmap.png" alt="SRM Logo" class="logo">
            </div>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            <li><a href="room_booking.php"><i class="fas fa-bed"></i> <span>Room Booking</span></a></li>
            <li><a href="complaint.php"><i class="fas fa-exclamation-circle"></i> <span>Complaints</span></a></li>
            <li><a href="access_log.php" class="active"><i class="fas fa-history"></i> <span>Access Log</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="page-header">
            <h2><i class="fas fa-history"></i> Access Log</h2>
        </div>

        <form class="controls" method="GET" action="">
            <div class="filter-group">
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                    <option value="success" <?php echo ($status_filter === 'success') ? 'selected' : ''; ?>>Success</option>
                    <option value="failed" <?php echo ($status_filter === 'failed') ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" value="<?php echo $date_filter; ?>">
            </div>

            <button type="submit">Apply Filters</button>
            <button type="button" class="reset-button" onclick="window.location.href='access_log.php'">Reset</button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User ID</th>
                        <th>Email</th>
                        <th>IP Address</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1; // Start ID from 1 for each user
                    if ($result->num_rows > 0): 
                        while ($row = $result->fetch_assoc()): 
                            $loginStatus = strtolower($row['login_status']);
                            $statusClass = ($loginStatus === 'success') ? 'success' : 'failed';
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_email']); ?></td>
                                <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($row['login_time'])); ?></td>
                                <td>
                                    <?php 
                                    if ($loginStatus === 'failed') {
                                        echo '<span class="status failed">N/A</span>';
                                    } else {
                                        echo $row['logout_time'] 
                                            ? date('M d, Y h:i A', strtotime($row['logout_time'])) 
                                            : '<span class="logout-pending">Currently Active</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($loginStatus); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <i class="fas fa-info-circle"></i> No login records found for this student.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Add current date to date input if empty
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            if (!dateInput.value) {
                const today = new Date();
                const year = today.getFullYear();
                let month = today.getMonth() + 1;
                let day = today.getDate();
                
                // Format with leading zeros
                month = month < 10 ? '0' + month : month;
                day = day < 10 ? '0' + day : day;
                
                // Only set if form hasn't been submitted with filters
                if (!window.location.search.includes('status=') && 
                    !window.location.search.includes('date=')) {
                    dateInput.value = `${year}-${month}-${day}`;
                }
            }
        });
    </script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>