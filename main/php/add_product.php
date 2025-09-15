<?php
session_start();

// Check if user is logged in and is a seller
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION["user_id"];

// Check if user has seller role
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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $category = trim($_POST["category"]);
    $price = floatval($_POST["price"]);
    $stock_quantity = intval($_POST["stock_quantity"]);
    $sku = trim($_POST["sku"]);
    $image_url = trim($_POST["image_url"]);
    
    // Validate required fields
    if (empty($name) || empty($price) || $price <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } elseif (strlen($image_url) > 1000) {
        $error = "Image URL is too long. Please use a shorter URL or a different image hosting service.";
    } elseif (strlen($sku) > 255) {
        $error = "SKU is too long. Please use a shorter product code.";
    } else {
        // Generate SKU if not provided
        if (empty($sku)) {
            $sku = "SKU-" . time() . "-" . rand(100, 999);
        }
        
        // Insert product
        $sql = "INSERT INTO products (name, description, category, price, image, sku, stock_quantity, seller_id, is_active, is_featured) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, FALSE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssii", $name, $description, $category, $price, $image_url, $sku, $stock_quantity, $user_id);
        
        if ($stmt->execute()) {
            $success = "Product added successfully!";
            // Clear form data
            $name = $description = $category = $sku = $image_url = "";
            $price = $stock_quantity = 0;
        } else {
            $error = "Error adding product: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - MetaAccessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <?php include('theme_toggle.php'); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'ASUS ROG', Arial, sans-serif;
            background: #0A0A0A;
            color: #FFFFFF;
            min-height: 100vh;
        }

        .navbar {
            background: #000000;
            padding: 15px 20px;
            color: #44D62C;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #44D62C;
        }

        .navbar h2 {
            margin: 0;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .profile-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #222222;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #44D62C;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
            border: 2px solid #44D62C;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .form-container {
            background: #111111;
            border-radius: 10px;
            padding: 40px;
            border: 1px solid #333333;
        }

        .form-title {
            text-align: center;
            font-size: 2rem;
            color: #44D62C;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #44D62C;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #44D62C;
            border-radius: 8px;
            background: #1a1a1a;
            color: #FFFFFF;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.3);
            border-color: #36b020;
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .required {
            color: #ff4444;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #44D62C;
            color: #000000;
            width: 100%;
        }

        .btn-primary:hover {
            background: #36b020;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.3);
        }

        .btn-secondary {
            background: #333333;
            color: #FFFFFF;
            border: 1px solid #44D62C;
            margin-right: 15px;
        }

        .btn-secondary:hover {
            background: #44D62C;
            color: #000000;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            background: rgba(68, 214, 44, 0.2);
            color: #44D62C;
            border: 1px solid #44D62C;
        }

        .error {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .help-text {
            font-size: 0.9rem;
            color: #888;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <h2>Meta Accessories</h2>
        <div class="nav-right">
            <a href="seller_profile.php">
                <?php 
                // Fetch current user's profile image from database
                $current_user_id = $_SESSION['user_id'];
                $profile_query = "SELECT profile_image FROM users WHERE id = ?";
                $profile_stmt = $conn->prepare($profile_query);
                $profile_stmt->bind_param("i", $current_user_id);
                $profile_stmt->execute();
                $profile_result = $profile_stmt->get_result();
                $current_profile = $profile_result->fetch_assoc();
                $current_profile_image = $current_profile['profile_image'] ?? null;
                ?>
                <?php if(!empty($current_profile_image) && file_exists('uploads/' . $current_profile_image)): ?>
                    <img src="uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="uploads/default-avatar.svg" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Add New Product</h1>
            
            <?php if ($success): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Product Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe your product..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="accessories" <?php echo (isset($category) && $category === 'accessories') ? 'selected' : ''; ?>>Accessories</option>
                            <option value="phone" <?php echo (isset($category) && $category === 'phone') ? 'selected' : ''; ?>>Phone</option>
                            <option value="tablet" <?php echo (isset($category) && $category === 'tablet') ? 'selected' : ''; ?>>Tablet</option>
                            <option value="laptop" <?php echo (isset($category) && $category === 'laptop') ? 'selected' : ''; ?>>Laptop</option>
                            <option value="gaming" <?php echo (isset($category) && $category === 'gaming') ? 'selected' : ''; ?>>Gaming</option>
                            <option value="other" <?php echo (isset($category) && $category === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sku">SKU (Product Code)</label>
                        <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($sku ?? ''); ?>" placeholder="Leave empty for auto-generation">
                        <div class="help-text">Leave empty to auto-generate a unique SKU</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price ($) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $price ?? ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity <span class="required">*</span></label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo $stock_quantity ?? ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="image_url">Product Image URL</label>
                    <input type="url" id="image_url" name="image_url" value="<?php echo htmlspecialchars($image_url ?? ''); ?>" placeholder="https://example.com/image.jpg">
                    <div class="help-text">Enter a URL to an image of your product</div>
                </div>

                <div class="form-actions">
                    <a href="seller_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
