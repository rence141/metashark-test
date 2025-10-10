<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { 
    header("Location: login_users.php"); 
    exit(); 
}
$userId = (int)$_SESSION['user_id'];

// Validate order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: order_status.php");
    exit();
}
$orderId = (int)$_GET['order_id'];

// Fetch order details
$order_query = "SELECT o.*, 
                       COUNT(oi.id) as item_count,
                       MIN(oi.status) as overall_status,
                       GROUP_CONCAT(DISTINCT oi.status) as all_statuses
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                WHERE o.id = ? AND o.buyer_id = ?
                GROUP BY o.id";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("ii", $orderId, $userId);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    header("Location: order_status.php");
    exit();
}

// Fetch order items with details
$items_query = "SELECT oi.*, p.name, p.image, p.seller_id, u.seller_name
                FROM order_items oi 
                LEFT JOIN products p ON oi.product_id = p.id 
                LEFT JOIN users u ON p.seller_id = u.id
                WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $orderId);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Function to determine overall order status (reused from order_status.php)
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

$overall_status = getOverallOrderStatus($order_items);
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/order_status.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .header p {
            color: #888;
        }

        .order-card {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #44D62C;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .order-info h3 {
            margin: 0;
            font-size: 1.5em;
        }

        .order-date {
            color: #888;
            font-size: 0.9em;
        }

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

        .order-details {
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .detail-label {
            color: #888;
            font-weight: bold;
        }

        .detail-value {
            color: #fff;
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

        .seller-info {
            font-size: 0.8em;
            color: #888;
            margin-top: 2px;
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

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Details</h1>
            <p>Order #<?php echo $order['id']; ?> - Detailed Information</p>
        </div>

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

            <div class="order-actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #333;">
                <a href="order_status.php" class="btn btn-back">Back to Orders</a>
            </div>
        </div>
    </div>
</body>
</html>