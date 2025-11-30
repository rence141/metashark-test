<?php
session_start();
include("db.php");

// Get theme from session (default to dark)
$theme = $_SESSION['theme'] ?? 'dark';

// Get seller ID from URL parameter
$seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;
$buyer_id = $_SESSION['user_id'] ?? 0;
if ($buyer_id) {
    $ver = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
    if ($ver) { $ver->bind_param("i", $buyer_id); $ver->execute(); $vr = $ver->get_result(); $vu = $vr->fetch_assoc(); }
    if (empty($vu) || (int)($vu['is_verified'] ?? 0) !== 1) {
        header("Location: verify_account.php");
        exit();
    }
}

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

// Load active vouchers for this seller if column exists
$store_vouchers = [];
$vcCol = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'seller_id'");
if ($vcCol && $vcCol->num_rows > 0) {
    $vstmt = $conn->prepare("SELECT code, discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses FROM vouchers WHERE seller_id = ? AND expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses) ORDER BY expiry_date ASC");
    if ($vstmt) { $vstmt->bind_param("i", $seller_id); $vstmt->execute(); $vres = $vstmt->get_result(); while ($row = $vres->fetch_assoc()) { $store_vouchers[] = $row; } }
}

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
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seller['seller_name'] ?: $seller['fullname']); ?> - Shop</title>
    <link rel="stylesheet" href="fonts/fonts.css">
     <link rel="icon" type="image/png" href="uploads/logo1.png">
    <!-- Added Bootstrap Icons for Theme Toggle -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/seller_shop.css">
    <?php include('theme_toggle.php'); ?>
    <style>
        /* Theme Toggle Button Styles */
        .theme-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 12px;
            font-size: 16px;
        }
        .theme-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #44D62C;
            color: #44D62C;
        }

        /* --- Vouchers Styles (Default Dark) --- */
        .vouchers-card {
            background: #111;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 16px;
            margin: 16px 0;
        }
        .vouchers-card h2 {
            color: #44D62C;
            margin: 0 0 10px;
        }
        .voucher-list {
            list-style: none; 
            padding: 0; 
            margin: 0;
        }
        .voucher-item {
            padding: 10px 0; 
            border-bottom: 1px solid #222; 
            display: flex; 
            justify-content: space-between; 
            gap: 12px; 
            flex-wrap: wrap;
        }
        .voucher-code {
            font-weight: bold;
            color: #fff;
        }
        .voucher-subtext {
            color: #aaa; 
            font-size: 0.9em;
        }
        .copy-btn {
            margin-top: 6px; 
            padding: 8px 12px; 
            border: none; 
            background: #44D62C; 
            color: #000; 
            border-radius: 6px; 
            cursor: pointer;
        }

        /* --- Light Mode Overrides --- */
        [data-theme="light"] body {
            background: #f3f4f6;
            color: #1f2937;
        }
        [data-theme="light"] .navbar {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        [data-theme="light"] .navbar h2 {
            color: #1f2937;
        }
        [data-theme="light"] .menu li a, 
        [data-theme="light"] .nav-right a {
            color: #4b5563 !important;
        }
        [data-theme="light"] .theme-btn {
            color: #4b5563;
            border-color: #d1d5db;
        }
        [data-theme="light"] .theme-btn:hover {
            background: #f9fafb;
            color: #44D62C;
            border-color: #44D62C;
        }
        [data-theme="light"] .shop-title {
            color: #111;
        }
        /* Override generic cards */
        [data-theme="light"] .card {
            background: #ffffff !important;
            border-color: #e5e7eb !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        /* Override Product Cards explicitly */
        [data-theme="light"] .product-card {
            background: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            color: #1f2937;
        }
        [data-theme="light"] .product-info h3 {
            color: #1f2937;
        }
        [data-theme="light"] .product-info .price {
            color: #44D62C;
            font-weight: 700;
        }
        [data-theme="light"] .product-info .stock {
            color: #6b7280;
        }
        [data-theme="light"] .seller-header {
            background: #ffffff;
            border: 1px solid #e5e7eb;
        }
        [data-theme="light"] .seller-info h1 {
            color: #1f2937;
        }
        [data-theme="light"] .stat-label {
            color: #6b7280;
        }
        [data-theme="light"] .stat-value {
            color: #1f2937;
        }
        [data-theme="light"] .hamburger {
            color: #1f2937;
        }
        
        /* Voucher Card Light Mode */
        [data-theme="light"] .vouchers-card {
            background: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        [data-theme="light"] .voucher-item {
            border-bottom-color: #e5e7eb;
        }
        [data-theme="light"] .voucher-code {
            color: #1f2937;
        }
        [data-theme="light"] .voucher-subtext {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <h2>Meta Shark</h2>
        <div class="nav-right">
            <!-- THEME TOGGLE BUTTON -->
            <button id="themeBtn" class="theme-btn" title="Switch Theme">
                <?php if ($theme === 'dark'): ?>
                    <i class="bi bi-moon-stars-fill"></i>
                <?php else: ?>
                    <i class="bi bi-sun-fill"></i>
                <?php endif; ?>
            </button>

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
                <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                    <span style="font-size:18px;">🛒</span>
                    <span>(<?php echo (int)$cart_count; ?>)</span>
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
            <li><a href="shop.php" style="color: inherit; text-decoration: none;">Home</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <li><a href="carts_users.php" style="color: inherit; text-decoration: none;">Cart (<?php echo $cart_count; ?>)</a></li>
                <?php 
                $user_role = $_SESSION['role'] ?? 'buyer';
                $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
                ?>
                <li><a href="<?php echo $profile_page; ?>" style="color: inherit; text-decoration: none;">Profile</a></li>
                <li><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></li>
            <?php else: ?>
                <li><a href="login_users.php" style="color: inherit; text-decoration: none;">Login</a></li>
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
                    <?php $bt_disp = ucwords(str_replace('_', ' ', (string)$seller['business_type'])); ?>
                    <p class="business-type"><?php echo htmlspecialchars($bt_disp); ?></p>
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

        <!-- Store Vouchers (if any) -->
        <?php if (!empty($store_vouchers)): ?>
            <!-- Replaced inline styles with class 'vouchers-card' -->
            <div class="vouchers-card">
                <h2>Store Vouchers</h2>
                <ul class="voucher-list">
                    <?php foreach ($store_vouchers as $v): ?>
                        <li class="voucher-item">
                            <div>
                                <div class="voucher-code">Code: <?php echo htmlspecialchars($v['code']); ?></div>
                                <div class="voucher-subtext">
                                    <?php echo $v['discount_type'] === 'percentage' ? (float)$v['discount_value'] . '% off' : '₱' . number_format((float)$v['discount_value'], 2) . ' off'; ?>
                                    <?php if ($v['min_purchase'] > 0): ?>
                                        · Min spend ₱<?php echo number_format((float)$v['min_purchase'], 2); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div class="voucher-subtext">Expires: <?php echo htmlspecialchars($v['expiry_date']); ?></div>
                                <button class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($v['code']); ?>'); alert('Voucher copied!');">Copy Code</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

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

        // Theme Toggle Logic
        const themeBtn = document.getElementById('themeBtn');
        const html = document.documentElement;
        
        themeBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            themeBtn.innerHTML = newTheme === 'dark' ? '<i class="bi bi-moon-stars-fill"></i>' : '<i class="bi bi-sun-fill"></i>';
            
            // Save to local storage
            localStorage.setItem('theme', newTheme);
            
            // Update session via AJAX (optional, but good for persistence)
            fetch('theme_toggle.php?theme=' + newTheme);
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