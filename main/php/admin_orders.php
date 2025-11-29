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

// Function specifically for Order Updates
function triggerOrderStatusEmail($email, $fullname, $order_id, $new_status) {
    $subject = "Order #$order_id Update: " . ucfirst($new_status);
    $color = "#44D62C"; // Default Green
    
    // Custom messages based on status
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
        $stmt_user = $conn->prepare("
            SELECT u.email, u.fullname 
            FROM orders o 
            JOIN users u ON o.buyer_id = u.id 
            WHERE o.id = ? LIMIT 1
        ");
        $stmt_user->bind_param("i", $order_id);
        $stmt_user->execute();
        $user_data = $stmt_user->get_result()->fetch_assoc();

        // 2. Update Status
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        
        if($stmt->execute()) {
            // 3. Send Email if user exists
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
/* CSS VARIABLES (Matches Admin Users) */
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
.nav-user-info a:hover {color: var(--primary);}

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
table { width: 100%; border-collapse: collapse; min-width: 800px; }
th,td { padding: 18px 25px; text-align: left; font-size: 14px; }
th { background: rgba(68,214,44,0.05); color: var(--primary); font-weight: 700; border-bottom: 1px solid var(--panel-border); white-space: nowrap; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
td { border-top: 1px solid var(--panel-border); vertical-align: middle; }

/* Components */
.filters{background:var(--panel);padding:20px;border-radius:var(--radius);border:1px solid var(--panel-border);box-shadow:var(--shadow);margin-bottom:25px;display:flex;gap:12px;flex-wrap:wrap;}
.filters input,.filters select{padding:10px 15px;border:1px solid var(--panel-border);border-radius:8px;background:var(--bg);color:var(--text); outline:none;}
.filters button{padding:10px 20px;background:var(--primary);color:#000;border:none;border-radius:8px;cursor:pointer;font-weight:700;}
.btn-clear{background:var(--text-muted);color:var(--panel)!important;padding:10px 20px;border-radius:8px;font-weight:600;text-decoration:none;}

.btn{padding:8px 14px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;white-space:nowrap; text-decoration:none;}
.btn-view{background:rgba(68,214,44,0.1); color: var(--primary); border:1px solid var(--primary);}
.btn-view:hover{background:var(--primary); color:#000;}

.alert{padding:15px 25px;border-radius:8px;margin-bottom:25px;font-size:15px; font-weight: 500;}
.alert-success{background:rgba(68,214,44,0.1);color:var(--primary);border:1px solid var(--primary);}

.status-select {
    padding: 6px 10px; border:1px solid var(--panel-border); border-radius:6px; background:var(--bg); color:var(--text); font-size:13px; cursor: pointer;
}
.status-badge { padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
.status-pending { background: rgba(255,152,0,0.15); color: #ff9800; }
.status-confirmed { background: rgba(0,212,255,0.15); color: #00d4ff; }
.status-shipped { background: rgba(68,214,44,0.15); color: var(--primary); }
.status-delivered { background: rgba(255,255,255,0.1); border:1px solid var(--text-muted); color: var(--text); }
.status-cancelled { background: rgba(244,67,54,0.15); color: var(--danger); }
</style>
</head>
<body>

<div class="admin-navbar">
    <div class="navbar-left">
        <link rel="icon" href="uploads/logo1.png">
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
        <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
        <a href="admin_orders.php" class="sidebar-item active"><i class="bi bi-bag"></i> Orders</a>
    </div>

    <div class="admin-main">
        <h2 style="margin-bottom:25px; font-size: 28px; font-weight: 700;">Orders Management</h2>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Order status updated successfully. Buyer notified via email.</div>
        <?php endif; ?>

        <div class="filters">
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
                <a href="admin_orders.php" class="btn btn-clear"><i class="bi bi-x-circle"></i> Clear</a>
            </form>
        </div>

        <div class="table-card">
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
                                    <a href="order_details.php?id=<?php echo $o['id']; ?>" class="btn btn-view" target="_blank"><i class="bi bi-eye"></i> View</a>
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