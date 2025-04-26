<?php
include('db.php');
session_start();
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $student_email = $user['email'];
    try {
        $sql = "UPDATE login_details 
                SET logout_time = CURRENT_TIMESTAMP 
                WHERE student_email = ? 
                AND login_status = 'success' 
                AND logout_time IS NULL 
                ORDER BY login_time DESC 
                LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $student_email);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                error_log("No logout record updated for email: $student_email");
            }
            $stmt->close();
        } else {
            error_log("Error preparing logout statement: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
session_start();
$_SESSION['message'] = "You have been successfully logged out.";
header("Location: login.php");
exit;
?>