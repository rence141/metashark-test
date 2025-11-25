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
        $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total_revenue FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL)");
        $totals['total_revenue'] = (float)$q->fetch_assoc()['total_revenue'];
        $q = $conn->query("SELECT IFNULL(SUM(stock),0) as total_stock FROM products");
        $totals['total_stock'] = (int)$q->fetch_assoc()['total_stock'];
        $q = $conn->query("SELECT COUNT(*) as sellers FROM users WHERE role='seller'");
        $totals['total_sellers'] = (int)$q->fetch_assoc()['sellers'];
        $q = $conn->query("SELECT COUNT(*) as orders FROM orders");
        $totals['total_orders'] = (int)$q->fetch_assoc()['orders'];
        echo json_encode($totals);
        break;

    case 'monthly_revenue':
        $rows = [];
        $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, SUM(total_price) as amt FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL) GROUP BY ym ORDER BY ym ASC LIMIT 12");
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'top_products':
        $rows = [];
        $stmt = $conn->prepare("SELECT p.id, p.name, SUM(oi.quantity) as total_qty FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) GROUP BY p.id ORDER BY total_qty DESC LIMIT 5");
        if($stmt) { $stmt->execute(); $res = $stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r; }
        echo json_encode($rows);
        break;

    case 'top_sellers':
        $rows = [];
        $stmt = $conn->prepare("SELECT u.id, COALESCE(NULLIF(u.seller_name,''), u.fullname) as seller, SUM(o.total_price) as revenue FROM orders o JOIN users u ON o.seller_id = u.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) GROUP BY u.id ORDER BY revenue DESC LIMIT 5");
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
        $q = $conn->query("SELECT id, fullname, email, role, status FROM users ORDER BY id DESC LIMIT 200");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'recent_orders':
        $status = $_GET['status'] ?? 'all';
        $allowed = ['pending','confirmed','shipped','delivered','cancelled','received'];
        $sql = "SELECT o.id, o.total_price, o.status, o.payment_method, o.created_at, u.fullname as buyer, u.email as buyer_email
                FROM orders o
                LEFT JOIN users u ON u.id = o.buyer_id";
        $params = [];
        $types = '';
        if ($status && $status !== 'all' && in_array($status, $allowed, true)) {
            $sql .= " WHERE o.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        $sql .= " ORDER BY o.created_at DESC LIMIT 15";
        $stmt = $conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($rows);
        break;

    case 'seller_overview':
        $sql = "SELECT 
                    u.id,
                    COALESCE(NULLIF(u.seller_name,''), u.fullname) as seller_name,
                    u.email,
                    IFNULL(u.seller_rating, 0) as rating,
                    u.is_active_seller,
                    COUNT(DISTINCT o.id) as total_orders,
                    IFNULL(SUM(o.total_price),0) as revenue
                FROM users u
                LEFT JOIN orders o ON o.seller_id = u.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                WHERE u.role = 'seller'
                GROUP BY u.id
                ORDER BY revenue DESC
                LIMIT 10";
        $res = $conn->query($sql);
        if ($res) {
            echo json_encode($res->fetch_all(MYSQLI_ASSOC));
        } else {
            echo json_encode(['error' => 'seller_overview_query_failed']);
        }
        break;

    case 'unread_count':
        $user_id = $_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE is_read = 0 AND user_id IS NULL");
        if (!$stmt) {
            echo json_encode(['error' => 'notifications_table_missing']);
            break;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $countRow = $res ? $res->fetch_assoc() : ['cnt' => 0];
        echo json_encode(['count' => (int)($countRow['cnt'] ?? 0)]);
        break;

    default:
        echo json_encode(['error'=>'unknown_action']);
        break;
}
