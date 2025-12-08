<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

$err = '';

// If already logged in, redirect
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';

    if ($email && $pw) {
        // Prepare statement
        $stmt = $conn->prepare("SELECT id, first_name, password FROM admin_accounts WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $r = $result->fetch_assoc();

            if ($r && password_verify($pw, $r['password'])) {
                // Login Success
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$r['id'];
                $_SESSION['admin_name'] = htmlspecialchars($r['first_name']);
                
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $err = 'Invalid email or password.';
                // Security: Add a small delay to slow down brute-force attacks
                sleep(1);
            }
            $stmt->close();
        } else {
            $err = 'Database error. Please try again.';
        }
    } else {
        $err = 'Please enter both email and password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-dark: #0f1115;
            --panel-dark: #161b22;
            --border-dark: #242c38;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --primary: #44D62C;
            --primary-hover: #3bc224;
            --danger: #ef4444;
            --font-main: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: var(--font-main);
            background-color: var(--bg-dark);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* --- LEFT SIDE: BRANDING --- */
        .split-left {
            flex: 1;
            background: linear-gradient(135deg, #161b22 0%, #0a0c0f 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        /* Abstract Shape Decoration */
        .split-left::before {
            content: '';
            position: absolute;
            top: -10%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(68, 214, 44, 0.15) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .brand-content { position: relative; z-index: 2; max-width: 450px; }
        .brand-logo { height: 48px; margin-bottom: 30px; }
        .brand-title { font-size: 32px; font-weight: 700; margin-bottom: 15px; letter-spacing: -0.5px; }
        .brand-desc { color: var(--text-muted); font-size: 16px; line-height: 1.6; margin-bottom: 40px; }
        
        .feature-list { list-style: none; }
        .feature-list li { display: flex; align-items: center; margin-bottom: 15px; color: #e2e8f0; font-weight: 500; }
        .feature-list li i { color: var(--primary); margin-right: 12px; font-size: 18px; }

        /* --- RIGHT SIDE: FORM --- */
        .split-right {
            flex: 1;
            max-width: 600px;
            background: var(--bg-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            border-left: 1px solid var(--border-dark);
        }

        .login-wrapper { width: 100%; max-width: 400px; }

        .form-header { margin-bottom: 30px; }
        .form-header h2 { font-size: 24px; font-weight: 600; margin-bottom: 8px; }
        .form-header p { color: var(--text-muted); font-size: 14px; }

        /* Inputs */
        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label { display: block; font-size: 13px; font-weight: 500; color: var(--text-muted); margin-bottom: 8px; }
        .input-field {
            width: 100%;
            padding: 12px 16px;
            background: var(--panel-dark);
            border: 1px solid var(--border-dark);
            border-radius: 8px;
            color: white;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(68, 214, 44, 0.1);
        }
        .input-field::placeholder { color: #475569; }

        /* Password Toggle */
        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 16px;
        }
        .toggle-password:hover { color: white; }

        /* Button */
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s, filter 0.2s;
        }
        .btn-primary:hover { filter: brightness(1.1); }
        .btn-primary:active { transform: scale(0.98); }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); }
        .alert-success { background: rgba(68, 214, 44, 0.1); border: 1px solid var(--primary); color: var(--primary); }

        /* Footer Links */
        .auth-footer { margin-top: 25px; text-align: center; font-size: 14px; color: var(--text-muted); }
        .auth-footer a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }

        /* Mobile Responsive */
        @media (max-width: 900px) {
            body { flex-direction: column; overflow-y: auto; }
            .split-left { display: none; } /* Hide branding on mobile for cleaner look */
            .split-right { max-width: 100%; padding: 40px 20px; flex: 1; border-left: none; }
        }
    </style>
</head>
<body>

    <div class="split-left">
        <div class="brand-content">
            <img src="uploads/logo1.png" alt="Meta Shark Logo" class="brand-logo">
            <h1 class="brand-title">Meta Shark Console</h1>
            <p class="brand-desc">
                Secure access for system administrators. Monitor sales, manage inventory, and analyze performance from a unified command center.
            </p>
            <ul class="feature-list">
                <li><i class="bi bi-shield-check"></i> Secure Environment</li>
                <li><i class="bi bi-graph-up-arrow"></i> Real-time Analytics</li>
                <li><i class="bi bi-people"></i> Seller Management</li>
            </ul>
        </div>
    </div>

    <div class="split-right">
        <div class="login-wrapper">
            <div class="form-header">
                <h2>Welcome back</h2>
                <p>Please enter your credentials to access the dashboard.</p>
            </div>

            <?php if ($err): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i> 
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['signup'])): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> 
                    Account created successfully. Please login.
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="input-field" placeholder="admin@metashark.com" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="input-field" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="bi bi-eye" id="eye-icon"></i>
                        </button>
                    </div>
                    <div style="text-align: right; margin-top: 8px;">
                        <a href="#" style="font-size: 12px; color: var(--text-muted); text-decoration: none;">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Sign In to Console</button>
            </form>

            <div class="auth-footer">
                <span>New staff member?</span>
                <a href="admin_signup.php">Request Access</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>