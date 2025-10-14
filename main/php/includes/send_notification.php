<?php
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['admin_id'])) { echo json_encode(['error'=>'Not authenticated']); exit; }

$data = $_POST;
$message = trim($data['message'] ?? '');
$link = trim($data['link'] ?? '#');
$target = $data['target'] ?? 'all'; // 'all' or user id

if (!$message) { echo json_encode(['error'=>'no message']); exit; }

if ($target === 'all') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (NULL, ?, ?, 0, NOW())");
    $stmt->bind_param('ss', $message, $link);
    $ok = $stmt->execute();
    echo json_encode(['success'=>$ok]);
    exit;
}

if (is_numeric($target)) {
    $uid = (int)$target;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param('iss', $uid, $message, $link);
    $ok = $stmt->execute();
    echo json_encode(['success'=>$ok]);
    exit;
}

echo json_encode(['error'=>'invalid target']);
