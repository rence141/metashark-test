<?php
session_start();
// Debug: Log session data to verify variables
error_log("Shop.php session data: " . print_r($_SESSION, true));

// Check if user just logged in
$just_logged_in = isset($_SESSION['login_success']) && $_SESSION['login_success'] === true;
// Clear the login success flag
if ($just_logged_in) {
    $_SESSION['login_success'] = false;
}

// Set theme preference
if (isset($_GET['theme'])) {
    $new_theme = in_array($_GET['theme'], ['light', 'dark', 'device']) ? $_GET['theme'] : 'device';
    $_SESSION['theme'] = $new_theme;
} else {
    $theme = $_SESSION['theme'] ?? 'device'; // Default to 'device' if no theme is set
}

// Determine the effective theme for rendering
$effective_theme = $theme;
if ($theme === 'device') {
    $effective_theme = 'dark'; // Fallback; client-side JS will override based on prefers-color-scheme
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
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meta Shark</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="Uploads/logo1.png">
  <link rel="stylesheet" href="../../css/shop.css">
  <link rel="stylesheet" href="../../css/ai_chat.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  
  <style>
    :root {
      --background: #fff;
      --text-color: #333;
      --primary-color: #00ff88;
      --secondary-bg: #f8f9fa;
      --border-color: #dee2e6;
      --theme-menu: black;
      --theme-btn: black;
    }

    [data-theme="dark"] {
      --background: #000000ff;
      --text-color: #e0e0e0;
      --primary-color: #00ff88;
      --secondary-bg: #2a2a2a;
      --border-color: #444;
      --theme-menu: white;
      --theme-btn: white;
    }


    body {
      background: var(--background);
      color: var(--text-color);
    }

    /* Device select styling to match login button look */
    .login-btn-select, #deviceSelect {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background: #000;
      color: #fff;
      border: 2px solid #00ff88;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      min-width: 160px;
      transition: all 0.3s ease;
    }
    .login-btn-select:hover, #deviceSelect:hover {
      background: #00ff88;
      color: #000;
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(1, 235, 28, 0.12);
    }
    .device-toggle { position: relative; display: inline-block; }
    .device-toggle:after {
      content: '\25BC';
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      color: #fff;
    }

    .ai-input-wrapper {
  width: 100%; /* Stretch to full parent width */
  max-width: 600px; /* Default cap for standard screens */
  margin: 10px auto; /* Center horizontally */
  padding: 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(68, 214, 44, 0.3);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.ai-input-wrapper:hover {
  border-color: rgba(68, 214, 44, 0.5);
  box-shadow: 0 6px 16px rgba(68, 214, 44, 0.3);
  transform: translateY(-2px);
}

.ai-input-wrapper:focus-within {
  border-color: #44D62C;
  box-shadow: 0 0 0 4px rgba(68, 214, 44, 0.2);
}

.ai-input-wrapper::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(68, 214, 44, 0.2), transparent);
  transition: transform 0.3s ease-in-out;
  z-index: -1;
}

.ai-input-wrapper:hover::before,
.ai-input-wrapper:focus-within::before {
  transform: translateX(100%);
}

/* Stretch further on larger screens */
@media (min-width: 768px) {
  .ai-input-wrapper {
    max-width: 800px; /* Stretch further on larger screens */
  }
}

/* Stretch even more when maximized */
@media (min-width: 1200px) {
  .ai-input-wrapper {
    max-width: 1000px; /* Maximum stretch for large, maximized screens */
  }
}

/* Responsive adjustments for smaller screens */
@media (max-width: 480px) {
  .ai-input-wrapper {
    padding: 8px;
    border-radius: 10px;
    max-width: 100%; /* Full width on small screens */
  }
}



    .filter-bar .device-toggle .login-btn-select,
    .filter-bar .device-toggle .device-toggle-btn {
      background: #000 !important;
      color: #fff !important;
      border: 2px solid #00ff88 !important;
      padding: 12px 16px !important;
      border-radius: 12px !important;
      font-size: 16px !important;
      font-weight: 600 !important;
      min-width: 160px !important;
    }

    .filter-bar .device-toggle .login-btn-select:hover,
    .filter-bar .device-toggle .device-toggle-btn:hover {
      background: #00ff88 !important;
      color: #000 !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 10px 20px rgba(1, 235, 28, 0.12) !important;
    }

    .filter-bar .device-toggle .device-menu {
      background: rgba(255,255,255,0.95) !important;
      border: 2px solid rgba(0,255,136,0.3) !important;
      z-index: 2000 !important;
    }

    .device-toggle { position: relative; display: inline-block; }
    .device-toggle-btn { display: inline-flex; align-items: center; gap: 8px; }
    .device-menu {
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 8px;
      background: rgba(255,255,255,0.95);
      border: 2px solid rgba(0,255,136,0.3);
      border-radius: 12px;
      padding: 8px;
      min-width: 160px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      display: none;
      z-index: 1000;
    }
    .device-toggle.active .device-menu { display: block; }
    .device-option {
      width: 100%;
      padding: 10px 12px;
      border: none;
      background: transparent;
      border-radius: 8px;
      cursor: pointer;
      text-align: left;
      font-weight: 600;
    }
    .device-option:hover { background: rgba(0,255,136,0.08); color: #00aa55; }

    /* Theme dropdown styling */
    .theme-dropdown {
      position: relative;
      display: inline-block;
    }
    .theme-btn {
      appearance: none;
      background: var(--theme-btn);
      color: var(--secondary-bg);
      border: 2px solid #006400;
      padding: 8px 12px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      min-width: 120px;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .theme-btn:hover {
      background: #006400;
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(0, 100, 0, 0.2);
    }
    .theme-dropdown:after {
      content: '\25BC';
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
      color: var(--secondary-bg);
    }
    .theme-menu {
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 8px;
      background:var(--theme-menu);
      border: 2px solid rgba(0,255,136,0.3);
      border-radius: 12px;
      padding: 8px;
      min-width: 90px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      display: none;
      z-index: 1000;
    }
    .theme-dropdown.active .theme-menu {
      display: block;
    }
    .theme-option {
      width: 100%;
      padding: 10px 12px;
      border: none;
      background: transparent;
      border-radius: 8px;
      cursor: pointer;
      text-align: left;
      font-weight: 600;
      color: #ceccccff;
    }
    [data-theme="dark"] .theme-option {
      color: #3c3c3cff;
    }
    .theme-option:hover {
      background: rgba(0,255,136,0.08);
      color: #00aa55;
    }

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
    .ai-chat-modal {
      position: fixed;
      bottom: 90px;
      right: 20px;
      width: 800px;
      height: 600px;
      background: var(--background);
      border: 1px solid var(--border-color);
      border-radius: 10px;
      box-shadow: 0 6px 16px rgba(0,0,0,0.3);
      display: none;
      flex-direction: column;
      overflow: hidden;
      z-index: 1100;
      visibility: hidden;
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
    .ai-chat-header {
      background: #07e1a3ff;
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
    .ai-chat-container {
      display: flex;
      flex: 1;
      overflow: hidden;
    }
    .ai-chat-sidebar {
      width: 250px;
      background: var(--secondary-bg);
      border-right: 1px solid var(--border-color);
      display: flex;
      flex-direction: column;
    }
    #newChatBtn {
      padding: 10px;
      background: #03e4d9ff;
      color: white;
      border: none;
      cursor: pointer;
      font-size: 16px;
    }
    #newChatBtn:hover {
      background: #0ae997ff;
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
      border-bottom: 1px solid var(--border-color);
      color: var(--text-color);
    }
    #chatHistoryList li:hover,
    #chatHistoryList li.active {
      background: var(--border-color);
    }
    .ai-chat-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .ai-chat-messages {
      flex: 1;
      padding: 10px;
      overflow-y: auto;
      background: var(--secondary-bg);
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
      background: #04d2cbff;
      color: white;
      align-self: flex-end;
    }
    .ai-chat-form {
      display: flex;
      border-top: 1px solid var(--border-color);
    }
    .ai-chat-form input {
      flex: 1;
      padding: 10px;
      border: none;
      outline: none;
      background: var(--background);
      color: var(--text-color);
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
    footer {
      background: var(--secondary-bg);
      border-top: 1px solid var(--border-color);
      padding: 40px 0 20px;
      color: var(--text-color);
      font-size: 14px;
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
      color: var(--text-color);
      font-size: 24px;
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
      color: var(--text-color);
      transition: color 0.3s;
    }
    .footer-links li a:hover {
      color: #27ed15;
    }
    .footer-bottom {
      text-align: center;
      padding-top: 20px;
      border-top: 1px solid var(--border-color);
      margin-top: 20px;
    }
    .footer-bottom p {
      margin: 0;
      color: var(--text-color);
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
      // Theme toggle functionality
  const themeDropdown = document.getElementById('themeDropdown');
  const themeMenu = document.getElementById('themeMenu');
  const themeBtn = document.getElementById('themeDropdownBtn');
  const themeIcon = document.getElementById('themeIcon');
  const themeText = document.getElementById('themeText');
  let currentTheme = '<?php echo htmlspecialchars($theme); ?>';
  
  // Initialize theme
  applyTheme(currentTheme);

  // Apply theme based on selection or system preference
  function applyTheme(theme) {
    let effectiveTheme = theme;
    if (theme === 'device') {
      effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', effectiveTheme);
    updateThemeUI(theme, effectiveTheme);
    
    // Save theme to server
    fetch(`?theme=${theme}`, { method: 'GET' })
      .catch(error => console.error('Error saving theme:', error));
  }

  // Update theme button UI
  function updateThemeUI(theme, effectiveTheme) {
    if (themeIcon && themeText) {
      if (theme === 'device') {
        themeIcon.className = 'bi theme-icon bi-laptop';
        themeText.textContent = 'Device';
      } else if (theme === 'dark') {
        themeIcon.className = 'bi theme-icon bi-moon-fill';
        themeText.textContent = 'Dark';
      } else {
        themeIcon.className = 'bi theme-icon bi-sun-fill';
        themeText.textContent = 'Light';
      }
    }
  }

  // Theme dropdown toggle
  if (themeBtn && themeDropdown) {
    themeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      themeDropdown.classList.toggle('active');
    });
  }

  // Theme option selection
  if (themeMenu) {
    themeMenu.addEventListener('click', (e) => {
      const option = e.target.closest('.theme-option');
      if (!option) return;
      currentTheme = option.dataset.theme;
      applyTheme(currentTheme);
      themeDropdown.classList.remove('active');
    });
  }

  // Close theme menu when clicking outside
  document.addEventListener('click', (e) => {
    if (themeDropdown && !themeDropdown.contains(e.target)) {
      themeDropdown.classList.remove('active');
    }
  });

  // Listen for system theme changes when 'device' is selected
  if (currentTheme === 'device') {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', (e) => {
      if (currentTheme === 'device') {
        applyTheme('device');
      }
    });
  }

      // Carousel functionality
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

      // Auto-slide every 5 seconds
      setInterval(() => {
        showSlide(currentIndex + 1);
      }, 5000);

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
      const deviceToggle = document.getElementById('deviceToggle');
      const deviceMenu = document.getElementById('deviceMenu');
      const deviceLabel = deviceToggle ? deviceToggle.querySelector('.device-label') : null;
      const productCards = document.querySelectorAll('.product-card');
      let selectedDevice = 'all';

      function filterProducts() {
        const searchText = searchInput ? searchInput.value.toLowerCase() : '';
        const category = selectedDevice.toLowerCase();

        productCards.forEach(card => {
          const title = card.querySelector('h3').textContent.toLowerCase();
          const categories = card.dataset.category
            .toLowerCase()
            .split(',')
            .map(c => c.trim());

          const matchesSearch = title.includes(searchText);
          const matchesCategory = category === 'all' || categories.includes(category);
          card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });
      }

      if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
      }

      // Device toggle button behavior
      const deviceToggleBtn = document.getElementById('deviceToggleBtn');
      if (deviceToggleBtn && deviceToggle) {
        deviceToggleBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          deviceToggle.classList.toggle('active');
        });
      }

      if (deviceMenu) {
        deviceMenu.addEventListener('click', (e) => {
          const opt = e.target.closest('.device-option');
          if (!opt) return;
          selectedDevice = opt.dataset.device || 'all';
          if (deviceLabel) deviceLabel.textContent = opt.textContent;
          deviceToggle.classList.remove('active');
          filterProducts();
        });
      }

      // Close device menu when clicking outside
      document.addEventListener('click', (e) => {
        if (deviceToggle && !deviceToggle.contains(e.target)) {
          deviceToggle.classList.remove('active');
        }
      });

      // Category card click functionality
      const categoryCards = document.querySelectorAll('.category-card');
      categoryCards.forEach(card => {
        card.addEventListener('click', function(e) {
          e.preventDefault();
          const category = this.dataset.category;
          if (deviceLabel) {
            deviceLabel.textContent = category.charAt(0).toUpperCase() + category.slice(1);
            selectedDevice = category;
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
              showNotification('Please login to add items to cart!', 'info');
              closePopup();
            }
          } else {
            showNotification('Please login to add items to cart!', 'info');
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
  </div>
  <div class="nav-right">
    <!-- Theme dropdown -->
    <div class="theme-dropdown" id="themeDropdown">
      <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme">
        <i class="bi theme-icon" id="themeIcon"></i>
        <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($effective_theme === 'light' ? 'Dark' : 'Light'); ?></span>
      </button>
      <div class="theme-menu" id="themeMenu" aria-hidden="true">
        <button class="theme-option" data-theme="light">Light</button>
        <button class="theme-option" data-theme="dark">Dark</button>
        <button class="theme-option" data-theme="device">Device</button>     
      </div>
    </div>
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
        <?php if(!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
          <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
        <?php else: ?>
          <img src="Uploads/Logo.png" alt="Profile" class="profile-icon">
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
  <ul class="menu" id="menu">
    <li><a href="shop.php">Home</a></li>
    <li><a href="carts_users.php">Cart (<span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>)</a></li>
     <li><a href="order_status.php">My Purchases</a></li>
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
<section class="product bg1" style="background: url('uploads/5vv1uf4kn1xvorsl-4_0_desktop_0_2X.jpeg'); background-size: cover;">
  <div class="product-bg1">
  <h2>Unleash Your Capabilities</h2>
  <p>Guaranteed 0% Interest.</p>
  <a href="laptop.php" class="cta-button">Learn More</a>
  </div>
  
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
<!-- Category Section -->
<div class="shop-container">
  <h2 class="shop-title">Featured Products</h2>
  <div class="filter-bar">
    <div class="search-bar">
      <input type="text" id="searchInput" placeholder="Search products...">
    </div>
      <div class="device-toggle" id="deviceToggle">
            <button class="device-toggle-btn login-btn-select" id="deviceToggleBtn" title="Select device">
              <span class="device-icon"><i class="bi bi-list"></i></span>
              <span class="device-label">All</span>
            </button>
                <div class="device-menu" id="deviceMenu" aria-hidden="true">
                      <button class="device-option" data-device="all"><i class="bi bi-list"></i> All</button>
                      <button class="device-option" data-device="phone"><i class="bi bi-phone"></i> Phones</button>
                      <button class="device-option" data-device="tablet"><i class="bi bi-tablet"></i> Tablets</button>
                      <button class="device-option" data-device="accessories"><i class="bi bi-usb-plug"></i> Accessories</button>
                      <button class="device-option" data-device="laptop"><i class="bi bi-laptop"></i> Laptops</button>
                      <button class="device-option" data-device="gaming"><i class="bi bi-joystick"></i> Gaming</button>

                </div>
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
    <div class="ai-layout">
      <div class="ai-sidebar">
        <div class="ai-brand">
          <h3>AI Assistant</h3>
          <div class="ai-subtitle">Chat Support</div>
        </div>
        <button id="newChatBtn" class="ai-btn ai-btn-primary">+ New Chat</button>
        <ul id="chatHistoryList" class="ai-chat-list"></ul>
        <div class="ai-sidebar-footer">
          <button id="clearChatBtn" class="ai-btn ai-btn-secondary">Clear Chat</button>
        </div>
      </div>
      <div class="ai-chat-main">
        <div class="ai-chat-messages" id="aiChatMessages">
          <div class="message bot">Hello I'm Verna Meta Shark Attendee and Staff, how can I help you?</div>
        </div>
        <form id="aiChatForm" class="ai-chat-form">
          <div class="ai-input-wrapper">
            <input type="text" id="aiChatInput" placeholder="Type your message..." required>
            <button type="submit" class="ai-send-btn" title="Send"><i class="bi bi-send"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- Chat Widget -->
<a href="chat.php" class="chat-widget" title="Chat with us">
  <i class="bi bi-chat-dots-fill chat-icon"></i>
</a>
<script>window.currentUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;</script>
<script src="../js/ai_chat.js"></script>
<footer>
  <div class="footer-content">
    <div class="footer-top">
      <div class="footer-logo">
        <img src="Uploads/logo1.png" alt="Meta Shark Logo">
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