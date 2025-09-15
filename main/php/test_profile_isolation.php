<?php
// Simple test to check profile image isolation
include("db.php");

echo "<h2>🔍 Profile Image Isolation Test</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .test-box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .user-info { background: #e8f4fd; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; }
    .error { background: #ffebee; padding: 15px; margin: 10px 0; border-left: 4px solid #f44336; }
    .success { background: #e8f5e8; padding: 15px; margin: 10px 0; border-left: 4px solid #4caf50; }
</style>";

// Get users with the specific emails you mentioned
$emails = ['prepotetnelroenze@gmail.com', 'lroenzez0987@gmail.com'];

foreach ($emails as $email) {
    echo "<div class='test-box'>";
    echo "<h3>📧 Testing: $email</h3>";
    
    $sql = "SELECT id, fullname, email, role, profile_image FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($user = $result->fetch_assoc()) {
            echo "<div class='user-info'>";
            echo "<strong>User ID:</strong> {$user['id']}<br>";
            echo "<strong>Name:</strong> {$user['fullname']}<br>";
            echo "<strong>Email:</strong> {$user['email']}<br>";
            echo "<strong>Role:</strong> {$user['role']}<br>";
            echo "<strong>Profile Image:</strong> " . ($user['profile_image'] ?: 'None') . "<br>";
            
            if ($user['profile_image']) {
                $imagePath = 'uploads/' . $user['profile_image'];
                if (file_exists($imagePath)) {
                    echo "<strong>Image Status:</strong> ✅ File exists<br>";
                    echo "<strong>Image Preview:</strong><br>";
                    echo "<img src='$imagePath' style='max-width: 100px; max-height: 100px; border: 2px solid #ddd; border-radius: 8px; margin: 10px 0;'>";
                } else {
                    echo "<strong>Image Status:</strong> ❌ File missing<br>";
                }
            }
            echo "</div>";
        }
    } else {
        echo "<div class='error'>❌ No user found with email: $email</div>";
    }
    echo "</div>";
}

// Check for any users sharing the same profile image
echo "<div class='test-box'>";
echo "<h3>🔍 Checking for Shared Profile Images</h3>";

$sql = "SELECT profile_image, COUNT(*) as count, GROUP_CONCAT(CONCAT(id, ':', fullname, ':', email) SEPARATOR ' | ') as users 
        FROM users 
        WHERE profile_image IS NOT NULL AND profile_image != '' 
        GROUP BY profile_image 
        HAVING count > 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<div class='error'>";
    echo "<strong>⚠️ FOUND SHARED PROFILE IMAGES:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "Image: <strong>{$row['profile_image']}</strong> is used by {$row['count']} users:<br>";
        echo "Users: {$row['users']}<br><br>";
    }
    echo "</div>";
} else {
    echo "<div class='success'>✅ No shared profile images found - each image is unique to one user.</div>";
}

echo "</div>";

$conn->close();
?>
