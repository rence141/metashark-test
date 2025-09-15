<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meta Shark</title>
  <style>
    /* Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background: #f9f9f9;
      color: #333;
    }

    /* Navbar */
    .navbar {
      background: #111;
      padding: 15px 20px;
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .logo {
      height: 40px;
      width: auto;
      border-radius: 5px;
    }

    .navbar h2 {
      margin: 0;
    }

    .hamburger {
      font-size: 24px;
      cursor: pointer;
      background: none;
      border: none;
      color: white;
    }

    .menu {
      position: absolute;
      top: 60px;
      right: 20px;
      background: #222;
      list-style: none;
      padding: 15px;
      border-radius: 8px;
      display: none;
      flex-direction: column;
      gap: 10px;
    }

    .menu li {
      color: white;
      cursor: pointer;
      transition: color 0.3s;
    }

    .menu li:hover {
      color: #ff6600;
    }

    .menu.show {
      display: flex;
    }

    /* Banner */
    .banner {
      background: url("https://picsum.photos/id/1062/1200/400") center/cover no-repeat;
      height: 300px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      text-align: center;
    }

    .banner h1 {
      background: rgba(0, 0, 0, 0.5);
      padding: 20px;
      border-radius: 10px;
      font-size: 2.5rem;
    }

    /* Shop container */
    .shop-container {
      padding: 40px 20px;
      max-width: 1200px;
      margin: auto;
    }

    .shop-title {
      text-align: center;
      margin-bottom: 20px;
      font-size: 2rem;
      color: #333;
    }

    /* Search & Filter */
    .filter-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 30px;
      gap: 15px;
    }

    .search-bar input {
      padding: 10px;
      width: 250px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 1rem;
    }

    .category-filter select {
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #ccc;
      font-size: 1rem;
    }

    /* Product grid */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
    }

    .product-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      overflow: hidden;
      text-align: center;
      transition: transform 0.3s;
    }

    .product-card:hover {
      transform: translateY(-5px);
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
    }

    .product-info p {
      margin-bottom: 15px;
      color: #666;
    }

    .product-info button {
      padding: 10px 20px;
      background: #ff6600;
      border: none;
      color: white;
      font-size: 1rem;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .product-info button:hover {
      background: #e65c00;
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <div class="navbar">
    <div class="nav-left">
      <img src="uploads/logo.png" alt="SaysonCo Logo" class="logo">
      <h2>Meta Shark</h2>
    </div>
    <div class="nav-right">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php">
                <img src="uploads/<?php echo $_SESSION['profile_image']; ?>" 
                     alt="Profile" class="profile-icon">
            </a>
        <?php else: ?>
            <a href="login.html">Login</a>
            <a href="signup.html">Sign Up</a>
        <?php endif; ?>
        <button class="hamburger">☰</button>
    </div>
    <ul class="menu" id="menu">
      <li>Home</li>
      <li>Shop</li>
      <li>About</li>
      <li>Contact</li>
      <li><a href="login_users.php" style="color: white; text-decoration: none;">Login</a></li>
      <li><a href="signup_users.php" style="color: white; text-decoration: none;">Sign Up</a></li>
      <li><a href="profile.php" style="color: white; text-decoration: none;">Profile</a></li>
    </ul>
  </div>

  <!-- BANNER -->
  <div class="banner">
    <h1>Welcome to Meta</h1>
  </div>

  <!-- SHOP SECTION -->
  <div class="shop-container">
    <h2 class="shop-title">Featured Products</h2>

    <!-- Search & Filter -->
    <div class="filter-bar">
      <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search products...">
      </div>
      <div class="category-filter">
        <select id="categorySelect">
          <option value="all">All Categories</option>
          <option value="accessories">Accessories</option>
          <option value="phone">Phone</option>
          <option value="tablet">Tablet</option>
          <option value="laptop">Laptop</option>
        </select>
      </div>
    </div>

    <!-- Product Grid -->
    <div class="product-grid" id="productGrid">

      <!-- Product 1 -->
      <div class="product-card" data-category="accessories">
        <img src="https://picsum.photos/id/101/400/300" alt="Product 1">
        <div class="product-info">
          <h3>Wireless Headphones</h3>
          <p>$25.00</p>
          <button>Add to Cart</button>
        </div>
      </div>

      <!-- Product 2 -->
      <div class="product-card" data-category="phone">
        <img src="https://picsum.photos/id/102/400/300" alt="Product 2">
        <div class="product-info">
          <h3>Smartphone Pro</h3>
          <p>$499.00</p>
          <button>Add to Cart</button>
        </div>
      </div>

      <!-- Product 3 -->
      <div class="product-card" data-category="tablet">
        <img src="https://picsum.photos/id/103/400/300" alt="Product 3">
        <div class="product-info">
          <h3>Tablet X</h3>
          <p>$299.00</p>
          <button>Add to Cart</button>
        </div>
      </div>

      <!-- Product 4 -->
      <div class="product-card" data-category="laptop">
        <img src="https://picsum.photos/id/104/400/300" alt="Product 4">
        <div class="product-info">
          <h3>Laptop Ultra</h3>
          <p>$899.00</p>
          <button>Add to Cart</button>
        </div>
      </div>

      <!-- Product 5 -->
      <div class="product-card" data-category="accessories">
        <img src="https://picsum.photos/id/105/400/300" alt="Product 5">
        <div class="product-info">
          <h3>Smart Watch</h3>
          <p>$120.00</p>
          <button>Add to Cart</button>
        </div>
      </div>

    </div>
  </div>

  <script>
    // Toggle Hamburger Menu
    const hamburger = document.querySelector(".hamburger");
    const menu = document.getElementById("menu");

    hamburger.addEventListener("click", () => {
      menu.classList.toggle("show");
    });

    // Search & Filter
    const searchInput = document.getElementById("searchInput");
    const categorySelect = document.getElementById("categorySelect");
    const productCards = document.querySelectorAll(".product-card");

    function filterProducts() {
      const searchText = searchInput.value.toLowerCase();
      const category = categorySelect.value;

      productCards.forEach(card => {
        const title = card.querySelector("h3").textContent.toLowerCase();
        const matchesSearch = title.includes(searchText);
        const matchesCategory = category === "all" || card.dataset.category === category;

        card.style.display = matchesSearch && matchesCategory ? "block" : "none";
      });
    }

    searchInput.addEventListener("input", filterProducts);
    categorySelect.addEventListener("change", filterProducts);
  </script>

</body>
</html>