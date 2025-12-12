<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Set a debug mode flag for additional logging
const DEBUG_MODE = true; // Change to false for production

// Ensure admin is logged in for security
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); 
    exit;
}

header('Content-Type: application/json');
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$action = $_GET['action'] ?? '';
$data = []; // Initialize $data for all cases

if (DEBUG_MODE) {
    error_log("FETCH_DATA_DEBUG: Received action: " . $action . " from IP: " . $_SERVER['REMOTE_ADDR']);
}

// Ensure database connection is established
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error, 'action' => $action]);
    error_log("FETCH_DATA_ERROR: Database connection failed for action " . $action . ": " . $conn->connect_error);
    exit;
}

switch ($action) {
    // --- DASHBOARD STATS ---
    case 'dashboard_stats':
        $stats = [
            'total_products' => 0,
            'total_stock' => 0,
            'total_revenue' => 0,
            'total_orders' => 0,
            'total_sellers' => 0
        ];

        // Total Products & Stock
        $stmt = $conn->prepare("SELECT COUNT(id) AS total_products, SUM(stock_quantity) AS total_stock FROM products WHERE is_deleted = 0");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (dashboard_stats - products): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (dashboard_stats - products) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (dashboard_stats - products): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (dashboard_stats - products) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result()->fetch_assoc();
        $stats['total_products'] = (int)($result['total_products'] ?? 0);
        $stats['total_stock'] = (int)($result['total_stock'] ?? 0);
        $stmt->close();

        // Total Revenue & Orders (Assuming 'status' field in orders table)
        $stmt = $conn->prepare("SELECT SUM(total_amount) AS total_revenue, COUNT(id) AS total_orders FROM orders WHERE status = 'completed'");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (dashboard_stats - orders): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (dashboard_stats - orders) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (dashboard_stats - orders): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (dashboard_stats - orders) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result()->fetch_assoc();
        $stats['total_revenue'] = (float)($result['total_revenue'] ?? 0);
        $stats['total_orders'] = (int)($result['total_orders'] ?? 0);
        $stmt->close();

        // Total Active Sellers (Querying 'users' table directly)
        $stmt = $conn->prepare("SELECT COUNT(id) AS total_sellers FROM users WHERE role = 'seller' AND is_active_seller = 1 AND is_deleted = 0 AND is_suspended = 0");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (dashboard_stats - active sellers from users): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (dashboard_stats - active sellers from users) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (dashboard_stats - active sellers from users): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (dashboard_stats - active sellers from users) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result()->fetch_assoc();
        $stats['total_sellers'] = (int)($result['total_sellers'] ?? 0);
        $stmt->close();

        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned stats: " . json_encode($stats));
        }
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    // --- MONTHLY REVENUE (Line Chart) ---
    case 'monthly_revenue':
        $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(total_amount) AS amt 
                                FROM orders 
                                WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                                GROUP BY ym ORDER BY ym ASC");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (monthly_revenue): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (monthly_revenue) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (monthly_revenue): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (monthly_revenue) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned " . count($data) . " items.");
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // --- DAILY REVENUE (Sparkline/Trend) ---
    case 'daily_revenue':
        $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS date, SUM(total_amount) AS amt 
                                FROM orders 
                                WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                GROUP BY date ORDER BY date ASC");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (daily_revenue): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (daily_revenue) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (daily_revenue): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (daily_revenue) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned " . count($data) . " items.");
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // --- ORDER STATUS (Pie Chart) ---
    case 'orders_summary':
        $stmt = $conn->prepare("SELECT status, COUNT(id) AS cnt FROM orders GROUP BY status");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (orders_summary): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (orders_summary) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (orders_summary): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (orders_summary) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned " . count($data) . " items.");
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // --- TOP PRODUCTS (Bar Chart) ---
    case 'top_products':
        $stmt = $conn->prepare("SELECT 
                                    p.id, 
                                    p.name, 
                                    p.stock_quantity, 
                                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name ASC SEPARATOR ', ') AS categories,
                                    SUM(oi.quantity) AS total_qty
                                FROM order_items oi
                                JOIN products p ON oi.product_id = p.id
                                LEFT JOIN product_categories pc ON p.id = pc.product_id
                                LEFT JOIN categories c ON pc.category_id = c.id
                                GROUP BY p.id, p.name, p.stock_quantity
                                ORDER BY total_qty DESC
                                LIMIT 5"); // Limit to top 5 products
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (top_products): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (top_products) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (top_products): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (top_products) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned " . count($data) . " items.");
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // --- SALES BY COUNTRY (Geo Chart) ---
    case 'sales_by_country':
        $stmt = $conn->prepare("SELECT billing_country AS country, SUM(total_amount) AS value 
                                FROM orders 
                                WHERE status = 'completed'
                                GROUP BY billing_country 
                                ORDER BY value DESC
                                LIMIT 5"); // Top 5 countries by sales volume
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (sales_by_country): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (sales_by_country) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (sales_by_country): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (sales_by_country) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned " . count($data) . " items.");
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    // --- CATEGORY DISTRIBUTION (Bar Chart) ---
    case 'category_distribution':
        $stmt = $conn->prepare("SELECT c.name AS category, COUNT(DISTINCT p.id) AS count
                                FROM categories c
                                JOIN product_categories pc ON c.id = pc.category_id
                                JOIN products p ON pc.product_id = p.id
                                WHERE p.is_deleted = 0
                                GROUP BY c.name
                                ORDER BY count DESC");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Prepare Error (category_distribution): ' . $conn->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Prepare Error (category_distribution) for action " . $action . ": " . $conn->error);
            exit;
        }
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'SQL Execute Error (category_distribution): ' . $stmt->error, 'action' => $action]);
            error_log("FETCH_DATA_ERROR: SQL Execute Error (category_distribution) for action " . $action . ": " . $stmt->error);
            $stmt->close();
            exit;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Action " . $action . " returned " . count($data) . " items.");
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action', 'action' => $action]);
        if (DEBUG_MODE) {
            error_log("FETCH_DATA_DEBUG: Invalid action received: " . $action);
        }
        break;
}

$conn->close();
?>