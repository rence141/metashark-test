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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - MetaAccessories</title>
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
            color: #44D62C;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .cart-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            height: 2px;
            background: #44D62C;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
        }

        .cart-subtitle {
            color: #888;
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
            background: #111111;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #333333;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #333333;
            gap: 20px;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #333333;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 1.2rem;
            color: #FFFFFF;
            margin-bottom: 5px;
        }

        .item-description {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .item-price {
            font-size: 1.1rem;
            color: #44D62C;
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
            border: 1px solid #44D62C;
            background: #111111;
            color: #44D62C;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: #44D62C;
            color: #000000;
        }

        .quantity-input {
            width: 60px;
            height: 30px;
            text-align: center;
            border: 1px solid #44D62C;
            background: #111111;
            color: #FFFFFF;
            border-radius: 4px;
            font-size: 1rem;
        }

        .remove-btn {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .remove-btn:hover {
            background: #cc3333;
        }

        /* Cart Summary */
        .cart-summary {
            background: #111111;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #333333;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 1.5rem;
            color: #44D62C;
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #333333;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: #44D62C;
        }

        .summary-label {
            color: #888;
        }

        .summary-value {
            color: #FFFFFF;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: #44D62C;
            color: #000000;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .checkout-btn:hover {
            background: #36b020;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.3);
        }

        .clear-cart-btn {
            width: 100%;
            padding: 10px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }

        .clear-cart-btn:hover {
            background: #cc3333;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: #111111;
            border-radius: 10px;
            border: 1px solid #333333;
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            color: #44D62C;
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: #888;
            margin-bottom: 25px;
        }

        .shop-btn {
            display: inline-block;
            padding: 12px 25px;
            background: #44D62C;
            color: #000000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .shop-btn:hover {
            background: #36b020;
            transform: translateY(-2px);
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
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <h2>Meta Shark</h2>
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
            <li><a href="carts_users.php" style="color: white; text-decoration: none;">Cart (<?php echo $total_items; ?>)</a></li>
            <?php 
            $user_role = $_SESSION['role'] ?? 'buyer';
            $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
            ?>
            <li><a href="<?php echo $profile_page; ?>" style="color: white; text-decoration: none;">Profile</a></li>
            <li><a href="logout.php" style="color: white; text-decoration: none;">Logout</a></li>
        </ul>
    </div>

    <!-- CART CONTAINER -->
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
                    
                    <button class="checkout-btn" onclick="alert('Checkout functionality coming soon!')">
                        Proceed to Checkout
                    </button>
                    
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

    <script>
        // Toggle Hamburger Menu
        const hamburger = document.querySelector(".hamburger");
        const menu = document.getElementById("menu");

        hamburger.addEventListener("click", () => {
            menu.classList.toggle("show");
        });

        // Close menu when clicking outside
        document.addEventListener("click", (e) => {
            if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove("show");
            }
        });
    </script>
</body>
</html>
