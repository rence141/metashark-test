<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/email.php';

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
		$errors[] = 'Invalid form submission. Please try again.';
	} else {
		// normalize inputs
		$fn = trim($_POST['first_name'] ?? '');
		$ln = trim($_POST['last_name'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$pw = $_POST['password'] ?? '';

		// Normalize names and email for consistent storage/display
		$fn = ucwords(strtolower($fn));
		$ln = ucwords(strtolower($ln));
		$email = strtolower($email);

		// save normalized values for re-populating form
		$old['first_name'] = $fn;
		$old['last_name']  = $ln;
		$old['email']      = $email;

		// Validation
		if (!$fn || !$ln || !$email || !$pw) $errors[] = 'All fields are required.';
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

		if (empty($errors)) {
			// Ensure requests table exists
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

			// Prevent duplicate pending/approved admin requests
			$chk = $conn->prepare("SELECT id, status FROM admin_requests WHERE email = ? LIMIT 1");
			$chk->bind_param('s', $email);
			$chk->execute();
			$r = $chk->get_result()->fetch_assoc();
			if ($r) {
				$errors[] = $r['status'] === 'pending' ? 'A request for this email is already pending.' : 'This email was already processed.';
			} else {
				// Ensure not already an admin
				$chk2 = $conn->prepare("SELECT id FROM admin_accounts WHERE email = ? LIMIT 1");
				$chk2->bind_param('s', $email);
				$chk2->execute();
				if ($chk2->get_result()->fetch_assoc()) {
					$errors[] = 'An admin account with this email already exists.';
				} else {
					$hash = password_hash($pw, PASSWORD_DEFAULT);
					$token = bin2hex(random_bytes(32));

					$ins = $conn->prepare("INSERT INTO admin_requests (first_name,last_name,email,password_hash,token) VALUES (?,?,?,?,?)");
					$ins->bind_param('sssss', $fn, $ln, $email, $hash, $token);
					if ($ins->execute()) {
						// Build approval/rejection links
						$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
						$host = $_SERVER['HTTP_HOST'];
						$base = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
						$approveUrl = "{$scheme}://{$host}{$base}/admin_requests_handler.php?token={$token}&action=approve";
						$rejectUrl  = "{$scheme}://{$host}{$base}/admin_requests_handler.php?token={$token}&action=reject";

						// Email to super admin
						$super = 'renceprepotente@gmail.com';
						$subject = 'New Admin Signup Request';
						$body = "A new admin signup request was submitted.\n\nName: {$fn} {$ln}\nEmail: {$email}\n\nApprove: {$approveUrl}\nReject: {$rejectUrl}\n\n(These links will activate/reject the request.)";
						send_email($super, $subject, $body);

						$success = 'Your admin request has been submitted and is pending approval.';
					} else {
						$errors[] = 'Failed to submit request. Try again later.';
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
<html>
<head>
<meta charset="utf-8">
<title>Admin Signup — Request Approval</title>
<link rel="stylesheet" href="assets/css/admin.css">
<style>
/* Replace previous container styles with login-like theme (keeps dark/light variables) */
:root {
  --primary-color: #06dd78ff;
  --secondary-color: #00d4ff;
  --accent-color: #00ff88;
  --text-primary: #fff;
  --card-background: rgba(255,255,255,0.03);
  --border-color: rgba(255,255,255,0.06);
  --shadow-color: rgba(0,0,0,0.6);
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg,#07110a 0%,#071116 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  color: var(--text-primary);
}

/* Login-like card */
.form-container {
  background: var(--card-background);
  padding: 48px;
  border-radius: 16px;
  width: 100%;
  max-width: 640px;
  text-align: center;
  position: relative;
  z-index: 2;
  box-shadow: 0 20px 40px var(--shadow-color);
  border: 1px solid var(--border-color);
  backdrop-filter: blur(6px);
}

/* Logo */
.logo {
  width: 84px;
  height: 84px;
  margin: 0 auto 18px;
  border-radius: 50%;
  object-fit: cover;
  display:block;
  background: rgba(255,255,255,0.06);
}

/* Headings */
.form-container h2 {
  margin-bottom: 18px;
  color: var(--accent-color);
  font-size: 28px;
  font-weight: 700;
}

/* Inputs */
.form-container input[type="text"],
.form-container input[type="email"],
.form-container input[type="password"] {
  width: 100%;
  padding: 14px 18px;
  margin: 8px 0;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: rgba(255,255,255,0.02);
  color: var(--text-primary);
  font-size: 15px;
  outline: none;
  transition: all 0.2s ease;
}
.form-container input:focus {
  box-shadow: 0 8px 20px rgba(0,0,0,0.4);
  transform: translateY(-2px);
  border-color: var(--secondary-color);
}

/* two-column input row for name fields */
.input-row {
  display: flex;
  gap: 12px;
  margin: 8px 0;
}
.input-row input {
  flex: 1;
  /* Make sure name inputs match the main input appearance */
  padding: 14px 18px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: rgba(255,255,255,0.02);
  color: var(--text-primary);
  font-size: 15px;
  outline: none;
  transition: all 0.2s ease;
  /* ensure equal height with single-column inputs */
  height: 48px;
  box-sizing: border-box;
}
.input-row input::placeholder {
  color: rgba(255,255,255,0.5);
}
.input-row input:focus {
  box-shadow: 0 8px 20px rgba(0,0,0,0.4);
  transform: translateY(-2px);
  border-color: var(--secondary-color);
}

/* screen-reader-only helper for accessible labels */
.sr-only {
  position: absolute !important;
  width: 1px !important;
  height: 1px !important;
  padding: 0 !important;
  margin: -1px !important;
  overflow: hidden !important;
  clip: rect(0, 0, 0, 0) !important;
  white-space: nowrap !important;
  border: 0 !important;
}

/* ensure stacked on narrow screens */
@media (max-width: 600px) {
  .input-row { flex-direction: column; gap: 10px; }
  .input-row input { height: auto; }
}

/* Button (use same class as login) */
.login-btn {
  width: 100%;
  padding: 14px;
  background: #000;
  color: #fff;
  font-weight: 700;
  border-radius: 12px;
  border: 2px solid var(--primary-color);
  cursor: pointer;
  margin-top: 12px;
  transition: all 0.2s ease;
}
.login-btn:hover {
  background: var(--primary-color);
  color: #000;
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(4,150,60,0.12);
}

/* Messages */
.error{ background: rgba(255,107,107,0.08); border-left:4px solid #ff6b6b; color:#ff6b6b; padding:10px; border-radius:8px; margin:12px 0; }
.success{ background: rgba(68,214,44,0.06); border-left:4px solid var(--accent-color); color:var(--accent-color); padding:10px; border-radius:8px; margin:12px 0; }

/* Responsive */
@media (max-width:480px){
  .form-container { padding: 26px; max-width: 92%; }
  .logo { width:72px; height:72px; }
}
</style>
</head>
<body>
<div class="form-container">
    <img src="Uploads/logo1.png" alt="Meta Shark" class="logo">
	<h2>Request Admin Access</h2>
	<?php if ($errors): ?><div class="error"><?php echo implode('<br>', $errors); ?></div><?php endif; ?>
	<?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

	<form method="post">
        <div class="input-row" aria-label="Name">
            <label class="sr-only" for="first_name">First name</label>
             <input id="first_name" name="first_name" placeholder="First name" required value="<?php echo htmlspecialchars($old['first_name']); ?>" aria-label="First name" autocomplete="given-name">
            <label class="sr-only" for="last_name">Last name</label>
             <input id="last_name" name="last_name" placeholder="Last name" required value="<?php echo htmlspecialchars($old['last_name']); ?>" aria-label="Last name" autocomplete="family-name">
        </div>
		<label class="sr-only" for="email">Email</label>
		<input id="email" name="email" type="email" placeholder="Email" required value="<?php echo htmlspecialchars($old['email']); ?>" autocomplete="email">
		<label class="sr-only" for="password">Password</label>
		<input id="password" name="password" type="password" placeholder="Password" required minlength="8" autocomplete="new-password" aria-describedby="pwHelp">
		<div id="pwHelp" style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:-6px;margin-bottom:8px">Use at least 8 characters.</div>
		<!-- CSRF token -->
		<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
		<button type="submit" class="login-btn">Request Admin Access</button>
	</form>
    <p style="margin-top:14px;color:rgba(255,255,255,0.6)"><a href="admin_login.php" style="color:var(--accent-color);text-decoration:none;font-weight:600">Back to admin login</a></p>
</div>
</body>
</html>
