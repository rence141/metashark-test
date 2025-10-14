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
<title>Admin Login</title>
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-auth">
<div class="auth-card">
    <h2>Admin Login</h2>
    <?php if ($err): ?><div class="errors"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if (isset($_GET['signup'])): ?><div class="success">Account created. Please login.</div><?php endif; ?>
    <form method="post">
        <input name="email" type="email" placeholder="Email" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p><a href="admin_signup.php">Create admin account</a></p>
</div>
</body>
</html>
