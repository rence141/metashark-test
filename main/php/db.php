<?php
// PostgreSQL configuration
$host = "dpg-d39duls9c44c73ar309g-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "metaaccesories";
$user = "metaaccesories_user";
$pass = "8rE5tiPpHHWkLbfvxZbUoBwdWZ9ZfjBs";

// Create connection string
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$pass";

// Connect to PostgreSQL
$conn = pg_connect($conn_string);

// Check connection
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}
// echo "Connected successfully";
?>
