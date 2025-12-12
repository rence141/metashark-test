<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Require admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$theme = $_SESSION['theme'] ?? 'dark';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: appeals.php');
    exit;
}

// Ensure table exists (defensive)
$conn->query("CREATE TABLE IF NOT EXISTS user_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_email VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    appeal_type ENUM('account','shop') NOT NULL DEFAULT 'account',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 1. Fetch the appeal
$stmt = $conn->prepare("SELECT * FROM user_appeals WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$appeal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appeal || $appeal['appeal_type'] !== 'shop') {
    header('Location: appeals.php');
    exit;
}

// --- FIX START: Correct Email if Unknown ---
// If the saved email is 'unknown@localhost' but we have a User ID, fetch the real email
if (
    ($appeal['user_email'] === 'unknown@localhost' || $appeal['user_email'] === '') 
    && !empty($appeal['user_id'])
) {
    // We assume your main users table is named 'users' and has columns 'id' and 'email'
    $userStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("i", $appeal['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userRow = $userResult->fetch_assoc()) {
            // Update the variable for display
            $appeal['user_email'] = $userRow['email'];
            
            // OPTIONAL: Self-heal the database record so it's fixed permanently
            $updateFix = $conn->prepare("UPDATE user_appeals SET user_email = ? WHERE id = ?");
            $updateFix->bind_param("si", $userRow['email'], $id);
            $updateFix->execute();
            $updateFix->close();
        }
        $userStmt->close();
    }
}
// --- FIX END ---
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Appeal #<?php echo (int)$appeal['id']; ?> — Meta Shark</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #44D62C;
            --bg: #0f1115;
            --panel: #161b22;
            --panel-border: #242c38;
            --text: #e6eef6;
            --text-muted: #94a3b8;
            --radius: 16px;
            --shadow: 0 8px 20px rgba(0,0,0,0.35);
        }
        [data-theme="light"] {
            --bg: #f3f4f6;
            --panel: #ffffff;
            --panel-border: #e5e7eb;
            --text: #1f2937;
            --text-muted: #6b7280;
            --shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 24px; }
        .card { background: var(--panel); border: 1px solid var(--panel-border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; max-width: 900px; margin: 0 auto; }
        h1 { margin-bottom: 12px; }
        .muted { color: var(--text-muted); }
        .section { margin-top: 16px; }
        .label { font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; background: rgba(0,212,255,0.12); color: #00d4ff; }
        pre { white-space: pre-wrap; font-family: inherit; background: rgba(0,0,0,0.15); padding: 12px; border-radius: 12px; border: 1px solid var(--panel-border); }
        a.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 10px; text-decoration: none; border: 1px solid var(--panel-border); color: var(--text); margin-top: 14px; }
        a.btn:hover { border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>
    <div class="card">
        <div style="display:flex; justify-content: space-between; align-items: center;">
            <h1>Shop Appeal #<?php echo (int)$appeal['id']; ?></h1>
            <span class="pill">Shop Appeal</span>
        </div>
        <div class="section">
            <div class="label">User</div>
            <?php if (!empty($appeal['user_id'])): ?>
                <a class="btn" href="admin_users.php?search=<?php echo (int)$appeal['user_id']; ?>"><i class="bi bi-person"></i> User ID: <?php echo (int)$appeal['user_id']; ?></a>
            <?php endif; ?>
            <div style="margin-top:6px;"><?php echo htmlspecialchars($appeal['user_email']); ?></div>
        </div>
        <div class="section">
            <div class="label">Submitted</div>
            <div><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($appeal['created_at']))); ?></div>
        </div>
        <div class="section">
            <div class="label">Reason</div>
            <pre><?php echo htmlspecialchars($appeal['reason']); ?></pre>
        </div>
        <div class="section">
            <a class="btn" href="appeals.php"><i class="bi bi-arrow-left"></i> Back to Appeals</a>
        </div>
    </div>
</body>
</html>