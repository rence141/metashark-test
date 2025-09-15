<?php
// Debug script to check user profiles and profile images
include("db.php");

echo "<h2>User Profile Debug</h2>";

// Get all users with their profile images
$sql = "SELECT id, fullname, email, role, profile_image FROM users ORDER BY id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Profile Image</th><th>Image File</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $imageExists = !empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image']);
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['profile_image'] ?? 'None') . "</td>";
        echo "<td>" . ($imageExists ? "✅ Exists" : "❌ Missing") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No users found.";
}

// Check uploads directory
echo "<h3>Uploads Directory Contents:</h3>";
$uploadDir = "uploads/";
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "Uploads directory does not exist.";
}

$conn->close();
?>
