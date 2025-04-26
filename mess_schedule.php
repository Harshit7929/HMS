<?php
include 'db.php';
session_start();
$nationality = "Indian";
if (isset($_SESSION['student_id'])) {
    $student_id = $_SESSION['student_id'];
    $query = "SELECT nationality FROM student_signup WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['nationality'] != "Indian") {$nationality = "International";}
    }
}
if (isset($_GET['view'])) {
    if ($_GET['view'] == 'international') {$cuisine_type = 'International';} 
    elseif ($_GET['view'] == 'indian') {$cuisine_type = 'Indian';} 
    else {$cuisine_type = ($nationality == 'Indian') ? 'Indian' : 'International';}} 
else {$cuisine_type = ($nationality == 'Indian') ? 'Indian' : 'International';}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Mess Schedule</title>
    <!-- <link rel="stylesheet" href="css/mess_schedule.css"> -->
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Arial", sans-serif; }
      body { background-color: #f5f5f5; color: #333; line-height: 1.6; }
      .container { display: flex; flex-direction: column; min-height: 100vh; }
      header { background: #2c3e50; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
      .logo h1 { font-size: 1.5rem; }
      .user-info { display: flex; align-items: center; gap: 1rem; }
      .logout-btn { background: #e74c3c; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-size: 0.9rem; transition: background-color 0.3s; }
      .logout-btn:hover { background: #c0392b; }
      .content-wrapper { display: flex; flex: 1; }
      .sidebar { width: 250px; background: #34495e; color: white; padding-top: 2rem; }
      .sidebar nav ul { list-style: none; }
      .sidebar nav ul li { margin-bottom: 0.5rem; }
      .sidebar nav ul li a { display: block; padding: 0.8rem 1.5rem; color: white; text-decoration: none; transition: background-color 0.3s; }
      .sidebar nav ul li a:hover, .sidebar nav ul li a.active { background: #2c3e50; border-left: 4px solid #3498db; }
      .main-content { flex: 1; padding: 2rem; }
      h2 { color: #2c3e50; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #eee; }
      .section { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); padding: 1.5rem; margin-bottom: 2rem; }
      h3 { color: #3498db; margin-bottom: 1rem; }
      h4 { color: #2c3e50; margin-bottom: 1rem; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
      th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
      th { background-color: #f8f9fa; font-weight: bold; color: #2c3e50; }
      .timings-table { max-width: 500px; }
      .timings-table th:first-child, .timings-table td:first-child { width: 40%; }
      .menu-table { margin-bottom: 2rem; overflow-x: auto; }
      .menu-table table th:first-child, .menu-table table td:first-child { font-weight: bold; }
      .btn { display: inline-block; background: #3498db; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: background-color 0.3s; }
      .btn:hover { background: #2980b9; }
      .alt-menu { text-align: center; margin-top: 1rem; }
      footer { background: #2c3e50; color: white; text-align: center; padding: 1rem; margin-top: auto; }
      @media (max-width: 992px) { .content-wrapper { flex-direction: column; } .sidebar { width: 100%; padding-top: 0; } .sidebar nav ul { display: flex; flex-wrap: wrap; } .sidebar nav ul li { margin-bottom: 0; } .sidebar nav ul li a { padding: 0.5rem 1rem; } }
      @media (max-width: 768px) { header { flex-direction: column; padding: 1rem; text-align: center; } .user-info { margin-top: 1rem; } .main-content { padding: 1rem; } th, td { padding: 0.5rem; font-size: 0.9rem; } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo"><h1>Hostel Management System</h1></div>
            <div class="user-info">
                <span>Welcome, <?php echo isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student'; ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <div class="content-wrapper">
            <div class="sidebar">
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="room_details.php">Room Details</a></li>
                        <li><a href="mess_schedule.php" class="active">Mess Schedule</a></li>
                        <li><a href="complaints.php">Complaints</a></li>
                        <li><a href="payment_history.php">Payments</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </nav>
            </div>
            <div class="main-content">
                <h2>Mess Schedule</h2>
                <div class="section">
                    <h3>Mess Timings</h3>
                    <table class="timings-table">
                        <thead>
                            <tr>
                                <th>Meal</th>
                                <th>Timing</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Breakfast</td>
                                <td>8:00 AM - 9:30 AM</td>
                            </tr>
                            <tr>
                                <td>Lunch</td>
                                <td>12:30 PM - 2:30 PM</td>
                            </tr>
                            <tr>
                                <td>Evening Snacks</td>
                                <td>4:30 PM - 5:30 PM</td>
                            </tr>
                            <tr>
                                <td>Dinner</td>
                                <td>8:00 PM - 9:30 PM</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="section">
                    <h3><?php echo $cuisine_type; ?> Menu</h3>
                    <div class="menu-table">
                        <h4>Weekly Menu - <?php echo $cuisine_type; ?> Cuisine</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Breakfast</th>
                                    <th>Lunch</th>
                                    <th>Evening Snacks</th>
                                    <th>Dinner</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM mess_menu WHERE cuisine_type = ? ORDER BY day_order";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("s", $cuisine_type);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['day_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['breakfast']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['lunch']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['snacks']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['dinner']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {echo "<tr><td colspan='5'>No menu found</td></tr>";}
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alt-menu">
                        <a href="mess_schedule.php?view=<?php echo ($cuisine_type == 'Indian') ? 'international' : 'indian'; ?>" class="btn">
                            View <?php echo ($cuisine_type == 'Indian') ? 'International' : 'Indian'; ?> Menu
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <footer><p>&copy; <?php echo date('Y'); ?> Hostel Management System. All rights reserved.</p></footer>
    </div>
</body>
</html>