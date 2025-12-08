<?php
session_start();
include("db.php"); // Database connection
include('navbar.php');

// Set theme preference
$theme = $_SESSION['theme'] ?? 'dark';

// Handle Add to Cart
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_to_cart"])) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $product_id = $_POST['product_id'];
        $quantity = 1;

        // Get stock
        $stock_stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stock_stmt->bind_param("i", $product_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();
        $stock = $stock_result->fetch_assoc()['stock_quantity'] ?? 0;

        if ($stock > 0) {
            // Check if product exists in cart
            $check_sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $existing_item = $check_result->fetch_assoc();
                $new_quantity = $existing_item['quantity'] + $quantity;
                if ($new_quantity > $stock) $new_quantity = $stock;

                $update_sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
                $update_stmt->execute();
            } else {
                $insert_sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
                $insert_stmt->execute();
            }

            // Return success JSON for fetch
            echo json_encode(['status' => 'success', 'message' => 'Item added to cart!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product out of stock!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
    }
    exit; // Stop further HTML output for AJAX
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tech Haven - Accessories</title>
<link rel="stylesheet" href="styles.css">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<link rel="stylesheet" href="../../css/accessories.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


</head>
<body>

<!-- Loading Screen -->
<div class="loading-screen">
  <div class="logo-container">
    <div class="logo-outline"></div>
    <div class="logo-fill"></div>
  </div>
  <div class="loading-text">Loading...</div>
</div>

<div class="banner">
  <div class="banner-overlay">
    <div class="banner-quick-links">
      <a href="/SaysonCotest/index.html"><i class="bi bi-house"></i></a>
      <span style="color: grey;">/</span>
      <a href="shop.php">Shop</a>
    </div>
    <h1 class="banner-title">Accessories</h1>
  </div>
</div>

<div class="container">
  <div class="product-grid">
  <?php
$category_name = "Accessories";

$sql = "SELECT DISTINCT p.*, u.seller_name, u.fullname AS seller_fullname
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        INNER JOIN product_categories pc ON p.id = pc.product_id
        INNER JOIN categories c ON pc.category_id = c.id
        WHERE c.name = ? AND p.is_active = TRUE
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $category_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($product = $result->fetch_assoc()) {
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
        echo '<div class="product-image"><img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '"></div>';
        echo '<div class="product-details">';
        echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
        echo '<p>Seller: ' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</p>';
        echo '<p class="categories">Categories: ' . htmlspecialchars($category_list) . '</p>';
        echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
        echo '<p>Stock: ' . $product['stock_quantity'] . '</p>';

        if (!isset($_SESSION['user_id'])) {
            echo '<button onclick="alert(\'Please login to add to cart!\')">Add to Cart</button>';
        } else {
            echo '<form method="POST" class="add-to-cart-form">';
            echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
            echo '<button type="button" class="add-to-cart-btn" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . '>';
            echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
            echo '</button>';
            echo '</form>';
        }

        echo '</div></div>';
    }
} else {
    echo '<div style="text-align:center; padding:40px; color:#24e334ff;">No accessories available.</div>';
}
?>
  </div>
</div>

<!-- Notification -->
<div class="cart-notification" id="cartNotification">Added to cart!</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Loading screen
  const loadingScreen = document.querySelector('.loading-screen');
  setTimeout(() => {
    loadingScreen.classList.add('hidden');
  }, 2000);

  // Add to Cart AJAX
  const notification = document.getElementById('cartNotification');
  document.querySelectorAll('.add-to-cart-btn').forEach(button => {
      button.addEventListener('click', () => {
          const form = button.closest('form');
          const productId = form.querySelector('input[name="product_id"]').value;

          fetch('', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'add_to_cart=1&product_id=' + productId
          })
          .then(res => res.json())
          .then(data => {
              if(data.status === 'success'){
                  notification.textContent = data.message;
                  notification.classList.add('show');
                  setTimeout(() => notification.classList.remove('show'), 3000);

                  button.classList.add('added');
                  setTimeout(() => button.classList.remove('added'), 700);
              } else {
                  notification.textContent = data.message;
                  notification.classList.add('show');
                  setTimeout(() => notification.classList.remove('show'), 3000);
              }
          });
      });
  });
});
</script>

</body>
</html>
