<?php
include 'admin_db.php';
session_start();

function getStaffList($conn) {
    $query = "SELECT * FROM staff ORDER BY id DESC";
    return $conn->query($query);
}

if (isset($_POST['add_staff'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $staff_id = $_POST['staff_id'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $password = $_POST['password'];
    $hostels = isset($_POST['hostels']) ? implode(',', $_POST['hostels']) : '';

    try {
        $stmt = $conn->prepare("INSERT INTO staff (name, email, staff_id, position, department, password, hostel) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $email, $staff_id, $position, $department, $password, $hostels);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Staff added successfully!";
        } else {
            throw new Exception("Error adding staff.");
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $_SESSION['error'] = "Duplicate entry for Staff ID.";
        } else {
            $_SESSION['error'] = $e->getMessage();
        } 
    }
    
    header("Location: manage_staff.php");
    exit();
}

if (isset($_POST['update_staff'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $staff_id = $_POST['staff_id'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $password = $_POST['password'];
    $hostels = isset($_POST['hostels']) ? implode(',', $_POST['hostels']) : '';

    $stmt = $conn->prepare("UPDATE staff SET name=?, email=?, staff_id=?, position=?, department=?, password=?, hostel=? WHERE id=?");
    $stmt->bind_param("sssssssi", $name, $email, $staff_id, $position, $department, $password, $hostels, $id);
    
    $_SESSION[$stmt->execute() ? 'success' : 'error'] = $stmt->execute() ? "Staff details updated!" : "Error updating staff.";
    header("Location: manage_staff.php");
    exit();
}

$staff_list = getStaffList($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <title>Staff Management</title>
    <!-- <link rel="stylesheet" href="css/manage_staff.css"> -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Arial", sans-serif; }
        body { background-color: #f5f5f5; color: #333; line-height: 1.6; }
        header { background-color: #2c3e50; color: white; padding: 1rem 2rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .container { display: flex; min-height: calc(100vh - 60px); }
        .sidebar { width: 250px; background-color: #34495e; color: white; padding: 20px 0; box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1); }
        .sidebar ul { list-style: none; }
        .sidebar ul li { padding: 10px 0; }
        .sidebar ul li a { color: white; text-decoration: none; padding: 10px 20px; display: block; transition: background-color 0.3s; }
        .sidebar ul li a:hover { background-color: #2c3e50; }
        .sidebar ul li a.active { background-color: #2980b9; border-left: 4px solid #3498db; }
        .content { flex: 1; padding: 20px; }
        h2 { color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        h3 { color: #34495e; margin: 20px 0 15px; }
        form { background-color: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        input, button { display: block; width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #2980b9; color: white; border: none; cursor: pointer; transition: background-color 0.3s; }
        button:hover { background-color: #3498db; }
        .checkbox-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .checkbox-container .hostel-option { background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 5px; padding: 10px; display: flex; align-items: center; cursor: pointer; transition: background-color 0.3s ease; }
        .checkbox-container .hostel-option:hover { background-color: #e0e0e0; }
        .checkbox-container input[type="checkbox"] { display: none; }
        .checkbox-container .hostel-option.selected { background-color: #007bff; color: white; }
        table { width: 100%; border-collapse: collapse; background-color: white; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background-color: #34495e; color: white; }
        table tr:hover { background-color: #f9f9f9; }
        table button, table a { display: inline-block; padding: 6px 10px; margin: 2px; font-size: 0.9em; width: auto; }
        table a { background-color: #e74c3c; color: white; text-decoration: none; border-radius: 4px; }
        table a:hover { background-color: #c0392b; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 768px) {
        .container { flex-direction: column; }
        .sidebar { width: 100%; padding: 10px 0; }
        .sidebar ul li { display: inline-block; }
        .sidebar ul li a { padding: 8px 15px; }
        table { font-size: 0.9em; }
        table th, table td { padding: 8px 10px; }
        table button, table a { padding: 4px 8px; font-size: 0.8em; }}
    </style>
</head>
<body>
    <header><h1>Admin Dashboard</h1></header>
    <div class="container">
        <aside class="sidebar">
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_staff.php" class="active"><i class="fas fa-users"></i> Staff Management</a></li>
                <li><a href="staff_access_log.php"><i class="fas fa-clipboard-list"></i> Staff Access Log</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        <main class="content">
            <h2>Staff Management</h2>
            <?php if (isset($_SESSION['success'])) { echo "<div class='alert success'>{$_SESSION['success']}</div>"; unset($_SESSION['success']); } ?>
            <?php if (isset($_SESSION['error'])) { echo "<div class='alert error'>{$_SESSION['error']}</div>"; unset($_SESSION['error']); } ?>
            <form method="post" id="staffForm">
                <h3 id="formTitle">Add New Staff</h3>
                <input type="hidden" name="id">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="staff_id" placeholder="Staff ID" required>
                <input type="text" name="position" placeholder="Position" required>
                <input type="text" name="department" placeholder="Department" required>
                <input type="text" name="password" placeholder="Password" required>
                <div class="checkbox-container">
                    <label class="hostel-option">
                        <input type="checkbox" name="hostels[]" value="vedavathi">
                        <span>Vedavathi</span>
                    </label>
                    <label class="hostel-option">
                        <input type="checkbox" name="hostels[]" value="ganga">
                        <span>Ganga</span>
                    </label>
                    <label class="hostel-option">
                        <input type="checkbox" name="hostels[]" value="narmadha">
                        <span>Narmadha</span>
                    </label>
                    <label class="hostel-option">
                        <input type="checkbox" name="hostels[]" value="yamuna">
                        <span>Yamuna</span>
                    </label>
                    <label class="hostel-option">
                        <input type="checkbox" name="hostels[]" value="krishna">
                        <span>Krishna</span>
                    </label>
                </div>
                <button type="submit" name="add_staff">Add Staff</button>
                <button type="submit" name="update_staff" style="display:none;">Update Staff</button>
            </form>
            <h3>All Staff Members</h3>
            <table>
                <thead>
                    <tr><th>ID</th><th>Staff ID</th><th>Name</th><th>Email</th><th>Position</th><th>Department</th><th>Hostels</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php while ($row = $staff_list->fetch_assoc()) { 
                        $hostels = explode(',', $row['hostel']);
                        $hostels_display = is_array($hostels) ? implode(', ', $hostels) : '';
                    ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= $row['staff_id'] ?></td>
                            <td><?= $row['name'] ?></td>
                            <td><?= $row['email'] ?></td>
                            <td><?= $row['position'] ?></td>
                            <td><?= $row['department'] ?></td>
                            <td><?= $hostels_display ?></td>
                            <td>
                                <button onclick="editStaff(<?= $row['id'] ?>, '<?= $row['name'] ?>', '<?= $row['email'] ?>', '<?= $row['staff_id'] ?>', '<?= $row['position'] ?>', '<?= $row['department'] ?>', '<?= $row['password'] ?>', '<?= htmlspecialchars(json_encode($hostels)) ?>')">Edit</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </main>
    </div>
<script>
    document.querySelectorAll('.hostel-option').forEach(label => {
        label.addEventListener('click', () => {
            const checkbox = label.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            label.classList.toggle('selected', checkbox.checked);
        });
    });
    function editStaff(id, name, email, staff_id, position, department, password, hostels) {
        document.querySelector('input[name="id"]').value = id;
        document.querySelector('input[name="name"]').value = name;
        document.querySelector('input[name="email"]').value = email;
        document.querySelector('input[name="staff_id"]').value = staff_id;
        document.querySelector('input[name="position"]').value = position;
        document.querySelector('input[name="department"]').value = department;
        document.querySelector('input[name="password"]').value = password;
        const hostelsArray = JSON.parse(hostels);
        document.querySelectorAll('input[name="hostels[]"]').forEach(checkbox => {
            checkbox.checked = hostelsArray.includes(checkbox.value);
            checkbox.closest('label').classList.toggle('selected', checkbox.checked);
        });
        document.querySelector('button[name="add_staff"]').style.display = "none";
        document.querySelector('button[name="update_staff"]').style.display = "inline-block";
        document.getElementById("formTitle").innerText = "Update Staff";
    }
</script>
</body>
</html>