<?php
session_start();

// Security: Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

// Enforce verified users only
if (!isset($_SESSION['role'])) {
    // fetch from DB to check is_verified quickly
    include("db.php");
    $uid = $_SESSION['user_id'];
    $chk = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
    if ($chk) { $chk->bind_param("i", $uid); $chk->execute(); $r = $chk->get_result(); $u = $r->fetch_assoc(); }
    if (empty($u) || (int)($u['is_verified'] ?? 0) !== 1) {
        header("Location: verify_account.php");
        exit();
    }
}

include("db.php");
include_once("email.php");
$user_id = $_SESSION['user_id'];

// Fetch available vouchers for dropdown
$available_vouchers = [];
$voucher_list_query = "SELECT code FROM vouchers WHERE expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses) ORDER BY code";
$voucher_list_stmt = $conn->prepare($voucher_list_query);
if ($voucher_list_stmt) {
    $voucher_list_stmt->execute();
    $voucher_list_result = $voucher_list_stmt->get_result();
    while ($v = $voucher_list_result->fetch_assoc()) {
        $available_vouchers[] = $v['code'];
    }
}

// Fetch user's contact number dynamically (use phone if available)
$default_gcash_number = '';
$gcq = $conn->prepare("SELECT phone FROM users WHERE id = ?");
if ($gcq) {
    $gcq->bind_param("i", $user_id);
    $gcq->execute();
    $gcr = $gcq->get_result();
    if ($gcr->num_rows) {
        $row = $gcr->fetch_assoc();
        $default_gcash_number = trim((string)($row['phone'] ?? ''));
    }
}

// Support single-item checkout via ?product_id=...
$onlyProductId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Fetch cart items with product details (basic query without seller join to avoid error)
if ($onlyProductId > 0) {
    $sql = "SELECT c.*, p.name, p.price, p.image, p.description 
            FROM cart c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? AND c.product_id = ? 
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $onlyProductId);
} else {
    $sql = "SELECT c.*, p.name, p.price, p.image, p.description 
            FROM cart c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? 
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}
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
$subtotal = round($subtotal, 2);
$tax = round($subtotal * 0.08, 2); // 8% tax
$discount = 0;
$voucher_code = '';
$voucher_message = '';

// Handle voucher application
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_voucher']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $voucher_code = filter_input(INPUT_POST, 'voucher_code', FILTER_SANITIZE_STRING) ?? '';
    if (!empty($voucher_code)) {
        $voucher_query = "SELECT discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses 
                         FROM vouchers 
                         WHERE code = ? AND expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses)";
        $voucher_stmt = $conn->prepare($voucher_query);
        if ($voucher_stmt) {
            $voucher_stmt->bind_param("s", $voucher_code);
            $voucher_stmt->execute();
            $voucher_result = $voucher_stmt->get_result();
            
            if ($voucher_result->num_rows > 0) {
                $voucher = $voucher_result->fetch_assoc();
                if ($subtotal >= $voucher['min_purchase']) {
                    $discount = $voucher['discount_type'] === 'percentage' 
                        ? $subtotal * ($voucher['discount_value'] / 100) 
                        : $voucher['discount_value'];
                    $voucher_message = "Voucher applied successfully!";
                    $_SESSION['applied_voucher'] = $voucher_code;
                } else {
                    $voucher_message = "Cart subtotal must be at least $" . number_format($voucher['min_purchase'], 2) . " to use this voucher.";
                }
            } else {
                $voucher_message = "Invalid or expired voucher code.";
            }
        } else {
            $voucher_message = "Voucher system not configured.";
        }
    } else {
        $voucher_message = "Please select a voucher code.";
    }
}

// Apply previously saved voucher
if (isset($_SESSION['applied_voucher'])) {
    $voucher_code = $_SESSION['applied_voucher'];
    $voucher_query = "SELECT discount_type, discount_value, min_purchase 
                     FROM vouchers 
                     WHERE code = ? AND expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses)";
    $voucher_stmt = $conn->prepare($voucher_query);
    if ($voucher_stmt) {
        $voucher_stmt->bind_param("s", $voucher_code);
        $voucher_stmt->execute();
        $voucher_result = $voucher_stmt->get_result();
        
        if ($voucher_result->num_rows > 0) {
            $voucher = $voucher_result->fetch_assoc();
            if ($subtotal >= $voucher['min_purchase']) {
                $discount = $voucher['discount_type'] === 'percentage' 
                    ? $subtotal * ($voucher['discount_value'] / 100) 
                    : $voucher['discount_value'];
            } else {
                unset($_SESSION['applied_voucher']);
                $voucher_message = "Voucher removed: Cart subtotal is below minimum requirement.";
            }
        } else {
            unset($_SESSION['applied_voucher']);
            $voucher_message = "Applied voucher is no longer valid.";
        }
    } else {
        unset($_SESSION['applied_voucher']);
        $voucher_message = "Voucher system not configured.";
    }
}

$discount = round($discount, 2);
$total = round($subtotal + $tax - $discount, 2);

// Handle checkout submission
$order_success = false;
$order_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $shipping_name = filter_input(INPUT_POST, 'shipping_name', FILTER_SANITIZE_STRING) ?? '';
    $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_STRING) ?? '';
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING) ?? '';

    // Enhanced validation
    if (empty($shipping_name) || empty($shipping_address) || empty($payment_method)) {
        $order_message = "Please fill in all required fields.";
    } elseif (strlen($shipping_name) > 100 || strlen($shipping_address) > 500) {
        $order_message = "Input fields exceed maximum length.";
    } elseif (empty($cart_items)) {
        $order_message = "Your cart is empty.";
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();

            // Re-fetch latest cart and prices, verify stock & product active
            $verify_sql = "SELECT c.product_id, c.quantity, p.price, p.is_active, p.stock_quantity AS stock_quantity
                           FROM cart c JOIN products p ON p.id = c.product_id
                           WHERE c.user_id = ?";
            $vs = $conn->prepare($verify_sql);
            $latest_items = [];
            $latest_subtotal = 0;
            if ($vs) {
                $vs->bind_param("i", $user_id);
                $vs->execute();
                $rs = $vs->get_result();
                while ($row = $rs->fetch_assoc()) {
                    if (!($row['is_active'] ?? 1)) { throw new Exception('Product inactive.'); }
                    if ((int)$row['stock_quantity'] < (int)$row['quantity']) { throw new Exception('Insufficient stock.'); }
                    $latest_items[] = $row;
                    $latest_subtotal += ((float)$row['price'] * (int)$row['quantity']);
                }
            }
            $latest_subtotal = round($latest_subtotal, 2);
            $latest_tax = round($latest_subtotal * 0.08, 2);
            $latest_discount = 0.0;
            if (!empty($_SESSION['applied_voucher'])) {
                $codeCheck = $_SESSION['applied_voucher'];
                // Load voucher and optional seller scoping
                $hasSeller = false;
                $col = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'seller_id'");
                if ($col && $col->num_rows > 0) { $hasSeller = true; }
                if ($hasSeller) {
                    $vq = $conn->prepare("SELECT discount_type, discount_value, min_purchase, seller_id FROM vouchers WHERE code = ? AND expiry_date > NOW()");
                } else {
                    $vq = $conn->prepare("SELECT discount_type, discount_value, min_purchase, NULL AS seller_id FROM vouchers WHERE code = ? AND expiry_date > NOW()");
                }
                if ($vq) {
                    $vq->bind_param("s", $codeCheck);
                    $vq->execute();
                    $vr = $vq->get_result();
                    if ($vr->num_rows > 0) {
                        $v = $vr->fetch_assoc();
                        // If seller-scoped, compute subtotal for that seller only
                        $scopeSubtotal = 0.0;
                        if (!empty($v['seller_id'])) {
                            foreach ($latest_items as $li) {
                                // Need seller_id for product; fetch quickly
                                $ps = $conn->prepare("SELECT seller_id FROM products WHERE id = ?");
                                if ($ps) { $pid = (int)$li['product_id']; $ps->bind_param("i", $pid); $ps->execute(); $pr = $ps->get_result(); if ($pr->num_rows) { $prow = $pr->fetch_assoc(); if ((int)$prow['seller_id'] === (int)$v['seller_id']) { $scopeSubtotal += ((float)$li['price'] * (int)$li['quantity']); } } }
                            }
                        } else {
                            $scopeSubtotal = $latest_subtotal;
                        }

                        if ($scopeSubtotal >= (float)$v['min_purchase']) {
                            $latest_discount = ($v['discount_type'] === 'percentage') ? round($scopeSubtotal * ((float)$v['discount_value'] / 100), 2) : round((float)$v['discount_value'], 2);
                        } else {
                            unset($_SESSION['applied_voucher']);
                        }
                    }
                }
            }
            $latest_total = round($latest_subtotal + $latest_tax - $latest_discount, 2);

            // Attempt to create order (mark as paid immediately; email verification already required)
            $order_created = false;
            $order_id = null;
            try {
                // Create order with status
                $order_query = "INSERT INTO orders (user_id, shipping_name, shipping_address, payment_method, subtotal, tax, discount, total, status, created_at, paid_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW(), NOW())";
                $order_stmt = $conn->prepare($order_query);
                if ($order_stmt) {
                    $order_stmt->bind_param("isssdddd", $user_id, $shipping_name, $shipping_address, $payment_method, $latest_subtotal, $latest_tax, $latest_discount, $latest_total);
                    $order_stmt->execute();
                    $order_id = $conn->insert_id;
                    $order_created = true;
                }
            } catch (Exception $e) {
                error_log("Order DB insert failed: " . $e->getMessage());
            }

            // Insert order items if order was created
            if ($order_created) {
                try {
                    $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                    $item_stmt = $conn->prepare($item_query);
                    if ($item_stmt) {
                        foreach ($latest_items as $li) {
                            $pid = (int)$li['product_id'];
                            $qty = (int)$li['quantity'];
                            $price = (float)$li['price'];
                            $item_stmt->bind_param("iiid", $order_id, $pid, $qty, $price);
                            $item_stmt->execute();
                        }
                    }
                } catch (Exception $e) {
                    error_log("Order items insert failed: " . $e->getMessage());
                }
            }

            // Commit creation of paid order + items
            $conn->commit();

            // Stock deduction moved to order_status.php when status updated to 'delivered'

            // Update voucher usage if applicable
            if (isset($_SESSION['applied_voucher'])) {
                try {
                    $update_voucher = "UPDATE vouchers SET current_uses = current_uses + 1 WHERE code = ?";
                    $update_stmt = $conn->prepare($update_voucher);
                    if ($update_stmt) {
                        $update_stmt->bind_param("s", $_SESSION['applied_voucher']);
                        $update_stmt->execute();
                    }
                } catch (Exception $e) {
                    error_log("Voucher update failed: " . $e->getMessage());
                }
            }

            // Clear cart
            $clear_sql = "DELETE FROM cart WHERE user_id = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            if ($clear_stmt) {
                $clear_stmt->bind_param("i", $user_id);
                $clear_stmt->execute();
            }
            
            // Add notification for buyer
            // Create product list for notification
            $product_list = "";
            foreach ($latest_items as $item) {
                $product_id = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $product_list .= "Product #$product_id ($quantity), ";
            }
            $product_list = rtrim($product_list, ", ");
            
            $buyer_message = "Order #$order_id  $product_list successfully placed!. You will be notified when your items ship.";
            $notification_type = "order";
            $insert_notification = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
            $notif_stmt = $conn->prepare($insert_notification);
            if ($notif_stmt) {
                $notif_stmt->bind_param("iss", $user_id, $buyer_message, $notification_type);
                $notif_stmt->execute();
            }
            
            // For each product, notify the seller directly
            foreach ($latest_items as $li) {
                $product_id = (int)$li['product_id'];
                $quantity = (int)$li['quantity'];
                
                // Get seller ID for this product
                $seller_query = "SELECT p.seller_id, p.name, u.email FROM products p JOIN users u ON p.seller_id = u.id WHERE p.id = ?";
                $seller_stmt = $conn->prepare($seller_query);
                if ($seller_stmt) {
                    $seller_stmt->bind_param("i", $product_id);
                    $seller_stmt->execute();
                    $seller_result = $seller_stmt->get_result();
                    if ($seller_row = $seller_result->fetch_assoc()) {
                        $seller_id = (int)$seller_row['seller_id'];
                        $product_name = $seller_row['name'];
                        
                        // Add notification for seller
                        $seller_message = "New order #$order_id received for $quantity item(s) of '$product_name'. Please prepare for shipment.";
                        $seller_notification_sql = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, 'order', NOW(), 0)";
                        $seller_notification_stmt = $conn->prepare($seller_notification_sql);
                        if ($seller_notification_stmt) {
                            $seller_notification_stmt->bind_param("is", $seller_id, $seller_message);
                            $seller_notification_stmt->execute();
                        }
                    }
                }
            }

            // Prepare cart items for notifications (fetch seller info separately)
            $items_with_sellers = $cart_items;
            if ($order_created && !empty($cart_items)) {
                $seller_query = "SELECT u.email, u.fullname FROM users u 
                                 JOIN products p ON p.user_id = u.id 
                                 WHERE p.id = ?";
                $seller_stmt = $conn->prepare($seller_query);
                if ($seller_stmt) {
                    foreach ($items_with_sellers as &$item) {
                        $seller_stmt->bind_param("i", $item['product_id']);
                        $seller_stmt->execute();
                        $seller_result = $seller_stmt->get_result();
                        if ($seller_result->num_rows > 0) {
                            $seller = $seller_result->fetch_assoc();
                            $item['seller_email'] = $seller['email'] ?? '';
                            $item['seller_name'] = $seller['fullname'] ?? 'Unknown Seller';
                        } else {
                            $item['seller_email'] = '';
                            $item['seller_name'] = 'Unknown Seller';
                        }
                    }
                }
            }

            // Send notifications if order was created in DB
            if ($order_created && !empty($items_with_sellers)) {
                // Group items by seller
                $seller_orders = [];
                foreach ($items_with_sellers as $item) {
                    if (!empty($item['seller_email'])) {
                        $seller_email = $item['seller_email'];
                        if (!isset($seller_orders[$seller_email])) {
                            $seller_orders[$seller_email] = [
                                'name' => $item['seller_name'] ?? 'Seller',
                                'items' => [],
                                'subtotal' => 0
                            ];
                        }
                        $item_sub = $item['price'] * $item['quantity'];
                        $seller_orders[$seller_email]['items'][] = $item;
                        $seller_orders[$seller_email]['subtotal'] += $item_sub;
                    }
                }

                // Notify each seller
                foreach ($seller_orders as $email => $order_data) {
                    $subject = "New Order #" . $order_id . " - " . $order_data['name'];
                    $body = "Hello " . $order_data['name'] . ",\n\n";
                    $body .= "You have received a new order from " . $shipping_name . "\n";
                    $body .= "Order ID: " . $order_id . "\n";
                    $body .= "Shipping Address: " . $shipping_address . "\n";
                    $body .= "Subtotal for your items: $" . number_format($order_data['subtotal'], 2) . "\n\n";
                    $body .= "Items:\n";
                    foreach ($order_data['items'] as $item) {
                        $body .= "- " . $item['name'] . " x" . $item['quantity'] . " @ $" . $item['price'] . " each\n";
                    }
                    $body .= "\nPlease check your seller dashboard for more details.\n\n";
                    $body .= "Best regards,\nMeta Shark Team";
                    @send_email($email, $subject, $body);
                    
                    // Add in-app notification for seller
                    $seller_id = 0;
                    $seller_query = "SELECT id FROM users WHERE email = ?";
                    $seller_stmt = $conn->prepare($seller_query);
                    if ($seller_stmt) {
                        $seller_stmt->bind_param("s", $email);
                        $seller_stmt->execute();
                        $seller_result = $seller_stmt->get_result();
                        if ($seller_result->num_rows > 0) {
                            $seller = $seller_result->fetch_assoc();
                            $seller_id = $seller['id'];
                        }
                    }
                    
                    if ($seller_id > 0) {
                        $notification_message = "New order #" . $order_id . " requires shipment. " . count($order_data['items']) . " item(s) ordered.";
                        $notification_type = "order";
                        $insert_notification = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
                        $notif_stmt = $conn->prepare($insert_notification);
                        if ($notif_stmt) {
                            $notif_stmt->bind_param("iss", $seller_id, $notification_message, $notification_type);
                            $notif_stmt->execute();
                        }
                    }
                }
            }

            // Always notify buyer (simulate if no email)
            // Fetch buyer email
            $buyer_query = "SELECT email FROM users WHERE id = ?";
            $buyer_stmt = $conn->prepare($buyer_query);
            if ($buyer_stmt) {
                $buyer_stmt->bind_param("i", $user_id);
                $buyer_stmt->execute();
                $buyer_result = $buyer_stmt->get_result();
                $buyer = $buyer_result->fetch_assoc();
                $buyer_email = $buyer['email'] ?? '';
                if (!empty($buyer_email)) {
                    $buyer_subject = "Order Confirmation #" . ($order_id ?? 'SIM-' . time());
                    $buyer_body = "Hello " . $shipping_name . ",\n\n";
                    $buyer_body .= "Thank you for your purchase! Your order has been placed successfully.\n";
                    $buyer_body .= "Order ID: " . ($order_id ?? 'SIM-' . time()) . "\n";
                    $buyer_body .= "Payment Method: " . ucfirst($payment_method) . "\n";
                    $buyer_body .= "Total Amount: $" . number_format($total, 2) . "\n";
                    $buyer_body .= "Shipping Address: " . $shipping_address . "\n\n";
                    $buyer_body .= "Items Ordered:\n";
                    foreach ($cart_items as $item) {  // Note: cart_items cleared below, but used here
                        $buyer_body .= "- " . $item['name'] . " x" . $item['quantity'] . "\n";
                    }
                    $buyer_body .= "\nWe'll notify you when your order ships.\n\n";
                    $buyer_body .= "Best regards,\nMeta Shark Team";
                    @send_email($buyer_email, $buyer_subject, $buyer_body);
                }
            }

            // Success message
            $order_message = $order_created ? "Order placed successfully! A confirmation email has been sent to you and your sellers. Stock will be deducted upon delivery." : "Order placed successfully! (Database recording skipped - contact admin if issues). Thank you for your purchase.";
            $order_success = true;
            unset($_SESSION['applied_voucher']);
            $cart_items = [];  // Clear after using for emails
            $total_items = 0;
            $subtotal = 0;
            $tax = 0;
            $discount = 0;
            $total = 0;

        } catch (Exception $e) {
            $conn->rollback();
            $order_message = "Error placing order: " . $e->getMessage();
        }
    }
}

// Fetch user profile
$profile_query = "SELECT fullname, email FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$default_name = $profile['fullname'] ?? '';
$default_address = ''; // Default to empty since address column doesn't exist
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
            // Hamburger menu
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
                menu.querySelectorAll('a').forEach(item => {
                    item.addEventListener('click', () => menu.classList.remove('show'));
                });
            }

            // Notification auto-dismiss
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            });

            // QR Code modal enhancements
            const paymentMethodSelect = document.getElementById('payment_method');
            const qrModal = document.getElementById('qrModal');
            const qrContent = document.getElementById('qrContent');
            const closeModalBtn = document.querySelector('.close-btn');
            const closeSpan = document.querySelector('.modal-content .close');
            const gcashNumber = '<?php echo addslashes($default_gcash_number); ?>';
            const total = <?php echo json_encode($total); ?>;

            function updateQRModal() {
                qrModal.classList.remove('show');
                qrContent.innerHTML = '';
                if (paymentMethodSelect.value === 'gcash' && gcashNumber && total > 0) {
                    const qrDiv = document.createElement('div');
                    qrDiv.className = 'qr-code-item';
                    qrDiv.id = 'qrCode';
                    const p = document.createElement('p');
                    p.textContent = `Pay $${Number(total).toFixed(2)} to ${gcashNumber}`;
                    p.style.marginBottom = '10px';
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
                } else if (paymentMethodSelect.value === 'gcash') {
                    alert('No GCash number configured or cart total is zero.');
                }
            }

            paymentMethodSelect.addEventListener('change', updateQRModal);
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', () => {
                    qrModal.classList.remove('show');
                    qrContent.innerHTML = '';
                });
            }
            if (closeSpan) {
                closeSpan.addEventListener('click', () => {
                    qrModal.classList.remove('show');
                    qrContent.innerHTML = '';
                });
            }
            // Close modal on outside click
            qrModal.addEventListener('click', (e) => {
                if (e.target === qrModal) {
                    qrModal.classList.remove('show');
                    qrContent.innerHTML = '';
                }
            });

            // Enhanced client-side form validation with real-time feedback
            const form = document.querySelector('form');
            const shippingName = document.getElementById('shipping_name');
            const shippingAddress = document.getElementById('shipping_address');

            function validateField(field, minLen, maxLen) {
                const value = field.value;
                if (value.length < minLen || value.length > maxLen) {
                    field.classList.add('error-input');
                    return false;
                } else {
                    field.classList.remove('error-input');
                    return true;
                }
            }

            shippingName.addEventListener('blur', () => validateField(shippingName, 2, 100));
            shippingAddress.addEventListener('blur', () => validateField(shippingAddress, 10, 500));

            form.addEventListener('submit', function(e) {
                let hasError = false;
                if (!validateField(shippingName, 2, 100)) hasError = true;
                if (!validateField(shippingAddress, 10, 500)) hasError = true;

                if (hasError) {
                    e.preventDefault();
                    alert('Please correct the errors in the form. Name must be 2-100 characters, address 10-500 characters.');
                }
            });
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
        <div class="notification <?php echo strpos($voucher_message, 'successfully') !== false || strpos($voucher_message, 'applied') !== false ? 'success' : 'error'; ?> show" id="voucherNotification">
            <?php echo strpos($voucher_message, 'successfully') !== false || strpos($voucher_message, 'applied') !== false ? '✅' : '❌'; ?> <?php echo htmlspecialchars($voucher_message); ?>
        </div>
    <?php endif; ?>

    <div class="modal" id="qrModal">
        <div class="modal-content">
            <span class="close">&times;</span>
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
                    <!-- Voucher form separated to avoid triggering checkout -->
                    <form method="POST" style="margin-bottom: 12px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group voucher-group">
                            <div style="flex: 1;">
                                <label for="voucher_code">Select Voucher</label>
                                <select id="voucher_code" name="voucher_code">
                                    <option value="">-- Choose a voucher --</option>
                                    <?php foreach ($available_vouchers as $vcode): ?>
                                        <option value="<?php echo htmlspecialchars($vcode); ?>" <?php echo ($vcode === $voucher_code) ? 'selected' : ''; ?>><?php echo htmlspecialchars($vcode); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="apply_voucher" class="apply-voucher-btn">Apply</button>
                        </div>
                    </form>

                    <!-- Checkout form -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="checkout" value="1">
                        <div class="form-group">
                            <label for="shipping_name">Full Name</label>
                            <input type="text" id="shipping_name" name="shipping_name" value="<?php echo htmlspecialchars($default_name); ?>" required maxlength="100" minlength="2">
                        </div>
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address</label>
                            <textarea id="shipping_address" name="shipping_address" required maxlength="500" minlength="10"><?php echo htmlspecialchars($default_address); ?></textarea>
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
                    <?php if (!empty($cart_items)): ?>
                        <?php foreach ($cart_items as $item): ?>
                            <div class="summary-row">
                                <span class="summary-label"><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                                <span class="summary-value">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                    <div class="summary-row total">
                        <span class="summary-label">Total:</span>
                        <span class="summary-value">$<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>