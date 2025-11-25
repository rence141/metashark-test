<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    $cpw = $_POST['confirm_password'] ?? '';
    if (!$fn || !$ln || !$email || !$pw || !$cpw) $errors[] = 'All fields required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if ($pw !== $cpw) $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM admin_accounts WHERE email = ? LIMIT 1");
        if (!$check) {
            $errors[] = 'Could not prepare validation query. Please check database schema.';
        } else {
            $check->bind_param('s', $email);
            $check->execute();
            $res = $check->get_result();
            if ($res && $res->num_rows > 0) {
                $errors[] = 'Email already registered.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO admin_accounts (first_name,last_name,email,password,created_at) VALUES (?,?,?,?,NOW())");
                if (!$ins) {
                    $errors[] = 'Could not prepare insert query. Please check database schema.';
                } else {
                    $ins->bind_param('ssss', $fn, $ln, $email, $hash);
                    if ($ins->execute()) {
                        header('Location: admin_login.php?signup=1');
                        exit;
                    } else {
                        $errors[] = 'Failed to create account.';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Signup</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth">
<div class="auth-shell">
  <section class="auth-panel auth-intro">
    <div class="brand-mark">
      <img src="Uploads/logo1.png" alt="Meta Shark" loading="lazy">
      <div>
        <p class="eyebrow">Meta Shark Console</p>
        <h1>Invite Administrators</h1>
      </div>
    </div>
    <p>Grant secure access to the operations console with multi-role support and fine-grained controls.</p>
    <ul>
      <li>Track KPIs with live dashboards</li>
      <li>Manage sellers and inventory</li>
      <li>Resolve escalations faster</li>
    </ul>
  </section>

  <section class="auth-panel auth-card">
    <div class="auth-header">
      <h2>Create admin credentials</h2>
      <p>All fields are required for compliance.</p>
    </div>

    <?php if($errors): ?>
      <div class="alert errors">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="auth-form">
      <div class="input-row">
        <label class="input-group">
          <span>First name</span>
          <input name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" placeholder="Jane" required>
        </label>
        <label class="input-group">
          <span>Last name</span>
          <input name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" placeholder="Doe" required>
        </label>
      </div>
      <label class="input-group">
        <span>Work email</span>
        <input name="email" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="admin@company.com" required>
      </label>
      <label class="input-group">
        <span>Password</span>
        <input name="password" type="password" placeholder="Minimum 8 characters" required>
      </label>
      <label class="input-group">
        <span>Confirm password</span>
        <input name="confirm_password" type="password" placeholder="Re-enter password" required>
      </label>
      <button type="submit" class="primary-btn">Create admin</button>
    </form>

    <div class="auth-meta">
      <span>Already have access?</span>
      <a href="admin_login.php">Return to login</a>
    </div>
  </section>
</div>
</body>
</html>
