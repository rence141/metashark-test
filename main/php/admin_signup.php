<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    if (!$fn || !$ln || !$email || !$pw) $errors[] = 'All fields required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM admin_accounts WHERE email = ? LIMIT 1");
        $check->bind_param('s', $email);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows > 0) { $errors[] = 'Email already registered.'; }
        else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO admin_accounts (first_name,last_name,email,password,created_at) VALUES (?,?,?,?,NOW())");
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
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Signup</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth">
<div class="auth-card">
    <h2>Admin Signup</h2>
    <?php if($errors): ?><div class="errors"><?php echo implode('<br>', $errors); ?></div><?php endif; ?>
    <form method="post">
        <input name="first_name" placeholder="First name" required>
        <input name="last_name" placeholder="Last name" required>
        <input name="email" type="email" placeholder="Email" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Create Account</button>
    </form>
    <p><a href="admin_login.php">Already have an account? Login</a></p>
</div>
</body>
</html>
