<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/email.php'; // Ensure this file exists for email sending

$errors = [];
$success = '';

// Preserve previous submission values
$old = ['first_name'=>'', 'last_name'=>'', 'email'=>''];

// Ensure a CSRF token exists for the session
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = 'Security token mismatch. Please refresh and try again.';
  } else {
    // normalize inputs
    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    $cpw = $_POST['confirm_password'] ?? '';

    // Normalize formatting
    $fn = ucwords(strtolower($fn));
    $ln = ucwords(strtolower($ln));
    $email = strtolower($email);

    // save normalized values for re-populating form
    $old['first_name'] = $fn;
    $old['last_name']  = $ln;
    $old['email']      = $email;

    // Validation
    if (!$fn || !$ln || !$email || !$pw) $errors[] = 'All fields are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $cpw) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
      // Create table if not exists (Keep user's logic)
      $create = "CREATE TABLE IF NOT EXISTS admin_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        token VARCHAR(128) NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(email)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
      $conn->query($create);

      // Check duplicates
      $chk = $conn->prepare("SELECT id, status FROM admin_requests WHERE email = ? LIMIT 1");
      $chk->bind_param('s', $email);
      $chk->execute();
      $r = $chk->get_result()->fetch_assoc();
      
      if ($r) {
        $errors[] = $r['status'] === 'pending' ? 'Request already pending approval.' : 'This email has already been processed.';
      } else {
        // Ensure not already an admin
        $chk2 = $conn->prepare("SELECT id FROM admin_accounts WHERE email = ? LIMIT 1");
        $chk2->bind_param('s', $email);
        $chk2->execute();
        if ($chk2->get_result()->fetch_assoc()) {
          $errors[] = 'An admin account with this email already exists.';
        } else {
          // Insert Request
          $hash = password_hash($pw, PASSWORD_DEFAULT);
          $token = bin2hex(random_bytes(32));

          $ins = $conn->prepare("INSERT INTO admin_requests (first_name,last_name,email,password_hash,token) VALUES (?,?,?,?,?)");
          $ins->bind_param('sssss', $fn, $ln, $email, $hash, $token);
          
          if ($ins->execute()) {
            // Build approval links
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $base = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $approveUrl = "{$scheme}://{$host}{$base}/admin_requests_handler.php?token={$token}&action=approve";
            $rejectUrl  = "{$scheme}://{$host}{$base}/admin_requests_handler.php?token={$token}&action=reject";

            // Email to super admin
            $super = 'renceprepotente@gmail.com';
            $subject = 'New Admin Signup Request';
            $body = "A new admin signup request was submitted.\n\nName: {$fn} {$ln}\nEmail: {$email}\n\nApprove: {$approveUrl}\nReject: {$rejectUrl}";
            
            // Send Email Function
            if(function_exists('send_email')) {
                send_email($super, $subject, $body);
            }

            $success = 'Request submitted! Please wait for Super Admin approval.';
            // Reset form
            $old = ['first_name'=>'', 'last_name'=>'', 'email'=>''];
          } else {
            $errors[] = 'Database error. Please try again.';
          }
        }
        $chk2->close();
      }
      $chk->close();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Admin Access — Meta Shark</title>
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
            --primary: #44D62C; /* Meta Shark Green */
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

        /* --- LEFT SIDE: FORM (Swapped position from Login) --- */
        .split-left {
            flex: 1;
            max-width: 650px;
            background: var(--bg-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            border-right: 1px solid var(--border-dark);
            overflow-y: auto; /* Allow scrolling if form is tall */
        }

        /* --- RIGHT SIDE: BRANDING (Swapped position from Login) --- */
        .split-right {
            flex: 1;
            background: linear-gradient(135deg, #0a0c0f 0%, #161b22 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        /* Different Abstract Art for Signup (Grid Pattern) */
        .split-right::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .brand-content { position: relative; z-index: 2; max-width: 500px; margin-left: auto; margin-right: auto; text-align: center; }
        .brand-logo { height: 64px; margin-bottom: 30px; filter: drop-shadow(0 0 15px rgba(68,214,44,0.4)); }
        .brand-title { font-size: 32px; font-weight: 700; margin-bottom: 15px; letter-spacing: -0.5px; }
        .brand-desc { color: var(--text-muted); font-size: 16px; line-height: 1.6; }

        /* --- FORM STYLES --- */
        .signup-wrapper { width: 100%; max-width: 440px; }

        .form-header { margin-bottom: 30px; }
        .form-header h2 { font-size: 26px; font-weight: 700; margin-bottom: 8px; color: var(--text-main); }
        .form-header p { color: var(--text-muted); font-size: 14px; }

        .input-row { display: flex; gap: 15px; }
        .input-group { margin-bottom: 20px; width: 100%; }
        
        .input-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        
        .input-field {
            width: 100%;
            padding: 12px 16px;
            background: var(--panel-dark);
            border: 1px solid var(--border-dark);
            border-radius: 8px;
            color: white;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(68, 214, 44, 0.1);
        }
        .input-field::placeholder { color: #475569; }

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
            transition: filter 0.2s;
            margin-top: 10px;
        }
        .btn-primary:hover { filter: brightness(1.1); }

        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.4;
        }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); }
        .alert-success { background: rgba(68, 214, 44, 0.1); border: 1px solid var(--primary); color: var(--primary); }

        .auth-footer { margin-top: 25px; text-align: center; font-size: 14px; color: var(--text-muted); border-top: 1px solid var(--border-dark); padding-top: 20px; }
        .auth-footer a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 900px) {
            body { flex-direction: column; overflow-y: auto; }
            .split-right { display: none; } /* Hide branding on mobile */
            .split-left { max-width: 100%; padding: 40px 20px; flex: 1; border-right: none; }
            .input-row { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

    <div class="split-left">
        <div class="signup-wrapper">
            <div class="form-header">
                <h2>Request Admin Account</h2>
                <p>Request administrator privileges for the Meta Shark console.</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle-fill" style="margin-top:2px;"></i> 
                    <div><?php echo implode('<br>', $errors); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill" style="margin-top:2px;"></i> 
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="input-row">
                    <div class="input-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="input-field" placeholder="First Name" value="<?php echo htmlspecialchars($old['first_name']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="input-field" placeholder="Surname" value="<?php echo htmlspecialchars($old['last_name']); ?>" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="email">Work Email</label>
                    <input type="email" id="email" name="email" class="input-field" placeholder="admin@metashark.com" value="<?php echo htmlspecialchars($old['email']); ?>" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input-field" placeholder="Min. 8 characters" required>
                </div>
                
                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder="Repeat password" required>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <button type="submit" class="btn-primary">Submit Request</button>
            </form>

            <div class="auth-footer">
                <span>Already have an account?</span>
                <a href="admin_login.php">Sign in here</a>
            </div>
        </div>
    </div>

    <div class="split-right">
        <div class="brand-content">
            <img src="uploads/logo1.png" alt="Meta Shark Logo" class="brand-logo">
            <h1 class="brand-title">Scale Operations</h1>
            <p class="brand-desc">
                Join the administration team to oversee orders, manage sellers, and maintain marketplace integrity.
            </p>
        </div>
    </div>

</body>
</html>