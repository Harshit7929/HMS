<?php
include 'db.php';
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$query = "SELECT event_name, event_date FROM academic_events ORDER BY event_date";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$events = array();
$allEvents = array();
while ($row = $result->fetch_assoc()) {
    $eventDate = date('j', strtotime($row['event_date']));
    $eventMonth = date('n', strtotime($row['event_date']));
    $eventYear = date('Y', strtotime($row['event_date']));
    if ($eventMonth == $month && $eventYear == $year) {
        if (!isset($events[$eventDate])) {$events[$eventDate] = array();}
        $events[$eventDate][] = $row['event_name'];
    }
    $allEvents[] = $row;
} 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/academic_events.css">
    <style>
        * {margin: 0;padding: 0;box-sizing: border-box;}
        body {font-family: Arial, sans-serif;background-color: #f4f4f4;}
        header {background-color: #2c3e50;color: white;padding: 1rem 2rem;display: flex;justify-content: space-between;
            align-items: center;position: fixed;width: 100%;top: 0;z-index: 1000;}
        .logo {font-size: 1.5rem;font-weight: bold;}
        .container {display: flex;margin-top: 60px;min-height: calc(100vh - 60px);}
        .sidebar {width: 250px;background-color: #34495e;color: white;padding: 2rem 0;position: fixed;height: calc(100vh - 60px);}
        .sidebar ul {list-style: none;}
        .sidebar ul li a {color: white;text-decoration: none;padding: 0.8rem 2rem;display: block;transition: background-color 0.3s;}
        .sidebar ul li a:hover {background-color: #2c3e50;}
        .content {flex: 1;margin-left: 250px;padding: 2rem;}
        .calendar-container {background-color: white;border-radius: 8px;box-shadow: 0 2px 4px rgba(0,0,0,0.1);padding: 20px;
            max-width: 900px;margin: 0 auto;}
        .calendar-header {text-align: center;margin-bottom: 20px;}
        .calendar-header h2 {color: #e74c3c;font-size: 24px;font-weight: bold;}
        .calendar-grid {display: grid;grid-template-columns: repeat(7, 1fr);gap: 1px;background-color: #ddd;border: 1px solid #ddd;}
        .weekday-header {background-color: #f8f9fa;padding: 10px;text-align: center;font-weight: bold;font-size: 14px;}
        .calendar-day {background-color: white;min-height: 80px;padding: 5px;position: relative;}
        .calendar-day.empty {background-color: #f8f9fa;}
        .calendar-day .date-number {position: absolute;top: 5px;right: 5px;font-size: 16px;font-weight: bold;}
        .calendar-day.sunday .date-number {color: #e74c3c;}
        .calendar-day.saturday .date-number {color: #3498db;}
        .events-list {margin-top: 20px;}
        .events-list h3 {color: #2c3e50;margin-bottom: 15px;}
        .events-table {width: 100%;border-collapse: collapse;}
        .events-table th,.events-table td {padding: 10px;border: 1px solid #ddd;text-align: left;}
        .events-table th {background-color: #f8f9fa;font-weight: bold;}
        @media (max-width: 768px) {
            .sidebar {width: 100%;height: auto;position: relative;}
            .content {margin-left: 0;}
            .container {flex-direction: column;}
            .calendar-day {min-height: 60px;}}
        .calendar-controls {margin-bottom: 20px;text-align: center;}
        .calendar-controls select {padding: 8px 15px;margin: 0 10px;font-size: 16px;border: 1px solid #ddd;border-radius: 4px;cursor: pointer;}
        .event-list {margin-top: 25px;font-size: 12px;}
        .event-item {background-color: #3498db;color: white;padding: 2px 4px;margin-bottom: 2px;border-radius: 2px;white-space: nowrap;
            overflow: hidden;text-overflow: ellipsis;font-size: 11px;}
        .calendar-day {background-color: white;min-height: 100px;padding: 5px;position: relative;border: 1px solid #ddd;}
        .calendar-day .date-number {position: absolute;top: 5px;right: 5px;font-size: 16px;font-weight: bold;}
        .calendar-header {background-color: #f8f9fa;padding: 15px;margin-bottom: 10px;border-radius: 4px;}
        .calendar-header h2 {margin: 0;color: #e74c3c;}
        @media (max-width: 768px) {
            .calendar-day {min-height: 80px;}
            .event-item {font-size: 10px;}
            .calendar-controls select {width: 45%;margin: 5px;}}
        .flex-container {display: flex;gap: 20px;max-width: 1400px;margin: 0 auto;}
        .calendar-section {flex: 3;min-width: 0;}
        .events-section {flex: 1;min-width: 300px;background: white;border-radius: 8px;box-shadow: 0 2px 4px rgba(0,0,0,0.1);height: fit-content;}
        .events-list-header {padding: 15px;background-color: #34495e;color: white;border-top-left-radius: 8px;border-top-right-radius: 8px}
        .events-list-header h3 {margin: 0;font-size: 18px;}
        .events-list-container {padding: 15px;max-height: 600px;overflow-y: auto;}
        .event-list-item {padding: 10px;border-bottom: 1px solid #eee;}
        .event-list-item:last-child {border-bottom: none;}
        .event-date {font-size: 14px;color: #666;margin-bottom: 5px;}
        .event-name {font-size: 16px;color: #2c3e50;}
        .calendar-container {background-color: white;border-radius: 8px;box-shadow: 0 2px 4px rgba(0,0,0,0.1);padding: 15px;}
        .calendar-grid {display: grid;grid-template-columns: repeat(7, 1fr);gap: 1px;background-color: #ddd;border: 1px solid #ddd;}
        @media (max-width: 1024px) {
            .flex-container {flex-direction: column;}
            .events-section {min-width: 100%;}
            .calendar-section {width: 100%;}}
    </style>
</head>
<body>
    <header><div class="logo">Student Portal</div></header>
    <div class="container">
        <nav class="sidebar">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="room_booking.php"><i class="fas fa-bed"></i> Book Room</a></li>
                <li><a href="payment_history.php"><i class="fas fa-money-bill-wave"></i> Payments</a></li>
                <li><a href="access_log.php"><i class="fas fa-door-open"></i> Access Log</a></li>
                <li><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
        <main class="content">
            <div class="flex-container">
                <div class="calendar-section">
                    <div class="calendar-controls">
                        <select id="monthSelect" onchange="changeMonth(this.value)">
                            <?php
                            $months = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 
                                          'July', 'August', 'September', 'October', 'November', 'December');
                            foreach ($months as $num => $name) {
                                $selected = $num == $month ? 'selected' : '';
                                echo "<option value='$num' $selected>$name</option>";
                            }
                            ?>
                        </select>
                        <select id="yearSelect" onchange="changeYear(this.value)">
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 5; $y <= $currentYear + 5; $y++) {
                                $selected = $y == $year ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="calendar-container">
                        <div class="calendar-header"><h2><?php echo strtoupper($months[(int)$month]) . " " . $year; ?></h2></div>
                        <div class="calendar-grid">
                            <div class="weekday-header">SUN</div>
                            <div class="weekday-header">MON</div>
                            <div class="weekday-header">TUE</div>
                            <div class="weekday-header">WED</div>
                            <div class="weekday-header">THU</div>
                            <div class="weekday-header">FRI</div>
                            <div class="weekday-header">SAT</div>
                            <?php
                            $firstDay = date('w', strtotime("$year-$month-01"));
                            $daysInMonth = date('t', strtotime("$year-$month-01"));
                            for ($i = 0; $i < $firstDay; $i++) {echo '<div class="calendar-day empty"></div>';}
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $class = 'calendar-day';
                                if (date('w', strtotime("$year-$month-$day")) == 0) {$class .= ' sunday';}
                                if (date('w', strtotime("$year-$month-$day")) == 6) {$class .= ' saturday';}
                                echo "<div class='$class'>";
                                echo "<span class='date-number'>$day</span>";
                                if (isset($events[$day])) {
                                    echo "<div class='event-list'>";
                                    foreach ($events[$day] as $event) {echo "<div class='event-item'>" . htmlspecialchars($event) . "</div>";}
                                    echo "</div>";
                                }
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="events-section">
                    <div class="events-list-header"><h3>All Events</h3></div>
                    <div class="events-list-container">
                        <?php foreach ($allEvents as $event): ?>
                            <div class="event-list-item">
                                <div class="event-date"><?php echo date('d M Y', strtotime($event['event_date'])); ?></div>
                                <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
    function changeMonth(month) {
        const year = document.getElementById('yearSelect').value;
        window.location.href = `academic_events.php?year=${year}&month=${month}`;
    }
    function changeYear(year) {
        const month = document.getElementById('monthSelect').value;
        window.location.href = `academic_events.php?year=${year}&month=${month}`;
    }
    </script>
</body>
</html>