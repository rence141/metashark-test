<?php
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Link Sent</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <?php include('theme_toggle.php'); ?>
  <style>
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
    }
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
      padding: 32px 28px 28px 28px;
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
    .msg {
      margin-top: 10px;
      color: #44D62C;
      font-size: 1.08rem;
      text-align: center;
      min-height: 24px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h2>Reset Link Sent</h2>
    <div class="msg">A password reset link has been sent to email: <b><?php echo $email; ?></b></div>
  </div>
</body>
</html>
