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
    $stmt = $conn->prepare('SELECT role, message, timestamp FROM ai_chats WHERE user_id = ? ORDER BY timestamp ASC LIMIT ?');
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

// Default: Proxy to aiChat-bot.php for AI logic
require_once('aiChat-bot.php');
