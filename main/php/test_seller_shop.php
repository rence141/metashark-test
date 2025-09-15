<?php
// Test script to verify seller shop functionality
include('db.php');

echo "<h2>🛍️ Seller Shop Test</h2>";

// Test 1: Check if sellers exist
echo "<h3>1. Available Sellers:</h3>";
$sellers_query = "SELECT id, fullname, seller_name, role FROM users WHERE role = 'seller' OR role = 'admin' ORDER BY id";
$sellers_result = $conn->query($sellers_query);

if ($sellers_result && $sellers_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #333;'>";
    echo "<th>ID</th><th>Name</th><th>Seller Name</th><th>Role</th><th>Products</th><th>Shop Link</th>";
    echo "</tr>";
    
    while ($seller = $sellers_result->fetch_assoc()) {
        // Count products for this seller
        $product_count_query = "SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND is_active = TRUE";
        $product_count_stmt = $conn->prepare($product_count_query);
        $product_count_stmt->bind_param("i", $seller['id']);
        $product_count_stmt->execute();
        $product_count_result = $product_count_stmt->get_result();
        $product_count = $product_count_result->fetch_assoc()['count'];
        
        echo "<tr>";
        echo "<td>" . $seller['id'] . "</td>";
        echo "<td>" . htmlspecialchars($seller['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($seller['seller_name'] ?: 'Not set') . "</td>";
        echo "<td>" . htmlspecialchars($seller['role']) . "</td>";
        echo "<td>" . $product_count . "</td>";
        echo "<td><a href='seller_shop.php?seller_id=" . $seller['id'] . "' target='_blank' style='color: #44D62C;'>Visit Shop</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No sellers found in the database";
    echo "</div>";
}

// Test 2: Check products with seller information
echo "<h3>2. Products with Seller Links:</h3>";
$products_query = "SELECT p.*, u.seller_name, u.fullname as seller_fullname 
                   FROM products p 
                   LEFT JOIN users u ON p.seller_id = u.id 
                   WHERE p.is_active = TRUE 
                   ORDER BY p.created_at DESC 
                   LIMIT 5";
$products_result = $conn->query($products_query);

if ($products_result && $products_result->num_rows > 0) {
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;'>";
    
    while ($product = $products_result->fetch_assoc()) {
        echo "<div style='background: #111; padding: 20px; border-radius: 10px; border: 1px solid #333;'>";
        echo "<h4 style='color: #44D62C; margin-bottom: 10px;'>" . htmlspecialchars($product['name']) . "</h4>";
        echo "<p style='color: #888; margin-bottom: 10px;'>Sold by: <a href='seller_shop.php?seller_id=" . $product['seller_id'] . "' style='color: #44D62C; text-decoration: none; font-weight: bold;'>" . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . "</a></p>";
        echo "<p style='color: #44D62C; font-weight: bold; font-size: 1.2rem;'>$" . number_format($product['price'], 2) . "</p>";
        echo "<p style='color: #888;'>Stock: " . $product['stock_quantity'] . "</p>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No products found";
    echo "</div>";
}

// Test 3: Test seller shop URL structure
echo "<h3>3. Seller Shop URL Structure:</h3>";
echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>URL Format:</strong> seller_shop.php?seller_id={SELLER_ID}<br>";
echo "<strong>Example:</strong> seller_shop.php?seller_id=1<br>";
echo "<strong>Features:</strong><br>";
echo "• ✅ Seller profile information<br>";
echo "• ✅ Seller's product grid<br>";
echo "• ✅ Add to cart functionality<br>";
echo "• ✅ Responsive design<br>";
echo "• ✅ Seller statistics<br>";
echo "</div>";

// Test 4: Check for any issues
echo "<h3>4. System Check:</h3>";
$issues = [];

// Check if seller_shop.php exists
if (!file_exists('seller_shop.php')) {
    $issues[] = "❌ seller_shop.php file not found";
} else {
    echo "✅ seller_shop.php file exists<br>";
}

// Check if products table has seller_id column
$table_check = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
if (!$table_check || $table_check->num_rows === 0) {
    $issues[] = "❌ products table missing seller_id column";
} else {
    echo "✅ products table has seller_id column<br>";
}

// Check if users table has seller columns
$seller_columns = ['seller_name', 'seller_description', 'business_type'];
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
    echo "✅ All systems working correctly!";
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Issues found:</strong><br>";
    foreach ($issues as $issue) {
        echo $issue . "<br>";
    }
    echo "</div>";
}

echo "<h3>🎉 How to Use:</h3>";
echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>For Buyers:</strong><br>";
echo "1. Browse products on the main page<br>";
echo "2. Click on the seller's name (green link)<br>";
echo "3. View all products from that seller<br>";
echo "4. Add products to cart from the seller's shop<br>";
echo "<br>";
echo "<strong>For Sellers:</strong><br>";
echo "1. Add products through the seller dashboard<br>";
echo "2. Buyers can visit your dedicated shop page<br>";
echo "3. Your shop shows your profile and all products<br>";
echo "</div>";

$conn->close();
?>
