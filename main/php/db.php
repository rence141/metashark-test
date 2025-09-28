<?php
// Database configuration (InfinityFree)
$host   = "sqlXXX.infinityfree.com";   // from MySQL Databases page
$user   = "if0_40045083";              // your DB username
$pass   = "Lorenzezz003421";             // your DB password
$dbname = "if0_40045083_metashark";    // your DB name
$port   = 3306;                        // InfinityFree uses default 3306

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
// echo "✅ Connected successfully";
?>
