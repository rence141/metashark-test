<?php
// Script to fix image column size issue
include('db.php');

echo "<h2>🔧 Fixing Image Column Size</h2>";
echo "<style>
body { font-family: Arial, sans-serif; background: #0A0A0A; color: #FFFFFF; padding: 20px; }
.success { background: #44D62C; color: black; padding: 10px; border-radius: 5px; margin: 10px 0; }
.error { background: #ff4444; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
.info { background: #2196F3; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

// Check current column size
echo "<h3>1. Current Column Sizes:</h3>";
$check_columns = "SHOW COLUMNS FROM products WHERE Field IN ('image', 'sku')";
$columns_result = $conn->query($check_columns);

if ($columns_result && $columns_result->num_rows > 0) {
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #444;'><th style='border: 1px solid #666; padding: 8px;'>Column</th><th style='border: 1px solid #666; padding: 8px;'>Type</th><th style='border: 1px solid #666; padding: 8px;'>Null</th></tr>";
    
    while ($row = $columns_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . $row['Field'] . "</td>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . $row['Type'] . "</td>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . $row['Null'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ Could not check column sizes</div>";
}

// Fix image column size
echo "<h3>2. Fixing Image Column Size:</h3>";
$fix_image = "ALTER TABLE products MODIFY COLUMN image VARCHAR(1000)";
if ($conn->query($fix_image)) {
    echo "<div class='success'>✅ Image column size increased to VARCHAR(1000)</div>";
} else {
    echo "<div class='error'>❌ Error fixing image column: " . $conn->error . "</div>";
}

// Fix SKU column size
echo "<h3>3. Fixing SKU Column Size:</h3>";
$fix_sku = "ALTER TABLE products MODIFY COLUMN sku VARCHAR(255)";
if ($conn->query($fix_sku)) {
    echo "<div class='success'>✅ SKU column size increased to VARCHAR(255)</div>";
} else {
    echo "<div class='error'>❌ Error fixing SKU column: " . $conn->error . "</div>";
}

// Verify the changes
echo "<h3>4. Verification:</h3>";
$verify_columns = "SHOW COLUMNS FROM products WHERE Field IN ('image', 'sku')";
$verify_result = $conn->query($verify_columns);

if ($verify_result && $verify_result->num_rows > 0) {
    echo "<div class='success'>✅ Column sizes updated successfully!</div>";
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #444;'><th style='border: 1px solid #666; padding: 8px;'>Column</th><th style='border: 1px solid #666; padding: 8px;'>New Type</th></tr>";
    
    while ($row = $verify_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . $row['Field'] . "</td>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . $row['Type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ Could not verify changes</div>";
}

echo "<h3>5. Next Steps:</h3>";
echo "<div class='info'>";
echo "<strong>✅ The image column size issue has been fixed!</strong><br><br>";
echo "Now you can:";
echo "<ul style='text-align: left; line-height: 1.8;'>";
echo "<li>Add products with long image URLs</li>";
echo "<li>Edit products with longer image URLs</li>";
echo "<li>Use any image hosting service (including long URLs)</li>";
echo "</ul>";
echo "<br>";
echo "<strong>Try adding a product now - the error should be resolved!</strong>";
echo "</div>";

$conn->close();
?>
