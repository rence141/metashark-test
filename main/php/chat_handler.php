<?php
session_start();
include("db.php");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'get_new_messages') {
    $seller_id = intval($_GET['seller_id'] ?? 0);
    
    if ($seller_id <= 0) {
        echo json_encode(['error' => 'Invalid seller ID']);
        exit;
    }
    
    // Get count of unseen messages from this seller
    $stmt = $conn->prepare("
        SELECT COUNT(*) as new_count 
        FROM chat_messages 
        WHERE sender_id = ? AND receiver_id = ? AND is_seen = 0
    ");
    $stmt->bind_param("ii", $seller_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'new_messages' => $result['new_count']
    ]);
    exit;
}

if ($action === 'send_notification') {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $message_preview = trim($_POST['message_preview'] ?? '');
    $sender_name = trim($_POST['sender_name'] ?? '');
    
    if ($receiver_id <= 0 || empty($sender_name)) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    // Store notification in database
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type, created_at) 
        VALUES (?, ?, 'message', NOW())
    ");
    $notification_message = "New message from " . $sender_name . ": " . (!empty($message_preview) ? $message_preview : "[Image/File]");
    $stmt->bind_param("is", $receiver_id, $notification_message);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
