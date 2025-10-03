<?php
session_start();
include("db.php");
include_once("email.php");

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if ($email !== '') {
    // Always generate a token and try to update, even if user does not exist
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    if ($upd) { $upd->bind_param("sss", $token, $expires, $email); $upd->execute(); }

    $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . urlencode($token);
    $subject = 'Password Reset';
    $body = "Click the link to reset your password (valid for 1 hour):\n$link";
    send_email($email, $subject, $body);

    // Redirect to confirmation page
    header('Location: reset_link_sent.php?email=' . urlencode($email));
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password</title>
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
    <h2>Forgot Password</h2>
    <form method="POST">
      <input type="email" name="email" placeholder="Your email" required>
      <button class="btn" type="submit">Send reset link</button>
    </form>
  </div>
</body>
</html>


