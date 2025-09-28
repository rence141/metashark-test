<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }
$userId = (int)$_SESSION['user_id'];
$theme = $_SESSION['theme'] ?? 'dark';

// Fetch user's orders
$orders = [];
$os = $conn->prepare("SELECT id, total_price, status, created_at, paid_at FROM orders WHERE buyer_id = ? ORDER BY created_at DESC");
if ($os) { $os->bind_param("i", $userId); $os->execute(); $rs = $os->get_result(); while ($row = $rs->fetch_assoc()) { $orders[] = $row; } }

// Fetch status updates per order
$updates = [];
if (!empty($orders)) {
  $ids = array_map(function($o){ return (int)$o['id']; }, $orders);
  $in = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $sql = $conn->prepare("SELECT order_id, status, note, created_at FROM order_status_updates WHERE order_id IN ($in) ORDER BY created_at ASC");
  if ($sql) {
    $sql->bind_param($types, ...$ids);
    $sql->execute();
    $rs = $sql->get_result();
    while ($row = $rs->fetch_assoc()) { $updates[$row['order_id']][] = $row; }
  }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <?php include('theme_toggle.php'); ?>
  <style>
    body { background: var(--bg-primary); color: var(--text-primary); font-family: Arial, sans-serif; }
    .container { max-width: 1000px; margin: 24px auto; padding: 0 16px; }
    h1 { color: #44D62C; }
    .order-card { background: var(--bg-secondary); border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:14px; }
    .row { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .timeline { margin-top:10px; border-left: 2px solid var(--border); padding-left: 12px; }
    .tl-item { margin-bottom:8px; }
    .muted { color: var(--text-muted); font-size: 0.9em; }
  </style>
</head>
<body>
  <div class="container">
    <h1>My Orders</h1>
    <?php if (empty($orders)): ?>
      <div class="order-card">No orders yet.</div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="order-card">
          <div class="row">
            <div><strong>Order #<?php echo (int)$o['id']; ?></strong></div>
            <div class="muted">Placed: <?php echo htmlspecialchars($o['created_at']); ?></div>
          </div>
          <div class="row" style="margin-top:6px;">
            <div>Status: <strong><?php echo htmlspecialchars($o['status']); ?></strong></div>
            <div>Total: ₱<?php echo number_format((float)$o['total'], 2); ?></div>
          </div>
          <div class="timeline">
            <?php $list = $updates[$o['id']] ?? []; ?>
            <?php if (empty($list)): ?>
              <div class="tl-item muted">No tracking updates yet.</div>
            <?php else: ?>
              <?php foreach ($list as $u): ?>
                <div class="tl-item">
                  <div><strong><?php echo htmlspecialchars($u['status']); ?></strong></div>
                  <?php if (!empty($u['note'])): ?><div class="muted"><?php echo htmlspecialchars($u['note']); ?></div><?php endif; ?>
                  <div class="muted"><?php echo htmlspecialchars($u['created_at']); ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>


