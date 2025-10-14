<?php
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['admin_id'])) { echo json_encode(['error'=>'Not authenticated']); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'dashboard_stats':
        // totals
        $totals = [];
        $q = $conn->query("SELECT COUNT(*) as total_products FROM products");
        $totals['total_products'] = (int)$q->fetch_assoc()['total_products'];
        $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total_revenue FROM orders WHERE status IN ('confirmed','shipped','received')");
        $totals['total_revenue'] = (float)$q->fetch_assoc()['total_revenue'];
        $q = $conn->query("SELECT IFNULL(SUM(stock),0) as total_stock FROM products");
        $totals['total_stock'] = (int)$q->fetch_assoc()['total_stock'];
        $q = $conn->query("SELECT COUNT(*) as sellers FROM users WHERE user_type='seller'");
        $totals['total_sellers'] = (int)$q->fetch_assoc()['sellers'];
        $q = $conn->query("SELECT COUNT(*) as orders FROM orders");
        $totals['total_orders'] = (int)$q->fetch_assoc()['orders'];
        echo json_encode($totals);
        break;

    case 'monthly_revenue':
        $rows = [];
        $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, SUM(total_price) as amt FROM orders WHERE status IN ('confirmed','shipped','received') GROUP BY ym ORDER BY ym ASC LIMIT 12");
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'top_products':
        $rows = [];
        $stmt = $conn->prepare("SELECT p.id, p.name, SUM(oi.quantity) as total_qty FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE o.status IN ('confirmed','shipped','received') GROUP BY p.id ORDER BY total_qty DESC LIMIT 5");
        if($stmt) { $stmt->execute(); $res = $stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r; }
        echo json_encode($rows);
        break;

    case 'top_sellers':
        $rows = [];
        $stmt = $conn->prepare("SELECT u.id, u.fullname as seller, SUM(o.total_price) as revenue FROM orders o JOIN users u ON o.seller_id = u.id WHERE o.status IN ('confirmed','shipped','received') GROUP BY u.id ORDER BY revenue DESC LIMIT 5");
        if($stmt){ $stmt->execute(); $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r; }
        echo json_encode($rows);
        break;

    case 'low_stock':
        $rows = [];
        $stmt = $conn->prepare("SELECT id,name,stock FROM products ORDER BY stock ASC LIMIT 20");
        $stmt->execute();
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode($rows);
        break;

    case 'orders_summary':
        $rows=[];
        $stmt=$conn->prepare("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
        $stmt->execute(); $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode($rows);
        break;

    case 'users_list':
        $q = $conn->query("SELECT id, name, email, user_type, status FROM users ORDER BY id DESC LIMIT 200");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'unread_count':
        $user_id = $_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE is_read = 0 AND user_id IS NULL"); // admin-wide
        $stmt->execute(); $res = $stmt->get_result()->fetch_assoc();
        echo json_encode(['count' => (int)$res['cnt']]);
        break;

    default:
        echo json_encode(['error'=>'unknown_action']);
        break;
}
