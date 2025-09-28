<?php
// Database configuration
$host = "localhost";
$user = "root";       // change if you set a MySQL user
$pass = "003421.!";           // change if you set a MySQL password
$dbname = "MetaAccesories";
$port = 3307;

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully";
?>