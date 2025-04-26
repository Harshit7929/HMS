<?php
require_once 'db.php';
function getNotices($filter = 'latest') {
    global $conn;
    $sql = "SELECT * FROM notices";
    switch($filter) {
        case 'week':
            $sql .= " WHERE date_posted >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $sql .= " WHERE date_posted >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'latest':
        default:
            break;
    }
    $sql .= " ORDER BY date_posted DESC";
    $result = $conn->query($sql);
    $notices = [];
    if ($result && $result->num_rows > 0) {while($row = $result->fetch_assoc()) {$notices[] = $row;}}
    return $notices;
}
$filter = 'latest'; 
if (isset($_GET['filter'])) {if ($_GET['filter'] == 'week' || $_GET['filter'] == 'month') { $filter = $_GET['filter'];}}
$notices = getNotices($filter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Notice Board</title>
    <link rel="stylesheet" href="noticeboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; background-color: #f5f5f5; color: #333; }
      a { text-decoration: none; color: #3a6ea5; }
      a:hover { text-decoration: underline; }
      .container { max-width: 1200px; margin: 0 auto; padding: 20px; background-color: #fff; min-height: 100vh; display: flex; flex-direction: column; box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); }
      .header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; color: white; background: linear-gradient(to right, #3a6ea5, #5d93c7); border-radius: 8px 8px 0 0; margin-bottom: 20px; box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1); }
      .logo { display: flex; align-items: center; }
      .logo i { font-size: 24px; margin-right: 15px; color: #ffeb3b; }
      .logo h1 { font-size: 24px; font-weight: 600; }
      .header-right { font-size: 16px; }
      .main-content { display: flex; flex: 1; gap: 20px; }
      .sidebar { flex: 0 0 300px; background-color: #f9f9f9; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
      .sidebar-header h3 { font-size: 18px; color: #3a6ea5; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #e1e1e1; }
      .sidebar-section { margin-top: 25px; }
      .sidebar-section h3 { font-size: 16px; color: #3a6ea5; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #e1e1e1; }
      .sidebar-section p { font-size: 14px; color: #555; margin-bottom: 10px; }
      .quick-links { list-style: none; }
      .quick-links li { margin-bottom: 8px; font-size: 14px; }
      .quick-links a { display: flex; align-items: center; padding: 8px 5px; border-radius: 4px; transition: background-color 0.2s; }
      .quick-links a:hover { background-color: #e9e9e9; text-decoration: none; }
      .quick-links i { margin-right: 10px; color: #3a6ea5; width: 16px; text-align: center; }
      .notice-board { flex: 1; background-color: #f0f0f0; background-image: linear-gradient(#e9e9e9 1px, transparent 1px), linear-gradient(90deg, #e9e9e9 1px, transparent 1px); background-size: 20px 20px; border-radius: 8px; padding: 20px; position: relative; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); background-color: #d7b889; }
      .board-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background-color: rgba(255, 255, 255, 0.9); padding: 10px 15px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
      .board-header h2 { font-size: 20px; color: #333; }
      .view-options a { margin: 0 5px; padding: 3px 8px; border-radius: 3px; }
      .view-options a.active { background-color: #3a6ea5; color: white; }
      .notices-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
      .notice { background-color: #fff8dc; border-radius: 4px; padding: 15px; position: relative; box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.2); transform: rotate(0.5deg); transition: transform 0.2s, box-shadow 0.2s; }
      .notice:nth-child(even) { transform: rotate(-0.5deg); background-color: #ffffe0; }
      .notice:hover { transform: scale(1.02) rotate(0); box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.25); z-index: 10; }
      .notice-pin { position: absolute; top: -5px; left: 50%; transform: translateX(-50%); color: #e74c3c; font-size: 18px; }
      .notice-title { font-size: 18px; margin-top: 8px; margin-bottom: 10px; color: #333; }
      .notice-content { color: #555; font-size: 14px; margin-bottom: 12px; }
      .notice-footer { font-size: 12px; color: #777; display: flex; justify-content: space-between; border-top: 1px dashed #ddd; padding-top: 8px; }
      .notice-date, .notice-time { display: flex; align-items: center; }
      .notice-date i, .notice-time i { margin-right: 5px; }
      .empty-notice { grid-column: 1 / -1; text-align: center; padding: 30px; background-color: rgba(255, 255, 255, 0.8); border-radius: 8px; }
      .footer { text-align: center; margin-top: 30px; padding: 15px; background-color: #f9f9f9; border-top: 1px solid #eee; color: #777; font-size: 14px; border-radius: 0 0 8px 8px; }
      @media (max-width: 768px) { 
        .main-content { flex-direction: column; }
        .sidebar { flex: none; width: 100%; margin-bottom: 20px; }
        .notices-container { grid-template-columns: 1fr; }
        .header { flex-direction: column; text-align: center; }
        .logo { margin-bottom: 10px; }
      }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <i class="fas fa-thumbtack"></i>
                <h1>Student Notice Board</h1>
            </div>
            <div class="header-right"><span class="date"><?php echo date("F j, Y"); ?></span></div>
        </header>
        <div class="main-content">
            <div class="sidebar">
                <div class="sidebar-header"><h3>Student Information</h3></div>
                <div class="sidebar-section">
                    <h3>About</h3>
                    <p>This notice board displays important announcements from administration. Check back regularly for updates.</p>
                </div>
                <div class="sidebar-section">
                    <h3>Quick Links</h3>
                    <ul class="quick-links">
                        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="academic_events.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
                        <li><a href="payment_history.php"><i class="fas fa-money-bill-wave"></i> Payment</a></li>
                        <!-- <li><a href="#"><i class="fas fa-book-open"></i> Library</a></li>
                        <li><a href="#"><i class="fas fa-question-circle"></i> Help Desk</a></li> -->
                    </ul>
                </div>
            </div>
            <div class="notice-board">
                <div class="board-header">
                    <h2>Notices</h2>
                    <div class="view-options">
                        <span>View: </span>
                        <a href="?filter=latest" <?php if($filter == 'latest') echo 'class="active"'; ?>>Latest</a> | 
                        <a href="?filter=week" <?php if($filter == 'week') echo 'class="active"'; ?>>This Week</a> | 
                        <a href="?filter=month" <?php if($filter == 'month') echo 'class="active"'; ?>>This Month</a>
                    </div>
                </div>
                <div class="notices-container">
                    <?php if (empty($notices)): ?>
                        <div class="empty-notice">
                            <p>No notices available for the selected time period. Please check back later or try a different filter.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $notice): ?>
                            <div class="notice">
                                <div class="notice-pin"><i class="fas fa-thumbtack"></i></div>
                                <h3 class="notice-title"><?php echo htmlspecialchars($notice["title"]); ?></h3>
                                <div class="notice-content">
                                    <p><?php echo nl2br(htmlspecialchars($notice["message"])); ?></p>
                                </div>
                                <div class="notice-footer">
                                    <span class="notice-date">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php echo date("M j, Y", strtotime($notice["date_posted"])); ?>
                                    </span>
                                    <span class="notice-time">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date("g:i A", strtotime($notice["date_posted"])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <footer class="footer">
            <p>&copy; <?php echo date("Y"); ?> Student Notice Board. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>