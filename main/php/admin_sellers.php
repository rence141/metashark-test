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

// --- Utility: fetch user details ---
function getUserDetails($conn, $id) {
    $stmt = $conn->prepare("SELECT email, fullname FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- Handle Actions ---
if (isset($_GET['suspend']) && is_numeric($_GET['suspend'])) {
    $id = (int)$_GET['suspend'];
    
    // Change role to 'suspended_seller'
    $stmt = $conn->prepare("UPDATE users SET role = 'suspended_seller' WHERE id = ? AND role = 'seller'");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $u = getUserDetails($conn, $id);
        if ($u) triggerSuspensionEmail($u['email'], $u['fullname']);
        header("Location: admin_sellers.php?suspended=1");
        exit;
    }
}

if (isset($_GET['unsuspend']) && is_numeric($_GET['unsuspend'])) {
    $id = (int)$_GET['unsuspend'];
    
    // Change role back to 'seller'
    $stmt = $conn->prepare("UPDATE users SET role = 'seller' WHERE id = ? AND role = 'suspended_seller'");
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
$sql = "SELECT u.id, u.fullname, u.email, u.role, 
        COALESCE(u.seller_name, '') as seller_name, 
        COALESCE(u.seller_rating, 0) as seller_rating,
        COUNT(DISTINCT p.id) as product_count,
        COALESCE(SUM(CASE WHEN (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) THEN o.total_price ELSE 0 END), 0) as total_revenue,
        COUNT(DISTINCT CASE WHEN (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) THEN o.id ELSE NULL END) as order_count
        FROM users u
        LEFT JOIN products p ON u.id = p.seller_id
        LEFT JOIN orders o ON u.id = o.seller_id
        WHERE u.role IN ('seller', 'suspended_seller') 
        GROUP BY u.id, u.fullname, u.email, u.role, u.seller_name, u.seller_rating
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
<title>Manage Sellers — Meta Shark</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* CSS VARIABLES */
:root{--primary:#44D62C;--bg:#f3f4f6;--panel:#fff;--panel-border:#e5e7eb;--text:#1f2937;--text-muted:#6b7280;--radius:12px;--shadow:0 4px 6px rgba(0,0,0,0.1);--danger:#f44336; --muted:#6b7280;}
[data-theme="dark"]{--bg:#0f1115;--panel:#161b22;--panel-border:#242c38;--text:#e6eef6;--text-muted:#94a3b8;--shadow:0 10px 15px rgba(0,0,0,0.5); --muted:#94a3b8;}
*{margin:0;padding:0;box-sizing:border-box;} 
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);}

/* LAYOUT STRUCTURE */
.admin-wrapper { display: flex; flex-direction: column; min-height: 100vh; }

/* Navbar */
.admin-navbar {
    position: fixed; top: 0; left: 0; right: 0;
    height: 80px;
    background: var(--panel);
    border-bottom: 1px solid var(--panel-border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 30px;
    z-index: 1000;
}
.navbar-left{display:flex;align-items:center;gap:20px;}
.navbar-left img{ height: 50px; width: auto; }
.navbar-left h1{ font-size: 24px; margin: 0; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
.nav-user-info{display:flex;align-items:center;gap:20px;font-size:15px;}
.nav-user-info a {color: var(--text); text-decoration: none; font-weight: 500;}

/* Layout Container */
.layout-container {
    display: flex;
    margin-top: 80px; 
    min-height: calc(100vh - 80px);
}

/* Sidebar */
.admin-sidebar {
    width: 250px;
    background: var(--panel);
    border-right: 1px solid var(--panel-border);
    flex-shrink: 0;
    position: fixed;
    height: calc(100vh - 80px);
    overflow-y: auto;
}
.sidebar-item{display:flex;align-items:center;gap:12px;padding:15px 25px;color:var(--text-muted);text-decoration:none;font-weight:500;border-left:4px solid transparent; font-size: 15px;}
.sidebar-item:hover,.sidebar-item.active{background:var(--bg);color:var(--primary);border-left-color:var(--primary);}

/* Main Content */
.admin-main {
    flex-grow: 1;
    margin-left: 250px;
    padding: 30px;
    width: calc(100% - 250px);
}

/* Table Card */
.table-card {
    background: var(--panel);
    border-radius: var(--radius);
    border: 1px solid var(--panel-border);
    box-shadow: var(--shadow);
    width: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.table-card h3 {
    padding: 20px;
    border-bottom: 1px solid var(--panel-border);
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    background: rgba(68,214,44,0.02);
    color: var(--text);
}

.table-responsive { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 900px; }
th,td { padding: 18px 25px; text-align: left; font-size: 14px; }
th { background: rgba(68,214,44,0.05); color: var(--primary); font-weight: 700; border-bottom: 1px solid var(--panel-border); white-space: nowrap; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
td { border-top: 1px solid var(--panel-border); vertical-align: middle; }

/* Components & BUTTONS */
.btn {
    padding: 8px 14px; 
    border-radius: 6px; 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    font-size: 13px; 
    font-weight: 600; 
    white-space: nowrap; 
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: 0.2s;
}

/* Suspend (Outline/Glass - Orange) */
.btn-suspend { background: rgba(255,152,0,0.1); color: #ff9800; border: 1px solid #ff9800; }
.btn-suspend:hover { background: #ff9800; color: #fff; }

/* Activate (Outline/Glass - Green) */
.btn-activate { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
.btn-activate:hover { background: var(--primary); color: #000; }

.btn-icon-only { padding: 0; width: 36px; height: 36px; border-radius: 6px; justify-content: center;}

/* Badges */
.status-badge { padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
.status-active { background: rgba(68,214,44,0.15); color: var(--primary); border: 1px solid rgba(68,214,44,0.3); }
.status-suspended { background: rgba(255,152,0,0.15); color: #ff9800; border: 1px solid rgba(255,152,0,0.3); }

.alert{padding:15px 25px;border-radius:8px;margin-bottom:25px;font-size:15px; font-weight: 500;}
.alert-success{background:rgba(68,214,44,0.1);color:var(--primary);border:1px solid var(--primary);}
</style>
</head>
<body>

<div class="admin-navbar">
    <div class="navbar-left">
        <img src="uploads/logo1.png" alt="Logo" onerror="this.src='https://placehold.co/150x50/161b22/44D62C/png?text=SHARK'">
        <h1>Meta Shark</h1>
    </div>
    <div class="nav-user-info">
        <span style="color:var(--text-muted)">Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong></span>
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="admin_logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<div class="layout-container">
    <div class="admin-sidebar">
        <div style="padding:20px 25px; color:var(--text); font-weight:800; font-size:12px; letter-spacing:1px; opacity:0.6;">MAIN MENU</div>
        <a href="admin_dashboard.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        
        <div style="padding:20px 25px 10px; color:var(--text); font-weight:800; font-size:12px; letter-spacing:1px; opacity:0.6;">ANALYTICS</div>
        <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-graph-up"></i> Overview</a>
        <a href="charts_line.php" class="sidebar-item"><i class="bi bi-bar-chart-line"></i> Revenue</a>
        <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart"></i> Categories</a>
        <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart"></i> Orders</a>
        
        <div style="padding:20px 25px 10px; color:var(--text); font-weight:800; font-size:12px; letter-spacing:1px; opacity:0.6;">ADMINISTRATION</div>
        <a href="admin_products.php" class="sidebar-item"><i class="bi bi-box"></i> Products</a>
        <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people"></i> Users</a>
        <a href="admin_sellers.php" class="sidebar-item active"><i class="bi bi-shop"></i> Sellers</a>
        <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag"></i> Orders</a>
    </div>

    <div class="admin-main">
        <h2 style="margin-bottom:25px; font-size: 28px; font-weight: 700;">Shop Management</h2>

        <?php if (isset($_GET['suspended'])): ?>
            <div class="alert alert-success">Shop <strong>suspended</strong> successfully. Notification sent.</div>
        <?php endif; ?>
        <?php if (isset($_GET['unsuspended'])): ?>
            <div class="alert alert-success">Shop <strong>reactivated</strong> successfully.</div>
        <?php endif; ?>

        <div class="table-card">
            <h3>Active & Suspended Sellers (<?php echo count($sellers); ?>)</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
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
                            <tr><td colspan="9" style="text-align:center;padding:50px;color:var(--text-muted)">No Sellers found.</td></tr>
                        <?php else: foreach ($sellers as $s): ?>
                            <tr>
                                <td><span style="font-family:monospace; font-weight:700">#<?php echo $s['id']; ?></span></td>
                                <td>
                                    <div style="font-weight:600"><?php echo htmlspecialchars($s['fullname']); ?></div>
                                    <div style="font-size:12px; color:var(--text-muted)"><?php echo htmlspecialchars($s['email']); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($s['seller_name'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <?php if ($s['role'] === 'seller'): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-suspended">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color:#ffc107">★</span> <?php echo number_format($s['seller_rating'] ?? 0, 1); ?>
                                </td>
                                <td><?php echo $s['product_count']; ?> Items</td>
                                <td><?php echo $s['order_count']; ?> Orders</td>
                                <td style="font-weight:700; color:var(--primary)">$<?php echo number_format($s['total_revenue'], 2); ?></td>
                                <td>
                                    <?php if ($s['role'] === 'seller'): ?>
                                        <a href="admin_sellers.php?suspend=<?php echo $s['id']; ?>" class="btn btn-suspend" onclick="return confirm('WARNING: Suspending this seller will hide all their listings. Continue?')" title="Suspend Shop">
                                            <i class="bi bi-pause-circle"></i> Suspend
                                        </a>
                                    <?php else: ?>
                                        <a href="admin_sellers.php?unsuspend=<?php echo $s['id']; ?>" class="btn btn-activate" title="Reactivate Shop">
                                            <i class="bi bi-play-circle"></i> Activate
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>