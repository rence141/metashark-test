<?php
// ai_chat_handler.php
// Handles AI chat AJAX requests and proxies to aiChat-bot.php for logic

header('Content-Type: application/json');

session_start();
include_once('db.php');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$user_id = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);

if (!$user_id) {
    echo json_encode(['error' => 'No user ID.']);
    exit;
}

if ($action === 'save_message') {
    $role = $input['role'] ?? 'user';
    $message = $input['message'] ?? '';
    if ($message !== '') {
        $stmt = $conn->prepare('INSERT INTO ai_chats (user_id, role, message) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user_id, $role, $message);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['error' => 'No message.']);
    exit;
}

if ($action === 'load_history') {
    $limit = intval($input['limit'] ?? 100);
    $stmt = $conn->prepare('SELECT id, role, message, timestamp FROM ai_chats WHERE user_id = ? ORDER BY timestamp ASC, id ASC LIMIT ?');
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    echo json_encode(['history' => $history]);
    exit;
}

if ($action === 'delete_session') {
    $session_index = intval($input['session_index'] ?? 0); // 1-based
    if ($session_index <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid session index']);
        exit;
    }

    // Load full history for this user (no LIMIT to capture all sessions)
    $rows = [];
    if ($stmt = $conn->prepare('SELECT id, role, message, timestamp FROM ai_chats WHERE user_id = ? ORDER BY timestamp ASC, id ASC')) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
    }

    // Split into sessions by marker rows
    $sessions = [];
    $current = [];
    foreach ($rows as $r) {
        $isMarker = (isset($r['role']) && ($r['role'] === 'marker' || $r['role'] === 'system')) && (trim($r['message'] ?? '') === '__session_break__');
        if ($isMarker) {
            if (!empty($current)) { $sessions[] = $current; }
            $current = [];
            continue;
        }
        $current[] = $r;
    }
    // Always push trailing (may be empty)
    $sessions[] = $current;

    if ($session_index > count($sessions)) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }

    $toDelete = $sessions[$session_index - 1];
    if (empty($toDelete)) {
        echo json_encode(['success' => true, 'deleted' => 0]);
        exit;
    }

    $deleted = 0;
    if ($del = $conn->prepare('DELETE FROM ai_chats WHERE user_id = ? AND id = ?')) {
        foreach ($toDelete as $row) {
            $id = intval($row['id']);
            $del->bind_param('ii', $user_id, $id);
            @$del->execute();
            $deleted += ($del->affected_rows > 0) ? 1 : 0;
        }
        $del->close();
    }

    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

// Default: No AI proxy. Client uses Puter.js directly.
echo json_encode(['error' => 'No action specified.']);
exit;
