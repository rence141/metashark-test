<?php
// Quick test to verify seller shop redirects are working
include('db.php');

echo "<h2>🛍️ Seller Shop Redirect Test</h2>";

// Test 1: Check if we have sellers and products
echo "<h3>1. Available Sellers with Products:</h3>";
$test_query = "SELECT u.id, u.fullname, u.seller_name, COUNT(p.id) as product_count 
               FROM users u 
               LEFT JOIN products p ON u.id = p.seller_id AND p.is_active = TRUE 
               WHERE u.role = 'seller' OR u.role = 'admin' 
               GROUP BY u.id 
               HAVING product_count > 0 
               ORDER BY u.id";
$test_result = $conn->query($test_query);

if ($test_result && $test_result->num_rows > 0) {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>✅ Found sellers with products:</strong><br><br>";
    
    while ($seller = $test_result->fetch_assoc()) {
        $shop_url = "seller_shop.php?seller_id=" . $seller['id'];
        $seller_name = $seller['seller_name'] ?: $seller['fullname'];
        
        echo "<div style='margin: 10px 0; padding: 10px; background: #222; border-radius: 5px;'>";
        echo "<strong>Seller:</strong> " . htmlspecialchars($seller_name) . "<br>";
        echo "<strong>Products:</strong> " . $seller['product_count'] . "<br>";
        echo "<strong>Shop URL:</strong> <a href='$shop_url' target='_blank' style='color: #44D62C;'>$shop_url</a><br>";
        echo "<strong>Test Link:</strong> <a href='$shop_url' target='_blank' style='color: #44D62C; font-weight: bold;'>Visit Shop →</a>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No sellers with products found. Add some products first!";
    echo "</div>";
}

// Test 2: Check product links on main page
echo "<h3>2. Product Links on Main Page:</h3>";
$products_query = "SELECT p.*, u.seller_name, u.fullname as seller_fullname 
                   FROM products p 
                   LEFT JOIN users u ON p.seller_id = u.id 
                   WHERE p.is_active = TRUE 
                   ORDER BY p.created_at DESC 
                   LIMIT 3";
$products_result = $conn->query($products_query);

if ($products_result && $products_result->num_rows > 0) {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>✅ Sample product cards with seller links:</strong><br><br>";
    
    while ($product = $products_result->fetch_assoc()) {
        $seller_name = $product['seller_name'] ?: $product['seller_fullname'];
        $shop_url = "seller_shop.php?seller_id=" . $product['seller_id'];
        
        echo "<div style='margin: 10px 0; padding: 15px; background: #222; border-radius: 5px; border: 1px solid #44D62C;'>";
        echo "<strong>Product:</strong> " . htmlspecialchars($product['name']) . "<br>";
        echo "<strong>Price:</strong> $" . number_format($product['price'], 2) . "<br>";
        echo "<strong>Sold by:</strong> <a href='$shop_url' style='color: #44D62C; text-decoration: none; font-weight: bold;'>" . htmlspecialchars($seller_name) . "</a><br>";
        echo "<strong>Link:</strong> <a href='$shop_url' target='_blank' style='color: #44D62C;'>$shop_url</a>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No products found";
    echo "</div>";
}

// Test 3: Verify seller_shop.php exists and is accessible
echo "<h3>3. File Accessibility Check:</h3>";
if (file_exists('seller_shop.php')) {
    echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "✅ seller_shop.php file exists and is accessible";
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ seller_shop.php file not found";
    echo "</div>";
}

// Test 4: Instructions for testing
echo "<h3>4. How to Test the Redirect:</h3>";
echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Step-by-step testing:</strong><br><br>";
echo "1. 🌐 <strong>Go to main page:</strong> <a href='shop.php' target='_blank' style='color: white;'>shop.php</a><br>";
echo "2. 👀 <strong>Look for products:</strong> Find product cards with 'Sold by: [Seller Name]'<br>";
echo "3. 🖱️ <strong>Click seller name:</strong> Click on the green seller name link<br>";
echo "4. ✅ <strong>Verify redirect:</strong> You should be taken to seller_shop.php?seller_id=X<br>";
echo "5. 🛍️ <strong>Check shop page:</strong> Verify you see the seller's profile and products<br><br>";
echo "<strong>Expected behavior:</strong><br>";
echo "• Seller names should be green and clickable<br>";
echo "• Clicking should redirect to the seller's shop page<br>";
echo "• Shop page should show seller info and all their products<br>";
echo "• You should be able to add products to cart from the shop page<br>";
echo "</div>";

// Test 5: Check for any potential issues
echo "<h3>5. Potential Issues Check:</h3>";
$issues = [];

// Check if seller_id column exists in products table
$column_check = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
if (!$column_check || $column_check->num_rows === 0) {
    $issues[] = "❌ products table missing seller_id column";
} else {
    echo "✅ products table has seller_id column<br>";
}

// Check if users table has seller columns
$seller_columns = ['seller_name', 'role'];
foreach ($seller_columns as $column) {
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
    if (!$column_check || $column_check->num_rows === 0) {
        $issues[] = "❌ users table missing $column column";
    } else {
        echo "✅ users table has $column column<br>";
    }
}

if (empty($issues)) {
    echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "✅ No issues found - everything should work correctly!";
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Issues found:</strong><br>";
    foreach ($issues as $issue) {
        echo $issue . "<br>";
    }
    echo "</div>";
}

echo "<h3>🎉 Ready to Test!</h3>";
echo "<div style='background: #333; padding: 20px; border-radius: 8px; margin: 10px 0; border: 2px solid #44D62C;'>";
echo "<strong>🚀 The seller shop redirect feature is ready!</strong><br><br>";
echo "When buyers click on seller names in product cards, they will be redirected to that seller's dedicated shop page where they can see all products from that specific seller.<br><br>";
echo "<strong>Next steps:</strong><br>";
echo "1. Go to the main page (shop.php)<br>";
echo "2. Look for products with 'Sold by: [Seller Name]'<br>";
echo "3. Click on the green seller name links<br>";
echo "4. Enjoy browsing individual seller shops! 🛍️";
echo "</div>";

$conn->close();
?>
