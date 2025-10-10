<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { 
    header("Location: login_users.php"); 
    exit(); 
}
$userId = (int)$_SESSION['user_id'];

// Function to clear all pending result sets
function clearPendingResults($conn) {
    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/order_status.css">
    <style>
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-card {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #44D62C;
        }
        
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
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: #2a2a2a;
            border-radius: 6px;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
        }
        
        .item-details {
            font-size: 0.9em;
            color: #ccc;
        }
        
        .item-specs {
            display: block;
            font-size: 0.8em;
            color: #888;
            margin-top: 2px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .order-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
            display: flex;
            gap: 10px;
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
            background: #6c757d;
            color: white;
        }
        
        .tracking-info {
            background: #2a2a2a;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            border-left: 3px solid #44D62C;
        }
        
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
    <div class="container">
        <div class="header">
            <h1>My Orders</h1>
            <p>Track your order status and history</p>
        </div>

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
                            <div class="order-status">
                                <span class="status-badge status-<?php echo $overall_status; ?>">
                                    <?php echo ucfirst($overall_status); ?>
                                </span>
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
                                <span class="detail-value"><?php echo ucfirst($order['payment_method']); ?></span>
                            </div>
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
                                <div class="order-item">
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
            </div>
        <?php endif; ?>
    </div>

    <script>
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
