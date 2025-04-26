<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['user']['email'];

// Fetch booking details for the user
$bookingQuery = "SELECT * FROM room_bookings WHERE user_email = ? ORDER BY booking_date DESC LIMIT 1";
$bookingStmt = $conn->prepare($bookingQuery);
$bookingStmt->bind_param("s", $userEmail);
$bookingStmt->execute();
$bookingResult = $bookingStmt->get_result();
$bookingDetails = $bookingResult->fetch_assoc();

// Check if booking details were fetched successfully
if (!$bookingDetails) {
    die("Booking details not found. Please try again.");
}

// Get the current date and time
date_default_timezone_set('UTC'); // Set the correct timezone if needed
$currentDateTime = date('F d, Y h:i A');

?>

<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Successful</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/success.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .success-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        h2 {
            color: #28a745;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .booking-details {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .booking-details th, .booking-details td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .booking-details th {
            background-color: #f8f8f8;
            color: #333;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <h2>Booking Successful!</h2>
        <div class="alert alert-success">
            <h3><i class="fas fa-check-circle"></i> Congratulations, <?php echo htmlspecialchars($_SESSION['user']['firstName']); ?>!</h3>
            <p>Your room has been successfully booked. Here are the details:</p>
            <table class="booking-details">
                <tr>
                    <th>Hostel Name</th>
                    <td><?php echo htmlspecialchars($bookingDetails['hostel_name']); ?></td>
                </tr>
                <tr>
                    <th>Room Number</th>
                    <td><?php echo htmlspecialchars($bookingDetails['room_number']); ?></td>
                </tr>
                <tr>
                    <th>Floor</th>
                    <td><?php echo htmlspecialchars($bookingDetails['floor']); ?></td>
                </tr>
                <tr>
                    <th>Room Type</th>
                    <td><?php echo $bookingDetails['is_ac'] ? 'AC' : 'Non-AC'; ?></td>
                </tr>
                <tr>
                    <th>Sharing Type</th> 
                    <td><?php echo htmlspecialchars($bookingDetails['sharing_type']); ?></td>
                </tr>
                <tr>
                    <th>Stay Period</th>
                    <td><?php echo htmlspecialchars($bookingDetails['stay_period']); ?> Months</td>
                </tr>
                <tr>
                    <th>Booking Date & Time</th>
                    <td><?php 
                        // Set the timezone to your local timezone
                        date_default_timezone_set('Asia/Kolkata'); // Change this to your timezone (e.g., 'America/New_York')
                        echo date('F d, Y h:i A'); // This will show the current time in your timezone
                    ?></td>
                </tr>
            </table>
        </div>
        <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
    </div>
</body>
</html>