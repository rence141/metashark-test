<?php
// Enable error reporting for debugging (comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $country = $_POST['country'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        // Connect to DB
        $conn = new mysqli("localhost", "root", "", "myshop");
        if ($conn->connect_error) {
            $error = "Connection failed: " . $conn->connect_error;
        } else {
            // Check if email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Email already registered!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (fullname, email, phone, country, password) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssss", $fullname, $email, $phone, $country, $hashed);
                    
                    if ($stmt->execute()) {
                        $success = "Signup successful! You can now login.";
                    } else {
                        $error = "Error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            $check->close();
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
  <title>Sign Up — Meta Shark</title>
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  
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

    /* --- LEFT SIDE: FORM --- */
    .login-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 40px 80px;
        max-width: 650px;
        background: var(--surface);
        border-right: 1px solid var(--border);
        position: relative;
        z-index: 2;
        overflow-y: auto; /* Allow scrolling for longer form */
    }

    .header { margin-bottom: 30px; }
    .logo-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .logo-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); }
    .logo-text { font-weight: 700; font-size: 20px; letter-spacing: -0.5px; }
    
    .header h1 { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
    .header p { color: var(--text-muted); font-size: 15px; }

    /* Form Grid */
    .row { display: flex; gap: 16px; margin-bottom: 20px; }
    .col { flex: 1; position: relative; }

    /* Floating Input System */
    .input-group { position: relative; margin-bottom: 20px; }
    
    .form-control {
        width: 100%;
        padding: 16px 16px 16px 16px; 
        background: var(--input-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: #fff;
        font-size: 15px;
        outline: none;
        transition: all 0.2s ease;
        height: 54px; /* Fixed height for consistency */
    }

    .form-control:focus {
        border-color: var(--primary);
        background: #222;
        box-shadow: 0 0 0 4px rgba(68, 214, 44, 0.1);
    }

    /* Label Styling */
    .form-label {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
        transition: 0.2s ease;
        font-size: 15px;
        background: transparent;
    }

    /* Float Animation */
    .form-control:focus ~ .form-label,
    .form-control:not(:placeholder-shown) ~ .form-label {
        top: 0;
        left: 12px;
        font-size: 12px;
        padding: 0 4px;
        background: var(--surface);
        color: var(--primary);
        font-weight: 600;
        z-index: 2;
    }

    /* Select specific fix */
    select.form-control { appearance: none; cursor: pointer; }
    .select-arrow {
        position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
        pointer-events: none; color: var(--text-muted);
    }
    /* Select label always floats if valid selection made, handled by JS or simple valid CSS */
    select.form-control:valid ~ .form-label {
        top: 0; left: 12px; font-size: 12px; padding: 0 4px; background: var(--surface); color: var(--primary);
    }

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
    }
    .google-btn:hover { background: rgba(255,255,255,0.05); border-color: #444; }

    /* Footer Links */
    .form-footer {
        margin-top: 30px;
        text-align: center;
        font-size: 14px;
        color: var(--text-muted);
    }
    .form-footer a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .form-footer a:hover { text-decoration: underline; }

    /* Alerts */
    .alert { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
    .alert-error { background: rgba(255, 71, 87, 0.1); border: 1px solid rgba(255, 71, 87, 0.3); color: var(--error); }
    .alert-success { background: rgba(68, 214, 44, 0.1); border: 1px solid var(--primary); color: var(--primary); }

    /* --- RIGHT SIDE: ART --- */
    .art-section {
        flex: 1.2;
        background: radial-gradient(circle at center, #0e1610 0%, #000000 100%);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .orb { position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.5; animation: floatOrb 12s infinite ease-in-out alternate; }
    .orb-1 { width: 500px; height: 500px; background: var(--primary); top: 10%; right: 10%; }
    .orb-2 { width: 400px; height: 400px; background: #9900ff; bottom: 10%; left: 10%; animation-delay: -6s; }

    @keyframes floatOrb {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(-40px, 40px) scale(1.1); }
    }

    .art-content {
        position: relative; z-index: 10;
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px);
        padding: 50px;
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,0.1);
        text-align: center;
        max-width: 450px;
    }
    .art-content h2 { font-size: 32px; margin-bottom: 12px; }
    .art-content p { color: #aaa; line-height: 1.6; }

    /* Responsive */
    @media (max-width: 992px) {
        .art-section { display: none; }
        .login-section { max-width: 100%; border-right: none; padding: 40px 20px; }
        .row { flex-direction: column; gap: 0; margin-bottom: 0; }
        .input-group { margin-bottom: 20px; }
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
      <h1>Create Account</h1>
      <p>Join the community and start your journey.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="signupprocess_users.php" method="POST" id="signupForm">
      
      <div class="row">
        <div class="col input-group">
            <input type="text" name="fullname" id="fullname" class="form-control" placeholder=" " required>
            <label for="fullname" class="form-label">Full Name</label>
        </div>
        <div class="col input-group">
            <input type="email" name="email" id="email" class="form-control" placeholder=" " required>
            <label for="email" class="form-label">Email Address</label>
        </div>
      </div>

      <div class="row">
        <div class="col input-group">
            <input type="text" name="phone" id="phone" class="form-control" placeholder=" " maxlength="15" required>
            <label for="phone" class="form-label">Phone Number</label>
        </div>
        <div class="col input-group">
            <select name="country" id="country" class="form-control" required>
                <option value="" disabled selected></option> <option value="Philippines">Philippines</option>
                <option value="United States">United States</option>
                <option value="United Kingdom">United Kingdom</option>
                <option value="Canada">Canada</option>
                <option value="Australia">Australia</option>
                <option value="Japan">Japan</option>
                <option value="South Korea">South Korea</option>
                <option value="China">China</option>
                <option value="Singapore">Singapore</option>
                <option value="Germany">Germany</option>
                <option value="France">France</option>
                <option value="Italy">Italy</option>
                <option value="Spain">Spain</option>
                <option value="Russia">Russia</option>
                <option value="Brazil">Brazil</option>
                <option value="India">India</option>
                <option value="Mexico">Mexico</option>
                <option value="Indonesia">Indonesia</option>
                <option value="Malaysia">Malaysia</option>
                <option value="Thailand">Thailand</option>
                <option value="Vietnam">Vietnam</option>
            </select>
            <label for="country" class="form-label">Country</label>
            <i class="bi bi-chevron-down select-arrow"></i>
        </div>
      </div>

      <div class="row">
        <div class="col input-group">
            <input type="password" name="password" id="password" class="form-control" placeholder=" " required>
            <label for="password" class="form-label">Password</label>
        </div>
        <div class="col input-group">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder=" " required>
            <label for="confirm_password" class="form-label">Confirm</label>
        </div>
      </div>

      <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <button type="button" class="google-btn" id="googleSignupBtn">
      <i class="bi bi-google"></i> Sign up with Google
    </button>

    <div class="form-footer">
      <p>Already have an account? <a href="login_users.php">Log In</a></p>
      <p style="margin-top:10px; font-size:13px; opacity:0.7;">
        By signing up, you agree to our <a href="../../terms.html">Terms</a> & <a href="../../privacy_policy.html">Privacy Policy</a>
      </p>
    </div>
  </div>

  <div class="art-section">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <div class="art-content">
      <h2>Join the Revolution</h2>
      <p>Unlock exclusive deals, track your orders in real-time, and experience the future of e-commerce today.</p>
    </div>
  </div>

  <script>
    // Google Redirect (Preserved from your code)
    document.getElementById('googleSignupBtn').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = 'google_login.php?source=signup';
    });

    // Loading State
    document.getElementById('signupForm').addEventListener('submit', function() {
        const btn = this.querySelector('.btn-primary');
        btn.innerHTML = '<span class="spinner-border" role="status"></span> Creating Account...';
        btn.style.opacity = '0.7';
        btn.style.pointerEvents = 'none';
    });
  </script>
</body>
</html>