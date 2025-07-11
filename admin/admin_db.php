<?php
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "manage_hostel"; 
$port = 4306; 
try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit(); 
}
?>
 