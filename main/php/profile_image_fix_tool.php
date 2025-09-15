<?php
// Enhanced profile image fix with better isolation
session_start();
include('db.php');

echo "<h2>🔧 Profile Image Fix Tool</h2>";

if (!isset($_SESSION['user_id'])) {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ Please log in first to use this tool.";
    echo "</div>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user info
$user_query = "SELECT id, fullname, email, profile_image FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();

echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>Current User:</h3>";
echo "ID: " . $current_user['id'] . "<br>";
echo "Name: " . htmlspecialchars($current_user['fullname']) . "<br>";
echo "Email: " . htmlspecialchars($current_user['email']) . "<br>";
echo "Current Profile Image: " . ($current_user['profile_image'] ?: 'None') . "<br>";
echo "</div>";

// Handle image upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["new_image"])) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Generate unique filename with user ID and timestamp
    $originalName = basename($_FILES["new_image"]["name"]);
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $uniqueFileName = $user_id . "_" . time() . "_" . uniqid() . "." . $fileExtension;
    $targetFilePath = $targetDir . $uniqueFileName;

    // Validate file type
    $allowedTypes = ["jpg", "jpeg", "png", "gif"];
    if (in_array($fileExtension, $allowedTypes)) {
        if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $targetFilePath)) {
            // Delete old image if it exists
            if (!empty($current_user['profile_image']) && file_exists($targetDir . $current_user['profile_image'])) {
                unlink($targetDir . $current_user['profile_image']);
                echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "✅ Old image deleted: " . htmlspecialchars($current_user['profile_image']);
                echo "</div>";
            }

            // Update database
            $update_query = "UPDATE users SET profile_image = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $uniqueFileName, $user_id);
            
            if ($update_stmt->execute()) {
                echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "✅ Profile image updated successfully!<br>";
                echo "New filename: " . htmlspecialchars($uniqueFileName) . "<br>";
                echo "File saved to: " . htmlspecialchars($targetFilePath);
                echo "</div>";
                
                // Refresh user data
                $current_user['profile_image'] = $uniqueFileName;
            } else {
                echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "❌ Database update failed: " . $conn->error;
                echo "</div>";
            }
        } else {
            echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "❌ File upload failed";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "❌ Invalid file type. Only JPG, PNG, and GIF are allowed.";
        echo "</div>";
    }
}

// Show current image
echo "<h3>Current Profile Image:</h3>";
if (!empty($current_user['profile_image']) && file_exists('uploads/' . $current_user['profile_image'])) {
    echo "<div style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<img src='uploads/" . htmlspecialchars($current_user['profile_image']) . "' style='width: 200px; height: 200px; object-fit: cover; border-radius: 50%; border: 3px solid #44D62C;'>";
    echo "<br><br>";
    echo "<strong>Filename:</strong> " . htmlspecialchars($current_user['profile_image']) . "<br>";
    echo "<strong>File Path:</strong> uploads/" . htmlspecialchars($current_user['profile_image']) . "<br>";
    echo "<strong>File Size:</strong> " . number_format(filesize('uploads/' . $current_user['profile_image']) / 1024, 2) . " KB";
    echo "</div>";
} else {
    echo "<div style='background: #ff4444; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "❌ No profile image found";
    echo "</div>";
}

// Upload form
echo "<h3>Upload New Profile Image:</h3>";
echo "<form method='POST' enctype='multipart/form-data' style='background: #333; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<input type='file' name='new_image' accept='image/*' required style='margin: 10px 0; padding: 10px; border: 2px solid #44D62C; border-radius: 5px; background: #111; color: white;'>";
echo "<br>";
echo "<button type='submit' style='background: #44D62C; color: black; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;'>Upload New Image</button>";
echo "</form>";

// Show all users for comparison
echo "<h3>All Users Comparison:</h3>";
$all_users_query = "SELECT id, fullname, email, profile_image FROM users ORDER BY id";
$all_users_result = $conn->query($all_users_query);

if ($all_users_result && $all_users_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #333;'>";
    echo "<th>ID</th><th>Name</th><th>Email</th><th>Profile Image</th><th>Preview</th>";
    echo "</tr>";
    
    while ($row = $all_users_result->fetch_assoc()) {
        $isCurrentUser = $row['id'] == $user_id;
        $rowStyle = $isCurrentUser ? "background-color: #333;" : "";
        
        $imagePreview = '';
        if (!empty($row['profile_image']) && file_exists('uploads/' . $row['profile_image'])) {
            $imagePreview = "<img src='uploads/" . htmlspecialchars($row['profile_image']) . "' style='width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #44D62C;'>";
        } else {
            $imagePreview = "❌ No Image";
        }
        
        echo "<tr style='$rowStyle'>";
        echo "<td>" . $row['id'] . ($isCurrentUser ? " (YOU)" : "") . "</td>";
        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['profile_image'] ?? 'None') . "</td>";
        echo "<td>" . $imagePreview . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>💡 Instructions:</h3>";
echo "<div style='background: #44D62C; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>To test profile image isolation:</strong><br>";
echo "1. Upload a new image using the form above<br>";
echo "2. Logout and login with a different account<br>";
echo "3. Upload a different image for that account<br>";
echo "4. Logout and login back to this account<br>";
echo "5. Verify you see your original image<br>";
echo "</div>";

$conn->close();
?>
