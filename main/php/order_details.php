<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { 
    header("Location: login_users.php"); 
    exit(); 
}
$userId = (int)$_SESSION['user_id'];

// Validate order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header("Location: order_status.php");
    exit();
}
$orderId = (int)$_GET['order_id'];

// Theme preference (match shop.php)
if (isset($_GET['theme'])) {
    $new_theme = in_array($_GET['theme'], ['light', 'dark', 'device']) ? $_GET['theme'] : 'device';
    $_SESSION['theme'] = $new_theme;
} else {
    $theme = $_SESSION['theme'] ?? 'device';
}
$effective_theme = $theme;
if ($theme === 'device') {
    $effective_theme = 'dark';
}

// Fetch order details
$order_query = "SELECT o.*, 
                       COUNT(oi.id) as item_count,
                       MIN(oi.status) as overall_status,
                       GROUP_CONCAT(DISTINCT oi.status) as all_statuses
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                WHERE o.id = ? AND o.buyer_id = ?
                GROUP BY o.id";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("ii", $orderId, $userId);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    header("Location: order_status.php");
    exit();
}

// Fetch order items with details
$items_query = "SELECT oi.*, p.name, p.image, p.seller_id, u.seller_name
                FROM order_items oi 
                LEFT JOIN products p ON oi.product_id = p.id 
                LEFT JOIN users u ON p.seller_id = u.id
                WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $orderId);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Function to determine overall order status (reused from order_status.php)
function getOverallOrderStatus($order_items) {
    $statuses = array_column($order_items, 'status');
    
    if (in_array('cancelled', $statuses)) {
        return 'cancelled';
    }
    
    if (count(array_unique($statuses)) === 1 && $statuses[0] === 'delivered') {
        return 'delivered';
    }
    
    if (in_array('shipped', $statuses)) {
        return 'shipped';
    }
    
    if (in_array('confirmed', $statuses)) {
        return 'confirmed';
    }
    
    return 'pending';
}

$overall_status = getOverallOrderStatus($order_items);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/order_status.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* Navbar styles (from shop.php) */
        .navbar { position: sticky; top: 0; z-index: 1000; background: var(--background, #000); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, #444); }
        .nav-left { display: flex; align-items: center; gap: 10px; }
        .logo { height: 40px; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .profile-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #00640033; }
        .nonuser-text { font-size: 16px; color: #fff; text-decoration: none; }
        .hamburger { background: none; border: none; font-size: 24px; cursor: pointer; color: #fff; }
        .menu { display: none; position: absolute; top: 60px; right: 20px; background: var(--background, #000); border: 1px solid var(--border-color, #444); border-radius: 8px; padding: 10px; list-style: none; margin: 0; z-index: 1000; }
        .menu.show { display: block; }
        .menu li { margin: 10px 0; }
        .menu li a { color: #fff; text-decoration: none; font-size: 16px; }
        .menu li a:hover { color: #27ed15; }
        .theme-dropdown { position: relative; display: inline-block; }
        .theme-btn { appearance: none; background: #000; color: #fff; border: 2px solid #00ff88; padding: 8px 12px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; min-width: 120px; display: inline-flex; align-items: center; gap: 6px; }
        .theme-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: rgba(0,0,0,0.95); border: 2px solid rgba(0,255,136,0.3); border-radius: 12px; padding: 8px; min-width: 120px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); display: none; z-index: 1000; }
        .theme-dropdown.active .theme-menu { display: block; }
        .theme-option { width: 100%; padding: 10px 12px; border: none; background: transparent; border-radius: 8px; cursor: pointer; text-align: left; font-weight: 600; color: #cecccc; }
        .theme-option:hover { background: rgba(0,255,136,0.08); color: #00aa55; }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .header p {
            color: #888;
        }

        .order-card {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #44D62C;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .order-info h3 {
            margin: 0;
            font-size: 1.5em;
        }

        .order-date {
            color: #888;
            font-size: 0.9em;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .order-details { margin-bottom: 16px; display: grid; gap: 6px; }
        .detail-row { display: grid; grid-template-columns: 140px 1fr; align-items: baseline; margin: 0; }
        .detail-label { color: #888; font-weight: bold; line-height: 1.2; margin-right: 8px; }
        .detail-value { color: #fff; line-height: 1.2; justify-self: start; }

        .order-items {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
        }

        .order-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; margin-bottom: 6px; background: #2a2a2a; border-radius: 6px; }

        .item-info {
            flex: 1;
        }

        .item-name { font-weight: bold; display: block; margin-bottom: 2px; }

        .item-details { font-size: 0.9em; color: #ccc; line-height: 1.3; }

        .item-specs {
            display: block;
            font-size: 0.8em;
            color: #888;
            margin-top: 2px;
        }

        .seller-info {
            font-size: 0.8em;
            color: #888;
            margin-top: 2px;
        }

        .tracking-info { background: #2a2a2a; padding: 8px; border-radius: 6px; margin-top: 8px; border-left: 3px solid #44D62C; }
        .product-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-right: 12px; flex-shrink: 0; background: #111; border: 1px solid #222; }

        .tracking-number {
            font-family: monospace;
            background: #1a1a1a;
            padding: 4px 8px;
            border-radius: 3px;
            margin-left: 5px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <img src="uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <div class="theme-dropdown" id="themeDropdown">
              <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme">
                <i class="bi theme-icon" id="themeIcon"></i>
                <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($effective_theme === 'light' ? 'Dark' : 'Light'); ?></span>
              </button>
              <div class="theme-menu" id="themeMenu" aria-hidden="true">
                <button class="theme-option" data-theme="light">Light</button>
                <button class="theme-option" data-theme="dark">Dark</button>
                <button class="theme-option" data-theme="device">Device</button>
              </div>
            </div>
            <a href="notifications.php" title="Notifications" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-bell" style="font-size:18px;"></i>
              <span></span>
            </a>
            <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-cart" style="font-size:18px;"></i>
              <span></span>
            </a>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
              <?php
                $user_role = $_SESSION['role'] ?? 'buyer';
                $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
                $profile_query = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
                $profile_query->bind_param("i", $userId);
                $profile_query->execute();
                $profile_result = $profile_query->get_result();
                $current_profile = $profile_result ? $profile_result->fetch_assoc() : null;
                $current_profile_image = $current_profile['profile_image'] ?? null;
              ?>
              <a href="<?php echo $profile_page; ?>">
                <?php if(!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                  <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                  <img src="Uploads/Logo.png" alt="Profile" class="profile-icon">
                <?php endif; ?>
              </a>
            <?php else: ?>
              <a href="login_users.php"><div class="nonuser-text">Login</div></a>
              <a href="signup_users.php"><div class="nonuser-text">Signup</div></a>
              <a href="login_users.php"><div class="profile-icon">👤</div></a>
            <?php endif; ?>
            <button class="hamburger">☰</button>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <li><a href="carts_users.php">Cart</a></li>
            <li><a href="order_status.php">My Purchases</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
              <?php $user_role = $_SESSION['role'] ?? 'buyer'; ?>
              <?php if($user_role === 'seller' || $user_role === 'admin'): ?>
                <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
              <?php else: ?>
                <li><a href="become_seller.php">Become Seller</a></li>
              <?php endif; ?>
              <li><a href="<?php echo $profile_page; ?>">Profile</a></li>
              <li><a href="logout.php">Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="container">
        <div class="header">
            <h1>Order Details</h1>
            <p>Order #<?php echo $order['id']; ?> - Detailed Information</p>
        </div>

        <div class="order-card">
            <div class="order-header">
                <div class="order-info">
                    <h3>Order #<?php echo $order['id']; ?></h3>
                    <p class="order-date">Placed on <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="order-status">
                    <span class="status-badge status-<?php echo $overall_status; ?>">
                        <?php echo ucfirst($overall_status); ?>
                    </span>
                </div>
            </div>

            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Items:</span>
                    <span class="detail-value"><?php echo $order['item_count']; ?> item(s)</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">$<?php echo number_format($order['total_price'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value"><?php echo ucfirst($order['payment_method']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Shipping Address:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_name']); ?>, <?php echo htmlspecialchars($order['shipping_address']); ?></span>
                </div>
                <?php if (!empty($order['voucher_code'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Voucher Used:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order['voucher_code']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="order-items">
                <h4>Order Items:</h4>
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <?php
                            $img = trim($item['image'] ?? '');
                            $imgSrc = $img !== '' ? str_replace('\\', '/', $img) : 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90"><rect width="100%" height="100%" fill="#222"/><text x="50%" y="50%" fill="#888" font-size="12" dominant-baseline="middle" text-anchor="middle">No image</text></svg>');
                        ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-thumb" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<?php echo rawurlencode('<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"120\" height=\"90\"><rect width=\"100%\" height=\"100%\" fill=\"#222\"/><text x=\"50%\" y=\"50%\" fill=\"#888\" font-size=\"12\" dominant-baseline=\"middle\" text-anchor=\"middle\">No image</text></svg>'); ?>';">
                        <div class="item-info">
                            <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <div class="item-details">
                                <span class="item-quantity">Qty: <?php echo $item['quantity']; ?></span> • 
                                <span class="item-price">$<?php echo number_format($item['price'], 2); ?> each</span>
                            </div>
                            <?php if (!empty($item['seller_name'])): ?>
                                <div class="seller-info">
                                    Sold by: <?php echo htmlspecialchars($item['seller_name']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['spec_combination'])): ?>
                                <span class="item-specs">
                                    <?php 
                                    $specs = json_decode($item['spec_combination'], true);
                                    if ($specs && is_array($specs)) {
                                        echo "Specs: " . implode(", ", array_map(fn($k, $v) => "$k: $v", array_keys($specs), $specs));
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($item['tracking_number'])): ?>
                                <div class="tracking-info">
                                    <strong>Tracking Number:</strong>
                                    <span class="tracking-number"><?php echo htmlspecialchars($item['tracking_number']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- per-item badge removed -->
                    </div>
                <?php endforeach; ?>
                <div style="text-align:center; margin-top:10px;">
                    <button type="button" id="showMoreItemsBtn" class="btn btn-back" style="display:none;">Show more</button>
                </div>
            </div>

            <div class="order-actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #333;">
                <a href="order_status.php" class="btn btn-back">Back to Orders</a>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeDropdown = document.getElementById('themeDropdown');
            const themeMenu = document.getElementById('themeMenu');
            const themeBtn = document.getElementById('themeDropdownBtn');
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            let currentTheme = '<?php echo htmlspecialchars($theme); ?>';

            function applyTheme(theme) {
                let effective = theme;
                if (theme === 'device') { effective = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                document.documentElement.setAttribute('data-theme', effective);
                if (themeIcon && themeText) {
                    if (theme === 'device') { themeIcon.className = 'bi theme-icon bi-laptop'; themeText.textContent = 'Device'; }
                    else if (theme === 'dark') { themeIcon.className = 'bi theme-icon bi-moon-fill'; themeText.textContent = 'Dark'; }
                    else { themeIcon.className = 'bi theme-icon bi-sun-fill'; themeText.textContent = 'Light'; }
                }
                fetch(`?theme=${theme}`, { method: 'GET' }).catch(() => {});
            }
            applyTheme(currentTheme);
            if (themeBtn && themeDropdown) {
                themeBtn.addEventListener('click', (e) => { e.stopPropagation(); themeDropdown.classList.toggle('active'); });
            }
            if (themeMenu) {
                themeMenu.addEventListener('click', (e) => {
                    const option = e.target.closest('.theme-option');
                    if (!option) return; currentTheme = option.dataset.theme; applyTheme(currentTheme); themeDropdown.classList.remove('active');
                });
            }
            document.addEventListener('click', (e) => { if (themeDropdown && !themeDropdown.contains(e.target)) themeDropdown.classList.remove('active'); });
            if (currentTheme === 'device') { const mq = window.matchMedia('(prefers-color-scheme: dark)'); mq.addEventListener('change', () => { if (currentTheme === 'device') applyTheme('device'); }); }

            const hamburger = document.querySelector('.hamburger');
            const menu = document.getElementById('menu');
            if (hamburger && menu) {
                hamburger.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); menu.classList.toggle('show'); });
                document.addEventListener('click', function(e) { if (!hamburger.contains(e.target) && !menu.contains(e.target)) { menu.classList.remove('show'); } });
                menu.querySelectorAll('a').forEach(item => { item.addEventListener('click', () => menu.classList.remove('show')); });
            }
            // Show-more pagination for order items (5 at a time)
            const orderItems = document.querySelectorAll('.order-items .order-item');
            const showMoreBtn = document.getElementById('showMoreItemsBtn');
            const PAGE_SIZE = 5;
            let visibleCount = 0;

            function updateVisibility() {
                orderItems.forEach((el, idx) => {
                    el.style.display = idx < visibleCount ? 'flex' : 'none';
                });
                if (showMoreBtn) {
                    showMoreBtn.style.display = visibleCount < orderItems.length ? 'inline-block' : 'none';
                }
            }

            if (orderItems.length > 0) {
                visibleCount = Math.min(PAGE_SIZE, orderItems.length);
                updateVisibility();
            }

            if (showMoreBtn) {
                showMoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    visibleCount = Math.min(visibleCount + PAGE_SIZE, orderItems.length);
                    updateVisibility();
                });
            }
        });
    </script>
</body>
</html>