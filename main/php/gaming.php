
<?php
session_start();

// Set theme preference
$theme = $_SESSION['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tech Haven - Accessories</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  <style>
    /* Global Reset */
    *, :after, :before { box-sizing: border-box; }

    /* Body */
    body {
      background: #f8f7f7ff; /* Black background */
      color: #FFFFFF; /* White text */
      font-family: 'Orbitron', 'Arial', sans-serif;
      margin: 0;
      overflow-x: hidden;
    }

    /* Container */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Navbar */
    .navbar {
      background: #000000; /* Black background */
      padding: 15px 0;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(36, 227, 52, 0.2); /* Green-tinted shadow */
      display: flex;
      justify-content: flex-end;
      width: 100%;
    }
    .nav-links {
      list-style: none;
      display: flex;
      margin: 0;
      padding: 0;
    }
    .nav-links li {
      margin: 0 20px;
    }
    .nav-links a {
      color: #FFFFFF; /* White text */
      text-decoration: none;
      font-size: 1.1rem;
      transition: color 0.3s;
    }
    .nav-links a:hover {
      color: #24e334ff; /* Green hover */
    }

    /* Banner */
    .banner {
      position: relative;
      width: 100%;
      height: 435.19px;
      background: url('uploads/gamingbanner.png') no-repeat center;
      background-size: cover;
      margin: 0 auto;
      overflow: hidden;
    }
    .banner-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.3)); /* Black-based gradient */
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .banner-quick-links {
      padding: 10px 20px;
      display: flex;
      gap: 15px;
    }
    .banner-quick-links a {
      color: #FFFFFF; /* White text */
      text-decoration: none;
      font-size: 1.2rem;
      transition: color 0.3s;
    }
    .banner-quick-links a:hover {
      color: #24e334ff; /* Green hover */
    }
    .banner-quick-links .home-icon::before {
      content: "⌂ ";
      font-size: 1.2rem;
      vertical-align: middle;
      color: #FFFFFF;
    }
    .banner-title {
      font-size: 4.5rem;
      color: #FFFFFF; /* White text */
      text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.8);
      text-transform: uppercase;
      animation: fadeIn 2s ease-in;
      padding: 20px;
      margin: 20px;
    }

    /* Product Grid */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 30px;
      padding: 60px 20px;
      background: #f6f5f5ff; /* Black background */
    }
    .product-card {
      background: #FFFFFF; /* White card background */
      border-radius: 12px;
      overflow: hidden;
      text-align: center;
      border: 1px solid #333333; /* Subtle dark border */
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      flex-direction: column; /* Stack image and details vertically */
    }
    .product-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.4);
    }
    .product-image {
      height: 250px; /* Fixed height for consistency */
      width: 100%; /* Full width */
      overflow: hidden; /* Prevent image overflow */
    }
    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover; /* Scale image properly */
      display: block; /* Ensure no inline spacing issues */
    }
    .product-details {
      padding: 20px;
      color: #000000; /* Black text */
      flex-grow: 1; /* Allow details to take remaining space */
    }
    .product-details h3 {
      margin: 0 0 12px;
      font-size: 1.4rem;
      color: #000000; /* Black text */
    }
    .product-details p {
      margin: 8px 0;
      font-size: 0.95rem;
      color: #24e334ff; /* Green for seller and stock */
    }
    .product-details .price {
      color: #000000; /* Black for price */
      font-weight: bold;
      font-size: 1.2rem;
    }
    .product-details button {
      margin-top: 12px;
      padding: 10px 20px;
      background: #000000; /* Black background */
      border: none;
      color: #FFFFFF; /* White text */
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
      font-size: 1rem;
    }
    .product-details button:hover {
      background: #24e334ff; /* Green hover */
      transform: translateY(-2px);
    }
    .product-details button:disabled {
      background: #666666; /* Gray for disabled */
      cursor: not-allowed;
    }

    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Theme Variables */
    :root {
      --color-background: #000000; /* Black */
      --color-foreground: #FFFFFF; /* White */
      --accent: #000000;
      --accent-hover: #24e334ff; /* Green */
      --border: #333333;
      --text-muted: #24e334ff;
    }
    [data-theme="light"] {
      --color-background: #000000; /* Black */
      --color-foreground: #FFFFFF; /* White */
      --accent: #000000;
      --accent-hover: #24e334ff; /* Green */
      --border: #333333;
      --text-muted: #24e334ff;
      background: #000000;
      color: #FFFFFF;
    }
    [data-theme="light"] .banner {
      background: url('uploads/gamingbanner.png') no-repeat center;
      background-size: cover;
    }
    [data-theme="light"] .navbar {
      background: #000000; /* Black */
    }
    [data-theme="light"] .nav-links a {
      color: #FFFFFF; /* White text */
    }
    [data-theme="light"] .nav-links a:hover {
      color: #24e334ff; /* Green hover */
    }
    [data-theme="light"] .product-card {
      background: #FFFFFF; /* White card */
      color: #000000; /* Black text */
    }
    [data-theme="light"] .product-details h3 {
      color: #000000; /* Black */
    }
    [data-theme="light"] .product-details .price {
      color: #000000; /* Black */
    }

    /* Loading Screen Styles */
    .loading-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.9);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .loading-screen.active {
      opacity: 1;
      visibility: visible;
    }

    .logo-container {
      position: relative;
      width: 200px;
      height: 200px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .logo-outline {
      position: absolute;
      width: 100%;
      height: 100%;
      background-image: url('Uploads/logo1.png');
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;
      opacity: 0.5;
      animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
        opacity: 0.5;
      }
      50% {
        transform: scale(1.05);
        opacity: 0.7;
      }
    }

    .logo-fill {
      position: absolute;
      width: 100%;
      height: 100%;
      background-image: url('Uploads/logo1.png');
      background-size: contain;
      background-repeat: no-repeat;
      background-position: center;
      clip-path: inset(100% 0 0 0);
      animation: water-fill 2.5s ease-in-out infinite;
      filter: brightness(1.2) saturate(1.2);
    }

    @keyframes water-fill {
      0% {
        clip-path: inset(100% 0 0 0);
        filter: hue-rotate(0deg);
      }
      50% {
        clip-path: inset(0 0 0 0);
        filter: hue-rotate(30deg);
      }
      100% {
        clip-path: inset(100% 0 0 0);
        filter: hue-rotate(0deg);
      }
    }

    .loading-text {
      color: #44D62C;
      font-size: 24px;
      margin-top: 20px;
      font-weight: bold;
      text-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
      animation: text-wave 2.5s ease-in-out infinite;
    }

    @keyframes text-wave {
      0%, 100% {
        opacity: 0.7;
        transform: translateY(0);
      }
      50% {
        opacity: 1;
        transform: translateY(-5px);
      }
    }
  </style>
  <script>
    // Handle loading screen
    document.addEventListener('DOMContentLoaded', () => {
      const loadingScreen = document.querySelector('.loading-screen');
      // Ensure loading screen is active on page load
      loadingScreen.classList.add('active');
      // Hide loading screen after 2.5 seconds
      setTimeout(() => {
        loadingScreen.classList.remove('active');
      }, 2500);
    });
  </script>
</head>
<body>
  <!-- Loading Screen -->
  <div class="loading-screen active">
    <div class="logo-container">
      <div class="logo-outline"></div>
      <div class="logo-fill"></div>
    </div>
    <div class="loading-text">Loading...</div>
  </div>

  <nav class="navbar">
    <div class="container">
      <ul class="nav-links">
        <li><a href="/SaysonCo/index.html">Home</a></li>
        <li><a href="phone.php">Phones</a></li>
        <li><a href="Tablets.php">Tablets</a></li>
        <li><a href="accessories.php">Accessories</a></li>
        <li><a href="laptop.php">Laptops</a></li>
        <li><a href="Gaming.php">Gaming</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>
  </nav>
  <div class="banner">
    <div class="banner-overlay">
      <div class="banner-quick-links">
        <a href="/SaysonCo/index.html" class="home-icon"></a>
        <a href="shop.php">Shop</a>
      </div>
      <h1 class="banner-title">Gaming Gears</h1>
    </div>
  </div>
  <div class="container">
    <div class="product-grid">
      <?php
      include("db.php"); // Adjust path to database connection file
      $category = "Gaming";
      $sql = "SELECT p.*, u.seller_name, u.fullname as seller_fullname 
              FROM products p 
              LEFT JOIN users u ON p.seller_id = u.id 
              WHERE p.category = ? AND p.is_active = TRUE 
              ORDER BY p.created_at DESC";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("s", $category);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result && $result->num_rows > 0) {
        while ($product = $result->fetch_assoc()) {
          echo '<div class="product-card">';
          echo '<div class="product-image">';
          echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
          echo '</div>';
          echo '<div class="product-details">';
          echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
          echo '<p>Seller: ' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</p>';
          echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
          echo '<p>Stock: ' . $product['stock_quantity'] . '</p>';
          if (!isset($_SESSION['user_id'])) {
            echo '<button onclick="alert(\'Please login to add to cart!\')">Add to Cart</button>';
          } else {
            echo '<form method="POST" style="display: inline;">';
            echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
            echo '<button type="submit" name="add_to_cart" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . '>';
            echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
            echo '</button>';
            echo '</form>';
          }
          echo '</div>';
          echo '</div>';
        }
      } else {
        echo '<div style="text-align: center; padding: 40px; color: #24e334ff;">No accessories available.</div>';
      }
      ?>
    </div>
  </div>
</body>
</html>
