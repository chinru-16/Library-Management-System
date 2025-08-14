<?php
$host = "localhost"; 
$user = "root"; // default for XAMPP/MAMP
$pass = "";     // default is empty
$db   = "cozy_library"; // database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
