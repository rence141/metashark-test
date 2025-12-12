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

// Generate Initial for Profile Avatar
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Fetch ALL Pending Requests
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    /* --- MASTER CSS FROM DASHBOARD --- */
    :root {
        --primary: #44D62C;
        --primary-glow: rgba(68, 214, 44, 0.3);
        --accent: #00ff88;
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --sidebar-width: 260px;
        --danger: #f44336;
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
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; }
    a { text-decoration: none; color: inherit; }

    /* --- Animations --- */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.5s ease forwards; }

    /* --- Navbar --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 70px;
        background: var(--panel); border-bottom: 1px solid var(--panel-border);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 50; backdrop-filter: blur(10px);
        box-shadow: var(--shadow);
    }
    .navbar-left { display: flex; align-items: center; gap: 16px; }
    .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; letter-spacing: -0.5px; }
    .logo-area img { height: 32px; filter: drop-shadow(0 0 5px var(--primary-glow)); }
    .sidebar-toggle { display: none; background: none; border: none; color: var(--text); font-size: 24px; cursor: pointer; }

    /* --- Profile Widget --- */
    .navbar-profile-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; transition: var(--transition); color: var(--text); }
    .navbar-profile-link:hover { background: rgba(68,214,44,0.1); color: var(--primary); }
    .profile-info-display { text-align: right; line-height: 1.2; display: none; }
    @media (min-width: 640px) { .profile-info-display { display: block; } }
    .profile-name { font-size: 14px; font-weight: 600; color: var(--text); transition: color 0.2s; }
    .navbar-profile-link:hover .profile-name { color: var(--primary); }
    .profile-role { font-size: 11px; color: var(--text-muted); }
    .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--primary); box-shadow: 0 0 8px var(--primary-glow); flex-shrink: 0; }

    /* --- Sidebar --- */
    .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: var(--transition); z-index: 40; }
    .sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
    .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: var(--transition); margin-bottom: 4px; }
    .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    [data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
    .sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
    .sidebar-item i { font-size: 18px; }

    /* --- Main Content --- */
    .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); transition: var(--transition); }

    /* --- Table Styles --- */
    .table-card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); overflow: hidden; }
    .card-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); display: flex; justify-content: space-between; align-items: center; }
    .card-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
    
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 800px; }
    th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); white-space: nowrap; }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    [data-theme="light"] tr:hover td { background: #f9fafb; }

    /* Utilities */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
    .badge-warning { background: rgba(255,152,0,0.15); color: #ff9800; }
    
    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; text-decoration: none; display: inline-block; border: 1px solid transparent; transition: 0.2s; cursor: pointer; }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-primary:hover { filter: brightness(1.1); }
    .btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

    @media (max-width: 992px) {
        .admin-sidebar { transform: translateX(-100%); }
        .admin-sidebar.show { transform: translateX(0); }
        .admin-main { margin-left: 0; }
        .sidebar-toggle { display: block; }
    }
    </style>
</head>
<body>

<?php include 'admin_navbar.php'; ?>
<?php include 'admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="fade-in" style="margin-bottom: 30px;">
        <h2 style="font-size:24px; font-weight:700; margin-bottom:8px;">Admin Access Requests</h2>
        <p style="color:var(--text-muted);">Manage pending applications for administrator access.</p>
    </div>

    <div class="table-card fade-in" style="animation-delay: 0.1s;">
        <div class="card-header">
            <h3>Pending Requests</h3>
            <span class="badge badge-warning" style="background:rgba(255,152,0,0.1); color:#ff9800;"><?php echo count($requests); ?> Waiting</span>
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

<script>
// --- UI Interactivity ---
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); });
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

// Theme Logic (persist across admin pages)
const themeBtn = document.getElementById('themeBtn');
const storedTheme = localStorage.getItem('theme') || '<?php echo $theme; ?>';
function applyTheme(t){ document.documentElement.setAttribute('data-theme', t); localStorage.setItem('theme', t); }
function updateThemeIcon(t){ const icon = themeBtn.querySelector('i'); icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'; }
applyTheme(storedTheme); updateThemeIcon(storedTheme);
themeBtn.addEventListener('click', () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme); updateThemeIcon(newTheme);
    fetch('theme_toggle.php?theme=' + newTheme).catch(console.error);
});

// --- SweetAlert Trigger ---
<?php if (isset($_SESSION['swal_trigger'])): ?>
    Swal.fire({
        icon: '<?php echo $_SESSION['swal_trigger']['icon']; ?>',
        title: '<?php echo addslashes($_SESSION['swal_trigger']['title']); ?>',
        text: '<?php echo addslashes($_SESSION['swal_trigger']['text']); ?>',
        confirmButtonColor: '#44D62C',
        confirmButtonText: 'Okay',
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#161b22' : '#ffffff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e6eef6' : '#1f2937'
    });
<?php unset($_SESSION['swal_trigger']); ?>
<?php endif; ?>
</script>

</body>
</html>