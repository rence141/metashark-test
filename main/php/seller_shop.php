<?php
session_start();
include("db.php");

// Get seller ID from URL parameter
$seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;

if ($seller_id <= 0) {
    header("Location: shop.php");
    exit();
}

// Get seller information
$seller_query = "SELECT id, fullname, email, seller_name, seller_description, business_type, seller_rating, total_sales, profile_image FROM users WHERE id = ? AND (role = 'seller' OR role = 'admin')";
$seller_stmt = $conn->prepare($seller_query);
$seller_stmt->bind_param("i", $seller_id);
$seller_stmt->execute();
$seller_result = $seller_stmt->get_result();

if ($seller_result->num_rows === 0) {
    header("Location: shop.php");
    exit();
}

$seller = $seller_result->fetch_assoc();

// Get seller's products
$products_query = "SELECT * FROM products WHERE seller_id = ? AND is_active = TRUE ORDER BY created_at DESC";
$products_stmt = $conn->prepare($products_query);
$products_stmt->bind_param("i", $seller_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Get cart count for logged in users
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $_SESSION['user_id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    if ($count_result->num_rows > 0) {
        $cart_data = $count_result->fetch_assoc();
        $cart_count = $cart_data['total'] ?: 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seller['seller_name'] ?: $seller['fullname']); ?> - Shop</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <style>
        /* Reset */
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

        /* Navbar */
        .navbar {
            background: #000000;
            padding: 15px 20px;
            color: #44D62C;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
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
        
        .profile-icon:hover {
            background-color: #333333;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(68, 214, 44, 0.8);
        }

        .hamburger {
            font-size: 24px;
            cursor: pointer;
            background: none;
            border: none;
            color: #44D62C;
            transition: transform 0.3s ease;
        }
        
        .hamburger:hover {
            transform: scale(1.1);
        }

        .menu {
            position: absolute;
            top: 60px;
            right: 20px;
            background: #111111;
            list-style: none;
            padding: 15px;
            border-radius: 8px;
            display: none;
            flex-direction: column;
            gap: 10px;
            border: 1px solid #44D62C;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.3);
            z-index: 9999;
        }

        .menu li {
            color: #FFFFFF;
            cursor: pointer;
            transition: color 0.3s, transform 0.2s;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .menu li:hover {
            color: #44D62C;
            background-color: #222222;
            transform: translateX(5px);
        }

        .menu.show {
            display: flex;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Seller Header */
        .seller-header {
            background: linear-gradient(135deg, #111111 0%, #222222 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #333333;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .seller-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #44D62C;
            box-shadow: 0 0 20px rgba(68, 214, 44, 0.5);
        }

        .seller-info h1 {
            font-size: 2.5rem;
            color: #44D62C;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .seller-info .business-type {
            color: #888;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        .seller-info .description {
            color: #ccc;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .seller-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            color: #44D62C;
            font-weight: bold;
        }

        .stat-label {
            color: #888;
            font-size: 0.9rem;
        }

        /* Shop Title */
        .shop-title {
            font-size: 2rem;
            color: #44D62C;
            text-align: center;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .product-card {
            background: #111111;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #333333;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(68, 214, 44, 0.2);
            border-color: #44D62C;
        }

        .product-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .product-info {
            padding: 20px;
        }

        .product-info h3 {
            margin-bottom: 10px;
            font-size: 1.3rem;
            color: #FFFFFF;
        }

        .product-info p {
            margin-bottom: 10px;
            color: #666;
        }

        .price {
            color: #44D62C !important;
            font-size: 1.4rem;
            font-weight: bold;
        }

        .stock {
            color: #888 !important;
            font-size: 0.9rem;
        }

        .product-info button {
            width: 100%;
            padding: 12px 20px;
            background: #44D62C;
            border: none;
            color: #000000;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: bold;
            margin-top: 10px;
        }

        .product-info button:hover {
            background: #36b020;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.3);
        }

        .product-info button:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Notification System */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: linear-gradient(135deg, #44D62C, #36b020);
            border-left: 4px solid #2a8a1a;
        }

        .notification.error {
            background: linear-gradient(135deg, #ff4444, #cc3333);
            border-left: 4px solid #aa2222;
        }

        .notification.info {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border-left: 4px solid #1565C0;
        }

        /* Button Loading State */
        .product-info button.loading {
            background: #666;
            cursor: not-allowed;
            position: relative;
            color: transparent;
        }

        .product-info button.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #44D62C;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Button Click Animation */
        .product-info button:active {
            transform: scale(0.95);
        }

        /* Cart Count Animation */
        .cart-count {
            transition: all 0.3s ease;
        }

        .cart-count.updated {
            animation: bounce 0.6s ease;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: scale(1); }
            40% { transform: scale(1.2); }
            60% { transform: scale(1.1); }
        }

        /* Empty State */
        .empty-products {
            text-align: center;
            padding: 80px 20px;
            background: #111111;
            border-radius: 10px;
            border: 1px solid #333333;
            margin: 40px 0;
        }

        .empty-products h3 {
            font-size: 2rem;
            color: #44D62C;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .empty-products p {
            color: #888;
            font-size: 1.2rem;
            margin-bottom: 30px;
        }

        .btn-back {
            display: inline-block;
            padding: 12px 25px;
            background: #44D62C;
            color: #000000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #36b020;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .seller-header {
                flex-direction: column;
                text-align: center;
            }
            
            .seller-stats {
                justify-content: center;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <h2>Meta Accessories</h2>
        <div class="nav-right">
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                <?php
                // Check user role to determine profile page
                $user_role = $_SESSION['role'] ?? 'buyer';
                $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
                
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
                <a href="<?php echo $profile_page; ?>">
                    <?php if(!empty($current_profile_image) && file_exists('uploads/' . $current_profile_image)): ?>
                        <img src="uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                    <?php else: ?>
                        <img src="uploads/default-avatar.svg" alt="Profile" class="profile-icon">
                    <?php endif; ?>
                </a>
                <button class="hamburger">☰</button>
            <?php else: ?>
                <a href="login_users.php">
                    <div class="profile-icon">👤</div>
                </a>
                <button class="hamburger">☰</button>
            <?php endif; ?>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php" style="color: white; text-decoration: none;">Home</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <li><a href="carts_users.php" style="color: white; text-decoration: none;">Cart (<?php echo $cart_count; ?>)</a></li>
                <?php 
                $user_role = $_SESSION['role'] ?? 'buyer';
                $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
                ?>
                <li><a href="<?php echo $profile_page; ?>" style="color: white; text-decoration: none;">Profile</a></li>
                <li><a href="logout.php" style="color: white; text-decoration: none;">Logout</a></li>
            <?php else: ?>
                <li><a href="login_users.php" style="color: white; text-decoration: none;">Login</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="container">
        <!-- Seller Header -->
        <div class="seller-header">
            <?php if(!empty($seller['profile_image']) && file_exists('uploads/' . $seller['profile_image'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($seller['profile_image']); ?>" alt="Seller Avatar" class="seller-avatar">
            <?php else: ?>
                <img src="uploads/default-avatar.svg" alt="Seller Avatar" class="seller-avatar">
            <?php endif; ?>
            
            <div class="seller-info">
                <h1><?php echo htmlspecialchars($seller['seller_name'] ?: $seller['fullname']); ?></h1>
                <?php if($seller['business_type']): ?>
                    <p class="business-type"><?php echo htmlspecialchars($seller['business_type']); ?></p>
                <?php endif; ?>
                <?php if($seller['seller_description']): ?>
                    <p class="description"><?php echo htmlspecialchars($seller['seller_description']); ?></p>
                <?php endif; ?>
                
                <div class="seller-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($products); ?></div>
                        <div class="stat-label">Products</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($seller['seller_rating'], 1); ?></div>
                        <div class="stat-label">Rating</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $seller['total_sales']; ?></div>
                        <div class="stat-label">Sales</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shop Title -->
        <h2 class="shop-title"><?php echo htmlspecialchars($seller['seller_name'] ?: $seller['fullname']); ?>'s Products</h2>

        <!-- Product Grid -->
        <?php if (empty($products)): ?>
            <div class="empty-products">
                <h3>No Products Available</h3>
                <p>This seller hasn't added any products yet.</p>
                <a href="shop.php" class="btn-back">Back to Main Store</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
                            <p class="stock">Stock: <?php echo $product['stock_quantity']; ?></p>
                            
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <form method="POST" action="shop.php" style="display: inline;" class="add-to-cart-form" data-product-id="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="add_to_cart" value="1">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="add-to-cart-btn" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?> data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button onclick="alert('Please login to add items to cart!')">Add to Cart</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle Hamburger Menu
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector(".hamburger");
            const menu = document.getElementById("menu");

            if (hamburger && menu) {
                hamburger.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    menu.classList.toggle("show");
                });

                // Close menu when clicking outside
                document.addEventListener("click", function(e) {
                    if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove("show");
                    }
                });

                // Close menu when clicking on menu items
                const menuItems = menu.querySelectorAll('a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        menu.classList.remove("show");
                    });
                });
            }
        });

        // Enhanced Add to Cart functionality
        const addToCartForms = document.querySelectorAll('.add-to-cart-form');
        addToCartForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = form.querySelector('.add-to-cart-btn');
                const productName = button.getAttribute('data-product-name');
                
                // Add loading state
                button.classList.add('loading');
                button.disabled = true;
                
                // Show immediate feedback
                showNotification('Adding to cart...', 'info');
                
                // Simulate processing time (remove in production)
                setTimeout(() => {
                    // The form will submit normally after this
                }, 500);
            });
        });

        // Notification function
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notif => notif.remove());
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = message;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>
