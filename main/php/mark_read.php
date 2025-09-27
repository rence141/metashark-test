<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$notifId = (int)$_POST['id'] ?? 0;

if ($notifId > 0) {
    $update_sql = $conn->prepare("UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?");
    if ($update_sql) {
        $update_sql->bind_param("ii", $notifId, $userId);
        $update_sql->execute();
        if ($conn->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>