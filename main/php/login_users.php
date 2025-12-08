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
    <title>Login — Meta Shark</title>
    <link rel="icon" type="image/png" href="uploads/logo1.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        :root {
            /* Palette: Consumer Friendly Dark Mode */
            --bg-body: #050505;
            --surface: #121212;
            --primary: #44D62C;
            --primary-dark: #2da81b;
            --text-main: #ffffff;
            --text-muted: #888888;
            --border: #2a2a2a;
            --input-bg: #1a1a1a;
            --error: #ff4757;
            --font-main: 'Plus Jakarta Sans', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* --- LEFT SIDE: FORM (Active Area) --- */
        .login-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 80px;
            max-width: 600px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: relative;
            z-index: 2;
        }

        .header { margin-bottom: 40px; }
        .logo-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .logo-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); }
        .logo-text { font-weight: 700; font-size: 20px; letter-spacing: -0.5px; }
        
        .header h1 { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .header p { color: var(--text-muted); font-size: 15px; }

        /* Floating Input System */
        .input-group { position: relative; margin-bottom: 20px; }
        
        .form-control {
            width: 100%;
            padding: 16px 16px 16px 50px; /* Space for icon */
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: #222;
            box-shadow: 0 0 0 4px rgba(68, 214, 44, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
            transition: color 0.2s;
        }
        
        .form-control:focus ~ .input-icon { color: var(--primary); }

        .form-label {
            position: absolute;
            left: 50px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            transition: 0.2s ease;
            font-size: 15px;
            background: transparent;
        }

        /* The Float Animation */
        .form-control:focus ~ .form-label,
        .form-control:not(:placeholder-shown) ~ .form-label {
            top: 0;
            left: 15px;
            font-size: 12px;
            padding: 0 4px;
            background: var(--surface);
            color: var(--primary);
            font-weight: 600;
        }

        /* Password Toggle */
        .toggle-pw {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            padding: 5px;
        }
        .toggle-pw:hover { color: #fff; }

        /* Buttons */
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: #000;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.1s, filter 0.2s;
            margin-top: 10px;
        }
        .btn-primary:hover { filter: brightness(1.1); }
        .btn-primary:active { transform: scale(0.98); }

        .google-btn {
            width: 100%;
            padding: 14px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: 0.2s;
            margin-top: 20px;
            text-decoration: none;
            font-size: 15px;
        }
        .google-btn:hover { background: rgba(255,255,255,0.05); border-color: #444; }

        /* Footer Links */
        .form-footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
        .form-footer a { color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .form-footer a:hover { color: var(--primary); }

        /* --- RIGHT SIDE: ART (Visual Experience) --- */
        .art-section {
            flex: 1.5;
            background: radial-gradient(circle at center, #111e13 0%, #000000 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Glowing Orbs Animation */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: floatOrb 10s infinite ease-in-out alternate;
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: var(--primary);
            top: 20%; left: 20%;
        }
        .orb-2 {
            width: 300px; height: 300px;
            background: #00d4ff;
            bottom: 20%; right: 20%;
            animation-delay: -5s;
        }

        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, -30px) scale(1.1); }
        }

        /* Glass Card in Art Section */
        .art-content {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            max-width: 400px;
        }
        .art-content h2 { font-size: 28px; margin-bottom: 12px; }
        .art-content p { color: #aaa; line-height: 1.6; }

        /* Error Message */
        .alert-error {
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid rgba(255, 71, 87, 0.3);
            color: var(--error);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .art-section { display: none; }
            .login-section { max-width: 100%; border-right: none; padding: 40px 20px; }
        }
    </style>
</head>
<body>

    <div class="login-section">
        <div class="logo-wrap">
            <img src="uploads/logo1.png" alt="Logo" class="logo-img">
            <span class="logo-text">Meta Shark</span>
        </div>

        <div class="header">
            <h1>Welcome back</h1>
            <p>Enter your details to access your personal dashboard.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="loginprocess_users.php" method="POST" id="loginForm">
            
            <div class="input-group">
                <input type="email" name="email" id="email" class="form-control" placeholder=" " required>
                <i class="bi bi-envelope input-icon"></i>
                <label for="email" class="form-label">Email Address</label>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" placeholder=" " required>
                <i class="bi bi-lock input-icon"></i>
                <label for="password" class="form-label">Password</label>
                <i class="bi bi-eye-slash toggle-pw" onclick="togglePassword()"></i>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">Sign In</button>
        </form>

        <a href="google_login.php" class="google-btn">
            <i class="bi bi-google"></i> Sign in with Google
        </a>

        <div class="form-footer">
            <a href="forgot_password.php">Forgot Password?</a>
            <span style="color:var(--border);">|</span>
            <a href="signup_users.php" style="color:#fff; font-weight:600;">Create Account</a>
        </div>
    </div>

    <div class="art-section">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        
        <div class="art-content">
            <h2>Shop Smarter</h2>
            <p>Experience the future of e-commerce. Track orders in real-time and get exclusive deals delivered to your dashboard.</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.querySelector('.toggle-pw');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        // Add loading state on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="spinner-border" role="status"></span> Signing In...';
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';
        });
    </script>
</body>
</html>