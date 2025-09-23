<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }

$orderId = intval($_GET['order_id'] ?? ($_SESSION['pending_payment_order_id'] ?? 0));
$method = $_GET['method'] ?? ($_SESSION['pending_payment_method'] ?? '');

if ($orderId <= 0 || !in_array($method, ['gcash','maya'])) {
    header("Location: shop.php");
    exit();
}

// Verify order belongs to user and is pending
$stmt = $conn->prepare("SELECT id, total, status FROM orders WHERE id = ? AND user_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows !== 1) { header("Location: shop.php"); exit(); }
    $order = $res->fetch_assoc();
    if ($order['status'] !== 'pending') { header("Location: shop.php"); exit(); }
}

// In real world: create payment session with PSP and redirect to checkout URL
// Here: simulate by redirecting to a mock payment page
$_SESSION['pending_payment_order_id'] = $orderId;
$_SESSION['pending_payment_method'] = $method;
$_SESSION['pending_payment_total'] = $order['total'];

header("Location: payment_mock.php");
exit();
?>


