<?php
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['admin_id'])) { echo json_encode(['error'=>'Not authenticated']); exit; }

$action = $_POST['action'] ?? '';
if ($action === 'toggle_ban') {
    $user_id = intval($_POST['user_id'] ?? 0);
    if ($user_id <= 0) { echo json_encode(['error'=>'invalid']); exit; }
    // fetch current
    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i',$user_id); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc();
    $new = ($r && $r['status']==='banned') ? 'active' : 'banned';
    $up = $conn->prepare("UPDATE users SET status=? WHERE id=?");
    $up->bind_param('si',$new,$user_id); $ok = $up->execute();
    echo json_encode(['success'=>$ok,'status'=>$new]);
    exit;
}

if ($action === 'mark_all_read') {
    $target_user = $_POST['user_id'] ?? null; // if provided, mark for that user; else mark admin-wide (user_id NULL)
    if ($target_user) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param('i', $target_user);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id IS NULL");
        $stmt->execute();
    }
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['error'=>'unknown_action']);
