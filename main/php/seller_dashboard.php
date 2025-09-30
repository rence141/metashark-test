<?php
session_start();

// Check if user is logged in and is a seller
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION["user_id"];

// Enforce verification for logged-in sellers/admins
$ver = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
if ($ver) { $ver->bind_param("i", $user_id); $ver->execute(); $vr = $ver->get_result(); $vu = $vr->fetch_assoc(); }
if (empty($vu) || (int)($vu['is_verified'] ?? 0) !== 1) {
    header("Location: verify_account.php");
    exit();
}

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

$theme = $_SESSION['theme'] ?? 'dark';

// Cart count for navbar
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param("i", $_SESSION['user_id']);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_result && $count_result->num_rows > 0) {
            $cart_data = $count_result->fetch_assoc();
            $cart_count = $cart_data['total'] ?: 0;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - MetaAccessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="uploads/logo1.png">
    <link rel="stylesheet" href="../../css/seller_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <?php include('theme_toggle.php'); ?>
    <script>
        // Handle loading screen
        document.addEventListener('DOMContentLoaded', () => {
            const loadingScreen = document.querySelector('.loading-screen');
            // Ensure loading screen is active on page load
            loadingScreen.classList.add('active');
            // Hide loading screen after 2.5 seconds
            setTimeout(() => {
                loadingScreen.classList.remove('active');
            }, 2500);
        });
    </script>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen active">
        <div class="logo-container">
            <div class="logo-outline"></div>
            <div class="logo-fill"></div>
        </div>
        <div class="loading-text">Loading...</div>
    </div>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <h2>Meta Shark 
                <!-- Theme Toggle Component -->
<div class="theme-toggle" id="themeToggle">
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle Theme">
        <span class="theme-icon" id="themeIcon">
            <?php echo $theme === 'light' ? '🌙' : '☀️'; ?>
        </span>
        <span class="theme-text" id="themeText">
            <?php echo $theme === 'light' ? 'Dark' : 'Light'; ?>
        </span>
    </button>
</div>

<?php // Handle theme toggle
            
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_theme"])) {
    $_SESSION['theme'] = ($_SESSION['theme'] ?? 'dark') === 'dark' ? 'light' : 'dark';
    header("Location: seller_dashboard.php");
    exit();
} ?></h2>
        
        </div>
    

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
            <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-cart" style="font-size:18px;"></i>
                <span>(<?php echo (int)$cart_count; ?>)</span>
            </a>
            <a href="notifications.php" title="Notifications" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-bell" style="font-size:18px;"></i>
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
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $success; ?></div>
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
            <a href="seller_vouchers.php" class="btn btn-secondary">Vouchers</a>
            <a href="order_status.php" class="btn btn-secondary">Order Status</a>
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