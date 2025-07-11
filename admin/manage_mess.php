<?php
include 'admin_db.php';
session_start();
$message = "";
$alertType = "";
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'menu';
$selectedDay = isset($_GET['day']) ? $_GET['day'] : '1'; 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_menu'])) {
        $day_order = $_POST['day_order'];
        $cuisine_type = $_POST['cuisine_type'];
        $breakfast = $_POST['breakfast'];
        $lunch = $_POST['lunch'];
        $snacks = $_POST['snacks'];
        $dinner = $_POST['dinner'];
        $query = "UPDATE mess_menu SET 
                  breakfast = ?, 
                  lunch = ?, 
                  snacks = ?, 
                  dinner = ? 
                  WHERE day_order = ? AND cuisine_type = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $breakfast, $lunch, $snacks, $dinner, $day_order, $cuisine_type);
        if ($stmt->execute()) {
            $message = "Menu updated successfully!";
            $alertType = "success";
        } else {
            $message = "Error updating menu: " . $conn->error;
            $alertType = "error";
        }
        $activeTab = 'menu';
        $selectedDay = $day_order; 
    }
}
function getAverageRating($conn, $meal_type, $date_range = 'all') {
    $query = "SELECT AVG(rating) as avg_rating FROM mess_feedback WHERE meal_type = ?";
    if ($date_range === 'week') {$query .= " AND feedback_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";} 
    elseif ($date_range === 'month') {$query .= " AND feedback_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";}
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $meal_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['avg_rating'] === null) {return 'N/A';} 
    else {return number_format($row['avg_rating'], 1);}
}
function getFeedbackCount($conn, $meal_type, $date_range = 'all') {
    $query = "SELECT COUNT(*) as count FROM mess_feedback WHERE meal_type = ?";
    if ($date_range === 'week') {$query .= " AND feedback_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";} 
    elseif ($date_range === 'month') {$query .= " AND feedback_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";}
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $meal_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$meal_filter = isset($_GET['meal_filter']) ? $_GET['meal_filter'] : 'all';
$rating_filter = isset($_GET['rating_filter']) ? $_GET['rating_filter'] : 'all';
$feedback_query = "SELECT f.*, s.firstName, s.lastName 
                  FROM mess_feedback f
                  JOIN student_signup s ON f.regNo = s.regNo
                  WHERE 1=1";
if ($date_filter === 'today') {$feedback_query .= " AND f.feedback_date = CURDATE()";} 
elseif ($date_filter === 'week') {$feedback_query .= " AND f.feedback_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";} 
elseif ($date_filter === 'month') {$feedback_query .= " AND f.feedback_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";}
if ($meal_filter !== 'all') {$feedback_query .= " AND f.meal_type = '" . mysqli_real_escape_string($conn, $meal_filter) . "'";}
if ($rating_filter !== 'all') {$feedback_query .= " AND f.rating = " . intval($rating_filter);}
$feedback_query .= " ORDER BY f.created_at DESC LIMIT 100";
$days_query = "SELECT DISTINCT day_order, day_name FROM mess_menu ORDER BY day_order";
$days_result = $conn->query($days_query);
$days = [];
while ($day = $days_result->fetch_assoc()) {$days[] = $day;}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Mess - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="css/manage_mess.css"> -->
     <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { display: flex; flex-direction: column; min-height: 100vh; }
        header { background-color: #1a237e; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .logo h1 { font-size: 1.5rem; font-weight: 600; }
        .user-info span { background-color: rgba(255, 255, 255, 0.2); padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.9rem; }
        .content-wrapper { display: flex; flex: 1; }
        .sidebar { width: 250px; background-color: #fff; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05); }
        .sidebar nav ul { list-style: none; }
        .sidebar nav ul li a { display: flex; align-items: center; padding: 1rem 1.5rem; color: #5c6778; text-decoration: none; transition: all 0.3s ease; border-left: 3px solid transparent; }
        .sidebar nav ul li a:hover { background-color: #f0f4f8; color: #1a237e; border-left: 3px solid #1a237e; }
        .sidebar nav ul li a.active { background-color: #e8eaf6; color: #1a237e; border-left: 3px solid #1a237e; font-weight: 600; }
        .sidebar nav ul li a i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { flex: 1; padding: 2rem; }
        .main-content h2 { color: #1a237e; margin-bottom: 1.5rem; font-weight: 600; }
        .section { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); padding: 1.5rem; margin-bottom: 2rem; }
        .section h3 { color: #3f51b5; margin-bottom: 1rem; font-weight: 500; }
        .section h4 { color: #455a64; margin-bottom: 0.8rem; font-weight: 500; }
        .alert { padding: 0.8rem 1rem; margin-bottom: 1.5rem; border-radius: 5px; transition: opacity 0.5s ease; }
        .alert-success { background-color: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .alert-error { background-color: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .tabs { margin-top: 1rem; }
        .tab-links { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 1rem; }
        .tab-links a { padding: 0.8rem 1.5rem; text-decoration: none; color: #5c6778; font-weight: 500; transition: all 0.3s ease; border-bottom: 2px solid transparent; margin-right: 0.5rem; }
        .tab-links a:hover { color: #3f51b5; }
        .tab-links a.active { color: #1a237e; border-bottom: 2px solid #1a237e; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .cuisine-selector { display: flex; margin-bottom: 1.5rem; }
        .cuisine-btn { padding: 0.6rem 1.2rem; background-color: #f0f4f8; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.3s ease; margin-right: 0.5rem; font-weight: 500; color: #5c6778; }
        .cuisine-btn:hover { background-color: #e8eaf6; }
        .cuisine-btn.active { background-color: #3f51b5; color: white; border-color: #3f51b5; }
        .day-selector { margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #455a64; }
        .form-group textarea { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px; resize: vertical; font-family: inherit; transition: border-color 0.3s ease; }
        .form-group textarea:focus { outline: none; border-color: #3f51b5; box-shadow: 0 0 0 2px rgba(63, 81, 181, 0.1); }
        .form-actions { text-align: right; margin-top: 1rem; }
        .btn { padding: 0.6rem 1.2rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary { background-color: #3f51b5; color: white; }
        .btn-primary:hover { background-color: #303f9f; }
        .btn-secondary { background-color: #e0e0e0; color: #424242; }
        .btn-secondary:hover { background-color: #d5d5d5; }
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .stats-card { background-color: #fff; border-radius: 8px; padding: 1.2rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); }
        .feedback-filters { margin-bottom: 1.5rem; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; padding: 1rem; background-color: #f0f4f8; border-radius: 8px; }
        .feedback-list { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        thead { background-color: #f0f4f8; }
        th, td { padding: 0.8rem; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { font-weight: 600; color: #455a64; }
        footer { background-color: #1a237e; color: rgba(255, 255, 255, 0.7); text-align: center; padding: 1rem; font-size: 0.9rem; }
        @media (max-width: 1024px) { .stats-cards { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .content-wrapper { flex-direction: column; } .sidebar { width: 100%; } .filter-form { flex-direction: column; } .filter-group { width: 100%; } }
        @media (max-width: 576px) {
        .tab-links { flex-direction: column; }
        .tab-links a { width: 100%; border-bottom: none; border-left: 2px solid transparent; }
        .tab-links a.active { border-bottom: none; border-left: 2px solid #1a237e; }
        header { flex-direction: column; text-align: center; gap: 0.5rem; }
        .main-content { padding: 1rem; }
        }
     </style>
        
</head>
<body>
    <div class="container">
        <header>
            <div class="logo"><h1>Hostel Management System</h1></div>
            <div class="user-info"><span>Admin Panel</span></div>
        </header>
        <div class="content-wrapper">
            <div class="sidebar">
                <nav>
                    <ul>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="manage_students.php"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
                        <li><a href="manage_rooms.php"><i class="fas fa-door-open"></i> Manage Rooms</a></li>
                        <li><a href="manage_mess.php" class="active"><i class="fas fa-utensils"></i> Manage Mess</a></li>
                        <li><a href="admin_complaints.php"><i class="fas fa-comment-dots"></i> Complaints</a></li>
                        <!-- <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li> -->
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </nav>
            </div>
            <div class="main-content">
                <h2>Mess Management</h2>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $alertType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                <div class="tabs">
                    <div class="tab-links">
                        <a href="?tab=menu" class="<?php echo $activeTab === 'menu' ? 'active' : ''; ?>">Manage Menu</a>
                        <a href="?tab=feedback" class="<?php echo $activeTab === 'feedback' ? 'active' : ''; ?>">Student Feedback</a>
                    </div>
                    <div class="tab-content <?php echo $activeTab === 'menu' ? 'active' : ''; ?>" id="menu-tab">
                        <div class="section">
                            <h3>Mess Menu Configuration</h3>
                            <div class="cuisine-selector">
                                <button class="cuisine-btn active" data-cuisine="Indian">Indian Menu</button>
                                <button class="cuisine-btn" data-cuisine="International">International Menu</button>
                            </div>
                            <div class="day-selector">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" id="day-select-form">
                                    <input type="hidden" name="tab" value="menu">
                                    <label for="day-select">Select Day:</label>
                                    <select id="day-select" name="day" onchange="this.form.submit()">
                                        <?php foreach ($days as $day): ?>
                                        <option value="<?php echo $day['day_order']; ?>" <?php echo ($selectedDay == $day['day_order'] ? 'selected' : ''); ?>>
                                            <?php echo $day['day_name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="menu-days">
                                <div class="menu-table active" id="indian-menu">
                                    <?php
                                    $indian_menu_query = "SELECT * FROM mess_menu WHERE cuisine_type = 'Indian' AND day_order = ?";
                                    $stmt = $conn->prepare($indian_menu_query);
                                    $stmt->bind_param("s", $selectedDay);
                                    $stmt->execute();
                                    $indian_result = $stmt->get_result();
                                    $indian_menu = $indian_result->fetch_assoc();
                                    if ($indian_menu):
                                    ?>
                                    <div class="day-card" data-day="<?php echo $indian_menu['day_order']; ?>" data-cuisine="Indian">
                                        <h4><?php echo $indian_menu['day_name']; ?> - Indian Menu</h4>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="menu-form">
                                            <input type="hidden" name="day_order" value="<?php echo $indian_menu['day_order']; ?>">
                                            <input type="hidden" name="cuisine_type" value="<?php echo $indian_menu['cuisine_type']; ?>">
                                            <div class="form-group">
                                                <label for="breakfast-<?php echo $indian_menu['day_order']; ?>">Breakfast:</label>
                                                <textarea id="breakfast-<?php echo $indian_menu['day_order']; ?>" name="breakfast" rows="3"><?php echo htmlspecialchars($indian_menu['breakfast']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="lunch-<?php echo $indian_menu['day_order']; ?>">Lunch:</label>
                                                <textarea id="lunch-<?php echo $indian_menu['day_order']; ?>" name="lunch" rows="3"><?php echo htmlspecialchars($indian_menu['lunch']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="snacks-<?php echo $indian_menu['day_order']; ?>">Snacks:</label>
                                                <textarea id="snacks-<?php echo $indian_menu['day_order']; ?>" name="snacks" rows="3"><?php echo htmlspecialchars($indian_menu['snacks']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="dinner-<?php echo $indian_menu['day_order']; ?>">Dinner:</label>
                                                <textarea id="dinner-<?php echo $indian_menu['day_order']; ?>" name="dinner" rows="3"><?php echo htmlspecialchars($indian_menu['dinner']); ?></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" name="update_menu" class="btn btn-primary">Update Menu</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="menu-table" id="international-menu">
                                    <?php
                                    $int_menu_query = "SELECT * FROM mess_menu WHERE cuisine_type = 'International' AND day_order = ?";
                                    $stmt = $conn->prepare($int_menu_query);
                                    $stmt->bind_param("s", $selectedDay);
                                    $stmt->execute();
                                    $int_result = $stmt->get_result();
                                    $int_menu = $int_result->fetch_assoc();
                                    if ($int_menu):
                                    ?>
                                    <div class="day-card" data-day="<?php echo $int_menu['day_order']; ?>" data-cuisine="International">
                                        <h4><?php echo $int_menu['day_name']; ?> - International Menu</h4>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="menu-form">
                                            <input type="hidden" name="day_order" value="<?php echo $int_menu['day_order']; ?>">
                                            <input type="hidden" name="cuisine_type" value="<?php echo $int_menu['cuisine_type']; ?>">
                                            <div class="form-group">
                                                <label for="breakfast-int-<?php echo $int_menu['day_order']; ?>">Breakfast:</label>
                                                <textarea id="breakfast-int-<?php echo $int_menu['day_order']; ?>" name="breakfast" rows="3"><?php echo htmlspecialchars($int_menu['breakfast']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="lunch-int-<?php echo $int_menu['day_order']; ?>">Lunch:</label>
                                                <textarea id="lunch-int-<?php echo $int_menu['day_order']; ?>" name="lunch" rows="3"><?php echo htmlspecialchars($int_menu['lunch']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="snacks-int-<?php echo $int_menu['day_order']; ?>">Snacks:</label>
                                                <textarea id="snacks-int-<?php echo $int_menu['day_order']; ?>" name="snacks" rows="3"><?php echo htmlspecialchars($int_menu['snacks']); ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="dinner-int-<?php echo $int_menu['day_order']; ?>">Dinner:</label>
                                                <textarea id="dinner-int-<?php echo $int_menu['day_order']; ?>" name="dinner" rows="3"><?php echo htmlspecialchars($int_menu['dinner']); ?></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button type="submit" name="update_menu" class="btn btn-primary">Update Menu</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-content <?php echo $activeTab === 'feedback' ? 'active' : ''; ?>" id="feedback-tab">
                        <div class="section">
                            <h3>Student Feedback Analysis</h3>
                            <div class="stats-cards">
                                <div class="stats-card">
                                    <h4>Overall Ratings</h4>
                                    <div class="rating-overview">
                                        <div class="meal-rating">
                                            <span>Breakfast</span>
                                            <div class="stars">
                                                <?php 
                                                $breakfast_rating = getAverageRating($conn, 'Breakfast');
                                                echo $breakfast_rating; 
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Breakfast'); ?> reviews)</span>
                                        </div>
                                        <div class="meal-rating">
                                            <span>Lunch</span>
                                            <div class="stars">
                                                <?php 
                                                $lunch_rating = getAverageRating($conn, 'Lunch');
                                                echo $lunch_rating; 
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Lunch'); ?> reviews)</span>
                                        </div>
                                        <div class="meal-rating">
                                            <span>Snacks</span>
                                            <div class="stars">
                                                <?php 
                                                $snacks_rating = getAverageRating($conn, 'Snacks');
                                                echo $snacks_rating;
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Snacks'); ?> reviews)</span>
                                        </div>
                                        <div class="meal-rating">
                                            <span>Dinner</span>
                                            <div class="stars">
                                                <?php 
                                                $dinner_rating = getAverageRating($conn, 'Dinner');
                                                echo $dinner_rating;
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Dinner'); ?> reviews)</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="stats-card">
                                    <h4>This Week's Rating</h4>
                                    <div class="rating-overview">
                                        <div class="meal-rating">
                                            <span>Breakfast</span>
                                            <div class="stars">
                                                <?php 
                                                $breakfast_rating_week = getAverageRating($conn, 'Breakfast', 'week');
                                                echo $breakfast_rating_week;
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Breakfast', 'week'); ?> reviews)</span>
                                        </div>
                                        <div class="meal-rating">
                                            <span>Lunch</span>
                                            <div class="stars">
                                                <?php 
                                                $lunch_rating_week = getAverageRating($conn, 'Lunch', 'week');
                                                echo $lunch_rating_week; 
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Lunch', 'week'); ?> reviews)</span>
                                        </div>
                                        <div class="meal-rating">
                                            <span>Snacks</span>
                                            <div class="stars">
                                                <?php 
                                                $snacks_rating_week = getAverageRating($conn, 'Snacks', 'week');
                                                echo $snacks_rating_week;
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Snacks', 'week'); ?> reviews)</span>
                                        </div>
                                        <div class="meal-rating">
                                            <span>Dinner</span>
                                            <div class="stars">
                                                <?php 
                                                $dinner_rating_week = getAverageRating($conn, 'Dinner', 'week');
                                                echo $dinner_rating_week;
                                                ?> / 5
                                            </div>
                                            <span class="count">(<?php echo getFeedbackCount($conn, 'Dinner', 'week'); ?> reviews)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="feedback-filters">
                                <h4>Filter Feedback</h4>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                                    <input type="hidden" name="tab" value="feedback">
                                    <div class="filter-group">
                                        <label for="date_filter">Date Range:</label>
                                        <select id="date_filter" name="date_filter">
                                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="meal_filter">Meal Type:</label>
                                        <select id="meal_filter" name="meal_filter">
                                            <option value="all" <?php echo $meal_filter === 'all' ? 'selected' : ''; ?>>All Meals</option>
                                            <option value="Breakfast" <?php echo $meal_filter === 'Breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                                            <option value="Lunch" <?php echo $meal_filter === 'Lunch' ? 'selected' : ''; ?>>Lunch</option>
                                            <option value="Snacks" <?php echo $meal_filter === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                                            <option value="Dinner" <?php echo $meal_filter === 'Dinner' ? 'selected' : ''; ?>>Dinner</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="rating_filter">Rating:</label>
                                        <select id="rating_filter" name="rating_filter">
                                            <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                                            <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                                            <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                                            <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                                            <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                                            <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                                        </select>
                                    </div>
                                    <div class="filter-actions">
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                        <a href="?tab=feedback" class="btn btn-secondary">Reset</a>
                                    </div>
                                </form>
                            </div>
                            <div class="feedback-list">
                                <h4>Student Feedback</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th> <th>Student</th> <th>Meal</th>
                                            <th>Rating</th> <th>Feedback</th> <th>Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $feedback_result = $conn->query($feedback_query);
                                        if ($feedback_result && $feedback_result->num_rows > 0) {
                                            while ($feedback = $feedback_result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . date('d M Y', strtotime($feedback['feedback_date'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($feedback['firstName'] . ' ' . $feedback['lastName']) . "</td>";
                                                echo "<td>" . htmlspecialchars($feedback['meal_type']) . "</td>";
                                                echo "<td><div class='rating-display'>";
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $feedback['rating']) {echo "<span class='star filled'>★</span>";} 
                                                    else { echo "<span class='star'>★</span>";}
                                                }
                                                echo "</div></td>";
                                                echo "<td>" . htmlspecialchars($feedback['feedback']) . "</td>";
                                                echo "<td>" . date('d M Y H:i', strtotime($feedback['created_at'])) . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {echo "<tr><td colspan='6'>No feedback found matching your filters</td></tr>";}
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer><p>&copy; <?php echo date('Y'); ?> Hostel Management System. All rights reserved.</p></footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alertBox = document.querySelector('.alert');
        if (alertBox) {
            setTimeout(function() {
                alertBox.style.opacity = '0';
                setTimeout(function() {alertBox.style.display = 'none';}, 500);
            }, 5000);
        }
        const cuisineButtons = document.querySelectorAll('.cuisine-btn');
        const menuTables = document.querySelectorAll('.menu-table');
        cuisineButtons.forEach(button => {
            button.addEventListener('click', function() {
                cuisineButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                const cuisine = this.getAttribute('data-cuisine');
                menuTables.forEach(table => {table.classList.remove('active');});    
                if (cuisine === 'Indian') {document.getElementById('indian-menu').classList.add('active');} 
                else if (cuisine === 'International') {document.getElementById('international-menu').classList.add('active');}
            });
        });
    });
    </script>
</body>
</html>