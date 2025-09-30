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
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <link rel="stylesheet" href="../../css/shop.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
  .navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
  }
  .chat-widget {
  position: fixed;
  bottom: 20px;
  right: 20px;
  border-radius: 50%;
  width: 70px;
  height: 70px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 12px rgba(0,0,0,0.3);
  transition: transform 0.2s ease-in-out, background 0.2s;
  z-index: 1000;
  cursor: pointer;
  text-decoration: none;
}

[data-theme="light"] .chat-widget {
  background: #5bf431ff;
  color: #353434ff;
}

[data-theme="dark"] .chat-widget {
  background: #363636ff;
  color: white;
}

.chat-widget:hover {
  transform: scale(1.1);
  background: #27ed15ff;
  color: white;
}

.chat-icon {
  font-size: 36px;
  
}

/* AI Chatbot bubble (above the main chat) */
.chat-widget.ai {
  bottom: 100px;
}

[data-theme="light"] .chat-widget.ai {
  background: #46ff2dff;
  color: #333;
}

[data-theme="dark"] .chat-widget.ai {
  background: #232323ff;
  color: white;
}

.chat-widget.ai:hover {
  background: #11df18ff;
  color: white;
}

/* AI Chatbot modal */
.ai-chat-modal {
  position: fixed;
  bottom: 90px;
  right: 20px;
  width: 800px;
  height: 600px;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 10px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.3);
  display: none;
  flex-direction: column;
  overflow: hidden;
  z-index: 1100;
  visibility: hidden; /* Extra for safety */
}

[data-theme="dark"] .ai-chat-modal {
  background: #1e1e1e;
  border: 1px solid #333;
  color: #e0e0e0;
}

.ai-chat-modal.maximized {
  bottom: 20px;
  left: 20px;
  right: 20px;
  top: 20px;
  width: auto;
  height: auto;
}

.ai-chat-modal.show {
  display: flex;
  visibility: visible;
}

/* Header */
.ai-chat-header {
  background: #3deb26ff;
  color: white;
  padding: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.ai-chat-header .header-buttons {
  display: flex;
  gap: 10px;
}
.ai-chat-header button {
  background: transparent;
  border: none;
  color: white;
  font-size: 20px;
  cursor: pointer;
}

/* Chat Container */
.ai-chat-container {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* Sidebar */
.ai-chat-sidebar {
  width: 250px;
  background: #f8f9fa;
  border-right: 1px solid #dee2e6;
  display: flex;
  flex-direction: column;
}

[data-theme="dark"] .ai-chat-sidebar {
  background: #2a2a2a;
  border-right: 1px solid #444;
}

#newChatBtn {
  padding: 10px;
  background: #007bff;
  color: white;
  border: none;
  cursor: pointer;
  font-size: 16px;
}
#newChatBtn:hover {
  background: #0056b3;
}
#chatHistoryList {
  list-style: none;
  padding: 0;
  margin: 0;
  flex: 1;
  overflow-y: auto;
}
#chatHistoryList li {
  padding: 10px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  color: #333;
}
[data-theme="dark"] #chatHistoryList li {
  color: #e0e0e0;
  border-bottom: 1px solid #444;
}
#chatHistoryList li:hover,
#chatHistoryList li.active {
  background: #e9ecef;
}
[data-theme="dark"] #chatHistoryList li:hover,
[data-theme="dark"] #chatHistoryList li.active {
  background: #333;
}

/* Main */
.ai-chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Messages */
.ai-chat-messages {
  flex: 1;
  padding: 10px;
  overflow-y: auto;
  background: #f9f9f9;
}
[data-theme="dark"] .ai-chat-messages {
  background: #2a2a2a;
}
.ai-chat-messages .message {
  margin: 8px 0;
  padding: 8px 12px;
  border-radius: 6px;
  max-width: 80%;
  word-wrap: break-word;
}
.ai-chat-messages .message.bot {
  background: #e2e6ea;
  align-self: flex-start;
  color: black;
}
[data-theme="dark"] .ai-chat-messages .message.bot {
  background: #444;
  color: #e0e0e0;
}
.ai-chat-messages .message.user {
  background: #38f61fff;
  color: white;
  align-self: flex-end;
}

/* Form */
.ai-chat-form {
  display: flex;
  border-top: 1px solid #ddd;
}
[data-theme="dark"] .ai-chat-form {
  border-top: 1px solid #444;
}
.ai-chat-form input {
  flex: 1;
  padding: 10px;
  border: none;
  outline: none;
  background: #fff;
  color: #333;
}
[data-theme="dark"] .ai-chat-form input {
  background: #333;
  color: #e0e0e0;
}
.ai-chat-form button {
  background: #48f514ff;
  color: white;
  border: none;
  padding: 0 15px;
  cursor: pointer;
}
.ai-chat-form button:hover {
  background: #13e60fff;
}
    /* Notification styles (if not in CSS) */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 10px 20px;
      border-radius: 5px;
      color: white;
      z-index: 9999;
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.3s ease;
    }
    .notification.show {
      opacity: 1;
      transform: translateX(0);
    }
    .notification.success { background: #28a745; }
    .notification.error { background: #dc3545; }
    .notification.info { background: #17a2b8; }

    /* Footer Styles */
    footer {
      background: #f8f9fa;
      border-top: 1px solid #dee2e6;
      padding: 40px 0 20px;
      color: #333;
      font-size: 14px;
    }

    [data-theme="dark"] footer {
      background: #1e1e1e;
      border-top: 1px solid #333;
      color: #e0e0e0;
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .footer-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
      gap: 20px;
    }

    .footer-logo {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .footer-logo img {
      height: 40px;
      width: auto;
    }

    .footer-logo h3 {
      margin: 0;
      color: #333;
      font-size: 24px;
    }

    [data-theme="dark"] .footer-logo h3 {
      color: #e0e0e0;
    }

    .footer-links {
      display: flex;
      gap: 30px;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .footer-links li a {
      text-decoration: none;
      color: #333;
      transition: color 0.3s;
    }

    [data-theme="dark"] .footer-links li a {
      color: #e0e0e0;
    }

    .footer-links li a:hover {
      color: #27ed15;
    }

    .footer-bottom {
      text-align: center;
      padding-top: 20px;
      border-top: 1px solid #dee2e6;
      margin-top: 20px;
    }

    [data-theme="dark"] .footer-bottom {
      border-top: 1px solid #333;
    }

    .footer-bottom p {
      margin: 0;
      color: #666;
    }

    [data-theme="dark"] .footer-bottom p {
      color: #aaa;
    }

    @media (max-width: 768px) {
      .footer-top {
        flex-direction: column;
        text-align: center;
      }

      .footer-links {
        flex-direction: column;
        gap: 10px;
      }
    }
  </style>
  <script src="https://js.puter.com/v2/"></script>
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
      if (loadingScreen && loadingScreen.classList.contains('active')) {
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
        const searchText = searchInput ? searchInput.value.toLowerCase() : '';
        const category = categorySelect ? categorySelect.value : 'all';
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
          
          // Check if user is logged in
          const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
          if (!isLoggedIn) {
            // Redirect to login page
            showNotification('Please login to add items to cart', 'info');
            setTimeout(() => {
              window.location.href = 'login_users.php';
            }, 1000);
            return;
          }
          
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
    <img src="uploads/logo1.png" alt="Meta Shark Logo" class="logo">
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
    <?php
    // Get unread notification count
    $notif_count = 0;
    if(isset($_SESSION['user_id'])) {
      include("db.php");
      $user_id = $_SESSION['user_id'];
      $notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0";
      $notif_stmt = $conn->prepare($notif_sql);
      $notif_stmt->bind_param("i", $user_id);
      $notif_stmt->execute();
      $notif_result = $notif_stmt->get_result();
      if ($notif_result->num_rows > 0) {
        $notif_data = $notif_result->fetch_assoc();
        $notif_count = $notif_data['count'];
      }
    }
    ?>
    <a href="notifications.php" title="Notifications" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
      <i class="bi bi-bell" style="font-size:18px;"></i>
      <span><?php echo $notif_count > 0 ? "($notif_count)" : ""; ?></span>
    </a>
    <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
      <i class="bi bi-cart" style="font-size:18px;"></i>
      <span>(<?php echo (int)$cart_count; ?>)</span>
    </a>
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
        <?php if(!empty($current_profile_image) && file_exists('uploads/' . $current_profile_image)): ?>
          <img src="uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
        <?php else: ?>
          <img src="uploads/default-avatar.svg" alt="Profile" class="profile-icon">
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
  <img src="uploads/logo1.png" alt="Meta Shark Logo" class="video-logo">
</div>
<!-- Features Section -->
<div class="features-section">
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
  <a href="laptop.php" class="cta-button">Learn More</a>
</section>

<!-- Categories Section -->
<div class="categories-section">
  <div class="container">
    <h2 class="section-title">Shop by Category</h2>
    <div class="categories-grid">
      <a href="phone.php" class="category-card" data-category="phones">
        <div class="category-icon"></div>
        <h3>Phones</h3>
      </a>
      <a href="Tablets.php" class="category-card" data-category="tablet">
        <div class="category-icon"></div>
        <h3>Tablets</h3>
      </a>
      <a href="accessories.php" class="category-card" data-category="accessories">
        <div class="category-icon"></div>
        <h3>Accessories</h3>
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
          echo '<div class="product-card" data-category="' . htmlspecialchars(strtolower($product['categories'])) . '" data-product-id="' . $product['id'] . '">';
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

  // Product card click handler
  productCards.forEach(card => {
    card.addEventListener('click', function(e) {
      if (e.target.tagName.toLowerCase() === 'img' || e.target.closest('.product-actions')) {
        return;
      }
      const productId = this.dataset.productId;
      if (productId) {
        window.location.href = `product-details.php?id=${productId}`;
      }
    });
  });
});
</script>

<!-- AI Chatbot Widget -->
<a href="javascript:void(0)" onclick="openAiChat()" class="chat-widget ai" title="Chat with AI">
  <i class="bi bi-robot chat-icon"></i>
</a>
<div id="aiChatModal" class="ai-chat-modal">
  <div class="ai-chat-header">
    <span>AI Chatbot</span>
    <div class="header-buttons">
      <button id="maximizeBtn" title="Maximize">□</button>
      <button onclick="closeAiChat()">×</button>
    </div>
  </div>
  <div class="ai-chat-container">
    <div class="ai-chat-sidebar">
      <button id="newChatBtn">+ New Chat</button>
      <ul id="chatHistoryList"></ul>
    </div>
    <div class="ai-chat-main">
      <div class="ai-chat-messages" id="aiChatMessages">
        <div class="message bot">Hello I'm metashark Staff how can I help you?</div>
      </div>
      <form id="aiChatForm" class="ai-chat-form">
        <input type="text" id="aiChatInput" placeholder="Type your question..." required>
        <button type="submit">Send</button>
      </form>
    </div>
  </div>
</div>
<!-- Chat Widget -->
<a href="chat.php" class="chat-widget" title="Chat with us">
  <i class="bi bi-chat-dots-fill chat-icon"></i>
</a>

<script>
let currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>; // PHP-injected user ID
let currentGroup = null;
let isMaximized = false;

function openAiChat() {
  console.log('Opening AI Chat...'); // Debug log
  const modal = document.getElementById("aiChatModal");
  if (modal) {
    modal.classList.add('show');
    console.log('Modal opened'); // Debug log
    loadChatHistory(); // Load history (non-blocking now)
  } else {
    console.error('Modal element not found!'); // Debug log
  }
}

function closeAiChat() {
  console.log('Closing AI Chat...'); // Debug log
  const modal = document.getElementById("aiChatModal");
  if (modal) {
    modal.classList.remove('show');
  }
}

// Format date
function formatDate(dateStr) {
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  return new Date(dateStr).toLocaleDateString(undefined, options);
}

// Show welcome message
function showWelcome() {
  const messagesDiv = document.getElementById("aiChatMessages");
  if (messagesDiv) {
    messagesDiv.innerHTML = '<div class="message bot">Hello I\'m metashark Staff how can I help you?</div>';
  }
}

// Display messages for a group
function displayMessages(msgs) {
  const messagesDiv = document.getElementById("aiChatMessages");
  if (messagesDiv) {
    messagesDiv.innerHTML = '';
    if (msgs.length === 0) {
      showWelcome();
    } else {
      msgs.forEach(msg => {
        const msgDiv = document.createElement("div");
        msgDiv.className = `message ${msg.role}`;
        msgDiv.textContent = (msg.role === 'ai' ? ' ' : '') + msg.message;
        messagesDiv.appendChild(msgDiv);
      });
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
  }
}

// Populate sidebar
function populateSidebar(dates, groups) {
  const list = document.getElementById("chatHistoryList");
  if (!list) return;
  list.innerHTML = '';
  dates.forEach(date => {
    const li = document.createElement("li");
    li.textContent = formatDate(date) + ` (${groups[date].length} msgs)`;
    li.dataset.date = date;
    li.onclick = (e) => {
      e.stopPropagation();
      currentGroup = date;
      displayMessages(groups[date]);
      document.querySelectorAll("#chatHistoryList li").forEach(l => l.classList.remove("active"));
      li.classList.add("active");
    };
    list.appendChild(li);
  });
  // Activate current group if exists
  const currentLi = document.querySelector(`#chatHistoryList li[data-date="${currentGroup}"]`);
  if (currentLi) {
    currentLi.classList.add("active");
  }
}

// Refresh chat (sidebar and display)
async function refreshChat() {
  if (!currentUserId) return;

  try {
    const response = await fetch("ai_chat_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "load_history", user_id: currentUserId, limit: 100 })
    });
    const data = await response.json();

    const allMessages = data.history || [];

    // Group by date (YYYY-MM-DD)
    const groups = {};
    allMessages.forEach(msg => {
      const msgDate = new Date(msg.timestamp).toISOString().split('T')[0];
      if (!groups[msgDate]) groups[msgDate] = [];
      groups[msgDate].push(msg);
    });

    const sortedDates = Object.keys(groups).sort((a, b) => new Date(b) - new Date(a)); // Newest first
    populateSidebar(sortedDates, groups);

    // Display current group messages
    if (currentGroup && groups[currentGroup]) {
      displayMessages(groups[currentGroup]);
    } else if (sortedDates.length > 0) {
      // Fallback to latest if no current
      currentGroup = sortedDates[0];
      displayMessages(groups[currentGroup]);
    } else {
      showWelcome();
    }
  } catch (error) {
    console.error("Refresh Chat Error:", error);
  }
}

// Load history from server (non-blocking)
async function loadChatHistory() {
  if (!currentUserId) {
    console.log('No user ID, skipping history load'); // Debug
    showWelcome();
    populateSidebar([], {});
    return;
  }

  try {
    const response = await fetch("ai_chat_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "load_history", user_id: currentUserId, limit: 100 })
    });
    const data = await response.json();
    console.log('History loaded:', data); // Debug

    const allMessages = data.history || [];

    if (allMessages.length === 0) {
      showWelcome();
      populateSidebar([], {});
      return;
    }

    // Group by date (YYYY-MM-DD)
    const groups = {};
    allMessages.forEach(msg => {
      const msgDate = new Date(msg.timestamp).toISOString().split('T')[0];
      if (!groups[msgDate]) groups[msgDate] = [];
      groups[msgDate].push(msg);
    });

    const sortedDates = Object.keys(groups).sort((a, b) => new Date(b) - new Date(a)); // Newest first
    populateSidebar(sortedDates, groups);

    // Check for 24h inactivity or no messages today
    const lastMsg = allMessages[allMessages.length - 1];
    const lastTime = new Date(lastMsg.timestamp).getTime();
    const now = Date.now();
    const twentyFourHours = 24 * 60 * 60 * 1000;
    const today = new Date().toISOString().split('T')[0];

    if ((now - lastTime > twentyFourHours) || !sortedDates.includes(today)) {
      // Start new chat
      currentGroup = today;
      showWelcome();
      console.log('Starting new chat due to 24h inactivity'); // Debug
    } else {
      // Load latest group
      currentGroup = sortedDates[0];
      displayMessages(groups[currentGroup]);
      console.log('Displaying recent chat history'); // Debug
    }
  } catch (error) {
    console.error("Load History Error:", error);
    // Fallback to welcome message without failing modal
    showWelcome();
    populateSidebar([], {});
  }
}

// New chat
function newChat() {
  const today = new Date().toISOString().split('T')[0];
  currentGroup = today;
  showWelcome();
  document.querySelectorAll("#chatHistoryList li").forEach(l => l.classList.remove("active"));
  refreshChat();
  console.log('New chat started');
}

// Maximize toggle
function toggleMaximize() {
  const modal = document.getElementById("aiChatModal");
  const btn = document.getElementById("maximizeBtn");
  isMaximized = !isMaximized;
  modal.classList.toggle("maximized", isMaximized);
  btn.textContent = isMaximized ? "⛶" : "□";
}

// Handle sending messages
document.addEventListener('DOMContentLoaded', function() {
  const aiChatForm = document.getElementById("aiChatForm");
  const newChatBtn = document.getElementById("newChatBtn");
  const maximizeBtn = document.getElementById("maximizeBtn");

  if (newChatBtn) {
    newChatBtn.addEventListener("click", newChat);
  }

  if (maximizeBtn) {
    maximizeBtn.addEventListener("click", toggleMaximize);
  }

  if (aiChatForm) {
    aiChatForm.addEventListener("submit", async function(e) {
      e.preventDefault();
      
      if (!currentUserId) {
        alert("Please log in to chat.");
        return;
      }

      const input = document.getElementById("aiChatInput");
      const message = input.value.trim();
      if (!message) return;

      // Set current group if not set
      if (!currentGroup) {
        currentGroup = new Date().toISOString().split('T')[0];
      }

      const messagesDiv = document.getElementById("aiChatMessages");

      // Add user message to UI
      const userMsg = document.createElement("div");
      userMsg.className = "message user";
      userMsg.textContent = message;
      messagesDiv.appendChild(userMsg);
      input.value = "";
      messagesDiv.scrollTop = messagesDiv.scrollHeight;

      // Save user msg
      await saveMessage(currentUserId, 'user', message);

      // Add temporary "thinking" message
      const botMsg = document.createElement("div");
      botMsg.className = "message bot";
      botMsg.textContent = "Thinking...";
      messagesDiv.appendChild(botMsg);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;

      try {
        // System prompt
        const systemPrompt = "You are a professional staff member of Meta Shark. Keep responses concise, informative, and relevant to shopping, gaming, or site queries. Do not use emojis.";
        const fullPrompt = `${systemPrompt}\n\nUser: ${message}`;

        // Use Puter.js for AI response (DeepSeek Chat)
        const aiResponse = await puter.ai.chat(fullPrompt, {
          model: 'deepseek-chat'
        });

        const aiContent = aiResponse.message?.content || "Sorry, I couldn’t understand that.";

        // Update UI with AI reply
        botMsg.textContent = " " + aiContent;

        // Save AI response to DB
        await saveMessage(currentUserId, 'ai', aiContent);

        // Refresh chat after messages are saved
        refreshChat();
      } catch (error) {
        botMsg.textContent = " Error connecting to AI. Please try again.";
        console.error("AI Chat Error:", error);
        // Still refresh chat
        refreshChat();
      }

      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    });
  }
});

// Save single message to server
async function saveMessage(userId, role, message) {
  try {
    await fetch("ai_chat_handler.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "save_message", user_id: userId, role: role, message: message })
    });
    console.log('Message saved:', role); // Debug
  } catch (error) {
    console.error("Save Message Error:", error);
  }
}
</script>

<footer>
  <div class="footer-content">
    <div class="footer-top">
      <div class="footer-logo">
        <img src="uploads/logo1.png" alt="Meta Shark Logo">
        <h3>Meta Shark</h3>
      </div>
      <ul class="footer-links">
        <li><a href="../../index.html">Landing Page</a></li>
        <li><a href="../../about.html">About</a></li>
        <li><a href="../../privacy_policy.html">Privacy Policy</a></li>
        <li><a href="../../terms.html">Terms of Service</a></li>
      </ul>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?php echo date("Y"); ?> Meta Shark. All rights reserved.</p>
    </div>
  </div>
</footer>

</body>
</html>