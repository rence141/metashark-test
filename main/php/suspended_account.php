<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

$msg = '';
$msg_type = '';

// Ensure appeals table exists
$conn->query("CREATE TABLE IF NOT EXISTS user_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_email VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    appeal_type ENUM('account','shop') NOT NULL DEFAULT 'account',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Backward compatibility: add missing columns if table was created earlier without them
$colCheck = $conn->query("SHOW COLUMNS FROM user_appeals LIKE 'user_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE user_appeals ADD COLUMN user_id INT NULL AFTER id");
}
$colCheck = $conn->query("SHOW COLUMNS FROM user_appeals LIKE 'appeal_type'");
$colExists = ($colCheck && $colCheck->num_rows > 0);
if (!$colExists) {
    $conn->query("ALTER TABLE user_appeals ADD COLUMN appeal_type ENUM('account','shop') NOT NULL DEFAULT 'account' AFTER reason");
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_appeal'])) {
    
    $reason = trim($_POST['appeal_reason']);
    $email = trim($_POST['contact_email']);
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if (empty($reason) || empty($email)) {
        $msg = "Please fill in all fields.";
        $msg_type = "error";
    } else {
        // Insert appeal into database
        $stmt = $conn->prepare("INSERT INTO user_appeals (user_id, user_email, reason, appeal_type) VALUES (?, ?, ?, 'account')");
        if ($stmt) {
            $stmt->bind_param("iss", $userId, $email, $reason);
            if ($stmt->execute()) {
                $msg = "Appeal submitted successfully. Our team will review your request.";
                $msg_type = "success";
            } else {
                $msg = "Database error: " . $conn->error;
                $msg_type = "error";
            }
            $stmt->close();
        } else {
            $msg = "System error. Please try again later.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Access Restricted - Meta Shark</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* Softer gray */
            color: #1f2937;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 550px;
            width: 100%;
        }
        .icon-box {
            color: #ef4444; /* Red-500 */
            font-size: 64px;
            margin-bottom: 24px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        h1 {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            margin: 0 0 12px 0;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 24px;
        }
        
        /* Appeal Form Styling */
        .appeal-form {
            text-align: left;
            margin-top: 32px;
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
        }
        .appeal-form h2 {
            font-size: 18px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group { margin-bottom: 16px; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        button {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 24px;
            background: #111827; /* Dark button */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
        }
        button:hover { background: #000; transform: translateY(-1px); }

        /* Alerts */
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <div class="card">
        <div class="icon-box">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        
        <h1>Account Suspended</h1>
        
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($msg_type !== 'success'): ?>
            <p>
                Your account access has been temporarily restricted due to a violation of our Terms of Service or suspicious activity.
            </p>
            <p style="font-size: 14px; color: #6b7280;">
                If you believe this is a mistake, please file an appeal below immediately.
            </p>
            
            <div class="appeal-form">
                <h2><i class="bi bi-send"></i> Submit an Appeal</h2>
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="contact_email">Registered Email Address</label>
                        <input type="email" id="contact_email" name="contact_email" required placeholder="e.g. john@example.com">
                    </div>

                    <div class="form-group">
                        <label for="appeal_reason">Explanation / Reason</label>
                        <textarea id="appeal_reason" name="appeal_reason" required placeholder="Please explain why your account should be reinstated..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_appeal">Submit Appeal</button>
                </form>
            </div>
        <?php else: ?>
            <div style="margin-top:20px;">
                <p>Thank you. Your appeal ID has been recorded.</p>
                <a href="login_users.php" style="color:#2563eb; text-decoration:none; font-weight:600;">Return to Login</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
