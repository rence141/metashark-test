<?php
session_start();
include("db.php");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get selected chat user
$chat_with = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;

// Prevent chatting with yourself
if ($chat_with === $current_user_id) {
    $chat_with = 0;
}

// Fetch all chat histories (last message + user info)
$history_stmt = $conn->prepare("
    SELECT u.id, u.seller_name, u.fullname, u.profile_image, m.message, m.file_type, m.file_path, m.created_at
    FROM (
        SELECT IF(sender_id = ?, receiver_id, sender_id) AS user_id, MAX(id) as last_message_id
        FROM chat_messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY user_id
    ) t
    JOIN chat_messages m ON m.id = t.last_message_id
    JOIN users u ON u.id = t.user_id
    ORDER BY m.created_at DESC
");
$history_stmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
$history_stmt->execute();
$chat_histories = $history_stmt->get_result();

// If no specific chat selected, default to most recent chat
if ($chat_with == 0 && $chat_histories->num_rows > 0) {
    $chat_histories->data_seek(0);
    $recent_chat = $chat_histories->fetch_assoc();
    $chat_with = $recent_chat['id'];
    // Re-fetch histories after seek
    $history_stmt->execute();
    $chat_histories = $history_stmt->get_result();
}

// Fetch selected chat user info
$chat_user = null;
if ($chat_with > 0) {
    $stmt = $conn->prepare("SELECT id, seller_name, fullname, profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $chat_with);
    $stmt->execute();
    $chat_user = $stmt->get_result()->fetch_assoc();
}

// Handle sending new message (text + file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chat_user) {
    $message = trim($_POST['message']);
    $file_path = null;
    $file_type = null;

    // Handle file upload
    if (!empty($_FILES['file']['name'])) {
        $upload_dir = "uploads/messages/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_name = time() . "_" . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $file_type = 'image';
            } elseif (in_array($ext, ['mp4','webm','mov'])) {
                $file_type = 'video';
            } else {
                $file_type = 'other';
            }
        }
    }

    if (!empty($message) || $file_path) {
        $insert_stmt = $conn->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message, file_path, file_type, is_seen, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $insert_stmt->bind_param("iisss", $current_user_id, $chat_with, $message, $file_path, $file_type);
        $insert_stmt->execute();
        
        // Send notification to receiver - get sender info from database
        $sender_stmt = $conn->prepare("SELECT seller_name, fullname FROM users WHERE id = ?");
        $sender_stmt->bind_param("i", $current_user_id);
        $sender_stmt->execute();
        $sender_result = $sender_stmt->get_result()->fetch_assoc();
        $sender_name = $sender_result['seller_name'] ?: $sender_result['fullname'];
        
        $message_preview = !empty($message) ? $message : ($file_path ? "[Image/File]" : "");
        
        // Insert notification (simplified without is_read for now)
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, type, created_at) 
            VALUES (?, ?, 'message', NOW())
        ");
        $notification_message = "New message from " . $sender_name . ": " . $message_preview;
        $notif_stmt->bind_param("is", $chat_with, $notification_message);
        $notif_stmt->execute();
    }

    header("Location: chat.php?seller_id=" . $chat_with);
    exit();
}

// Fetch conversation messages if chat is active
$messages = [];
if ($chat_user) {
    // Mark all messages from this chat partner as seen
    $update_seen = $conn->prepare("
        UPDATE chat_messages 
        SET is_seen = 1 
        WHERE receiver_id = ? AND sender_id = ? AND is_seen = 0
    ");
    $update_seen->bind_param("ii", $current_user_id, $chat_with);
    $update_seen->execute();

    $chat_stmt = $conn->prepare("
        SELECT m.*, u.fullname, u.seller_name, u.profile_image
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $chat_stmt->bind_param("iiii", $current_user_id, $chat_with, $chat_with, $current_user_id);
    $chat_stmt->execute();
    $messages_result = $chat_stmt->get_result();
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme'] ?? 'dark'; ?>">
<head>
    <meta charset="UTF-8">
    <title>Chat | Meta Shark</title>
    <link rel="stylesheet" href="../../css/shop.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/chat.css">
        
</head>
<body class="chat-page">

<div class="chat-sidebar">
    <a href="shop.php" class="back-btn">
        <i class="bi bi-arrow-left"></i> Back to Shop
    </a>
    <h2>Chats</h2>
    <ul class="chat-list">
        <?php $chat_histories->data_seek(0); // Reset result pointer
        while ($row = $chat_histories->fetch_assoc()): ?>
            <li onclick="window.location.href='chat.php?seller_id=<?php echo $row['id']; ?>'">
                <img src="<?php echo !empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image']) ? 'uploads/' . htmlspecialchars($row['profile_image']) : 'uploads/default-avatar.svg'; ?>" alt="Avatar">
                <div>
                    <div><b><?php echo htmlspecialchars($row['seller_name'] ?: $row['fullname']); ?></b></div>
                    <div style="font-size:0.85rem; color:var(--secondary-text);">
                        <?php 
                        if ($row['file_type'] === 'image') echo "[Image]";
                        elseif ($row['file_type'] === 'video') echo "[Video]";
                        else echo htmlspecialchars($row['message']);
                        ?>
                    </div>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>
    <!-- Sidebar footer -->
    <div class="chat-sidebar-footer">
    </div>
</div>

<div class="chat-main">
    <?php if ($chat_user): ?>
        <div class="chat-header">
            <img src="<?php echo !empty($chat_user['profile_image']) && file_exists('uploads/' . $chat_user['profile_image']) ? 'uploads/' . htmlspecialchars($chat_user['profile_image']) : 'uploads/default-avatar.svg'; ?>" alt="User Avatar" style="width:45px;height:45px;border-radius:50%;">
            <h2><?php echo htmlspecialchars($chat_user['seller_name'] ?: $chat_user['fullname']); ?></h2>
        </div>

        <div class="chat-messages" id="chat-messages">
    <?php foreach ($messages as $row): ?>
        <div class="message <?php echo $row['sender_id'] == $current_user_id ? 'me' : 'them'; ?>">
            
            <!-- Show media first -->
            <?php if (!empty($row['file_path'])): ?>
                <?php if ($row['file_type'] === 'image'): ?>
                    <img src="<?php echo htmlspecialchars($row['file_path']); ?>" 
                         alt="Image" style="max-width: 220px; border-radius:8px; display:block; margin-bottom:6px; cursor:pointer;" 
                         onclick="openImagePopup('<?php echo htmlspecialchars($row['file_path']); ?>')"
                         class="chat-image">
                <?php elseif ($row['file_type'] === 'video'): ?>
                    <video controls style="max-width: 220px; border-radius:8px; display:block; margin-bottom:6px;">
                        <source src="<?php echo htmlspecialchars($row['file_path']); ?>" type="video/mp4">
                        Your browser does not support video.
                    </video>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($row['file_path']); ?>" download>Download File</a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Then show text (if any) -->
            <?php if (!empty($row['message'])): ?>
                <div style="margin-top:4px;"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>
            <?php endif; ?>

           <!-- Timestamp + seen check -->
        <small style="display:block; margin-top:4px; font-size:0.75rem; opacity:0.8;">
            <?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?>

            <?php if ($row['is_seen']): ?>
                <span style="color:green;">&#10003;&#10003;</span> <!-- Seen -->
            <?php else: ?>
                <span>&#10003;</span> <!-- Sent but not seen -->
            <?php endif; ?>
        </small>

        </div>
    <?php endforeach; ?>
</div>

        <form method="POST" class="chat-form" enctype="multipart/form-data" onsubmit="showMessageSentNotification()">
            <label for="file-upload" class="btn btn-primary mb-0">
                <i class="bi bi-image" style="font-size:1.4rem;"></i>
            </label>
            <input type="file" id="file-upload" name="file" accept="image/*,video/*" style="display:none;">

            <div id="file-preview"></div>

            <textarea name="message" rows="2" placeholder="Type your message..."></textarea>
            <button type="submit">Send</button>
        </form>
    <?php else: ?>
        <div style="flex:1; display:flex; justify-content:center; align-items:center; color:var(--opacity-text);">
            <h2>Select a chat from the left</h2>
        </div>
    <?php endif; ?>
</div>

<!-- Image Popup Modal -->
<div id="imagePopupModal" class="image-popup-modal">
    <div class="image-popup-overlay" onclick="closeImagePopup()"></div>
    <div class="image-popup-content">
        <div class="image-popup-header">
            <h3>Image Preview</h3>
            <button class="close-btn" onclick="closeImagePopup()">&times;</button>
        </div>
        <div class="image-popup-body">
            <img id="popupImage" src="" alt="Full size image">
        </div>
        <div class="image-popup-footer">
            <button class="btn btn-secondary" onclick="closeImagePopup()">Close</button>
            <button class="btn btn-primary" onclick="downloadImage()">Download</button>
        </div>
    </div>
</div>

<!-- Notification System -->
<div id="notificationContainer" class="notification-container"></div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const textarea = document.querySelector(".chat-form textarea");
    const form = document.querySelector(".chat-form");
    const chatMessages = document.getElementById("chat-messages");
    const fileInput = document.getElementById("file-upload");
    const filePreview = document.getElementById("file-preview");

    // Enter to send (Shift+Enter = newline)
    textarea.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            form.submit();
        }
    });

    // Auto scroll to bottom
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Preview file before sending
    fileInput.addEventListener("change", function () {
        filePreview.innerHTML = ""; // clear old preview
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            let element;
            if (file.type.startsWith("image/")) {
                element = document.createElement("img");
                element.src = e.target.result;
            } else if (file.type.startsWith("video/")) {
                element = document.createElement("video");
                element.src = e.target.result;
                element.controls = true;
            } else {
                element = document.createElement("div");
                element.textContent = "File ready: " + file.name;
            }
            filePreview.appendChild(element);
        };
        reader.readAsDataURL(file);
    });

    // Auto-refresh messages every 3 seconds
    setInterval(refreshMessages, 3000);
});

// Image Popup Functions
function openImagePopup(imageSrc) {
    const modal = document.getElementById('imagePopupModal');
    const img = document.getElementById('popupImage');
    img.src = imageSrc;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeImagePopup() {
    const modal = document.getElementById('imagePopupModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function downloadImage() {
    const img = document.getElementById('popupImage');
    const link = document.createElement('a');
    link.href = img.src;
    link.download = 'chat-image-' + Date.now() + '.jpg';
    link.click();
}

// Notification Functions
function showNotification(message, type = 'info', duration = 3000) {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="bi bi-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="closeNotification(this)">&times;</button>
    `;
    
    container.appendChild(notification);
    
    // Auto remove after duration
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'info': 'info-circle',
        'warning': 'exclamation-circle'
    };
    return icons[type] || 'info-circle';
}

function closeNotification(button) {
    button.parentNode.remove();
}

// Message refresh function for notifications
function refreshMessages() {
    if (!window.location.search.includes('seller_id=')) return;
    
    fetch('chat_handler.php?action=get_new_messages&seller_id=' + new URLSearchParams(window.location.search).get('seller_id'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_messages > 0) {
                showNotification(`You have ${data.new_messages} new message(s)`, 'info');
                // Reload page to show new messages
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(error => console.error('Error checking for new messages:', error));
}

// Show notification when message is sent
function showMessageSentNotification() {
    showNotification('Message sent successfully!', 'success', 2000);
}
</script>

</body>
</html>