<?php
// Database connection using environment variables with fallback to hardcoded values
$host = getenv('DB_HOST') ?: 'dpg-d39duls9c44c73ar309g-a.singapore-postgres.render.com';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'metaaccesories';
$user = getenv('DB_USER') ?: 'metaaccesories_user';
$pass = getenv('DB_PASS') ?: '8rE5tiPpHHWkLbfvxZbUoBwdWZ9ZfjBs';

// Create connection string
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$pass";

// Connect to PostgreSQL
$conn = pg_connect($conn_string);

// Check connection
if (!$conn) {
    die("Database connection failed: " . pg_last_error());
}
// echo "Connected successfully";
?>
