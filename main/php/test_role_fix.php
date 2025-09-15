<?php
// Test script to verify the role column fix
include('db.php');

echo "<h2>🔧 Testing Role Column Fix</h2>";
echo "<style>
body { font-family: Arial, sans-serif; background: #0A0A0A; color: #FFFFFF; padding: 20px; }
.success { background: #44D62C; color: black; padding: 10px; border-radius: 5px; margin: 10px 0; }
.error { background: #ff4444; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
.info { background: #2196F3; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

// Test 1: Check if role column exists
echo "<h3>1. Checking Database Structure:</h3>";
$check_columns = "SHOW COLUMNS FROM users LIKE 'role'";
$column_result = $conn->query($check_columns);

if ($column_result && $column_result->num_rows > 0) {
    echo "<div class='success'>✅ 'role' column exists in users table</div>";
} else {
    echo "<div class='error'>❌ 'role' column does not exist</div>";
    echo "<div class='info'>You may need to run the database migration scripts to add the role column.</div>";
}

// Test 2: Check current users and their roles
echo "<h3>2. Current Users and Roles:</h3>";
$users_query = "SELECT id, username, email, role FROM users LIMIT 5";
$users_result = $conn->query($users_query);

if ($users_result && $users_result->num_rows > 0) {
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #444;'><th style='border: 1px solid #666; padding: 8px;'>ID</th><th style='border: 1px solid #666; padding: 8px;'>Username</th><th style='border: 1px solid #666; padding: 8px;'>Email</th><th style='border: 1px solid #666; padding: 8px;'>Role</th></tr>";
    
    while ($row = $users_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . $row['id'] . "</td>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td style='border: 1px solid #666; padding: 8px;'>" . ($row['role'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ No users found</div>";
}

// Test 3: Test the fixed query
echo "<h3>3. Testing Fixed Query:</h3>";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $test_query = "SELECT role FROM users WHERE id = ?";
    $test_stmt = $conn->prepare($test_query);
    $test_stmt->bind_param("i", $user_id);
    $test_stmt->execute();
    $test_result = $test_stmt->get_result();
    $user_role = $test_result->fetch_assoc()['role'] ?? 'buyer';
    
    echo "<div class='success'>✅ Query executed successfully</div>";
    echo "<div class='info'>Your role: <strong>" . $user_role . "</strong></div>";
    
    if ($user_role === 'seller' || $user_role === 'admin') {
        echo "<div class='success'>✅ You are a seller - can add products</div>";
    } else {
        echo "<div class='info'>ℹ️ You are a buyer - can become a seller</div>";
    }
} else {
    echo "<div class='info'>ℹ️ Not logged in - please login to test role functionality</div>";
}

// Test 4: Check if the main page will work now
echo "<h3>4. Main Page Test:</h3>";
echo "<div class='info'>";
echo "The main page should now work without the 'is_seller' column error.<br>";
echo "Try visiting: <a href='shop.php' style='color: #44D62C;'>shop.php</a>";
echo "</div>";

$conn->close();
?>
