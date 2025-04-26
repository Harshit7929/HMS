<?php
include 'db.php';
session_start();
$regNo = "";
$message = "";
$alertType = "";
if (!isset($_SESSION['regNo'])) {
    $sql = "SELECT student_email, user_id FROM login_details ORDER BY login_time DESC LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $student_email = $row['student_email'];
        $query = "SELECT regNo FROM student_signup WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $student_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $regNo = $row['regNo'];
            $_SESSION['regNo'] = $regNo; 
        } else {
            echo "<p style='color: red; text-align: center; font-size: 18px;'>Student not found. Please log in again.</p>";
            exit;
        }
    } else {
        echo "<p style='color: red; text-align: center; font-size: 18px;'>No student login records found. Please log in.</p>";
        exit;
    }
} else {$regNo = $_SESSION['regNo'];}
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = "Feedback submitted successfully.";
    $alertType = "success";
}
else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['check_duplicate']) && $_POST['check_duplicate'] == 1) {
        $feedback_date = mysqli_real_escape_string($conn, $_POST['feedback_date']);
        $meal_type = mysqli_real_escape_string($conn, $_POST['meal_type']);
        $check_duplicate = "SELECT id FROM mess_feedback WHERE regNo = ? AND feedback_date = ? AND meal_type = ?";
        $check_dup_stmt = $conn->prepare($check_duplicate);
        $check_dup_stmt->bind_param("sss", $regNo, $feedback_date, $meal_type);
        $check_dup_stmt->execute();
        $check_dup_result = $check_dup_stmt->get_result();
        if ($check_dup_result->num_rows > 0) {
            echo "<div class='alert alert-error'>You have already submitted feedback for this meal on this date.</div>";}
        exit;
    }
    if (empty($_POST['feedback_date']) || empty($_POST['meal_type']) || empty($_POST['feedback']) || empty($_POST['rating'])) {
        $message = "Please fill all required fields.";
        $alertType = "error";
    } else {
        $feedback_date = mysqli_real_escape_string($conn, $_POST['feedback_date']);
        $meal_type = mysqli_real_escape_string($conn, $_POST['meal_type']);
        $feedback = mysqli_real_escape_string($conn, $_POST['feedback']); 
        $rating = intval($_POST['rating']);
        if ($rating < 1 || $rating > 5) {
            $message = "Invalid rating. Please select a rating between 1 and 5.";
            $alertType = "error";
        } else {
            $check_duplicate = "SELECT id FROM mess_feedback WHERE regNo = ? AND feedback_date = ? AND meal_type = ?";
            $check_dup_stmt = $conn->prepare($check_duplicate);
            $check_dup_stmt->bind_param("sss", $regNo, $feedback_date, $meal_type);
            $check_dup_stmt->execute();
            $check_dup_result = $check_dup_stmt->get_result();
            if ($check_dup_result->num_rows > 0) {
                $message = "You have already submitted feedback for this meal on this date.";
                $alertType = "error";
            } else {
                $query = "INSERT INTO mess_feedback (regNo, feedback_date, meal_type, feedback, rating) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssi", $regNo, $feedback_date, $meal_type, $feedback, $rating);
                
                if ($stmt->execute()) {
                    header("Location: mess_feedback.php?status=success", true, 303);
                    exit();
                } else {
                    $message = "Error submitting feedback: " . $conn->error;
                    $alertType = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mess Feedback - Hostel Management System</title>
    <link rel="stylesheet" href="css/mess_feedback.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Arial", sans-serif; }
        body { background-color: #f5f5f5; color: #333; line-height: 1.6; }
        .container { display: flex; flex-direction: column; min-height: 100vh; }
        header { background: #2c3e50; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between;
            align-items: center; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .logo h1 { font-size: 1.5rem; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .logout-btn { background: #e74c3c; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; 
            font-size: 0.9rem; transition: background-color 0.3s; }
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
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; color: #2c3e50; }
        .form-group input[type="date"], .form-group select, .form-group textarea { width: 100%; padding: 0.8rem; 
            border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 2rem; color: #ddd; cursor: pointer; padding: 0 0.1rem; transition: color 0.2s; }
        .star-rating label:hover, .star-rating label:hover ~ label, .star-rating input:checked ~ label { color: #ffb400; }
        .rating-display { display: flex; }
        .rating-display .star { font-size: 1.2rem; color: #ddd; margin-right: 2px; }
        .rating-display .star.filled { color: #ffb400; }
        .btn { display: inline-block; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; 
            text-decoration: none; font-size: 0.9rem; transition: background-color 0.3s; margin-right: 0.5rem; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .form-actions { display: flex; margin-top: 1rem; }
        .feedback-history table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .feedback-history th, .feedback-history td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        .feedback-history th { background-color: #f8f9fa; font-weight: bold; color: #2c3e50; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; transition: opacity 0.5s ease; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        footer { background: #2c3e50; color: white; text-align: center; padding: 1rem; margin-top: auto; }
        @media (max-width: 992px) { .content-wrapper { flex-direction: column; } .sidebar { width: 100%; padding-top: 0; } 
        .sidebar nav ul { display: flex; flex-wrap: wrap; } .sidebar nav ul li { margin-bottom: 0; } .sidebar nav ul li a { padding: 0.5rem 1rem; } }
        @media (max-width: 768px) { header { flex-direction: column; padding: 1rem; text-align: center; } .user-info { margin-top: 1rem; } 
        .main-content { padding: 1rem; } .form-actions { flex-direction: column; } .form-actions .btn { width: 100%; margin-bottom: 0.5rem; 
            margin-right: 0; } .star-rating label { font-size: 1.5rem; } .feedback-history { overflow-x: auto; } .feedback-history th, 
            .feedback-history td { padding: 0.5rem; font-size: 0.9rem; } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo"><h1>Hostel Management System</h1></div>
        </header>
        <div class="content-wrapper">
            <div class="sidebar">
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="room_details.php">Room Details</a></li>
                        <li><a href="mess_schedule.php">Mess Schedule</a></li>
                        <li><a href="mess_feedback.php" class="active">Mess Feedback</a></li>
                        <li><a href="complaints.php">Complaints</a></li>
                        <li><a href="payment_history.php">Payments</a></li>
                        <li><a href="profile.php">Profile</a></li>
                    </ul>
                </nav>
            </div>
            <div class="main-content">
                <h2>Mess Feedback</h2>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $alertType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                <div class="section">
                    <h3>Submit Feedback</h3>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="feedback-form">
                        <div class="form-group">
                            <label for="feedback_date">Date:</label>
                            <input type="date" id="feedback_date" name="feedback_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="meal_type">Meal:</label>
                            <select id="meal_type" name="meal_type" required>
                                <option value="">Select Meal</option>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Lunch">Lunch</option>
                                <option value="Snacks">Evening Snacks</option>
                                <option value="Dinner">Dinner</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Rating:</label>
                            <div class="star-rating">
                                <input type="radio" name="rating" id="rating-5" value="5">
                                <label for="rating-5">★</label>
                                <input type="radio" name="rating" id="rating-4" value="4">
                                <label for="rating-4">★</label>
                                <input type="radio" name="rating" id="rating-3" value="3" checked>
                                <label for="rating-3">★</label>
                                <input type="radio" name="rating" id="rating-2" value="2">
                                <label for="rating-2">★</label>
                                <input type="radio" name="rating" id="rating-1" value="1">
                                <label for="rating-1">★</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feedback">Your Feedback:</label>
                            <textarea id="feedback" name="feedback" rows="4" placeholder="Please enter your feedback about the meal quality, service, and suggestions for improvement..." required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Submit Feedback</button>
                            <button type="reset" class="btn btn-secondary">Reset Form</button>
                            <a href="mess_schedule.php" class="btn btn-secondary">Back to Mess Schedule</a>
                        </div>
                    </form>
                </div>
                <div class="section feedback-history">
                    <h3>Your Recent Feedback</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Date</th>
                                <th>Meal</th>
                                <th>Rating</th>
                                <th>Feedback</th>
                                <th>Submitted On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT * FROM mess_feedback WHERE regNo = ? ORDER BY created_at DESC LIMIT 10";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("s", $regNo);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $sno = 1;
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $sno . "</td>";
                                    echo "<td>" . date('d M Y', strtotime($row['feedback_date'])) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['meal_type']) . "</td>";
                                    echo "<td><div class='rating-display'>";
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $row['rating']) {echo "<span class='star filled'>★</span>";} 
                                        else {echo "<span class='star'>★</span>";}
                                    }
                                    echo "</div></td>";
                                    echo "<td>" . htmlspecialchars($row['feedback']) . "</td>";
                                    echo "<td>" . date('d M Y H:i', strtotime($row['created_at'])) . "</td>";
                                    echo "</tr>";
                                    $sno++; 
                                }
                            } else {echo "<tr><td colspan='6'>No feedback submissions found</td></tr>";}
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Hostel Management System. All rights reserved.</p>
        </footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alertBox = document.querySelector('.alert');
        if (alertBox) {
            setTimeout(function() {
                alertBox.style.opacity = '0';
                setTimeout(function() {
                    alertBox.style.display = 'none';
                }, 500);
            }, 5000);
        }
        const feedbackForm = document.querySelector('.feedback-form');
        if (feedbackForm) {
            feedbackForm.addEventListener('submit', function(e) {
                const feedback = document.getElementById('feedback').value.trim();
                const mealType = document.getElementById('meal_type').value;
                if (!feedback || !mealType) {
                    e.preventDefault();
                    alert('Please fill all required fields.');
                }
            });
        }
        const dateInput = document.getElementById('feedback_date');
        const mealSelect = document.getElementById('meal_type');
        const submitButton = document.querySelector('button[type="submit"]');
        if (dateInput && mealSelect && submitButton) {
            const checkDuplicateFeedback = function() {
                const date = dateInput.value;
                const meal = mealSelect.value;
                if (date && meal) {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (this.status === 200) {
                            try {
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = this.responseText;
                                const alerts = tempDiv.querySelectorAll('.alert.alert-error');
                                for (let i = 0; i < alerts.length; i++) {
                                    if (alerts[i].textContent.includes('already submitted feedback')) {
                                        alert('You have already submitted feedback for this meal on this date.');
                                        submitButton.disabled = true;
                                        return;
                                    }
                                }
                                submitButton.disabled = false;
                            } catch (error) {
                                console.error('Error parsing response:', error);
                                submitButton.disabled = false;
                            }
                        }
                    };
                    xhr.onerror = function() {
                        console.error('Network error occurred');
                        submitButton.disabled = false;
                    };
                    xhr.send('feedback_date=' + encodeURIComponent(date) + '&meal_type=' + encodeURIComponent(meal) + '&check_duplicate=1');
                }
            };
            dateInput.addEventListener('change', checkDuplicateFeedback);
            mealSelect.addEventListener('change', checkDuplicateFeedback);
        }
    });
    </script>
</body>
</html>