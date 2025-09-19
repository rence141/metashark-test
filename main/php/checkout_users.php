
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
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

        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .checkout-title {
            font-size: 2.5rem;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
            animation: fadeIn 1s ease-out;
        }

        .checkout-title::after {
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

        .checkout-subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        .order-summary {
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

        .checkout-form {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 25px;
            border: 1px solid var(--border);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 8px rgba(68, 214, 44, 0.5);
            transform: scale(1.02);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .voucher-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .voucher-group input {
            flex: 1;
        }

        .apply-voucher-btn {
            padding: 10px 20px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .apply-voucher-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
        }

        .confirm-btn {
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
            position: relative;
            overflow: hidden;
        }

        .confirm-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
            animation: buttonGlow 1s ease-in-out infinite;
        }

        @keyframes buttonGlow {
            0%, 100% { box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4); }
            50% { box-shadow: 0 5px 20px rgba(68, 214, 44, 0.6); }
        }

        .confirm-btn:active {
            transform: scale(0.95);
        }

        .confirm-btn:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .confirm-btn::after {
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

        .confirm-btn:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: var(--bg-primary);
            font-weight: bold;
            z-index: 10000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .notification.show {
            transform: translateX(0);
            animation: bounceIn 0.5s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: translateX(400px) scale(0.8); opacity: 0; }
            60% { transform: translateX(-10px) scale(1.05); opacity: 1; }
            100% { transform: translateX(0) scale(1); opacity: 1; }
        }

        .notification.success {
            background: linear-gradient(135deg, #44D62C, #36b020);
            border-left: 4px solid #2a8a1a;
        }

        .notification.error {
            background: linear-gradient(135deg, #ff4444, #cc3333);
            border-left: 4px solid #aa2222;
        }

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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10001;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            border: 1px solid var(--border);
            box-shadow: 0 5px 15px var(--shadow);
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content h3 {
            color: var(--accent);
            margin-bottom: 20px;
        }

        .modal-content p {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .qr-code-item {
            margin-bottom: 20px;
        }

        .qr-code-item div {
            background: white;
            padding: 10px;
            border-radius: 5px;
            display: inline-block;
        }

        .close-btn {
            padding: 10px 20px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .close-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
