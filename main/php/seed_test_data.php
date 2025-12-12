<?php
/**
 * Quick Test Data Seeder for Dashboard Development
 * This script inserts sample data for testing charts and dashboard
 * 
 * NOTE: Only run this on development/test databases!
 */

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Security: Only allow if logged in as admin
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized: Admin login required');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_data'])) {
    try {
        // 1. Insert test categories
        $categories = ['Accessories', 'Phone', 'Tablet', 'Laptop', 'Gaming'];
        foreach ($categories as $cat) {
            $conn->query("INSERT IGNORE INTO categories (name) VALUES ('$cat')");
        }
        $message .= "✓ Categories inserted<br>";

        // 2. Insert test products
        $products = [
            ['USB Cable', 'Accessories', 100, 5.99],
            ['Screen Protector', 'Accessories', 250, 2.99],
            ['Case', 'Phone', 80, 15.99],
            ['Screen Protector for Tablet', 'Tablet', 60, 8.99],
            ['Laptop Stand', 'Laptop', 40, 29.99],
            ['Gaming Mouse', 'Gaming', 120, 49.99],
            ['Keyboard', 'Accessories', 75, 79.99],
            ['HDMI Cable', 'Accessories', 200, 3.99]
        ];

        foreach ($products as [$name, $cat, $stock, $price]) {
            $conn->query("INSERT IGNORE INTO products (name, category, stock_quantity, price, is_active) 
                         VALUES ('$name', '$cat', $stock, $price, 1)");
        }
        $message .= "✓ Products inserted<br>";

        // 3. Insert test orders (past 12 months)
        $statuses = ['pending', 'processing', 'completed', 'cancelled'];
        $countries = ['USA', 'Canada', 'UK', 'Australia', 'Germany', 'France', 'Japan', 'Brazil'];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i months"));
            for ($j = 0; $j < 5; $j++) {
                $status = $statuses[array_rand($statuses)];
                $country = $countries[array_rand($countries)];
                $amount = rand(50, 500);
                
                $conn->query("INSERT INTO orders (user_id, total_amount, status, billing_country, created_at) 
                             VALUES (1, $amount, '$status', '$country', '$date 10:00:00')");
            }
        }
        $message .= "✓ Orders inserted (60 orders)<br>";

        // 4. Insert order items linking to products
        $result = $conn->query("SELECT id FROM products LIMIT 5");
        $product_ids = [];
        while ($row = $result->fetch_assoc()) {
            $product_ids[] = $row['id'];
        }
        
        $result = $conn->query("SELECT id FROM orders LIMIT 20");
        $order_ids = [];
        while ($row = $result->fetch_assoc()) {
            $order_ids[] = $row['id'];
        }
        
        foreach ($order_ids as $order_id) {
            for ($i = 0; $i < rand(1, 3); $i++) {
                $product_id = $product_ids[array_rand($product_ids)];
                $quantity = rand(1, 5);
                $conn->query("INSERT INTO order_items (order_id, product_id, quantity) 
                             VALUES ($order_id, $product_id, $quantity)");
            }
        }
        $message .= "✓ Order items linked<br>";

        $message = "<div style='color:green; padding:10px; background:#eef6ee; border-radius:4px;'>
                    <strong>✓ Test Data Seeded Successfully!</strong><br>$message
                    <small>The dashboard should now display data. Click <a href='admin_dashboard.php'>here</a> to view.</small>
                   </div>";
    } catch (Exception $e) {
        $error = "<div style='color:red; padding:10px; background:#fee; border-radius:4px;'>
                  <strong>✗ Error:</strong> " . $e->getMessage() . "
                  </div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Seed Test Data - Meta Shark</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        button { padding: 10px 20px; background: #44D62C; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #3ab51e; }
        .warning { padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌱 Dashboard Test Data Seeder</h1>
        
        <?php if ($message) echo $message; ?>
        <?php if ($error) echo $error; ?>
        
        <div class="warning">
            <strong>⚠️ Warning:</strong> This script inserts sample data for development/testing purposes only.
            It will insert ~60 test orders, 8 products, and sample categories.
        </div>
        
        <form method="POST">
            <button type="submit" name="seed_data" value="1">Insert Test Data Now</button>
        </form>
        
        <p style="margin-top: 20px; font-size: 14px; color: #666;">
            After seeding, reload your dashboard to see:<br>
            ✓ Revenue charts with 12 months of data<br>
            ✓ Order status distribution<br>
            ✓ Geographic sales data<br>
            ✓ Product inventory information
        </p>
    </div>
</body>
</html>
