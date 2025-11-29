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
        $totals['total_products'] = (int)($q ? $q->fetch_assoc()['total_products'] : 0);
        
        // Revenue: Try with status filter first, fallback to all orders
        $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total_revenue FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL)");
        $revenue = $q ? (float)$q->fetch_assoc()['total_revenue'] : 0;
        if ($revenue == 0) {
            // Fallback: count all orders
            $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total_revenue FROM orders");
            $revenue = $q ? (float)$q->fetch_assoc()['total_revenue'] : 0;
        }
        $totals['total_revenue'] = $revenue;
        
        $q = $conn->query("SELECT IFNULL(SUM(stock),0) as total_stock FROM products");
        $totals['total_stock'] = (int)($q ? $q->fetch_assoc()['total_stock'] : 0);
        $q = $conn->query("SELECT COUNT(*) as sellers FROM users WHERE role='seller'");
        $totals['total_sellers'] = (int)($q ? $q->fetch_assoc()['sellers'] : 0);
        $q = $conn->query("SELECT COUNT(*) as orders FROM orders");
        $totals['total_orders'] = (int)($q ? $q->fetch_assoc()['orders'] : 0);
        echo json_encode($totals);
        break;

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
        // If no results, try all orders
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

    case 'top_products':
        $rows = [];
        // Try with status filter first
        $stmt = $conn->prepare("SELECT p.id, p.name, SUM(oi.quantity) as total_qty FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) GROUP BY p.id ORDER BY total_qty DESC LIMIT 5");
        if($stmt) { 
            $stmt->execute(); 
            $res = $stmt->get_result(); 
            while($r=$res->fetch_assoc()) $rows[]=$r;
            $stmt->close();
        }
        // If no results, try all orders
        if (empty($rows)) {
            $stmt = $conn->prepare("SELECT p.id, p.name, SUM(oi.quantity) as total_qty FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id GROUP BY p.id ORDER BY total_qty DESC LIMIT 5");
            if($stmt) { 
                $stmt->execute(); 
                $res = $stmt->get_result(); 
                while($r=$res->fetch_assoc()) $rows[]=$r;
                $stmt->close();
            }
        }
        echo json_encode($rows);
        break;

    case 'top_sellers':
        $rows = [];
        // Try with status filter first
        $stmt = $conn->prepare("SELECT u.id, COALESCE(NULLIF(u.seller_name,''), u.fullname) as seller, SUM(o.total_price) as revenue FROM orders o JOIN users u ON o.seller_id = u.id WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL) GROUP BY u.id ORDER BY revenue DESC LIMIT 5");
        if($stmt){ 
            $stmt->execute(); 
            $res=$stmt->get_result(); 
            while($r=$res->fetch_assoc()) $rows[]=$r;
            $stmt->close();
        }
        // If no results, try all orders
        if (empty($rows)) {
            $stmt = $conn->prepare("SELECT u.id, COALESCE(NULLIF(u.seller_name,''), u.fullname) as seller, SUM(o.total_price) as revenue FROM orders o JOIN users u ON o.seller_id = u.id GROUP BY u.id ORDER BY revenue DESC LIMIT 5");
            if($stmt){ 
                $stmt->execute(); 
                $res=$stmt->get_result(); 
                while($r=$res->fetch_assoc()) $rows[]=$r;
                $stmt->close();
            }
        }
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

    case 'daily_revenue':
        $rows = [];
        // Try with status filter first
        $stmt = $conn->prepare("SELECT DATE(created_at) as date, SUM(total_price) as amt FROM orders WHERE status IN ('confirmed','shipped','received') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
        }
        // If no results, try all orders
        if (empty($rows)) {
            $stmt = $conn->prepare("SELECT DATE(created_at) as date, SUM(total_price) as amt FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                while($r = $res->fetch_assoc()) $rows[] = $r;
                $stmt->close();
            }
        }
        echo json_encode($rows);
        break;

    case 'category_distribution':
        $rows = [];
        // Get categories with products sold (quantity from order_items)
        // First try to get sales data by category
        $sql = "SELECT 
                    COALESCE(p.category, 'Uncategorized') as category,
                    COALESCE(SUM(oi.quantity), 0) as count
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN orders o ON oi.order_id = o.id AND (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL)
                GROUP BY COALESCE(p.category, 'Uncategorized')
                ORDER BY count DESC, category ASC
                LIMIT 10";
        
        $result = $conn->query($sql);
        if ($result) {
            while ($r = $result->fetch_assoc()) {
                $count = (int)$r['count'];
                // Only include categories with sales OR if no sales data exists, include all
                if ($count > 0 || empty($rows)) {
                    $rows[] = [
                        'category' => $r['category'] ?: 'Uncategorized',
                        'count' => $count
                    ];
                }
            }
        }
        
        // If no sales data at all, fallback to product count per category
        $hasSales = false;
        foreach ($rows as $row) {
            if ($row['count'] > 0) {
                $hasSales = true;
                break;
            }
        }
        
        if (!$hasSales && !empty($rows)) {
            // Replace with product counts
            $rows = [];
            $sql = "SELECT 
                        COALESCE(category, 'Uncategorized') as category,
                        COUNT(*) as count
                    FROM products 
                    GROUP BY COALESCE(category, 'Uncategorized')
                    ORDER BY count DESC 
                    LIMIT 10";
            $result = $conn->query($sql);
            if ($result) {
                while ($r = $result->fetch_assoc()) {
                    $rows[] = [
                        'category' => $r['category'] ?: 'Uncategorized',
                        'count' => (int)$r['count']
                    ];
                }
            }
        }
        
        echo json_encode($rows);
        break;

    case 'fulfillment_rate':
        $q1 = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status = 'delivered'");
        $completed = $q1 ? (int)$q1->fetch_assoc()['c'] : 0;
        $q2 = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status IN ('pending','confirmed','shipped')");
        $pending = $q2 ? (int)$q2->fetch_assoc()['c'] : 0;
        echo json_encode(['completed' => $completed, 'pending' => $pending]);
        break;

    case 'user_registration_trend':
        $rows = [];
        $stmt = $conn->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 30");
        if($stmt) { $stmt->execute(); $res = $stmt->get_result(); while($r = $res->fetch_assoc()) $rows[] = $r; }
        echo json_encode($rows);
        break;

    case 'sales_by_country':
        // Normalize country names to Google Charts GeoChart expected format
        function normalizeCountryName($country) {
            if (empty($country)) return null;
            $country = trim($country);
            $upper = strtoupper($country);
            
            // Map common variations to Google Charts format
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
            
            // Check if it matches a valid name (case-insensitive)
            $validNames = ['United States', 'United Kingdom', 'United Arab Emirates', 'Canada', 'Australia', 
                          'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Brazil', 'Mexico', 'India', 
                          'China', 'Japan', 'South Korea', 'Russia', 'South Africa', 'Egypt', 'Nigeria',
                          'Argentina', 'Chile', 'Colombia', 'Peru', 'Venezuela', 'Indonesia', 'Malaysia', 
                          'Singapore', 'Thailand', 'Vietnam', 'Turkey', 'Saudi Arabia', 'Israel', 'Poland', 
                          'Sweden', 'Norway', 'Denmark', 'Finland', 'Belgium', 'Switzerland', 'Austria', 
                          'Portugal', 'Greece', 'Ireland', 'New Zealand', 'Philippines', 'Czechia'];
            
            foreach ($validNames as $valid) {
                if (strcasecmp($country, $valid) === 0) {
                    return $valid;
                }
            }
            
            return $country;
        }
        
        // Try buyer_id first, fallback to user_id if buyer_id doesn't exist
        $rows = [];
        $countryTotals = [];
        
        // Check which column exists
        $hasBuyerId = false;
        $hasShippingCountry = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'buyer_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $hasBuyerId = true;
        }
        $colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'shipping_country'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $hasShippingCountry = true;
        }
        
        if ($hasShippingCountry && $hasBuyerId) {
            $sql = "SELECT COALESCE(NULLIF(o.shipping_country,''), NULLIF(u.country_name,''), NULLIF(u.country,'')) AS country, SUM(o.total_price) AS value
                    FROM orders o
                    LEFT JOIN users u ON o.buyer_id = u.id
                    WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL)
                      AND COALESCE(NULLIF(o.shipping_country,''), NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL
                    GROUP BY country
                    ORDER BY value DESC
                    LIMIT 200";
        } elseif ($hasBuyerId) {
            $sql = "SELECT COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) AS country, SUM(o.total_price) AS value
                    FROM orders o
                    LEFT JOIN users u ON o.buyer_id = u.id
                    WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL)
                      AND COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL
                    GROUP BY country
                    ORDER BY value DESC
                    LIMIT 200";
        } else {
            // Fallback: try user_id
            $sql = "SELECT COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) AS country, SUM(o.total_price) AS value
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE (o.status IN ('confirmed','shipped','delivered','received') OR o.paid_at IS NOT NULL)
                      AND COALESCE(NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL
                    GROUP BY country
                    ORDER BY value DESC
                    LIMIT 200";
        }
        
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
        
        // Convert to array format
        foreach ($countryTotals as $country => $value) {
            $rows[] = ['country' => $country, 'value' => $value];
        }
        
        // Sort by value descending
        usort($rows, function($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        
        echo json_encode($rows);
        break;

    default:
        echo json_encode(['error'=>'unknown_action']);
        break;
}
