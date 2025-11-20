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
        // Keep discount value sane for percentage
        if ($discountType === 'percentage' && $discountValue > 100) $discountValue = 100;
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
            } else {
                $message = 'Database error when preparing voucher insert.';
            }
        } elseif ($hasSellerColumn) {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses, seller_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
            if ($sql) {
                $sql->bind_param("ssddssi", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses, $userId);
                if ($sql->execute()) { $message = 'Voucher created: ' . $code; }
                else { $message = 'Failed to create voucher.'; }
            } else {
                $message = 'Database error when preparing voucher insert.';
            }
        } elseif ($hasCreatedBy) {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses, created_by) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
            if ($sql) {
                $sql->bind_param("ssddssi", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses, $userId);
                if ($sql->execute()) { $message = 'Voucher created (global): ' . $code . ' — run SQL migration to scope to seller.'; }
                else { $message = 'Failed to create voucher.'; }
            } else {
                $message = 'Database error when preparing voucher insert.';
            }
        } else {
            $sql = $conn->prepare("INSERT INTO vouchers (code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses) VALUES (?, ?, ?, ?, ?, ?, 0)");
            if ($sql) {
                $sql->bind_param("ssddsd", $code, $discountType, $discountValue, $minPurchase, $expiry, $maxUses);
                if ($sql->execute()) { $message = 'Voucher created (global): ' . $code . ' — run SQL migration to scope to seller.'; }
                else { $message = 'Failed to create voucher.'; }
            } else {
                $message = 'Database error when preparing voucher insert.';
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
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller Vouchers</title>

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <!-- Font Awesome for icons (UI only) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="" crossorigin="anonymous">

    <style>
       :root{
    --bg:#0a0a0a;
    --card:#111;
    --muted:#222;
    --accent:#44D62C;
    --accent-2:#00d4ff;
    --text:#ffffff;
    --badge-dark-text:#001;
    --radius:12px;
}

/* GLOBAL — Bigger, Comfortable */
html {
    font-size: 18px; /* main upgrade */
}
html,body{height:100%}
body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 0;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
    line-height: 1.7;
}

.wrap {
    max-width: 1150px; /* slightly wider */
    margin: 34px auto;
    padding: 22px;
}

/* HEADER */
header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    margin-bottom:22px;
}
.brand {
    display:flex;
    align-items:center;
    gap:14px;
}
.brand img { 
    height:52px; 
    width:52px; 
    border-radius:10px; 
    object-fit:cover; 
    border:1px solid rgba(255,255,255,0.05); 
}
h1{
    font-size:1.7rem; /* bigger heading */
    margin:0;
}
.sub { 
    color:rgba(255,255,255,0.6); 
    font-size:1.1rem; /* bigger */
    margin-top:3px; 
}

/* LAYOUT */
.grid {
    display:grid;
    grid-template-columns: 1fr 450px;
    gap:22px;
}
@media (max-width:950px){
    .grid { grid-template-columns: 1fr; }
}

/* CARD — Bigger padding + smoother */
.card {
    background:var(--card);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: var(--radius);
    padding:24px; /* bigger */
    box-shadow: 0 10px 28px rgba(0,0,0,0.7);
}

/* FORM */
form .row { 
    display:flex; 
    gap:14px; 
    margin-bottom:14px; 
    flex-wrap:wrap; 
}
label { 
    display:block; 
    font-size:1.05rem; /* bigger */
    color:var(--accent); 
    margin-bottom:8px; 
}
input[type="text"], 
input[type="number"], 
select {
    width:100%;
    padding:14px 14px; /* bigger inputs */
    border-radius:10px;
    border: 1px solid rgba(68,214,44,0.18);
    background:#121212;
    color:var(--text);
    outline:none;
    box-sizing:border-box;
    font-size:1.05rem; /* bigger text */
}

input[type="number"]::-webkit-outer-spin-button, 
input[type="number"]::-webkit-inner-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}

/* BUTTONS */
.controls { 
    display:flex; 
    gap:12px; 
    align-items:center; 
    margin-top:12px; 
    flex-wrap:wrap; 
}
.btn {
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    color:#001;
    padding:14px 20px; /* bigger */
    border-radius:10px;
    border:none;
    font-weight:700;
    cursor:pointer;
    box-shadow: 0 8px 22px rgba(0,0,0,0.7);
    transition: transform .12s ease, opacity .12s ease;
    font-size:1.05rem; /* bigger */
}
.btn:hover { transform: translateY(-2px); opacity:0.98; }
.btn.ghost { 
    background: transparent; 
    color:var(--accent); 
    border:1px solid rgba(68,214,44,0.18); 
    box-shadow:none; 
}

/* NOTE */
.note {
    background:var(--muted);
    padding:14px;
    border-radius:10px;
    color:rgba(255,255,255,0.78);
    font-size:1.05rem;
}

/* TABLE — Main readability upgrade */
.table-wrap { 
    overflow:auto; 
    max-height:60vh; 
    padding-right:8px; 
}
table {
    width:100%;
    border-collapse:collapse;
    min-width:760px;
}

th, td {
    text-align:left;
    padding:16px 12px; /* bigger rows */
    border-bottom:1px solid rgba(255,255,255,0.05);
    vertical-align:middle;
    font-size:1.05rem; /* bigger text */
}

th { 
    color: var(--accent); 
    position: sticky; 
    top: 0; 
    background: linear-gradient(180deg, rgba(10,10,10,0.96), rgba(10,10,10,0.9)); 
    z-index: 1;
    font-size:1.15rem; /* bigger header */
}

tr:hover { background:#161616; }

/* small text */
.small { 
    font-size:0.95rem; 
    color:rgba(255,255,255,0.75); 
}

/* VOUCHER BADGE */
.voucher-badge {
    display:inline-block;
    padding:8px 14px; /* bigger */
    border-radius:999px;
    font-weight:700;
    font-size:1.05rem;
    background: linear-gradient(90deg, var(--accent), var(--accent-2));
    color: var(--badge-dark-text);
    border: 1px solid rgba(0,0,0,0.08);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    vertical-align:middle;
    white-space:nowrap;
}
.voucher-badge.small { 
    padding:6px 10px; 
    font-size:0.95rem; 
}

/* COLORS */
.expiry { color:#ff4444; font-weight:700; }
.uses { color:#ffaa00; font-weight:700; }

/* META STATS */
.meta { 
    display:flex; 
    flex-direction:column; 
    gap:16px; 
}
.meta .card { padding:18px; }
.meta h4 { 
    margin:0 0 8px 0; 
    color:var(--accent); 
    font-size:1.2rem;
}
.meta .stat { 
    font-size:1.9rem; 
    font-weight:700; 
    color:var(--text); 
}

/* MOBILE */
@media (max-width:520px){
    th, td { padding:12px 10px; font-size:1rem; }
    .brand img { height:40px; width:40px; }
    .voucher-badge { font-size:0.95rem; padding:6px 12px; }
}

/* UTIL */
.row-center { display:flex; align-items:center; gap:10px; }
.muted { color:rgba(255,255,255,0.5); font-size:1rem; }

.msg { 
    margin-bottom:12px; 
    padding:12px 14px; 
    border-radius:10px; 
    font-size:1.05rem; 
}
.msg.ok { 
    background: linear-gradient(90deg, rgba(68,214,44,0.1), rgba(0,212,255,0.08)); 
    color:var(--accent); 
    border:1px solid rgba(68,214,44,0.08); 
}
.msg.err { 
    background: rgba(255,68,68,0.1); 
    color:#ff6b6b; 
    border:1px solid rgba(255,68,68,0.1); 
}

/* copy button */
.copy-btn {
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:10px;
    border:1px solid rgba(255,255,255,0.05);
    background:transparent;
    color:var(--accent);
    cursor:pointer;
    font-weight:700;
    font-size:1.05rem;
}
.copy-btn:active { transform: translateY(1px); }

/* fade-in */
.fade-in { animation: fadeIn .28s ease both; }
@keyframes fadeIn { 
    from { opacity:0; transform: translateY(6px);} 
    to { opacity:1; transform:none; } 
}
    </style>
</head>
<body>
    <div class="wrap">
        <header class="fade-in">
            <div class="brand">
                <img src="Uploads/logo1.png" alt="logo" onerror="this.style.display='none'">
                <div>
                    <h1>Vouchers</h1>
                    <div class="sub">Create a vouchers for discounted products.</div>
                </div>
            </div>
            <div class="row-center">
                <div class="muted">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Seller'); ?></strong></div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="msg <?php echo (strpos($message, 'Failed') !== false || strpos($message, 'exists') !== false) ? 'err' : 'ok'; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- LEFT: Create + List -->
            <div>
                <div class="card fade-in" style="margin-bottom:14px;">
                    <h3 style="margin-top:0;">Create a Voucher</h3>
                    <form method="POST" id="createForm">
                        <div class="row">
                            <div style="flex:1; min-width:200px;">
                                <label for="code">Code <span class="muted">(optional)</span></label>
                                <input type="text" id="code" name="code" placeholder="e.g., SAVE10" maxlength="50" inputmode="latin">
                            </div>
                            <div style="flex:0 0 160px; min-width:140px;">
                                <label for="discount_type">Type</label>
                                <select id="discount_type" name="discount_type">
                                    <option value="percentage">Percentage %</option>
                                    <option value="fixed">Fixed Amount (₱)</option>
                                </select>
                            </div>
                            <div style="flex:0 0 160px; min-width:140px;">
                                <label for="discount_value">Value</label>
                                <input type="number" step="0.01" id="discount_value" name="discount_value" required min="0">
                            </div>
                        </div>

                        <div class="row">
                            <div style="flex:1; min-width:160px;">
                                <label for="min_purchase">Minimum Purchase ($)</label>
                                <input type="number" step="0.01" id="min_purchase" name="min_purchase" value="0" min="0">
                            </div>
                            <div style="flex:0 0 160px;">
                                <label for="max_uses">Max Uses</label>
                                <input type="number" id="max_uses" name="max_uses" placeholder="" min="0">
                            </div>
                            <div style="flex:0 0 120px;">
                                <label for="days_valid">Days Valid</label>
                                <input type="number" id="days_valid" name="days_valid" value="30" min="1">
                            </div>
                        </div>

                        <div class="controls">
                            <button class="btn" type="submit" name="create_voucher" value="1">
                                <i class="fa-solid fa-plus" style="margin-right:8px;"></i>Create Voucher
                            </button>
                            <button type="button" class="btn ghost" id="fillSample">Sample</button>
                            <div class="muted" style="margin-left:auto;">Code auto-generates when left empty.</div>
                        </div>
                    </form>
                </div>

                <div class="card fade-in">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px;">
                        <h3 style="margin:0;">My Vouchers</h3>
                        <div class="muted small">Total: <?php echo count($list); ?></div>
                    </div>

                    <?php if (empty($list)): ?>
                        <div class="note">No vouchers yet. Create one above.</div>
                    <?php else: ?>
                        <div class="table-wrap">
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
                                            <td style="white-space:nowrap;">
                                                <strong>
                                                    <span class="voucher-badge small"><?php echo htmlspecialchars($v['code']); ?></span>
                                                </strong>
                                                <button class="copy-btn" data-code="<?php echo htmlspecialchars($v['code']); ?>" title="Copy code" style="margin-left:8px;">
                                                    <i class="fa-regular fa-copy"></i> Copy
                                                </button>
                                            </td>
                                            <td><?php echo htmlspecialchars(ucfirst($v['discount_type'])); ?></td>
                                            <td class="small">
                                                <?php
                                                    if ($v['discount_type'] === 'percentage') {
                                                        echo (float)$v['discount_value'] . '%';
                                                    } else {
                                                        echo '₱' . number_format((float)$v['discount_value'], 2);
                                                    }
                                                ?>
                                            </td>
                                            <td>₱<?php echo number_format((float)$v['min_purchase'], 2); ?></td>
                                            <td>
                                                <?php
                                                    $isExpired = $v['expiry_date'] <= date('Y-m-d H:i:s');
                                                    echo $isExpired ? '<span class="expiry">Expired</span>' : htmlspecialchars($v['expiry_date']);
                                                ?>
                                            </td>
                                            <td class="uses"><?php echo (int)$v['current_uses']; ?><?php echo isset($v['max_uses']) && $v['max_uses'] !== null ? (' / ' . (int)$v['max_uses']) : ' / ∞'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!$hasSellerColumn): ?>
                        <div style="margin-top:16px; padding:12px; background:#0f0f0f; border-radius:8px;">
                            <strong>Note:</strong>
                            <div class="muted small" style="margin-top:6px;">
                                Vouchers are global (not seller-scoped). To scope to sellers, run:
                                <pre style="background:transparent; border:1px dashed rgba(255,255,255,0.03); padding:8px; margin-top:8px; border-radius:6px; overflow:auto;">
ALTER TABLE vouchers ADD seller_id INT, ADD FOREIGN KEY (seller_id) REFERENCES users(id);
                                </pre>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Stats / Quick Actions -->
            <aside class="meta">
                <div class="card fade-in">
                    <h4>Quick Actions</h4>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="btn" id="copyAll"><i class="fa-solid fa-copy" style="margin-right:8px;"></i>Copy All Codes</button>
                        <button class="btn ghost" id="downloadCSV"><i class="fa-solid fa-file-csv" style="margin-right:8px;"></i>Download CSV</button>
                    </div>
                </div>

                <div class="card fade-in">
                    <h4>Overview</h4>
                    <div>
                        <div class="small">Total vouchers</div>
                        <div class="stat"><?php echo count($list); ?></div>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <script>
        // Small UI helpers (purely client-side)
        (function(){
            // Fill sample
            document.getElementById('fillSample').addEventListener('click', function(){
                document.getElementById('code').value = 'SAVE10';
                document.getElementById('discount_type').value = 'percentage';
                document.getElementById('discount_value').value = 10;
                document.getElementById('min_purchase').value = 0;
                document.getElementById('max_uses').value = 100;
                document.getElementById('days_valid').value = 30;
            });

            // Copy individual
            document.querySelectorAll('.copy-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var code = this.getAttribute('data-code') || '';
                    if (!code) return;
                    navigator.clipboard?.writeText(code).then(function(){
                        btn.innerHTML = '<i class="fa-regular fa-check"></i> Copied';
                        setTimeout(function(){ btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy'; }, 1500);
                    }, function(){
                        alert('Copy failed — select and copy manually.');
                    });
                });
            });

            // Copy all codes
            document.getElementById('copyAll').addEventListener('click', function(){
                var codes = Array.from(document.querySelectorAll('.voucher-badge')).map(function(el){ return el.textContent.trim(); }).filter(Boolean);
                if (codes.length === 0) { alert('No vouchers to copy.'); return; }
                var payload = codes.join(', ');
                navigator.clipboard?.writeText(payload).then(function(){
                    var old = this.innerHTML;
                    document.getElementById('copyAll').innerHTML = '<i class="fa-regular fa-check"></i> Done';
                    setTimeout(function(){ document.getElementById('copyAll').innerHTML = old; }, 1500);
                }.bind(this), function(){
                    alert('Copy failed.');
                });
            });

            // Download CSV
            document.getElementById('downloadCSV').addEventListener('click', function(){
                var rows = [['Code','Type','Value','Min Purchase','Expiry','Uses']];
                document.querySelectorAll('table tbody tr').forEach(function(tr){
                    var cells = tr.querySelectorAll('td');
                    if (!cells.length) return;
                    var code = cells[0].querySelector('.voucher-badge')?.textContent.trim() || '';
                    var type = cells[1].textContent.trim();
                    var value = cells[2].textContent.trim();
                    var minPurchase = cells[3].textContent.trim();
                    var expiry = cells[4].textContent.trim();
                    var uses = cells[5].textContent.trim();
                    rows.push([code, type, value, minPurchase, expiry, uses]);
                });
                if (rows.length <= 1) { alert('No vouchers available to export.'); return; }
                var csv = rows.map(r => r.map(function(cell){
                    if (typeof cell === 'string' && cell.indexOf(',') !== -1) return '"' + cell.replace(/"/g,'""') + '"';
                    return cell;
                }).join(',')).join('\n');

                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'vouchers_export_<?php echo date("Ymd_His"); ?>.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            });

            // Client-side minimal validation: percentage <= 100
            document.getElementById('createForm').addEventListener('submit', function(e){
                var type = document.getElementById('discount_type').value;
                var val = parseFloat(document.getElementById('discount_value').value) || 0;
                if (type === 'percentage' && val > 100) {
                    e.preventDefault();
                    alert('Percentage discount cannot be greater than 100%.');
                    return false;
                }
            });

        })();
    </script>
</body>
</html>
