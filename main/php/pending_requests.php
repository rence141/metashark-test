<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Fetch ALL Pending Requests (No limit since this is the dedicated page)
$requests = [];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, created_at, token FROM admin_requests WHERE status = 'pending' ORDER BY created_at ASC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $requests[] = $r;
    $stmt->close();
}
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Requests — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    :root {
        --primary: #44D62C;
        --primary-glow: rgba(68, 214, 44, 0.3);
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --sidebar-width: 260px;
    }

    [data-theme="dark"] {
        --bg: #0f1115;
        --panel: #161b22;
        --panel-border: #242c38;
        --text: #e6eef6;
        --text-muted: #94a3b8;
        --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
    a { text-decoration: none; color: inherit; }

    /* Layout */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 70px;
        background: var(--panel); border-bottom: 1px solid var(--panel-border);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 50;
    }
    .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; }
    .logo-area img { height: 32px; }

    .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; z-index: 40; }
    .sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
    .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: 0.2s; margin-bottom: 4px; }
    .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    [data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
    .sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
    
    .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; }

    /* Table Styles */
    .card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); }
    .card-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); }
    .card-header h3 { margin: 0; font-size: 18px; }
    
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 16px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }

    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-warning { background: rgba(255,152,0,0.15); color: #ff9800; }
    
    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; text-decoration: none; display: inline-block; border: 1px solid transparent; }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-outline { border-color: var(--panel-border); color: var(--text); }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

    /* Profile Widget */
    .navbar-profile-link { display: flex; align-items: center; gap: 12px; }
    .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #000; display: flex; align-items: center; justify-content: center; font-weight: 700; }
    </style>
</head>
<body>

<nav class="admin-navbar">
    <div class="navbar-left">
        <div class="logo-area">
            <img src="uploads/logo1.png" alt="Meta Shark">
            <span>META SHARK</span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:16px;">
        <a href="admin_profile.php" class="navbar-profile-link">
            <div style="text-align:right; line-height:1.2;">
                <div style="font-size:14px; font-weight:600;"><?php echo htmlspecialchars($admin_name); ?></div>
                <div style="font-size:11px; color:var(--primary);">Administrator</div>
            </div>
            <div class="profile-avatar"><?php echo $admin_initial; ?></div>
        </a>
    </div>
</nav>

<aside class="admin-sidebar">
    <a href="admin_dashboard.php" class="sidebar-item"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    
    <div class="sidebar-group-label">Analytics</div>
    <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-activity"></i> Overview</a>
    <a href="charts_line.php" class="sidebar-item"><i class="bi bi-graph-up-arrow"></i> Revenue</a>
    <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart-fill"></i> Categories</a>
    <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart-fill"></i> Orders</a>
    <a href="charts_geo.php" class="sidebar-item"><i class="bi bi-globe2"></i> Geography</a>

    <div class="sidebar-group-label">Management and Access</div>
    <a href="pending_requests.php" class="sidebar-item active"><i class="bi bi-shield-lock"></i> Requests</a>
    <a href="admin_products.php" class="sidebar-item"><i class="bi bi-box-seam"></i> Products</a>
    <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people-fill"></i> Users</a>
    <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
    <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag-check-fill"></i> Orders</a>
    <!-- Active Link -->

    <div class="sidebar-group-label">Settings</div>
    <a href="admin_profile.php" class="sidebar-item"><i class="bi bi-person-gear"></i> My Profile</a>
</aside>

<main class="admin-main">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size:24px; font-weight:700; margin-bottom:8px;">Admin Access Requests</h2>
        <p style="color:var(--text-muted);">Manage pending applications for administrator access.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Pending Requests</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Requested Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">
                                <i class="bi bi-check-circle" style="font-size:32px; display:block; margin-bottom:10px; opacity:0.5;"></i>
                                All caught up! No pending requests.
                            </td>
                        </tr>
                    <?php else: foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></div>
                            </td>
                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($req['email']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($req['created_at']))); ?></td>
                            <td><span class="badge badge-warning">Pending</span></td>
                            <td>
                                <a href="admin_requests_handler.php?token=<?php echo urlencode($req['token']); ?>&action=approve" class="btn-xs btn-primary">Approve</a>
                                <a href="admin_requests_handler.php?token=<?php echo urlencode($req['token']); ?>&action=reject" class="btn-xs btn-outline" style="margin-left:5px;">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>