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
    include("db.php");
    $uid = $_SESSION['user_id'];
    $chk = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
    if ($chk) { 
        $chk->bind_param("i", $uid); 
        $chk->execute(); 
        $r = $chk->get_result(); 
        $u = $r->fetch_assoc(); 
    }
    if (empty($u) || (int)($u['is_verified'] ?? 0) !== 1) {
        header("Location: verify_account.php");
        exit();
    }
}

include("db.php");
include_once("email.php");
$user_id = $_SESSION['user_id'];

// Capture selected product IDs from GET or POST to support "checkout selected" flow
$selectedProductIds = [];
if (isset($_GET['selected_items']) && is_array($_GET['selected_items'])) {
    $selectedProductIds = array_values(array_unique(array_map('intval', $_GET['selected_items'])));
} elseif (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
    $selectedProductIds = array_values(array_unique(array_map('intval', $_POST['selected_items'])));
}

// Fetch available vouchers for dropdown
$available_vouchers = [];
$voucher_list_query = "SELECT code, discount_type, discount_value, min_purchase 
                      FROM vouchers 
                      WHERE expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses) 
                      ORDER BY code";
$voucher_list_stmt = $conn->prepare($voucher_list_query);
if ($voucher_list_stmt) {
    $voucher_list_stmt->execute();
    $voucher_list_result = $voucher_list_stmt->get_result();
    while ($v = $voucher_list_result->fetch_assoc()) {
        $available_vouchers[] = $v;
    }
    $voucher_list_stmt->close();
}

// Fetch user's contact number dynamically
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
    $gcq->close();
}

// Support single-item checkout via ?product_id=... (fallback when no selected_items[] passed)
$onlyProductId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Fetch cart items honoring selected IDs if provided
if (!empty($selectedProductIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedProductIds), '?'));
    $sql = "SELECT c.*, p.name, p.price, p.image, p.description, p.seller_id 
            FROM cart c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? AND c.product_id IN ($placeholders)
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    // types: one user_id + N product ids
    $types = 'i' . str_repeat('i', count($selectedProductIds));
    $params = array_merge([$user_id], $selectedProductIds);
    $stmt->bind_param($types, ...$params);
} elseif ($onlyProductId > 0) {
    $sql = "SELECT c.*, p.name, p.price, p.image, p.description, p.seller_id 
            FROM cart c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? AND c.product_id = ? 
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $onlyProductId);
} else {
    $sql = "SELECT c.*, p.name, p.price, p.image, p.description, p.seller_id 
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
$stmt->close();

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $item_total = $item['price'] * $item['quantity'];
    $subtotal += $item_total;
    $total_items += $item['quantity'];
}
$subtotal = round($subtotal, 2);
$tax = round($subtotal * 0.08, 2);
$discount = 0;
$voucher_code = '';
$voucher_message = '';


// Enhanced voucher validation with detailed error handling
$selected_voucher = $_POST['voucher_code'] ?? $_SESSION['applied_voucher'] ?? '';

if (!empty($selected_voucher)) {
    $voucher_query = "SELECT discount_type, discount_value, min_purchase, expiry_date, max_uses, current_uses 
                     FROM vouchers 
                     WHERE UPPER(code) = ?";
    $voucher_stmt = $conn->prepare($voucher_query);
    if ($voucher_stmt) {
        $voucher_stmt->bind_param("s", $selected_voucher);
        $voucher_stmt->execute();
        $voucher_result = $voucher_stmt->get_result();
        
        if ($voucher_result->num_rows > 0) {
            $voucher = $voucher_result->fetch_assoc();
            
            // Check voucher validity with detailed error messages
            if (strtotime($voucher['expiry_date']) <= time()) {
                $voucher_message = "❌ Voucher '$selected_voucher' has expired.";
                $selected_voucher = '';
                unset($_SESSION['applied_voucher']);
                unset($_SESSION['voucher_details']);
            } elseif ($voucher['max_uses'] !== null && $voucher['current_uses'] >= $voucher['max_uses']) {
                $voucher_message = "❌ Voucher '$selected_voucher' has reached its usage limit.";
                $selected_voucher = '';
                unset($_SESSION['applied_voucher']);
                unset($_SESSION['voucher_details']);
            } elseif ($subtotal < $voucher['min_purchase']) {
                $min_purchase_formatted = number_format($voucher['min_purchase'], 2);
                $current_subtotal = number_format($subtotal, 2);
                $voucher_message = "❌ Voucher requires minimum purchase of $$min_purchase_formatted. Your subtotal is $$current_subtotal.";
                unset($_SESSION['applied_voucher']);
                unset($_SESSION['voucher_details']);
            } else {
                // Voucher is valid - apply discount
                $discount = $voucher['discount_type'] === 'percentage' 
                    ? $subtotal * ($voucher['discount_value'] / 100) 
                    : $voucher['discount_value'];
                $discount = round($discount, 2);
                
                // Ensure discount doesn't exceed subtotal
                if ($discount > $subtotal) {
                    $discount = $subtotal;
                }
                
                $voucher_code = $selected_voucher;
                $_SESSION['applied_voucher'] = $selected_voucher;
                $_SESSION['voucher_details'] = [
                    'discount_type' => $voucher['discount_type'],
                    'discount_value' => $voucher['discount_value'],
                    'min_purchase' => $voucher['min_purchase']
                ];
                
                $discount_formatted = number_format($discount, 2);
                $voucher_message = "✅ Voucher '$voucher_code' applied successfully! Discount: $$discount_formatted";
            }
        } else {
            $voucher_message = "❌ Invalid voucher code '$selected_voucher'. Please check and try again.";
            $selected_voucher = '';
            unset($_SESSION['applied_voucher']);
            unset($_SESSION['voucher_details']);
        }
        $voucher_stmt->close();
    } else {
        $voucher_message = "❌ System error: Could not validate voucher. Please try again.";
        error_log("Voucher validation prepare failed: " . $conn->error);
    }
} elseif (isset($_POST['voucher_code']) && empty($_POST['voucher_code'])) {
    // User explicitly removed voucher
    $voucher_message = "ℹ️ Voucher removed.";
    unset($_SESSION['applied_voucher']);
    unset($_SESSION['voucher_details']);
}

$total = round($subtotal + $tax - $discount, 2);

// Handle checkout submission
$order_success = false;
$order_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['checkout']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $shipping_name = filter_input(INPUT_POST, 'shipping_name', FILTER_SANITIZE_STRING) ?? '';
    $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_STRING) ?? '';
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING) ?? '';

    // Basic validation
    if (empty($shipping_name) || empty($shipping_address) || empty($payment_method)) {
        $order_message = "Please fill in all required fields.";
    } elseif (strlen($shipping_name) > 100 || strlen($shipping_address) > 500) {
        $order_message = "Input fields exceed maximum length.";
    } elseif (empty($cart_items)) {
        $order_message = "Your cart is empty.";
    } else {
        $conn->begin_transaction();
        try {
            // Recalculate totals
            $latest_subtotal = 0;
            foreach ($cart_items as $item) {
                $latest_subtotal += $item['price'] * $item['quantity'];
            }
            $latest_subtotal = round($latest_subtotal, 2);
            $latest_tax = round($latest_subtotal * 0.08, 2);
            $latest_discount = 0;
            $voucher_code_used = $_SESSION['applied_voucher'] ?? null;
            
            // Revalidate voucher
            if ($voucher_code_used) {
                $voucher_query = "SELECT discount_type, discount_value, min_purchase 
                                 FROM vouchers 
                                 WHERE UPPER(code) = ? AND expiry_date > NOW() AND (max_uses IS NULL OR current_uses < max_uses)";
                $voucher_stmt = $conn->prepare($voucher_query);
                if ($voucher_stmt) {
                    $voucher_stmt->bind_param("s", $voucher_code_used);
                    $voucher_stmt->execute();
                    $voucher_result = $voucher_stmt->get_result();
                    
                    if ($voucher_result->num_rows > 0) {
                        $voucher = $voucher_result->fetch_assoc();
                        if ($latest_subtotal >= $voucher['min_purchase']) {
                            $latest_discount = $voucher['discount_type'] === 'percentage' 
                                ? $latest_subtotal * ($voucher['discount_value'] / 100) 
                                : $voucher['discount_value'];
                            $latest_discount = round($latest_discount, 2);
                        } else {
                            throw new Exception("Voucher minimum purchase requirement not met.");
                        }
                    } else {
                        throw new Exception("Voucher is no longer valid.");
                    }
                    $voucher_stmt->close();
                } else {
                    throw new Exception("Voucher system error.");
                }
            }
            
            $latest_total = round($latest_subtotal + $latest_tax - $latest_discount, 2);

            // Precompute global totals for discount proration
            $global_subtotal = 0.0;
            foreach ($cart_items as $ci) { $global_subtotal += $ci['price'] * $ci['quantity']; }
            $prorate_base = max($global_subtotal, 0.00001);

            // Prepare statements reused in loop
            $order_query = "INSERT INTO orders (buyer_id, subtotal, tax, discount, total_price, status, shipping_name, shipping_address, payment_method, voucher_code, created_at, paid_at) 
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW(), NOW())";
            $order_stmt = $conn->prepare($order_query);
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price, status) VALUES (?, ?, ?, ?, 'pending')";
            $item_stmt = $conn->prepare($item_query);

            if ($order_stmt && $item_stmt) {
            $created_order_ids = [];
            foreach ($cart_items as $it) {
                $item_subtotal = $it['price'] * $it['quantity'];
                $item_tax = round($item_subtotal * 0.08, 2);
                // Prorate discount by item share of subtotal
                $item_discount = 0.0;
                if ($latest_discount > 0) {
                    $item_discount = round(($item_subtotal / $prorate_base) * $latest_discount, 2);
                    if ($item_discount > $item_subtotal) { $item_discount = $item_subtotal; }
                }
                $item_total = round($item_subtotal + $item_tax - $item_discount, 2);

                // Create order for this single item
                $order_stmt->bind_param("idddsssss", $user_id, $item_subtotal, $item_tax, $item_discount, $item_total, $shipping_name, $shipping_address, $payment_method, $voucher_code_used);
                $order_stmt->execute();
                $order_id = $conn->insert_id;
                $created_order_ids[] = $order_id;

                // Insert the one item
                $item_stmt->bind_param("iiid", $order_id, $it['product_id'], $it['quantity'], $it['price']);
                $item_stmt->execute();

                // Notify seller for this item
                $sellerId = (int)($it['seller_id'] ?? 0);
                if ($sellerId && $sellerId != $user_id) {
                    $notification_type = "order";
                    $notification_sql = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
                    $seller_message = "New order #$order_id received for product: " . $it['name'] . " (Quantity: " . $it['quantity'] . ")";
                    $seller_notif_stmt = $conn->prepare($notification_sql);
                    if ($seller_notif_stmt) {
                        $seller_notif_stmt->bind_param("iss", $sellerId, $seller_message, $notification_type);
                        $seller_notif_stmt->execute();
                        $seller_notif_stmt->close();
                    }
                }
            }
            $item_stmt && $item_stmt->close();

                // Update voucher usage ONCE (regardless of number of seller orders)
                if ($voucher_code_used) {
                    $update_voucher = $conn->prepare("UPDATE vouchers SET current_uses = current_uses + 1 WHERE UPPER(code) = ?");
                    $update_voucher->bind_param("s", $voucher_code_used);
                    $update_voucher->execute();
                    $update_voucher->close();
                }

                // Clear only the items that were part of this checkout
                if (!empty($selectedProductIds)) {
                    $ph = implode(',', array_fill(0, count($selectedProductIds), '?'));
                    $clear_sql = "DELETE FROM cart WHERE user_id = ? AND product_id IN ($ph)";
                    $clear_cart = $conn->prepare($clear_sql);
                    $types = 'i' . str_repeat('i', count($selectedProductIds));
                    $params = array_merge([$user_id], $selectedProductIds);
                    $clear_cart->bind_param($types, ...$params);
                    $clear_cart->execute();
                    $clear_cart->close();
                } elseif ($onlyProductId > 0) {
                    $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    $clear_cart->bind_param("ii", $user_id, $onlyProductId);
                    $clear_cart->execute();
                    $clear_cart->close();
                } else {
                    $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                    $clear_cart->bind_param("i", $user_id);
                    $clear_cart->execute();
                    $clear_cart->close();
                }

                $conn->commit();
                
                // Buyer notifications per created order
                $notification_type = "order";
                $notification_sql = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
                foreach ($created_order_ids as $oid) {
                    $buyer_message = "Order #$oid placed successfully!";
                    $notif_stmt = $conn->prepare($notification_sql);
                    if ($notif_stmt) {
                        $notif_stmt->bind_param("iss", $user_id, $buyer_message, $notification_type);
                        $notif_stmt->execute();
                        $notif_stmt->close();
                    }
                }

                $order_message = "Order placed successfully!";
                $order_success = true;
                unset($_SESSION['applied_voucher']);
                unset($_SESSION['voucher_details']);
                
                header("Location: order_status.php?success=1");
                exit;
                
            } else {
                throw new Exception("Failed to prepare order statement");
            }

        } catch (Exception $e) {
            $conn->rollback();
            $order_message = "Error placing order: " . $e->getMessage();
            error_log("Checkout Error: " . $e->getMessage());
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
$default_address = '';
$profile_stmt->close();
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
            qrModal.addEventListener('click', (e) => {
                if (e.target === qrModal) {
                    qrModal.classList.remove('show');
                    qrContent.innerHTML = '';
                }
            });

            // Enhanced client-side form validation
            const form = document.querySelector('form[name="checkout"]');
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

            if (form) {
                form.addEventListener('submit', function(e) {
                    let hasError = false;
                    if (!validateField(shippingName, 2, 100)) hasError = true;
                    if (!validateField(shippingAddress, 10, 500)) hasError = true;

                    if (hasError) {
                        e.preventDefault();
                        alert('Please correct the errors in the form. Name must be 2-100 characters, address 10-500 characters.');
                    }
                });
            }

            // Enhanced voucher handling with better error display
            const voucherSelect = document.getElementById('voucher_code');
            if (voucherSelect) {
                voucherSelect.addEventListener('change', function() {
                    const selectedVoucher = this.value;
                    
                    if (selectedVoucher) {
                        // Show loading state
                        this.disabled = true;
                        
                        // Create loading indicator
                        const loadingText = document.createElement('span');
                        loadingText.textContent = ' Applying voucher...';
                        loadingText.className = 'voucher-loading';
                        loadingText.style.marginLeft = '10px';
                        this.parentNode.appendChild(loadingText);
                        
                        // Remove any existing voucher messages
                        const existingMessage = document.querySelector('.voucher-message');
                        if (existingMessage) {
                            existingMessage.remove();
                        }
                        
                        // Apply voucher via AJAX
                        applyVoucherWithoutRefresh(selectedVoucher)
                            .finally(() => {
                                // Re-enable select after request completes
                                setTimeout(() => {
                                    this.disabled = false;
                                    if (loadingText.parentNode) {
                                        loadingText.parentNode.removeChild(loadingText);
                                    }
                                }, 1000);
                            });
                    } else {
                        // User selected "Choose a voucher" - remove voucher
                        applyVoucherWithoutRefresh('');
                    }
                });
            }

            // Enhanced AJAX voucher application
            function applyVoucherWithoutRefresh(voucherCode) {
                const formData = new FormData();
                formData.append('voucher_code', voucherCode);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                // Persist selected items so server scopes totals to selection
                document.querySelectorAll('input[name="selected_items[]"]').forEach(input => {
                    formData.append('selected_items[]', input.value);
                });
                
                return fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    // Parse the response and update the order summary
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update voucher message
                    const newVoucherMessage = doc.querySelector('.voucher-message');
                    if (newVoucherMessage) {
                        const currentMessage = document.querySelector('.voucher-message');
                        if (currentMessage) {
                            currentMessage.replaceWith(newVoucherMessage.cloneNode(true));
                        } else {
                            document.querySelector('#voucher_code').parentNode.appendChild(newVoucherMessage.cloneNode(true));
                        }
                        
                        // Auto-hide success messages after 5 seconds
                        if (newVoucherMessage.classList.contains('success')) {
                            setTimeout(() => {
                                const message = document.querySelector('.voucher-message.success');
                                if (message) {
                                    message.style.opacity = '0';
                                    setTimeout(() => message.remove(), 300);
                                }
                            }, 5000);
                        }
                    }
                    
                    // Update order summary values
                    const newSummary = doc.querySelector('.order-summary');
                    if (newSummary) {
                        document.querySelector('.order-summary').innerHTML = newSummary.innerHTML;
                    }
                    
                    // Update total for QR code
                    const newTotalElement = doc.querySelector('.summary-row.total .summary-value');
                    if (newTotalElement) {
                        const newTotal = parseFloat(newTotalElement.textContent.replace('$', ''));
                        // Update the total variable for QR code generation
                        if (paymentMethodSelect.value === 'gcash') {
                            // Re-generate QR code with new total
                            updateQRModal();
                        }
                    }
                    
                    // Update cart item count in menu if needed
                    const newCartCount = doc.querySelector('.menu a[href="carts_users.php"]');
                    if (newCartCount) {
                        const currentCartLink = document.querySelector('.menu a[href="carts_users.php"]');
                        if (currentCartLink) {
                            currentCartLink.innerHTML = newCartCount.innerHTML;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error applying voucher:', error);
                    
                    // Show error message to user
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'voucher-message error';
                    errorMessage.innerHTML = '❌ Network error applying voucher. Please try again.';
                    
                    const existingMessage = document.querySelector('.voucher-message');
                    if (existingMessage) {
                        existingMessage.replaceWith(errorMessage);
                    } else {
                        document.querySelector('#voucher_code').parentNode.appendChild(errorMessage);
                    }
                    
                    // Re-enable the select
                    const voucherSelect = document.getElementById('voucher_code');
                    if (voucherSelect) {
                        voucherSelect.disabled = false;
                    }
                    
                    // Remove loading text
                    const loadingText = document.querySelector('.voucher-loading');
                    if (loadingText && loadingText.parentNode) {
                        loadingText.parentNode.removeChild(loadingText);
                    }
                    
                    // Auto-remove error after 5 seconds
                    setTimeout(() => {
                        if (errorMessage.parentNode) {
                            errorMessage.style.opacity = '0';
                            setTimeout(() => errorMessage.remove(), 300);
                        }
                    }, 5000);
                });
            }
        });
    </script>
    <style>
        .error-input { border: 1px solid red; }
        .notification { 
            transition: opacity 0.3s ease, transform 0.3s ease; 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1000; 
            padding: 15px; 
            border-radius: 5px; 
            max-width: 300px;
        }
        .notification.show { opacity: 1; transform: translateY(0); }
        .notification.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .notification.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .view-orders-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #44D62C;
            text-decoration: none;
            font-weight: bold;
        }
        .view-orders-link:hover {
            text-decoration: underline;
        }
        .voucher-details {
            font-size: 0.9em;
            color: #888;
            margin-top: 5px;
            padding: 5px;
            background: #2a2a2a;
            border-radius: 3px;
        }
        .voucher-message {
            font-size: 0.9em;
            margin-top: 8px;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .voucher-message.success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
            border: 1px solid #c3e6cb;
        }
        .voucher-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        .voucher-message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
            border: 1px solid #bee5eb;
        }
        .voucher-loading {
            color: #44D62C;
            font-size: 0.9em;
            margin-left: 10px;
            font-style: italic;
        }

        /* Voucher select styling */
        .form-group select:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Error states for form fields */
        .error-input {
            border: 2px solid #dc3545 !important;
            background-color: #fff5f5;
        }

        /* Voucher details styling */
        .voucher-details {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #44D62C;
        }
    </style>
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
            $profile_stmt->close();
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
            <li><a href="order_status.php">My Orders</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <?php if (!empty($order_message)): ?>
        <div class="notification <?php echo $order_success ? 'success' : 'error'; ?> show" id="orderNotification">
            <?php echo $order_success ? '✅' : '❌'; ?> <?php echo htmlspecialchars($order_message); ?>
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
            <a href="order_status.php" class="view-orders-link">View My Orders →</a>
        </div>
        <?php if (empty($cart_items) && !$order_success): ?>
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="shop-btn">Start Shopping</a>
                <a href="order_status.php" class="view-orders-link">View My Orders</a>
            </div>
        <?php else: ?>
            <div class="checkout-content">
                <div class="checkout-form">
                    <h3 class="summary-title">Shipping & Payment</h3>
                    
                    <form method="POST" name="checkout">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <?php if (!empty($selectedProductIds)): ?>
                            <?php foreach ($selectedProductIds as $pid): ?>
                                <input type="hidden" name="selected_items[]" value="<?php echo (int)$pid; ?>">
                            <?php endforeach; ?>
                        <?php elseif ($onlyProductId > 0): ?>
                            <input type="hidden" name="selected_items[]" value="<?php echo (int)$onlyProductId; ?>">
                        <?php endif; ?>
                        
                        <!-- Voucher form - Auto-applies when selected -->
                        <div class="form-group">
                            <label for="voucher_code">Apply Voucher</label>
                            <select id="voucher_code" name="voucher_code">
                                <option value="">-- Remove voucher --</option>
                                <?php foreach ($available_vouchers as $voucher): 
                                    $discount_display = $voucher['discount_type'] === 'percentage' 
                                        ? $voucher['discount_value'] . '% off' 
                                        : '$' . number_format($voucher['discount_value'], 2) . ' off';
                                    $min_purchase_display = number_format($voucher['min_purchase'], 2);
                                ?>
                                    <option value="<?php echo htmlspecialchars($voucher['code']); ?>" 
                                        <?php echo ($voucher['code'] === $voucher_code) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($voucher['code']) . " - $discount_display (min: $$min_purchase_display)"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($voucher_message)): ?>
                                <div class="voucher-message <?php 
                                    echo strpos($voucher_message, '✅') !== false ? 'success' : 
                                         (strpos($voucher_message, '❌') !== false ? 'error' : 'info'); 
                                ?>">
                                    <?php echo htmlspecialchars($voucher_message); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="shipping_name">Full Name *</label>
                            <input type="text" id="shipping_name" name="shipping_name" value="<?php echo htmlspecialchars($default_name); ?>" required maxlength="100" minlength="2" placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address *</label>
                            <textarea id="shipping_address" name="shipping_address" required maxlength="500" minlength="10" placeholder="Enter your complete shipping address"><?php echo htmlspecialchars($default_address); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="" disabled selected>Select payment method</option>
                                <option value="gcash">GCash</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <button type="submit" name="checkout" value="1" class="confirm-btn" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>Place Order & View Order Status</button>
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
                    <?php if (isset($_SESSION['voucher_details'])): ?>
                        <div class="voucher-details">
                            <?php echo htmlspecialchars($voucher_code) . ': ' . ($_SESSION['voucher_details']['discount_type'] === 'percentage' ? $_SESSION['voucher_details']['discount_value'] . '% off' : '$' . number_format($_SESSION['voucher_details']['discount_value'], 2) . ' off') . ', min $' . number_format($_SESSION['voucher_details']['min_purchase'], 2); ?>
                        </div>
                    <?php endif; ?>
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