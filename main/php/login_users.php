<?php
session_start();
include("db.php");

$error = isset($_GET['error']) ? $_GET['error'] : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - MyShop</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <style>
    body {
      font-family: Arial, sans-serif;
      background: rgb(56, 55, 55);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
      padding: 0;
    }

    .form-container {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      width: 350px;
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .form-container h2 {
      margin-bottom: 20px;
    }

    .form-container input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    .form-container button {
      width: 100%;
      padding: 12px;
      background: rgb(3, 1, 0);
      border: none;
      color: white;
      font-size: 1rem;
      border-radius: 5px;
      cursor: pointer;
    }

    .form-container button:hover {
      background: rgb(0, 230, 4);
    }

    .form-container a {
      color: rgb(0, 255, 13);
      text-decoration: none;
    }

    .form-container .logo {
      width: 80px;
      height: auto;
      margin-bottom: 15px;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }

    .loading-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.9);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .loading-screen.active {
      opacity: 1;
      visibility: visible;
    }

    .worm-container {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .worm-dot {
      width: 16px;
      height: 16px;
      background-color: #44D62C;
      border-radius: 50%;
      opacity: 0.2;
    }

    .worm-dot:nth-child(1) { animation: worm 1.2s infinite 0s; }
    .worm-dot:nth-child(2) { animation: worm 1.2s infinite 0.2s; }
    .worm-dot:nth-child(3) { animation: worm 1.2s infinite 0.4s; }
    .worm-dot:nth-child(4) { animation: worm 1.2s infinite 0.6s; }
    .worm-dot:nth-child(5) { animation: worm 1.2s infinite 0.8s; }

    .loading-text {
      color: #44D62C;
      font-size: 24px;
      margin-top: 20px;
      font-weight: bold;
      text-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
    }

    @keyframes worm {
      0%, 100% {
        transform: scale(0.6);
        opacity: 0.2;
      }
      50% {
        transform: scale(1);
        opacity: 1;
        box-shadow: 0 0 10px rgba(68, 214, 44, 0.8);
      }
    }
  </style>
</head>
<body>
<div class="loading-screen">
  <div class="worm-container">
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
  </div>
  <div class="loading-text">Loading...</div>
</div>

<div class="form-container">
  <img src="uploads/logo1.png" alt="MyShop Logo" class="logo">
  <h2>Login</h2>

  <form action="loginprocess_users.php" method="POST" id="loginForm">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
    <div style="margin-top:10px;">
      <a href="forgot_password.php" style="color:#44D62C;">Forgot password?</a>
    </div>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
  </form>

  <!-- Google Login -->
  <div style="margin: 20px 0;">
    <a href="google_login.php" style="display:inline-block; text-decoration:none;">
      <img src="https://developers.google.com/identity/images/btn_google_signin_dark_normal_web.png" 
           alt="Sign in with Google">
    </a>
  </div>

  <p>Don't have an account? <a href="signup_users.php">Sign Up</a></p>

  <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
    <strong>Are you a seller?</strong><br>
    <a href="seller_login.php" style="color: #44D62C; font-weight: bold; text-decoration: none; background: #f0f0f0; padding: 8px 15px; border-radius: 5px; display: inline-block; margin-top: 10px;">Seller Login</a>
  </p>
</div>

<script>
  document.getElementById('loginForm').addEventListener('submit', function() {
    document.querySelector('.loading-screen').classList.add('active');
  });

  window.addEventListener('load', function() {
    const errorMessage = document.querySelector('p[style="color:red;"]');
    if (!errorMessage) {
      document.querySelector('.loading-screen').classList.remove('active');
    }
  });
</script>
</body>
</html>
