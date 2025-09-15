<?php
// Comprehensive test to identify profile image sharing issue
session_start();
include('db.php');

echo "<h2>🔍 Profile Image Sharing Investigation</h2>";

// Test 1: Show current session
echo "<h3>1. Current Session:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Logged in as:</strong><br>";
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Name: " . htmlspecialchars($_SESSION['fullname'] ?? 'Not set') . "<br>";
    echo "Email: " . htmlspecialchars($_SESSION['email'] ?? 'Not set') . "<br>";
    echo "Role: " . htmlspecialchars($_SESSION['role'] ?? 'Not set') . "<br>";
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No user logged in";
    echo "</div>";
}

// Test 2: Show all users and their profile images
echo "<h3>2. All Users and Their Profile Images:</h3>";
$result = $conn->query("SELECT id, fullname, email, role, profile_image FROM users ORDER BY id");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #333;'>";
    echo "<th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Profile Image</th><th>File Exists</th><th>Preview</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $isCurrentUser = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['id'];
        $rowStyle = $isCurrentUser ? "background-color: #333;" : "";
        
        $imageExists = !empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image']);
        $imagePreview = '';
        
        if ($imageExists) {
            $imagePreview = "<img src='uploads/" . htmlspecialchars($row['profile_image']) . "' style='width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #44D62C;'>";
        } else {
            $imagePreview = "❌ No Image";
        }
        
        echo "<tr style='$rowStyle'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['profile_image'] ?? 'None') . "</td>";
        echo "<td>" . ($imageExists ? "✅ Yes" : "❌ No") . "</td>";
        echo "<td>" . $imagePreview . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found.</p>";
}

// Test 3: Check for duplicate profile images
echo "<h3>3. Checking for Duplicate Profile Images:</h3>";
$sql = "SELECT profile_image, COUNT(*) as count, GROUP_CONCAT(CONCAT(id, ':', fullname, ':', email) SEPARATOR ' | ') as users 
        FROM users 
        WHERE profile_image IS NOT NULL AND profile_image != '' 
        GROUP BY profile_image 
        HAVING count > 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>❌ DUPLICATE PROFILE IMAGES FOUND!</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "<strong>Image:</strong> " . htmlspecialchars($row['profile_image']) . "<br>";
        echo "<strong>Used by:</strong> " . htmlspecialchars($row['users']) . "<br>";
        echo "<strong>Count:</strong> " . $row['count'] . "<br><br>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "✅ No duplicate profile images found";
    echo "</div>";
}

// Test 4: Check uploads directory
echo "<h3>4. Uploads Directory Contents:</h3>";
$uploadDir = "uploads/";
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Files in uploads/ directory:</strong><br>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "📁 " . htmlspecialchars($file) . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ Uploads directory does not exist";
    echo "</div>";
}

// Test 5: Test profile image display logic
echo "<h3>5. Testing Profile Image Display Logic:</h3>";
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $profile_query = "SELECT profile_image FROM users WHERE id = ?";
    $profile_stmt = $conn->prepare($profile_query);
    $profile_stmt->bind_param("i", $current_user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    $current_profile = $profile_result->fetch_assoc();
    $current_profile_image = $current_profile['profile_image'] ?? null;
    
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>Current User's Profile Image Logic:</strong><br>";
    echo "User ID: " . $current_user_id . "<br>";
    echo "Profile Image Filename: " . ($current_profile_image ?: 'None') . "<br>";
    echo "File Exists: " . (!empty($current_profile_image) && file_exists('uploads/' . $current_profile_image) ? '✅ Yes' : '❌ No') . "<br>";
    
    if (!empty($current_profile_image) && file_exists('uploads/' . $current_profile_image)) {
        echo "Image Preview:<br>";
        echo "<img src='uploads/" . htmlspecialchars($current_profile_image) . "' style='width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #44D62C; margin: 10px 0;'>";
    } else {
        echo "Default Avatar:<br>";
        echo "<img src='uploads/default-avatar.svg' style='width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 2px solid #44D62C; margin: 10px 0;'>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No user logged in to test";
    echo "</div>";
}

// Test 6: Check if there's a caching issue
echo "<h3>6. Browser Cache Check:</h3>";
echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Possible Causes:</strong><br>";
echo "1. 🔄 <strong>Browser Cache:</strong> Your browser might be caching the old image<br>";
echo "2. 📁 <strong>File Overwrite:</strong> New image might be overwriting the old one<br>";
echo "3. 🔗 <strong>Session Issue:</strong> Session might not be updating properly<br>";
echo "4. 🗂️ <strong>Database Issue:</strong> Database might not be updating correctly<br>";
echo "</div>";

echo "<h3>7. Solutions to Try:</h3>";
echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>✅ Try these steps:</strong><br>";
echo "1. 🔄 <strong>Hard Refresh:</strong> Press Ctrl+F5 to clear browser cache<br>";
echo "2. 🚪 <strong>Logout and Login:</strong> Use the logout button, then login again<br>";
echo "3. 🧹 <strong>Clear Browser Data:</strong> Clear cookies and cache for localhost<br>";
echo "4. 🔍 <strong>Check Database:</strong> Verify the profile_image field in database<br>";
echo "</div>";

$conn->close();
?>
