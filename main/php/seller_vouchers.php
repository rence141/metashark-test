<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }
$userId = (int)$_SESSION['user_id'];

// Ensure seller/admin
$role = $_SESSION['role'] ?? 'buyer';
if ($role !== 'seller' && $role !== 'admin') { header("Location: shop.php"); exit(); }

$message = '';

// Detect if vouchers.seller_id and created_by exist
$hasSellerColumn = false;
$colCheck = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'seller_id'");
if ($colCheck && $colCheck->num_rows > 0) { $hasSellerColumn = true; }

$hasCreatedBy = false;
$colCheck2 = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'created_by'");
if ($colCheck2 && $colCheck2->num_rows > 0) { $hasCreatedBy = true; }

// Handle create voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_voucher'])) {
    $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $_POST['code'] ?? ''));
    if (empty($code)) { $code = substr(strtoupper(bin2hex(random_bytes(6))), 0, 10); }

    // Check for duplicate code
    $dupCheck = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
    if ($dupCheck) {
        $dupCheck->bind_param("s", $code);
        $dupCheck->execute();
        if ($dupCheck->get_result()->num_rows > 0) {
            $message = 'Voucher code already exists. Please choose a different code.';
        }
        $dupCheck->close();
    } else {
        $message = 'Database error: Unable to check voucher code.';
    }

    if (empty($message)) {
        $discountType = ($_POST['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
        $discountValue = max(0, (float)($_POST['discount_value'] ?? 0));
        $minPurchase = max(0, (float)($_POST['min_purchase'] ?? 0));
        $maxUses = isset($_POST['max_uses']) && $_POST['max_uses'] !== '' ? max(0, (int)$_POST['max_uses']) : null;
        $daysValid = max(1, (int)($_POST['days_valid'] ?? 30));
        $expiry = date('Y-m-d H:i:s', time() + ($daysValid * 86400));

        if ($hasSellerColumn && $hasCreatedBy) {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses, seller_id, created_by) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
            if ($sql) {
                $sql->bind_param("ssddssii", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses, $userId, $userId);
                if ($sql->execute()) { $message = 'Voucher created: ' . $code; }
                else { $message = 'Failed to create voucher.'; }
            }
        } elseif ($hasSellerColumn) {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses, seller_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
            if ($sql) {
                $sql->bind_param("ssddssi", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses, $userId);
                if ($sql->execute()) { $message = 'Voucher created: ' . $code; }
                else { $message = 'Failed to create voucher.'; }
            }
        } elseif ($hasCreatedBy) {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses, created_by) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
            if ($sql) {
                $sql->bind_param("ssddssi", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses, $userId);
                if ($sql->execute()) { $message = 'Voucher created (global): ' . $code . ' — run SQL migration to scope to seller.'; }
                else { $message = 'Failed to create voucher.'; }
            }
        } else {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses) VALUES (?, ?, ?, ?, ?, ?, 0)");
            if ($sql) {
                $sql->bind_param("ssddsd", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses);
                if ($sql->execute()) { $message = 'Voucher created (global): ' . $code . ' — run SQL migration to scope to seller.'; }
                else { $message = 'Failed to create voucher.'; }
            }
        }
    }
}

// Load existing vouchers for this seller (or all if column missing)
$list = [];
if ($hasSellerColumn) {
    $ls = $conn->prepare("SELECT code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses FROM vouchers WHERE seller_id = ? ORDER BY expiry_date DESC");
    if ($ls) { $ls->bind_param("i", $userId); $ls->execute(); $rs = $ls->get_result(); while ($row = $rs->fetch_assoc()) { $list[] = $row; } }
} else {
    $rs = $conn->query("SELECT code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses FROM vouchers ORDER BY expiry_date DESC");
    if ($rs) { while ($row = $rs->fetch_assoc()) { $list[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Vouchers</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <style>
        body { font-family: Arial, sans-serif; background:#0a0a0a; color:#fff; }
        .container { max-width: 900px; margin: 40px auto; padding: 20px; }
        .card { background:#111; border:1px solid #333; border-radius:10px; padding:20px; margin-bottom:20px; }
        .row { display:flex; gap:12px; flex-wrap:wrap; }
        label { display:block; margin:8px 0 4px; color:#44D62C; }
        input, select { width:100%; padding:10px; border:1px solid #44D62C; border-radius:8px; background:#1a1a1a; color:#fff; }
        .btn { padding:12px 16px; background:#44D62C; color:#000; border:none; border-radius:8px; font-weight:bold; cursor:pointer; }
        .btn:hover { background:#44D62C; opacity:0.9; }
        .msg { margin-bottom: 10px; color:#44D62C; }
        .msg.error { color:#ff4444; }
        table { width:100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding:10px; border-bottom:1px solid #333; text-align:left; }
        th { color:#44D62C; }
        tr:hover { background:#222; }
        .expiry { color:#ff4444; }
        .uses { color:#ffaa00; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Vouchers</h1>
        <?php if ($message): ?>
            <div class="msg <?php echo strpos($message, 'Failed') !== false || strpos($message, 'exists') !== false ? 'error' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <div class="card">
            <h3>Create a Voucher</h3>
            <form method="POST">
                <div class="row">
                    <div style="flex:1; min-width:200px;">
                        <label for="code">Code (optional - auto-generate if empty)</label>
                        <input type="text" id="code" name="code" placeholder="e.g., SAVE10" maxlength="50">
                    </div>
                    <div style="flex:1; min-width:200px;">
                        <label for="discount_type">Type</label>
                        <select id="discount_type" name="discount_type">
                            <option value="percentage">Percentage %</option>
                            <option value="fixed">Fixed Amount (₱)</option>
                        </select>
                    </div>
                    <div style="flex:1; min-width:200px;">
                        <label for="discount_value">Value</label>
                        <input type="number" step="0.01" id="discount_value" name="discount_value" required min="0">
                    </div>
                </div>
                <div class="row">
                    <div style="flex:1; min-width:200px;">
                        <label for="min_purchase">Minimum Purchase (₱)</label>
                        <input type="number" step="0.01" id="min_purchase" name="min_purchase" value="0" min="0">
                    </div>
                    <div style="flex:1; min-width:200px;">
                        <label for="max_uses">Max Uses (blank = unlimited)</label>
                        <input type="number" id="max_uses" name="max_uses" placeholder="" min="0">
                    </div>
                    <div style="flex:1; min-width:200px;">
                        <label for="days_valid">Days Valid</label>
                        <input type="number" id="days_valid" name="days_valid" value="30" min="1">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <button class="btn" type="submit" name="create_voucher" value="1">Create Voucher</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>My Vouchers</h3>
            <?php if (empty($list)): ?>
                <div>No vouchers yet. Create one above.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Purchase</th>
                            <th>Expires</th>
                            <th>Uses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $v): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($v['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars(ucfirst($v['discount_type'])); ?></td>
                                <td><?php echo $v['discount_type'] === 'percentage' ? (float)$v['discount_value'] . '%' : '₱' . number_format((float)$v['discount_value'], 2); ?></td>
                                <td>₱<?php echo number_format((float)$v['min_purchase'], 2); ?></td>
                                <td><?php echo $v['expiry_date'] > date('Y-m-d H:i:s') ? htmlspecialchars($v['expiry_date']) : '<span class="expiry">Expired</span>'; ?></td>
                                <td class="uses"><?php echo (int)$v['current_uses']; ?><?php echo isset($v['max_uses']) && $v['max_uses'] !== null ? (' / ' . (int)$v['max_uses']) : ' / ∞'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php if (!$hasSellerColumn): ?>
                <div style="margin-top:20px; padding:10px; background:#222; border-radius:8px;">
                    <strong>Note:</strong> Vouchers are global (not seller-scoped). To scope to sellers, run: <code>ALTER TABLE vouchers ADD seller_id INT, ADD FOREIGN KEY (seller_id) REFERENCES users(id);</code>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
