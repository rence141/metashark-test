<?php
session_start();
include("db.php");
if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }

$orderId = $_SESSION['pending_payment_order_id'] ?? 0;
$method = $_SESSION['pending_payment_method'] ?? '';
$total = $_SESSION['pending_payment_total'] ?? 0;
if (!$orderId || !$method) { header("Location: shop.php"); exit(); }

// Load per-seller breakdown with contact numbers
$perSeller = [];
$q = $conn->prepare("SELECT s.id AS seller_id,
                            COALESCE(s.seller_name, s.fullname) AS seller_name,
                            s.email AS seller_email,
                            s.gcash_number AS gcash_number,
                            s.phone AS phone,
                            s.contact_number AS contact_number,
                            p.name AS product_name,
                            oi.quantity,
                            oi.price
                     FROM order_items oi
                     JOIN products p ON p.id = oi.product_id
                     JOIN users s ON s.id = p.seller_id
                     WHERE oi.order_id = ?");
if ($q) {
  $q->bind_param("i", $orderId);
  $q->execute();
  $rs = $q->get_result();
  while ($row = $rs->fetch_assoc()) {
    $sid = (int)$row['seller_id'];
    if (!isset($perSeller[$sid])) {
      $perSeller[$sid] = [
        'seller_name' => $row['seller_name'] ?? 'Seller',
        'contact' => ($row['gcash_number'] ?? '') ?: (($row['phone'] ?? '') ?: ($row['contact_number'] ?? '')),
        'items' => [],
        'subtotal' => 0.0,
      ];
    }
    $line = (float)$row['price'] * (int)$row['quantity'];
    $perSeller[$sid]['items'][] = [
      'product_name' => $row['product_name'],
      'quantity' => (int)$row['quantity'],
      'price' => (float)$row['price'],
      'line_total' => $line,
    ];
    $perSeller[$sid]['subtotal'] += $line;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mock Payment</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <style>
    body { font-family: Arial, sans-serif; background:#0a0a0a; color:#fff; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { background:#111; border:1px solid #333; border-radius:10px; padding:30px; width:100%; max-width:420px; }
    h1 { color:#44D62C; margin:0 0 10px; }
    .btn { width:100%; padding:12px; background:#44D62C; color:#000; border:none; border-radius:8px; font-weight:bold; cursor:pointer; margin-top:12px; }
    .btn.secondary { background:#333; color:#fff; }
    .row { margin:10px 0; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Pay with <?php echo htmlspecialchars(strtoupper($method)); ?></h1>
    <div class="row">Order #<?php echo htmlspecialchars($orderId); ?></div>
    <div class="row">Total: ₱<?php echo number_format((float)$total, 2); ?></div>

    <?php if (in_array(strtolower($method), ['gcash', 'alipay'])): ?>
      <div class="row" style="margin-top:16px;">
        <strong>Scan QR per seller to pay their share:</strong>
      </div>
      <?php foreach ($perSeller as $sid => $seller): ?>
        <?php
          $amount = number_format((float)$seller['subtotal'], 2, '.', '');
          $contact = trim((string)($seller['contact'] ?? ''));
          // Build a simple payload string (for demo). Real GCash/Alipay QR payloads differ.
          $payload = strtoupper($method) . ":$contact|ORDER:$orderId|AMOUNT:$amount";
          $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($payload);
        ?>
        <div class="row" style="padding:10px; border:1px solid #333; border-radius:8px; margin:10px 0;">
          <div style="margin-bottom:6px; color:#44D62C; font-weight:bold;">
            <?php echo htmlspecialchars($seller['seller_name']); ?> — Amount: ₱<?php echo number_format((float)$seller['subtotal'], 2); ?>
          </div>
          <?php if ($contact !== ''): ?>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
              <img src="<?php echo $qrUrl; ?>" alt="<?php echo htmlspecialchars(strtoupper($method)); ?> QR" width="200" height="200">
              <div>
                <div>Contact: <?php echo htmlspecialchars($contact); ?></div>
                <div style="color:#888; font-size:0.9em;">Scan with <?php echo htmlspecialchars(strtoupper($method)); ?> app</div>
              </div>
            </div>
          <?php else: ?>
            <div style="color:#ff7676;">No contact number configured for this seller. Please contact the seller or choose a different method.</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <form method="POST" action="payment_callback.php">
      <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
      <input type="hidden" name="status" value="success">
      <button class="btn" type="submit">Simulate Successful Payment</button>
    </form>
    <form method="POST" action="payment_callback.php">
      <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
      <input type="hidden" name="status" value="failed">
      <button class="btn secondary" type="submit">Simulate Failed Payment</button>
    </form>
  </div>
</body>
</html>


