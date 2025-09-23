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

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: seller_dashboard.php");
    exit();
}

// Check if product belongs to this seller
$check_sql = "SELECT id, name, image FROM products WHERE id = ? AND seller_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $product_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    header("Location: seller_dashboard.php");
    exit();
}

$product = $check_result->fetch_assoc();

// Handle deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["confirm_delete"])) {
    // First, remove from cart (if any)
    $clear_cart_sql = "DELETE FROM cart WHERE product_id = ?";
    $clear_cart_stmt = $conn->prepare($clear_cart_sql);
    $clear_cart_stmt->bind_param("i", $product_id);
    $clear_cart_stmt->execute();
    
    // Delete the product
    $delete_sql = "DELETE FROM products WHERE id = ? AND seller_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $product_id, $user_id);
    
    if ($delete_stmt->execute()) {
        header("Location: seller_dashboard.php?deleted=1");
        exit();
    } else {
        $error = "Error deleting product: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product - Meta Accessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="uploads/logo1.png">
    <link rel="stylesheet" href="../../css/delete_product.css">
</head>
<body>
    <div class="container">
        <h1> Delete Product</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="warning">
            <h3>⚠️ Warning!</h3>
            <p>You are about to permanently delete this product. This action cannot be undone!</p>
            <p>All cart items containing this product will also be removed.</p>
        </div>

        <div class="product-info">
            <img src="<?php echo htmlspecialchars($product['image'] ?? 'uploads/no-image.png'); ?>" 
              alt="<?php echo htmlspecialchars($product['name']); ?>">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>Are you sure you want to delete this product?</p>
        </div>

        <form method="POST">
            <div class="form-actions">
                <button type="submit" name="confirm_delete" class="btn" onclick="return confirm('Are you absolutely sure you want to delete this product? This action cannot be undone!')">
                     Yes, Delete Product
                </button>
                <a href="seller_dashboard.php" class="btn btn-secondary"> Cancel</a>
            </div>
        </form>

    </div>
</body>
</html>
