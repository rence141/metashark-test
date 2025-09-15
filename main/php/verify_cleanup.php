<?php
// Verification script to check if hardcoded products have been removed
include('db.php');

echo "<h2>🔍 Database Verification</h2>";
echo "<style>
body { font-family: Arial, sans-serif; background: #0A0A0A; color: #FFFFFF; padding: 20px; }
.success { background: #44D62C; color: black; padding: 10px; border-radius: 5px; margin: 10px 0; }
.error { background: #ff4444; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
.info { background: #2196F3; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #666; padding: 8px; text-align: left; }
th { background: #444; }
</style>";

// Check products table
$products_query = "SELECT COUNT(*) as count FROM products";
$products_result = $conn->query($products_query);
$product_count = $products_result->fetch_assoc()['count'];

echo "<h3>📊 Current Database State:</h3>";
echo "<div class='info'>Products in database: <strong>$product_count</strong></div>";

if ($product_count > 0) {
    echo "<div class='error'>⚠️ Products still exist in database</div>";
    
    // Show what products exist
    $show_products = "SELECT id, name, seller_id, created_at FROM products ORDER BY id";
    $show_result = $conn->query($show_products);
    
    if ($show_result && $show_result->num_rows > 0) {
        echo "<h4>Current Products:</h4>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Seller ID</th><th>Created</th></tr>";
        
        while ($row = $show_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . $row['seller_id'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='info'>";
        echo "<strong>To remove these products:</strong><br>";
        echo "1. Run <a href='clean_products.php' style='color: #44D62C;'>clean_products.php</a> in your browser<br>";
        echo "2. Or run the SQL script: <code>clean_hardcoded_products.sql</code>";
        echo "</div>";
    }
} else {
    echo "<div class='success'>✅ Database is clean - no hardcoded products found!</div>";
    echo "<div class='info'>Sellers can now add their own products using the 'Add Product' feature.</div>";
}

// Check cart table
$cart_query = "SELECT COUNT(*) as count FROM cart";
$cart_result = $conn->query($cart_query);
$cart_count = $cart_result->fetch_assoc()['count'];

echo "<div class='info'>Cart items: <strong>$cart_count</strong></div>";

// Check if tables exist
$tables_query = "SHOW TABLES LIKE 'products'";
$tables_result = $conn->query($tables_query);

if ($tables_result && $tables_result->num_rows > 0) {
    echo "<div class='success'>✅ Products table exists</div>";
} else {
    echo "<div class='error'>❌ Products table does not exist</div>";
}

$conn->close();
?>
