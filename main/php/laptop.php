<?php
session_start();
$theme = $_SESSION['theme'] ?? 'dark';
include("db.php");
include('navbar.php');

// Handle Add to Cart AJAX
if (isset($_POST['add_to_cart']) && isset($_SESSION['user_id'])) {
    $product_id = intval($_POST['product_id']);
    $user_id = $_SESSION['user_id'];

    // Check if product is already in cart
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($qty);
        $stmt->fetch();
        $new_qty = $qty + 1;
        $update = $conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
        $update->bind_param("iii", $new_qty, $user_id, $product_id);
        $update->execute();
    } else {
        $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $insert->bind_param("ii", $user_id, $product_id);
        $insert->execute();
    }

    echo json_encode(['status' => 'success']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tech Haven - Laptops</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="../../css/laptop.css">
<link rel="icon" type="image/png" href="uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>
/* Button animation when item is added */
button.added-success {
  animation: addedCart 0.7s ease forwards;
  background-color: #24e334ff !important; /* Turn green */
  color: #fff !important;
}

@keyframes addedCart {
  0% { transform: scale(1); box-shadow: 0 0 0 rgba(36, 227, 52, 0); }
  50% { transform: scale(1.1); box-shadow: 0 0 15px rgba(36, 227, 52, 0.7); }
  100% { transform: scale(1); box-shadow: 0 0 0 rgba(36, 227, 52, 0); }
}

/* Cart Notification */
.cart-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: #fff;
    font-weight: bold;
    z-index: 10000;
    opacity: 0;
    transform: translateY(-20px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.cart-notification.show {
    opacity: 1;
    transform: translateY(0);
}
</style>

</head>
<body>

<!-- Banner -->
<div class="banner">
  <div class="banner-overlay">
    <div class="banner-quick-links">
      <a href="/SaysonCotest/index.html"><i class="bi bi-house"></i></a>
      <span style="color: grey;">/</span>
      <a href="shop.php">Shop</a>
    </div>
    <h1 class="banner-title">Laptops</h1>
  </div>
</div>

<!-- Products Grid -->
<div class="container">
  <div class="product-grid">
    <?php
$category = "Laptop"; // Category name from categories table

$sql = "SELECT DISTINCT p.*, u.seller_name, u.fullname AS seller_fullname
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

        // Fetch all categories for this product
        $cat_sql = "SELECT c.name FROM categories c
                    INNER JOIN product_categories pc ON c.id = pc.category_id
                    WHERE pc.product_id = ?";
        $cat_stmt = $conn->prepare($cat_sql);
        $cat_stmt->bind_param("i", $product['id']);
        $cat_stmt->execute();
        $cat_result = $cat_stmt->get_result();
        $categories = [];
        while ($cat_row = $cat_result->fetch_assoc()) {
            $categories[] = $cat_row['name'];
        }
        $category_list = implode(", ", $categories);

        echo '<div class="product-card">';
            echo '<div class="product-image">';
                echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
            echo '</div>';
            echo '<div class="product-details">';
                echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
                echo '<p>Seller: ' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</p>';
                echo '<p class="categories">Categories: ' . htmlspecialchars($category_list) . '</p>';
                echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
                echo '<p>Stock: ' . $product['stock_quantity'] . '</p>';

                if (!isset($_SESSION['user_id'])) {
                    echo '<button onclick="alert(\'Please login to add to cart!\')">Add to Cart</button>';
                } else {
                    echo '<button class="add-to-cart-btn" data-product-id="' . $product['id'] . '" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . '>';
                    echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
                    echo '</button>';
                }

            echo '</div>';
        echo '</div>';
    }
} else {
    echo '<div style="text-align: center; padding: 40px; color: #24e334ff;">No laptops available.</div>';
}
?>

  </div>
</div>

<!-- Cart Notification -->
<div class="cart-notification" id="cartNotification"></div>

<script>
document.querySelectorAll('.add-to-cart-btn').forEach(button => {
  button.addEventListener('click', () => {
    const productId = button.dataset.productId;
    const notification = document.getElementById('cartNotification');

    fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'add_to_cart=1&product_id=' + productId
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        button.classList.add('added-success');
        setTimeout(() => button.classList.remove('added-success'), 700);

        notification.textContent = 'Added to cart!';
        notification.style.background = '#24e334';
      } else {
        notification.textContent = 'Error adding to cart!';
        notification.style.background = '#ff4d4d';
      }

      notification.classList.add('show');
      setTimeout(() => notification.classList.remove('show'), 2000);
    })
    .catch(err => {
      notification.textContent = 'Something went wrong!';
      notification.style.background = '#ff4d4d';
      notification.classList.add('show');
      setTimeout(() => notification.classList.remove('show'), 2000);
    });
  });
});
</script>

</body>
</html>
