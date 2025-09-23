<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'buyer';

$theme = $_SESSION['theme'] ?? 'dark';

$rows = [];
if ($role === 'seller' || $role === 'admin') {
    // Seller notifications: paid orders containing this seller's products
    $sql = $conn->prepare("SELECT o.id AS order_id, o.paid_at, oi.product_id, oi.quantity, oi.price, p.name AS product_name
                           FROM orders o
                           JOIN order_items oi ON oi.order_id = o.id
                           JOIN products p ON p.id = oi.product_id
                           WHERE o.status = 'paid' AND p.seller_id = ?
                           ORDER BY o.paid_at DESC, o.id DESC LIMIT 100");
    if ($sql) { $sql->bind_param("i", $userId); $sql->execute(); $res = $sql->get_result(); while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
} else {
    // Buyer notifications: your paid orders
    $sql = $conn->prepare("SELECT o.id AS order_id, o.paid_at, oi.product_id, oi.quantity, oi.price, p.name AS product_name
                           FROM orders o
                           JOIN order_items oi ON oi.order_id = o.id
                           JOIN products p ON p.id = oi.product_id
                           WHERE o.status = 'paid' AND o.user_id = ?
                           ORDER BY o.paid_at DESC, o.id DESC LIMIT 100");
    if ($sql) { $sql->bind_param("i", $userId); $sql->execute(); $res = $sql->get_result(); while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  <?php include('theme_toggle.php'); ?>
  <style>
    body { background: var(--bg-primary); color: var(--text-primary); font-family: Arial, sans-serif; }
    .container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
    h1 { color: #44D62C; }
    .card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 12px; }
    .muted { color: var(--text-muted); font-size: 0.9em; }
    .row { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Notifications</h1>
    <?php if (empty($rows)): ?>
      <div class="card">No notifications yet.</div>
    <?php else: ?>
      <?php foreach ($rows as $n): ?>
        <div class="card">
          <div class="row">
            <div>
              <div><strong>Order #<?php echo (int)$n['order_id']; ?></strong></div>
              <div class="muted">Item: <?php echo htmlspecialchars($n['product_name']); ?> · Qty: <?php echo (int)$n['quantity']; ?> · ₱<?php echo number_format((float)$n['price'], 2); ?></div>
            </div>
            <div class="muted"><?php echo htmlspecialchars($n['paid_at'] ?: ''); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>


