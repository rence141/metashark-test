
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
    <link rel="stylesheet" href="../../css/carts_users.css">
    <script>
        // Handle loading screen
       

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
            };
    </script>
</head>
<body>

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
            <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                <span style="font-size:18px;">🛒</span>
                <span>(<?php echo (int)$total_items; ?>)</span>
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

                                <form method="GET" action="checkout_users.php" style="display: inline; margin-left: 8px;">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" class="checkout-btn">Checkout This Item</button>
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
                    <a href="orders.php" class="checkout-btn" style="background:#333; margin-top:8px;">My Orders</a>
                    
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
