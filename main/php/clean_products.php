<?php
// Simple script to remove hardcoded products - can be run in browser
include('db.php');

echo "<h2>🗑️ Removing Hardcoded Products</h2>";
echo "<style>
body { font-family: Arial, sans-serif; background: #0A0A0A; color: #FFFFFF; padding: 20px; }
.success { background: #44D62C; color: black; padding: 10px; border-radius: 5px; margin: 10px 0; }
.error { background: #ff4444; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
.info { background: #2196F3; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

// Check current products
$check_products = "SELECT COUNT(*) as count FROM products";
$result = $conn->query($check_products);
$product_count = $result->fetch_assoc()['count'];

echo "<div class='info'>Found $product_count products in database</div>";

if ($product_count > 0) {
    // Clear cart first
    echo "<div class='info'>Clearing cart items...</div>";
    $clear_cart = "DELETE FROM cart";
    if ($conn->query($clear_cart)) {
        echo "<div class='success'>✅ Cart cleared successfully</div>";
    } else {
        echo "<div class='error'>❌ Error clearing cart: " . $conn->error . "</div>";
    }
    
    // Remove all products
    echo "<div class='info'>Removing all products...</div>";
    $remove_products = "DELETE FROM products";
    if ($conn->query($remove_products)) {
        echo "<div class='success'>✅ All products removed successfully</div>";
    } else {
        echo "<div class='error'>❌ Error removing products: " . $conn->error . "</div>";
    }
    
    // Reset auto increment
    $reset_auto = "ALTER TABLE products AUTO_INCREMENT = 1";
    if ($conn->query($reset_auto)) {
        echo "<div class='success'>✅ Auto increment reset successfully</div>";
    } else {
        echo "<div class='error'>❌ Error resetting auto increment: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='success'>✅ Database is already clean - no products to remove</div>";
}

// Verify cleanup
$verify_products = "SELECT COUNT(*) as count FROM products";
$verify_result = $conn->query($verify_products);
$final_count = $verify_result->fetch_assoc()['count'];

if ($final_count == 0) {
    echo "<div class='success'><strong>🎉 SUCCESS! Database is now clean of hardcoded products!</strong></div>";
    echo "<div class='info'>Sellers can now add their own products using the 'Add Product' feature.</div>";
} else {
    echo "<div class='error'><strong>❌ WARNING! $final_count products still remain.</strong></div>";
}

$conn->close();
?>
