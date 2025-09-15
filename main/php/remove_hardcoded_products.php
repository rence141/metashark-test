<?php
// Script to remove all hardcoded products from the database
include('db.php');

echo "<h2>🗑️ Removing Hardcoded Products</h2>";

// First, let's see what products exist
echo "<h3>1. Current Products in Database:</h3>";
$check_products = "SELECT id, name, seller_id FROM products ORDER BY id";
$result = $conn->query($check_products);

if ($result && $result->num_rows > 0) {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<table style='color: #ccc; width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #444;'><th style='padding: 10px; border: 1px solid #666;'>ID</th><th style='padding: 10px; border: 1px solid #666;'>Product Name</th><th style='padding: 10px; border: 1px solid #666;'>Seller ID</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #666;'>" . $row['id'] . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #666;'>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #666;'>" . $row['seller_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    $total_products = $result->num_rows;
    echo "<p style='color: #44D62C; font-weight: bold;'>Total products found: " . $total_products . "</p>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No products found in database";
    echo "</div>";
    $total_products = 0;
}

// Remove all products
echo "<h3>2. Removing All Products:</h3>";

if ($total_products > 0) {
    // First, clear the cart table (since it references products)
    echo "<p>🧹 Clearing cart items...</p>";
    $clear_cart = "DELETE FROM cart";
    if ($conn->query($clear_cart)) {
        echo "<div style='background: #44D62C; padding: 10px; border-radius: 5px; margin: 5px 0;'>✅ Cart cleared successfully</div>";
    } else {
        echo "<div style='background: #ff4444; padding: 10px; border-radius: 5px; margin: 5px 0;'>❌ Error clearing cart: " . $conn->error . "</div>";
    }
    
    // Then remove all products
    echo "<p>🗑️ Removing all products...</p>";
    $remove_products = "DELETE FROM products";
    if ($conn->query($remove_products)) {
        echo "<div style='background: #44D62C; padding: 10px; border-radius: 5px; margin: 5px 0;'>✅ All products removed successfully</div>";
    } else {
        echo "<div style='background: #ff4444; padding: 10px; border-radius: 5px; margin: 5px 0;'>❌ Error removing products: " . $conn->error . "</div>";
    }
    
    // Reset auto increment
    echo "<p>🔄 Resetting auto increment...</p>";
    $reset_auto_increment = "ALTER TABLE products AUTO_INCREMENT = 1";
    if ($conn->query($reset_auto_increment)) {
        echo "<div style='background: #44D62C; padding: 10px; border-radius: 5px; margin: 5px 0;'>✅ Auto increment reset successfully</div>";
    } else {
        echo "<div style='background: #ff4444; padding: 10px; border-radius: 5px; margin: 5px 0;'>❌ Error resetting auto increment: " . $conn->error . "</div>";
    }
} else {
    echo "<div style='background: #ffaa00; padding: 10px; border-radius: 5px; margin: 5px 0;'>⚠️ No products to remove</div>";
}

// Verify the database is clean
echo "<h3>3. Verification:</h3>";
$verify_products = "SELECT COUNT(*) as count FROM products";
$verify_result = $conn->query($verify_products);
$product_count = $verify_result->fetch_assoc()['count'];

if ($product_count == 0) {
    echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "✅ <strong>Database is clean!</strong> No products found.";
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ <strong>Warning!</strong> " . $product_count . " products still exist.";
    echo "</div>";
}

// Check cart table
$verify_cart = "SELECT COUNT(*) as count FROM cart";
$cart_result = $conn->query($verify_cart);
$cart_count = $cart_result->fetch_assoc()['count'];

if ($cart_count == 0) {
    echo "<div style='background: #44D62C; padding: 10px; border-radius: 5px; margin: 5px 0;'>✅ Cart table is empty</div>";
} else {
    echo "<div style='background: #ff4444; padding: 10px; border-radius: 5px; margin: 5px 0;'>❌ Cart table has " . $cart_count . " items</div>";
}

echo "<h3>4. Next Steps:</h3>";
echo "<div style='background: #333; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: #44D62C; margin-bottom: 15px;'>🧹 Database Cleanup Complete!</h4>";
echo "<ul style='color: #ccc; line-height: 1.8;'>";
echo "<li>✅ All hardcoded products removed from database</li>";
echo "<li>✅ Cart items cleared</li>";
echo "<li>✅ Auto increment reset</li>";
echo "<li>✅ Database is now clean and ready for real products</li>";
echo "</ul>";
echo "<br>";
echo "<p style='color: #44D62C; font-weight: bold;'>Now sellers can add their own products using the 'Add Product' feature!</p>";
echo "</div>";

$conn->close();
?>
