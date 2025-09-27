<?php
session_start();

// Security: Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

// Enforce verified sellers only
$role = $_SESSION['role'] ?? '';
if ($role !== 'seller' && $role !== 'admin') {
    header("Location: shop.php");
    exit();
}

include("db.php");
include_once("email.php");
$seller_id = $_SESSION['user_id'];

// Handle status update submission
$update_success = false;
$update_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING) ?? '';

    // Validate status
    $valid_statuses = ['processing', 'shipped', 'delivered'];
    if ($order_id > 0 && in_array($new_status, $valid_statuses)) {
        try {
            // Start transaction
            $conn->begin_transaction();

            // Fetch order details to verify seller ownership and get buyer info
            $order_query = "SELECT o.id, o.product_id, o.quantity, o.total_price AS price, o.status, o.buyer_id, u.fullname AS shipping_name, u.email
                            FROM orders o
                            JOIN products p ON o.product_id = p.id
                            JOIN users u ON o.buyer_id = u.id
                            WHERE o.id = ? AND o.seller_id = ?";
            $order_stmt = $conn->prepare($order_query);
            $order_stmt->bind_param("ii", $order_id, $seller_id);
            $order_stmt->execute();
            $order_result = $order_stmt->get_result();
            
            if ($order_result->num_rows > 0) {
                $order = $order_result->fetch_assoc();
                $old_status = $order['status'];

                // Update status only if changed
                if ($old_status !== $new_status) {
                    $update_query = "UPDATE orders SET status = ? WHERE id = ? AND seller_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("sii", $new_status, $order_id, $seller_id);
                    $update_stmt->execute();

                    if ($conn->affected_rows > 0) {
                        // Fetch product name for email
                        $prod_query = "SELECT name FROM products WHERE id = ?";
                        $prod_stmt = $conn->prepare($prod_query);
                        $prod_stmt->bind_param("i", $order['product_id']);
                        $prod_stmt->execute();
                        $prod_result = $prod_stmt->get_result();
                        $product_name = $prod_result->num_rows > 0 ? $prod_result->fetch_assoc()['name'] : 'Item';

                        // Send email to buyer if email exists
                        $buyer_email = $order['email'];
                        if (!empty($buyer_email)) {
                            $status_messages = [
                                'processing' => 'Your order item is being processed.',
                                'shipped' => 'Your order item has been shipped!',
                                'delivered' => 'Your order item has been delivered.'
                            ];
                            $status_title = ucfirst(str_replace('_', ' ', $new_status));

                            $subject = "Order Update: {$status_title} - Order #{$order_id}";
                            $body = "Hello {$order['shipping_name']},\n\n";
                            $body .= "Great news! The status of your item '{$product_name}' (x{$order['quantity']}) has been updated to {$status_title}.\n";
                            $body .= $status_messages[$new_status] . "\n\n";
                            $body .= "Order ID: #{$order_id}\n";
                            $body .= "Total: $" . number_format($order['price'], 2) . "\n\n";
                            $body .= "If you have any questions, please contact the seller.\n\n";
                            $body .= "Best regards,\nMeta Shark Team";

                            @send_email($buyer_email, $subject, $body);
                        }

                        $update_success = true;
                        $update_message = "Status updated successfully to '{$status_title}' and notification sent to customer.";
                    } else {
                        $update_message = "No changes made - status was already up to date.";
                    }
                } else {
                    $update_message = "No changes made - status was already '{$new_status}'.";
                }
            } else {
                $update_message = "Order not found or you do not own this item.";
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Status update failed: " . $e->getMessage());
            $update_message = "Error updating status: " . $e->getMessage();
        }
    } else {
        $update_message = "Invalid order or status.";
    }
}

// Fetch seller's orders with details (assuming flat orders table where each row is an order item)
$sql = "SELECT o.id AS order_id, u.fullname AS shipping_name, o.created_at, o.quantity, o.total_price AS price, o.status, p.name AS product_name, p.image
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON o.buyer_id = u.id
        WHERE o.seller_id = ?
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$order_items = $result->fetch_all(MYSQLI_ASSOC);

// No grouping needed since each row is a separate order item
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Order Management - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/checkout_users.css"> <!-- Reuse for simplicity; customize as needed -->
    <?php include("theme_toggle.php"); ?>
    <style>
        .notification { 
            transition: opacity 0.3s ease, transform 0.3s ease; 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1000; 
            padding: 15px; 
            border-radius: 5px;
            max-width: 300px;
        }
        .notification.show { opacity: 1; transform: translateY(0); }
        .notification.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .notification.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .order-list { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .order-card { background: #f8f9fa; border-radius: 8px; margin-bottom: 20px; padding: 20px; }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .item-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
        .item-details { flex: 1; }
        .item-image { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
        .status-select { padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .update-btn { background: #007bff; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .update-btn:hover { background: #0056b3; }
        .status-badge { padding: 4px 8px; border-radius: 4px; color: white; font-size: 0.8em; }
        .status-processing { background: #ffc107; }
        .status-shipped { background: #17a2b8; }
        .status-delivered { background: #28a745; }
        .status-pending { background: #6c757d; }
        @media (max-width: 768px) { .item-row { flex-direction: column; align-items: flex-start; } }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Notification auto-dismiss
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            });
        });
    </script>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <?php
            $profile_page = 'seller_profile.php';
            $profile_query = "SELECT profile_image FROM users WHERE id = ?";
            $profile_stmt = $conn->prepare($profile_query);
            $profile_stmt->bind_param("i", $seller_id);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            $current_profile = $profile_result->fetch_assoc();
            $current_profile_image = $current_profile['profile_image'] ?? null;
            ?>
            <a href="<?php echo $profile_page; ?>">
                <?php if (!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                    <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="Uploads/default-avatar.svg" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
            <button class="hamburger">☰</button>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <li><a href="carts_users.php">Cart</a></li>
            <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
            <li><a href="<?php echo $profile_page; ?>">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <?php if (!empty($update_message)): ?>
        <div class="notification <?php echo $update_success ? 'success' : 'error'; ?> show">
            <?php echo $update_success ? '✅' : '❌'; ?> <?php echo htmlspecialchars($update_message); ?>
        </div>
    <?php endif; ?>

    <div class="order-list">
        <h1>Seller Order Management</h1>
        <p>Update the status of your order items below. Customers will be notified via email automatically.</p>
        <?php if (empty($order_items)): ?>
            <p>No orders found. <a href="seller_dashboard.php">Back to Dashboard</a></p>
        <?php else: ?>
            <?php foreach ($order_items as $item): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <strong>Order #<?php echo $item['order_id']; ?></strong> - <?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?>
                            <br><small>Shipping to: <?php echo htmlspecialchars($item['shipping_name']); ?></small>
                        </div>
                    </div>
                    <div class="item-row">
                        <div class="item-details">
                            <?php if (!empty($item['image'])): ?>
                                <img src="Uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                            <small>Qty: <?php echo $item['quantity']; ?> | Price: $<?php echo number_format($item['price'], 2); ?></small>
                        </div>
                        <div style="text-align: right;">
                            <span class="status-badge status-<?php echo $item['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                            </span>
                            <br><br>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="order_id" value="<?php echo $item['order_id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="new_status" class="status-select">
                                    <option value="pending" <?php echo $item['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $item['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $item['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $item['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                </select>
                                <br><br>
                                <button type="submit" class="update-btn">Update Status</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>