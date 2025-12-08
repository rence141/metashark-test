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

// Generate Initial for Profile Avatar (Required for the new Navbar)
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

// Function specifically for Order Updates
function triggerOrderStatusEmail($email, $fullname, $order_id, $new_status) {
    $subject = "Order #$order_id Update: " . ucfirst($new_status);
    $color = "#44D62C"; // Default Green
    
    $status_msg = "";
    if($new_status == 'confirmed') {
        $status_msg = "Your order has been confirmed and is being processed.";
    } elseif($new_status == 'shipped') {
        $status_msg = "Great news! Your items are on the way.";
        $color = "#00d4ff"; // Blue for shipping
    } elseif($new_status == 'delivered') {
        $status_msg = "Your order has been delivered. Enjoy your purchase!";
    } elseif($new_status == 'cancelled') {
        $status_msg = "Your order has been cancelled.";
        $color = "#d9534f"; // Red for cancel
    } else {
        $status_msg = "The status of your order is now: <strong>$new_status</strong>";
    }

    $message = "
        <p>We are writing to let you know that your order <strong>#$order_id</strong> has been updated.</p>
        <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid $color; margin: 20px 0;'>
            <strong>New Status: " . strtoupper($new_status) . "</strong><br>
            $status_msg
        </div>
        <p>You can view your order details in your dashboard.</p>
    ";

    $body = getEmailTemplate($fullname, "Order Update", $message, $color);
    return send_email($email, $subject, $body);
}

// --- Handle Status Update ---
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    if (in_array($new_status, ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])) {
        // 1. Fetch buyer info BEFORE update to send email
        $stmt_user = $conn->prepare("SELECT u.email, u.fullname FROM orders o JOIN users u ON o.buyer_id = u.id WHERE o.id = ? LIMIT 1");
        $stmt_user->bind_param("i", $order_id);
        $stmt_user->execute();
        $user_data = $stmt_user->get_result()->fetch_assoc();

        // 2. Update Status
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        
        if($stmt->execute()) {
            if($user_data) {
                triggerOrderStatusEmail($user_data['email'], $user_data['fullname'], $order_id, $new_status);
            }
            header("Location: admin_orders.php?updated=1");
            exit;
        }
    }
}

// --- Search / Filter ---
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$where = "1=1";
$params = [];
$types = "";

if ($search && is_numeric($search)) {
    $where .= " AND o.id = ?";
    $params[] = (int)$search;
    $types .= "i";
}

if ($status) {
    $where .= " AND o.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Get orders
$sql = "SELECT o.id, o.total_price, o.status, o.created_at, o.paid_at,
        u.fullname as buyer_name, u.email as buyer_email,
        s.fullname as seller_name
        FROM orders o
        LEFT JOIN users u ON o.buyer_id = u.id
        LEFT JOIN users s ON o.seller_id = s.id
        WHERE $where
        ORDER BY o.id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders — Meta Shark</title>
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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

    /* --- Order Specific Styles --- */
    .table-card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); width: 100%; overflow: hidden; display: flex; flex-direction: column; }
    .table-card h3 { padding: 20px; border-bottom: 1px solid var(--panel-border); margin: 0; font-size: 16px; font-weight: 600; background: rgba(68,214,44,0.02); color: var(--text); }
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 800px; }
    th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); white-space: nowrap; }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    [data-theme="light"] tr:hover td { background: #f9fafb; }

    .filters { background: var(--panel); padding: 20px; border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); margin-bottom: 25px; display: flex; gap: 12px; flex-wrap: wrap; }
    .filters input, .filters select { padding: 10px 15px; border: 1px solid var(--panel-border); border-radius: 8px; background: var(--bg); color: var(--text); outline: none; }
    .filters button { padding: 10px 20px; background: var(--primary); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; }
    
    .btn-clear { background: var(--text-muted); color: var(--panel) !important; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; }
    .btn-view { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-view:hover { background: var(--primary); color: #000; }

    .alert { padding: 15px 25px; border-radius: 8px; margin-bottom: 25px; font-size: 15px; font-weight: 500; }
    .alert-success { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }

    .status-select { padding: 6px 10px; border: 1px solid var(--panel-border); border-radius: 6px; background: var(--bg); color: var(--text); font-size: 13px; cursor: pointer; }

    /* Button Utilities */
    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; border: 1px solid transparent; display: inline-block; }
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

<nav class="admin-navbar">
    <div class="navbar-left">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="logo-area">
            <img src="uploads/logo1.png" alt="Meta Shark">
            <span>META SHARK</span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:16px;">
        <button id="themeBtn" class="btn-xs btn-outline" style="font-size:16px; border:none;">
            <i class="bi bi-moon-stars"></i>
        </button>
        
        <a href="admin_profile.php" class="navbar-profile-link">
            <div class="profile-info-display">
                <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="profile-role" style="color:var(--primary);">Administrator</div>
            </div>
            <div class="profile-avatar">
                <?php echo $admin_initial; ?>
            </div>
        </a>
        <a href="admin_logout.php" class="btn-xs btn-outline" style="color:var(--text-muted); border-color:var(--panel-border);"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<?php include 'admin_sidebar.php'; ?>

<main class="admin-main">
    <h2 class="fade-in" style="margin-bottom:25px; font-size: 24px; font-weight: 700;">Orders Management</h2>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success fade-in">Order status updated successfully. Buyer notified via email.</div>
    <?php endif; ?>

    <div class="filters fade-in">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1">
            <input type="number" name="search" placeholder="Order ID..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:200px">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button type="submit"><i class="bi bi-funnel"></i> Filter</button>
            <a href="admin_orders.php" class="btn-clear"><i class="bi bi-x-circle"></i> Clear</a>
        </form>
    </div>

    <div class="table-card fade-in" style="animation-delay: 0.1s;">
        <h3>Recent Orders (<?php echo count($orders); ?>)</h3>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Buyer</th>
                        <th>Seller</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Payment</th>
                        <th>Status Update</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--text-muted)">No orders found.</td></tr>
                    <?php else: foreach ($orders as $o): ?>
                        <tr>
                            <td><span style="font-family:monospace; font-weight:700">#<?php echo str_pad($o['id'], 6, '0', STR_PAD_LEFT); ?></span></td>
                            <td>
                                <div style="font-weight:600"><?php echo htmlspecialchars($o['buyer_name'] ?? 'Unknown'); ?></div>
                                <div style="font-size:12px;color:var(--text-muted)"><?php echo htmlspecialchars($o['buyer_email'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($o['seller_name'] ?? 'System'); ?></td>
                            <td style="font-weight:700; color:var(--primary)">$<?php echo number_format($o['total_price'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                            <td>
                                <?php if($o['paid_at']): ?>
                                    <span style="color:#44D62C"><i class="bi bi-check-circle-fill"></i> Paid</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted)"><i class="bi bi-hourglass"></i> Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <select name="new_status" onchange="this.form.submit()" class="status-select">
                                        <option value="pending" <?php echo $o['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $o['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="shipped" <?php echo $o['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $o['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $o['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td>
                                <a href="admin_order_details.php?order_id=<?php echo $o['id']; ?>" class="btn-view" target="_blank"><i class="bi bi-eye"></i> View</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
// --- UI Interactivity (Copied from Dashboard) ---
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

// On load, enforce session theme across all admin pages
applyTheme(currentTheme);
updateThemeIcon(currentTheme);

themeBtn.addEventListener('click', () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
    updateThemeIcon(newTheme);
    fetch('theme_toggle.php?theme=' + newTheme).catch(console.error);
});
</script>
</body>
</html>