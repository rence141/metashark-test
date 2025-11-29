<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/email.php';

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

if (!$token || !in_array($action, ['approve','reject'])) {
	http_response_code(400);
	$msg = "Invalid request.";
	$ok = false;
	// render below
} else {
	// Fetch request
	$stmt = $conn->prepare("SELECT * FROM admin_requests WHERE token = ? LIMIT 1");
	$stmt->bind_param('s', $token);
	$stmt->execute();
	$req = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if (!$req) {
		$msg = "Request not found or token invalid.";
		$ok = false;
	} elseif ($req['status'] !== 'pending') {
		$msg = "This request has already been processed (status: " . htmlspecialchars($req['status']) . ").";
		$ok = false;
	} else {
		$requesterEmail = $req['email'];
		$first = $req['first_name'];
		$last = $req['last_name'];

		if ($action === 'approve') {
			// Prevent duplicate admin
			$chk = $conn->prepare("SELECT id FROM admin_accounts WHERE email = ? LIMIT 1");
			$chk->bind_param('s', $requesterEmail);
			$chk->execute();
			$exists = (bool)$chk->get_result()->fetch_assoc();
			$chk->close();

			if ($exists) {
				$conn->query("UPDATE admin_requests SET status='approved' WHERE id=" . (int)$req['id']);
				send_email($requesterEmail, 'Admin Request Approved', "Your admin request has been approved. An account already exists. Please login at admin_login.php");
				$msg = "Approved — account already exists. The requester has been notified.";
				$ok = true;
			} else {
				// Create admin_accounts row
				$ins = $conn->prepare("INSERT INTO admin_accounts (first_name,last_name,email,password,created_at) VALUES (?,?,?,?,NOW())");
				$ins->bind_param('ssss', $first, $last, $requesterEmail, $req['password_hash']);
				if ($ins->execute()) {
					$conn->query("UPDATE admin_requests SET status='approved' WHERE id=" . (int)$req['id']);
					send_email($requesterEmail, 'Admin Request Approved', "Hello {$first},\n\nYour admin request has been approved. You can now login at admin_login.php using your credentials.");
					$msg = "Request approved and admin account created. Requester notified.";
					$ok = true;
				} else {
					$msg = "Failed to create admin account: " . htmlspecialchars($conn->error);
					$ok = false;
				}
				$ins->close();
			}
		} else { // reject
			$conn->query("UPDATE admin_requests SET status='rejected' WHERE id=" . (int)$req['id']);
			send_email($requesterEmail, 'Admin Request Rejected', "Hello {$first},\n\nYour admin request has been rejected. If you believe this is a mistake, contact support.");
			$msg = "Request rejected. Requester notified.";
			$ok = true;
		}
	}
}

// Render a small styled page showing the result
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<title>Admin Request Result</title>
<style>
:root{ --bg:#0b1220; --card:#0f1720; --accent:#44D62C; --muted:#9aa6b2; --text:#e6eef6; }
html,body{height:100%;margin:0;font-family:Inter,system-ui,Arial;background:linear-gradient(180deg,#05060a 0%,#0b1220 100%);color:var(--text);display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:720px;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.04);padding:28px;border-radius:12px;box-shadow:0 10px 30px rgba(2,6,23,0.6)}
.header{display:flex;align-items:center;gap:16px}
.logo{width:52px;height:52px;border-radius:8px;background:url('uploads/logo1.png') center/cover no-repeat;border:1px solid rgba(255,255,255,0.04)}
.title{font-size:18px;font-weight:700}
.status{margin-top:18px;padding:18px;border-radius:10px;background:rgba(0,0,0,0.25);display:flex;align-items:center;gap:12px}
.status .icon{width:44px;height:44;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px}
.status.ok .icon{background:linear-gradient(180deg,#eaffea,#d8ffd8);color:#0b2b00}
.status.ok .msg{color:var(--accent);font-weight:700}
.status.fail .icon{background:linear-gradient(180deg,#ffdede,#ffd2d2);color:#5a0000}
.status.fail .msg{color:#ff8b8b;font-weight:700}
.details{margin-top:14px;color:var(--muted);font-size:14px;line-height:1.4}
.actions{margin-top:20px;display:flex;gap:12px;flex-wrap:wrap}
.btn{padding:10px 14px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
.btn.primary{background:var(--accent);color:#001;border:1px solid rgba(0,0,0,0.08)}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(255,255,255,0.06)}
@media (max-width:640px){ .card{padding:18px} .title{font-size:16px} }
</style>
</head>
<body>
<div class="card" role="main" aria-live="polite">
	<div class="header">
		<div class="logo" aria-hidden="true"></div>
		<div>
			<div class="title">Admin Request — <?php echo htmlspecialchars(ucfirst($action ?: 'result')); ?></div>
			<div style="color:var(--muted);font-size:13px"><?php echo htmlspecialchars(date('F j, Y, H:i')); ?></div>
		</div>
	</div>

	<div class="status <?php echo $ok ? 'ok' : 'fail'; ?>" role="status" aria-label="<?php echo $ok ? 'Success' : 'Error'; ?>">
		<div class="icon" aria-hidden="true"><?php echo $ok ? '✔' : '✖'; ?></div>
		<div class="msg"><?php echo htmlspecialchars($msg); ?></div>
	</div>

	<?php if (!empty($req)): ?>
		<div class="details">
			<strong>Requester:</strong> <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?><br>
			<strong>Email:</strong> <?php echo htmlspecialchars($req['email']); ?><br>
			<strong>Request ID:</strong> <?php echo (int)$req['id']; ?>
		</div>
	<?php endif; ?>

	<div class="actions">
		<a href="admin_login.php" class="btn primary">Go to Admin Login</a>
		<button class="btn ghost" onclick="window.close()">Close</button>
	</div>
</div>
</body>
</html>
