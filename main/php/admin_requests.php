<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Fetch pending requests
$pending = [];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, created_at, token FROM admin_requests WHERE status = 'pending' ORDER BY created_at DESC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $pending[] = $r;
    $stmt->close();
}
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'dark'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<title>Pending Admin Requests — Meta Shark</title>
<link rel="icon" href="uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root{ --bg:#0b1220; --panel:#0f1720; --accent:#44D62C; --muted:#9aa6b2; --text:#e6eef6; --border:rgba(255,255,255,0.04); --radius:10px; }
body{margin:0;font-family:Segoe UI,system-ui,Arial;background:var(--bg);color:var(--text);padding:24px;}
.header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
.header .title{display:flex;align-items:center;gap:12px}
.logo{width:44px;height:44px;border-radius:8px;background:url('uploads/logo1.png') center/cover no-repeat}
.container{max-width:1100px;margin:0 auto}
.card{background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));border:1px solid var(--border);padding:18px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.6)}
.toolbar{display:flex;gap:8px;align-items:center;margin-bottom:12px}
.input-filter{padding:8px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:var(--text)}
.btn{padding:8px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
.btn.primary{background:var(--accent);color:#001}
.btn.ghost{background:transparent;color:var(--text);border:1px solid rgba(255,255,255,0.04)}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse;margin-top:8px}
th,td{padding:12px 14px;text-align:left;border-top:1px solid var(--border)}
th{background:rgba(68,214,44,0.03);color:var(--accent);font-weight:700}
.actions a{display:inline-block;margin-right:8px;padding:6px 10px;border-radius:8px;text-decoration:none;font-weight:600}
.actions .approve{background:var(--accent);color:#001}
.actions .reject{background:#ff6b6b;color:#fff}
.empty{padding:18px;text-align:center;color:var(--muted)}
@media (max-width:680px){ th,td{padding:10px} .toolbar{flex-direction:column;align-items:stretch} }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="title"><div class="logo" aria-hidden="true"></div><div><h1 style="margin:0;font-size:18px">Pending Admin Requests</h1><div style="color:var(--muted);font-size:13px">Manage new admin access requests</div></div></div>
    <div>
      <a href="admin_dashboard.php" class="btn ghost" style="margin-right:8px">Back to Dashboard</a>
      <button class="btn primary" id="refreshBtn" title="Refresh list">Refresh</button>
    </div>
  </div>

  <div class="card" role="main">
    <div class="toolbar">
      <input id="filterInput" class="input-filter" placeholder="Filter by name or email" aria-label="Filter pending requests">
      <div style="flex:1"></div>
      <div style="color:var(--muted)">Signed in as <?php echo htmlspecialchars($admin_name); ?></div>
    </div>

    <div class="table-wrap" id="tableWrap">
      <?php if (empty($pending)): ?>
        <div class="empty">No pending admin requests.</div>
      <?php else: ?>
        <table id="requestsTable" role="table" aria-label="Pending admin requests">
          <thead>
            <tr><th>Name</th><th>Email</th><th>Requested At</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($pending as $req): ?>
              <tr>
                <td><?php echo htmlspecialchars($req['first_name'].' '.$req['last_name']); ?></td>
                <td><?php echo htmlspecialchars($req['email']); ?></td>
                <td><?php echo htmlspecialchars($req['created_at']); ?></td>
                <td class="actions">
                  <a class="approve" href="admin_requests_handler.php?token=<?php echo urlencode($req['token']); ?>&action=approve" target="_blank" rel="noopener" onclick="return confirmAction(event,'approve','<?php echo htmlspecialchars($req['email']); ?>')">Approve</a>
                  <a class="reject" href="admin_requests_handler.php?token=<?php echo urlencode($req['token']); ?>&action=reject" target="_blank" rel="noopener" onclick="return confirmAction(event,'reject','<?php echo htmlspecialchars($req['email']); ?>')">Reject</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// refresh button reloads the page
document.getElementById('refreshBtn').addEventListener('click', () => location.reload());

// simple client-side filter
const filterInput = document.getElementById('filterInput');
filterInput.addEventListener('input', () => {
  const q = filterInput.value.toLowerCase();
  const rows = document.querySelectorAll('#requestsTable tbody tr');
  rows.forEach(r => {
    const txt = r.textContent.toLowerCase();
    r.style.display = txt.includes(q) ? '' : 'none';
  });
});

function confirmAction(e, action, email) {
  e.preventDefault();
  const ok = confirm(`Are you sure you want to ${action} the admin request for ${email}? This will open the handler page to finalize the action.`);
  if (ok) {
    // open link in new tab (href already has target="_blank")
    window.open(e.currentTarget.href, '_blank', 'noopener');
  }
  return false;
}
</script>
</body>
</html>
