
<?php
session_start();

// If user not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Default GCash number (replace with your store's GCash number or fetch from a config)
$default_gcash_number = '09123456789'; // TODO: Replace with actual number or make configurable

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
    $item_total = $item['price'] * $item['quantity'];
    $subtotal += $item_total;
    $total_items += $item['quantity'];
}
$tax = $subtotal * 0.08; // 8% tax
$discount = 0;
$voucher_code = '';
$voucher_message = '';

// Handle voucher application
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_voucher'])) {
    $voucher_code = $_POST['voucher_code'] ?? '';
    if (!empty($voucher_code)) {
        $voucher_query = "SELECT discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses 
                         FROM vouchers 
                         WHERE code = ? AND expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses)";
        $voucher_stmt = $conn->prepare($voucher_query);
        $voucher_stmt->bind_param("s", $voucher_code);
        $voucher_stmt->execute();
        $voucher_result = $voucher_stmt->get_result();
        
        if ($voucher_result->num_rows > 0) {
            $voucher = $voucher_result->fetch_assoc();
            if ($subtotal >= $voucher['min_purchase']) {
                if ($voucher['discount_type'] === 'percentage') {
                    $discount = $subtotal * ($voucher['discount_value'] / 100);
                } else {
                    $discount = $voucher['discount_value'];
                }
                $voucher_message = "Voucher applied successfully!";
                $_SESSION['applied_voucher'] = $voucher_code; // Store for checkout
            } else {
                $voucher_message = "Cart subtotal must be at least $" . number_format($voucher['min_purchase'], 2) . " to use this voucher.";
            }
        } else {
            $voucher_message = "Invalid or expired voucher code.";
        }
    } else {
        $voucher_message = "Please enter a voucher code.";
    }
}

// Apply previously saved voucher if exists
if (isset($_SESSION['applied_voucher'])) {
    $voucher_code = $_SESSION['applied_voucher'];
    $voucher_query = "SELECT discount_type, discount_value, min_purchase 
                     FROM vouchers 
                     WHERE code = ? AND expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses)";
    $voucher_stmt = $conn->prepare($voucher_query);
    $voucher_stmt->bind_param("s", $voucher_code);
    $voucher_stmt->execute();
    $voucher_result = $voucher_stmt->get_result();
    
    if ($voucher_result->num_rows > 0) {
        $voucher = $voucher_result->fetch_assoc();
        if ($subtotal >= $voucher['min_purchase']) {
            if ($voucher['discount_type'] === 'percentage') {
                $discount = $subtotal * ($voucher['discount_value'] / 100);
            } else {
                $discount = $voucher['discount_value'];
            }
        } else {
            unset($_SESSION['applied_voucher']);
            $voucher_message = "Voucher removed: Cart subtotal is below minimum requirement.";
        }
    } else {
        unset($_SESSION['applied_voucher']);
        $voucher_message = "Applied voucher is no longer valid.";
    }
}

$total = $subtotal + $tax - $discount;

// Handle checkout submission
$order_success = false;
$order_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout'])) {
    $shipping_name = $_POST['shipping_name'] ?? '';
    $shipping_address = $_POST['shipping_address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';

    // Basic validation
    if (empty($shipping_name) || empty($shipping_address) || empty($payment_method)) {
        $order_message = "Please fill in all required fields.";
    } elseif (empty($cart_items)) {
        $order_message = "Your cart is empty.";
    } elseif ($payment_method === 'gcash' && empty($default_gcash_number)) {
        $order_message = "No GCash number configured for payment.";
    } else {
        // Update voucher usage
        if (isset($_SESSION['applied_voucher'])) {
            $update_voucher = "UPDATE vouchers SET current_uses = current_uses + 1 WHERE code = ?";
            $update_stmt = $conn->prepare($update_voucher);
            $update_stmt->bind_param("s", $_SESSION['applied_voucher']);
            $update_stmt->execute();
        }

        // Clear cart
        $clear_sql = "DELETE FROM cart WHERE user_id = ?";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();

        // Simulate order creation (replace with actual order insertion into database)
        $order_message = "Order placed successfully! Thank you for your purchase.";
        $order_success = true;
        unset($_SESSION['applied_voucher']); // Clear voucher after checkout
        $cart_items = [];
        $total_items = 0;
        $subtotal = 0;
        $tax = 0;
        $discount = 0;
        $total = 0;
    }
}

// Fetch user profile for pre-filling form
$profile_query = "SELECT fullname FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$default_name = $profile['fullname'] ?? '';
$default_address = '';
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/checkout_users.css">
    <?php include("theme_toggle.php"); ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
   
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            const notification = document.getElementById('orderNotification') || document.getElementById('voucherNotification');
            if (notification) {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 3000);
            }

            // QR Code modal
            const paymentMethodSelect = document.getElementById('payment_method');
            const qrModal = document.getElementById('qrModal');
            const qrContent = document.getElementById('qrContent');
            const closeModalBtn = document.querySelector('.close-btn');
            const gcashNumber = '<?php echo addslashes($default_gcash_number); ?>';
            const total = <?php echo json_encode($total); ?>;

            function updateQRModal() {
                if (paymentMethodSelect.value === 'gcash') {
                    qrContent.innerHTML = ''; // Clear previous content
                    if (gcashNumber && total > 0) {
                        const qrDiv = document.createElement('div');
                        qrDiv.className = 'qr-code-item';
                        qrDiv.id = 'qrCode';
                        const p = document.createElement('p');
                        p.textContent = `Pay $${Number(total).toFixed(2)} to ${gcashNumber}`;
                        qrContent.appendChild(p);
                        qrContent.appendChild(qrDiv);
                        const qrText = `gcash://send?number=${gcashNumber}&amount=${total.toFixed(2)}`;
                        new QRCode(qrDiv, {
                            text: qrText,
                            width: 150,
                            height: 150,
                            colorDark: '#000000',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.H
                        });
                        qrModal.classList.add('show');
                    } else {
                        qrModal.classList.remove('show');
                        alert('No GCash number configured or cart total is zero.');
                    }
                } else {
                    qrModal.classList.remove('show');
                    qrContent.innerHTML = ''; // Clear QR codes
                }
            }

            paymentMethodSelect.addEventListener('change', updateQRModal);
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', () => {
                    qrModal.classList.remove('show');
                    qrContent.innerHTML = ''; // Clear QR codes
                });
            }

            // Initial check
            updateQRModal();
        });
    </script>
</head>
<body>
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

    <?php if (!empty($order_message)): ?>
        <div class="notification <?php echo $order_success ? 'success' : 'error'; ?> show" id="orderNotification">
            <?php echo $order_success ? '✅' : '❌'; ?> <?php echo htmlspecialchars($order_message); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($voucher_message)): ?>
        <div class="notification <?php echo strpos($voucher_message, 'successfully') !== false ? 'success' : 'error'; ?> show" id="voucherNotification">
            <?php echo strpos($voucher_message, 'successfully') !== false ? '✅' : '❌'; ?> <?php echo htmlspecialchars($voucher_message); ?>
        </div>
    <?php endif; ?>

    <div class="modal" id="qrModal">
        <div class="modal-content">
            <h3>GCash Payment QR Code</h3>
            <div id="qrContent"></div>
            <button class="close-btn">Close</button>
        </div>
    </div>

    <div class="checkout-container">
        <div class="checkout-header">
            <h1 class="checkout-title">Checkout</h1>
            <p class="checkout-subtitle"><?php echo $total_items; ?> item(s) in your order</p>
        </div>

        <?php if (empty($cart_items) && !$order_success): ?>
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="shop-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="checkout-content">
                <div class="checkout-form">
                    <h3 class="summary-title">Shipping & Payment</h3>
                    <form method="POST">
                        <div class="form-group voucher-group">
                            <div>
                                <label for="voucher_code">Voucher Code</label>
                                <input type="text" id="voucher_code" name="voucher_code" value="<?php echo htmlspecialchars($voucher_code); ?>" placeholder="Enter voucher code">
                            </div>
                            <button type="submit" name="apply_voucher" class="apply-voucher-btn">Apply</button>
                        </div>
                        <input type="hidden" name="checkout" value="1">
                        <div class="form-group">
                            <label for="shipping_name">Full Name</label>
                            <input type="text" id="shipping_name" name="shipping_name" value="<?php echo htmlspecialchars($default_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address</label>
                            <textarea id="shipping_address" name="shipping_address" required><?php echo htmlspecialchars($default_address); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="" disabled selected>Select payment method</option>
                                <option value="gcash">GCash</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <button type="submit" class="confirm-btn" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>Confirm Order</button>
                    </form>
                </div>

                <div class="order-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-row">
                            <span class="summary-label"><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                            <span class="summary-value">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-row">
                        <span class="summary-label">Subtotal (<?php echo $total_items; ?> items):</span>
                        <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Tax (8%):</span>
                        <span class="summary-value">$<?php echo number_format($tax, 2); ?></span>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div class="summary-row">
                            <span class="summary-label">Discount (<?php echo htmlspecialchars($voucher_code); ?>):</span>
                            <span class="summary-value">-$<?php echo number_format($discount, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-row">
                        <span class="summary-label">Total:</span>
                        <span class="summary-value">$<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
