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
if ($ver) { 
    $ver->bind_param("i", $user_id); 
    $ver->execute(); 
    $vr = $ver->get_result(); 
    $vu = $vr->fetch_assoc(); 
}
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

// Success message
$success = "";
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success = "Product deleted successfully!";
}

// Seller products
$products_sql = "SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC";
$products_stmt = $conn->prepare($products_sql);
$products_stmt->bind_param("i", $user_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = $products_result->fetch_all(MYSQLI_ASSOC);

// Seller stats
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

// Cart count
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
    <title>Seller Dashboard - MetaAccessories</title>
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
            transition: background-color 0.3s ease;
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

        .cart-count {
            background: var(--primary-color);
            color: #000;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .container {
            padding: 20px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .dashboard-title {
            font-size: 24px;
            margin: 0;
        }

        .stats-grid {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: var(--secondary-bg);
            padding: 16px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            text-align: center;
            flex: 1;
            max-width: 200px;
        }

        .stat-number {
            font-size: 20px;
            font-weight: bold;
        }

        .actions {
            text-align: center;
            margin-bottom: 24px;
        }

        .btn {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            margin: 6px;
            display: inline-block;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #000;
        }

        .btn-secondary {
            background-color: var(--secondary-bg);
            color: var(--text-color);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .product-card {
            background-color: var(--secondary-bg);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 10px;
            text-align: center;
        }

        .product-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
        }

        .btn-danger {
            background-color: #e53935;
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-danger:hover {
            background-color: #c62828;
            transform: scale(1.03);
        }

        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
            }

            .nav-right {
                gap: 8px;
            }

            .menu {
                width: 100%;
                right: 0;
                top: 70px;
                padding: 15px;
            }

            .theme-btn {
                min-width: 100px;
                font-size: 12px;
            }

            .profile-icon {
                width: 32px;
                height: 32px;
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
            <li role="none"><a href="carts_users.php" role="menuitem">My Cart <?php echo $cart_count > 0 ? "<span class='cart-count'>$cart_count</span>" : ''; ?></a></li>
            <li role="none"><a href="<?php echo $profile_page; ?>" role="menuitem">Profile</a></li>
            <li role="none"><a href="logout.php" role="menuitem">Logout</a></li>
            <li role="none"><a href="seller_order_status.php" role="menuitem">Orders</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Seller Dashboard</h1>
            <p class="seller-name">Welcome, <?php echo htmlspecialchars($user_data['seller_name'] ?: $_SESSION['fullname']); ?>!</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $stats['total_products']; ?></div><div class="stat-label">Total Products</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['total_stock']; ?></div><div class="stat-label">Total Stock</div></div>
            <div class="stat-card"><div class="stat-number">$<?php echo number_format($stats['avg_price'], 2); ?></div><div class="stat-label">Average Price</div></div>
        </div>

        <div class="actions">
            <a href="add_product.php" class="btn btn-primary">Add Product</a>
            <a href="shop.php" class="btn btn-secondary">View Store</a>
            <a href="carts_users.php" class="btn btn-secondary">My Cart</a>
            <a href="seller_profile.php" class="btn btn-secondary">Profile</a>
            <a href="seller_vouchers.php" class="btn btn-secondary">Vouchers</a>
            <a href="seller_order_status.php" class="btn btn-secondary">Orders</a>
        </div>

        <div class="products-section">
            <h2>My Products</h2>
            <?php if (empty($products)): ?>
                <div class="empty-state" style="text-align:center;">
                    <h3>No products yet</h3>
                    <p>Start by adding your first product!</p>
                    <a href="add_product.php" class="btn btn-primary" style="margin-top: 20px;">Add Product</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-stock">Stock: <?php echo $product['stock_quantity']; ?></p>
                            <div class="product-actions">
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary btn-small">Edit</a>
                                <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Delete this product?')">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle functionality
            const themeDropdown = document.getElementById('themeDropdown');
            const themeMenu = document.getElementById('themeMenu');
            const themeBtn = document.getElementById('themeDropdownBtn');
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            let currentTheme = localStorage.getItem('theme') || '<?php echo htmlspecialchars($theme); ?>';

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
                localStorage.setItem('theme', theme);
                
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
        });
    </script>
</body>
</html>