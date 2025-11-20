<?php
session_start();
$theme = $_SESSION['theme'] ?? 'dark';
include("db.php"); // Adjust path to your DB connection

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle Add to Cart
if (isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]++;
    } else {
        $_SESSION['cart'][$product_id] = 1;
    }

    $_SESSION['cart_message'] = "Item added to cart!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meta Shark - Gaming</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <link rel="stylesheet" href="../../css/gaming.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    .cart-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #24e334ff;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }
    .cart-notification.show {
        opacity: 1;
        visibility: visible;
    }
    button.added {
        animation: addedCart 0.7s ease forwards;
        background-color: #24e334ff !important;
        color: #fff !important;
    }
    @keyframes addedCart {
      0% { transform: scale(1); box-shadow: 0 0 0 rgba(36, 227, 52, 0); }
      50% { transform: scale(1.1); box-shadow: 0 0 15px rgba(36, 227, 52, 0.7); }
      100% { transform: scale(1); box-shadow: 0 0 0 rgba(36, 227, 52, 0); }
    }
  </style>
</head>
<body>

<?php if (isset($_SESSION['cart_message'])): ?>
<div class="cart-notification show">
    <?php 
        echo htmlspecialchars($_SESSION['cart_message']); 
        unset($_SESSION['cart_message']);
    ?>
</div>
<?php endif; ?>

<!-- Navbar -->
<nav class="navbar">
  <div class="container">
    <ul class="nav-links">
      <li><a href="../../index.html">Home</a></li>
      <li><a href="phone.php">Phones</a></li>
      <li><a href="Tablets.php">Tablets</a></li>
      <li><a href="accessories.php">Accessories</a></li>
      <li><a href="laptop.php">Laptops</a></li>
      <li><a href="gaming.php">Gaming</a></li>
    </ul>
  </div>
</nav>

<!-- Banner -->
<div class="banner">
  <div class="banner-overlay">
    <div class="banner-quick-links">
      <a href="/SaysonCotest/index.html"><i class="bi bi-house"></i></a>
      <span style="color: grey;">/</span>
      <a href="shop.php">Shop</a>
    </div>
    <h1 class="banner-title">Gaming Gears</h1>
  </div>
</div>

<!-- Products -->
<div class="container">
  <div class="product-grid">
    <?php
    // Categories you want to show on this page
    $category = 'Gaming'; // Only show products tagged as Gaming

$sql = "SELECT DISTINCT p.*, u.seller_name, u.fullname as seller_fullname
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        INNER JOIN product_categories pc ON p.id = pc.product_id
        INNER JOIN categories c ON pc.category_id = c.id
        WHERE c.name = ? AND p.is_active = TRUE
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $category);
$stmt->execute();
$result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($product = $result->fetch_assoc()) {
            // Get all categories for this product
            $cat_sql = "SELECT c.name FROM categories c
                        INNER JOIN product_categories pc ON c.id = pc.category_id
                        WHERE pc.product_id = ?";
            $cat_stmt = $conn->prepare($cat_sql);
            $cat_stmt->bind_param("i", $product['id']);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            $product_categories = [];
            while ($cat_row = $cat_result->fetch_assoc()) {
                $product_categories[] = $cat_row['name'];
            }
            $category_list = implode(", ", $product_categories);

            echo '<div class="product-card">';
            echo '<div class="product-image"><img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '"></div>';
            echo '<div class="product-details">';
            echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
            echo '<p>Seller: ' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</p>';
            echo '<p>Categories: ' . htmlspecialchars($category_list) . '</p>';
            echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
            echo '<p>Stock: ' . $product['stock_quantity'] . '</p>';

            // Add to Cart button
            echo '<form method="POST">';
            echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
            echo '<button type="submit" name="add_to_cart" class="add-to-cart"' . ($product['stock_quantity'] <= 0 ? ' disabled' : '') . '>';
            echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
            echo '</button>';
            echo '</form>';

            echo '</div></div>';
        }
    } else {
        echo '<div style="text-align:center; padding:40px; color:#24e334ff;">No products available.</div>';
    }
    ?>
  </div>
</div>

<script>
document.querySelectorAll('.add-to-cart').forEach(button => {
  button.addEventListener('click', e => {
      button.classList.add('added');
      setTimeout(() => button.classList.remove('added'), 700);
      const notification = document.querySelector('.cart-notification');
      if(notification) {
          notification.classList.add('show');
          setTimeout(() => notification.classList.remove('show'), 3000);
      }
  });
});
</script>

</body>
</html>
