<?php
$host = getenv('DB_HOST') ?: 'dpg-d39duls9c44c73ar309g-a.singapore-postgres.render.com';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'metaaccesories';
$user = getenv('DB_USER') ?: 'metaaccesories_user';
$pass = getenv('DB_PASS') ?: '8rE5tiPpHHWkLbfvxZbUoBwdWZ9ZfjBs';

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connected successfully";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
