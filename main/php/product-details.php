<?php
session_start();
include("db.php");

// --- 1. Theme Logic (Matches Shop.php) ---
if (isset($_GET['theme'])) {
    $new_theme = in_array($_GET['theme'], ['light', 'dark', 'device']) ? $_GET['theme'] : 'device';
    $_SESSION['theme'] = $new_theme;
} else {
    $theme = $_SESSION['theme'] ?? 'device';
}

$effective_theme = $theme;
if ($theme === 'device') {
    $effective_theme = 'dark'; // Fallback default
}

// --- 2. Cart Count Logic ---
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    if ($count_result->num_rows > 0) {
        $cart_data = $count_result->fetch_assoc();
        $cart_count = $cart_data['total'] ?: 0;
    }
}

// --- 3. Notification Logic ---
$notif_count = 0;
if(isset($_SESSION['user_id'])) {
    $n_user_id = $_SESSION['user_id'];
    $notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("i", $n_user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    if ($notif_result->num_rows > 0) {
        $notif_data = $notif_result->fetch_assoc();
        $notif_count = $notif_data['count'];
    }
}

// --- Product Fetching Logic ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Fetch product info
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.image, p.price, p.stock_quantity, p.description, p.seller_id, p.sku,
           u.seller_name, u.fullname AS seller_fullname, u.profile_image,
           GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ',') AS categories
    FROM products p
    LEFT JOIN users u ON p.seller_id = u.id
    LEFT JOIN product_categories pc ON p.id = pc.product_id
    LEFT JOIN categories c ON pc.category_id = c.id
    WHERE p.id = ?
    GROUP BY p.id, p.name, p.image, p.price, p.stock_quantity, p.description, p.seller_id, u.seller_name, u.fullname, u.profile_image
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Product not found.";
    exit();
}

$product = $result->fetch_assoc();

// Ensure consistent image path
$image_path = $product['image'];
if (empty($image_path) || !file_exists($image_path)) {
    $image_path = 'Uploads/default-product.jpg';
}

// Check variant columns
$variant_columns_exist = false;
$check_columns = $conn->query("SHOW COLUMNS FROM product_specs LIKE 'price'");
if ($check_columns->num_rows > 0) {
    $variant_columns_exist = true;
}

// Fetch specifications
$spec_stmt = $conn->prepare("SELECT DISTINCT spec_name FROM product_specs WHERE product_id = ? ORDER BY spec_name");
$spec_stmt->bind_param("i", $product_id);
$spec_stmt->execute();
$spec_result = $spec_stmt->get_result();
$spec_types = [];
while ($row = $spec_result->fetch_assoc()) {
    $spec_types[] = $row['spec_name'];
}

// Fetch options and variants
$spec_options = [];
$variants = [];
if ($variant_columns_exist) {
    $variant_query = "
        SELECT spec_name, spec_value, price, stock_quantity, image, sku
        FROM product_specs
        WHERE product_id = ?
        ORDER BY spec_name, spec_value
    ";
    $variant_stmt = $conn->prepare($variant_query);
    $variant_stmt->bind_param("i", $product_id);
    $variant_stmt->execute();
    $variant_result = $variant_stmt->get_result();
    while ($row = $variant_result->fetch_assoc()) {
        $spec_name = $row['spec_name'];
        $spec_value = $row['spec_value'];
        if (!isset($spec_options[$spec_name])) {
            $spec_options[$spec_name] = [];
        }
        if (!in_array($spec_value, $spec_options[$spec_name])) {
            $spec_options[$spec_name][] = $spec_value;
        }
        $variants[] = [
            'spec_name' => $spec_name,
            'spec_value' => $spec_value,
            'price' => $row['price'] ?? $product['price'],
            'stock_quantity' => $row['stock_quantity'] ?? $product['stock_quantity'],
            'image' => $row['image'] ?? $image_path,
            'sku' => $row['sku'] ?? $product['sku']
        ];
    }
} else {
    foreach ($spec_types as $spec_name) {
        $opt_stmt = $conn->prepare("SELECT DISTINCT spec_value FROM product_specs WHERE product_id = ? AND spec_name = ?");
        $opt_stmt->bind_param("is", $product_id, $spec_name);
        $opt_stmt->execute();
        $opt_result = $opt_stmt->get_result();
        $options = [];
        while ($row = $opt_result->fetch_assoc()) {
            $options[] = $row['spec_value'];
            $variants[] = [
                'spec_name' => $spec_name,
                'spec_value' => $row['spec_value'],
                'price' => $product['price'],
                'stock_quantity' => $product['stock_quantity'],
                'image' => $image_path,
                'sku' => $product['sku']
            ];
        }
        $spec_options[$spec_name] = $options;
    }
}

// Seller stats
$seller_rating_stmt = $conn->prepare("
    SELECT AVG(r.rating) as avg_rating, COUNT(r.id) as review_count 
    FROM seller_reviews r 
    WHERE r.seller_id = ?
");
$seller_rating_stmt->bind_param("i", $product['seller_id']);
$seller_rating_stmt->execute();
$seller_rating_result = $seller_rating_stmt->get_result();
$seller_rating = $seller_rating_result->fetch_assoc();
$avg_rating = round($seller_rating['avg_rating'] ?? 0, 1);
$review_count = $seller_rating['review_count'] ?? 0;

$products_count_stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE seller_id = ? AND is_active = TRUE");
$products_count_stmt->bind_param("i", $product['seller_id']);
$products_count_stmt->execute();
$products_count_result = $products_count_stmt->get_result();
$products_count = $products_count_result->fetch_assoc()['product_count'] ?? 0;

$response_rate = 100; 
$followers = 1000; 
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($product['name']); ?> | Meta Shark</title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<link rel="stylesheet" href="../../css/shop.css">
<link rel="stylesheet" href="css/shop_custom.css">

<style>
/* --- 1. COPY OF SHOP.PHP VARIABLES (Ensures Exact Colors) --- */
:root {
    --bg-primary: var(--dark-bg-primary);
    --bg-secondary: var(--dark-bg-secondary);
    --bg-tertiary: var(--dark-bg-tertiary);
    --text-primary: var(--dark-text-primary);
    --text-secondary: var(--dark-text-secondary);
    --text-muted: var(--dark-text-muted);
    --border: var(--dark-border);
    --border-light: var(--dark-border-light);
    --accent: var(--dark-accent);
    --accent-hover: var(--dark-accent-hover);
    --accent-light: var(--dark-accent-light);
    --shadow: var(--dark-shadow);
    --shadow-hover: var(--dark-shadow-hover);
    --theme-toggle-bg: var(--dark-theme-toggle-bg);
    --theme-toggle-text: var(--dark-theme-toggle-text);
    --theme-toggle-border: var(--dark-theme-toggle-border);
    --theme-toggle-hover: var(--dark-theme-toggle-hover);
    --theme-shadow: var(--dark-theme-shadow);
    
    /* Light Theme Definitions */
    --light-bg-primary: #ffffff;
    --light-bg-secondary: #f9f9f9;
    --light-bg-tertiary: #f0f0f0;
    --light-text-primary: #000000;
    --light-text-secondary: #333333;
    --light-text-muted: #666666;
    --light-border: #e0e0e0;
    --light-border-light: #f0f0f0;
    --light-accent: #44D62C;
    --light-accent-hover: #36b020;
    --light-accent-light: #eaffea;
    --light-shadow: rgba(0, 0, 0, 0.1);
    --light-shadow-hover: rgba(0, 0, 0, 0.2);
    --light-theme-toggle-bg: #ffffff;
    --light-theme-toggle-text: #000000;
    --light-theme-toggle-border: #44D62C;
    --light-theme-toggle-hover: #f0f0f0;
    --light-theme-shadow: rgba(0, 0, 0, 0.1);

    /* Dark Theme Definitions */
    --dark-bg-primary: #000000;
    --dark-bg-secondary: #111111;
    --dark-bg-tertiary: #1a1a1a;
    --dark-text-primary: #ffffff;
    --dark-text-secondary: #cccccc;
    --dark-text-muted: #888888;
    --dark-border: #333333;
    --dark-border-light: #444444;
    --dark-accent: #44D62C;
    --dark-accent-hover: #36b020;
    --dark-accent-light: #2a5a1a;
    --dark-shadow: rgba(0, 0, 0, 0.3);
    --dark-shadow-hover: rgba(0, 0, 0, 0.4);
    --dark-theme-toggle-bg: #1a1a1a;
    --dark-theme-toggle-text: #ffffff;
    --dark-theme-toggle-border: #44D62C;
    --dark-theme-toggle-hover: #333333;
    --dark-theme-shadow: rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] {
    --bg-primary: var(--dark-bg-primary);
    --bg-secondary: var(--dark-bg-secondary);
    --bg-tertiary: var(--dark-bg-tertiary);
    --text-primary: var(--dark-text-primary);
    --text-secondary: var(--dark-text-secondary);
    --text-muted: var(--dark-text-muted);
    --border: var(--dark-border);
    --border-light: var(--dark-border-light);
    --accent: var(--dark-accent);
    --accent-hover: var(--dark-accent-hover);
    --accent-light: var(--dark-accent-light);
    --shadow: var(--dark-shadow);
    --shadow-hover: var(--dark-shadow-hover);
    --theme-toggle-bg: var(--dark-theme-toggle-bg);
    --theme-toggle-text: var(--dark-theme-toggle-text);
    --theme-toggle-border: var(--dark-theme-toggle-border);
    --theme-toggle-hover: var(--dark-theme-toggle-hover);
    --theme-shadow: var(--dark-theme-shadow);
}

[data-theme="light"] {
    --bg-primary: var(--light-bg-primary);
    --bg-secondary: var(--light-bg-secondary);
    --bg-tertiary: var(--light-bg-tertiary);
    --text-primary: var(--light-text-primary);
    --text-secondary: var(--light-text-secondary);
    --text-muted: var(--light-text-muted);
    --border: var(--light-border);
    --border-light: var(--light-border-light);
    --accent: var(--light-accent);
    --accent-hover: var(--light-accent-hover);
    --accent-light: var(--light-accent-light);
    --shadow: var(--light-shadow);
    --shadow-hover: var(--light-shadow-hover);
    --theme-toggle-bg: var(--light-theme-toggle-bg);
    --theme-toggle-text: var(--light-theme-toggle-text);
    --theme-toggle-border: var(--light-theme-toggle-border);
    --theme-toggle-hover: var(--light-theme-toggle-hover);
    --theme-shadow: var(--light-theme-shadow);
}

body.product-details-page {
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: "Poppins", sans-serif;
    margin: 0;
    padding: 0;
}

/* Nav specific overrides for non-users */
.nonuser-text {
    font-weight: 600;
    font-size: 14px;
    color: var(--accent) !important;
}

/* --- PRODUCT DETAILS SPECIFIC CSS --- */
.product-detail-container {
    display: flex;
    gap: 2rem;
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}
.product-image { flex: 1; max-width: 50%; }
.product-image img {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.product-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: var(--bg-tertiary);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.product-info h1 { margin: 0; font-size: 2rem; color: var(--text-primary); }
.price { font-size: 1.5rem; font-weight: bold; color: var(--accent) !important; } 
.seller-info, .stock, .sku { margin: 0.5rem 0; font-size: 1rem; color: var(--text-secondary); }
.seller-info a { color: var(--accent); text-decoration: none; }
.specs {
    background: var(--bg-primary);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.specs h3 { margin: 0 0 0.5rem; font-size: 1.25rem; color: var(--text-primary); }
.spec-group { margin-bottom: 1rem; }
.spec-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary); }
.spec-options { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.spec-option {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 0.5rem 1rem;
    cursor: pointer;
    color: var(--text-primary);
    transition: all 0.3s ease;
}
.spec-option:hover { border-color: var(--accent); background: rgba(0,255,136,0.1); }
.spec-option.selected { border-color: var(--accent); background: rgba(0,255,136,0.2); font-weight: 600; color: var(--accent); }
.spec-option.disabled { background: #6c757d; color: #fff; cursor: not-allowed; opacity: 0.6; }

.add-to-cart-btn, .btn-edit {
    background: var(--accent); 
    color: #000; 
    border: none;
    padding: 1rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 4px;
    cursor: pointer;
    align-self: flex-start;
    transition: background 0.3s ease, transform 0.2s ease;
}
.add-to-cart-btn:hover, .btn-edit:hover {
    background: var(--accent-hover);
    transform: translateY(-2px);
    box-shadow: 0 0 10px rgba(0, 255, 136, 0.4);
}
.add-to-cart-btn:disabled { background: #6c757d; cursor: not-allowed; color: white;}

.seller-section {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 1.5rem;
    background: var(--bg-tertiary);
    border-radius: 8px;
    border: 1px solid var(--border);
}
.seller-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.seller-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
.seller-name { font-size: 1.5rem; font-weight: bold; color: var(--text-primary); }
.seller-rating { display: flex; align-items: center; gap: 0.5rem; color: #ffc107; }
.seller-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.stat-item { text-align: center; }
.stat-label { font-size: 0.9rem; color: var(--text-secondary); }
.stat-value { font-weight: bold; font-size: 1.1rem; color: var(--text-primary); }
.seller-actions { display: flex; gap: 1rem; }
.btn-seller {
    background: transparent;
    color: var(--accent);
    border: 1px solid var(--accent);
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
}
.btn-seller:hover {
    background: var(--accent);
    color: var(--bg-primary);
}
.description {
    margin-top: 1.5rem;
    line-height: 1.6;
    color: var(--text-secondary);
    background: var(--bg-primary);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}
.description h3 { margin: 0 0 0.5rem; font-size: 1.25rem; color: var(--text-primary); }

@media (max-width: 768px) {
    .product-detail-container { flex-direction: column; }
    .product-image { max-width: 100%; }
    .product-info { padding: 1rem; }
    .seller-header { flex-direction: column; text-align: center; }
    .seller-stats { grid-template-columns: 1fr; }
    .add-to-cart-btn, .btn-edit { width: 100%; text-align: center; }
    .spec-options { flex-direction: column; }
    .spec-option { width: 100%; text-align: center; }
}
</style>
</head>
<body class="product-details-page">

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
      <i class="bi bi-bell" style="font-size:18px; color: #00ff88;"></i>
      <span style="color: #00ff88;"><?php echo $notif_count > 0 ? "($notif_count)" : ""; ?></span>
    </a>

    <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
      <i class="bi bi-cart" style="font-size:18px; color: #00ff88;"></i>
      <span style="color: #00ff88;">(<?php echo (int)$cart_count; ?>)</span>
    </a>

   <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
  <?php
  $user_role = $_SESSION['role'] ?? 'buyer';
  $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
  
  // Get current user data
  $current_user_id = $_SESSION['user_id'];
  $cp_stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
  $cp_stmt->bind_param("i", $current_user_id);
  $cp_stmt->execute();
  $cp_res = $cp_stmt->get_result();
  $cp_data = $cp_res->fetch_assoc();
  $current_profile_image = $cp_data['profile_image'] ?? null;
  
  // Path checking logic
  $img_path = 'uploads/logo1.png'; // Default safe fallback
  if (!empty($current_profile_image)) {
      if (file_exists('Uploads/' . $current_profile_image)) {
          $img_path = 'Uploads/' . $current_profile_image;
      } elseif (file_exists('uploads/' . $current_profile_image)) {
          $img_path = 'uploads/' . $current_profile_image;
      }
  }
  ?>
  <a href="<?php echo $profile_page; ?>" style="margin-left: 10px;">
      <img src="<?php echo htmlspecialchars($img_path); ?>" alt="Profile" class="profile-icon">
  </a>
<?php else: ?>
  <a href="login_users.php" style="text-decoration: none;">
    <div class="nonuser-text" style="color: #00ff88; margin-left: 10px;">Login</div>
  </a>
  <a href="signup_users.php" style="text-decoration: none;">
    <div class="nonuser-text" style="color: #00ff88; margin-left: 10px;">Signup</div>
  </a>
  <a href="login_users.php" style="margin-left: 10px;">
     <div class="profile-icon" style="display:flex; align-items:center; justify-content:center; color:#00ff88;">
        <i class="bi bi-person-fill"></i>
     </div>
  </a>
<?php endif; ?>
    <button class="hamburger" style="color: #00ff88;">☰</button>
  </div>
  
  <ul class="menu" style="color: #00ff88;" id="menu">
    <li><a href="shop.php" style="color: #00ff88;">Home</a></li>
    <li><a href="carts_users.php" style="color: #00ff88;">Cart (<?php echo $cart_count; ?>)</a></li>
     <li><a href="order_status.php" style="color: #00ff88;">My Purchases</a></li>
    <?php if(isset($_SESSION['user_id'])): ?>
      <?php if($user_role === 'seller' || $user_role === 'admin'): ?>
        <li><a href="seller_dashboard.php" style="color: #00ff88;">Seller Dashboard</a></li>
      <?php else: ?>
        <li><a href="become_seller.php" style="color: #00ff88;">Become Seller</a></li>
      <?php endif; ?>
      <li><a href="<?php echo $profile_page; ?>" style="border: #00ff88;">Profile</a></li>
      <li><a href="logout.php" style="color: #00ff88;">Logout</a></li>
    <?php endif; ?>
  </ul>
</div>

<div class="product-detail-container">
    <div class="product-image">
        <img id="product-image" src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
    </div>
    <div class="product-info">
        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
        <p class="price" id="product-price">$<?php echo number_format($product['price'], 2); ?></p>
        <p class="seller-info">
            Sold by: 
            <a href="seller_shop.php?seller_id=<?php echo $product['seller_id']; ?>">
                <?php echo htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']); ?>
            </a>
        </p>
        <p class="stock" id="product-stock">Stock: <?php echo $product['stock_quantity']; ?></p>
        <p class="sku" id="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
        <?php if (!empty($spec_options)): ?>
        <div class="specs">
            <h3>Select Specifications:</h3>
            <?php foreach ($spec_options as $spec_name => $options): ?>
            <div class="spec-group">
                <label><?php echo htmlspecialchars($spec_name); ?>:</label>
                <div class="spec-options" data-spec-name="<?php echo htmlspecialchars($spec_name); ?>">
                    <?php foreach ($options as $option): ?>
                        <button type="button" class="spec-option" 
                                data-spec-name="<?php echo htmlspecialchars($spec_name); ?>" 
                                data-spec-value="<?php echo htmlspecialchars($option); ?>"
                                aria-label="Select <?php echo htmlspecialchars($spec_name . ': ' . $option); ?>">
                            <?php echo htmlspecialchars($option); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $product['seller_id']): ?>
        <form method="POST" action="shop.php" class="add-to-cart-form">
            <input type="hidden" name="add_to_cart" value="1">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <input type="hidden" name="spec_combination" id="selected-spec-combination" value="">
            <button type="submit" class="add-to-cart-btn" id="add-to-cart-btn" 
                    <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                <?php echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
            </button>
        </form>
        <?php else: ?>
            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn-edit">Edit Product</a>
        <?php endif; ?>
    </div>
</div>

<div class="seller-section">
    <div class="seller-header">
        <img src="<?php echo !empty($product['profile_image']) && file_exists('Uploads/' . $product['profile_image']) ? 'Uploads/' . htmlspecialchars($product['profile_image']) : 'Uploads/default-avatar.svg'; ?>" alt="Seller Avatar" class="seller-avatar">
        <div>
            <div class="seller-name"><?php echo htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']); ?></div>
            <div class="seller-rating">
                ★ <?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)
            </div>
        </div>
    </div>
    <div class="seller-stats">
        <div class="stat-item">
            <div class="stat-value"><?php echo $response_rate; ?>%</div>
            <div class="stat-label">Response Rate</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo $products_count; ?></div>
            <div class="stat-label">Products</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo number_format($followers); ?></div>
            <div class="stat-label">Followers</div>
        </div>
    </div>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $product['seller_id']): ?>
    <div class="seller-actions">
        <a href="chat.php?seller_id=<?php echo $product['seller_id']; ?>" class="btn-seller">Chat Now</a>
        <a href="seller_shop.php?seller_id=<?php echo $product['seller_id']; ?>" class="btn-seller">View Shop</a>
    </div>
    <?php endif; ?>
    <div class="description">
        <h3>Description:</h3>
        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme toggle functionality (Updated to match Shop.php logic)
    const themeDropdown = document.getElementById('themeDropdown');
    const themeMenu = document.getElementById('themeMenu');
    const themeBtn = document.getElementById('themeDropdownBtn');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    let currentTheme = '<?php echo htmlspecialchars($theme); ?>';

    // Initialize theme
    applyTheme(currentTheme);

    // Apply theme based on selection or system preference
    function applyTheme(theme) {
        let effectiveTheme = theme;
        if (theme === 'device') {
            effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        updateThemeUI(theme, effectiveTheme);
        
        // Save theme to server
        fetch(`?theme=${theme}`, { method: 'GET' })
            .catch(error => console.error('Error saving theme:', error));
    }

    // Update theme button UI
    function updateThemeUI(theme, effectiveTheme) {
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
            themeDropdown.classList.toggle('active');
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
        });
    }

    // Close theme menu when clicking outside
    document.addEventListener('click', (e) => {
        if (themeDropdown && !themeDropdown.contains(e.target)) {
            themeDropdown.classList.remove('active');
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

    // Variant selection logic (Preserved)
    const variants = <?php echo json_encode($variants); ?>;
    const selectedSpecs = {};
    const specButtons = document.querySelectorAll('.spec-option');
    const productImage = document.getElementById('product-image');
    const productPrice = document.getElementById('product-price');
    const productStock = document.getElementById('product-stock');
    const productSku = document.getElementById('product-sku');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const specCombinationInput = document.getElementById('selected-spec-combination');

    function updateVariant() {
        // Find matching variant
        const selectedVariant = variants.find(variant => {
            return Object.keys(selectedSpecs).every(specName => 
                variant.spec_name === specName && variant.spec_value === selectedSpecs[specName]
            );
        });

        if (selectedVariant && Object.keys(selectedSpecs).length === <?php echo count($spec_types); ?>) {
            productPrice.textContent = `$${Number(selectedVariant.price).toFixed(2)}`;
            productStock.textContent = `Stock: ${selectedVariant.stock_quantity}`;
            productSku.textContent = `SKU: ${selectedVariant.sku}`;
            productImage.src = selectedVariant.image;
            addToCartBtn.disabled = selectedVariant.stock_quantity <= 0;
            addToCartBtn.textContent = selectedVariant.stock_quantity <= 0 ? 'Out of Stock' : 'Add to Cart';
            specCombinationInput.value = JSON.stringify(selectedSpecs);
        } else {
            // Fallback to default product details
            productPrice.textContent = `$<?php echo number_format($product['price'], 2); ?>`;
            productStock.textContent = `Stock: <?php echo $product['stock_quantity']; ?>`;
            productSku.textContent = `SKU: <?php echo htmlspecialchars($product['sku']); ?>`;
            productImage.src = '<?php echo htmlspecialchars($image_path); ?>';
            specCombinationInput.value = '';
            addToCartBtn.disabled = <?php echo $product['stock_quantity'] <= 0 ? 'true' : 'false'; ?>;
            addToCartBtn.textContent = '<?php echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>';
        }

        // Update button states
        specButtons.forEach(button => {
            const specName = button.dataset.specName;
            const specValue = button.dataset.specValue;
            const isValid = isValidCombination(specName, specValue);
            if (isValid) {
                button.classList.remove('disabled');
            } else {
                button.classList.add('disabled');
            }
        });
    }

    function isValidCombination(specName, specValue) {
        const tempSpecs = { ...selectedSpecs, [specName]: specValue };
        return variants.some(variant => 
            Object.keys(tempSpecs).every(key => 
                variant.spec_name === key && variant.spec_value === tempSpecs[key]
            )
        );
    }

    specButtons.forEach(button => {
        button.addEventListener('click', () => {
            const specName = button.dataset.specName;
            const specValue = button.dataset.specValue;
            
            // Toggle selection
            if (selectedSpecs[specName] === specValue) {
                delete selectedSpecs[specName];
                button.classList.remove('selected');
            } else {
                selectedSpecs[specName] = specValue;
                // Update selected state
                document.querySelectorAll(`.spec-option[data-spec-name="${specName}"]`).forEach(btn => {
                    btn.classList.remove('selected');
                });
                button.classList.add('selected');
            }

            updateVariant();
        });
    });

    // Initialize default selections
    <?php foreach ($spec_options as $spec_name => $options): ?>
        <?php if (!empty($options)): ?>
            selectedSpecs['<?php echo htmlspecialchars($spec_name); ?>'] = '<?php echo htmlspecialchars($options[0]); ?>';
            document.querySelector(`.spec-option[data-spec-name="<?php echo htmlspecialchars($spec_name); ?>"][data-spec-value="<?php echo htmlspecialchars($options[0]); ?>"]`)?.classList.add('selected');
        <?php endif; ?>
    <?php endforeach; ?>
    updateVariant();
});
</script>
</body>
</html>