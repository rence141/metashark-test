<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// 1. Security Guard
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// 2. Validate Input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Invalid ID? Go back to appeals
    header('Location: admin_appeals.php?error=invalid_id');
    exit;
}

$id = (int)$_GET['id'];
$type = $_GET['type'] ?? 'account'; // Keep track of which tab we were on

// 3. Perform Deletion
$stmt = $conn->prepare("DELETE FROM user_appeals WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    // Success: Redirect back to the specific tab
    header("Location: admin_appeals.php?type=$type&msg=deleted");
} else {
    // Error: Redirect with error message
    header("Location: admin_appeals.php?type=$type&error=db_error");
}

$stmt->close();
$conn->close();
exit;
?>