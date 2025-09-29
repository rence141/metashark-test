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
    SELECT p.id, p.name, p.image, p.price, p.stock_quantity, p.description, p.seller_id,
           u.seller_name, u.fullname AS seller_fullname, u.profile_image,
           GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ',') AS categories,
           GROUP_CONCAT(ps.spec_name, ':', ps.spec_value SEPARATOR ',') AS specs
    FROM products p
    LEFT JOIN users u ON p.seller_id = u.id
    LEFT JOIN product_categories pc ON p.id = pc.product_id
    LEFT JOIN categories c ON pc.category_id = c.id
    LEFT JOIN product_specs ps ON p.id = ps.product_id
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
    $image_path = 'uploads/default-product.jpg'; // Assuming a default product image exists
}

// Parse optional specs
$specs = [];
if (!empty($product['specs'])) {
    $specItems = explode(',', $product['specs']);
    foreach ($specItems as $item) {
        [$key, $value] = explode(':', $item, 2);
        $specs[trim($key)] = trim($value);
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

// Mock or fetch other stats (e.g., response rate, followers - assuming fields exist)
$response_rate = 100; // Mock
$followers = 1000; // Mock
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme'] ?? 'dark'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($product['name']); ?> | Meta Shark</title>
<link rel="stylesheet" href="../../css/shop.css">
<style>
body.product-details-page {
    background: #ffffffff;
    color: #fff;
    font-family: Arial, sans-serif;
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
}
.product-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background-color: #dce0e0ff;
    padding: 1rem;
    border-radius: 8px;
    color: #000;
}
.product-info h1 {
    margin: 0;
    font-size: 2rem;
}
.price {
    font-size: 1.5rem;
    font-weight: bold;
    color: #28a745;
}
.seller-info, .stock {
    margin: 0.5rem 0;
}
.description {
    line-height: 1.6;
}
.specs {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}
.specs ul {
    margin: 0;
    padding-left: 1.5rem;
}
.add-to-cart-btn {
    background: #28a745;
    color: white;
    border: none;
    padding: 1rem 2rem;
    font-size: 1rem;
    border-radius: 4px;
    cursor: pointer;
    align-self: flex-start;
}
.add-to-cart-btn:hover {
    background: #218838;
}
.add-to-cart-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}
.seller-section {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
    background: #e0dfdfff;
    border-radius: 8px;
    padding: 1.5rem;
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
    color: #000;
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
    color: #666;
}
.stat-value {
    font-weight: bold;
    font-size: 1.1rem;
    color: #000;
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
}
.btn-seller:hover {
    background: #0056b3;
}
@media (max-width: 768px) {
    .product-detail-container {
        flex-direction: column;
    }
    .product-image {
        max-width: 100%;
    }
    .seller-header {
        flex-direction: column;
        text-align: center;
    }
    .seller-stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body class="product-details-page">

<div class="product-detail-container">
    <div class="product-image">
        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
    </div>
    <div class="product-info">
        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
        <p class="price">$<?php echo number_format($product['price'], 2); ?></p>
        <p class="seller-info">
            Sold by: 
            <a href="seller_shop.php?seller_id=<?php echo $product['seller_id']; ?>">
                <?php echo htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']); ?>
            </a>
        </p>
        <p class="stock">Stock: <?php echo $product['stock_quantity']; ?></p>
        <p class="description"><?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?></p>

        <?php if(!empty($specs)): ?>
        <div class="specs">
            <h3>Specifications:</h3>
            <ul>
                <?php foreach($specs as $key => $value): ?>
                    <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $product['seller_id']): ?>
        <form method="POST" action="shop.php" class="add-to-cart-form">
            <input type="hidden" name="add_to_cart" value="1">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <button type="submit" class="add-to-cart-btn" <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
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
        <img src="<?php echo !empty($product['profile_image']) && file_exists('uploads/' . $product['profile_image']) ? 'uploads/' . htmlspecialchars($product['profile_image']) : 'uploads/default-avatar.svg'; ?>" alt="Seller Avatar" class="seller-avatar">
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
</div>

</body>
</html>