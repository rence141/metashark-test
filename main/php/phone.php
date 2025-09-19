
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
  <title>Meta Shark - Phones</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  <style>
    /* Global Reset */
    *, :after, :before { box-sizing: border-box; }

    /* Body */
    body {
      background: #faf4f4ff; /* Black background */
      color: #FFFFFF; /* White text for contrast */
      font-family: 'ASUS ROG', Arial, sans-serif;
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
      justify-content: center;
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
      background: url('Uploads/BS5_black3.jpeg') no-repeat center;
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
      gap: 20px;
      padding: 40px 20px;
      background: #f8f5f5ff; /* Black background */
    }
    .product-card {
      background: #FFFFFF; /* White background for contrast */
      border-radius: 10px;
      overflow: hidden;
      text-align: center;
      border: 1px solid #333333; /* Darker border */
      transition: all 0.3s ease;
    }
    .product-card img {
      width: 100%;
      height: 250px;
      object-fit: cover;
    }
    .product-info {
      padding: 15px;
    }
    .product-info h3 {
      margin-bottom: 10px;
      font-size: 1.2rem;
      color: #000000; /* Black text */
    }
    .product-info p.seller-info {
      color: #050505ff; /* Green for seller info */
      font-size: 0.9rem;
      margin-bottom: 5px;
    }
    .product-info p.price {
      color: #000000; /* Black text */
      font-weight: bold;
      font-size: 1.2rem;
    }
    .product-info p.stock {
      color: #24e334ff; /* Green for stock info */
      font-size: 0.9rem;
    }
    .product-info button {
      padding: 10px 20px;
      background: #000000; /* Black background */
      border: none;
      color: #FFFFFF; /* White text */
      font-size: 1rem;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
    }
    .product-info button:hover {
      background: #24e334ff; /* Green hover */
      transform: translateY(-2px);
    }
    .product-info button:disabled {
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
      --gradient-background: linear-gradient(#000000, #000000); /* Solid black */
      --page-padding: 20px;
      --border-radius: 10px;
      --dark-bg-primary: #000000;
      --dark-bg-secondary: #000000;
      --dark-bg-tertiary: #FFFFFF;
      --dark-text-primary: #FFFFFF;
      --bg-primary: #000000;
      --bg-tertiary: #FFFFFF;
      --border: #333333;
      --accent: #000000;
      --accent-hover: #24e334ff; /* Green hover */
      --text-muted: #24e334ff;
      --dark-border: #333333;
      --dark-text-muted: #24e334ff;
    }
    [data-theme="light"] {
      --color-background: #ede2e2ff; /* Black */
      --color-foreground: #FFFFFF; /* White */
      --gradient-background: linear-gradient(#000000, #000000);
      --light-bg-primary: #000000;
      --light-bg-secondary: #000000;
      --light-bg-tertiary: #FFFFFF;
      --light-text-primary: #FFFFFF;
      --bg-primary: #000000;
      --bg-tertiary: #FFFFFF;
      --border: #333333;
      --accent: #000000;
      --accent-hover: #24e334ff; /* Green hover */
      --text-muted: #24e334ff;
      --light-border: #333333;
      --light-text-muted: #24e334ff;
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
      <h1 class="banner-title">Phones</h1>
    </div>
  </div>
  <div class="container">
    <div class="product-grid">
      <?php
      include("db.php"); // Adjust path to database connection file
      $category = "phone";
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
          echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
          echo '<div class="product-info">';
          echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
          echo '<p class="seller-info">Sold by: ' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</p>';
          echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
          echo '<p class="stock">Stock: ' . $product['stock_quantity'] . '</p>';
          if (!isset($_SESSION['user_id'])) {
            echo '<button onclick="alert(\'Please login to add items to cart!\')">Add to Cart</button>';
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
        echo '<div style="text-align: center; padding: 40px; color: #24e334ff;">No phones available.</div>';
      }
      ?>
    </div>
  </div>
</body>
</html>
