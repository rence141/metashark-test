<?php
session_start();

// Debug: Log session data to verify variables
error_log("Profile.php session data: " . print_r($_SESSION, true));

// If user not logged in, redirect to login
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

// Set theme preference
if (isset($_GET['theme'])) {
    $new_theme = in_array($_GET['theme'], ['light', 'dark', 'device']) ? $_GET['theme'] : 'device';
    $_SESSION['theme'] = $new_theme;
} else {
    $theme = $_SESSION['theme'] ?? 'device'; // Default to 'device' if no theme is set
}

// Determine the effective theme for rendering
$effective_theme = $theme;
if ($theme === 'device') {
    $effective_theme = 'dark'; // Fallback; client-side JS will override based on prefers-color-scheme
}

include("db.php");

$user_id = $_SESSION["user_id"];

// Get cart count for display
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    if ($count_result->num_rows > 0) {
        $cart_data = $count_result->fetch_assoc();
        $cart_count = $cart_data['total'] ?: 0;
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $new_password = trim($_POST["password"]);
    $profile_image = null;

    // Handle image upload
    if (!empty($_FILES["profile_image"]["name"])) {
        $targetDir = "Uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Get current profile image to delete it later
        $currentImageQuery = "SELECT profile_image FROM users WHERE id = ?";
        $currentImageStmt = $conn->prepare($currentImageQuery);
        $currentImageStmt->bind_param("i", $user_id);
        $currentImageStmt->execute();
        $currentImageResult = $currentImageStmt->get_result();
        $currentImage = $currentImageResult->fetch_assoc()['profile_image'];

        $fileName = $user_id . "_" . time() . "_" . basename($_FILES["profile_image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        // Allow only jpg, jpeg, png
        $allowedTypes = ["jpg", "jpeg", "png"];
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFilePath)) {
                $profile_image = $fileName;
                
                // Delete old profile image if it exists
                if (!empty($currentImage) && file_exists($targetDir . $currentImage)) {
                    unlink($targetDir . $currentImage);
                }
            } else {
                $error = "Error uploading profile image.";
            }
        } else {
            $error = "Only JPG, JPEG, and PNG files are allowed.";
        }
    }

    // If password is provided, update with hashing
    if (!empty($new_password)) {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

        if ($profile_image) {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, password=?, profile_image=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $fullname, $email, $phone, $hashedPassword, $profile_image, $user_id);
        } else {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, password=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $fullname, $email, $phone, $hashedPassword, $user_id);
        }
    } else {
        if ($profile_image) {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, profile_image=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $fullname, $email, $phone, $profile_image, $user_id);
        } else {
            $sql = "UPDATE users SET fullname=?, email=?, phone=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
        }
    }

    if ($stmt->execute()) {
        $_SESSION["name"] = $fullname; // Update session name to align with shop.php
        $success = "Profile updated successfully!";
        
        // Log update
        if ($profile_image) {
            $success .= " Profile image updated to: " . $profile_image;
        }
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
}

// Fetch user info
$sql = "SELECT id, fullname, email, phone, profile_image FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get unread notification count
$notif_count = 0;
if(isset($_SESSION['user_id'])) {
    $notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    if ($notif_result->num_rows > 0) {
        $notif_data = $notif_result->fetch_assoc();
        $notif_count = $notif_data['count'];
    }
}

// Set default profile image path
$default_profile_image = "Uploads/Logo.png";

// Determine profile page based on role
$user_role = $_SESSION['role'] ?? 'buyer';
$profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
$current_user_id = $_SESSION['user_id'];
$profile_query = "SELECT profile_image FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $current_user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$current_profile = $profile_result->fetch_assoc();
$current_profile_image = $current_profile['profile_image'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user["fullname"]); ?> - Profile</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --background: #fff;
            --text-color: #333;
            --primary-color: #00ff88;
            --secondary-bg: #f8f9fa;
            --border-color: #dee2e6;
            --theme-menu: black;
            --theme-btn: black;
        }

        [data-theme="dark"] {
            --background: #000000ff;
            --text-color: #e0e0e0;
            --primary-color: #00ff88;
            --secondary-bg: #2a2a2a;
            --border-color: #444;
            --theme-menu: white;
            --theme-btn: white;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--background);
            color: var(--text-color);
            margin: 0;
            padding: 0;
        }

        /* Navigation Styles */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--background);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-left .logo {
            height: 40px;
            width: auto;
        }

        .nav-left h2 {
            margin: 0;
            color: var(--text-color);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .nonuser-text {
            padding: 8px 16px;
            background: var(--primary-color);
            color: #000;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nonuser-text:hover {
            background: #00cc6a;
            transform: translateY(-2px);
        }

        .hamburger {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-color);
            cursor: pointer;
        }

        .menu {
            position: absolute;
            top: 100%;
            right: 20px; /* Align under hamburger */
            width: 200px; /* Consistent width */
            background: var(--background);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            flex-direction: column;
            padding: 15px;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 999;
            pointer-events: none;
        }

        .menu.show {
            display: flex;
            pointer-events: auto;
        }

        .menu li {
            margin: 10px 0;
            list-style: none;
        }

        .menu li a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .menu li a:hover {
            color: var(--primary-color);
        }

        /* Theme Dropdown */
        .theme-dropdown {
            position: relative;
            display: inline-block;
        }

        .theme-btn {
            appearance: none;
            background: var(--theme-btn);
            color: var(--secondary-bg);
            border: 2px solid #006400;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            min-width: 120px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .theme-btn:hover {
            background: #006400;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 100, 0, 0.2);
        }

        .theme-dropdown:after {
            content: '\25BC';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--secondary-bg);
        }

        .theme-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--theme-menu);
            border: 2px solid rgba(0,255,136,0.3);
            border-radius: 12px;
            padding: 8px;
            min-width: 90px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            display: none;
            z-index: 1000;
        }

        .theme-dropdown.active .theme-menu {
            display: block;
        }

        .theme-option {
            width: 100%;
            padding: 10px 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            text-align: left;
            font-weight: 600;
            color: #ceccccff;
        }

        [data-theme="dark"] .theme-option {
            color: #3c3c3cff;
        }

        .theme-option:hover {
            background: rgba(0,255,136,0.08);
            color: #00aa55;
        }

        /* Profile Container Styles */
        .profile-container {
            max-width: 600px;
            width: 90%;
            margin: 50px auto;
            background: var(--background);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0px 6px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            display: block;
        }

        .profile-image-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .profile-container img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--primary-color);
        }

        .profile-image-container .edit-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            color: var(--primary-color);
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            padding: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-image-container:hover .edit-icon {
            opacity: 1;
        }

        .file-selected {
            margin-top: 10px;
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.8;
        }

        form input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            background: var(--background);
            color: var(--text-color);
        }

        .btn {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            background: var(--primary-color);
            color: #000;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #00cc6a;
            transform: translateY(-2px);
        }

        .message {
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .nav-right {
                gap: 10px;
            }

            .theme-btn {
                min-width: 100px;
                font-size: 12px;
            }

            .profile-container {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <!-- Theme dropdown -->
            <div class="theme-dropdown" id="themeDropdown">
                <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme">
                    <i class="bi theme-icon" id="themeIcon"></i>
                    <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($effective_theme === 'light' ? 'Dark' : 'Light'); ?></span>
                </button>
                <div class="theme-menu" id="themeMenu" aria-hidden="true">
                    <button class="theme-option" data-theme="light">Light</button>
                    <button class="theme-option" data-theme="dark">Dark</button>
                    <button class="theme-option" data-theme="device">Device</button>     
                </div>
            </div>
            
            <a href="notifications.php" title="Notifications" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-bell" style="font-size:18px;"></i>
                <span><?php echo $notif_count > 0 ? "($notif_count)" : ""; ?></span>
            </a>
            
            <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-cart" style="font-size:18px;"></i>
                <span>(<?php echo (int)$cart_count; ?>)</span>
            </a>
            
            <button class="hamburger">☰</button>
        </div>
        
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <li><a href="carts_users.php">Cart (<span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>)</a></li>
            <li><a href="order_status.php">My Purchases</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php
                $user_role = $_SESSION['role'] ?? 'buyer';
                ?>
                <?php if($user_role === 'seller' || $user_role === 'admin'): ?>
                    <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
                <?php else: ?>
                    <li><a href="become_seller.php">Become Seller</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="profile-container">
        <h1>Edit Profile</h1>

        <?php if (!empty($success)) echo "<div class='message success'>$success</div>"; ?>
        <?php if (!empty($error)) echo "<div class='message error'>$error</div>"; ?>

        <!-- Profile Picture -->
        <div class="profile-image-container" tabindex="0" aria-label="Click to change profile picture">
            <?php if (!empty($user["profile_image"]) && file_exists("Uploads/" . $user["profile_image"])): ?>
                <img src="Uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Picture" id="profileImage">
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($default_profile_image); ?>" alt="Default Profile Picture" id="profileImage">
            <?php endif; ?>
            <i class="bi bi-pencil edit-icon"></i>
        </div>
        <div class="file-selected" id="fileSelectedText"></div>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;">
            <!-- User ID Display (Read-only) -->
            <div style="background: var(--secondary-bg); padding: 12px; border-radius: 8px; margin: 10px 0; border: 1px solid var(--border-color);">
                <label style="display: block; font-weight: bold; color: var(--text-color); margin-bottom: 5px;">User ID:</label>
                <input type="text" value="<?php echo htmlspecialchars($user['id']); ?>" readonly 
                       style="width: 99%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--secondary-bg); color: var(--text-color); font-family: monospace;">
                <small style="color: var(--text-color); font-size: 0.8rem; opacity: 0.7;">This is your unique identifier</small>
            </div>
            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            <input type="password" name="password" placeholder="New Password (leave blank to keep current)">
            <button type="submit" class="btn">Update Profile</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle functionality
            const themeDropdown = document.getElementById('themeDropdown');
            const themeMenu = document.getElementById('themeMenu');
            const themeBtn = document.getElementById('themeDropdownBtn');
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            let currentTheme = '<?php echo htmlspecialchars($theme); ?>';
            
            // Initialize theme
            applyTheme(currentTheme);

            // Apply theme based on selection or system preference
            function applyTheme(theme) {
                let effectiveTheme = theme;
                if (theme === 'device') {
                    effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', effectiveTheme);
                updateThemeUI(theme, effectiveTheme);
                
                // Save theme to server
                fetch(`?theme=${theme}`, { method: 'GET' })
                    .catch(error => console.error('Error saving theme:', error));
            }

            // Update theme button UI
            function updateThemeUI(theme, effectiveTheme) {
                if (themeIcon && themeText) {
                    if (theme === 'device') {
                        themeIcon.className = 'bi theme-icon bi-laptop';
                        themeText.textContent = 'Device';
                    } else if (theme === 'dark') {
                        themeIcon.className = 'bi theme-icon bi-moon-fill';
                        themeText.textContent = 'Dark';
                    } else {
                        themeIcon.className = 'bi theme-icon bi-sun-fill';
                        themeText.textContent = 'Light';
                    }
                }
            }

            // Theme dropdown toggle
            if (themeBtn && themeDropdown) {
                themeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    themeDropdown.classList.toggle('active');
                });
            }

            // Theme option selection
            if (themeMenu) {
                themeMenu.addEventListener('click', (e) => {
                    const option = e.target.closest('.theme-option');
                    if (!option) return;
                    currentTheme = option.dataset.theme;
                    applyTheme(currentTheme);
                    themeDropdown.classList.remove('active');
                });
            }

            // Close theme menu when clicking outside
            document.addEventListener('click', (e) => {
                if (themeDropdown && !themeDropdown.contains(e.target)) {
                    themeDropdown.classList.remove('active');
                }
            });

            // Listen for system theme changes when 'device' is selected
            if (currentTheme === 'device') {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                mediaQuery.addEventListener('change', (e) => {
                    if (currentTheme === 'device') {
                        applyTheme('device');
                    }
                });
            }

            // Hamburger Menu Toggle
            const hamburger = document.querySelector('.hamburger');
            const menu = document.getElementById('menu');
            if (hamburger && menu) {
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    menu.classList.toggle('show');
                });
                
                document.addEventListener('click', function(e) {
                    if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });

                const menuItems = menu.querySelectorAll('a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        menu.classList.remove('show');
                    });
                });
            }

            // Profile Image File Selection
            const profileImageContainer = document.querySelector('.profile-image-container');
            const profileImageInput = document.getElementById('profileImageInput');
            const fileSelectedText = document.getElementById('fileSelectedText');

            if (profileImageContainer && profileImageInput && fileSelectedText) {
                // Click handler for image
                profileImageContainer.addEventListener('click', function() {
                    console.log('Profile image clicked, triggering file input');
                    profileImageInput.click();
                });

                // Keyboard handler for accessibility
                profileImageContainer.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        console.log('Profile image activated via keyboard, triggering file input');
                        profileImageInput.click();
                    }
                });

                // Display selected file name
                profileImageInput.addEventListener('change', function() {
                    if (profileImageInput.files.length > 0) {
                        fileSelectedText.textContent = `Selected: ${profileImageInput.files[0].name}`;
                    } else {
                        fileSelectedText.textContent = '';
                    }
                });
            }
        });
    </script>
</body>
</html>