<?php
session_start();
include("db.php");

$message = '';

// Get token (from GET or POST)
$token = $_POST['token'] ?? $_GET['token'] ?? '';

// --- Validate token (GET request) ---
if ($token && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $sel = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    if ($sel) {
        $sel->bind_param("s", $token);
        $sel->execute();
        $res = $sel->get_result();

        if ($res->num_rows !== 1) {
            $message = 'Invalid or expired token. Please restart the password reset process.';
            $token = ''; // hide form
        }
    } else {
        $message = 'Database error while checking token.';
        $token = '';
    }
} elseif (!$token && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $message = 'Error: Missing password reset token.';
}

// --- Handle password submission (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $pwd = $_POST['password'] ?? '';
    $cpwd = $_POST['confirm_password'] ?? '';

    if ($pwd !== $cpwd) {
        $message = 'Passwords do not match.';
    } elseif (strlen($pwd) < 6) {
        $message = 'Use at least 6 characters for your new password.';
    } else {
        // Double-check token validity again before changing password
        $sel = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        if ($sel) {
            $sel->bind_param("s", $token);
            $sel->execute();
            $res = $sel->get_result();

            if ($res->num_rows === 1) {
                $user = $res->fetch_assoc();

                // Hash and update password, invalidate token
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                if ($upd) {
                    $upd->bind_param("si", $hash, $user['id']);
                    if ($upd->execute()) {
                        $message = 'Password successfully updated! You can now log in.';
                        $token = ''; // Hide form after success
                    } else {
                        $message = 'Database error while updating password.';
                    }
                }
            } else {
                $message = 'Invalid or expired token. Please request a new link.';
                $token = '';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <?php include('theme_toggle.php'); ?>
  <style>
    html, body { height: 100%; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--bg-primary);
      color: var(--text-primary);
      font-family: 'Segoe UI', Arial, sans-serif;
    }
    .card {
      width: 100%;
      max-width: 400px;
      background: #181818cc;
      border: 1px solid #222;
      border-radius: 16px;
      padding: 32px 28px;
      box-shadow: 0 6px 32px 0 #0008;
      display: flex;
      flex-direction: column;
      align-items: center;
      animation: fadeIn 0.7s;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: none; }
    }
    .logo {
      width: 64px;
      height: 64px;
      margin-bottom: 18px;
      border-radius: 50%;
      box-shadow: 0 2px 8px #0004;
      background: #fff;
      object-fit: cover;
    }
    h2 {
      margin: 0 0 18px 0;
      font-weight: 600;
      font-size: 1.5rem;
      letter-spacing: 0.5px;
    }
    form {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 14px;
      align-items: center;
      justify-content: center;
    }
    input[type="password"] {
      width: 90%;
      max-width: 260px;
      margin: 0 auto;
      display: block;
      padding: 12px 14px;
      border: 1px solid #44D62C;
      border-radius: 8px;
      background: #232323;
      color: #fff;
      font-size: 1rem;
      transition: border 0.2s;
      text-align: center;
    }
    input[type="password"]:focus {
      border: 1.5px solid #44D62C;
      outline: none;
      background: #222;
    }
    .btn {
      width: 100%;
      padding: 12px;
      background: linear-gradient(90deg, #44D62C 60%, #2ecc40 100%);
      color: #000;
      border: none;
      border-radius: 8px;
      margin-top: 8px;
      font-weight: bold;
      font-size: 1.08rem;
      cursor: pointer;
      box-shadow: 0 2px 8px #44d62c22;
      transition: background 0.2s;
    }
    .btn:hover {
      background: linear-gradient(90deg, #2ecc40 60%, #44D62C 100%);
    }
    .msg {
      margin-top: 10px;
      color: #44D62C;
      font-size: 1.02rem;
      text-align: center;
      min-height: 24px;
    }
    .msg.error { color: #ff4d4f; font-weight: bold; }
  </style>
</head>
<body>
  <div class="card">
    <img src="uploads/logo1.png" alt="Logo" class="logo">
    <h2>Reset Password</h2>

    <?php 
      if ($message) {
        $is_error = strpos($message, 'successfully updated') === false;
        $class = $is_error ? 'msg error' : 'msg';
        echo '<div class="' . $class . '">' . htmlspecialchars($message) . '</div>';
      }
    ?>

    <?php if ($token): ?>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="password" name="password" placeholder="New password" required minlength="6">
        <input type="password" name="confirm_password" placeholder="Confirm password" required minlength="6">
        <button class="btn" type="submit">Update Password</button>
      </form>
    <?php endif; ?>

    <div style="margin-top: 20px;">
      <a href="login_users.php" style="color: #44D62C; text-decoration: none; font-size: 0.95rem;">Back to Login</a>
    </div>
  </div>
</body>
</html>
