<?php
// Test script to demonstrate Add to Cart triggers
include('db.php');

echo "<h2>🛒 Add to Cart Trigger Test</h2>";

// Test 1: Show available products
echo "<h3>1. Available Products for Testing:</h3>";
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
        $seller_name = $product['seller_name'] ?: $product['seller_fullname'];
        
        echo "<div style='background: #111; padding: 20px; border-radius: 10px; border: 1px solid #333;'>";
        echo "<h4 style='color: #44D62C; margin-bottom: 10px;'>" . htmlspecialchars($product['name']) . "</h4>";
        echo "<p style='color: #888; margin-bottom: 10px;'>Sold by: " . htmlspecialchars($seller_name) . "</p>";
        echo "<p style='color: #44D62C; font-weight: bold; font-size: 1.2rem;'>$" . number_format($product['price'], 2) . "</p>";
        echo "<p style='color: #888;'>Stock: " . $product['stock_quantity'] . "</p>";
        echo "<button onclick='testAddToCart(\"" . htmlspecialchars($product['name']) . "\")' style='background: #44D62C; color: black; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px;'>Test Add to Cart</button>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No products found";
    echo "</div>";
}

// Test 2: Show trigger features
echo "<h3>2. Add to Cart Trigger Features:</h3>";
echo "<div style='background: #333; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: #44D62C; margin-bottom: 15px;'>✨ Visual Triggers Added:</h4>";
echo "<ul style='color: #ccc; line-height: 1.8;'>";
echo "<li>🔄 <strong>Loading Animation:</strong> Button shows spinning loader when clicked</li>";
echo "<li>📢 <strong>Notification System:</strong> Toast notifications appear in top-right corner</li>";
echo "<li>🎯 <strong>Button Feedback:</strong> Button scales down when clicked for tactile feedback</li>";
echo "<li>🔢 <strong>Cart Count Animation:</strong> Cart count bounces when updated</li>";
echo "<li>⏱️ <strong>Auto-hide Notifications:</strong> Notifications disappear after 3 seconds</li>";
echo "<li>🎨 <strong>Color-coded Messages:</strong> Success (green), Info (blue), Error (red)</li>";
echo "</ul>";
echo "</div>";

// Test 3: Show notification types
echo "<h3>3. Notification Types:</h3>";
echo "<div style='display: flex; gap: 15px; margin: 20px 0;'>";
echo "<button onclick='showTestNotification(\"✅ Item added to cart!\", \"success\")' style='background: #44D62C; color: black; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;'>Success Notification</button>";
echo "<button onclick='showTestNotification(\"ℹ️ Adding to cart...\", \"info\")' style='background: #2196F3; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;'>Info Notification</button>";
echo "<button onclick='showTestNotification(\"❌ Error adding to cart!\", \"error\")' style='background: #ff4444; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;'>Error Notification</button>";
echo "</div>";

// Test 4: Instructions
echo "<h3>4. How to Test:</h3>";
echo "<div style='background: #44D62C; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: black; margin-bottom: 15px;'>🚀 Testing Instructions:</h4>";
echo "<ol style='color: black; line-height: 1.8;'>";
echo "<li><strong>Go to Main Page:</strong> <a href='shop.php' target='_blank' style='color: black; font-weight: bold;'>shop.php</a></li>";
echo "<li><strong>Find Products:</strong> Look for product cards with 'Add to Cart' buttons</li>";
echo "<li><strong>Click Add to Cart:</strong> Click any 'Add to Cart' button</li>";
echo "<li><strong>Watch Triggers:</strong> Notice the loading animation and notification</li>";
echo "<li><strong>Check Cart Count:</strong> See the cart count update in the navigation</li>";
echo "<li><strong>Test Seller Shops:</strong> <a href='seller_shop.php?seller_id=1' target='_blank' style='color: black; font-weight: bold;'>Visit a seller shop</a> and test there too</li>";
echo "</ol>";
echo "</div>";

// Test 5: Technical details
echo "<h3>5. Technical Implementation:</h3>";
echo "<div style='background: #333; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: #44D62C; margin-bottom: 15px;'>🔧 What Was Added:</h4>";
echo "<ul style='color: #ccc; line-height: 1.8;'>";
echo "<li><strong>CSS Animations:</strong> Loading spinner, button click effects, cart count bounce</li>";
echo "<li><strong>JavaScript Events:</strong> Form submission handlers with visual feedback</li>";
echo "<li><strong>Notification System:</strong> Dynamic toast notifications with auto-hide</li>";
echo "<li><strong>Button States:</strong> Loading, disabled, and active states</li>";
echo "<li><strong>Responsive Design:</strong> Works on all screen sizes</li>";
echo "</ul>";
echo "</div>";

echo "<h3>🎉 Ready to Test!</h3>";
echo "<div style='background: #333; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #44D62C;'>";
echo "<strong>🚀 The Add to Cart trigger system is ready!</strong><br><br>";
echo "When users click 'Add to Cart', they will now see:<br>";
echo "• Loading animation on the button<br>";
echo "• Toast notification confirming the action<br>";
echo "• Cart count animation in the navigation<br>";
echo "• Smooth visual feedback throughout the process<br><br>";
echo "<strong>Test it now by going to the main page and clicking any 'Add to Cart' button!</strong>";
echo "</div>";

$conn->close();
?>

<script>
// Test functions for demonstration
function testAddToCart(productName) {
    showTestNotification('Adding "' + productName + '" to cart...', 'info');
    
    // Simulate loading
    setTimeout(() => {
        showTestNotification('✅ "' + productName + '" added to cart!', 'success');
    }, 1000);
}

function showTestNotification(message, type) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.test-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `test-notification notification ${type}`;
    notification.innerHTML = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '15px 25px';
    notification.style.borderRadius = '8px';
    notification.style.color = 'white';
    notification.style.fontWeight = 'bold';
    notification.style.zIndex = '10000';
    notification.style.transform = 'translateX(400px)';
    notification.style.transition = 'all 0.3s ease';
    notification.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.3)';
    
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #44D62C, #36b020)';
        notification.style.borderLeft = '4px solid #2a8a1a';
    } else if (type === 'info') {
        notification.style.background = 'linear-gradient(135deg, #2196F3, #1976D2)';
        notification.style.borderLeft = '4px solid #1565C0';
    } else if (type === 'error') {
        notification.style.background = 'linear-gradient(135deg, #ff4444, #cc3333)';
        notification.style.borderLeft = '4px solid #aa2222';
    }
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}
</script>
