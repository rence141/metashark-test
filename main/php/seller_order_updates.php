<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'buyer';
if ($role !== 'seller' && $role !== 'admin') { header("Location: shop.php"); exit(); }
$theme = $_SESSION['theme'] ?? 'dark';

$message = '';

// On POST, add status update for allowed order (contains seller's items)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $note = trim($_POST['note'] ?? '');
    if ($orderId > 0 && $status !== '') {
        // Check that order includes this seller's product
        $chk = $conn->prepare("SELECT 1 FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? AND p.seller_id = ? LIMIT 1");
        if ($chk) { $chk->bind_param("ii", $orderId, $userId); $chk->execute(); $has = $chk->get_result()->num_rows === 1; }
        if ($has) {
            $ins = $conn->prepare("INSERT INTO order_status_updates (order_id, seller_id, status, note) VALUES (?, ?, ?, ?)");
            if ($ins) { $ins->bind_param("iiss", $orderId, $userId, $status, $note); $ins->execute(); $message = 'Update added.'; }
        } else {
            $message = 'You are not allowed to update this order.';
        }
    }
}

// Load recent orders that include this seller's products
$orders = [];
$os = $conn->prepare("SELECT DISTINCT o.id, o.status, o.created_at, o.paid_at
                      FROM orders o
                      JOIN order_items oi ON oi.order_id = o.id
                      JOIN products p ON p.id = oi.product_id
                      WHERE p.seller_id = ?
                      ORDER BY o.created_at DESC LIMIT 100");
if ($os) { $os->bind_param("i", $userId); $os->execute(); $rs = $os->get_result(); while ($row = $rs->fetch_assoc()) { $orders[] = $row; } }

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Updates</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <?php include('theme_toggle.php'); ?>
  <style>
    body {
  background: var(--bg-primary);
  color: var(--text-primary);
  font-family: 'Segoe UI', Roboto, Arial, sans-serif;
  margin: 0;
  padding: 0;
  line-height: 1.6;
}

.container {
  max-width: 900px;
  margin: 40px auto;
  padding: 0 20px;
}

h1 {
  color: #44D62C;
  font-size: 2rem;
  margin-bottom: 24px;
  text-align: center;
}

.card {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px 24px;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #ccc;
}

select,
input,
textarea {
  width: 100%;
  padding: 12px;
  margin-bottom: 16px;
  border: 1px solid #333;
  border-radius: 8px;
  background: #121212;
  color: #fff;
  font-size: 0.95rem;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

select:focus,
input:focus,
textarea:focus {
  outline: none;
  border-color: #44D62C;
  box-shadow: 0 0 0 3px rgba(68,214,44,0.3);
}

.btn {
  display: inline-block;
  padding: 12px 24px;
  background: #44D62C;
  color: #000;
  border: none;
  border-radius: 8px;
  font-weight: bold;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.15s ease;
}

.btn:hover {
  background: #39b826;
  transform: translateY(-1px);
}

.btn:active {
  transform: translateY(1px);
}

  </style>
</head>
<body>
  <div class="container">
    <h1>Order Updates</h1>
    <?php if ($message) { echo '<div class="card">' . htmlspecialchars($message) . '</div>'; } ?>
    <div class="card">
      <form method="POST">
        <label>Order</label>
        <select name="order_id" required>
          <option value="">Select order</option>
          <?php foreach ($orders as $o): ?>
            <option value="<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?> — <?php echo htmlspecialchars($o['status']); ?> — <?php echo htmlspecialchars($o['created_at']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Status</label>
        <input type="text" name="status" placeholder="e.g., Shipped, Out for delivery" required>
        <label>Note (optional)</label>
        <textarea name="note" rows="3" placeholder="Add details like tracking number or location"></textarea>
        <div style="margin-top:10px;"><button class="btn" type="submit">Add Update</button></div>
      </form>
    </div>
  </div>
</body>
</html>


