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
$check_sql = "SELECT id, name FROM products WHERE id = ? AND seller_id = ?";
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
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #111;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #333;
            text-align: center;
        }

        h1 {
            color: #ff4444;
            margin-bottom: 30px;
        }

        .warning {
            background: #ff4444;
            color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #cc3333;
        }

        .product-info {
            background: #222;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .product-info img {
            max-width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .product-info h3 {
            color: #44D62C;
            margin-bottom: 10px;
        }

        .btn {
            background: #ff4444;
            color: #fff;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 0 10px;
        }

        .btn:hover {
            background: #cc3333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #666;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #555;
        }

        .form-actions {
            margin-top: 30px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #44D62C;
            text-decoration: none;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #ff4444;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Delete Product</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="warning">
            <h3>⚠️ Warning!</h3>
            <p>You are about to permanently delete this product. This action cannot be undone!</p>
            <p>All cart items containing this product will also be removed.</p>
        </div>

        <div class="product-info">
            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>Are you sure you want to delete this product?</p>
        </div>

        <form method="POST">
            <div class="form-actions">
                <button type="submit" name="confirm_delete" class="btn" onclick="return confirm('Are you absolutely sure you want to delete this product? This action cannot be undone!')">
                    🗑️ Yes, Delete Product
                </button>
                <a href="seller_dashboard.php" class="btn btn-secondary">❌ Cancel</a>
            </div>
        </form>

        <div class="back-link">
            <a href="seller_dashboard.php">← Back to Seller Dashboard</a>
        </div>
    </div>
</body>
</html>
