<?php
session_start();
include("db.php");
include_once("email.php");

$message = '';
$email = isset($_GET['email']) ? $_GET['email'] : ($_SESSION['pending_verification_email'] ?? '');

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $userId = $_SESSION['pending_verification_user_id'] ?? 0;

    if ($userId && $code !== '') {
        $sql = "SELECT verification_code, verification_expires FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 1) {
                $row = $res->fetch_assoc();
                $valid = $row['verification_code'] === $code && strtotime($row['verification_expires']) >= time();
                if ($valid) {
                    $upd = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param("i", $userId);
                        $upd->execute();
                    }

                    // Promote to full session and redirect based on role
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['email'] = $_SESSION['pending_verification_email'] ?? '';
                    $_SESSION['role'] = $_SESSION['pending_verification_role'] ?? 'buyer';
                    $_SESSION['login_success'] = true;

                    unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email'], $_SESSION['pending_verification_role']);

                    $role = $_SESSION['role'];
                    if ($role === 'seller' || $role === 'admin') {
                        header("Location: seller_dashboard.php");
                    } else {
                        header("Location: shop.php");
                    }
                    exit();
                } else {
                    $message = 'Invalid or expired code. Please request a new one.';
                }
            }
        }
    } else {
        $message = 'Enter the verification code.';
    }
}

// Resend code
if (isset($_GET['resend']) && ($_SESSION['pending_verification_user_id'] ?? 0)) {
    $userId = $_SESSION['pending_verification_user_id'];
    $code = (string)random_int(100000, 999999);
    $expiryAt = date('Y-m-d H:i:s', time() + 15 * 60);
    $upd = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param("ssi", $code, $expiryAt, $userId);
        $upd->execute();
        $message = 'A new code has been generated.';
    }
    if (!empty($_SESSION['pending_verification_email'])) {
        $subject = 'Your new Meta Shark verification code';
        $body = "Hello,\n\nYour new verification code is: " . htmlspecialchars($code) . "\nThis code expires in 15 minutes.";
        
        // Debug: Log before sending
        error_log("DEBUG: Attempting to resend email to: " . $_SESSION['pending_verification_email'] . " with code: " . $code);
        
        $emailSent = send_email($_SESSION['pending_verification_email'], $subject, $body);
        
        if ($emailSent) {
            $message .= ' Check your email (including spam).';
            error_log("DEBUG: Email resend SUCCESS for: " . $_SESSION['pending_verification_email']);
        } else {
            $message .= ' But email delivery failed. Check XAMPP error log or SMTP config.';
            error_log("DEBUG: Email resend FAILED for: " . $_SESSION['pending_verification_email'] . ". Check full error log for details.");
        }
    }
} elseif (isset($_GET['resend'])) {
    $message = 'No pending verification found. Please register/login again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="uploads/logo1.png">
    <style>
        body { font-family: Arial, sans-serif; background: #918f8fff; color: #fff; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .card { background: #fff; border:1px solid #faf1f1ff; border-radius:10px; padding:30px; width:100%; max-width:400px; }
        h1 { color:#44D62C; margin:0 0 10px; }
        p { color:#aaa; }
        input { width:100%; padding:12px; border-radius:8px; border:1px solid #44D62C; background: #fff; color: #000; margin:10px 0 20px; }
        .btn { width:100%; padding:12px; background:#44D62C; color: #fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer; }
        .link { color:#44D62C; text-decoration:none; }
        .msg { margin-top:10px; color: #44D62C; }
        .error-msg { color: #ff0000 !important; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Verify your account</h1>
        <p>We sent a 6-digit code to <?php echo htmlspecialchars($email); ?>. Enter it below.</p>
        <?php if (!empty($message)) { 
            $class = (strpos($message, 'failed') !== false || strpos($message, 'error') !== false) ? 'error-msg' : 'msg'; 
            echo '<div class="' . $class . '">' . htmlspecialchars($message) . '</div>'; 
        } ?>
        <form method="POST">
            <input type="text" name="code" placeholder="Enter 6-digit code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required>
            <button type="submit" class="btn">Verify</button>
        </form>
        <p style="margin-top:12px;">
            <a class="link" href="verify_account.php?resend=1">Resend code</a>
        </p>
    </div>
</body>
</html>