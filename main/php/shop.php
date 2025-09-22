<?php
session_start();
// Check if user just logged in
$just_logged_in = isset($_SESSION['login_success']) && $_SESSION['login_success'] === true;
// Clear the login success flag
if ($just_logged_in) {
    $_SESSION['login_success'] = false;
}
// Set theme preference
$theme = $_SESSION['theme'] ?? 'dark';
// Handle theme toggle
if (isset($_GET['theme'])) {
    $new_theme = $_GET['theme'] === 'light' ? 'light' : 'dark';
    $_SESSION['theme'] = $new_theme;
    $theme = $new_theme;
}
// Verify user session is valid
if (isset($_SESSION['user_id'])) {
    include("db.php");
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
   
    if ($result->num_rows === 0) {
        session_unset();
        session_destroy();
        header("Location: login_users.php");
        exit();
    }
}
// Handle add to cart
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_to_cart"])) {
    if (isset($_SESSION['user_id'])) {
        include("db.php");
        $user_id = $_SESSION['user_id'];
        $product_id = $_POST['product_id'];
        $quantity = 1;
       
        $check_sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
       
        if ($check_result->num_rows > 0) {
            $existing_item = $check_result->fetch_assoc();
            $new_quantity = $existing_item['quantity'] + $quantity;
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
       
        $cart_message = "Item added to cart!";
        $cart_success = true;
    } else {
        header("Location: login_users.php");
        exit();
    }
}
// Get cart count for display
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    include("db.php");
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
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meta Shark</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  <link rel="stylesheet" href="../../css/shop.css">
<script>
document.addEventListener('DOMContentLoaded', function() {
  const carousel = document.querySelector('.carousel-slides');
  const prevBtn = document.querySelector('.carousel-prev');
  const nextBtn = document.querySelector('.carousel-next');
  let currentIndex = 0;
  const slides = document.querySelectorAll('.carousel-slide');
  const totalSlides = slides.length;

  function showSlide(index) {
    if (index >= totalSlides) {
      currentIndex = 0;
    } else if (index < 0) {
      currentIndex = totalSlides - 1;
    } else {
      currentIndex = index;
    }
    carousel.style.transform = `translateX(-${currentIndex * 100}%)`;
  }

  prevBtn.addEventListener('click', () => {
    showSlide(currentIndex - 1);
  });

  nextBtn.addEventListener('click', () => {
    showSlide(currentIndex + 1);
  });

  // Optional: Auto-slide every 5 seconds
  setInterval(() => {
    showSlide(currentIndex + 1);
  }, 5000);
});

document.addEventListener('DOMContentLoaded', function() {
  // Theme toggle functionality
  const theme = '<?php echo $theme; ?>';
  document.documentElement.setAttribute('data-theme', theme);
  updateThemeToggleButton();
  // Handle loading screen
  const loadingScreen = document.querySelector('.loading-screen');
  if (loadingScreen.classList.contains('active')) {
    setTimeout(() => {
      loadingScreen.classList.remove('active');
    }, 2000);
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
  // Search & Filter
  const searchInput = document.getElementById('searchInput');
  const categorySelect = document.getElementById('categorySelect');
  const productCards = document.querySelectorAll('.product-card');
  function filterProducts() {
    const searchText = searchInput.value.toLowerCase();
    const category = categorySelect.value;
    productCards.forEach(card => {
      const title = card.querySelector('h3').textContent.toLowerCase();
      const matchesSearch = title.includes(searchText);
      const matchesCategory = category === 'all' || card.dataset.category === category;
      card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
    });
  }
  if (searchInput) {
    searchInput.addEventListener('input', filterProducts);
  }
  if (categorySelect) {
    categorySelect.addEventListener('change', filterProducts);
  }
  // Category card click functionality
  const categoryCards = document.querySelectorAll('.category-card');
  categoryCards.forEach(card => {
    card.addEventListener('click', function(e) {
      e.preventDefault();
      const category = this.dataset.category;
      if (categorySelect) {
        categorySelect.value = category;
        filterProducts();
        window.location.href = this.href;
      }
    });
  });
  // Product Popup Advertisement
  const productPopup = document.getElementById('productPopup');
  const popupOverlay = document.getElementById('popupOverlay');
  const popupClose = document.getElementById('popupClose');
  const popupImage = document.getElementById('popupImage');
  const popupName = document.getElementById('popupName');
  const popupPrice = document.getElementById('popupPrice');
  const popupDescription = document.getElementById('popupDescription');
  const popupViewBtn = document.getElementById('popupViewBtn');
  const popupAddBtn = document.getElementById('popupAddBtn');
  function getAllProducts() {
    const products = [];
    productCards.forEach(card => {
      const product = {
        name: card.querySelector('h3').textContent,
        price: card.querySelector('.price').textContent,
        image: card.querySelector('img').src,
        category: card.dataset.category,
        productId: card.querySelector('.add-to-cart-form') ?
                  card.querySelector('.add-to-cart-form').dataset.productId : null
      };
      products.push(product);
    });
    return products;
  }
  function getRandomProduct() {
    const products = getAllProducts();
    if (products.length === 0) return null;
    const randomIndex = Math.floor(Math.random() * products.length);
    return products[randomIndex];
  }
  function showProductPopup(product) {
    if (!product) return;
    popupImage.src = product.image;
    popupName.textContent = product.name;
    popupPrice.textContent = product.price;
    popupViewBtn.onclick = function() {
      productCards.forEach(card => {
        if (card.querySelector('h3').textContent === product.name) {
          card.scrollIntoView({ behavior: 'smooth' });
          closePopup();
        }
      });
    };
    popupAddBtn.onclick = function() {
      if (product.productId) {
        const form = document.querySelector(`.add-to-cart-form[data-product-id="${product.productId}"]`);
        if (form) {
          form.submit();
          closePopup();
        } else {
          alert('Please login to add items to cart!');
          closePopup();
        }
      } else {
        alert('Please login to add items to cart!');
        closePopup();
      }
    };
    popupOverlay.classList.add('show');
    productPopup.classList.add('show');
  }
  function closePopup() {
    popupOverlay.classList.remove('show');
    productPopup.classList.remove('show');
  }
  if (popupClose) {
    popupClose.addEventListener('click', closePopup);
  }
  if (popupOverlay) {
    popupOverlay.addEventListener('click', closePopup);
  }
  function showRandomProductPopup() {
    const randomProduct = getRandomProduct();
    if (randomProduct) {
      const lastPopupTime = sessionStorage.getItem('lastPopupTime');
      const currentTime = new Date().getTime();
      if (!lastPopupTime || (currentTime - parseInt(lastPopupTime)) > 30 * 60 * 1000) {
        setTimeout(() => {
          showProductPopup(randomProduct);
          sessionStorage.setItem('lastPopupTime', currentTime.toString());
        }, 3000);
      }
    }
  }
  <?php if (isset($just_logged_in) && $just_logged_in): ?>
    setTimeout(() => {
      showRandomProductPopup();
    }, 3500);
  <?php else: ?>
    showRandomProductPopup();
  <?php endif; ?>
  // Enhanced Add to Cart functionality
  const addToCartForms = document.querySelectorAll('.add-to-cart-form');
  addToCartForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const button = form.querySelector('.add-to-cart-btn');
      const productName = button.getAttribute('data-product-name');
      button.classList.add('loading');
      button.disabled = true;
      showNotification(`Adding ${productName} to cart...`, 'info');
      // Submit form via AJAX
      const formData = new FormData(form);
      fetch(form.action, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(() => {
        button.classList.remove('loading');
        button.disabled = false;
        showNotification(`${productName} added to cart!`, 'success');
        // Update cart count
        const cartCount = document.getElementById('cartCount');
        if (cartCount) {
          const currentCount = parseInt(cartCount.textContent) || 0;
          cartCount.textContent = currentCount + 1;
          cartCount.classList.add('updated');
          setTimeout(() => {
            cartCount.classList.remove('updated');
          }, 600);
        }
      })
      .catch(() => {
        button.classList.remove('loading');
        button.disabled = false;
        showNotification('Failed to add to cart. Please try again.', 'error');
      });
    });
  });
  // Notification function
  function showNotification(message, type = 'success') {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notif => notif.remove());
    const notification = document.createElement('div');
    notification.className = `notification ${type} show`;
    notification.innerHTML = `${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'} ${message}`;
    document.body.appendChild(notification);
    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 3000);
  }
  // Auto-hide existing notification
  const notification = document.getElementById('cartNotification');
  if (notification) {
    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 3000);
  }
  // Theme toggle functionality
  function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', newTheme);
    updateThemeToggleButton();
    fetch(`?theme=${newTheme}`);
  }
  function updateThemeToggleButton() {
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    const currentTheme = document.documentElement.getAttribute('data-theme');
    if (themeIcon && themeText) {
      themeIcon.textContent = currentTheme === 'light' ? '🌙' : '☀️';
      themeText.textContent = currentTheme === 'light' ? 'Dark' : 'Light';
    }
  }
});
</script>
</head>
<body>
<!-- Loading Screen -->
<div class="loading-screen<?php echo $just_logged_in ? ' active' : ''; ?>">
  <div class="logo-container">
    <div class="logo-outline"></div>
    <div class="logo-fill"></div>
  </div>
  <div class="loading-text">Loading...</div>
</div>
<!-- Product Popup Advertisement -->
<div class="popup-overlay" id="popupOverlay"></div>
<div class="product-popup" id="productPopup">
  <div class="popup-header">
    <h3>Featured Product</h3>
    <button class="popup-close" id="popupClose">&times;</button>
  </div>
  <div class="popup-content">
    <img src="" alt="Product Image" class="popup-image" id="popupImage">
    <div class="popup-product-info">
      <h4 class="popup-product-name" id="popupName"></h4>
      <div class="popup-product-price" id="popupPrice"></div>
      <p class="popup-product-description" id="popupDescription">Check out this amazing product from our collection!</p>
      <div class="popup-actions">
        <button class="popup-view-btn" id="popupViewBtn">View Details</button>
        <button class="popup-add-btn" id="popupAddBtn">Add to Cart</button>
      </div>
    </div>
  </div>
</div>
<!-- Navbar -->
<div class="navbar">
  <div class="nav-left">
    <img src="Uploads/logo1.png" alt="SaysonCo Logo" class="logo">
    <h2>Meta Shark</h2>
    <div class="theme-toggle" id="themeToggle">
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle Theme">
        <span class="theme-icon" id="themeIcon">
            <?php echo $theme === 'light' ? '🌙' : '☀️'; ?>
        </span>
        <span class="theme-text" id="themeText">
            <?php echo $theme === 'light' ? 'Dark' : 'Light'; ?>
        </span>
    </button>
</div>
    <?php include('theme_toggle.php'); ?>
  </div>
  <div class="nav-right">
    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
      <?php
      $user_role = $_SESSION['role'] ?? 'buyer';
      $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
      $current_user_id = $_SESSION['user_id'];
      $profile_query = "SELECT profile_image FROM users WHERE id = ?";
      $profile_stmt = $conn->prepare($profile_query);
      $profile_stmt->bind_param("i", $current_user_id);
      $profile_stmt->execute();
      $profile_result = $profile_stmt->get_result();
      $current_profile = $profile_result->fetch_assoc();
      $current_profile_image = $current_profile['profile_image'] ?? null;
      ?>
      <a href="<?php echo $profile_page; ?>">
        <?php if(!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
          <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
        <?php else: ?>
          <img src="Uploads/default-avatar.svg" alt="Profile" class="profile-icon">
        <?php endif; ?>
      </a>
    <?php else: ?>
      <a href="login_users.php">
        <div class="nonuser-text">Login</div>
      </a>
      <a href="signup_users.php">
        <div class="nonuser-text">Signup</div>
      </a>
      <a href="login_users.php">
        <div class="profile-icon">👤</div>
      </a>
    <?php endif; ?>
    <button class="hamburger">☰</button>
  </div>
  <ul class="menu " id="menu">
    <li><a href="shop.php">Home</a></li>
    <li><a href="carts_users.php">Cart (<span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>)</a></li>
    <?php if(isset($_SESSION['user_id'])): ?>
      <?php
      $user_role = $_SESSION['role'] ?? 'buyer';
      ?>
      <?php if($user_role === 'seller' || $user_role === 'admin'): ?>
        <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
      <?php else: ?>
        <li><a href="become_seller.php">Become Seller</a></li>
      <?php endif; ?>
      <li><a href="<?php echo $profile_page; ?>">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    <?php endif; ?>
  </ul>
</div>
<!-- Notification -->
<?php if (isset($cart_success) && $cart_success): ?>
  <div class="notification success show" id="cartNotification">
    ✅ <?php echo $cart_message; ?>
  </div>
<?php endif; ?>
<!-- Video Banner -->
<div class="banner video-banner">
  <video autoplay muted loop playsinline preload="auto">
    <source src="../../mp4/bateo.mp4" type="video/mp4">
    Your browser does not support the video tag.
  </video>
    <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="video-logo">
  </a>
</div>
<!-- Features Section -->
</div><div class="features-section">
  <div class="container">
    <h2 class="section-title">Why Choose Meta Shark?</h2>
    <div class="features-grid">
      <div class="feature-card-1">
        <div class="feature-icon"></div>
        <h3>Easy Shopping</h3>
        <p>Browse and buy from multiple sellers in one place</p>
      </div>
      <div class="feature-card-2">
        <div class="feature-icon"></div>
        <h3>Secure Payments</h3>
        <p>Safe and secure checkout process</p>
      </div>
      <div class="feature-card-3">
        <div class="feature-icon"></div>
        <h3>Fast Delivery</h3>
        <p>Quick shipping from trusted sellers</p>
      </div>
      <div class="feature-card-4">
        <div class="feature-icon"></div>
        <h3>Quality Products</h3>
        <p>Verified sellers with quality products</p>
      </div>
    </div>
  </div>
</div>
<!-- Product Sections -->
<section class="product bg1">
  <h2>Unleash Your Capabilities</h2>
  <p>Guaranteed 0% Interest.</p>
  <a href="shop.php" class="cta-button">Learn More</a>
</section>

<!-- Categories Section -->
<div class="categories-section">
  <div class="container">
    <h2 class="section-title">Shop by Category</h2>
    <div class="categories-grid">
      <a href="accessories.php" class="category-card" data-category="accessories">
        <div class="category-icon"></div>
        <h3>Accessories</h3>
      </a>
      <a href="phone.php" class="category-card" data-category="phones">
        <div class="category-icon"></div>
        <h3>Phones</h3>
      </a>
      <a href="Tablets.php" class="category-card" data-category="tablet">
        <div class="category-icon"></div>
        <h3>Tablets</h3>
      </a>
      <a href="laptop.php" class="category-card" data-category="laptop">
        <div class="category-icon"></div>
        <h3>Laptops</h3>
      </a>
      <a href="gaming.php" class="category-card" data-category="gaming">
        <div class="category-icon"></div>
        <h3>Gaming</h3>
      </a>
    </div>
  </div>
</div>

<section class="product bg2">
  <div class="carousel-container">
    <div class="carousel-slides">
      <div class="carousel-slide"></div>
      <div class="carousel-slide"></div>
      <div class="carousel-slide"></div>
        <div class="carousel-slide"></div>
    </div>
    <div class="carousel-nav">
      <button class="carousel-prev">&#10094;</button>
      <button class="carousel-next">&#10095;</button>
    </div>
  </div>
</section>

<!-- category section -->
<div class="shop-container">
  <h2 class="shop-title">Featured Products</h2>
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
        <option value="gaming">Gaming</option>
      </select>
    </div>
  </div>
  <div class="product-grid" id="productGrid">
    <?php
    include("db.php");

    $table_check = $conn->query("SHOW TABLES LIKE 'products'");
    if ($table_check && $table_check->num_rows > 0) {
      $products_sql = "
        SELECT p.id, p.name, p.image, p.price, p.stock_quantity, p.seller_id,
               u.seller_name, u.fullname AS seller_fullname,
               GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ',') AS categories
        FROM products p
        LEFT JOIN users u ON p.seller_id = u.id
        LEFT JOIN product_categories pc ON p.id = pc.product_id
        LEFT JOIN categories c ON pc.category_id = c.id
        WHERE p.is_active = TRUE
        GROUP BY p.id, p.name, p.image, p.price, p.stock_quantity, p.seller_id, u.seller_name, u.fullname
        ORDER BY p.created_at DESC
      ";
      $products_result = $conn->query($products_sql);

      if ($products_result && $products_result->num_rows > 0) {
        while ($product = $products_result->fetch_assoc()) {
          echo '<div class="product-card" data-category="' . htmlspecialchars(strtolower($product['categories'])) . '">';
          echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
          echo '<div class="product-info">';
          echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
          echo '<p class="seller-info">Sold by: <a href="seller_shop.php?seller_id=' . $product['seller_id'] . '">' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</a></p>';
          echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
          echo '<p class="stock">Stock: ' . $product['stock_quantity'] . '</p>';
          echo '<div class="product-actions">';

          if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $product['seller_id']) {
            echo '<form method="POST" class="add-to-cart-form" data-product-id="' . $product['id'] . '">';
            echo '<input type="hidden" name="add_to_cart" value="1">';
            echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
            echo '<button type="submit" class="add-to-cart-btn" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . ' data-product-name="' . htmlspecialchars($product['name']) . '">';
            echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
            echo '</button>';
            echo '</form>';
          } else {
            echo '<a href="edit_product.php?id=' . $product['id'] . '" class="btn-edit">Edit</a>';
            echo '<a href="seller_dashboard.php" class="btn-manage">Manage</a>';
          }

          echo '</div>'; // product-actions
          echo '</div>'; // product-info
          echo '</div>'; // product-card
        }
      } else {
        echo '<div class="empty-products">';
        echo '<h3>No Products Available</h3>';
        echo '<p>There are currently no products listed in the marketplace.</p>';
        echo '</div>';
      }
    } else {
      echo '<div class="empty-products">';
      echo '<h3>Database Setup Required</h3>';
      echo '<p>Please set up the database tables first to start using the marketplace.</p>';
      echo '</div>';
    }
    ?>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  const categorySelect = document.getElementById("categorySelect");
  const productCards = document.querySelectorAll(".product-card");

  function filterProducts() {
    const searchText = searchInput ? searchInput.value.toLowerCase() : "";
    const category = categorySelect ? categorySelect.value.toLowerCase() : "all";

    productCards.forEach(card => {
      const title = card.querySelector("h3").textContent.toLowerCase();
      const categories = card.dataset.category
        .toLowerCase()
        .split(",")
        .map(c => c.trim());

      const matchesSearch = title.includes(searchText);
      const matchesCategory = category === "all" || categories.includes(category);

      card.style.display = matchesSearch && matchesCategory ? "block" : "none";
    });
  }

  if (searchInput) searchInput.addEventListener("input", filterProducts);
  if (categorySelect) categorySelect.addEventListener("change", filterProducts);
});
</script>


  </div>
</div>

<footer>
    <div class="footer-bottom">
      <p>&copy; <?php echo date("Y"); ?> Meta Shark. All rights reserved.</p>
    </div>
  </div>
</footer>

</body>
</html>
