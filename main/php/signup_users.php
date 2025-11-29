<?php
// Enable error reporting for debugging (comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent stray output
ob_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        // Connect to DB
        $conn = new mysqli("localhost", "root", "", "myshop");
        if ($conn->connect_error) {
            $error = "Connection failed: " . $conn->connect_error;
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // Use prepared statement to prevent SQL injection
            $sql = "INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashed);
            
            if ($stmt->execute()) {
                $success = "Signup successful!";
            } else {
                $error = "Error: " . $conn->error;
            }
            $stmt->close();
            $conn->close();
        }
    }
}

// End output buffering
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - MyShop</title>
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
/* General Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --primary-color: #06d675ff;
  --secondary-color: #00d4ff;
  --accent-color: #00ff88;
  --text-primary: #333;
  --text-secondary: #6c757d;
  --background-primary: linear-gradient(135deg, rgb(2, 2, 4) 0%, rgb(14, 90, 5) 25%, rgb(17, 147, 22) 50%, rgb(28, 255, 28) 75%, rgb(0, 0, 0) 100%);
  --card-background: white;
  --border-color: rgba(0, 0, 0, 0.1);
  --shadow-color: rgba(0, 0, 0, 0.3);
  --placeholder-color: rgba(66, 66, 66, 1);
  --error-color: #ff4757;
}

.theme-dark {
  --text-primary: #e0e0e0;
  --text-secondary: #bbb;
  --background-primary: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 25%, #2a2a2a 50%, #3a3a3a 75%, #000000 100%);
  --card-background: #1e1e1e;
  --border-color: rgba(255, 255, 255, 0.1);
  --shadow-color: rgba(0, 0, 0, 0.5);
  --placeholder-color: rgb(87, 85, 85);
  --logo-container: white;
}

.theme-light {
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
              radial-gradient(circle at 80% 20%, rgba(119, 255, 126, 0.3) 0%, transparent 50%),
              radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
  animation: backgroundShift 20s ease-in-out infinite;
}

@keyframes backgroundShift {
  0%, 100% { opacity: 0.7; }
  50% { opacity: 1; }
}

/* Container */
.form-container {
  background: var(--card-background);
  padding: 50px;
  border-radius: 20px;
  width: 100%;
  max-width: 550px;
  text-align: center;
  position: relative;
  z-index: 2;
  box-shadow: 0 20px 40px var(--shadow-color);
  border: 3px solid transparent;
  background-clip: padding-box;
  animation: fadeIn 0.5s ease-in-out;
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

/* Logo */
.form-container img.logo {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  margin: 0 auto 20px;
  display: block;
  object-fit: cover;
  background-color: var(--logo-container);
}

@keyframes logoPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

/* Heading */
.form-container h2 {
  margin-bottom: 20px;
  color: var(--primary-color);
  font-size: 32px;
  font-weight: 700;
}

/* Input Row for side-by-side fields */
.input-row {
  display: flex;
  gap: 10px;
  margin: 10px 0;
}

/* Input Fields */
.form-container input {
  width: 100%;
  padding: 16px 22px;
  border: 2px solid #e1e5e9;
  border-radius: 14px;
  font-size: 17px;
  background: #f8f9fa;
  transition: all 0.3s ease;
  outline: none;
}

.input-row input {
  flex: 1;
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

/* Primary action button (does not affect .google-btn) */
.primary-btn {
  width: 100%;
  padding: 16px;
  background: #000;
  border: 2px solid var(--primary-color);
  color: #fff;
  font-size: 17px;
  font-weight: 600;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
}
.primary-btn:hover {
  background: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(1, 235, 28, 0.2);
}

/* Google Button */
.google-btn {
  width: 100%;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #f8f9fa;
  border: 1px solid #dadce0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  justify-content: center;
  margin: 12px 0 18px;
  transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.08s ease;
  box-shadow: none;
}

.google-btn:hover {
  background: #f7f8f9;
  transform: translateY(-1px);
  box-shadow: 0 1px 1px rgba(60, 64, 67, 0.08);
}


.google-icon {
  display: inline-flex;
  width: 18px;
  height: 18px;
  align-items: center;
  justify-content: center;
  flex: 0 0 18px;
}

.google-text {
  display: inline-block;
  line-height: 1;
  font-size: 14px;
  font-weight: 500;
  color: #000000;
}

/* Dark theme overrides for Google button */
.theme-dark .google-btn {
  background: white;
  color: #000000;
  border: 1px solid #4a4a4a;
}

.theme-dark .google-btn:hover {
  background: #f7f8f9;
  box-shadow: 0 1px 1px rgba(255, 255, 255, 0.1);
}

.theme-dark .google-btn .google-text {
  color: #000000;
}

/* Links */
.form-container p {
  margin: 15px 0;
  font-size: 14px;
  color: var(--text-secondary);
}

.form-container a {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s ease;
}

.form-container a:hover {
  color: var(--secondary-color);
}

/* Error Messages */
.error {
  color: var(--error-color);
  background: rgba(255, 71, 87, 0.1);
  padding: 10px;
  border-radius: 8px;
  margin: 10px 0;
  font-size: 14px;
  border-left: 3px solid var(--error-color);
}

/* Success Messages */
.success {
  color: #155724;
  background: #d4edda;
  padding: 10px;
  border-radius: 8px;
  margin: 10px 0;
  font-size: 14px;
  border-left: 3px solid #28a745;
}

/* Animation */
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

/* Responsive Design */
@media (max-width: 480px) {
  .form-container {
    padding: 35px 20px;
    margin: 10px;
    max-width: 95%;
  }
  
  .form-container h2 {
    font-size: 26px;
  }
  
  .form-container img.logo {
    width: 70px;
    height: 70px;
  }
  
  .input-row {
    flex-direction: column;
    gap: 10px;
  }
  
  .input-row input {
    width: 100%;
  }
}
  </style>
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
        mediaQuery.addEventListener('change', () => {
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

// Initialize theme system and Google button handler
document.addEventListener('DOMContentLoaded', () => {
    try {
        window.themeSystem = new ThemeSystem();

        const btn = document.getElementById('googleSignupBtn');
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Google Sign Up button clicked, redirecting to google_login.php?source=signup');
                window.location.href = 'google_login.php?source=signup';
            });
        } else {
            console.error('Google Sign Up button not found');
        }
    } catch (error) {
        console.error('Error in signupprocess_users.php JavaScript:', error);
    }
});

// Export for use in other scripts
window.ThemeSystem = ThemeSystem;
  </script>
</head>
<body>
  <div class="form-container">
    <img src="Uploads/logo1.png" alt="MyShop Logo" class="logo">
    <h2>Create Meta Shark Account</h2>

    <form action="signupprocess_users.php" method="POST">
      <div class="input-row">
        <input type="text" name="fullname" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
      </div>
      <input type="text" name="phone" placeholder="Phone Number" maxlength="15" required>
      <div class="input-row">
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
      </div>
      <button type="submit" class="primary-btn">Sign Up</button>

      <!-- Google Sign Up Button -->
      <button type="button" class="google-btn" id="googleSignupBtn" aria-label="Sign up with Google">
        <span class="google-icon" aria-hidden="true">
          <!-- Google "G" SVG (multicolor) -->
          <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path fill="#4285F4" d="M17.64 9.2045c0-.638-.0578-1.2509-.166-1.835H9v3.475h4.844c-.209 1.12-.845 2.07-1.803 2.71v2.257h2.912c1.705-1.571 2.697-3.88 2.697-6.607z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.468-.803 5.956-2.182l-2.912-2.257c-.806.543-1.84.866-3.044.866-2.34 0-4.325-1.58-5.033-3.705H1.01v2.328C2.496 15.861 5.548 18 9 18z"/>
            <path fill="#FBBC05" d="M3.967 10.74a5.49 5.49 0 0 1 0-3.48V4.93H1.01A9 9 0 0 0 0 9c0 1.47.33 2.86.91 4.07l3.057-2.33z"/>
            <path fill="#EA4335" d="M9 3.58c1.322 0 2.51.454 3.445 1.347l2.582-2.5C13.463.996 11.425 0 9 0 5.548 0 2.496 2.139 1.01 4.93l2.957 2.33C4.675 5.16 6.66 3.58 9 3.58z"/>
          </svg>
        </span>
        <span class="google-text">Sign up with Google</span>
      </button>
      
      <?php if (!empty($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
      <?php elseif (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
    </form>

    <p>Already have an account? <a href="login_users.php">Login</a></p>
    <p>Review our <a href="../../privacy_policy.html">Privacy Policy</a> and <a href="../../terms.html">Terms of Service</a></p>
  </div>
</body>
</html>