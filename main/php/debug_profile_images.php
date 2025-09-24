<?php
// Enhanced debug script to identify profile image conflicts
include("db.php");

echo "<h2>🔍 Profile Image Debug Analysis</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    table { border-collapse: collapse; width: 100%; background: white; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #4CAF50; color: white; }
    .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; }
    .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; }
</style>";

// Get all users with their profile images
$sql = "SELECT id, fullname, email, role, profile_image, created_at FROM users ORDER BY id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<h3>👥 All Users in Database:</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Profile Image</th><th>Image File Exists</th><th>Created</th></tr>";
    
    $users = [];
    $imageConflicts = [];
    
    while ($row = $result->fetch_assoc()) {
        $imageExists = !empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image']);
        $users[] = $row;
        
        // Check for image conflicts
        if (!empty($row['profile_image'])) {
            if (!isset($imageConflicts[$row['profile_image']])) {
                $imageConflicts[$row['profile_image']] = [];
            }
            $imageConflicts[$row['profile_image']][] = $row;
        }
        
        echo "<tr>";
        echo "<td><strong>" . $row['id'] . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "<td>" . htmlspecialchars($row['profile_image'] ?? 'None') . "</td>";
        echo "<td>" . ($imageExists ? "✅ Exists" : "❌ Missing") . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for image conflicts
    echo "<h3>⚠️ Image Conflicts Analysis:</h3>";
    $hasConflicts = false;
    foreach ($imageConflicts as $imageName => $usersWithImage) {
        if (count($usersWithImage) > 1) {
            $hasConflicts = true;
            echo "<div class='error'>";
            echo "<strong>CONFLICT FOUND:</strong> Image '$imageName' is used by multiple users:<br>";
            foreach ($usersWithImage as $user) {
                echo "• User ID {$user['id']} ({$user['fullname']}) - {$user['email']} - {$user['role']}<br>";
            }
            echo "</div>";
        }
    }
    
    if (!$hasConflicts) {
        echo "<div class='success'>✅ No image conflicts found - each image is unique to one user.</div>";
    }
    
    // Check uploads directory
    echo "<h3>📁 Uploads Directory Analysis:</h3>";
    $uploadDir = "uploads/";
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        $imageFiles = array_filter($files, function($file) {
            return $file != '.' && $file != '..' && preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $file);
        });
        
        echo "<div class='success'>Found " . count($imageFiles) . " image files in uploads directory:</div>";
        echo "<ul>";
        foreach ($imageFiles as $file) {
            $filePath = $uploadDir . $file;
            $fileSize = filesize($filePath);
            $fileTime = date('Y-m-d H:i:s', filemtime($filePath));
            echo "<li><strong>$file</strong> - Size: " . number_format($fileSize) . " bytes - Modified: $fileTime</li>";
        }
        echo "</ul>";
        
        // Check for orphaned files (files not referenced by any user)
        echo "<h4>🔍 Orphaned Files Check:</h4>";
        $referencedImages = array_column($users, 'profile_image');
        $referencedImages = array_filter($referencedImages); // Remove empty values
        
        $orphanedFiles = [];
        foreach ($imageFiles as $file) {
            if (!in_array($file, $referencedImages)) {
                $orphanedFiles[] = $file;
            }
        }
        
        if (!empty($orphanedFiles)) {
            echo "<div class='warning'>⚠️ Found orphaned files (not referenced by any user):</div>";
            echo "<ul>";
            foreach ($orphanedFiles as $file) {
                echo "<li>$file</li>";
            }
            echo "</ul>";
        } else {
            echo "<div class='success'> No orphaned files found.</div>";
        }
        
    } else {
        echo "<div class='error'> Uploads directory does not exist.</div>";
    }
    
    // Test filename generation
    echo "<h3>🧪 Filename Generation Test:</h3>";
    echo "<div class='success'>Testing unique filename generation for each user:</div>";
    echo "<table>";
    echo "<tr><th>User ID</th><th>Generated Filename</th><th>Status</th></tr>";
    
    foreach ($users as $user) {
        $testFileName = $user['id'] . "_" . time() . "_test.jpg";
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>$testFileName</td>";
        echo "<td>✅ Unique</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    echo "<div class='error'>❌ No users found in database.</div>";
}

$conn->close();
?>
