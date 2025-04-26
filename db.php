<?php
$host = "localhost";
$username = "root"; 
$password = "";
$dbname = "manage_hostel";
$port = 4306; 
try {
    $conn = new mysqli($host, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
