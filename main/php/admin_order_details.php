<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Require admin session
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Validate order_id
$orderParam = $_GET['order_id'] ?? $_GET['id'] ?? null;
if (!isset($orderParam) || !is_numeric($orderParam)) {
    header('Location: admin_orders.php');
    exit;
}
$orderId = (int)$orderParam;

// Fetch order summary
$orderSql = "SELECT o.*, 
                    buyer.fullname AS buyer_name, buyer.email AS buyer_email, buyer.phone AS buyer_phone,
                    seller.fullname AS seller_name, seller.email AS seller_email, seller.phone AS seller_phone
             FROM orders o
             LEFT JOIN users buyer ON o.buyer_id = buyer.id
             LEFT JOIN users seller ON o.seller_id = seller.id
             WHERE o.id = ?
             LIMIT 1";
$stmt = $conn->prepare($orderSql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: admin_orders.php');
    exit;
}

// Fetch order items
$itemsSql = "SELECT oi.*, p.name AS product_name, p.image AS product_image
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?";
$stmt = $conn->prepare($itemsSql);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function statusBadgeClass($status) {
    switch ($status) {
        case 'confirmed': return 'badge-info';
        case 'shipped': return 'badge-info';
        case 'delivered': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        default: return 'badge-warning';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo htmlspecialchars($orderId); ?> — Admin</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
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
        }
        [data-theme="dark"] {
            --bg: #0f1115;
            --panel: #161b22;
            --panel-border: #242c38;
            --text: #e6eef6;
            --text-muted: #94a3b8;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
        .admin-navbar {
            position: fixed; top: 0; left: 0; right: 0; height: 70px;
            background: var(--panel); border-bottom: 1px solid var(--panel-border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; z-index: 50; box-shadow: var(--shadow);
        }
        .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; }
        .logo-area img { height: 32px; filter: drop-shadow(0 0 5px var(--primary-glow)); }
        .navbar-profile-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; color: var(--text); }
        .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--primary); box-shadow: 0 0 8px var(--primary-glow); }
        .admin-main { max-width: 1100px; margin: 90px auto 40px; padding: 0 20px; }
        .card { background: var(--panel); border: 1px solid var(--panel-border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px; }
        .card h3 { margin-bottom: 12px; }
        .row { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .badge { padding: 6px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; display: inline-flex; gap: 6px; align-items: center; }
        .badge-success { background: rgba(68,214,44,0.15); color: var(--primary); }
        .badge-danger { background: rgba(244,67,54,0.15); color: var(--danger); }
        .badge-info { background: rgba(0,212,255,0.15); color: var(--info); }
        .badge-warning { background: rgba(255,193,7,0.15); color: #c48a00; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--panel-border); }
        th { color: var(--text-muted); text-transform: uppercase; font-size: 12px; }
        .muted { color: var(--text-muted); font-size: 13px; }
        .flex { display: flex; gap: 8px; align-items: center; }
        .btn { padding: 8px 12px; border-radius: 8px; text-decoration: none; font-weight: 700; border: 1px solid var(--panel-border); color: var(--text); }
        .btn:hover { border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>
<nav class="admin-navbar">
    <div class="logo-area">
        <img src="uploads/logo1.png" alt="Meta Shark">
        <span>Admin · Order Details</span>
    </div>
    <a href="admin_orders.php" class="btn"><i class="bi bi-arrow-left"></i> Back to Orders</a>
</nav>

<main class="admin-main">
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <div>
                <h3 style="margin:0;">Order #<?php echo htmlspecialchars($orderId); ?></h3>
                <div class="muted">Placed: <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($order['created_at']))); ?></div>
            </div>
            <div class="badge <?php echo statusBadgeClass($order['status']); ?>">
                <i class="bi bi-circle-fill" style="font-size:10px;"></i>
                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
            </div>
        </div>
        <div class="row" style="margin-top:16px;">
            <div>
                <div class="muted">Total</div>
                <div style="font-weight:700; font-size:20px;">$<?php echo number_format($order['total_price'], 2); ?></div>
            </div>
            <div>
                <div class="muted">Payment</div>
                <div><?php echo $order['paid_at'] ? 'Paid on '.htmlspecialchars(date('M d, Y h:i A', strtotime($order['paid_at']))) : 'Unpaid'; ?></div>
            </div>
            <div>
                <div class="muted">Buyer</div>
                <div style="font-weight:600;"><?php echo htmlspecialchars($order['buyer_name'] ?? 'Unknown'); ?></div>
                <div class="muted"><?php echo htmlspecialchars($order['buyer_email'] ?? ''); ?></div>
            </div>
            <div>
                <div class="muted">Seller</div>
                <div style="font-weight:600;"><?php echo htmlspecialchars($order['seller_name'] ?? 'System'); ?></div>
                <div class="muted"><?php echo htmlspecialchars($order['seller_email'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Items (<?php echo count($items); ?>)</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="4" class="muted">No items found.</td></tr>
                    <?php else: foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($it['product_name'] ?? 'Unknown Product'); ?></div>
                                <div class="muted">Item ID: <?php echo (int)$it['id']; ?></div>
                            </td>
                            <td><?php echo (int)$it['quantity']; ?></td>
                            <td>$<?php echo number_format($it['price'], 2); ?></td>
                            <td>$<?php echo number_format($it['price'] * $it['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>

