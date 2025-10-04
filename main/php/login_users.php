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
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary-color: #06dd78ff;
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
      --logo-container: white;
    }

    .theme-light{
      --logo-container: black;
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
                  radial-gradient(circle at 80% 20%, rgba(135, 255, 119, 0.3) 0%, transparent 50%),
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
      background-color: var(--logo-container);
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

    .google-btn {
    position: relative;
    overflow: hidden;
    background: #5a87e7ff; /* Default background, adjust as needed */
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.google-btn:hover {
    transform: translateY(-2px);
}

.google-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgb(12, 144, 14);
    transition: transform 0.2s ease-in-out;
    z-index: -1;
}

.google-btn:hover::before {
    transform: translateX(100%);
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
    <img src="Uploads/logo1.png" alt="MyShop Logo" class="logo">
    <h2 class>Meta Shark Login</h2>

    <form action="loginprocess_users.php" method="POST" id="loginForm">
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" class="login-btn">Login</button>
      
      <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
    </form>

    <!-- Google Login Button -->
    <button class="google-btn" id="googleLoginBtn">
      <div class="google-icon">G</div>
      Sign in with Google
    </button>

    <div class="forgot-password">
      <a href="forgot_password.php">Forgot password?</a>
    </div>

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

    document.getElementById('googleLoginBtn').addEventListener('click', function(e) {
      e.preventDefault(); // Prevent any form-related behavior
      window.location.href = 'google_login.php'; // Redirect to Google login
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

<script>
  class ThemeSystem {
    constructor() {
        this.themes = {
            light: 'light',
            dark: 'dark',
            device: 'device'
        };
        this.currentTheme = this.getStoredTheme() || this.themes.device;
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme);
        this.createThemeToggle();
        this.watchSystemTheme();
    }

    getStoredTheme() {
        return localStorage.getItem('theme');
    }

    setStoredTheme(theme) {
        localStorage.setItem('theme', theme);
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        const body = document.body;
        const root = document.documentElement;
        
        // Remove existing theme classes
        body.classList.remove('theme-light', 'theme-dark', 'theme-device');
        
        // Apply new theme
        if (theme === 'device') {
            const systemTheme = this.getSystemTheme();
            body.classList.add(`theme-${systemTheme}`);
            body.setAttribute('data-theme', systemTheme);
        } else {
            body.classList.add(`theme-${theme}`);
            body.setAttribute('data-theme', theme);
        }

        this.currentTheme = theme;
        this.setStoredTheme(theme);
        this.updateToggleUI();
    }

    createThemeToggle() {
        // Check if toggle already exists
        if (document.querySelector('.theme-toggle')) return;

        const toggle = document.createElement('div');
        toggle.className = 'theme-toggle';
        toggle.innerHTML = `
            <button class="theme-toggle-btn" title="Toggle Theme">
                <span class="theme-icon">
                    <i class="bi bi-laptop" data-theme="device"></i>
                    <i class="bi bi-sun" data-theme="light" style="display: none;"></i>
                    <i class="bi bi-moon" data-theme="dark" style="display: none;"></i>
                </span>
                <span class="theme-label">Device</span>
            </button>
            <div class="theme-menu">
                <button class="theme-option" data-theme="light">
                    <i class="bi bi-sun"></i>
                    <span>Light</span>
                </button>
                <button class="theme-option" data-theme="dark">
                    <i class="bi bi-moon"></i>
                    <span>Dark</span>
                </button>
                <button class="theme-option" data-theme="device">
                    <i class="bi bi-laptop"></i>
                    <span>Device</span>
                </button>
            </div>
        `;

        // Add to page
        document.body.appendChild(toggle);

        // Add event listeners
        toggle.querySelector('.theme-toggle-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            toggle.classList.toggle('active');
        });

        toggle.querySelectorAll('.theme-option').forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const theme = option.dataset.theme;
                this.applyTheme(theme);
                toggle.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target)) {
                toggle.classList.remove('active');
            }
        });
    }

    updateToggleUI() {
        const toggle = document.querySelector('.theme-toggle');
        if (!toggle) return;

        const themeLabel = toggle.querySelector('.theme-label');
        const allIcons = toggle.querySelectorAll('.theme-icon i');
        const activeIcon = toggle.querySelector(`.theme-icon i[data-theme="${this.currentTheme}"]`);
        
        // Update icon
        allIcons.forEach(icon => icon.style.display = 'none');
        if (activeIcon) activeIcon.style.display = 'block';

        // Update label
        themeLabel.textContent = this.currentTheme.charAt(0).toUpperCase() + this.currentTheme.slice(1);

        // Update active state in menu
        toggle.querySelectorAll('.theme-option').forEach(option => {
            option.classList.toggle('active', option.dataset.theme === this.currentTheme);
        });
    }

    watchSystemTheme() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addListener(() => {
            if (this.currentTheme === 'device') {
                this.applyTheme('device');
            }
        });
    }

    setTheme(theme) {
        if (this.themes[theme]) {
            this.applyTheme(theme);
        }
    }

    getEffectiveTheme() {
        if (this.currentTheme === 'device') {
            return this.getSystemTheme();
        }
        return this.currentTheme;
    }
}

// CSS for theme toggle
const themeStyles = `
<style>
.theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.theme-toggle-btn {
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid rgba(0, 255, 136, 0.3);
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.theme-toggle-btn:hover {
    background: rgba(255, 255, 255, 1);
    border-color: rgba(0, 255, 136, 0.6);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.theme-icon {
    position: relative;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.theme-icon i {
    position: absolute;
    font-size: 16px;
    color: #333;
    transition: all 0.3s ease;
}

.theme-label {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.theme-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid rgba(0, 255, 136, 0.3);
    border-radius: 12px;
    padding: 8px;
    min-width: 140px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.theme-toggle.active .theme-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.theme-option {
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #333;
    transition: all 0.2s ease;
    text-align: left;
}

.theme-option:hover {
    background: rgba(0, 255, 136, 0.1);
    color: #00ff88;
}

.theme-option.active {
    background: rgba(0, 255, 136, 0.2);
    color: #00ff88;
    font-weight: 600;
}

.theme-option i {
    width: 16px;
    text-align: center;
}

/* Dark theme styles */
.theme-dark .theme-toggle-btn {
    background: rgba(30, 30, 30, 0.9);
    border-color: rgba(0, 255, 136, 0.3);
    color: #e0e0e0;
}

.theme-dark .theme-toggle-btn:hover {
    background: rgba(30, 30, 30, 1);
    border-color: rgba(0, 255, 136, 0.6);
}

.theme-dark .theme-icon i,
.theme-dark .theme-label {
    color: #e0e0e0;
}

.theme-dark .theme-menu {
    background: rgba(30, 30, 30, 0.95);
    border-color: rgba(0, 255, 136, 0.3);
}

.theme-dark .theme-option {
    color: #e0e0e0;
}

.theme-dark .theme-option:hover {
    background: rgba(0, 255, 136, 0.1);
    color: #00ff88;
}

.theme-dark .theme-option.active {
    background: rgba(0, 255, 136, 0.2);
    color: #00ff88;
}

/* Responsive */
@media (max-width: 480px) {
    .theme-toggle {
        top: 15px;
        right: 15px;
    }
    
    .theme-toggle-btn {
        padding: 10px 12px;
    }
    
    .theme-label {
        font-size: 12px;
    }
    
    .theme-menu {
        right: -10px;
        min-width: 120px;
    }
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', themeStyles);

// Initialize theme system when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.themeSystem = new ThemeSystem();
});

// Export for use in other scripts
window.ThemeSystem = ThemeSystem;
</script>
</body>
</html>
