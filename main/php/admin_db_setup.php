<?php
// Simple DB + admin table setup script
// Usage: visit this script in browser once, then delete or move it for security.

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: 3306;
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '003421.!';
$DB_NAME = getenv('DB_NAME') ?: 'sayson_db';

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', (int)$DB_PORT);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "<h2>Connection failed</h2>";
    echo "<p>Please ensure MySQL is running and credentials are correct.</p>";
    echo "<pre>" . htmlspecialchars($mysqli->connect_error) . "</pre>";
    exit;
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$mysqli->query($sql)) {
    http_response_code(500);
    echo "<h2>Failed to create database</h2><pre>" . htmlspecialchars($mysqli->error) . "</pre>";
    exit;
}

// Select the database
$mysqli->select_db($DB_NAME);

// Create admin_accounts table
$create = <<<SQL
CREATE TABLE IF NOT EXISTS `admin_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(120) NOT NULL,
  `last_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

if (!$mysqli->query($create)) {
    http_response_code(500);
    echo "<h2>Failed to create table</h2><pre>" . htmlspecialchars($mysqli->error) . "</pre>";
    exit;
}

// Insert sample admin if none exists
$res = $mysqli->query("SELECT COUNT(*) as c FROM admin_accounts");
$row = $res ? $res->fetch_assoc() : null;
if ($row && (int)$row['c'] === 0) {
    $sampleEmail = 'admin@example.com';
    $samplePassword = 'Admin@123'; // change on first login
    $hash = password_hash($samplePassword, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("INSERT INTO admin_accounts (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
    $fn = 'System'; $ln = 'Admin';
    $stmt->bind_param('ssss', $fn, $ln, $sampleEmail, $hash);
    $ok = $stmt->execute();
    if ($ok) {
        echo "<h2>Success</h2>";
        echo "<p>Database <strong>" . htmlspecialchars($DB_NAME) . "</strong> and table <strong>admin_accounts</strong> created.</p>";
        echo "<p>Sample admin created: <strong>{$sampleEmail}</strong> / <strong>{$samplePassword}</strong></p>";
        echo "<p>Please log in via <a href='admin_login.php'>admin_login.php</a> and change the password immediately.</p>";
    } else {
        echo "<h2>Partial success</h2>";
        echo "<p>Tables created but failed to insert sample admin:</p>";
        echo "<pre>" . htmlspecialchars($stmt->error) . "</pre>";
    }
    $stmt->close();
} else {
    echo "<h2>Already initialized</h2>";
    echo "<p>Database and table exist. No sample admin inserted.</p>";
    echo "<p>If you need to create an admin, use admin_signup.php or insert a row manually.</p>";
}

$mysqli->close();
echo "<p style='margin-top:1rem;color:#aaa'>When finished, delete this file for security.</p>";
