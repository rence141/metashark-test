<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { 
    header("Location: login_users.php"); 
    exit(); 
}
$userId = (int)$_SESSION['user_id'];

// Theme preference (match shop.php)
if (isset($_GET['theme'])) {
    $new_theme = in_array($_GET['theme'], ['light', 'dark', 'device']) ? $_GET['theme'] : 'device';
    $_SESSION['theme'] = $new_theme;
} else {
    $theme = $_SESSION['theme'] ?? 'device';
}
$effective_theme = $theme;
if ($theme === 'device') {
    $effective_theme = 'dark';
}

// Notification count
$notif_count = 0;
$notif_sql = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0");
if ($notif_sql) {
    $notif_sql->bind_param("i", $userId);
    $notif_sql->execute();
    $notif_res = $notif_sql->get_result();
    if ($notif_res && $notif_res->num_rows) {
        $notif_row = $notif_res->fetch_assoc();
        $notif_count = (int)$notif_row['count'];
    }
}

// Cart count for navbar
$cart_count = 0;
$count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
if ($count_stmt) {
    $count_stmt->bind_param("i", $userId);
    $count_stmt->execute();
    $count_res = $count_stmt->get_result();
    if ($count_res && $count_res->num_rows) {
        $cart_data = $count_res->fetch_assoc();
        $cart_count = (int)($cart_data['total'] ?? 0);
    }
}

// Profile image and page
$user_role = $_SESSION['role'] ?? 'buyer';
$profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
$profile_query = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
if ($profile_query) {
    $profile_query->bind_param("i", $userId);
    $profile_query->execute();
    $profile_result = $profile_query->get_result();
    $current_profile = $profile_result ? $profile_result->fetch_assoc() : null;
    $current_profile_image = $current_profile['profile_image'] ?? null;
}

// Function to clear all pending result sets
function clearPendingResults($conn) {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

$payment_message = null;
$payment_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['beta_toggle_submit'])) {
    $_SESSION['beta_payment_enabled'] = isset($_POST['toggle_beta']) ? 1 : 0;
    $payment_message = $_SESSION['beta_payment_enabled']
        ? 'Beta payment tools enabled.'
        : 'Beta payment tools disabled.';
    $payment_success = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    if (empty($_SESSION['beta_payment_enabled'])) {
        $payment_message = "Beta payment must be enabled before marking orders as paid.";
        $payment_success = false;
    } else {
    clearPendingResults($conn);
    $conn->begin_transaction();
    try {
        $verify_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND buyer_id = ? LIMIT 1");
        if (!$verify_stmt) {
            throw new Exception("Unable to verify order ownership.");
        }
        $verify_stmt->bind_param("ii", $orderId, $userId);
        $verify_stmt->execute();
        $verify_stmt->store_result();
        if ($verify_stmt->num_rows === 0) {
            throw new Exception("Order not found or not owned by you.");
        }
        $verify_stmt->close();

        $order_upd = $conn->prepare("UPDATE orders SET paid_at = NOW() WHERE id = ? AND buyer_id = ?");
        $order_upd->bind_param("ii", $orderId, $userId);
        $order_upd->execute();
        $order_upd->close();

        $seller_stmt = $conn->prepare("SELECT DISTINCT p.seller_id FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $seller_stmt->bind_param("i", $orderId);
        $seller_stmt->execute();
        $seller_res = $seller_stmt->get_result();
        $notification_sql = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
        while ($seller = $seller_res->fetch_assoc()) {
            $sellerId = (int)$seller['seller_id'];
            if ($sellerId && $sellerId !== $userId) {
                $message = "Payment confirmed (beta) for order #$orderId. You can proceed with fulfillment.";
                $notif_stmt = $conn->prepare($notification_sql);
                if ($notif_stmt) {
                    $type = 'payment_beta';
                    $notif_stmt->bind_param("iss", $sellerId, $message, $type);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            }
        }
        $seller_res->free();
        $seller_stmt->close();

        $buyer_message = "Payment marked as done (beta) for order #$orderId.";
        $buyer_notif = $conn->prepare($notification_sql);
        if ($buyer_notif) {
            $type = 'payment_beta';
            $buyer_notif->bind_param("iss", $userId, $buyer_message, $type);
            $buyer_notif->execute();
            $buyer_notif->close();
        }

        $conn->commit();
        $payment_message = "Payment recorded for order #$orderId. Sellers will be notified while we finish the beta.";
        $payment_success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $payment_message = $e->getMessage();
        $payment_success = false;
    }
    clearPendingResults($conn);
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = (int)$_POST['order_id'];
    
    // Clear any pending results before transaction
    clearPendingResults($conn);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update order items status to cancelled
        $cancel_query = "UPDATE order_items 
                        SET status = 'cancelled' 
                        WHERE order_id = ? AND status IN ('pending', 'confirmed')";
        $cancel_stmt = $conn->prepare($cancel_query);
        $cancel_stmt->bind_param("i", $orderId);
        $cancel_stmt->execute();
        $affected_rows = $cancel_stmt->affected_rows;
        $cancel_stmt->close();
        
        if ($affected_rows > 0) {
            // Get sellers to notify about the cancellation
            $sellers_query = "SELECT DISTINCT p.seller_id, u.seller_name, p.name as product_name, oi.quantity
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             JOIN users u ON p.seller_id = u.id 
                             WHERE oi.order_id = ? AND p.seller_id != ?";
            $sellers_stmt = $conn->prepare($sellers_query);
            $sellers_stmt->bind_param("ii", $orderId, $userId);
            $sellers_stmt->execute();
            $sellers_result = $sellers_stmt->get_result();
            
            // Notify each seller
            $notified_sellers = 0;
            while ($seller = $sellers_result->fetch_assoc()) {
                $seller_id = $seller['seller_id'];
                $seller_name = $seller['seller_name'];
                $product_name = $seller['product_name'];
                $quantity = $seller['quantity'];
                
                $notification_message = "Order #$orderId for product '$product_name' (Quantity: $quantity) has been cancelled by the buyer.";
                $notification_type = "order_cancelled";
                
                $notification_sql = "INSERT INTO notifications (user_id, message, type, created_at, `read`) 
                                    VALUES (?, ?, ?, NOW(), 0)";
                $notif_stmt = $conn->prepare($notification_sql);
                $notif_stmt->bind_param("iss", $seller_id, $notification_message, $notification_type);
                $notif_stmt->execute();
                $notif_stmt->close();
                $notified_sellers++;
            }
            $sellers_result->free();
            $sellers_stmt->close();
            
            $conn->commit();
            $cancellation_message = "Order #$orderId has been cancelled successfully. Sellers have been notified.";
            $cancellation_success = true;
        } else {
            throw new Exception("No items were cancelled. They may have already been processed.");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $cancellation_message = $e->getMessage();
        $cancellation_success = false;
    }
    
    // Clear any pending results after transaction
    clearPendingResults($conn);
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$orders = [];

// Clear any pending results before main query
clearPendingResults($conn);

// Debug: Log connection state
error_log("Before main query: status_filter=$status_filter, more_results=" . ($conn->more_results() ? 'true' : 'false'));

if ($status_filter === 'all') {
    $order_query = "SELECT o.*, 
                           COUNT(oi.id) as item_count,
                           MIN(oi.status) as overall_status,
                           GROUP_CONCAT(DISTINCT oi.status) as all_statuses
                    FROM orders o 
                    LEFT JOIN order_items oi ON o.id = oi.order_id 
                    WHERE o.buyer_id = ? 
                    GROUP BY o.id 
                    ORDER BY o.created_at DESC";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("i", $userId);
    $order_stmt->execute();
    $orders_result = $order_stmt->get_result();
    $orders = $orders_result->fetch_all(MYSQLI_ASSOC);
    $orders_result->free();
    $order_stmt->close();
} else {
    // Debug: Log before filter query
    error_log("Before filter query: status_filter=$status_filter, more_results=" . ($conn->more_results() ? 'true' : 'false'));
    
    if ($status_filter === 'confirmed') {
        $filter_query = "SELECT o.*, 
                                COUNT(oi.id) as item_count,
                                MIN(oi.status) as overall_status,
                                GROUP_CONCAT(DISTINCT oi.status) as all_statuses
                         FROM orders o 
                         LEFT JOIN order_items oi ON o.id = oi.order_id 
                         WHERE o.buyer_id = ? AND oi.status = 'confirmed'
                         GROUP BY o.id 
                         HAVING overall_status = 'confirmed'
                         ORDER BY o.created_at DESC";
        $filter_stmt = $conn->prepare($filter_query);
        $filter_stmt->bind_param("i", $userId);
    } else {
        $filter_query = "SELECT o.*, 
                                COUNT(oi.id) as item_count,
                                MIN(oi.status) as overall_status,
                                GROUP_CONCAT(DISTINCT oi.status) as all_statuses
                         FROM orders o 
                         LEFT JOIN order_items oi ON o.id = oi.order_id 
                         WHERE o.buyer_id = ? AND oi.status = ? 
                         GROUP BY o.id 
                         ORDER BY o.created_at DESC";
        $filter_stmt = $conn->prepare($filter_query);
        $filter_stmt->bind_param("is", $userId, $status_filter);
    }
    $filter_stmt->execute();
    $orders_result = $filter_stmt->get_result(); // Line 123
    $orders = $orders_result->fetch_all(MYSQLI_ASSOC);
    $orders_result->free();
    $filter_stmt->close();
    
    // Clear any pending results after filter query
    clearPendingResults($conn);
}

// Helper: resolve product image URL for an order item
function resolveProductImageUrl(array $item) {
    // Always prefer the database value as-is (consistent with cart/shop rendering)
    $img = trim($item['image'] ?? '');
    if ($img !== '') {
        return str_replace('\\', '/', $img);
    }

    // Check session cart (some implementations store working image paths there)
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $cartItem) {
            if (isset($cartItem['product_id']) && isset($item['product_id']) && (int)$cartItem['product_id'] === (int)$item['product_id']) {
                if (!empty($cartItem['image'])) {
                    return $cartItem['image'];
                }
            }
        }
    }

    // Candidate relative paths (relative to this PHP file)
    $candidates = [
        __DIR__ . '/uploads/products/' . $img,
        __DIR__ . '/uploads/' . $img,
        __DIR__ . '/Uploads/products/' . $img,
        __DIR__ . '/Uploads/' . $img,
        __DIR__ . '/../uploads/products/' . $img,
        __DIR__ . '/../uploads/' . $img
    ];

    foreach ($candidates as $path) {
        if ($img !== '' && file_exists($path)) {
            return basename($path);
        }
    }

    // If image filename provided but file not found, try just using filename under uploads (best-effort)
    if ($img !== '') {
        if (file_exists(__DIR__ . '/uploads/' . $img)) return 'uploads/' . $img;
        if (file_exists(__DIR__ . '/Uploads/' . $img)) return 'Uploads/' . $img;
    }

    // Last-resort: default product image (prefer capitalized Uploads if present)
    $defaults = [
        'Uploads/default-product.png',
        'uploads/default-product.png'
    ];
    foreach ($defaults as $d) {
        if (file_exists(__DIR__ . '/' . $d)) return $d;
        if (file_exists(__DIR__ . '/../' . $d)) return $d;
    }

    // If none found, return a data placeholder or empty string
    return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90"><rect width="100%" height="100%" fill="#222"/><text x="50%" y="50%" fill="#888" font-size="12" dominant-baseline="middle" text-anchor="middle">No image</text></svg>');
}

// Function to determine overall order status based on item statuses
function getOverallOrderStatus($order_items) {
    $statuses = array_column($order_items, 'status');
    
    if (in_array('cancelled', $statuses)) {
        return 'cancelled';
    }
    
    if (count(array_unique($statuses)) === 1 && $statuses[0] === 'delivered') {
        return 'delivered';
    }
    
    if (in_array('shipped', $statuses)) {
        return 'shipped';
    }
    
    if (in_array('confirmed', $statuses)) {
        return 'confirmed';
    }
    
    return 'pending';
}

// Function to check if order can be cancelled
function canCancelOrder($order_items) {
    $cancellable_statuses = ['pending', 'confirmed'];
    
    foreach ($order_items as $item) {
        if (in_array($item['status'], $cancellable_statuses)) {
            return true;
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/order_status.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        /* product thumbnail */
        .product-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 12px;
            flex-shrink: 0;
            background: #111;
            border: 1px solid #222;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Navbar styles (from shop.php) */
        .navbar { position: sticky; top: 0; z-index: 1000; background: var(--background, #000); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, #444); }
        .nav-left { display: flex; align-items: center; gap: 10px; }
        .logo { height: 40px; }
        .nav-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .nav-controls { display: flex; align-items: center; gap: 14px; }
        .nav-icon-link { text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px; font-size:0.95rem; }
        .nav-icon-link i { font-size:18px; }
        .beta-toggle-inline { display:inline-flex; align-items:center; gap:8px; color:#c7c7c7; font-size:0.9rem; }
        .beta-switch { position:relative; display:inline-block; width:40px; height:20px; }
        .beta-switch input { opacity:0; width:0; height:0; }
        .beta-switch .slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#444; transition:.3s; border-radius:34px; }
        .beta-switch .slider:before { position:absolute; content:""; height:16px; width:16px; left:2px; bottom:2px; background-color:#fff; transition:.3s; border-radius:50%; }
        .beta-switch input:checked + .slider { background-color:#00ff88; }
        .beta-switch input:checked + .slider:before { transform:translateX(20px); }
        .beta-label { font-weight:600; text-transform:uppercase; letter-spacing:0.08em; }
        .profile-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #00640033; }
        .nonuser-text { font-size: 16px; color: #fff; text-decoration: none; }
        .hamburger { background: none; border: none; font-size: 24px; cursor: pointer; color: #fff; }
        .menu { display: none; position: absolute; top: 60px; right: 20px; background: var(--background, #000); border: 1px solid var(--border-color, #444); border-radius: 8px; padding: 10px; list-style: none; margin: 0; z-index: 1000; }
        .menu.show { display: block; }
        .menu li { margin: 10px 0; }
        .menu li a { color: #fff; text-decoration: none; font-size: 16px; }
        .menu li a:hover { color: #27ed15; }
        .theme-dropdown { position: relative; display: inline-block; }
        .theme-btn { appearance: none; background: #000; color: #fff; border: 2px solid #00ff88; padding: 8px 12px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; min-width: 120px; display: inline-flex; align-items: center; gap: 6px; }
        .theme-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: rgba(0,0,0,0.95); border: 2px solid rgba(0,255,136,0.3); border-radius: 12px; padding: 8px; min-width: 120px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); display: none; z-index: 1000; }
        .theme-dropdown.active .theme-menu { display: block; }
        .theme-option { width: 100%; padding: 10px 12px; border: none; background: transparent; border-radius: 8px; cursor: pointer; text-align: left; font-weight: 600; color: #cecccc; }
        .theme-option:hover { background: rgba(0,255,136,0.08); color: #00aa55; }

        .order-card {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #44D62C;
        }
        /* compact order detail rows */
        .order-details { display: grid; gap: 4px; }
        /* make label/value spacing consistent */
        .order-details .detail-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            align-items: baseline;
            padding: 0;
            margin: 0;
        }
        .detail-label { line-height: 1.2; margin-right: 8px; }
        .detail-value { line-height: 1.2; justify-self: start; }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #444;
            background: #2a2a2a;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .filter-btn.active {
            background: #44D62C;
            color: #000;
            border-color: #44D62C;
        }
        
        .order-items {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #333;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            margin-bottom: 6px;
            background: #2a2a2a;
            border-radius: 6px;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }
        
        .item-details {
            font-size: 0.9em;
            color: #ccc;
            line-height: 1.3;
        }
        
        .item-specs {
            display: block;
            font-size: 0.8em;
            color: #888;
            margin-top: 1px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .order-actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #333;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }
        
        .btn-cancel {
            background: #dc3545;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #c82333;
        }
        
        .btn-review {
            background: #44D62C;
            color: black;
        }
        
       .btn-details {
    background: #6c757d09; /* translucent background */
    color: white;
    border: 1px solid #00ff88; /* subtle gray border */
    border-radius: 5px; /* optional: rounded corners */
    padding: 8px 16px; /* optional: add spacing */
    cursor: pointer; /* makes it feel clickable */
    transition: 0.3s; /* smooth hover effect */
    }

    .btn-details:hover {
        background: #6c757d33; /* slightly darker on hover */
        border-color: #6c757d; /* keep border visible */
    }
        
        .tracking-info {
            background: #2a2a2a;
            padding: 8px;
            border-radius: 6px;
            margin-top: 8px;
            border-left: 3px solid #44D62C;
        }
        /* tighten order details rows */
        .order-details .detail-row { padding: 2px 0; }
        
        .tracking-number {
            font-family: monospace;
            background: #1a1a1a;
            padding: 4px 8px;
            border-radius: 3px;
            margin-left: 5px;
        }
        
        .seller-info {
            font-size: 0.8em;
            color: #888;
            margin-top: 2px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 1000;
            max-width: 400px;
        }
        .notification.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .notification.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <img src="uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <div class="nav-controls">
              <div class="theme-dropdown" id="themeDropdown">
                <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme">
                  <i class="bi theme-icon" id="themeIcon"></i>
                  <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($effective_theme === 'light' ? 'Dark' : 'Light'); ?></span>
                </button>
                <div class="theme-menu" id="themeMenu" aria-hidden="true">
                  <button class="theme-option" data-theme="light">Light</button>
                  <button class="theme-option" data-theme="dark">Dark</button>
                  <button class="theme-option" data-theme="device">Device</button>
                </div>
              </div>
              <form method="post" class="beta-toggle-inline">
                <label class="beta-switch">
                  <input type="checkbox" name="toggle_beta" value="1" <?php echo !empty($_SESSION['beta_payment_enabled']) ? 'checked' : ''; ?> onchange="this.form.submit()">
                  <span class="slider"></span>
                </label>
                <span class="beta-label">Beta Pay</span>
                <input type="hidden" name="beta_toggle_submit" value="1">
              </form>
            </div>
              <a href="notifications.php" title="Notifications" class="nav-icon-link">
                <i class="bi bi-bell"></i>
                <span><?php echo $notif_count > 0 ? "($notif_count)" : ""; ?></span>
              </a>
              <a href="carts_users.php" title="Cart" class="nav-icon-link">
                <i class="bi bi-cart"></i>
                <span>(<?php echo (int)$cart_count; ?>)</span>
              </a>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
              <a href="<?php echo $profile_page; ?>">
                <?php if(!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                  <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                  <img src="Uploads/Logo.png" alt="Profile" class="profile-icon">
                <?php endif; ?>
              </a>
            <?php else: ?>
              <a href="login_users.php"><div class="nonuser-text">Login</div></a>
              <a href="signup_users.php"><div class="nonuser-text">Signup</div></a>
              <a href="login_users.php"><div class="profile-icon">👤</div></a>
            <?php endif; ?>
            <button class="hamburger">☰</button>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <li><a href="carts_users.php">Cart (<span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>)</a></li>
            <li><a href="order_status.php">My Purchases</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
              <?php $user_role = $_SESSION['role'] ?? 'buyer'; ?>
              <?php if($user_role === 'seller' || $user_role === 'admin'): ?>
                <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
              <?php else: ?>
                <li><a href="become_seller.php">Become Seller</a></li>
              <?php endif; ?>
              <li><a href="<?php echo $profile_page; ?>">Profile</a></li>
              <li><a href="logout.php">Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="container">
        <div class="header">
            <h1>My Orders</h1>
            <p>Track your order status and history</p>
            <?php if (!empty($_SESSION['beta_payment_enabled'])): ?>
            <div class="beta-banner">
                <strong>Beta Payment Enabled:</strong> You can mark pending orders as paid for testing. Sellers will be notified that this is a beta confirmation.
            </div>
            <?php endif; ?>
        </div>

        <?php if ($payment_message !== null): ?>
            <div class="notification <?php echo $payment_success ? 'success' : 'error'; ?>">
                <?php echo $payment_success ? '💳' : '⚠️'; ?>
                <?php echo htmlspecialchars($payment_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($cancellation_message)): ?>
            <div class="notification <?php echo $cancellation_success ? 'success' : 'error'; ?>">
                <?php echo $cancellation_success ? '✅' : '❌'; ?> 
                <?php echo htmlspecialchars($cancellation_message); ?>
            </div>
        <?php endif; ?>

        <!-- Status Filter -->
        <div class="filter-buttons">
            <a href="order_status.php?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                All Orders
            </a>
            <a href="order_status.php?status=pending" class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                Pending
            </a>
            <a href="order_status.php?status=confirmed" class="filter-btn <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                Confirmed
            </a>
            <a href="order_status.php?status=shipped" class="filter-btn <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">
                Shipped
            </a>
            <a href="order_status.php?status=delivered" class="filter-btn <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">
                Delivered
            </a>
            <a href="order_status.php?status=cancelled" class="filter-btn <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                Cancelled
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <h3>No orders found</h3>
                <p>No orders process in this area</p>
                <a href="shop.php" class="shop-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                    // Clear any pending results before order items query
                    clearPendingResults($conn);
                    
                    // Get order items with their individual statuses
                    $items_query = "SELECT oi.*, p.name, p.image, p.seller_id,
                                           u.seller_name
                                   FROM order_items oi 
                                   LEFT JOIN products p ON oi.product_id = p.id 
                                   LEFT JOIN users u ON p.seller_id = u.id
                                   WHERE oi.order_id = ?";
                    $items_stmt = $conn->prepare($items_query);
                    $items_stmt->bind_param("i", $order['id']);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();
                    $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
                    $items_result->free();
                    $items_stmt->close();
                    
                    // Clear any pending results after order items query
                    clearPendingResults($conn);
                    
                    // Determine overall order status based on item statuses
                    $overall_status = getOverallOrderStatus($order_items);
                    $can_cancel = canCancelOrder($order_items);
                    ?>
                    
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Order #<?php echo $order['id']; ?></h3>
                                <p class="order-date">Placed on <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                            </div>
                           
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-row">
                                <span class="detail-label">Items:</span>
                                <span class="detail-value"><?php echo $order['item_count']; ?> item(s)</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Amount:</span>
                                <span class="detail-value">$<?php echo number_format($order['total_price'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Payment Method:</span>
                                <span class="detail-value">
                                    <?php echo ucfirst($order['payment_method']); ?>
                                    <?php if (!empty($order['paid_at'])): ?>
                                        <span class="beta-pill beta-success">Paid (Beta)</span>
                                    <?php else: ?>
                                        <span class="beta-pill beta-pending">Awaiting payment</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($order['paid_at'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Paid on:</span>
                                    <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($order['paid_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Shipping Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['shipping_name']); ?>, <?php echo htmlspecialchars($order['shipping_address']); ?></span>
                            </div>
                            <?php if (!empty($order['voucher_code'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Voucher Used:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($order['voucher_code']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Order Items -->
                        <div class="order-items">
                            <h4>Order Items:</h4>
                            <?php foreach ($order_items as $item): ?>
                                <?php
                                    // resolve product image URL robustly
                                    $imgSrc = resolveProductImageUrl($item);
                                ?>
                                <div class="order-item">
                                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-thumb" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<?php echo rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90"><rect width="100%" height="100%" fill="#222"/><text x="50%" y="50%" fill="#888" font-size="12" dominant-baseline="middle" text-anchor="middle">No image</text></svg>'); ?>';">
                                    <div class="item-info">
                                         <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                         <div class="item-details">
                                             <span class="item-quantity">Qty: <?php echo $item['quantity']; ?></span> • 
                                             <span class="item-price">$<?php echo number_format($item['price'], 2); ?> each</span>
                                         </div>
                                        <?php if (!empty($item['seller_name'])): ?>
                                            <div class="seller-info">
                                                Sold by: <?php echo htmlspecialchars($item['seller_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['spec_combination'])): ?>
                                            <span class="item-specs">
                                                <?php 
                                                $specs = json_decode($item['spec_combination'], true);
                                                if ($specs && is_array($specs)) {
                                                    echo "Specs: " . implode(", ", array_map(fn($k, $v) => "$k: $v", array_keys($specs), $specs));
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Tracking Information -->
                                        <?php if (!empty($item['tracking_number'])): ?>
                                            <div class="tracking-info">
                                                <strong>Tracking Number:</strong>
                                                <span class="tracking-number"><?php echo htmlspecialchars($item['tracking_number']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                <?php if (!empty($_SESSION['beta_payment_enabled'])): ?>
                                    <form method="POST" class="inline-beta-form">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" name="mark_paid" class="inline-beta-btn" onclick="return confirm('Simulate payment for order #<?php echo $order['id']; ?>?');">
                                            Beta Pay this Item
                                        </button>
                                    </form>
                                <?php endif; ?>
                                    </div>
                                    <div class="item-status">
                                        <span class="status-badge status-<?php echo $item['status']; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="order-actions">
                            <?php if (!empty($_SESSION['beta_payment_enabled'])): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="mark_paid" class="btn btn-beta" onclick="return confirm('Mark order #<?php echo $order['id']; ?> as paid? Sellers will be notified that this is a beta payment.');">
                                        Payment Done (Beta)
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($can_cancel): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="cancel_order" class="btn btn-cancel" 
                                            onclick="return confirm('Are you sure you want to cancel order #<?php echo $order['id']; ?>? Sellers will be notified.')">
                                        Cancel Order
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($overall_status === 'delivered'): ?>
                                <button class="btn btn-review" onclick="leaveReview(<?php echo $order['id']; ?>)">
                                    Leave Review
                                </button>
                            <?php endif; ?>
                            
                            <a href="order_details.php?order_id=<?php echo $order['id']; ?>" class="btn btn-details">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="text-align:center; margin-top:10px; display:flex; gap:8px; justify-content:center;">
                    <button type="button" id="showLessOrdersBtn" class="btn btn-details" style="display:none;">Show less</button>
                    <button type="button" id="showMoreOrdersBtn" class="btn btn-review" style="display:none;">Show more</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Navbar interactions (match shop.php)
        document.addEventListener('DOMContentLoaded', function() {
            const themeDropdown = document.getElementById('themeDropdown');
            const themeMenu = document.getElementById('themeMenu');
            const themeBtn = document.getElementById('themeDropdownBtn');
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            let currentTheme = '<?php echo htmlspecialchars($theme); ?>';

            function applyTheme(theme) {
                let effective = theme;
                if (theme === 'device') {
                    effective = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', effective);
                if (themeIcon && themeText) {
                    if (theme === 'device') { themeIcon.className = 'bi theme-icon bi-laptop'; themeText.textContent = 'Device'; }
                    else if (theme === 'dark') { themeIcon.className = 'bi theme-icon bi-moon-fill'; themeText.textContent = 'Dark'; }
                    else { themeIcon.className = 'bi theme-icon bi-sun-fill'; themeText.textContent = 'Light'; }
                }
                fetch(`?theme=${theme}`, { method: 'GET' }).catch(() => {});
            }

            applyTheme(currentTheme);

            if (themeBtn && themeDropdown) {
                themeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    themeDropdown.classList.toggle('active');
                });
            }
            if (themeMenu) {
                themeMenu.addEventListener('click', (e) => {
                    const option = e.target.closest('.theme-option');
                    if (!option) return;
                    currentTheme = option.dataset.theme;
                    applyTheme(currentTheme);
                    themeDropdown.classList.remove('active');
                });
            }
            document.addEventListener('click', (e) => {
                if (themeDropdown && !themeDropdown.contains(e.target)) {
                    themeDropdown.classList.remove('active');
                }
            });
            if (currentTheme === 'device') {
                const mq = window.matchMedia('(prefers-color-scheme: dark)');
                mq.addEventListener('change', () => { if (currentTheme === 'device') applyTheme('device'); });
            }

            const hamburger = document.querySelector('.hamburger');
            const menu = document.getElementById('menu');
            if (hamburger && menu) {
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    menu.classList.toggle('show');
                });
                document.addEventListener('click', function(e) {
                    if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });
                menu.querySelectorAll('a').forEach(item => {
                    item.addEventListener('click', () => menu.classList.remove('show'));
                });
            }
            // Show-more pagination for order cards (5 at a time)
            const orderCards = document.querySelectorAll('.orders-list .order-card');
            const showMoreOrdersBtn = document.getElementById('showMoreOrdersBtn');
            const showLessOrdersBtn = document.getElementById('showLessOrdersBtn');
            const PAGE_SIZE = 5;
            let visibleOrders = 0;

            function updateOrdersVisibility() {
                orderCards.forEach((el, idx) => {
                    el.style.display = idx < visibleOrders ? 'block' : 'none';
                });
                if (showMoreOrdersBtn) {
                    showMoreOrdersBtn.style.display = visibleOrders < orderCards.length ? 'inline-block' : 'none';
                }
                if (showLessOrdersBtn) {
                    showLessOrdersBtn.style.display = visibleOrders > PAGE_SIZE ? 'inline-block' : 'none';
                }
            }

            if (orderCards.length > 0) {
                visibleOrders = Math.min(PAGE_SIZE, orderCards.length);
                updateOrdersVisibility();
            }

            if (showMoreOrdersBtn) {
                showMoreOrdersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    visibleOrders = Math.min(visibleOrders + PAGE_SIZE, orderCards.length);
                    updateOrdersVisibility();
                });
            }
            if (showLessOrdersBtn) {
                showLessOrdersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    visibleOrders = Math.max(PAGE_SIZE, visibleOrders - PAGE_SIZE);
                    updateOrdersVisibility();
                    // Optionally scroll to the top of the list for context
                    const list = document.querySelector('.orders-list');
                    if (list) { list.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                });
            }
        });
        function leaveReview(orderId) {
            window.location.href = 'leave_review.php?order_id=' + orderId;
        }

        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>
