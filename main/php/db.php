<!--?php
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

<?php
$host = "dpg-d39duls9c44c73ar309g-a.singapore-postgres.render.com";
$port = "5432";
$db   = "metaaccesories";
$user = "metaaccesories_user";
$pass = "8rE5tiPpHHWkLbfvxZbUoBwdWZ9ZfjBs";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully to Render PostgreSQL!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
