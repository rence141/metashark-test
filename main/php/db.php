<!--DO NOT TOUCH!-->

<?php
// Database configuration
$host = "localhost";  
$port = 3307;
$user = "root";       
$pass = "003421.!";           
$dbname = "MetaAccesories";


// Create connection
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully";
?>  

