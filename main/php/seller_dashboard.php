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
$user_sql = "SELECT role, seller_name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data['role'] !== 'seller' && $user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

// Check for success messages
$success = "";
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success = "Product deleted successfully!";
}

// Handle seller registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["become_seller"])) {
    $seller_name = trim($_POST["seller_name"]);
    $seller_description = trim($_POST["seller_description"]);
    
    $update_sql = "UPDATE users SET role = 'seller', seller_name = ?, seller_description = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $seller_name, $seller_description, $user_id);
    $update_stmt->execute();
    
    header("Location: seller_dashboard.php");
    exit();
}

// Get seller's products
$products_sql = "SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC";
$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param("i", $user_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Get seller stats
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    SUM(stock_quantity) as total_stock,
    AVG(price) as avg_price
    FROM products WHERE seller_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - MetaAccessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
        <link rel="icon" type="image/png" href="uploads/logo1.png">
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .dashboard-title {
            font-size: 2.5rem;
            color: #44D62C;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .seller-name {
            color: #888;
            font-size: 1.2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #111111;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #333333;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #44D62C;
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            color: #44D62C;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #888;
            font-size: 1.1rem;
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #44D62C;
            color: #000000;
        }

        .btn-primary:hover {
            background: #36b020;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #333333;
            color: #FFFFFF;
            border: 1px solid #44D62C;
        }

        .btn-secondary:hover {
            background: #44D62C;
            color: #000000;
        }

        .products-section {
            background: #111111;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #333333;
        }

        .section-title {
            font-size: 1.8rem;
            color: #44D62C;
            margin-bottom: 20px;
            text-align: center;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: #1a1a1a;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #333333;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            border-color: #44D62C;
            transform: translateY(-3px);
        }

        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }

        .btn-danger {
            background: #ff4444;
            color: #fff;
            border: 1px solid #ff4444;
        }

        .btn-danger:hover {
            background: #cc3333;
            border-color: #cc3333;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .product-name {
            font-size: 1.2rem;
            color: #FFFFFF;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 1.1rem;
            color: #44D62C;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .product-stock {
            color: #888;
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: #ff4444;
            color: white;
        }

        .btn-danger:hover {
            background: #cc3333;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state h3 {
            color: #44D62C;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <h2>Meta Shark</h2>
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
        <!-- DASHBOARD HEADER -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">Seller Dashboard</h1>
            <p class="seller-name">Welcome, <?php echo htmlspecialchars($user_data['seller_name'] ?: $_SESSION['fullname']); ?>!</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="background: #44D62C; color: #000; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center; font-weight: bold;">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_stock']; ?></div>
                <div class="stat-label">Total Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['avg_price'], 2); ?></div>
                <div class="stat-label">Average Price</div>
            </div>
        </div>

        <!-- ACTIONS -->
        <div class="actions">
            <a href="add_product.php" class="btn btn-primary">Add New Product</a>
            <a href="shop.php" class="btn btn-secondary">View Store</a>
            <a href="carts_users.php" class="btn btn-secondary">My Cart</a>
            <a href="seller_profile.php" class="btn btn-secondary">Profile</a>
        </div>

        <!-- PRODUCTS SECTION -->
        <div class="products-section">
            <h2 class="section-title">My Products</h2>
            
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <h3>No products yet</h3>
                    <p>Start by adding your first product to the marketplace!</p>
                    <a href="add_product.php" class="btn btn-primary" style="margin-top: 20px;">Add Product</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-image">
                            
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-stock">Stock: <?php echo $product['stock_quantity']; ?></p>
                            
                            <div class="product-actions">
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-secondary btn-small">Edit</a>
                                <a href="delete_product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-danger btn-small"
                                   onclick="return confirm('Delete this product?')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
