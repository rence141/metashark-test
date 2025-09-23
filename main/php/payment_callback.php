<?php
session_start();
include("db.php");
include_once("email.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }

$orderId = intval($_POST['order_id'] ?? 0);
$status = $_POST['status'] ?? '';

// Validate order and ownership
$stmt = $conn->prepare("SELECT id, user_id, status FROM orders WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows !== 1) { header("Location: shop.php"); exit(); }
    $order = $res->fetch_assoc();
    if ((int)$order['user_id'] !== (int)$_SESSION['user_id']) { header("Location: shop.php"); exit(); }
    if ($order['status'] !== 'pending') { header("Location: shop.php"); exit(); }
}

if ($status === 'success') {
    // Mark paid only if stock is still sufficient; otherwise fail
    $conn->begin_transaction();

    // Check stock based on order_items and decrement
    $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    if ($items) {
        $items->bind_param("i", $orderId);
        $items->execute();
        $rs = $items->get_result();
        $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        while ($row = $rs->fetch_assoc()) {
            $q = (int)$row['quantity'];
            $pid = (int)$row['product_id'];
            if ($stockStmt) {
                $stockStmt->bind_param("iii", $q, $pid, $q);
                $stockStmt->execute();
                if ($conn->affected_rows === 0) {
                    $conn->rollback();
                    header("Location: checkout_users.php?payment_failed=1&reason=stock");
                    exit();
                }
            }
        }
    }

    // Mark order as paid
    $upd = $conn->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = ?");
    if ($upd) { $upd->bind_param("i", $orderId); $upd->execute(); }

    // Remove only ordered items from cart (supports single-item checkout)
    $clear = $conn->prepare("DELETE c FROM cart c JOIN order_items oi ON oi.product_id = c.product_id WHERE c.user_id = ? AND oi.order_id = ?");
    if ($clear) { $clear->bind_param("ii", $_SESSION['user_id'], $orderId); $clear->execute(); }

    $conn->commit();

    // Prepare notifications (buyer and sellers)
    $buyerEmail = '';
    $buyerName = '';
    $bu = $conn->prepare("SELECT u.email, u.fullname FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    if ($bu) { $bu->bind_param("i", $orderId); $bu->execute(); $br = $bu->get_result(); if ($br->num_rows) { $b = $br->fetch_assoc(); $buyerEmail = $b['email'] ?? ''; $buyerName = $b['fullname'] ?? 'Customer'; } }

    // Load items with seller info
    $itemsWithSellers = [];
    $its = $conn->prepare("SELECT oi.product_id, oi.quantity, oi.price, p.name AS product_name, s.email AS seller_email, COALESCE(s.seller_name, s.fullname) AS seller_name
                           FROM order_items oi
                           JOIN products p ON p.id = oi.product_id
                           JOIN users s ON s.id = p.seller_id
                           WHERE oi.order_id = ?");
    if ($its) { $its->bind_param("i", $orderId); $its->execute(); $ir = $its->get_result(); while ($row = $ir->fetch_assoc()) { $itemsWithSellers[] = $row; } }

    // Send buyer email
    if ($buyerEmail) {
        $body = "Hi $buyerName,\n\nYour order #$orderId has been paid successfully.\n\nItems:\n";
        foreach ($itemsWithSellers as $it) { $body .= "- {$it['product_name']} x{$it['quantity']}\n"; }
        $body .= "\nThank you for shopping with us!";
        @send_email($buyerEmail, 'Order confirmation #' . $orderId, $body);
    }

    // Group items per seller and notify each
    $bySeller = [];
    foreach ($itemsWithSellers as $it) {
        $em = $it['seller_email'] ?? '';
        if (!$em) { continue; }
        if (!isset($bySeller[$em])) { $bySeller[$em] = [ 'name' => ($it['seller_name'] ?? 'Seller'), 'items' => [] ]; }
        $bySeller[$em]['items'][] = $it;
    }
    foreach ($bySeller as $em => $data) {
        $body = "Hello {$data['name']},\n\nYou have a new paid order #$orderId.\n\nItems:\n";
        foreach ($data['items'] as $it) { $body .= "- {$it['product_name']} x{$it['quantity']}\n"; }
        $body .= "\nPlease prepare for fulfillment.";
        @send_email($em, 'New order #' . $orderId, $body);
    }

    // Cleanup session
    unset($_SESSION['pending_payment_order_id'], $_SESSION['pending_payment_total'], $_SESSION['pending_payment_method']);

    header("Location: checkout_users.php?success=1");
    exit();
}

// Failed or canceled
unset($_SESSION['pending_payment_order_id'], $_SESSION['pending_payment_total'], $_SESSION['pending_payment_method']);
header("Location: checkout_users.php?payment_failed=1");
exit();
?>


