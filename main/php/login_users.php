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
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="../../main/js/theme-system.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary-color: #00ff88;
      --secondary-color: #00d4ff;
      --accent-color: #00ff88;
      --text-primary: #333;
      --text-secondary: #6c757d;
      --background-primary: linear-gradient(135deg,rgb(2, 2, 4) 0%,rgb(14, 90, 5) 25%,rgb(17, 147, 22) 50%,rgb(28, 255, 28) 75%,rgb(0, 0, 0) 100%);
      --card-background: white;
      --border-color: rgba(0, 0, 0, 0.1);
      --shadow-color: rgba(0, 0, 0, 0.3);
      --placeholder-color: rgb(24, 195, 5);
      --error-color: #ff4757;
    }

    .theme-dark {
      --text-primary: #e0e0e0;
      --text-secondary: #bbb;
      --background-primary: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 25%, #2a2a2a 50%, #3a3a3a 75%, #000000 100%);
      --card-background: #1e1e1e;
      --border-color: rgba(255, 255, 255, 0.1);
      --shadow-color: rgba(0, 0, 0, 0.5);
      --placeholder-color:rgb(66, 67, 66);
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--background-primary);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
      color: var(--text-primary);
    }

    /* Animated background particles */
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                  radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                  radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
      animation: backgroundShift 20s ease-in-out infinite;
    }

    @keyframes backgroundShift {
      0%, 100% { opacity: 0.7; }
      50% { opacity: 1; }
    }

    .form-container {
      background: var(--card-background);
      padding: 40px;
      border-radius: 20px;
      width: 100%;
      max-width: 400px;
      text-align: center;
      position: relative;
      z-index: 2;
      box-shadow: 0 20px 40px var(--shadow-color);
      border: 3px solid transparent;
      background-clip: padding-box;
    }

    .form-container::before {
      content: '';
      position: absolute;
      top: -3px;
      left: -3px;
      right: -3px;
      bottom: -3px;
      background: var(--card-background);
      border-radius: 20px;
      z-index: -1;
    }

    @keyframes cardFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }

    @keyframes borderGlow {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    .logo {
      width: 80px;
      height: 80px;
      margin: 0 auto 20px;
      border-radius: 50%;
      display: block;
      object-fit: cover;
      box-shadow: 0 10px 20px rgba(0, 255, 47, 0.3);
      animation: logoPulse 2s ease-in-out infinite;
    }

    @keyframes logoPulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    .form-container h2 {
      margin-bottom: 30px;
      color: var(--primary-color);
      font-size: 28px;
      font-weight: 700;
    }

    .form-container input {
      width: 100%;
      padding: 15px 20px;
      margin: 10px 0;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      font-size: 16px;
      background: #f8f9fa;
      transition: all 0.3s ease;
      outline: none;
    }

    .form-container input:focus {
      border-color: #00d4ff;
      background: white;
      box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
      transform: translateY(-2px);
    }

    .form-container input::placeholder {
      color: var(--placeholder-color);
    }

    .login-btn {
      border-color: var(--primary-color);
      width: 100%;
      padding: 15px;
      background: #000;
      color: white;
      font-size: 16px;
      font-weight: 600;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
    }

    .login-btn:hover {
      background: var(--primary-color);
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(1, 235, 28, 0.2);
    }

    .forgot-password {
      margin: 15px 0;
    }

    .forgot-password a {
      color: var(--primary-color);
      text-decoration: none;
      font-size: 14px;
      transition: color 0.3s ease;
    }

    .forgot-password a:hover {
      color: var(--text-secondary);
    }

    .google-btn {
      width: 100%;
      padding: 12px;
      background: #4285f4;
      border: none;
      color: white;
      font-size: 14px;
      font-weight: 500;
      border-radius: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin: 20px 0;
      transition: all 0.3s ease;
    }

    .google-btn:hover {
      background:rgb(12, 144, 14);
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(66, 133, 244, 0.3);
    }

    .google-icon {
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color:rgb(72, 244, 66);
    }

    .signup-link, .seller-link {
      margin: 15px 0;
      font-size: 14px;
      color: var(--text-secondary);
    }

    .signup-link a, .seller-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .signup-link a:hover, .seller-link a:hover {
      color: var(--secondary-color);
    }

    .loading-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.9);
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

    .loading-dots {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
    }

    .loading-dot {
      width: 12px;
      height: 12px;
      background: linear-gradient(45deg, #00ff88, #00d4ff);
      border-radius: 50%;
      animation: loadingBounce 1.4s ease-in-out infinite both;
    }

    .loading-dot:nth-child(1) { animation-delay: -0.32s; }
    .loading-dot:nth-child(2) { animation-delay: -0.16s; }
    .loading-dot:nth-child(3) { animation-delay: 0s; }

    @keyframes loadingBounce {
      0%, 80%, 100% {
        transform: scale(0);
        opacity: 0.5;
      }
      40% {
        transform: scale(1);
        opacity: 1;
      }
    }

    .loading-text {
      color: #00ff88;
      font-size: 18px;
      font-weight: 600;
      text-shadow: 0 0 10px rgba(0, 255, 136, 0.5);
    }

    .error-message {
      color: var(--error-color);
      background: rgba(255, 71, 87, 0.1);
      padding: 10px;
      border-radius: 8px;
      margin: 10px 0;
      font-size: 14px;
      border-left: 3px solid var(--error-color);
    }

    /* Responsive Design */
    @media (max-width: 480px) {
      .form-container {
        padding: 30px 20px;
        margin: 10px;
      }
      
      .form-container h2 {
        font-size: 24px;
      }
      
      .logo {
        width: 60px;
        height: 60px;
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
<div class="loading-screen">
  <div class="loading-dots">
    <div class="loading-dot"></div>
    <div class="loading-dot"></div>
    <div class="loading-dot"></div>
  </div>
  <div class="loading-text">Signing you in...</div>
</div>

<div class="form-container">
  <img src="uploads/logo1.png" alt="MyShop Logo" class="logo">
  <h2>Login</h2>

  <form action="loginprocess_users.php" method="POST" id="loginForm">
    <input type="email" name="email" placeholder="admin" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit" class="login-btn">Login</button>
    
    <?php if (!empty($error)): ?>
      <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="forgot-password">
      <a href="forgot_password.php">Forgot password?</a>
    </div>
  </form>

  <!-- Google Login Button -->
  <button class="google-btn" onclick="window.location.href='google_login.php'">
    <div class="google-icon">G</div>
    Sign in with Google
  </button>

  <div class="signup-link">
    Don't have an account? <a href="signup_users.php">Sign Up</a>
  </div>

  <div class="seller-link">
    Are you a seller? <a href="seller_login.php">Seller Login</a>
  </div>
</div>

<script>
  document.getElementById('loginForm').addEventListener('submit', function() {
    document.querySelector('.loading-screen').classList.add('active');
  });

  window.addEventListener('load', function() {
    const errorMessage = document.querySelector('.error-message');
    if (!errorMessage) {
      document.querySelector('.loading-screen').classList.remove('active');
    }
  });

  // Add some interactive effects
  document.querySelectorAll('input').forEach(input => {
    input.addEventListener('focus', function() {
      this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
      this.parentElement.classList.remove('focused');
    });
  });
</script>
</body>
</html>
