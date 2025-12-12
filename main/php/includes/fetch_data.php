<?php
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// 1. Security Guard
if (!isset($_SESSION['admin_id'])) { 
    echo json_encode(['error' => 'Not authenticated']); 
    exit; 
}

header('Content-Type: application/json');
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$action = $_GET['action'] ?? '';

// Helper function for Country Normalization
// Defined outside switch to prevent redeclaration errors
function normalizeCountryName($country) {
    if (empty($country)) return null;
    $country = trim($country);
    $upper = strtoupper($country);
    
    // Map common codes/variations to full names
    $countryMap = [
        'US' => 'United States', 'USA' => 'United States', 'U.S.A.' => 'United States', 'U.S.' => 'United States',
        'UK' => 'United Kingdom', 'GB' => 'United Kingdom', 'GBR' => 'United Kingdom', 'U.K.' => 'United Kingdom',
        'UAE' => 'United Arab Emirates', 'AE' => 'United Arab Emirates',
        'PH' => 'Philippines', 'PHL' => 'Philippines',
        'CA' => 'Canada', 'CAN' => 'Canada',
        'AU' => 'Australia', 'AUS' => 'Australia',
        'DE' => 'Germany', 'DEU' => 'Germany',
        'FR' => 'France', 'FRA' => 'France',
        'IT' => 'Italy', 'ITA' => 'Italy',
        'ES' => 'Spain', 'ESP' => 'Spain',
        'NL' => 'Netherlands', 'NLD' => 'Netherlands',
        'BR' => 'Brazil', 'BRA' => 'Brazil',
        'MX' => 'Mexico', 'MEX' => 'Mexico',
        'IN' => 'India', 'IND' => 'India',
        'CN' => 'China', 'CHN' => 'China',
        'JP' => 'Japan', 'JPN' => 'Japan',
        'KR' => 'South Korea', 'KOR' => 'South Korea',
        'RU' => 'Russia', 'RUS' => 'Russia',
        'ZA' => 'South Africa', 'ZAF' => 'South Africa',
        'EG' => 'Egypt', 'EGY' => 'Egypt',
        'NG' => 'Nigeria', 'NGA' => 'Nigeria',
        'AR' => 'Argentina', 'ARG' => 'Argentina',
        'CL' => 'Chile', 'CHL' => 'Chile',
        'CO' => 'Colombia', 'COL' => 'Colombia',
        'PE' => 'Peru', 'PER' => 'Peru',
        'VE' => 'Venezuela', 'VEN' => 'Venezuela',
        'ID' => 'Indonesia', 'IDN' => 'Indonesia',
        'MY' => 'Malaysia', 'MYS' => 'Malaysia',
        'SG' => 'Singapore', 'SGP' => 'Singapore',
        'TH' => 'Thailand', 'THA' => 'Thailand',
        'VN' => 'Vietnam', 'VNM' => 'Vietnam',
        'TR' => 'Turkey', 'TUR' => 'Turkey',
        'SA' => 'Saudi Arabia', 'SAU' => 'Saudi Arabia',
        'IL' => 'Israel', 'ISR' => 'Israel',
        'PL' => 'Poland', 'POL' => 'Poland',
        'SE' => 'Sweden', 'SWE' => 'Sweden',
        'NO' => 'Norway', 'NOR' => 'Norway',
        'DK' => 'Denmark', 'DNK' => 'Denmark',
        'FI' => 'Finland', 'FIN' => 'Finland',
        'BE' => 'Belgium', 'BEL' => 'Belgium',
        'CH' => 'Switzerland', 'CHE' => 'Switzerland',
        'AT' => 'Austria', 'AUT' => 'Austria',
        'PT' => 'Portugal', 'PRT' => 'Portugal',
        'GR' => 'Greece', 'GRC' => 'Greece',
        'IE' => 'Ireland', 'IRL' => 'Ireland',
        'NZ' => 'New Zealand', 'NZL' => 'New Zealand',
        'CZ' => 'Czechia', 'CZE' => 'Czechia'
    ];
    
    if (isset($countryMap[$upper])) {
        return $countryMap[$upper];
    }
    
    // Check against a list of known valid full names (case-insensitive)
    $validNames = [
        'United States', 'United Kingdom', 'United Arab Emirates', 'Canada', 'Australia', 
        'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Brazil', 'Mexico', 'India', 
        'China', 'Japan', 'South Korea', 'Russia', 'South Africa', 'Egypt', 'Nigeria',
        'Argentina', 'Chile', 'Colombia', 'Peru', 'Venezuela', 'Indonesia', 'Malaysia', 
        'Singapore', 'Thailand', 'Vietnam', 'Turkey', 'Saudi Arabia', 'Israel', 'Poland', 
        'Sweden', 'Norway', 'Denmark', 'Finland', 'Belgium', 'Switzerland', 'Austria', 
        'Portugal', 'Greece', 'Ireland', 'New Zealand', 'Philippines', 'Czechia'
    ];
    
    foreach ($validNames as $valid) {
        if (strcasecmp($country, $valid) === 0) {
            return $valid;
        }
    }
    
    return $country;
}

switch ($action) {
    // --- DASHBOARD STATS ---
    case 'dashboard_stats':
        $totals = [];
        
        // 1. Total Products
        $q = $conn->query("SELECT COUNT(*) as total_products FROM products");
        $totals['total_products'] = (int)($q ? $q->fetch_assoc()['total_products'] : 0);
        
        // 2. Revenue: Try with status filter first, fallback to all orders
        // Note: Using 'total_price' based on your schema preference
        $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total_revenue FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL)");
        $revenue = $q ? (float)$q->fetch_assoc()['total_revenue'] : 0;
        
        if ($revenue == 0) {
            // Fallback: count all orders if status filter yields nothing (for testing/dev)
            $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total_revenue FROM orders");
            $revenue = $q ? (float)$q->fetch_assoc()['total_revenue'] : 0;
        }
        $totals['total_revenue'] = $revenue;
        
        // 3. Stock (prefers stock_quantity, falls back to stock)
        $q = $conn->query("SELECT IFNULL(SUM(COALESCE(stock_quantity, stock, 0)),0) as total_stock FROM products");
        $totals['total_stock'] = (int)($q ? $q->fetch_assoc()['total_stock'] : 0);
        
        // 4. Sellers
        $q = $conn->query("SELECT COUNT(*) as sellers FROM users WHERE role='seller'");
        $totals['total_sellers'] = (int)($q ? $q->fetch_assoc()['sellers'] : 0);
        
        // 5. Total Orders
        $q = $conn->query("SELECT COUNT(*) as orders FROM orders");
        $totals['total_orders'] = (int)($q ? $q->fetch_assoc()['orders'] : 0);
        
        echo json_encode($totals);
        break;

    // --- MONTHLY REVENUE (Line Chart) ---
    case 'monthly_revenue':
        $rows = [];
        // Try with status filter first
        $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, SUM(total_price) as amt FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL) GROUP BY ym ORDER BY ym ASC LIMIT 12");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        }
        // If no results, try all orders (fallback)
        if (empty($rows)) {
            $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, SUM(total_price) as amt FROM orders GROUP BY ym ORDER BY ym ASC LIMIT 12");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $rows[] = $r;
                $stmt->close();
            }
        }
        echo json_encode($rows);
        break;

    // --- TOP PRODUCTS (Bar Chart) ---
    case 'top_products':
        $rows = [];

        // Helper SQL snippet to avoid repetition
        $topProductsSql = "
            SELECT 
                p.id,
                p.name,
                p.price,
                COALESCE(p.stock_quantity, p.stock, 0) AS stock_quantity,
                COALESCE(NULLIF(p.category, ''), NULL) AS legacy_category,
                COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS categories,
                SUM(oi.quantity) AS total_qty
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_categories pc ON p.id = pc.product_id
            LEFT JOIN categories c ON pc.category_id = c.id
            WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL)
            GROUP BY p.id
            ORDER BY total_qty DESC
            LIMIT 5";

        $stmt = $conn->prepare($topProductsSql);
        if ($stmt) { 
            $stmt->execute(); 
            $res = $stmt->get_result(); 
            while($r=$res->fetch_assoc()) $rows[]=$r;
            $stmt->close();
        }

        // Fallback: if there are no orders yet, show top products by stock instead
        if (empty($rows)) {
            $fallbackSql = "
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    COALESCE(p.stock_quantity, p.stock, 0) AS stock_quantity,
                    COALESCE(NULLIF(p.category, ''), NULL) AS legacy_category,
                    COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '), '') AS categories,
                    COALESCE(p.stock_quantity, p.stock, 0) AS total_qty
                FROM products p
                LEFT JOIN product_categories pc ON p.id = pc.product_id
                LEFT JOIN categories c ON pc.category_id = c.id
                GROUP BY p.id
                ORDER BY total_qty DESC
                LIMIT 5";
            $stmt = $conn->prepare($fallbackSql);
            if($stmt) { 
                $stmt->execute(); 
                $res = $stmt->get_result(); 
                while($r=$res->fetch_assoc()) $rows[]=$r;
                $stmt->close();
            }
        }

        echo json_encode($rows);
        break;

    // --- TOP SELLERS ---
    case 'top_sellers':
        $rows = [];
        $stmt = $conn->prepare("SELECT u.id, COALESCE(NULLIF(u.seller_name,''), u.fullname) as seller, SUM(o.total_price) as revenue FROM orders o JOIN users u ON o.seller_id = u.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) GROUP BY u.id ORDER BY revenue DESC LIMIT 5");
        if($stmt){ 
            $stmt->execute(); 
            $res=$stmt->get_result(); 
            while($r=$res->fetch_assoc()) $rows[]=$r;
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
        }
        echo json_encode($rows);
        break;

    // --- ORDER STATUS (Pie Chart) ---
    case 'orders_summary':
        $rows=[];
        $stmt=$conn->prepare("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
        if($stmt) {
            $stmt->execute(); 
            $res=$stmt->get_result(); 
            while($r=$res->fetch_assoc()) $rows[]=$r;
            $stmt->close();
        }
        echo json_encode($rows);
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

    // --- CATEGORY DISTRIBUTION (Bar Chart) ---
    case 'category_distribution':
        $rows = [];
        // Sales based
        $sql = "SELECT COALESCE(p.category, 'Uncategorized') as category, COALESCE(SUM(oi.quantity), 0) as count FROM products p LEFT JOIN order_items oi ON p.id = oi.product_id LEFT JOIN orders o ON oi.order_id = o.id AND (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) GROUP BY COALESCE(p.category, 'Uncategorized') ORDER BY count DESC, category ASC LIMIT 10";
        $result = $conn->query($sql);
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                if ((int)$r['count'] > 0 || empty($rows)) {
                    $rows[] = ['category' => $r['category'] ?: 'Uncategorized', 'count' => (int)$r['count']];
                }
            }
        }
        // Fallback to simple product count if no sales
        $hasSales = false;
        foreach ($rows as $row) { if ($row['count'] > 0) { $hasSales = true; break; } }
        
        if (!$hasSales) {
            $rows = [];
            $sql = "SELECT COALESCE(category, 'Uncategorized') as category, COUNT(*) as count FROM products GROUP BY COALESCE(category, 'Uncategorized') ORDER BY count DESC LIMIT 10";
            $result = $conn->query($sql);
            if ($result) {
                while ($r = $result->fetch_assoc()) {
                    $rows[] = ['category' => $r['category'] ?: 'Uncategorized', 'count' => (int)$r['count']];
                }
            }
        }
        echo json_encode($rows);
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
        // Try buyer_id first, fallback to user_id
        $hasBuyerId = false;
        $hasShippingCountry = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'buyer_id'");
        if ($colCheck && $colCheck->num_rows > 0) $hasBuyerId = true;
        
        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_country'");
        if ($colCheck && $colCheck->num_rows > 0) $hasShippingCountry = true;
        
        if ($hasShippingCountry && $hasBuyerId) {
            $sql = "SELECT COALESCE(NULLIF(o.shipping_country,''), NULLIF(u.country_name,''), NULLIF(u.country,'')) AS country, SUM(o.total_price) AS value FROM orders o LEFT JOIN users u ON o.buyer_id = u.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) AND COALESCE(NULLIF(o.shipping_country,''), NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL GROUP BY country ORDER BY value DESC LIMIT 200";
        } elseif ($hasBuyerId) {
            $sql = "SELECT COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) AS country, SUM(o.total_price) AS value FROM orders o LEFT JOIN users u ON o.buyer_id = u.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) AND COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL GROUP BY country ORDER BY value DESC LIMIT 200";
        } else {
            // Fallback: user_id
            $sql = "SELECT COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) AS country, SUM(o.total_price) AS value FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) AND COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL GROUP BY country ORDER BY value DESC LIMIT 200";
        }
        
        $rows = [];
        $countryTotals = [];
        
        if ($res = $conn->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $normalized = normalizeCountryName($r['country']);
                if ($normalized) {
                    $value = (float)$r['value'];
                    if (!isset($countryTotals[$normalized])) {
                        $countryTotals[$normalized] = 0;
                    }
                    $countryTotals[$normalized] += $value;
                }
            }
        }
        
        // Convert to array format for Google Charts
        foreach ($countryTotals as $country => $value) {
            $rows[] = ['country' => $country, 'value' => $value];
        }
        
        // Sort descending
        usort($rows, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        
        echo json_encode($rows);
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