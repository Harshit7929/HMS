<?php
session_start();
include('admin_db.php');

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    
    // Update the logout time for the current session
    $update_query = "UPDATE admin_log 
                    SET logout_time = CURRENT_TIMESTAMP 
                    WHERE admin_id = ? 
                    AND session_id = ? 
                    AND logout_time IS NULL 
                    ORDER BY login_time DESC 
                    LIMIT 1";

    if ($stmt = $conn->prepare($update_query)) {
        $stmt->bind_param("is", $admin_id, session_id());
        $stmt->execute();
        $stmt->close();
    }
}

// Clear all session variables
session_unset();
session_destroy();

// Redirect to login page
header("Location: admin_login.php");
exit();
?>