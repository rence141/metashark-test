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
    // Check if user_id exists in database to ensure session is valid
    include("db.php");
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If user doesn't exist in database, clear session
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
        
        // Check if item already exists in cart
        $check_sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing quantity
            $existing_item = $check_result->fetch_assoc();
            $new_quantity = $existing_item['quantity'] + $quantity;
            $update_sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
            $update_stmt->execute();
        } else {
            // Add new item to cart
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
  <title>MetaAccessories</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <style>
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

    .worm-container {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .worm-dot {
      width: 16px;
      height: 16px;
      background-color: #44D62C;
      border-radius: 50%;
      opacity: 0.2;
    }

    .worm-dot:nth-child(1) {
      animation: worm 1.2s infinite 0s;
    }

    .worm-dot:nth-child(2) {
      animation: worm 1.2s infinite 0.2s;
    }

    .worm-dot:nth-child(3) {
      animation: worm 1.2s infinite 0.4s;
    }

    .worm-dot:nth-child(4) {
      animation: worm 1.2s infinite 0.6s;
    }

    .worm-dot:nth-child(5) {
      animation: worm 1.2s infinite 0.8s;
    }

    .loading-text {
      color: #44D62C;
      font-size: 24px;
      margin-top: 20px;
      font-weight: bold;
      text-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
    }

    @keyframes worm {
      0%, 100% {
        transform: scale(0.6);
        opacity: 0.2;
      }
      50% {
        transform: scale(1);
        opacity: 1;
        box-shadow: 0 0 10px rgba(68, 214, 44, 0.8);
      }
    }
    /* Theme Variables */
    :root {
      /* Default variables for both themes */
      --bg-primary: var(--dark-bg-primary);
      --bg-secondary: var(--dark-bg-secondary);
      --bg-tertiary: var(--dark-bg-tertiary);
      --text-primary: var(--dark-text-primary);
      --text-secondary: var(--dark-text-secondary);
      --text-muted: var(--dark-text-muted);
      --border: var(--dark-border);
      --border-light: var(--dark-border-light);
      --accent: var(--dark-accent);
      --accent-hover: var(--dark-accent-hover);
      --accent-light: var(--dark-accent-light);
      --shadow: var(--dark-shadow);
      --shadow-hover: var(--dark-shadow-hover);
      --theme-toggle-bg: var(--dark-theme-toggle-bg);
      --theme-toggle-text: var(--dark-theme-toggle-text);
      --theme-toggle-border: var(--dark-theme-toggle-border);
      --theme-toggle-hover: var(--dark-theme-toggle-hover);
      --theme-shadow: var(--dark-theme-shadow);
      /* Light Theme - Green and White */
      --light-bg-primary: #ffffff;
      --light-bg-secondary: #f5fff5;
      --light-bg-tertiary: #eaffea;
      --light-text-primary: #44D62C;
      --light-text-secondary: #36b020;
      --light-text-muted: #7dde6b;
      --light-border: #c9ffc9;
      --light-border-light: #e5ffe5;
      --light-accent: #44D62C;
      --light-accent-hover: #36b020;
      --light-accent-light: #d4edda;
      --light-shadow: rgba(68, 214, 44, 0.1);
      --light-shadow-hover: rgba(68, 214, 44, 0.15);
      --light-theme-toggle-bg: #ffffff;
      --light-theme-toggle-text: #44D62C;
      --light-theme-toggle-border: #44D62C;
      --light-theme-toggle-hover: #f5fff5;
      --light-theme-shadow: rgba(68, 214, 44, 0.1);
      
      /* Dark Theme */
      --dark-bg-primary: #0A0A0A;
      --dark-bg-secondary: #111111;
      --dark-bg-tertiary: #1a1a1a;
      --dark-text-primary: #ffffff;
      --dark-text-secondary: #cccccc;
      --dark-text-muted: #888888;
      --dark-border: #333333;
      --dark-border-light: #444444;
      --dark-accent: #44D62C;
      --dark-accent-hover: #36b020;
      --dark-accent-light: #2a5a1a;
      --dark-shadow: rgba(0, 0, 0, 0.3);
      --dark-shadow-hover: rgba(0, 0, 0, 0.4);
      --dark-theme-toggle-bg: #1a1a1a;
      --dark-theme-toggle-text: #ffffff;
      --dark-theme-toggle-border: #44D62C;
      --dark-theme-toggle-hover: #333333;
      --dark-theme-shadow: rgba(0, 0, 0, 0.3);
    }
    
    /* Dark Theme */
    [data-theme="dark"] {
      --bg-primary: var(--dark-bg-primary);
      --bg-secondary: var(--dark-bg-secondary);
      --bg-tertiary: var(--dark-bg-tertiary);
      --text-primary: var(--dark-text-primary);
      --text-secondary: var(--dark-text-secondary);
      --text-muted: var(--dark-text-muted);
      --border: var(--dark-border);
      --border-light: var(--dark-border-light);
      --accent: var(--dark-accent);
      --accent-hover: var(--dark-accent-hover);
      --accent-light: var(--dark-accent-light);
      --shadow: var(--dark-shadow);
      --shadow-hover: var(--dark-shadow-hover);
      --theme-toggle-bg: var(--dark-theme-toggle-bg);
      --theme-toggle-text: var(--dark-theme-toggle-text);
      --theme-toggle-border: var(--dark-theme-toggle-border);
      --theme-toggle-hover: var(--dark-theme-toggle-hover);
      --theme-shadow: var(--dark-theme-shadow);
    }
    
    /* Light Theme */
    [data-theme="light"] {
      --bg-primary: var(--light-bg-primary);
      --bg-secondary: var(--light-bg-secondary);
      --bg-tertiary: var(--light-bg-tertiary);
      --text-primary: var(--light-text-primary);
      --text-secondary: var(--light-text-secondary);
      --text-muted: var(--light-text-muted);
      --border: var(--light-border);
      --border-light: var(--light-border-light);
      --accent: var(--light-accent);
      --accent-hover: var(--light-accent-hover);
      --accent-light: var(--light-accent-light);
      --shadow: var(--light-shadow);
      --shadow-hover: var(--light-shadow-hover);
      --theme-toggle-bg: var(--light-theme-toggle-bg);
      --theme-toggle-text: var(--light-theme-toggle-text);
      --theme-toggle-border: var(--light-theme-toggle-border);
      --theme-toggle-hover: var(--light-theme-toggle-hover);
      --theme-shadow: var(--light-theme-shadow);
    }
    /* Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'ASUS ROG', Arial, sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* Navbar */
    .navbar {
      background: var(--bg-secondary);
      padding: 15px 20px;
      color: var(--accent);
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
      border-bottom: 2px solid var(--accent);
      box-shadow: 0 2px 10px var(--shadow);
    }
    
    .nav-left {
      display: flex;
      align-items: center;
    }

    .navbar h2 {
      margin: 0;
    }
    
    .nav-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .profile-icon {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background-color: var(--bg-secondary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #44D62C;
      font-size: 20px;
      cursor: pointer;
      transition: all 0.3s ease;
      object-fit: cover;
      border: 2px solid #44D62C;
      box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
    }
    
    .profile-icon:hover {
      background-color: var(--bg-tertiary);
      transform: scale(1.05);
      box-shadow: 0 0 15px rgba(68, 214, 44, 0.8);
    }

    .hamburger {
      font-size: 24px;
      cursor: pointer;
      background: none;
      border: none;
      color: #44D62C;
      transition: transform 0.3s ease;
    }
    
    .hamburger:hover {
      transform: scale(1.1);
    }

    .menu {
      position: absolute;
      top: 60px;
      right: 20px;
      background: var(--bg-tertiary);
      list-style: none;
      padding: 15px;
      border-radius: 8px;
      display: none;
      flex-direction: column;
      gap: 10px;
      border: 1px solid var(--accent);
      box-shadow: 0 0 10px var(--shadow);
      z-index: 9999;
    }

    .menu li {
      color: var(--text-primary);
      cursor: pointer;
      transition: color 0.3s, transform 0.2s, background-color 0.3s;
      padding: 5px 10px;
      border-radius: 4px;
    }
    
    /* Make navbar list green in light mode */
    [data-theme="light"] .menu li,
    [data-theme="light"] .menu li a {
      color: #44D62C;
    }

    .menu li:hover {
      color: var(--accent);
      background-color: var(--bg-secondary);
      transform: translateX(5px);
    }

    .menu.show {
      display: flex !important;
    }

    /* Debug: Make sure hamburger is clickable */
    .hamburger {
      cursor: pointer !important;
      z-index: 10000 !important;
      position: relative !important;
    }

    /* Debug: Make sure menu is visible when shown */
    .menu {
      z-index: 9999 !important;
    }

    /* Banner */
    .banner {
      background: linear-gradient(135deg, var(--accent-light), var(--bg-secondary));
      height: 300px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .banner::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--shadow);
      z-index: 1;
    }

    .banner h1 {
      background: var(--bg-tertiary);
      padding: 20px;
      border-radius: 10px;
      font-size: 2.5rem;
      border: 2px solid var(--accent);
      box-shadow: 0 0 20px var(--shadow);
      position: relative;
      z-index: 2;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 10px;
      color: var(--accent);
    }

    .banner p {
      background: var(--bg-tertiary);
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 1.2rem;
      border: 1px solid var(--accent);
      position: relative;
      z-index: 2;
      color: var(--accent);
    }

    /* Shop container */
    .shop-container {
      padding: 40px 20px;
      max-width: 1200px;
      margin: auto;
      background-color: var(--bg-primary);
    }

    .shop-title {
      text-align: center;
      margin-bottom: 20px;
      font-size: 2rem;
      color: #44D62C;
      text-transform: uppercase;
      letter-spacing: 2px;
      position: relative;
      display: inline-block;
      left: 50%;
      transform: translateX(-50%);
      padding-bottom: 10px;
    }
    
    .shop-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 2px;
      background: #44D62C;
      box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
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
      border: 1px solid #44D62C;
      font-size: 1rem;
      background-color: var(--bg-secondary);
      color: var(--text-primary);
      outline: none;
    }
    
    .search-bar input:focus {
      box-shadow: 0 0 8px rgba(68, 214, 44, 0.5);
    }

    .category-filter select {
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #44D62C;
      font-size: 1rem;
      background-color: var(--bg-secondary);
      color: var(--text-primary);
      outline: none;
    }
    
    .category-filter select:focus {
      box-shadow: 0 0 8px rgba(68, 214, 44, 0.5);
    }

    /* Product grid */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
    }

    .product-card {
      background: var(--bg-tertiary);
      border-radius: 10px;
      box-shadow: 0 5px 15px var(--shadow);
      overflow: hidden;
      text-align: center;
      transition: all 0.3s ease;
      border: 1px solid var(--border);
    }

    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px var(--shadow-hover);
      border-color: var(--accent);
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
      margin-bottom: 10px;
      color: var(--text-secondary);
    }

    .seller-info {
      color: var(--accent) !important;
      font-size: 0.9rem;
      font-weight: bold;
    }

    .seller-info a {
      color: var(--accent) !important;
      text-decoration: none;
      font-weight: bold;
      transition: all 0.3s ease;
    }

    .seller-info a:hover {
      color: var(--accent-hover) !important;
      text-decoration: underline;
      transform: scale(1.05);
    }

    .price {
      color: var(--accent) !important;
      font-size: 1.2rem;
      font-weight: bold;
    }

    .stock {
      color: var(--text-muted) !important;
      font-size: 0.9rem;
    }

    .product-info button {
      padding: 10px 20px;
      background: var(--accent);
      border: none;
      color: var(--bg-primary);
      font-size: 1rem;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.3s;
      font-weight: bold;
    }

    .product-info button:hover {
      background: var(--accent-hover);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px var(--shadow);
    }

    .product-info button:disabled {
      background: var(--text-muted);
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .product-actions {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      justify-content: center;
    }

    .btn-edit, .btn-manage {
      background: var(--accent);
      color: var(--bg-primary);
      padding: 8px 16px;
      border: none;
      border-radius: 5px;
      text-decoration: none;
      font-size: 14px;
      font-weight: bold;
      transition: all 0.3s ease;
      display: inline-block;
    }

    .btn-edit:hover, .btn-manage:hover {
      background: var(--accent-hover);
      transform: translateY(-2px);
      box-shadow: 0 3px 10px var(--shadow);
    }

    .btn-manage {
      background: var(--accent);
      color: var(--bg-primary);
    }

    .btn-manage:hover {
      background: var(--accent-hover);
    }

    /* Notification System */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 8px;
      color: white;
      font-weight: bold;
      z-index: 10000;
      transform: translateX(400px);
      transition: all 0.3s ease;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .notification.show {
      transform: translateX(0);
    }

    .notification.success {
      background: linear-gradient(135deg, #44D62C, #36b020);
      border-left: 4px solid #2a8a1a;
    }

    .notification.error {
      background: linear-gradient(135deg, #ff4444, #cc3333);
      border-left: 4px solid #aa2222;
    }

    .notification.info {
      background: linear-gradient(135deg, #44D62C, #36b020);
      border-left: 4px solid #2a8a1a;
    }

    /* Button Loading State */
    .product-info button.loading {
      background: var(--text-muted);
      cursor: not-allowed;
      position: relative;
      color: transparent;
    }

    .product-info button.loading::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 20px;
      height: 20px;
      border: 2px solid var(--accent);
      border-top: 2px solid transparent;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: translate(-50%, -50%) rotate(0deg); }
      100% { transform: translate(-50%, -50%) rotate(360deg); }
    }

    /* Button Click Animation */
    .product-info button:active {
      transform: scale(0.95);
    }

    /* Cart Count Animation */
    .cart-count {
      transition: all 0.3s ease;
    }

    .cart-count.updated {
      animation: bounce 0.6s ease;
    }

    @keyframes bounce {
      0%, 20%, 50%, 80%, 100% { transform: scale(1); }
      40% { transform: scale(1.2); }
      60% { transform: scale(1.1); }
    }

    /* Empty Products State */
    .empty-products {
      text-align: center;
      padding: 80px 20px;
      background: var(--bg-tertiary);
      border-radius: 10px;
      border: 1px solid var(--border);
      margin: 40px 0;
    }

    .empty-products h3 {
      font-size: 2rem;
      color: var(--accent);
      margin-bottom: 15px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .empty-products p {
      color: var(--text-secondary);
      font-size: 1.2rem;
      margin-bottom: 30px;
    }

    .btn-add-product,
    .btn-become-seller,
    .btn-login {
      display: inline-block;
      padding: 15px 30px;
      background: var(--accent);
      color: var(--bg-primary);
      text-decoration: none;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .btn-add-product:hover,
    .btn-become-seller:hover,
    .btn-login:hover {
      background: var(--accent-hover);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px var(--shadow);
    }

    .empty-products ul {
      list-style: none;
      padding: 0;
    }

    .empty-products li {
      padding: 8px 0;
      border-bottom: 1px solid var(--border);
    }

    .empty-products li:last-child {
      border-bottom: none;
    }

    /* Features Section */
    .features-section {
      background: var(--bg-secondary);
      padding: 60px 0;
      border-top: 2px solid var(--accent);
      border-bottom: 2px solid var(--accent);
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }

    .feature-card {
      background: #1a1a1a;
      padding: 30px 20px;
      border-radius: 10px;
      text-align: center;
      border: 1px solid #333333;
      transition: all 0.3s ease;
    }

    .feature-card:hover {
      border-color: #44D62C;
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(68, 214, 44, 0.2);
    }

    .feature-icon {
      font-size: 3rem;
      margin-bottom: 20px;
    }

    .feature-card h3 {
      color: #44D62C;
      font-size: 1.5rem;
      margin-bottom: 15px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .feature-card p {
      color: #888;
      font-size: 1rem;
      line-height: 1.6;
    }

    .text  {
      color: var(--dark-accent-hover);
    }

    /* Categories Section */
    .categories-section {
      background: #0A0A0A;
      padding: 60px 0;
    }

    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 25px;
      margin-top: 40px;
    }

    .category-card {
      background: #111111;
      padding: 25px 20px;
      border-radius: 10px;
      text-align: center;
      border: 1px solid #333333;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .category-card:hover {
      border-color: #44D62C;
      transform: translateY(-3px);
      box-shadow: 0 8px 15px rgba(68, 214, 44, 0.2);
    }

    .category-icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
    }

    .category-card h3 {
      color: #44D62C;
      font-size: 1.3rem;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .category-card p {
      color: #888;
      font-size: 0.9rem;
      line-height: 1.5;
    }

    /* Section Titles */
    .section-title {
      text-align: center;
      font-size: 2.2rem;
      color: #44D62C;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-bottom: 20px;
      position: relative;
      display: inline-block;
      left: 50%;
      transform: translateX(-50%);
      padding-bottom: 15px;
    }
    
    .section-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 2px;
      background: #44D62C;
      box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
    }

    /* Container */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Product images in grid and carousel - consistent sizing and fit */
.carousel-item {
  width: 100%;
  max-width: 600px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: var(--bg-tertiary);
  border-radius: 12px;
  box-shadow: 0 4px 16px var(--shadow);
  padding: 20px;
  min-height: 350px;
}

.carousel-item img {
  width: 100%;
  max-width: 400px;
  height: 250px;
  object-fit: cover;
  border-radius: 10px;
  background: #222;
  display: block;
  margin: 0 auto 15px auto;
}
  </style>
  <script>
    // Theme toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Set initial theme class based on session
      const theme = '<?php echo $theme; ?>';
      document.documentElement.className = theme === 'light' ? 'light' : '';
      
      // Update theme toggle button text
      updateThemeToggleButton();
      
      // Handle loading screen
      const loadingScreen = document.querySelector('.loading-screen');
      if (loadingScreen.classList.contains('active')) {
        // Hide loading screen after 2 seconds
        setTimeout(() => {
          loadingScreen.classList.remove('active');
        }, 2000);
      }
    });
    
    function toggleTheme() {
      const root = document.documentElement;
      const isLight = root.classList.contains('light');
      const newTheme = isLight ? 'dark' : 'light';
      
      // Toggle root class
      root.classList.toggle('light');
      
      // Update theme toggle button
      updateThemeToggleButton();
      
      // Save preference to session via AJAX
      fetch('?theme=' + newTheme);
    }
    
    function updateThemeToggleButton() {
      const isLight = document.documentElement.classList.contains('light');
      const themeIcon = document.getElementById('themeIcon');
      const themeText = document.getElementById('themeText');
      
      if (themeIcon && themeText) {
        themeIcon.textContent = isLight ? '🌙' : '☀️';
        themeText.textContent = isLight ? 'Dark' : 'Light';
      }
    }
  </script>
</head>
<body>
<!-- Loading Screen -->
<div class="loading-screen<?php echo $just_logged_in ? ' active' : ''; ?>">
  <div class="worm-container">
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
    <div class="worm-dot"></div>
  </div>
  <div class="loading-text">Loading...</div>
</div>

  <!-- NAVBAR -->
  <div class="navbar">
    <div class="nav-left">
      <h2>Meta Accessories</h2>
      <?php include('theme_toggle.php'); ?>
    </div>
    <div class="nav-right">
        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
            <?php
            // Check user role to determine profile page
            $user_role = $_SESSION['role'] ?? 'buyer';
            $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
            
            // Fetch current user's profile image from database
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
            <div class="text">Login</div>
          </a>
          <a href="signup_users.php">
            <div class="text">Signup</div>
          </a>
            <a href="login_users.php">
                <div class="profile-icon">👤</div>

            </a>
        <?php endif; ?>
        <button class="hamburger" onclick="console.log('Hamburger clicked!')">☰</button>
    </div>

<ul class="menu" id="menu">
  <li><a href="shop.php" style="color: var(--accent); text-decoration: none;">Home</a></li>
  <li><a href="carts_users.php" style="color: var(--accent); text-decoration: none;">Cart (<span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>)</a></li>
  <?php if(isset($_SESSION['user_id'])): ?>
    <?php
    // Check if user has seller role
    $user_role = $_SESSION['role'] ?? 'buyer';
    ?>
    <?php if($user_role === 'seller' || $user_role === 'admin'): ?>
      <li><a href="seller_dashboard.php" style="color: var(--accent); text-decoration: none;">Seller Dashboard</a></li>
    <?php else: ?>
      <li><a href="become_seller.php" style="color: var(--accent); text-decoration: none;">Become Seller</a></li>
    <?php endif; ?>
  <?php endif; ?>
  <?php 
  $user_role = $_SESSION['role'] ?? 'buyer';
  $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
  ?>
  <li><a href="<?php echo $profile_page; ?>" style="color: var(--accent); text-decoration: none;">Profile</a></li>
  <li><a href="logout.php" style="color: var(--accent); text-decoration: none;">Logout</a></li>
</ul>
  </div>

  <!-- NOTIFICATION -->
  <?php if (isset($cart_success) && $cart_success): ?>
    <div class="notification success show" id="cartNotification">
      ✅ <?php echo $cart_message; ?>
    </div>
  <?php endif; ?>

  <!-- BANNER -->
  <div class="banner">
    <h1>Welcome to Meta Accessories</h1>
    <p>Your Ultimate Tech Marketplace</p>
  </div>

 <?php
      include("db.php");
      $carousel_sql = "SELECT p.*, u.seller_name, u.fullname as seller_fullname 
        FROM products p 
        LEFT JOIN users u ON p.seller_id = u.id 
        WHERE p.is_active = TRUE 
        ORDER BY RAND() 
        LIMIT 5";
      $carousel_result = $conn->query($carousel_sql);
      $carousel_products = [];
      if ($carousel_result && $carousel_result->num_rows > 0) {
        while ($product = $carousel_result->fetch_assoc()) {
          $carousel_products[] = $product;
        }
      }
      foreach ($carousel_products as $i => $product) {
        echo '<div class="carousel-item" style="display:' . ($i === 0 ? 'block' : 'none') . ';text-align:center;">';
        echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '" style="width:100%;max-height:300px;object-fit:cover;border-radius:10px;">';
        echo '<h3 style="color:var(--accent);margin:15px 0 5px 0;">' . htmlspecialchars($product['name']) . '</h3>';
        echo '<p class="price" style="font-size:1.2rem;">$' . number_format($product['price'], 2) . '</p>';
        echo '<p class="seller-info" style="font-size:0.9rem;">Sold by: <a href="seller_shop.php?seller_id=' . $product['seller_id'] . '" style="color:var(--accent);text-decoration:none;font-weight:bold;">' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</a></p>';
        echo '</div>';
      }
    ?>
  </div>
  <button class="carousel-arrow right" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:var(--accent);color:var(--bg-primary);border:none;border-radius:50%;width:40px;height:40px;font-size:2rem;z-index:2;cursor:pointer;">&#8594;</button>
</div>

  <!-- FEATURES SECTION -->
  <div class="features-section">
    <div class="container">
      <h2 class="section-title">Why Choose Meta Shark?</h2>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">🛒</div>
          <h3>Easy Shopping</h3>
          <p>Browse and buy from multiple sellers in one place</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3>Secure Payments</h3>
          <p>Safe and secure checkout process</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🚀</div>
          <h3>Fast Delivery</h3>
          <p>Quick shipping from trusted sellers</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">⭐</div>
          <h3>Quality Products</h3>
          <p>Verified sellers with quality products</p>
        </div>
      </div>
    </div>
  </div>

  <!-- CATEGORIES SECTION -->
  <div class="categories-section">
    <div class="container">
      <h2 class="section-title">Shop by Category</h2>
      <div class="categories-grid">
        <div class="category-card" data-category="accessories">
          <div class="category-icon">🎧</div>
          <h3>Accessories</h3>
          <p>Headphones, cases, chargers and more</p>
        </div>
        <div class="category-card" data-category="phone">
          <div class="category-icon">📱</div>
          <h3>Phones</h3>
          <p>Latest smartphones and mobile devices</p>
        </div>
        <div class="category-card" data-category="tablet">
          <div class="category-icon">📱</div>
          <h3>Tablets</h3>
          <p>Tablets for work and entertainment</p>
        </div>
        <div class="category-card" data-category="laptop">
          <div class="category-icon">💻</div>
          <h3>Laptops</h3>
          <p>High-performance laptops and notebooks</p>
        </div>
        <div class="category-card" data-category="gaming">
          <div class="category-icon">🎮</div>
          <h3>Gaming</h3>
          <p>Gaming peripherals and accessories</p>
        </div>
        <div class="category-card" data-category="other">
          <div class="category-icon">🔧</div>
          <h3>Other</h3>
          <p>Miscellaneous tech products</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Success Message -->
  <?php if (isset($cart_message)): ?>
    <div style="background: #44D62C; color: #000000; padding: 15px; text-align: center; font-weight: bold;">
      <?php echo $cart_message; ?>
    </div>
  <?php endif; ?>

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
          <option value="gaming">Gaming</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <!-- Product Grid -->
    <div class="product-grid" id="productGrid">
      <?php
      // Fetch products from database
      if (isset($_SESSION['user_id'])) {
        include("db.php");
        
        // Check if products table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'products'");
        
        if ($table_check && $table_check->num_rows > 0) {
          $products_sql = "SELECT p.*, u.seller_name, u.fullname as seller_fullname 
                          FROM products p 
                          LEFT JOIN users u ON p.seller_id = u.id 
                          WHERE p.is_active = TRUE 
                          ORDER BY p.created_at DESC";
          $products_result = $conn->query($products_sql);
          
          if ($products_result && $products_result->num_rows > 0) {
          while ($product = $products_result->fetch_assoc()) {
            echo '<div class="product-card" data-category="' . htmlspecialchars($product['category']) . '">';
            echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
            echo '<div class="product-info">';
            echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
            echo '<p class="seller-info">Sold by: <a href="seller_shop.php?seller_id=' . $product['seller_id'] . '" style="color: #44D62C; text-decoration: none; font-weight: bold;">' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</a></p>';
            echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
            echo '<p class="stock">Stock: ' . $product['stock_quantity'] . '</p>';
            echo '<div class="product-actions">';
            
            // Add to Cart button for buyers
            if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $product['seller_id']) {
                echo '<form method="POST" style="display: inline;" class="add-to-cart-form" data-product-id="' . $product['id'] . '">';
                echo '<input type="hidden" name="add_to_cart" value="1">';
                echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
                echo '<button type="submit" class="add-to-cart-btn" ' . ($product['stock_quantity'] <= 0 ? 'disabled' : '') . ' data-product-name="' . htmlspecialchars($product['name']) . '">';
                echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart';
                echo '</button>';
                echo '</form>';
            } else {
                // Edit buttons for sellers viewing their own products
                echo '<a href="edit_product.php?id=' . $product['id'] . '" class="btn-edit"> Edit</a>';
                echo '<a href="seller_dashboard.php" class="btn-manage"> Manage</a>';
            }
            
            echo '</div>';
            echo '</div>';
            echo '</div>';
          }
          } else {
            // No products in database - show empty state
            echo '<div class="empty-products">';
            echo '<h3> Welcome to Meta Accessories!</h3>';
            echo '<p>Our marketplace is ready for sellers to add their amazing products!</p>';
            echo '<p>No products have been added yet - be the first to showcase your tech accessories!</p>';
            if(isset($_SESSION['user_id'])) {
              // Check if user is a seller using role column
              $seller_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
              $seller_check->bind_param("i", $_SESSION['user_id']);
              $seller_check->execute();
              $seller_result = $seller_check->get_result();
              $user_role = $seller_result->fetch_assoc()['role'] ?? 'buyer';
              
              if($user_role === 'seller' || $user_role === 'admin') {
                echo '<a href="add_product.php" class="btn-add-product">Add Your First Product</a>';
              } else {
                echo '<a href="become_seller.php" class="btn-become-seller">Become a Seller</a>';
              }
            } else {
              echo '<a href="login_users.php" class="btn-login">Login to Start Selling</a>';
            }
            echo '</div>';
          }
        } else {
          // Database tables don't exist - show setup message
          echo '<div class="empty-products">';
          echo '<h3>Database Setup Required</h3>';
          echo '<p>Please set up the database tables first to start using the marketplace.</p>';
          echo '<p>Run the SQL setup scripts in your MySQL database:</p>';
          echo '<ul style="text-align: left; max-width: 400px; margin: 20px auto; color: #888;">';
          echo '<li>1. Run setup_cart_tables.sql</li>';
          echo '<li>2. Run setup_seller_system.sql</li>';
          echo '</ul>';
          echo '<a href="login_users.php" class="btn-login">Login After Setup</a>';
          echo '</div>';
        }
      } else {
       
        // Show actual products from database for non-logged in users
        include("db.php");
        $table_check = $conn->query("SHOW TABLES LIKE 'products'");
        if ($table_check && $table_check->num_rows > 0) {
          $products_sql = "SELECT p.*, u.seller_name, u.fullname as seller_fullname 
                          FROM products p 
                          LEFT JOIN users u ON p.seller_id = u.id 
                          WHERE p.is_active = TRUE 
                          ORDER BY p.created_at DESC";
          $products_result = $conn->query($products_sql);
          if ($products_result && $products_result->num_rows > 0) {
            while ($product = $products_result->fetch_assoc()) {
              echo '<div class="product-card" data-category="' . htmlspecialchars($product['category']) . '">';
              echo '<img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">';
              echo '<div class="product-info">';
              echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
              echo '<p class="seller-info">Sold by: <a href="seller_shop.php?seller_id=' . $product['seller_id'] . '" style="color: #44D62C; text-decoration: none; font-weight: bold;">' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</a></p>';
              echo '<p class="price">$' . number_format($product['price'], 2) . '</p>';
              echo '<p class="stock">Stock: ' . $product['stock_quantity'] . '</p>';
              echo '<button onclick="alert(\'Please login to add items to cart!\')">Add to Cart</button>';
              echo '</div>';
              echo '</div>';
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
        // Static products for non-logged in users if database is not set up
        
        /**foreach ($static_products as $product) {
          echo '<div class="product-card" data-category="' . $product['category'] . '">';
          echo '<img src="' . $product['image'] . '" alt="' . $product['name'] . '">';
          echo '<div class="product-info">';
          echo '<h3>' . $product['name'] . '</h3>';
          echo '<p>$' . number_format($product['price'], 2) . '</p>';
          echo '<button onclick="alert(\'Please login to add items to cart!\')">Add to Cart</button>';
          echo '</div>';
          echo '</div>';
        }**/
      }
      ?>
    </div>
  </div>

  <script>
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
    // Toggle Hamburger Menu
    const hamburger = document.querySelector(".hamburger");
    const menu = document.getElementById("menu");

      if (hamburger && menu) {
        hamburger.addEventListener("click", function(e) {
          e.preventDefault();
          e.stopPropagation();
          console.log("Hamburger clicked"); // Debug log
      menu.classList.toggle("show");
    });

        // Close menu when clicking outside
        document.addEventListener("click", function(e) {
          if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove("show");
          }
        });

        // Close menu when clicking on menu items
        const menuItems = menu.querySelectorAll("a");
        menuItems.forEach(item => {
          item.addEventListener("click", function() {
            menu.classList.remove("show");
          });
        });
      } else {
        console.error("Hamburger or menu element not found");
      }

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

      if (searchInput) {
    searchInput.addEventListener("input", filterProducts);
      }
      if (categorySelect) {
    categorySelect.addEventListener("change", filterProducts);
      }

      // Category card click functionality
      const categoryCards = document.querySelectorAll(".category-card");
      categoryCards.forEach(card => {
        card.addEventListener("click", function() {
          const category = this.dataset.category;
          if (categorySelect) {
            categorySelect.value = category;
            filterProducts();
          }
        });
      });
    });

    // Enhanced Add to Cart functionality
    const addToCartForms = document.querySelectorAll('.add-to-cart-form');
    addToCartForms.forEach(form => {
      form.addEventListener('submit', function(e) {
        const button = form.querySelector('.add-to-cart-btn');
        const productName = button.getAttribute('data-product-name');
        
        // Add loading state
        button.classList.add('loading');
        button.disabled = true;
        
        // Show immediate feedback
        showNotification('Adding to cart...', 'info');
        
        // Simulate processing time (remove in production)
        setTimeout(() => {
          // The form will submit normally after this
        }, 500);
      });
    });

    // Auto-hide notification after 3 seconds
    const notification = document.getElementById('cartNotification');
    if (notification) {
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          notification.remove();
        }, 300);
      }, 3000);
    }

    // Update cart count animation
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
      cartCount.classList.add('updated');
      setTimeout(() => {
        cartCount.classList.remove('updated');
      }, 600);
    }

    // Notification function
    function showNotification(message, type = 'success') {
      // Remove existing notifications
      const existingNotifications = document.querySelectorAll('.notification');
      existingNotifications.forEach(notif => notif.remove());
      
      // Create new notification
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.innerHTML = message;
      
      // Add to page
      document.body.appendChild(notification);
      
      // Show notification
      setTimeout(() => {
        notification.classList.add('show');
      }, 100);
      
      // Auto-hide after 3 seconds
      setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
          notification.remove();
        }, 300);
      }, 3000);
    }


   document.addEventListener('DOMContentLoaded', function() {
  const items = document.querySelectorAll('.carousel-item');
  const leftArrow = document.querySelector('.carousel-arrow.left');
  const rightArrow = document.querySelector('.carousel-arrow.right');
  let current = 0;
  let interval;

  function showSlide(idx) {
    items.forEach((item, i) => {
      item.style.display = i === idx ? 'block' : 'none';
    });
  }

  function nextSlide() {
    current = (current + 1) % items.length;
    showSlide(current);
  }

  function prevSlide() {
    current = (current - 1 + items.length) % items.length;
    showSlide(current);
  }

  leftArrow.addEventListener('click', function() {
    prevSlide();
    resetInterval();
  });

  rightArrow.addEventListener('click', function() {
    nextSlide();
    resetInterval();
  });

  function resetInterval() {
    clearInterval(interval);
    interval = setInterval(nextSlide, 3000); // Infinite auto loop
  }

  if (items.length > 0) {
    showSlide(current);
    interval = setInterval(nextSlide, 3000); // Infinite auto loop
  }
});
  </script>

</body>
</html>