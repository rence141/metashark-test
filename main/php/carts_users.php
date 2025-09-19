
<?php
session_start();

// If user not logged in, redirect to login
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

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
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <style>
        /* Theme Variables */
        :root {
            --bg-primary: #0A0A0A;
            --bg-secondary: #111111;
            --bg-tertiary: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --text-muted: #888888;
            --border: #333333;
            --border-light: #444444;
            --accent: #44D62C;
            --accent-hover: #36b020;
            --accent-light: #2a5a1a;
            --shadow: rgba(0, 0, 0, 0.3);
            --shadow-hover: rgba(0, 0, 0, 0.4);
        }

        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'ASUS ROG', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Navbar */
        .navbar {
            background: var(--bg-secondary);
            padding: 15px 20px;
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            border-bottom: 2px solid var(--accent);
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
            background-color: var(--bg-secondary);
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
            background: var(--bg-tertiary);
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
            color: var(--text-primary);
            cursor: pointer;
            transition: color 0.3s, transform 0.2s, background-color 0.3s;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .menu li:hover {
            color: var(--accent);
            background-color: var(--bg-secondary);
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
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
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
            border: 2px solid var(--border);
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
            color: var(--text-primary);
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
            background: var(--bg-tertiary);
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
            color: var(--bg-primary);
            transform: scale(1.1);
        }

        .quantity-input {
            width: 60px;
            height: 30px;
            text-align: center;
            border: 1px solid var(--accent);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-radius: 4px;
            font-size: 1rem;
        }

        .remove-btn {
            background: #ff4444;
            color: var(--text-primary);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #cc3333;
            transform: scale(1.05);
        }

        /* Cart Summary */
        .cart-summary {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 25px;
            border: 1px solid var(--border);
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
            border-bottom: 1px solid var(--border);
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
            color: var(--text-primary);
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: var(--accent);
            color: var(--bg-primary);
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
            background: #ff4444;
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .clear-cart-btn:hover {
            background: #cc3333;
            transform: scale(1.05);
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-secondary);
            border-radius: 10px;
            border: 1px solid var(--border);
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
            background: var(--accent);
            color: var(--bg-primary);
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
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
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