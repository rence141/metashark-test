<?php
session_start();
include("db.php");

// --- NEW: HANDLE THEME TOGGLE (AJAX) ---
if (isset($_GET['toggle_theme'])) {
    $new_theme = ($_SESSION['theme'] ?? 'dark') === 'dark' ? 'light' : 'dark';
    $_SESSION['theme'] = $new_theme;
    echo $new_theme;
    exit; // Stop execution here so we don't load the whole page
}

// 1. AUTHENTICATION CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$chat_with = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
// Set current theme variable for PHP use
$theme = $_SESSION['theme'] ?? 'dark';

// Prevent chatting with self
if ($chat_with === $current_user_id) {
    $chat_with = 0;
}

// 2. FETCH SIDEBAR HISTORY
$history_stmt = $conn->prepare("
    SELECT u.id, u.seller_name, u.fullname, u.profile_image, m.message, m.file_type, m.created_at,
           (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_seen = 0) as unread
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
$history_stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$history_stmt->execute();
$chat_histories = $history_stmt->get_result();

// Default to most recent if no ID
if ($chat_with == 0 && $chat_histories->num_rows > 0) {
    $chat_histories->data_seek(0);
    $recent = $chat_histories->fetch_assoc();
    $chat_with = $recent['id'];
    $history_stmt->execute(); // Reset for display loop
    $chat_histories = $history_stmt->get_result();
}

// 3. FETCH ACTIVE CHAT USER
$chat_user = null;
if ($chat_with > 0) {
    $stmt = $conn->prepare("SELECT id, seller_name, fullname, profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $chat_with);
    $stmt->execute();
    $chat_user = $stmt->get_result()->fetch_assoc();
}

// 4. HANDLE MESSAGE SENDING
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chat_user) {
    $message = trim($_POST['message']);
    $file_path = null;
    $file_type = null;

    if (!empty($_FILES['file']['name'])) {
        $upload_dir = "uploads/messages/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . "_" . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) $file_type = 'image';
            elseif (in_array($ext, ['mp4','webm','mov'])) $file_type = 'video';
            else $file_type = 'other';
        }
    }

    if (!empty($message) || $file_path) {
        $ins = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message, file_path, file_type, is_seen, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $ins->bind_param("iisss", $current_user_id, $chat_with, $message, $file_path, $file_type);
        $ins->execute();
    }
    header("Location: chat.php?seller_id=" . $chat_with);
    exit();
}

// 5. FETCH MESSAGES & MARK SEEN
$messages = [];
if ($chat_user) {
    $conn->query("UPDATE chat_messages SET is_seen = 1 WHERE receiver_id = $current_user_id AND sender_id = $chat_with");
    
    $msg_stmt = $conn->prepare("
        SELECT * FROM chat_messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $msg_stmt->bind_param("iiii", $current_user_id, $chat_with, $chat_with, $current_user_id);
    $msg_stmt->execute();
    $res = $msg_stmt->get_result();
    while ($row = $res->fetch_assoc()) $messages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger | Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* --- MESSENGER STYLE VARIABLES --- */
        :root {
            --ms-bg: #fff;
            --ms-sidebar: #fff;
            --ms-chat-bg: #fff;
            --ms-text: #050505;
            --ms-subtext: #65676b;
            --ms-hover: #f0f2f5;
            --ms-border: #dbdbdb;
            --ms-blue: #09a538ff;
            --ms-gray-bubble: #e4e6eb;
            --ms-input-bg: #f0f2f5;
            --ms-own-text: #ffffffff;
        }

        [data-theme="dark"] {
            --ms-bg: #000;
            --ms-sidebar: #000;
            --ms-chat-bg: #000;
            --ms-text: #e4e6eb;
            --ms-subtext: #b0b3b8;
            --ms-hover: #3a3b3c;
            --ms-border: #2f3031;
            --ms-blue: #44D62C;
            --ms-gray-bubble: #3e4042;
            --ms-input-bg: #3a3b3c;
            --ms-own-text: #fff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body, html { height: 100%; overflow: hidden; background-color: var(--ms-bg); color: var(--ms-text); }

        /* --- LAYOUT --- */
        .messenger-layout { display: flex; height: 100vh; }
        
        /* --- SIDEBAR --- */
        .sidebar {
            width: 360px;
            background: var(--ms-sidebar);
            border-right: 1px solid var(--ms-border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .sidebar-header {
            padding: 20px 16px 10px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .sidebar-header h1 { font-size: 24px; font-weight: 700; }
        .icon-btn {
            width: 36px; height: 36px; border-radius: 50%; background: var(--ms-hover);
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            color: var(--ms-text); text-decoration: none; border: none; font-size: 18px;
        }
        
        .search-container { padding: 0 16px 10px; }
        .search-box {
            background: var(--ms-input-bg); border-radius: 20px; padding: 8px 12px;
            display: flex; align-items: center; gap: 8px; color: var(--ms-subtext);
        }
        .search-box input {
            border: none; background: transparent; outline: none; width: 100%;
            color: var(--ms-text); font-size: 15px;
        }

        .chat-list { flex: 1; overflow-y: auto; padding: 8px; list-style: none; }
        .chat-item {
            display: flex; align-items: center; gap: 12px; padding: 10px;
            border-radius: 10px; cursor: pointer; transition: 0.1s; text-decoration: none; color: inherit;
        }
        .chat-item:hover { background-color: var(--ms-hover); }
        .chat-item.active { background-color: rgba(45, 136, 255, 0.1); }
        .avatar-container { position: relative; }
        .avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 1px solid var(--ms-border); }
        .online-badge {
            position: absolute; bottom: 2px; right: 2px; width: 14px; height: 14px;
            background: #31a24c; border-radius: 50%; border: 2px solid var(--ms-sidebar);
        }
        .chat-info { flex: 1; min-width: 0; }
        .chat-name { font-weight: 600; font-size: 15px; margin-bottom: 2px; }
        .chat-preview {
            font-size: 13px; color: var(--ms-subtext); white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis; display: flex; justify-content: space-between;
        }
        .unread-dot {
            width: 12px; height: 12px; background: var(--ms-blue); border-radius: 50%;
        }

        /* --- MAIN CHAT AREA --- */
        .chat-area {
            flex: 1;
            display: flex; flex-direction: column;
            background: var(--ms-chat-bg);
            position: relative;
        }
        .chat-topbar {
            padding: 10px 16px; border-bottom: 1px solid var(--ms-border);
            display: flex; align-items: center; justify-content: space-between;
            background: var(--ms-chat-bg); z-index: 10;
        }
        .topbar-user { display: flex; align-items: center; gap: 12px; }
        .user-status { font-size: 12px; color: var(--ms-subtext); }
        .mobile-back { display: none; margin-right: 10px; font-size: 24px; cursor: pointer; }

        /* Messages Feed */
        .messages-feed {
            flex: 1; overflow-y: auto; padding: 20px;
            display: flex; flex-direction: column; gap: 4px;
        }
        
        .message-row {
            display: flex; margin-bottom: 2px;
            align-items: flex-end; gap: 8px;
            max-width: 70%;
        }
        .message-row.me { align-self: flex-end; flex-direction: row-reverse; }
        .message-row.them { align-self: flex-start; }
        
        .msg-bubble {
            padding: 8px 12px;
            font-size: 15px;
            line-height: 1.4;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }
        
        /* My Message Style */
        .me .msg-bubble {
            background: var(--ms-blue);
            color: var(--ms-own-text);
            border-bottom-right-radius: 4px;
        }
        
        /* Their Message Style */
        .them .msg-bubble {
            background: var(--ms-gray-bubble);
            color: var(--ms-text);
            border-bottom-left-radius: 4px;
        }

        .msg-image { max-width: 250px; border-radius: 12px; display: block; cursor: pointer; margin-bottom: 5px; }
        .msg-meta { font-size: 10px; opacity: 0.7; margin-top: 4px; text-align: right; }
        .them .msg-meta { text-align: left; }

        /* Input Area */
        .chat-input-area {
            padding: 12px 16px;
            display: flex; align-items: flex-end; gap: 10px;
            border-top: 1px solid var(--ms-border);
        }
        .input-actions { display: flex; gap: 10px; padding-bottom: 8px; }
        .action-icon { color: var(--ms-blue); font-size: 20px; cursor: pointer; }
        
        .input-wrapper {
            flex: 1; background: var(--ms-input-bg); border-radius: 20px;
            padding: 8px 12px; display: flex; flex-direction: column;
        }
        .file-preview-zone { display: none; padding: 8px; border-bottom: 1px solid var(--ms-border); }
        .file-preview-zone img { height: 60px; border-radius: 8px; }
        
        .msg-input {
            width: 100%; border: none; background: transparent; outline: none;
            color: var(--ms-text); font-size: 15px; resize: none; max-height: 100px;
            font-family: inherit;
        }
        .send-btn {
            background: none; border: none; color: var(--ms-blue);
            font-size: 24px; cursor: pointer; padding-bottom: 4px;
        }

        /* Empty State */
        .empty-state {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; color: var(--ms-subtext);
        }
        .empty-state i { font-size: 64px; margin-bottom: 20px; color: var(--ms-border); }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 100%; display: <?php echo $chat_with ? 'none' : 'flex'; ?>; }
            .chat-area { display: <?php echo $chat_with ? 'flex' : 'none'; ?>; width: 100%; }
            .mobile-back { display: block; color: var(--ms-blue); }
        }
    </style>
</head>
<body>

<div class="messenger-layout">
    
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Chats</h1>
            <div style="display:flex; gap:10px;">
                <button onclick="toggleTheme()" class="icon-btn" title="Toggle Theme">
                    <i id="themeIcon" class="bi <?php echo ($theme === 'dark') ? 'bi-moon-stars' : 'bi-sun'; ?>"></i>
                </button>

                <a href="shop.php" class="icon-btn" title="Back to Shop"><i class="bi bi-shop"></i></a>
                <a href="seller_dashboard.php" class="icon-btn" title="Dashboard"><i class="bi bi-speedometer2"></i></a>
            </div>
        </div>
        
        <div class="search-container">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search Messenger" id="chatSearch">
            </div>
        </div>

        <div class="chat-list">
            <?php 
            if ($chat_histories->num_rows > 0):
                while ($row = $chat_histories->fetch_assoc()): 
                    $isActive = ($chat_with == $row['id']) ? 'active' : '';
                    $name = htmlspecialchars($row['seller_name'] ?: $row['fullname']);
                    $img = !empty($row['profile_image']) ? 'uploads/'.$row['profile_image'] : 'uploads/default-avatar.svg';
                    $msgPreview = $row['file_type'] ? 'Attachment sent' : htmlspecialchars(substr($row['message'], 0, 30)) . '...';
            ?>
            <a href="chat.php?seller_id=<?php echo $row['id']; ?>" class="chat-item <?php echo $isActive; ?>">
                <div class="avatar-container">
                    <img src="<?php echo $img; ?>" class="avatar">
                    <div class="online-badge" style="display:none;"></div> 
                </div>
                <div class="chat-info">
                    <div class="chat-name"><?php echo $name; ?></div>
                    <div class="chat-preview">
                        <span><?php echo $msgPreview; ?></span>
                        <?php if($row['unread'] > 0): ?>
                            <div class="unread-dot"></div>
                        <?php else: ?>
                            <span style="font-size:11px;">• <?php echo date("H:i", strtotime($row['created_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endwhile; 
            else: ?>
                <div style="padding:20px; text-align:center; color:var(--ms-subtext);">No chats yet. Start browsing the shop!</div>
            <?php endif; ?>
        </div>
    </aside>

    <main class="chat-area">
        <?php if ($chat_user): ?>
            <div class="chat-topbar">
                <div class="topbar-user">
                    <a href="chat.php" class="mobile-back"><i class="bi bi-arrow-left"></i></a>
                    <img src="<?php echo !empty($chat_user['profile_image']) ? 'uploads/'.$chat_user['profile_image'] : 'uploads/default-avatar.svg'; ?>" class="avatar" style="width:40px; height:40px;">
                    <div>
                        <div style="font-weight:700; font-size:16px;"><?php echo htmlspecialchars($chat_user['seller_name'] ?: $chat_user['fullname']); ?></div>
                        <div class="user-status">Active now</div>
                    </div>
                </div>
                <div style="display:flex; gap:15px;">
                    <i class="bi bi-info-circle-fill icon-btn" style="background:transparent; color:var(--ms-blue);"></i>
                </div>
            </div>

            <div class="messages-feed" id="messagesFeed">
               <?php foreach ($messages as $msg): 
                    $isMe = $msg['sender_id'] == $current_user_id;
                    $class = $isMe ? 'me' : 'them';
                ?>
                <div class="message-row <?php echo $class; ?>">
                    <?php if (!$isMe): ?>
                         <img src="<?php echo !empty($chat_user['profile_image']) ? 'uploads/'.$chat_user['profile_image'] : 'uploads/default-avatar.svg'; ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
                    <?php endif; ?>
                    
                   <div class="msg-bubble">
                        <?php if ($msg['file_path']): ?>
                            <?php if ($msg['file_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($msg['file_path']); ?>" class="msg-image" onclick="window.open(this.src)">
                            <?php elseif ($msg['file_type'] === 'video'): ?>
                                <video src="<?php echo htmlspecialchars($msg['file_path']); ?>" controls style="max-width:200px; border-radius:10px;"></video>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($msg['file_path']); ?>" style="color:inherit; text-decoration:underline;">Download File</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                        
                        <div class="msg-meta" title="<?php echo $msg['created_at']; ?>">
                            <?php echo date("H:i", strtotime($msg['created_at'])); ?>
                            <?php if($isMe && $msg['is_seen']) echo ' • Seen'; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <form class="chat-input-area" method="POST" enctype="multipart/form-data">
                <div class="input-actions">
                    <label for="file-upload" class="action-icon"><i class="bi bi-image"></i></label>
                    <input type="file" id="file-upload" name="file" style="display:none;" onchange="previewFile()">
                </div>
                
                <div class="input-wrapper">
                    <div class="file-preview-zone" id="previewZone"></div>
                    <textarea name="message" class="msg-input" placeholder="Aa" rows="1" oninput="autoResize(this)" onkeydown="handleEnter(event)"></textarea>
                </div>
                
                <button type="submit" class="send-btn"><i class="bi bi-send-fill"></i></button>
            </form>

        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-chat-square-text"></i>
                <h2>Select a conversation</h2>
                <p>Choose a chat from the sidebar to start messaging.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    // Theme Toggle Logic
    function toggleTheme() {
        const html = document.documentElement;
        const icon = document.getElementById('themeIcon');
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        // 1. Update UI immediately
        html.setAttribute('data-theme', newTheme);
        
        if (newTheme === 'dark') {
            icon.classList.remove('bi-sun');
            icon.classList.add('bi-moon-stars');
        } else {
            icon.classList.remove('bi-moon-stars');
            icon.classList.add('bi-sun');
        }

        // 2. Save preference to server (Background request)
        fetch('chat.php?toggle_theme=1');
    }

    // Auto-scroll to bottom
    const feed = document.getElementById('messagesFeed');
    if (feed) feed.scrollTop = feed.scrollHeight;

    // Auto-resize textarea
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
    }

    // Handle Enter key to submit
    function handleEnter(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            e.target.closest('form').submit();
        }
    }

    // File Preview
    function previewFile() {
        const file = document.getElementById('file-upload').files[0];
        const zone = document.getElementById('previewZone');
        zone.innerHTML = '';
        
        if (file) {
            zone.style.display = 'block';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                zone.appendChild(img);
            } else {
                zone.innerHTML = `<span style="font-size:12px; color:var(--ms-text);"><i class="bi bi-file-earmark"></i> ${file.name}</span>`;
            }
        } else {
            zone.style.display = 'none';
        }
    }

    // Simple Filter
    document.getElementById('chatSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const name = item.querySelector('.chat-name').textContent.toLowerCase();
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });

    // Auto-refresh (Polling)
    setInterval(() => {
        // Only refresh if chat is active to update new messages
        if (window.location.search.includes('seller_id=')) {
            // Placeholder for AJAX refresh logic
        }
    }, 5000);
</script>

</body>
</html>