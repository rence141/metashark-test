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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $category = trim($_POST["category"]);
    $price = floatval($_POST["price"]);
    $stock_quantity = intval($_POST["stock_quantity"]);
    $sku = trim($_POST["sku"]);
    $image_url = trim($_POST["image_url"]);
    $is_active = isset($_POST["is_active"]) ? 1 : 0;
    $is_featured = isset($_POST["is_featured"]) ? 1 : 0;
    
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
        
        // Update product
        $update_sql = "UPDATE products SET 
                       name = ?, description = ?, category = ?, price = ?, 
                       image = ?, sku = ?, stock_quantity = ?, 
                       is_active = ?, is_featured = ?, updated_at = CURRENT_TIMESTAMP
                       WHERE id = ? AND seller_id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssdssiiiii", $name, $description, $category, $price, 
                                $image_url, $sku, $stock_quantity, $is_active, $is_featured, 
                                $product_id, $user_id);
        
        if ($update_stmt->execute()) {
            $success = "Product updated successfully!";
            // Refresh product data
            $product_sql = "SELECT * FROM products WHERE id = ? AND seller_id = ?";
            $product_stmt = $conn->prepare($product_sql);
            $product_stmt->bind_param("ii", $product_id, $user_id);
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            $product = $product_result->fetch_assoc();
        } else {
            $error = "Error updating product: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Meta Accessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <?php include('theme_toggle.php'); ?>
    <link rel="icon" type="image/png" href="uploads/logo1.png">
    <link rel="stylesheet" href="../../css/edit_product.css">
</head>
<body>
    <div class="container">
        <h1> Edit Product</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="product-preview">
            <h3>Current Product Preview</h3>
            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($product['name']); ?></p>
            <p><strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?></p>
            <p><strong>Stock:</strong> <?php echo $product['stock_quantity']; ?></p>
            <p><strong>Status:</strong> <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?></p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="accessories" <?php echo $product['category'] === 'accessories' ? 'selected' : ''; ?>>Accessories</option>
                    <option value="phone" <?php echo $product['category'] === 'phone' ? 'selected' : ''; ?>>Phone</option>
                    <option value="tablet" <?php echo $product['category'] === 'tablet' ? 'selected' : ''; ?>>Tablet</option>
                    <option value="laptop" <?php echo $product['category'] === 'laptop' ? 'selected' : ''; ?>>Laptop</option>
                    <option value="gaming" <?php echo $product['category'] === 'gaming' ? 'selected' : ''; ?>>Gaming</option>
                    <option value="audio" <?php echo $product['category'] === 'audio' ? 'selected' : ''; ?>>Audio</option>
                    <option value="other" <?php echo $product['category'] === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Price ($) *</label>
                <input type="number" id="price" name="price" value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" id="stock_quantity" name="stock_quantity" value="<?php echo $product['stock_quantity']; ?>" min="0">
            </div>

            <div class="form-group">
                <label for="sku">SKU (Product Code)</label>
                <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>">
            </div>

            <div class="form-group">
                <label for="image_url">Image URL</label>
                <input type="url" id="image_url" name="image_url" value="<?php echo htmlspecialchars($product['image']); ?>">
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="is_active" name="is_active" <?php echo $product['is_active'] ? 'checked' : ''; ?>>
                <label for="is_active">Product is Active (visible to customers)</label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="is_featured" name="is_featured" <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                <label for="is_featured">Featured Product (highlighted in listings)</label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">💾 Update Product</button>
                <a href="seller_dashboard.php" class="btn btn-secondary"> Cancel</a>
            </div>
        </form>

        <div class="back-link">
            <a href="seller_dashboard.php">← Back to Seller Dashboard</a>
        </div>
    </div>

    <script>
        // Real-time preview update
        function updatePreview() {
            const name = document.getElementById('name').value;
            const price = document.getElementById('price').value;
            const stock = document.getElementById('stock_quantity').value;
            const image = document.getElementById('image_url').value;
            const isActive = document.getElementById('is_active').checked;
            
            const preview = document.querySelector('.product-preview');
            preview.innerHTML = `
                <h3>Product Preview</h3>
                <img src="${image || 'https://via.placeholder.com/200x150?text=No+Image'}" alt="${name}">
                <p><strong>Name:</strong> ${name || 'Product Name'}</p>
                <p><strong>Price:</strong> $${price ? parseFloat(price).toFixed(2) : '0.00'}</p>
                <p><strong>Stock:</strong> ${stock || '0'}</p>
                <p><strong>Status:</strong> ${isActive ? 'Active' : 'Inactive'}</p>
            `;
        }

        // Add event listeners for real-time preview
        document.getElementById('name').addEventListener('input', updatePreview);
        document.getElementById('price').addEventListener('input', updatePreview);
        document.getElementById('stock_quantity').addEventListener('input', updatePreview);
        document.getElementById('image_url').addEventListener('input', updatePreview);
        document.getElementById('is_active').addEventListener('change', updatePreview);
    </script>
</body>
</html>
