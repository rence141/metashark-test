<?php
session_start();
require_once 'db.php';

// Optional email helper
$email_file = __DIR__ . '/includes/email.php';
if (!file_exists($email_file)) {
    $email_file = __DIR__ . '/email.php';
    if (!file_exists($email_file)) {
        function send_email($a,$b,$c){ return true; }
    } else { require_once $email_file; }
} else { require_once $email_file; }

if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? 'buyer';

// Fetch seller state (role + active flag + display fields)
$stmt = $conn->prepare("SELECT role, is_active_seller, seller_name, fullname, suspension_reason, suspended_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If no user row, or not suspended by either flag, send back
$isSuspendedFlag = isset($user['is_active_seller']) && (int)$user['is_active_seller'] === 0;
$isSuspendedRole = ($user['role'] ?? '') === 'suspended_seller';
if (!$user || (!$isSuspendedFlag && !$isSuspendedRole)) {
    header("Location: seller_profile.php");
    exit();
}

$reason = $user['suspension_reason'] ?? 'Your shop has been suspended pending review.';
$suspendedAt = $user['suspended_at'] ? date('M d, Y h:i A', strtotime($user['suspended_at'])) : 'N/A';
$displayName = $user['seller_name'] ?: $user['fullname'];
$sellerEmail = $user['email'] ?? ($_SESSION['email'] ?? '');

// Ensure appeals table exists
$conn->query("CREATE TABLE IF NOT EXISTS user_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_email VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    appeal_type ENUM('account','shop') NOT NULL DEFAULT 'account',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Backward compatibility: add missing columns if the table pre-exists without them
$colCheck = $conn->query("SHOW COLUMNS FROM user_appeals LIKE 'user_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE user_appeals ADD COLUMN user_id INT NULL AFTER id");
}
$colCheck = $conn->query("SHOW COLUMNS FROM user_appeals LIKE 'appeal_type'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE user_appeals ADD COLUMN appeal_type ENUM('account','shop') NOT NULL DEFAULT 'account' AFTER reason");
}

$appealSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appeal_message'])) {
    $appealMsg = trim($_POST['appeal_message']);
    if ($appealMsg !== '') {
        // Persist appeal
        $stmt = $conn->prepare("INSERT INTO user_appeals (user_id, user_email, reason, appeal_type) VALUES (?, ?, ?, 'shop')");
        $userEmail = $sellerEmail ?: 'unknown@localhost';
        $stmt->bind_param("iss", $userId, $userEmail, $appealMsg);
        $stmt->execute();
        $stmt->close();

        // Send appeal email to admin/support
        $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@localhost';
        $subject = "Appeal from suspended seller #{$userId}";
        $body = "<p>Seller: ".htmlspecialchars($displayName)."</p>"
              . "<p>User ID: {$userId}</p>"
              . "<p>Email: ".htmlspecialchars($userEmail)."</p>"
              . "<p>Suspended on: ".htmlspecialchars($suspendedAt)."</p>"
              . "<p>Original reason: ".nl2br(htmlspecialchars($reason))."</p>"
              . "<p><strong>Appeal message:</strong><br>".nl2br(htmlspecialchars($appealMsg))."</p>";
        send_email($adminEmail, $subject, $body);
        $appealSent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Suspended — Meta Shark</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #44D62C;
            --bg: #0f1115;
            --panel: #161b22;
            --text: #e6eef6;
            --muted: #94a3b8;
            --danger: #f44336;
        }
        body { margin:0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
        .card { max-width: 620px; width: 100%; background: var(--panel); border: 1px solid #242c38; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 12px; font-weight: 700; font-size: 12px; background: rgba(244,67,54,0.15); color: var(--danger); }
        .actions { display: flex; gap: 10px; margin-top: 18px; }
        .btn { padding: 10px 14px; border-radius: 10px; border: 1px solid #2a2f3a; color: var(--text); background: #11141b; text-decoration: none; font-weight: 700; display: inline-flex; gap: 8px; align-items: center; justify-content: center; flex: 1; }
        .btn:hover { border-color: var(--primary); color: var(--primary); }
        .muted { color: var(--muted); }
        h2 { margin: 12px 0 6px; }
        p { line-height: 1.5; margin: 8px 0; }
        form { margin-top: 18px; }
        textarea { width: 100%; min-height: 120px; padding: 12px; border-radius: 10px; border: 1px solid #2a2f3a; background: #0f131b; color: var(--text); resize: vertical; }
        .note { font-size: 12px; color: var(--muted); margin-top: 6px; }
        .alert { padding: 10px 12px; border-radius: 10px; background: rgba(68,214,44,0.12); color: var(--primary); border: 1px solid rgba(68,214,44,0.3); margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge"><i class="bi bi-pause-circle-fill"></i> Shop Suspended</span>
        <h2><?php echo htmlspecialchars($displayName); ?></h2>
        <p class="muted">Suspended on: <?php echo htmlspecialchars($suspendedAt); ?></p>
        <p><strong>Reason:</strong><br><?php echo nl2br(htmlspecialchars($reason)); ?></p>
        <p class="muted">You can still sign in and purchase as a buyer, but your shop and listings are hidden until this suspension is resolved.</p>
        <?php if ($appealSent): ?>
            <div class="alert"><i class="bi bi-check-circle"></i> Appeal submitted. We’ll review and get back to you.</div>
        <?php else: ?>
            <form method="POST">
                <label for="appeal" class="muted">Submit an appeal (goes to admin)</label>
                <textarea id="appeal" name="appeal_message" placeholder="Explain why your shop should be reactivated..." required></textarea>
                <div class="note">We’ll review your appeal. Response will be sent to your account email.</div>
                <div class="actions" style="margin-top:12px;">
                    <button type="submit" class="btn" style="border-color: var(--primary); color: var(--primary);"><i class="bi bi-send"></i> Send Appeal</button>
                    <a class="btn" href="profile.php"><i class="bi bi-person"></i> Go to Account</a>
                </div>
            </form>
        <?php endif; ?>
        <div class="actions">
            <a class="btn" href="contact.php"><i class="bi bi-life-preserver"></i> Contact Support</a>
        </div>
    </div>
</body>
</html>

