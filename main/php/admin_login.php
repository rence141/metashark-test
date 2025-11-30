<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    if ($email && $pw) {
        $stmt = $conn->prepare("SELECT id, first_name, password FROM admin_accounts WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r && password_verify($pw, $r['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$r['id'];
            $_SESSION['admin_name'] = $r['first_name'];
            header('Location: admin_dashboard.php');
            exit;
        } else $err = 'Invalid credentials.';
    } else $err = 'Provide email and password.';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<link rel="icon" href="uploads/logo1.png">
<title>Admin Login</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth">
<div class="auth-shell">
  <section class="auth-panel auth-intro">
    <div class="brand-mark">
      <img src="Uploads/logo1.png" alt="Meta Shark" loading="lazy">
      <div>
        <p class="eyebrow">Meta Shark Console</p>
        <h1>Administrator Access</h1>
      </div>
    </div>
    <p>Monitor orders, manage sellers, and keep the marketplace healthy with real-time insights.</p>
    <ul>
      <li>Unified dashboard for KPIs</li>
      <li>Granular order controls</li>
      <li>Role-based management</li>
    </ul>
  </section>

  <section class="auth-panel auth-card">
    <div class="auth-header">
      <h2>Welcome back</h2>
      <p>Sign in with your administrator credentials.</p>
    </div>

    <?php if ($err): ?><div class="alert errors"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if (isset($_GET['signup'])): ?><div class="alert success">Account created. Please login.</div><?php endif; ?>

    <form method="post" class="auth-form">
      <label class="input-group">
        <span>Email Address</span>
        <input name="email" type="email" placeholder="admin@company.com" required>
      </label>
      <label class="input-group">
        <span>Password</span>
        <input name="password" type="password" placeholder="••••••••" required>
      </label>
      <button type="submit" class="primary-btn">Sign in</button>
    </form>

    <div class="auth-meta">
      <span>Need access?</span>
      <a href="admin_signup.php">Request an admin account</a>
    </div>
  </section>
</div>
</body>
</html>
