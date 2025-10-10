<?php
session_start();
include("db.php");

// Get product ID from URL
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

// Ensure consistent image path - fallback to default if image doesn't exist
$image_path = $product['image'];
if (empty($image_path) || !file_exists($image_path)) {
    $image_path = 'Uploads/default-product.jpg'; // Assuming a default product image exists
}

// Check if product_specs table has variant-specific columns
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

// Fetch specification options and variant data
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

// Fetch seller stats
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

// Fetch total products for seller
$products_count_stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE seller_id = ? AND is_active = TRUE");
$products_count_stmt->bind_param("i", $product['seller_id']);
$products_count_stmt->execute();
$products_count_result = $products_count_stmt->get_result();
$products_count = $products_count_result->fetch_assoc()['product_count'] ?? 0;

// Mock or fetch other stats
$response_rate = 100; // Mock
$followers = 1000; // Mock
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme'] ?? 'dark'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($product['name']); ?> | Meta Shark</title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root {
    --background: #fff;
    --text-color: #333;
    --primary-color: #00ff88;
    --secondary-bg: #f8f9fa;
    --border-color: #dee2e6;
}
[data-theme="dark"] {
    --background: #000000ff;
    --text-color: #e0e0e0;
    --primary-color: #00ff88;
    --secondary-bg: #2a2a2a;
    --border-color: #444;
}
body.product-details-page {
    background: var(--background);
    color: var(--text-color);
    font-family: "Poppins", sans-serif;
    margin: 0;
    padding: 0;
}
.navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: var(--background);
    padding: 10px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
}
.nav-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.nav-left .logo {
    height: 40px;
}
.nav-left h2 {
    margin: 0;
    font-size: 24px;
    color: var(--text-color);
}
.nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.theme-dropdown {
    position: relative;
    display: inline-block;
}
.theme-btn {
    appearance: none;
    background: var(--background);
    color: var(--text-color);
    border: 2px solid #006400;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    min-width: 120px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.theme-btn:hover {
    background: #006400;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,100,0,0.2);
}
.theme-btn:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
.theme-dropdown:after {
    content: '\25BC';
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--text-color);
}
.theme-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: var(--background);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 8px;
    min-width: 90px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    display: none;
    z-index: 1000;
}
.theme-dropdown.active .theme-menu {
    display: block;
}
.theme-option {
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    font-weight: 600;
    color: var(--text-color);
    transition: background 0.2s ease, color 0.2s ease;
}
.theme-option:hover {
    background: var(--secondary-bg);
    color: #00aa55;
}
.theme-option:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.hamburger {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-color);
    transition: color 0.3s ease;
}
.hamburger:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
.menu {
    display: none;
    position: absolute;
    top: 60px;
    right: 20px;
    background: var(--background);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px;
    list-style: none;
    margin: 0;
    z-index: 1000;
    transform: translateY(-10px);
    opacity: 0;
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.menu.show {
    display: block;
    transform: translateY(0);
    opacity: 1;
}
.menu li {
    margin: 10px 0;
}
.menu li a {
    color: var(--text-color);
    text-decoration: none;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-radius: 4px;
    transition: color 0.2s ease, background 0.2s ease;
}
.menu li a:hover {
    color: #27ed15;
    background: var(--secondary-bg);
}
.product-detail-container {
    display: flex;
    gap: 2rem;
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}
.product-image {
    flex: 1;
    max-width: 50%;
}
.product-image img {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.product-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: var(--secondary-bg);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.product-info h1 {
    margin: 0;
    font-size: 2rem;
    color: var(--text-color);
}
.price {
    font-size: 1.5rem;
    font-weight: bold;
    color: #28a745;
}
.seller-info, .stock, .sku {
    margin: 0.5rem 0;
    font-size: 1rem;
    color: var(--text-color);
}
.seller-info a {
    color: #007bff;
    text-decoration: none;
}
.seller-info a:hover {
    text-decoration: underline;
}
.specs {
    background: var(--background);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.specs h3 {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
    color: var(--text-color);
}
.spec-group {
    margin-bottom: 1rem;
}
.spec-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-color);
}
.spec-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.spec-option {
    background: var(--background);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 0.5rem 1rem;
    cursor: pointer;
    color: var(--text-color);
    transition: all 0.3s ease;
}
.spec-option:hover {
    border-color: var(--primary-color);
    background: rgba(0,255,136,0.1);
}
.spec-option.selected {
    border-color: var(--primary-color);
    background: rgba(0,255,136,0.2);
    font-weight: 600;
}
.spec-option.disabled {
    background: #6c757d;
    color: #fff;
    cursor: not-allowed;
    opacity: 0.6;
}
.add-to-cart-btn, .btn-edit {
    background: #28a745;
    color: white;
    border: none;
    padding: 1rem 2rem;
    font-size: 1rem;
    border-radius: 4px;
    cursor: pointer;
    align-self: flex-start;
    transition: background 0.3s ease, transform 0.2s ease;
}
.add-to-cart-btn:hover, .btn-edit:hover {
    background: #218838;
    transform: translateY(-2px);
}
.add-to-cart-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}
.seller-section {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 1.5rem;
    background: var(--secondary-bg);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.seller-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}
.seller-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
}
.seller-name {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--text-color);
}
.seller-rating {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #ffc107;
}
.seller-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.stat-item {
    text-align: center;
}
.stat-label {
    font-size: 0.9rem;
    color: var(--text-color);
}
.stat-value {
    font-weight: bold;
    font-size: 1.1rem;
    color: var(--text-color);
}
.seller-actions {
    display: flex;
    gap: 1rem;
}
.btn-seller {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s ease, transform 0.2s ease;
}
.btn-seller:hover {
    background: #0056b3;
    transform: translateY(-2px);
}
.description {
    margin-top: 1.5rem;
    line-height: 1.6;
    color: var(--text-color);
    background: var(--background);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.description h3 {
    margin: 0 0 0.5rem;
    font-size: 1.25rem;
    color: var(--text-color);
}
@media (max-width: 768px) {
    .product-detail-container {
        flex-direction: column;
    }
    .product-image {
        max-width: 100%;
    }
    .product-info {
        padding: 1rem;
    }
    .seller-header {
        flex-direction: column;
        text-align: center;
    }
    .seller-stats {
        grid-template-columns: 1fr;
    }
    .add-to-cart-btn, .btn-edit {
        width: 100%;
        text-align: center;
    }
    .description {
        padding: 1rem;
    }
    .spec-options {
        flex-direction: column;
    }
    .spec-option {
        width: 100%;
        text-align: center;
    }
}
</style>
</head>
<body class="product-details-page">

<!-- Navbar -->
<div class="navbar" role="navigation" aria-label="Main navigation">
    <div class="nav-left">
        <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
        <h2>Meta Shark</h2>
    </div>
    <div class="nav-right">
        <!-- Theme dropdown -->
        <div class="theme-dropdown" id="themeDropdown">
            <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme" aria-haspopup="true" aria-expanded="false">
                <i class="bi theme-icon" id="themeIcon"></i>
                <span class="theme-text" id="themeText"><?php echo $_SESSION['theme'] === 'device' ? 'Device' : ($_SESSION['theme'] === 'light' ? 'Light' : 'Dark'); ?></span>
            </button>
            <div class="theme-menu" id="themeMenu" aria-hidden="true">
                <button class="theme-option" data-theme="light" role="menuitem">Light</button>
                <button class="theme-option" data-theme="dark" role="menuitem">Dark</button>
                <button class="theme-option" data-theme="device" role="menuitem">Device</button>
            </div>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="notifications.php" title="Notifications" style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;" aria-label="Notifications">
                <i class="bi bi-bell" style="font-size:18px;"></i>
                <span aria-hidden="true"><?php echo isset($notif_count) && $notif_count > 0 ? "($notif_count)" : ""; ?></span>
            </a>
            <?php
            $user_role = $_SESSION['role'] ?? 'buyer';
            $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
            $profile_query = "SELECT profile_image FROM users WHERE id = ?";
            $profile_stmt = $conn->prepare($profile_query);
            $profile_stmt->bind_param("i", $_SESSION['user_id']);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            $current_profile = $profile_result->fetch_assoc();
            $current_profile_image = $current_profile['profile_image'] ?? null;
            ?>
          
        <?php else: ?>
            <a href="login_users.php" class="nonuser-text">Login</a>
            <a href="signup_users.php" class="nonuser-text">Sign Up</a>
        <?php endif; ?>
        <button class="hamburger" aria-label="Toggle menu" aria-controls="menu" aria-expanded="false">☰</button>
    </div>
    <ul class="menu" id="menu" role="menu">
        <li role="none"><a href="shop.php" role="menuitem">Home</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
                <li role="none"><a href="seller_dashboard.php" role="menuitem">Seller Dashboard</a></li>
            <?php else: ?>
                <li role="none"><a href="become_seller.php" role="menuitem">Become Seller</a></li>
            <?php endif; ?>
            <li role="none"><a href="<?php echo $profile_page; ?>" role="menuitem">Profile</a></li>
            <li role="none"><a href="logout.php" role="menuitem">Logout</a></li>
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
    // Theme toggle functionality
    const themeDropdown = document.getElementById('themeDropdown');
    const themeMenu = document.getElementById('themeMenu');
    const themeBtn = document.getElementById('themeDropdownBtn');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    let currentTheme = '<?php echo htmlspecialchars($_SESSION['theme'] ?? 'dark'); ?>';

    // Initialize theme
    applyTheme(currentTheme);

    // Apply theme based on selection or system preference
    function applyTheme(theme) {
        let effectiveTheme = theme;
        if (theme === 'device') {
            effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        updateTheme(theme, effectiveTheme);
        
        // Save theme to server
        fetch(`?theme=${theme}`, { method: 'GET' })
            .catch(error => console.error('Error saving theme:', error));
    }

    // Update theme button UI
    function updateTheme(theme, effectiveTheme) {
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
            const isExpanded = themeBtn.getAttribute('aria-expanded') === 'true';
            themeBtn.setAttribute('aria-expanded', !isExpanded);
            themeDropdown.classList.toggle('active');
        });

        // Keyboard navigation for theme button
        themeBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                themeBtn.click();
            }
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
            themeBtn.setAttribute('aria-expanded', 'false');
        });

        // Keyboard navigation for theme options
        const options = themeMenu.querySelectorAll('.theme-option');
        options.forEach((option, index) => {
            option.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    option.click();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const nextIndex = (index + 1) % options.length;
                    options[nextIndex].focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prevIndex = (index - 1 + options.length) % options.length;
                    options[prevIndex].focus();
                }
            });
        });
    }

    // Close theme menu when clicking outside
    document.addEventListener('click', (e) => {
        if (themeDropdown && !themeDropdown.contains(e.target)) {
            themeDropdown.classList.remove('active');
            themeBtn.setAttribute('aria-expanded', 'false');
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
            const isExpanded = hamburger.getAttribute('aria-expanded') === 'true';
            hamburger.setAttribute('aria-expanded', !isExpanded);
            menu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('show');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });

        const menuItems = menu.querySelectorAll('a');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                menu.classList.remove('show');
                hamburger.setAttribute('aria-expanded', 'false');
            });

            // Keyboard navigation for menu items
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    item.click();
                }
            });
        });
    }

    // Variant selection logic
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

        // Keyboard navigation for spec buttons
        button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                button.click();
            }
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