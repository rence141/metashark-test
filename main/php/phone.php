<?php
session_start();
$theme = $_SESSION['theme'] ?? 'dark';

include("db.php"); // Your DB connection

// Handle Add to Cart AJAX request
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
  <title>Meta Shark - Phones</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <link rel="stylesheet" href="../../css/phones.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>
<body>
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
      <a href="/SaysonCo/index.html"><i class="bi bi-house"></i></a>
      <span style="color:grey;">/</span>
      <a href="shop.php">Shop</a>
    </div>
    <h1 class="banner-title">Phones</h1>
  </div>
</div>


  <div class="container">
    <div class="product-grid">
      <?php
      $category = "Phone"; // Must match the name in your categories table

      // Fetch products for the category
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
              echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
              echo '<div class="product-info">';
              echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
              echo '<p class="seller-info">Sold by: ' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</p>';
              echo '<p class="categories">Categories: ' . htmlspecialchars($category_list) . '</p>';
              echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
              echo '<p class="stock">Stock: ' . $product['stock_quantity'] . '</p>';

              if (!isset($_SESSION['user_id'])) {
                  echo '<button onclick="alert(\'Please login to add items to cart!\')">Add to Cart</button>';
              } else {
                  echo '<button class="add-to-cart-btn" data-product-id="' . $product['id'] . '" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . '>';
                  echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
                  echo '</button>';
              }

              echo '</div>';
              echo '</div>';
          }
      } else {
          echo '<div style="text-align: center; padding: 40px; color: #24e334ff;">No phones available.</div>';
      }
      ?>
    </div>
  </div>

  <div class="cart-notification" id="cartNotification">Added to cart!</div>

  <script>
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
      button.addEventListener('click', () => {
        const productId = button.dataset.productId;
        fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'add_to_cart=1&product_id=' + productId
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            button.classList.add('added');
            const notification = document.getElementById('cartNotification');
            notification.classList.add('show');
            setTimeout(() => { notification.classList.remove('show'); }, 2000);
            setTimeout(() => { button.classList.remove('added'); }, 700);
          }
        });
      });
    });
  </script>
</body>
</html>
