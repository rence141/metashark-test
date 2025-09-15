<?php
// Visual guide for switching between accounts
session_start();
include('db.php');

echo "<h2>🔄 How to Switch Between Accounts</h2>";

// Show current user
if (isset($_SESSION['user_id'])) {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>👤 Currently Logged In As:</h3>";
    echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($_SESSION['fullname'] ?? 'Not set') . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email'] ?? 'Not set') . "</p>";
    echo "<p><strong>Role:</strong> " . htmlspecialchars($_SESSION['role'] ?? 'Not set') . "</p>";
    echo "</div>";
} else {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>❌ No User Logged In</h3>";
    echo "<p>You are not currently logged in.</p>";
    echo "</div>";
}

echo "<h3>📋 Steps to Switch Accounts:</h3>";
echo "<ol style='font-size: 16px; line-height: 1.6;'>";
echo "<li><strong>Click the hamburger menu (☰)</strong> in the top-right corner</li>";
echo "<li><strong>Click 'Logout'</strong> from the dropdown menu</li>";
echo "<li><strong>You'll be redirected</strong> to the login page</li>";
echo "<li><strong>Enter different credentials</strong> for the other account</li>";
echo "<li><strong>Click 'Login'</strong> to access the other account</li>";
echo "</ol>";

echo "<h3>🔒 Why This is Secure:</h3>";
echo "<ul style='font-size: 16px; line-height: 1.6;'>";
echo "<li>✅ <strong>Session Persistence:</strong> Keeps you logged in until you logout</li>";
echo "<li>✅ <strong>Prevents Unauthorized Access:</strong> Others can't access your account</li>";
echo "<li>✅ <strong>Proper Session Management:</strong> Logout destroys the session completely</li>";
echo "<li>✅ <strong>Account Isolation:</strong> Each account is completely separate</li>";
echo "</ul>";

echo "<h3>🎯 Quick Actions:</h3>";
echo "<div style='margin: 20px 0;'>";
if (isset($_SESSION['user_id'])) {
    echo "<a href='logout.php' style='background: #ff4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🚪 Logout Now</a>";
    echo "<a href='login_users.php' style='background: #44D62C; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔑 Go to Login</a>";
} else {
    echo "<a href='login_users.php' style='background: #44D62C; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔑 Login</a>";
}
echo "</div>";

echo "<h3>📊 All Users in System:</h3>";
$result = $conn->query("SELECT id, fullname, email, role FROM users ORDER BY id");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #333;'>";
    echo "<th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Current Session</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $isCurrentUser = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['id'];
        $rowStyle = $isCurrentUser ? "background-color: #333;" : "";
        
        echo "<tr style='$rowStyle'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . ($isCurrentUser ? "✅ CURRENT USER" : "❌ Not logged in") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found in database.</p>";
}

echo "<h3>💡 Pro Tips:</h3>";
echo "<ul style='font-size: 16px; line-height: 1.6;'>";
echo "<li>🔄 <strong>Always logout</strong> before switching accounts</li>";
echo "<li>🔒 <strong>Don't share accounts</strong> - each user should have their own</li>";
echo "<li>💾 <strong>Sessions persist</strong> until you logout or close browser</li>";
echo "<li>🛡️ <strong>This is secure behavior</strong> - prevents unauthorized access</li>";
echo "</ul>";

$conn->close();
?>
