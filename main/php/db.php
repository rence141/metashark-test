<?php
// Database configuration for Render PostgreSQL
$host = "dpg-d39duls9c44c73ar309g-a.singapore-postgres.render.com";
$dbname = "metaaccesories";
$user = "metaaccesories_user";
$pass = "8rE5tiPpHHWkLbfvxZbUoBwdWZ9ZfjBs";
$port = "5432";

try {
    // PDO connection string for PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    // echo "Connected successfully!";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
