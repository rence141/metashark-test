<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION["user_id"];

// Check if user has seller/admin role
$user_sql = "SELECT role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data['role'] !== 'seller' && $user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

$success = "";
$error = "";
$product = null;

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id <= 0) {
    header("Location: seller_dashboard.php");
    exit();
}

// Fetch product details
$product_sql = "SELECT * FROM products WHERE id = ? AND seller_id = ?";
$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param("ii", $product_id, $user_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
if ($product_result->num_rows === 0) {
    header("Location: seller_dashboard.php");
    exit();
}
$product = $product_result->fetch_assoc();

// Fetch current categories
$cat_stmt = $conn->prepare("SELECT c.name FROM product_categories pc 
                            JOIN categories c ON pc.category_id = c.id 
                            WHERE pc.product_id = ?");
$cat_stmt->bind_param("i", $product_id);
$cat_stmt->execute();
$cat_res = $cat_stmt->get_result();
$current_categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $current_categories[] = $row['name'];
}

// Fetch current specs
$spec_stmt = $conn->prepare("SELECT * FROM product_specs WHERE product_id=?");
$spec_stmt->bind_param("i", $product_id);
$spec_stmt->execute();
$spec_result = $spec_stmt->get_result();
$current_specs = [];
while ($row = $spec_result->fetch_assoc()) {
    $current_specs[] = $row;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $categories = $_POST['categories'] ?? [];
    $price = floatval($_POST["price"]);
    $stock_quantity = intval($_POST["stock_quantity"]);
    $sku = trim($_POST["sku"]);
    $image_url = trim($_POST["image_url"]);
    $is_active = isset($_POST["is_active"]) ? 1 : 0;
    $is_featured = isset($_POST["is_featured"]) ? 1 : 0;

    // Validate
    if (empty($name) || $price <= 0 || empty($categories)) {
        $error = "Please fill in all required fields with valid values and select at least one category.";
    } elseif (strlen($sku) > 255) {
        $error = "SKU is too long. Please use a shorter product code.";
    } else {
        // Generate SKU if not provided
        if (empty($sku)) {
            $sku = "SKU-" . time() . "-" . rand(100, 999);
        }

        // Handle local image upload
        $image_path = "";
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image_file']['tmp_name'];
            $file_name = basename($_FILES['image_file']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];

            if (in_array($file_ext, $allowed)) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $target_file = $target_dir . time() . "_" . preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $file_name);
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error = "Failed to upload the image.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
            }
        }

        // If no new upload, fallback to URL or keep current
        if (empty($image_path)) {
            $image_path = !empty($image_url) ? $image_url : $product['image'];
        }

        if (empty($error)) {
            // Update product info
            $update_sql = "UPDATE products SET 
                           name=?, description=?, price=?, image=?, sku=?, stock_quantity=?, 
                           is_active=?, is_featured=?, updated_at=CURRENT_TIMESTAMP
                           WHERE id=? AND seller_id=?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssdssiiiii", 
                $name, $description, $price, $image_path, $sku, 
                $stock_quantity, $is_active, $is_featured, $product_id, $user_id);
            $update_stmt->execute();

            // Clear old categories
            $conn->query("DELETE FROM product_categories WHERE product_id = $product_id");

            // Insert new categories
            foreach ($categories as $cat_name) {
                $cat_stmt = $conn->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
                $cat_stmt->bind_param("s", $cat_name);
                $cat_stmt->execute();
                $cat_res = $cat_stmt->get_result();
                if ($cat_row = $cat_res->fetch_assoc()) {
                    $cat_id = $cat_row['id'];
                    $insert_cat = $conn->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                    $insert_cat->bind_param("ii", $product_id, $cat_id);
                    $insert_cat->execute();
                }
            }

            // Handle specs: delete all old and insert new
            $conn->query("DELETE FROM product_specs WHERE product_id = $product_id");
            if (!empty($_POST['spec_name']) && !empty($_POST['spec_value'])) {
                $spec_names = $_POST['spec_name'];
                $spec_values = $_POST['spec_value'];
                
                for ($i = 0; $i < count($spec_names); $i++) {
                    $spec_name = trim($spec_names[$i]);
                    $spec_value = trim($spec_values[$i]);
                    if (!empty($spec_name) && !empty($spec_value)) {
                        $spec_stmt = $conn->prepare("INSERT INTO product_specs (product_id, spec_name, spec_value) VALUES (?, ?, ?)");
                        $spec_stmt->bind_param("iss", $product_id, $spec_name, $spec_value);
                        $spec_stmt->execute();
                    }
                }
            }

            $success = "Product updated successfully!";
            // Refresh product and specs
            header("Location: edit_product.php?id=$product_id&success=1");
            exit();
        }
    }
}

// Fetch all available categories
$all_categories_sql = "SELECT name FROM categories ORDER BY name";
$all_categories_result = $conn->query($all_categories_sql);
$all_categories = [];
while ($row = $all_categories_result->fetch_assoc()) {
    $all_categories[] = $row['name'];
}

$theme = $_SESSION['theme'] ?? 'dark';

// Fetch notification count
$notif_count = 0;
$notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_result->num_rows > 0) {
    $notif_data = $notif_result->fetch_assoc();
    $notif_count = $notif_data['count'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - MetaAccessories</title>
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
            background: var(--background);
            color: var(--text-color);
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 0;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--background);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-left .logo {
            height: 40px;
        }

        .nav-left h2 {
            margin: 0;
            font-size: 24px;
            color: var(--text-color);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

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
            box-shadow: 0 8px 16px rgba(0,100,0,0.2);
        }

        .theme-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
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
            transition: background 0.2s ease, color 0.2s ease;
        }

        [data-theme="dark"] .theme-option {
            color: #3c3c3cff;
        }

        .theme-option:hover {
            background: rgba(0,255,136,0.08);
            color: #00aa55;
        }

        .theme-option:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .nonuser-text {
            font-size: 16px;
            color: var(--text-color);
            text-decoration: none;
        }

        .hamburger {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-color);
            transition: color 0.3s ease;
        }

        .hamburger:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        .menu {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            background: var(--background);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            list-style: none;
            margin: 0;
            z-index: 1000;
            transform: translateY(-10px);
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .menu.show {
            display: block;
            transform: translateY(0);
            opacity: 1;
        }

        .menu li {
            margin: 10px 0;
        }

        .menu li a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 4px;
            transition: color 0.2s ease, background 0.2s ease;
        }

        .menu li a:hover {
            color: #27ed15;
            background: var(--secondary-bg);
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--secondary-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-title {
            font-size: 24px;
            margin: 0;
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--background);
            color: var(--text-color);
            font-size: 16px;
            transition: border-color 0.3s ease, background 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 5px rgba(0,255,136,0.3);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input[type="checkbox"] {
            transform: scale(1.2);
            margin-right: 8px;
            cursor: pointer;
        }

        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            font-weight: 500;
            color: var(--text-color);
        }

        .spec-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .spec-row input {
            flex: 1;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: #000;
        }

        .btn-primary:hover {
            background: #00cc6a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,255,136,0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(0,255,136,0.2);
            color: #00aa55;
            border: 1px solid #00aa55;
        }

        .alert-error {
            background: rgba(220,53,69,0.2);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .spec-row {
                flex-direction: column;
            }

            .spec-row input {
                width: 100%;
            }

            .navbar {
                flex-wrap: wrap;
            }

            .nav-right {
                gap: 8px;
            }

            .theme-btn {
                min-width: 100px;
                font-size: 12px;
            }

            .profile-icon {
                width: 32px;
                height: 32px;
            }

            .menu {
                width: 100%;
                right: 0;
                top: 70px;
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <div class="navbar" role="navigation" aria-label="Main navigation">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <!-- Theme dropdown -->
            <div class="theme-dropdown" id="themeDropdown">
                <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme" aria-haspopup="true" aria-expanded="false">
                    <i class="bi theme-icon" id="themeIcon"></i>
                    <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($theme === 'light' ? 'Light' : 'Dark'); ?></span>
                </button>
                <div class="theme-menu" id="themeMenu" aria-hidden="true">
                    <button class="theme-option" data-theme="light" role="menuitem">Light</button>
                    <button class="theme-option" data-theme="dark" role="menuitem">Dark</button>
                    <button class="theme-option" data-theme="device" role="menuitem">Device</button>     
                </div>
            </div>
            <a href="notifications.php" title="Notifications" style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;" aria-label="Notifications">
                <i class="bi bi-bell" style="font-size:18px;"></i>
                <span aria-hidden="true"><?php echo $notif_count > 0 ? "($notif_count)" : ""; ?></span>
            </a>
            <?php
            $user_role = $_SESSION['role'] ?? 'buyer';
            $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
            $profile_query = "SELECT profile_image FROM users WHERE id = ?";
            $profile_stmt = $conn->prepare($profile_query);
            $profile_stmt->bind_param("i", $user_id);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            $current_profile = $profile_result->fetch_assoc();
            $current_profile_image = $current_profile['profile_image'] ?? null;
            ?>
            <a href="<?php echo $profile_page; ?>" aria-label="User profile">
                <?php if (!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                    <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="Uploads/logo1.png" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
            <button class="hamburger" aria-label="Toggle menu" aria-controls="menu" aria-expanded="false">☰</button>
        </div>
        <ul class="menu" id="menu" role="menu">
            <li role="none"><a href="shop.php" role="menuitem">Home</a></li>
            <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
                <li role="none"><a href="seller_dashboard.php" role="menuitem">Seller Dashboard</a></li>
            <?php else: ?>
                <li role="none"><a href="become_seller.php" role="menuitem">Become Seller</a></li>
            <?php endif; ?>
            <li role="none"><a href="<?php echo $profile_page; ?>" role="menuitem">Profile</a></li>
            <li role="none"><a href="logout.php" role="menuitem">Logout</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="form-header">
            <h1 class="form-title">Edit Product</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="categories">Categories *</label>
                <select id="categories" name="categories[]" multiple required>
                    <?php foreach ($all_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" 
                                <?php echo in_array($category, $current_categories) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Price ($) *</label>
                <input type="number" id="price" name="price" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" 
                       value="<?php echo htmlspecialchars($product['stock_quantity']); ?>">
            </div>

            <div class="form-group">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>">
            </div>

            <div class="form-group">
                <label for="image_file">Upload Image</label>
                <input type="file" id="image_file" name="image_file" accept="image/*">
                <p>Current image: <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Current product image" style="max-width: 100px; margin-top: 10px;"></p>
            </div>

            <div class="form-group">
                <label for="image_url">Or Enter Image URL</label>
                <input type="text" id="image_url" name="image_url" value="<?php echo htmlspecialchars($product['image']); ?>">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                    Active
                </label>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_featured" <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                    Featured
                </label>
            </div>

            <div class="form-group">
                <label>Optional Specs</label>
                <div id="specs-container">
                    <?php foreach ($current_specs as $spec): ?>
                        <div class="spec-row">
                            <input type="text" name="spec_name[]" placeholder="Spec Name" value="<?php echo htmlspecialchars($spec['spec_name']); ?>">
                            <input type="text" name="spec_value[]" placeholder="Spec Value" value="<?php echo htmlspecialchars($spec['spec_value']); ?>">
                            <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
                        </div>
                    <?php endforeach; ?>
                    <!-- Add empty row if none exists -->
                    <?php if (empty($current_specs)): ?>
                        <div class="spec-row">
                            <input type="text" name="spec_name[]" placeholder="Spec Name">
                            <input type="text" name="spec_value[]" placeholder="Spec Value">
                            <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-primary" onclick="addSpec()">Add Spec</button>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="seller_dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
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
                updateTheme(theme, effectiveTheme);
                
                // Save theme to server
                fetch(`?theme=${theme}`, { method: 'GET' })
                    .catch(error => console.error('Error saving theme:', error));
            }

            // Update theme button UI
            function updateTheme(theme, effectiveTheme) {
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
                    const isExpanded = themeBtn.getAttribute('aria-expanded') === 'true';
                    themeBtn.setAttribute('aria-expanded', !isExpanded);
                    themeDropdown.classList.toggle('active');
                });

                // Keyboard navigation for theme button
                themeBtn.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        themeBtn.click();
                    }
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
                    themeBtn.setAttribute('aria-expanded', 'false');
                });

                // Keyboard navigation for theme options
                const options = themeMenu.querySelectorAll('.theme-option');
                options.forEach((option, index) => {
                    option.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            option.click();
                        } else if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            const nextIndex = (index + 1) % options.length;
                            options[nextIndex].focus();
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            const prevIndex = (index - 1 + options.length) % options.length;
                            options[prevIndex].focus();
                        }
                    });
                });
            }

            // Close theme menu when clicking outside
            document.addEventListener('click', (e) => {
                if (themeDropdown && !themeDropdown.contains(e.target)) {
                    themeDropdown.classList.remove('active');
                    themeBtn.setAttribute('aria-expanded', 'false');
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

            // Toggle Hamburger Menu
            const hamburger = document.querySelector('.hamburger');
            const menu = document.getElementById('menu');
            if (hamburger && menu) {
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isExpanded = hamburger.getAttribute('aria-expanded') === 'true';
                    hamburger.setAttribute('aria-expanded', !isExpanded);
                    menu.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove('show');
                        hamburger.setAttribute('aria-expanded', 'false');
                    }
                });

                const menuItems = menu.querySelectorAll('a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        menu.classList.remove('show');
                        hamburger.setAttribute('aria-expanded', 'false');
                    });

                    // Keyboard navigation for menu items
                    item.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            item.click();
                        }
                    });
                });
            }

            // Specs handling
            function addSpec() {
                const container = document.getElementById('specs-container');
                const div = document.createElement('div');
                div.className = 'spec-row';
                div.innerHTML = `
                    <input type="text" name="spec_name[]" placeholder="Spec Name">
                    <input type="text" name="spec_value[]" placeholder="Spec Value">
                    <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
                `;
                container.appendChild(div);
            }

            function removeSpec(button) {
                button.parentElement.remove();
            }

            // Expose functions to global scope
            window.addSpec = addSpec;
            window.removeSpec = removeSpec;
        });
    </script>
</body>
</html>