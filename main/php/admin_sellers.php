<?php
// 1. ENABLE ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';

// Generate Initial for Profile Avatar
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// INCLUDE EMAIL SCRIPT
$email_file = __DIR__ . '/includes/email.php';
if (!file_exists($email_file)) {
    $email_file = __DIR__ . '/email.php';
    if (!file_exists($email_file)) {
        function send_email($a,$b,$c){ return true; } 
    } else { require_once $email_file; }
} else { require_once $email_file; }

// --- Email Templates & Triggers ---
if (!function_exists('getEmailTemplate')) {
    function getEmailTemplate($fullname, $title, $message, $color_code) {
        $logo_url = "https://placehold.co/150x50/161b22/ffffff/png?text=MetaShark";
        $current_date = date("F j, Y"); 
        $current_time = date("g:i A");

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { margin: 0; padding: 0; background-color: #f4f6f8; font-family: sans-serif; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <img src='$logo_url' style='display:block; margin:0 auto; height: 50px;'>
                <h2 style='color:$color_code; text-align:center;'>$title</h2>
                <p>Hello $fullname,</p>
                $message
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <small style='color: #888;'>$current_date - $current_time</small>
            </div>
        </body>
        </html>";
    }
}

if (!function_exists('triggerSuspensionEmail')) {
    function triggerSuspensionEmail($email, $fullname) {
        $msg = "<p>Your <strong>Seller Account</strong> has been suspended due to policy violations or a security review.</p><p>Your products are no longer visible to buyers.</p>";
        $body = getEmailTemplate($fullname, "Shop Suspended", $msg, "#d9534f");
        return send_email($email, "Action Required: Shop Suspended", $body);
    }
}

if (!function_exists('triggerUnsuspensionEmail')) {
    function triggerUnsuspensionEmail($email, $fullname) {
        $msg = "<p>Good news! Your <strong>Seller Account</strong> has been reactivated.</p><p>You can now continue selling on Meta Shark.</p>";
        $body = getEmailTemplate($fullname, "Shop Reactivated", $msg, "#44D62C");
        return send_email($email, "Shop Reactivated", $body);
    }
}

// --- Ensure suspension columns exist (suspension_reason, suspended_at) ---
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'suspension_reason'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN suspension_reason TEXT NULL");
}
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'suspended_at'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN suspended_at DATETIME NULL");
}

// --- Utility: fetch user details ---
function getUserDetails($conn, $id) {
    $stmt = $conn->prepare("SELECT email, fullname FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- Handle Actions ---
if (isset($_POST['suspend_seller']) && is_numeric($_POST['seller_id'])) {
    $id = (int)$_POST['seller_id'];
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') $reason = 'Policy violation or security review';
    
    // Keep role as seller (ENUM safe) but mark inactive; also support legacy suspended_seller if present
    $stmt = $conn->prepare("UPDATE users SET role = 'seller', is_active_seller = 0, suspension_reason = ?, suspended_at = NOW() WHERE id = ? AND role IN ('seller','suspended_seller')");
    $stmt->bind_param("si", $reason, $id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $u = getUserDetails($conn, $id);
        if ($u) triggerSuspensionEmail($u['email'], $u['fullname']);
        header("Location: admin_sellers.php?suspended=1");
        exit;
    }
}

if (isset($_POST['unsuspend_seller']) && is_numeric($_POST['seller_id'])) {
    $id = (int)$_POST['seller_id'];
    
    // Reactivate seller (set active flag, reset role to seller)
    $stmt = $conn->prepare("UPDATE users SET role = 'seller', is_active_seller = 1, suspension_reason = NULL, suspended_at = NULL WHERE id = ? AND role IN ('seller','suspended_seller')");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $u = getUserDetails($conn, $id);
        if ($u) triggerUnsuspensionEmail($u['email'], $u['fullname']);
        header("Location: admin_sellers.php?unsuspended=1");
        exit;
    }
}

// --- Fetch Sellers Data ---
$sellers = []; 
$sql = "SELECT u.id, u.fullname, u.email, u.role, u.is_active_seller,
        COALESCE(u.seller_name, '') as seller_name, 
        COALESCE(u.seller_rating, 0) as seller_rating,
        u.suspension_reason, u.suspended_at,
        COUNT(DISTINCT p.id) as product_count,
        COALESCE(SUM(CASE WHEN (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) THEN o.total_price ELSE 0 END), 0) as total_revenue,
        COUNT(DISTINCT CASE WHEN (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) THEN o.id ELSE NULL END) as order_count
        FROM users u
        LEFT JOIN products p ON u.id = p.seller_id
        LEFT JOIN orders o ON u.id = o.seller_id
        WHERE u.role IN ('seller', 'suspended_seller') 
        GROUP BY u.id, u.fullname, u.email, u.role, u.is_active_seller, u.seller_name, u.seller_rating, u.suspension_reason, u.suspended_at
        ORDER BY total_revenue DESC";

$result = $conn->query($sql);

if ($result) {
    $sellers = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Fallback if complex query fails
    $fallback = $conn->query("SELECT id, fullname, email, role, COALESCE(seller_name, '') as seller_name, COALESCE(seller_rating, 0) as seller_rating FROM users WHERE role IN ('seller', 'suspended_seller')"); 
    if ($fallback) {
        while ($row = $fallback->fetch_assoc()) {
            $row['product_count'] = 0;
            $row['total_revenue'] = 0;
            $row['order_count'] = 0;
            $sellers[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <title>Manage Sellers — Meta Shark</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    /* --- MASTER CSS (Matches Dashboard) --- */
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
        --danger: #f44336; 
        --info: #00d4ff;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; }
    a { text-decoration: none; color: inherit; transition: 0.2s; }

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
    .table-card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); overflow: hidden; display: flex; flex-direction: column; }
    .table-card h3 { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); margin: 0; font-size: 16px; font-weight: 600; background: rgba(68,214,44,0.02); color: var(--text); }
    
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
    th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); white-space: nowrap; }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    [data-theme="light"] tr:hover td { background: #f9fafb; }

    /* --- Utilities & Buttons --- */
    .btn { padding: 8px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: 0.2s; }
    
    .btn-suspend { background: rgba(255,152,0,0.1); color: #ff9800; border: 1px solid #ff9800; }
    .btn-suspend:hover { background: #ff9800; color: #fff; }

    .btn-activate { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
    .btn-activate:hover { background: var(--primary); color: #000; }

    .btn-xs { padding: 6px 12px; font-size: 12px; }
    .btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

    /* Badges */
    .status-badge { padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
    .status-active { background: rgba(68,214,44,0.15); color: var(--primary); border: 1px solid rgba(68,214,44,0.3); }
    .status-suspended { background: rgba(255,152,0,0.15); color: #ff9800; border: 1px solid rgba(255,152,0,0.3); }

    .alert { padding: 15px 25px; border-radius: 8px; margin-bottom: 25px; font-size: 15px; font-weight: 500; }
    .alert-success { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }

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
    <div class="fade-in" style="margin-bottom:25px;">
        <h2 style="font-size: 24px; font-weight: 700;">Shop Management</h2>
    </div>

    <?php if (isset($_GET['suspended'])): ?>
        <div class="alert alert-success fade-in">Shop <strong>suspended</strong> successfully. Notification sent.</div>
    <?php endif; ?>
    <?php if (isset($_GET['unsuspended'])): ?>
        <div class="alert alert-success fade-in">Shop <strong>reactivated</strong> successfully.</div>
    <?php endif; ?>

    <div class="table-card fade-in" style="animation-delay: 0.1s;">
        <h3>Active & Suspended Sellers (<?php echo count($sellers); ?>)</h3>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Seller Details</th>
                        <th>Shop Name</th>
                        <th>Status</th>
                        <th>Perf</th>
                        <th>Inventory</th>
                        <th>Sales</th>
                        <th>Revenue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sellers)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--text-muted)">No Sellers found.</td></tr>
                    <?php else: foreach ($sellers as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600"><?php echo htmlspecialchars($s['fullname']); ?></div>
                                <div style="font-size:12px; color:var(--text-muted)"><?php echo htmlspecialchars($s['email']); ?></div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($s['seller_name'] ?: 'N/A'); ?>
                            </td>
                            <td>
                                <?php
                                    $isSuspended = ($s['role'] !== 'seller') || ((int)($s['is_active_seller'] ?? 1) === 0);
                                    $suspendDate = !empty($s['suspended_at']) ? date('M d, Y', strtotime($s['suspended_at'])) : '';
                                    if ($isSuspended): ?>
                                        <span class="status-badge status-suspended">Suspended</span>
                                        <?php if ($suspendDate): ?>
                                            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Since <?php echo $suspendDate; ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php endif; ?>
                                <?php if ($isSuspended && !empty($s['suspension_reason'])): ?>
                                    <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
                                        <?php echo htmlspecialchars($s['suspension_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color:#ffc107">★</span> <?php echo number_format($s['seller_rating'] ?? 0, 1); ?>
                            </td>
                            <td><?php echo $s['product_count']; ?> Items</td>
                            <td><?php echo $s['order_count']; ?> Orders</td>
                            <td style="font-weight:700; color:var(--primary)">$<?php echo number_format($s['total_revenue'], 2); ?></td>
                            <td>
                                <?php $isSuspended = ($s['role'] !== 'seller') || ((int)($s['is_active_seller'] ?? 1) === 0); ?>
                                <?php if (!$isSuspended): ?>
                                    <form method="POST" class="suspend-form" style="display:inline">
                                        <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" name="suspend_seller" class="btn btn-suspend btn-xs" title="Suspend Shop">
                                            <i class="bi bi-pause-circle"></i> Suspend
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" name="unsuspend_seller" class="btn btn-activate btn-xs" title="Reactivate Shop">
                                            <i class="bi bi-play-circle"></i> Activate
                                        </button>
                                    </form>
                                <?php endif; ?>
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

// Theme Logic
const themeBtn = document.getElementById('themeBtn');
let currentTheme = '<?php echo $theme; ?>';

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
}

function updateThemeIcon(theme) {
    const icon = themeBtn.querySelector('i');
    icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}

// On load, enforce session theme across pages
applyTheme(currentTheme);
updateThemeIcon(currentTheme);

themeBtn.addEventListener('click', () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
    updateThemeIcon(newTheme);
    fetch('theme_toggle.php?theme=' + newTheme).catch(console.error);
});

// Prompt for suspension reason before submitting
document.querySelectorAll('.suspend-form').forEach(form => {
    form.addEventListener('submit', (e) => {
        const reason = prompt('Enter suspension reason (visible to the seller):', 'Policy violation');
        if (!reason) {
            e.preventDefault();
            return false;
        }
        form.querySelector('input[name="reason"]').value = reason;
    });
});
</script>

</body>
</html>