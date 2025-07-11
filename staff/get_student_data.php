<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'hostel_error.log');
require_once 'staff_db.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
if (!isset($_SESSION['staff_id'])) {
    echo json_encode([]);
    exit();
}
$room_number = isset($_GET['room']) ? $_GET['room'] : '';
$hostel_name = isset($_GET['hostel']) ? $_GET['hostel'] : '';
error_log("Student data request: Room $room_number in $hostel_name");
if (empty($room_number) || empty($hostel_name)) {
    echo json_encode([]);
    exit();
}
try {
    $room_check = "SELECT * FROM rooms WHERE hostel_name = ? AND room_number = ?";
    $stmt = $conn->prepare($room_check);
    $stmt->bind_param("ss", $hostel_name, $room_number); 
    $stmt->execute();
    $room_result = $stmt->get_result();
    if ($room_result->num_rows == 0) {
        error_log("Room not found: $hostel_name, $room_number");
        echo json_encode([]);
        $stmt->close();
        exit();
    }
    $stmt->close();
    $sql = "SELECT 
                s.regNo, 
                s.firstName, 
                s.lastName, 
                s.gender, 
                s.contact, 
                s.email, 
                sd.course, 
                sd.year_of_study
            FROM 
                room_bookings rb
            JOIN 
                student_signup s ON rb.user_email = s.email
            LEFT JOIN 
                student_details sd ON s.regNo = sd.reg_no
            WHERE 
                rb.hostel_name = ? AND 
                rb.room_number = ? AND 
                rb.status = 'confirmed'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $hostel_name, $room_number); 
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    error_log("Query executed. Found " . $result->num_rows . " students for room $room_number");
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    echo json_encode($students);
} catch (Exception $e) {
    error_log("Error in get_student_data.php: " . $e->getMessage());
    echo json_encode([]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>