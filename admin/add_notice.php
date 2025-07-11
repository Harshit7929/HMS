<?php
include 'admin_db.php'; 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action == 'add') {
            $title = $_POST['title'];
            $message = $_POST['message'];
            $sql = "INSERT INTO notices (title, message) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $title, $message);
            $stmt->execute();
            header("Location: add_notice.php");
            exit();
        } 
        elseif ($action == 'edit') {
            $noticeId = $_POST['noticeId'];
            $title = $_POST['title'];
            $message = $_POST['message'];
            $sql = "UPDATE notices SET title=?, message=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $title, $message, $noticeId);
            $stmt->execute();
            header("Location: add_notice.php");
            exit();
        } 
        elseif ($action == 'delete') {
            $noticeId = intval($_POST['noticeId']);
            $sql = "DELETE FROM notices WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $noticeId);
            $stmt->execute();
            header("Location: add_notice.php");
            exit();
        }
    }
}
$notices = $conn->query("SELECT * FROM notices ORDER BY date_posted DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notices</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #3498db; --secondary-color: #2c3e50; --light-color: #ecf0f1; --dark-color: #34495e; 
            --success-color: #2ecc71; --danger-color: #e74c3c; --warning-color: #f39c12; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; display: flex; min-height: 100vh; }
        header { position: fixed; top: 0; left: 0; right: 0; background-color: var(--secondary-color); color: white; padding: 15px 20px; 
            font-size: 1.5rem; font-weight: bold; text-align: center; z-index: 1000; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .sidebar { position: fixed; top: 60px; left: 0; width: 250px; height: calc(100vh - 60px); background-color: var(--secondary-color); 
            padding: 20px 0; overflow-y: auto; transition: all 0.3s ease; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); }
        .sidebar a { display: block; padding: 15px 20px; color: var(--light-color); text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid var(--primary-color); color: white; }
        .sidebar a i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { flex: 1; margin-left: 250px; margin-top: 60px; padding: 30px; background-color: white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.05); }
        h2, h3 { color: var(--dark-color); margin-bottom: 20px; border-bottom: 2px solid var(--primary-color); padding-bottom: 10px; }
        .notice-form { background-color: var(--light-color); padding: 25px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .form-group label { font-weight: 600; color: var(--dark-color); }
        .form-control { border: 1px solid #ddd; border-radius: 4px; padding: 10px; transition: border-color 0.3s; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25); }
        .btn-primary { background-color: var(--primary-color); border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #2980b9; }
        .table { box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); border-radius: 8px; overflow: hidden; }
        .table thead th { background-color: var(--dark-color); color: white; font-weight: 600; border: none; }
        .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(236, 240, 241, 0.5); }
        .action-buttons { display: flex; gap: 5px; }
        .btn-warning { background-color: var(--warning-color); border: none; color: white; }
        .btn-warning:hover { background-color: #e67e22; }
        .btn-danger { background-color: var(--danger-color); border: none; }
        .btn-danger:hover { background-color: #c0392b; }
        .delete-form { display: inline; margin: 0; padding: 0; background: none; box-shadow: none; }
        @media (max-width: 992px) { .sidebar { width: 200px; } .main-content { margin-left: 200px; } }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: relative; top: 60px; } 
        .main-content { margin-left: 0; margin-top: 120px; padding: 15px; } body { flex-direction: column; } }
    </style>
</head>
<body>
<header>Admin Panel</header>
<div class="sidebar">
    <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="update_profile.php"><i class="fas fa-user-edit"></i> Profile</a>
    <a href="admin_access_log.php"><i class="fas fa-user-shield"></i> Admin Access Log</a>
    <a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="main-content">
    <h2>Add/Edit Notice</h2>
    <form action="add_notice.php" method="POST" class="notice-form">
        <input type="hidden" id="noticeId" name="noticeId">
        <input type="hidden" id="formAction" name="action" value="add">
        <div class="form-group"> 
            <label for="title">Notice Title</label>
            <input type="text" id="title" name="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="message">Notice Message</label>
            <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Add Notice</button>
    </form>
    <h3 class="mt-5">Notice History</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Title</th> <th>Message</th>
                <th>Date Posted</th> <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $notices->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['message']) ?></td>
                    <td><?= $row['date_posted'] ?></td>
                    <td class="action-buttons">
                        <button onclick="editNotice(<?= $row['id'] ?>, '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['message'], ENT_QUOTES) ?>')" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form action="add_notice.php" method="POST" class="delete-form">
                            <input type="hidden" name="noticeId" value="<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this notice?');">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script>
    function editNotice(noticeId, title, message) {
        document.getElementById('title').value = title;
        document.getElementById('message').value = message;
        document.getElementById('noticeId').value = noticeId;
        document.getElementById('formAction').value = "edit";
        document.querySelector('button[type="submit"]').textContent = "Update Notice";
        window.scrollTo({top: 0,behavior: 'smooth'});
    }
</script>
</body>
</html>