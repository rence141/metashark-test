
<?php
session_start();

// If user not logged in, redirect to login
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

// Handle theme toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["toggle_theme"])) {
    $_SESSION['theme'] = ($_SESSION['theme'] ?? 'dark') === 'dark' ? 'light' : 'dark';
    header("Location: carts_users.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Handle cart operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"] ?? "";
    $product_id = $_POST["product_id"] ?? 0;
    $quantity = $_POST["quantity"] ?? 1;
    
    switch ($action) {
        case "update_quantity":
            if ($quantity > 0) {
                $sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $quantity, $user_id, $product_id);
                $stmt->execute();
            }
            break;
            
        case "remove_item":
            $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            break;
            
        case "clear_cart":
            $sql = "DELETE FROM cart WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            break;
    }
    
    // Redirect to prevent form resubmission
    header("Location: carts_users.php");
    exit();
}

// Fetch cart items with product details
$sql = "SELECT c.*, p.name, p.price, p.image, p.description 
        FROM cart c 
        LEFT JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}
$tax = $subtotal * 0.08; // 8% tax
$total = $subtotal + $tax;

$theme = $_SESSION['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <?php include("theme_toggle.php"); ?>
    <style>
        /* Theme Variables */
        :root {
            --color-background: #0A0A0A;
            --color-foreground: #FFFFFF;
            --navbar-bg: #000000;
            --navbar-border: #44D62C;
            --card-bg: #111111;
            --card-border: #333333;
            --product-card-bg: #1A1A1A;
            --accent: #44D62C;
            --accent-hover: #36B020;
            --text-muted: #888888;
            --button-primary-bg: #44D62C;
            --button-primary-text: #000000;
            --button-secondary-bg: #333333;
            --button-secondary-border: #44D62C;
            --button-danger-bg: #FF4444;
            --button-danger-hover: #CC3333;
            --shadow: rgba(0, 0, 0, 0.3);
            --shadow-hover: rgba(0, 0, 0, 0.4);
        }

        [data-theme="light"] {
            --color-background: #F5F5F5;
            --color-foreground: #000000;
            --navbar-bg: #FFFFFF;
            --navbar-border: #44D62C;
            --card-bg: #E0E0E0;
            --card-border: #BBBBBB;
            --product-card-bg: #D5D5D5;
            --accent: #44D62C;
            --accent-hover: #36B020;
            --text-muted: #666666;
            --button-primary-bg: #44D62C;
            --button-primary-text: #000000;
            --button-secondary-bg: #CCCCCC;
            --button-secondary-border: #44D62C;
            --button-danger-bg: #FF4444;
            --button-danger-hover: #CC3333;
            --shadow: rgba(0, 0, 0, 0.2);
            --shadow-hover: rgba(0, 0, 0, 0.3);
        }

        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'ASUS ROG', Arial, sans-serif;
            background: var(--color-background);
            color: var(--color-foreground);
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Navbar */
        .navbar {
            background: var(--navbar-bg);
            padding: 15px 20px;
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--navbar-border);
            box-shadow: 0 2px 10px var(--shadow);
            animation: slideInFromTop 0.5s ease-out;
        }

        @keyframes slideInFromTop {
            0% { transform: translateY(-100%); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            height: 40px;
            width: auto;
            border-radius: 5px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 0 15px rgba(68, 214, 44, 0.8);
        }

        .navbar h2 {
            margin: 0;
            transition: color 0.3s ease;
        }

        .navbar h2:hover {
            color: var(--accent-hover);
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
            background-color: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
            border: 2px solid var(--accent);
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
        }

        .profile-icon:hover {
            transform: scale(1.15) rotate(10deg);
            box-shadow: 0 0 20px rgba(68, 214, 44, 1);
        }

        .theme-toggle-btn {
            padding: 8px 16px;
            background: var(--button-secondary-bg);
            color: var(--color-foreground);
            border: 1px solid var(--button-secondary-border);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .theme-toggle-btn:hover {
            background: var(--accent);
            color: var(--button-primary-text);
            transform: translateY(-2px);
        }

        .hamburger {
            font-size: 24px;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--accent);
            transition: transform 0.3s ease;
        }

        .hamburger:hover {
            transform: scale(1.2) rotate(90deg);
        }

        .menu {
            position: absolute;
            top: 60px;
            right: 20px;
            background: var(--product-card-bg);
            list-style: none;
            padding: 15px;
            border-radius: 8px;
            display: none;
            flex-direction: column;
            gap: 10px;
            border: 1px solid var(--accent);
            box-shadow: 0 0 10px var(--shadow);
            z-index: 9999;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .menu.show {
            display: flex;
            transform: translateY(0);
            opacity: 1;
        }

        .menu li {
            color: var(--color-foreground);
            cursor: pointer;
            transition: color 0.3s, transform 0.2s, background-color 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .menu li:hover {
            color: var(--accent);
            background-color: var(--card-bg);
            transform: translateX(10px);
        }

        /* Cart Container */
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .cart-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .cart-title {
            font-size: 2.5rem;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
            animation: fadeIn 1s ease-out;
        }

        .cart-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            height: 2px;
            background: var(--accent);
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
            animation: expandWidth 1s ease-out;
        }

        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        @keyframes expandWidth {
            0% { width: 0; }
            100% { width: 100%; }
        }

        .cart-subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        /* Cart Content */
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        /* Cart Items */
        .cart-items {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid var(--card-border);
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--card-border);
            gap: 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--card-border);
            transition: transform 0.3s ease;
        }

        .item-image:hover {
            transform: scale(1.1);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 1.2rem;
            color: var(--color-foreground);
            margin-bottom: 5px;
        }

        .item-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .item-price {
            font-size: 1.1rem;
            color: var(--accent);
            font-weight: bold;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid var(--accent);
            background: var(--product-card-bg);
            color: var(--accent);
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--accent);
            color: var(--button-primary-text);
            transform: scale(1.1);
        }

        .quantity-input {
            width: 60px;
            height: 30px;
            text-align: center;
            border: 1px solid var(--accent);
            background: var(--product-card-bg);
            color: var(--color-foreground);
            border-radius: 4px;
            font-size: 1rem;
        }

        .remove-btn {
            background: var(--button-danger-bg);
            color: var(--color-foreground);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: var(--button-danger-hover);
            transform: scale(1.05);
        }

        /* Cart Summary */
        .cart-summary {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 25px;
            border: 1px solid var(--card-border);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 1.5rem;
            color: var(--accent);
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid var(--card-border);
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--accent);
        }

        .summary-label {
            color: var(--text-muted);
        }

        .summary-value {
            color: var(--color-foreground);
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: var(--button-primary-bg);
            color: var(--button-primary-text);
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .checkout-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
            animation: buttonGlow 1s ease-in-out infinite;
        }

        @keyframes buttonGlow {
            0%, 100% { box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4); }
            50% { box-shadow: 0 5px 20px rgba(68, 214, 44, 0.6); }
        }

        .checkout-btn:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .checkout-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
        }

        .checkout-btn:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
        }

        .clear-cart-btn {
            width: 100%;
            padding: 10px;
            background: var(--button-danger-bg);
            color: var(--color-foreground);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .clear-cart-btn:hover {
            background: var(--button-danger-hover);
            transform: scale(1.05);
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid var(--card-border);
            animation: fadeIn 1s ease-out;
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            color: var(--accent);
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        .shop-btn {
            display: inline-block;
            padding: 12px 25px;
            background: var(--button-primary-bg);
            color: var(--button-primary-text);
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .shop-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
            animation: buttonGlow 1s ease-in-out infinite;
        }

        /* Loading Screen Styles */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .loading-screen.active {
            opacity: 1;
            visibility: visible;
        }

        .logo-container {
            position: relative;
            width: 200px;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-outline {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: url('Uploads/logo1.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.5;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.5;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.7;
            }
        }

        .logo-fill {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: url('Uploads/logo1.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            clip-path: inset(100% 0 0 0);
            animation: water-fill 2.5s ease-in-out infinite;
            filter: brightness(1.2) saturate(1.2);
        }

        @keyframes water-fill {
            0% {
                clip-path: inset(100% 0 0 0);
                filter: hue-rotate(0deg);
            }
            50% {
                clip-path: inset(0 0 0 0);
                filter: hue-rotate(30deg);
            }
            100% {
                clip-path: inset(100% 0 0 0);
                filter: hue-rotate(0deg);
            }
        }

        .loading-text {
            color: var(--accent);
            font-size: 24px;
            margin-top: 20px;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
            animation: text-wave 2.5s ease-in-out infinite;
        }

        @keyframes text-wave {
            0%, 100% {
                opacity: 0.7;
                transform: translateY(0);
            }
            50% {
                opacity: 1;
                transform: translateY(-5px);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-controls {
                justify-content: center;
            }

            .nav-left {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
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

            // Toggle Hamburger Menu
            const hamburger = document.querySelector('.hamburger');
            const menu = document.getElementById('menu');
            if (hamburger && menu) {
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    menu.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });

                const menuItems = menu.querySelectorAll('a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        menu.classList.remove('show');
                    });
                });
            }
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

    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
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
        </div>
        <div class="nav-right">
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
            <a href="<?php echo $profile_page; ?>">
                <?php if (!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                    <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="Uploads/default-avatar.svg" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
            <button class="hamburger">☰</button>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <li><a href="carts_users.php">Cart (<?php echo $total_items; ?>)</a></li>
            <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
                <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
            <?php else: ?>
                <li><a href="become_seller.php">Become Seller</a></li>
            <?php endif; ?>
            <li><a href="<?php echo $profile_page; ?>">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Cart Container -->
    <div class="cart-container">
        <div class="cart-header">
            <h1 class="cart-title">Shopping Cart</h1>
            <p class="cart-subtitle"><?php echo $total_items; ?> item(s) in your cart</p>
        </div>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="shop-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <!-- Cart Content -->
            <div class="cart-content">
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo $item['image'] ?: 'https://picsum.photos/100/100'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="item-image">
                            
                            <div class="item-details">
                                <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                <p class="item-price">$<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                            
                            <div class="item-controls">
                                <div class="quantity-controls">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_quantity">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" 
                                                class="quantity-btn" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                    </form>
                                    
                                    <input type="number" value="<?php echo $item['quantity']; ?>" 
                                           class="quantity-input" readonly>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_quantity">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" 
                                                class="quantity-btn">+</button>
                                    </form>
                                </div>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" class="remove-btn" 
                                            onclick="return confirm('Remove this item from cart?')">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    
                    <div class="summary-row">
                        <span class="summary-label">Subtotal (<?php echo $total_items; ?> items):</span>
                        <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Tax (8%):</span>
                        <span class="summary-value">$<?php echo number_format($tax, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Total:</span>
                        <span class="summary-value">$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <a href="checkout_users.php" class="checkout-btn" <?php echo $total_items == 0 ? 'disabled' : ''; ?>>Proceed to Checkout</a>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="clear_cart">
                        <button type="submit" class="clear-cart-btn" 
                                onclick="return confirm('Clear entire cart? This action cannot be undone.')">
                            Clear Cart
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
