<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Checker - Meta Shark</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f1115;
            color: #e6eef6;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #44D62C;
            margin-bottom: 20px;
            border-bottom: 2px solid #44D62C;
            padding-bottom: 10px;
        }
        .section {
            background: #161b22;
            border: 1px solid #242c38;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .section h2 {
            color: #44D62C;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .success {
            color: #44D62C;
            background: rgba(68, 214, 44, 0.1);
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #44D62C;
            margin: 10px 0;
        }
        .error {
            color: #f44336;
            background: rgba(244, 67, 54, 0.1);
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #f44336;
            margin: 10px 0;
        }
        .info {
            color: #00d4ff;
            background: rgba(0, 212, 255, 0.1);
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #00d4ff;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #242c38;
        }
        th {
            background: #1c2128;
            color: #44D62C;
            font-weight: 600;
        }
        tr:hover {
            background: rgba(68, 214, 44, 0.05);
        }
        .count {
            font-size: 24px;
            font-weight: bold;
            color: #44D62C;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #44D62C;
            color: #000;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
        }
        .back-link:hover {
            background: #3ab820;
        }
        code {
            background: #1c2128;
            padding: 2px 6px;
            border-radius: 3px;
            color: #00d4ff;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Connection Checker</h1>

        <?php
        // Check database connection
        if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
            echo '<div class="section">';
            echo '<div class="error">';
            echo '<h2>❌ Database Connection Failed</h2>';
            echo '<p>Unable to connect to the database. Please check:</p>';
            echo '<ul style="margin-left: 20px; margin-top: 10px;">';
            echo '<li>MySQL is running in XAMPP Control Panel</li>';
            echo '<li>Database credentials in <code>includes/db_connect.php</code> are correct</li>';
            echo '<li>Database <code>metaaccesories</code> exists</li>';
            echo '</ul>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="section">';
            echo '<div class="success">';
            echo '<h2>✅ Database Connection Successful</h2>';
            echo '<p><strong>Host:</strong> ' . htmlspecialchars($conn->host_info) . '</p>';
            echo '<p><strong>Database:</strong> <code>metaaccesories</code></p>';
            echo '<p><strong>Server Info:</strong> ' . htmlspecialchars($conn->server_info) . '</p>';
            echo '</div>';
            echo '</div>';

            // Check tables
            echo '<div class="section">';
            echo '<h2>📊 Table Information</h2>';
            
            $tables = ['products', 'orders', 'order_items', 'users', 'categories', 'product_categories'];
            $tableData = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
                    $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
                    $tableData[] = ['name' => $table, 'exists' => true, 'count' => $count];
                } else {
                    $tableData[] = ['name' => $table, 'exists' => false, 'count' => 0];
                }
            }
            
            echo '<table>';
            echo '<tr><th>Table Name</th><th>Status</th><th>Record Count</th></tr>';
            foreach ($tableData as $data) {
                $status = $data['exists'] ? '<span style="color:#44D62C">✓ Exists</span>' : '<span style="color:#f44336">✗ Missing</span>';
                $count = $data['exists'] ? '<span class="count">' . number_format($data['count']) . '</span>' : 'N/A';
                echo '<tr><td><code>' . htmlspecialchars($data['name']) . '</code></td><td>' . $status . '</td><td>' . $count . '</td></tr>';
            }
            echo '</table>';
            echo '</div>';

            // Dashboard Stats Check
            echo '<div class="section">';
            echo '<h2>📈 Dashboard Data Check</h2>';
            
            try {
                // Total Products
                $q = $conn->query("SELECT COUNT(*) as total FROM products");
                $products = $q ? (int)$q->fetch_assoc()['total'] : 0;
                
                // Total Revenue
                $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL)");
                $revenue = $q ? (float)$q->fetch_assoc()['total'] : 0;
                if ($revenue == 0) {
                    $q = $conn->query("SELECT IFNULL(SUM(total_price),0) as total FROM orders");
                    $revenue = $q ? (float)$q->fetch_assoc()['total'] : 0;
                }
                
                // Total Orders
                $q = $conn->query("SELECT COUNT(*) as total FROM orders");
                $orders = $q ? (int)$q->fetch_assoc()['total'] : 0;
                
                // Total Sellers
                $q = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='seller'");
                $sellers = $q ? (int)$q->fetch_assoc()['total'] : 0;
                
                // Total Stock
                $q = $conn->query("SELECT IFNULL(SUM(COALESCE(stock_quantity, stock, 0)),0) as total FROM products");
                $stock = $q ? (int)$q->fetch_assoc()['total'] : 0;
                
                echo '<table>';
                echo '<tr><th>Metric</th><th>Value</th><th>Status</th></tr>';
                echo '<tr><td>Total Products</td><td><span class="count">' . number_format($products) . '</span></td><td>' . ($products > 0 ? '✓' : '⚠ No products') . '</td></tr>';
                echo '<tr><td>Total Revenue</td><td><span class="count">$' . number_format($revenue, 2) . '</span></td><td>' . ($revenue > 0 ? '✓' : '⚠ No revenue') . '</td></tr>';
                echo '<tr><td>Total Orders</td><td><span class="count">' . number_format($orders) . '</span></td><td>' . ($orders > 0 ? '✓' : '⚠ No orders') . '</td></tr>';
                echo '<tr><td>Active Sellers</td><td><span class="count">' . number_format($sellers) . '</span></td><td>' . ($sellers > 0 ? '✓' : '⚠ No sellers') . '</td></tr>';
                echo '<tr><td>Total Stock</td><td><span class="count">' . number_format($stock) . '</span></td><td>' . ($stock > 0 ? '✓' : '⚠ No stock') . '</td></tr>';
                echo '</table>';
                
                if ($products == 0 && $orders == 0) {
                    echo '<div class="info" style="margin-top: 15px;">';
                    echo '<strong>ℹ️ Note:</strong> Your database appears to be empty. This is normal for a new installation. ';
                    echo 'The dashboard will show "No data available" until you add products and orders.';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">Error checking dashboard data: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';

            // Chart Data Check
            echo '<div class="section">';
            echo '<h2>📊 Chart Data Availability</h2>';
            
            try {
                // Monthly Revenue
                $q = $conn->query("SELECT COUNT(*) as count FROM (SELECT DATE_FORMAT(created_at,'%Y-%m') as ym FROM orders WHERE (status IN ('confirmed','shipped','delivered','received') OR paid_at IS NOT NULL) GROUP BY ym) as sub");
                $monthlyRevenue = $q ? (int)$q->fetch_assoc()['count'] : 0;
                
                // Orders by Status
                $q = $conn->query("SELECT COUNT(DISTINCT status) as count FROM orders");
                $orderStatuses = $q ? (int)$q->fetch_assoc()['count'] : 0;
                
                // Category Distribution
                $q = $conn->query("SELECT COUNT(DISTINCT COALESCE(category, 'Uncategorized')) as count FROM products");
                $categories = $q ? (int)$q->fetch_assoc()['count'] : 0;
                
                // Sales by Country
                $q = $conn->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(shipping_country,''), NULLIF(u.country_name,''), NULLIF(u.country,''))) as count FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE COALESCE(NULLIF(shipping_country,''), NULLIF(u.country_name,''), NULLIF(u.country,'')) IS NOT NULL");
                $countries = $q ? (int)$q->fetch_assoc()['count'] : 0;
                
                echo '<table>';
                echo '<tr><th>Chart</th><th>Data Points</th><th>Status</th></tr>';
                echo '<tr><td>Revenue Trend</td><td>' . $monthlyRevenue . ' months</td><td>' . ($monthlyRevenue > 0 ? '✓' : '⚠ No data') . '</td></tr>';
                echo '<tr><td>Orders by Status</td><td>' . $orderStatuses . ' statuses</td><td>' . ($orderStatuses > 0 ? '✓' : '⚠ No data') . '</td></tr>';
                echo '<tr><td>Category Distribution</td><td>' . $categories . ' categories</td><td>' . ($categories > 0 ? '✓' : '⚠ No data') . '</td></tr>';
                echo '<tr><td>Sales by Country</td><td>' . $countries . ' countries</td><td>' . ($countries > 0 ? '✓' : '⚠ No data') . '</td></tr>';
                echo '</table>';
            } catch (Exception $e) {
                echo '<div class="error">Error checking chart data: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';

            // Sample Data Preview
            if ($products > 0 || $orders > 0) {
                echo '<div class="section">';
                echo '<h2>👀 Sample Data Preview</h2>';
                
                if ($products > 0) {
                    echo '<h3 style="color: #94a3b8; margin: 15px 0 10px 0; font-size: 14px;">Recent Products (Top 5):</h3>';
                    $q = $conn->query("SELECT id, name, price, COALESCE(stock_quantity, stock, 0) as stock FROM products ORDER BY id DESC LIMIT 5");
                    if ($q && $q->num_rows > 0) {
                        echo '<table>';
                        echo '<tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th></tr>';
                        while ($row = $q->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                            echo '<td>$' . number_format($row['price'], 2) . '</td>';
                            echo '<td>' . number_format($row['stock']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                }
                
                if ($orders > 0) {
                    echo '<h3 style="color: #94a3b8; margin: 15px 0 10px 0; font-size: 14px;">Recent Orders (Top 5):</h3>';
                    $q = $conn->query("SELECT id, total_price, status, created_at FROM orders ORDER BY id DESC LIMIT 5");
                    if ($q && $q->num_rows > 0) {
                        echo '<table>';
                        echo '<tr><th>ID</th><th>Total</th><th>Status</th><th>Date</th></tr>';
                        while ($row = $q->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                            echo '<td>$' . number_format($row['total_price'], 2) . '</td>';
                            echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                }
                echo '</div>';
            }
        }
        ?>

        <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>

