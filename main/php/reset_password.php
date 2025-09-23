<?php
session_start();
include("db.php");

$message = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $pwd = $_POST['password'] ?? '';
    $cpwd = $_POST['confirm_password'] ?? '';
    if ($pwd !== $cpwd) {
        $message = 'Passwords do not match';
    } elseif (strlen($pwd) < 6) {
        $message = 'Use at least 6 characters';
    } else {
        $sel = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        if ($sel) {
            $sel->bind_param("s", $token);
            $sel->execute();
            $res = $sel->get_result();
            if ($res->num_rows === 1) {
                $u = $res->fetch_assoc();
                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                if ($upd) { $upd->bind_param("si", $hash, $u['id']); $upd->execute(); $message = 'Password updated. You can now log in.'; }
            } else {
                $message = 'Invalid or expired token';
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
  body { background: var(--bg-primary); color: var(--text-primary); font-family: Arial, sans-serif; }
  .card { max-width:420px; margin:40px auto; background:#111; border:1px solid #333; border-radius:10px; padding:24px; }
  input { width:100%; padding:12px; border:1px solid #44D62C; border-radius:8px; background:#1a1a1a; color:#fff; }
  .btn { width:100%; padding:12px; background:#44D62C; color:#000; border:none; border-radius:8px; margin-top:12px; font-weight:bold; cursor:pointer; }
  .msg { margin-top:10px; color:#44D62C; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Reset Password</h2>
    <?php if ($message) { echo '<div class="msg">' . htmlspecialchars($message) . '</div>'; } ?>
    <form method="POST">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
      <input type="password" name="password" placeholder="New password" required>
      <input type="password" name="confirm_password" placeholder="Confirm password" required>
      <button class="btn" type="submit">Update Password</button>
    </form>
  </div>
</body>
</html>


