<?php
session_start();
include("db.php");
include_once("email.php");

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');

  if ($email !== '') {
    // 1. Check if the user exists first
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      // Generate secure token
      $token = bin2hex(random_bytes(32));

      // ✅ Use MySQL to generate expiry to avoid timezone mismatch
      $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
      if ($upd) {
        $upd->bind_param("ss", $token, $email);
        $upd->execute();

        // Build reset link
        $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
                $_SERVER['HTTP_HOST'] .
                dirname($_SERVER['REQUEST_URI']) .
                "/reset_password.php?token=" . urlencode($token);

        $subject = 'Password Reset Request';
        $body = "Hi,\n\nClick the link below to reset your password (valid for 1 hour):\n\n$link\n\nIf you didn’t request a password reset, please ignore this email.";

        // Send email (if email.php is configured)
        send_email($email, $subject, $body);
      }
    }

    // Redirect to confirmation page (always, even if email doesn’t exist)
    header('Location: reset_link_sent.php?email=' . urlencode($email));
    exit;
  } else {
    $message = 'Please enter your email address.';
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
    :root {
      --primary-color: #44D62C;
      --text-primary: #fff;
      --text-secondary: #aaa;
      --background-primary: #918f8f;
      --card-background: #111;
      --border-color: #333;
      --shadow-color: rgba(0, 0, 0, 0.5);
      --placeholder-color: #44D62C;
    }

    .theme-dark {
      --text-primary: #e0e0e0;
      --text-secondary: #bbb;
      --background-primary: #1e1e1e;
      --card-background: #1e1e1e;
      --border-color: rgba(255, 255, 255, 0.1);
      --shadow-color: rgba(0, 0, 0, 0.5);
      --placeholder-color: #44D62C;
    }

    .theme-light {
      --text-primary: #333;
      --text-secondary: #6c757d;
      --background-primary: #f8f9fa;
      --card-background: #fff;
      --border-color: rgba(0, 0, 0, 0.1);
      --shadow-color: rgba(0, 0, 0, 0.3);
      --placeholder-color: #44D62C;
    }

    body {
      font-family: Arial, sans-serif;
      background: var(--background-primary);
      color: var(--text-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    .card {
      max-width: 420px;
      margin: 40px auto;
      background: var(--card-background);
      border: 1px solid var(--border-color);
      border-radius: 10px;
      padding: 24px;
      box-shadow: 0 10px 20px var(--shadow-color);
      text-align: center;
    }

    h2 {
      color: var(--primary-color);
      margin: 0 0 20px;
      font-size: 24px;
      font-weight: bold;
    }

    input {
      display: block;
      width: calc(100% - 40px);
      max-width: 360px;
      margin: 10px auto;
      padding: 12px;
      border: 1px solid var(--primary-color);
      border-radius: 8px;
      background: var(--card-background);
      color: var(--text-primary);
      font-size: 16px;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
      z-index: 1;
    }

    input::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(to right, transparent, rgba(68, 214, 44, 0.3), transparent);
      transition: transform 0.3s ease-in-out;
      z-index: -1;
    }

    input:focus::before {
      transform: translateX(100%);
    }

    input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 8px rgba(68, 214, 44, 0.5);
      transform: translateY(-2px);
      outline: none;
    }

    input::placeholder {
      color: var(--placeholder-color);
      opacity: 0.7;
    }

    .btn {
      width: calc(100% - 40px);
      max-width: 360px;
      margin: 12px auto;
      padding: 12px;
      background: var(--primary-color);
      color: #000;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      display: block;
      transition: 0.2s;
    }

    .btn:hover {
      background: #2ecc40;
    }

    .msg {
      margin-top: 10px;
      color: var(--primary-color);
    }
  </style>
</head>
<body>
  <div class="card">
    <h2>Forgot Password</h2>
    <form method="POST">
      <input type="email" name="email" placeholder="Your email" required>
      <button class="btn" type="submit">Send reset link</button>
    </form>
    <?php if ($message): ?>
      <div class="msg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
