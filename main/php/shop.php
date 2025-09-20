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
  /* Theme Variables */
  :root {
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
    /* Light Theme */
    --light-bg-primary: #ffffff;
    --light-bg-secondary: #f9f9f9;
    --light-bg-tertiary: #f0f0f0;
    --light-text-primary: #000000;
    --light-text-secondary: #333333;
    --light-text-muted: #666666;
    --light-border: #e0e0e0;
    --light-border-light: #f0f0f0;
    --light-accent: #44D62C;
    --light-accent-hover: #36b020;
    --light-accent-light: #eaffea;
    --light-shadow: rgba(0, 0, 0, 0.1);
    --light-shadow-hover: rgba(0, 0, 0, 0.2);
    --light-theme-toggle-bg: #ffffff;
    --light-theme-toggle-text: #000000;
    --light-theme-toggle-border: #44D62C;
    --light-theme-toggle-hover: #f0f0f0;
    --light-theme-shadow: rgba(0, 0, 0, 0.1);
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
    animation: slideInFromTop 0.5s ease-out;
  }

  /* Navbar Links (non-users) */
  a {
  text-decoration: none;
}

a .nonuser-text,
a .profile-icon {
  color: var(--dark-accent);
}

  @keyframes slideInFromTop {
    0% {
      transform: translateY(-100%);
      opacity: 0;
    }
    100% {
      transform: translateY(0);
      opacity: 1;
    }
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
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .logo:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 0 15px rgba(68, 214, 44, 0.8);
  }
  .navbar h2 {
    margin: 0;
    transition: color 0.3s ease;
  }
  .navbar h2:hover {
    color: var(--accent-hover);
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
    transform: scale(1.15) rotate(10deg);
    box-shadow: 0 0 20px rgba(68, 214, 44, 1);
    opacity: 1;
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
    transform: scale(1.2) rotate(90deg);
    opacity: 1;
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
    transform: translateY(-20px);
    opacity: 0;
    transition: all 0.3s ease;
  }
  .menu.show {
    display: flex;
    transform: translateY(0);
    opacity: 1;
  }
  .menu li a {
    color: var(--text-primary);
    cursor: pointer;
    text-decoration: none;
    transition: color 0.3s, transform 0.2s, background-color 0.3s;
    padding: 5px 10px;
    border-radius: 4px;
    color: #44D62C;
    text-decoration: nonetext-decoration: none
  }
  [data-theme="light"] .menu li,
  [data-theme="light"] .menu li a {
    color: #44D62C;
  }
  .menu li:hover {
    color: var(--accent);
    background-color: var(--bg-secondary);
    transform: translateX(10px);
    opacity: 1;
  }
  .hamburger {
    cursor: pointer !important;
    z-index: 10000 !important;
    position: relative !important;
  }
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
    animation: fadeInScale 1s ease-out;
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
    animation: fadeInScale 1s ease-out 0.2s;
  }
  @keyframes fadeInScale {
    0% {
      opacity: 0;
      transform: scale(0.8);
    }
    100% {
      opacity: 1;
      transform: scale(1);
    }
  }
  /* Video Banner */
  .video-banner {
    position: relative;
    height: 700px;
    overflow: hidden;
    border-radius: 20px;
    margin: 20px auto;
    width: 100%;
    animation: zoomIn 1.5s ease-out;
  }
  @keyframes zoomIn {
    0% {
      transform: scale(1.1);
      opacity: 0.8;
    }
    100% {
      transform: scale(1);
      opacity: 1;
    }
  }
  .video-banner video {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 700px;
    max-width: none;
    transform: translate(-50%, -50%);
    object-fit: cover;
    z-index: 0;
    border-radius: 20px;
  }
  /* Video Logo */
  .video-logo {
    position: absolute;
    bottom: 20px;
    right: 20px;
    z-index: 2;
    width: 100px;
    height: auto;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .video-logo:hover {
    transform: scale(1.1);
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
    animation: fadeIn 1s ease-out;
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
    animation: expandWidth 1s ease-out;
  }
  @keyframes expandWidth {
    0% {
      width: 0;
    }
    100% {
      width: 100%;
    }
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
    transition: all 0.3s ease;
  }
  .search-bar input:focus {
    box-shadow: 0 0 8px rgba(68, 214, 44, 0.5);
    transform: scale(1.02);
  }
  /* Categories Section */
  .categories-section {
    background: var(--bg-primary);
    padding: 60px 0;
  }
  .category-filter select {
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #44D62C;
    font-size: 1rem;
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s ease;
  }
  .category-filter select:focus {
    box-shadow: 0 0 8px rgba(68, 214, 44, 0.5);
    transform: scale(1.02);
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
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.6s ease-out forwards;
  }
  .product-card:nth-child(2) { animation-delay: 0.1s; }
  .product-card:nth-child(3) { animation-delay: 0.2s; }
  .product-card:nth-child(4) { animation-delay: 0.3s; }
  .product-card:nth-child(n+5) { animation-delay: 0.4s; }
  @keyframes fadeInUp {
    0% {
      opacity: 0;
      transform: translateY(20px);
    }
    100% {
      opacity: 1;
      transform: translateY(0);
    }
  }
  .product-card:hover {
    transform: scale(1.05) translateY(-5px);
    box-shadow: 0 10px 20px rgba(68, 214, 44, 0.4);
    border-color: var(--accent);
    opacity: 1;
    animation: glowHover 1s ease-in-out infinite;
  }
  @keyframes glowHover {
    0%, 100% {
      box-shadow: 0 10px 20px rgba(68, 214, 44, 0.4);
    }
    50% {
      box-shadow: 0 10px 25px rgba(68, 214, 44, 0.6);
    }
  }
  .product-card img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    transition: transform 0.3s ease;
  }
  .product-card:hover img {
    transform: scale(1.1);
    opacity: 1;
  }
  .product-info {
    padding: 15px;
  }
  .product-info h3 {
    margin-bottom: 10px;
    font-size: 1.2rem;
    transition: color 0.3s ease;
  }
  .product-info h3:hover {
    color: var(--accent-hover);
    opacity: 1;
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
    opacity: 1;
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
    transition: all 0.3s ease;
    font-weight: bold;
    position: relative;
    overflow: hidden;
  }
  .product-info button:hover {
    background: var(--accent-hover);
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: buttonGlow 1s ease-in-out infinite;
  }
  @keyframes buttonGlow {
    0%, 100% {
      box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    }
    50% {
      box-shadow: 0 5px 20px rgba(68, 214, 44, 0.6);
    }
  }
  .product-info button:active {
    transform: scale(0.95);
  }
  .product-info button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
  }
  .product-info button:active::after {
    width: 200px;
    height: 200px;
    opacity: 0;
  }
  .product-info button:disabled {
    background: var(--text-muted);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
    opacity: 1;
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
    position: relative;
    overflow: hidden;
  }
  .btn-edit:hover, .btn-manage:hover {
    background: var(--accent-hover);
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: buttonGlow 1s ease-in-out infinite;
  }
  .btn-manage {
    background: var(--accent);
    color: var(--bg-primary);
  }
  .btn-manage:hover {
    background: var(--accent-hover);
    opacity: 1;
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
    animation: bounceIn 0.5s ease-out;
  }
  @keyframes bounceIn {
    0% {
      transform: translateX(400px) scale(0.8);
      opacity: 0;
    }
    60% {
      transform: translateX(-10px) scale(1.05);
      opacity: 1;
    }
    100% {
      transform: translateX(0) scale(1);
      opacity: 1;
    }
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
  /* Product Popup Advertisement */
  .product-popup {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.9);
    background: var(--bg-tertiary);
    border-radius: 15px;
    box-shadow: 0 10px 30px var(--shadow);
    width: 90%;
    max-width: 500px;
    height: 600px;
    z-index: 10001;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 2px solid var(--accent);
  }
  .product-popup.show {
    opacity: 1;
    visibility: visible;
    transform: translate(-50%, -50%) scale(1);
    animation: popIn 0.5s ease-out;
  }
  @keyframes popIn {
    0% {
      transform: translate(-50%, -50%) scale(0.5);
      opacity: 0;
    }
    70% {
      transform: translate(-50%, -50%) scale(1.05);
      opacity: 1;
    }
    100% {
      transform: translate(-50%, -50%) scale(1);
      opacity: 1;
    }
  }
  .popup-header {
    background: linear-gradient(135deg, var(--accent), var(--accent-hover));
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .popup-header h3 {
    color: var(--bg-primary);
    margin: 0;
    font-size: 1.3rem;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .popup-close {
    background: none;
    border: none;
    color: var(--bg-primary);
    font-size: 24px;
    cursor: pointer;
    transition: transform 0.3s ease;
  }
  .popup-close:hover {
    transform: scale(1.2) rotate(90deg);
    opacity: 1;
  }
  .popup-content {
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .popup-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    transition: transform 0.3s ease;
  }
  .popup-image:hover {
    transform: scale(1.1);
    opacity: 1;
  }
  .popup-product-info {
    width: 100%;
    text-align: center;
  }
  .popup-product-name {
    font-size: 1.4rem;
    margin-bottom: 10px;
    color: var(--text-primary);
    animation: fadeIn 0.5s ease-out;
  }
  .popup-product-price {
    font-size: 1.6rem;
    font-weight: bold;
    color: var(--accent);
    margin-bottom: 15px;
    animation: fadeIn 0.5s ease-out 0.1s;
  }
  .popup-product-description {
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
    animation: fadeIn 0.5s ease-out 0.2s;
  }
  .popup-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    width: 100%;
  }
  .popup-view-btn, .popup-add-btn {
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: none;
    position: relative;
    overflow: hidden;
  }
  .popup-view-btn {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
  }
  .popup-view-btn:hover {
    background: var(--bg-tertiary);
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: buttonGlow 1s ease-in-out infinite;
  }
  .popup-add-btn {
    background: var(--accent);
    color: var(--bg-primary);
  }
  .popup-add-btn:hover {
    background: var(--accent-hover);
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: buttonGlow 1s ease-in-out infinite;
  }
  .popup-view-btn:active, .popup-add-btn:active {
    transform: scale(0.95);
  }
  .popup-view-btn::after, .popup-add-btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
  }
  .popup-view-btn:active::after, .popup-add-btn:active::after {
    width: 200px;
    height: 200px;
    opacity: 0;
  }
  .popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
  }
  .popup-overlay.show {
    opacity: 1;
    visibility: visible;
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
    animation: fadeIn 1s ease-out;
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
    position: relative;
    overflow: hidden;
  }
  .btn-add-product:hover,
  .btn-become-seller:hover,
  .btn-login:hover {
    background: var(--accent-hover);
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: buttonGlow 1s ease-in-out infinite;
  }
  .btn-add-product:active,
  .btn-become-seller:active,
  .btn-login:active {
    transform: scale(0.95);
  }
  .btn-add-product::after,
  .btn-become-seller::after,
  .btn-login::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
  }
  .btn-add-product:active::after,
  .btn-become-seller:active::after,
  .btn-login:active::after {
    width: 200px;
    height: 200px;
    opacity: 0;
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
  background: url('Uploads/features-bg.jpg') no-repeat center center;
  background-size: cover;
  padding: 60px 0;
  border-top: 2px solid var(--accent);
  border-bottom: 2px solid var(--accent);
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

/* Default blur effect for background */
.features-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: inherit;
  filter: blur(5px);
  z-index: 0;
  transition: filter 0.3s ease;
}

/* Clearer image on hover */
.features-section:hover::before {
  filter: blur(0);
}

/* Ensure content stays above background */
.features-section > * {
  position: relative;
  z-index: 1;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 30px;
  margin-top: 40px;
}

.feature-card {
  background: var(--bg-tertiary);
  padding: 30px 20px;
  border-radius: 10px;
  text-align: center;
  border: 1px solid var(--border);
  transition: all 0.3s ease;
  opacity: 0;
  transform: translateY(20px);
  animation: slideUp 0.6s ease-out forwards;
}

.feature-card:nth-child(2) { animation-delay: 0.1s; }
.feature-card:nth-child(3) { animation-delay: 0.2s; }
.feature-card:nth-child(4) { animation-delay: 0.3s; }

@keyframes slideUp {
  0% {
    opacity: 0;
    transform: translateY(20px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

.feature-card:hover {
  border-color: var(--accent);
  transform: scale(1.05) translateY(-5px);
  box-shadow: 0 10px 20px rgba(68, 214, 44, 0.4);
  opacity: 1;
  animation: glowHover 1s ease-in-out infinite;
}

.feature-icon {
  font-size: 3rem;
  margin-bottom: 20px;
  transition: transform 0.3s ease, color 0.3s ease;
}

.feature-card:hover .feature-icon {
  transform: scale(1.2);
  color: var(--accent-hover);
  opacity: 1;
}

.feature-card h3 {
  color: #44D62C;
  font-size: 1.5rem;
  margin-bottom: 15px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.feature-card p {
  color: var(--text-secondary);
  font-size: 1rem;
  line-height: 1.6;
}

/* Theme-specific adjustments for background */
[data-theme="light"] .features-section::before {
  background: linear-gradient(rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.2)), url('Uploads/features-bg.jpg') no-repeat center center;
  background-size: cover;
}

[data-theme="dark"] .features-section::before {
  background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.3)), url('Uploads/features-bg.jpg') no-repeat center center;
  background-size: cover;
}
  /* Categories Section */
  .categories-section {
    background: var(--bg-primary);
    padding: 60px 0;
  }
  .categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 25px;
    margin-top: 40px;
  }
  .category-card {
    background: var(--bg-tertiary);
    padding: 25px 20px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.6s ease-out forwards;
  }
  .category-card:nth-child(2) { animation-delay: 0.1s; }
  .category-card:nth-child(3) { animation-delay: 0.2s; }
  .category-card:nth-child(4) { animation-delay: 0.3s; }
  .category-card:nth-child(5) { animation-delay: 0.4s; }
  .category-card:nth-child(6) { animation-delay: 0.5s; }
  .category-card:hover {
    border-color: var(--accent);
    transform: scale(1.05) translateY(-5px);
    box-shadow: 0 8px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: glowHover 1s ease-in-out infinite;
  }
  @keyframes glowHover {
    0%, 100% {
      box-shadow: 0 8px 15px rgba(68, 214, 44, 0.4);
    }
    50% {
      box-shadow: 0 8px 20px rgba(68, 214, 44, 0.6);
    }
  }
  .category-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
    transition: transform 0.3s ease;
  }
  .category-card:hover .category-icon {
    transform: scale(1.2);
    opacity: 1;
  }
  .category-card h3 {
    color: #44D62C;
    font-size: 1.3rem;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .category-card p {
    color: var(--text-secondary);
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
    animation: fadeIn 1s ease-out;
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
    animation: expandWidth 1s ease-out;
  }
  /* Product Section */
  .product {
    position: relative;
    width: 100%;
    height: 100vh;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    padding: 20px;
    color: #fff;
    text-align: left;
    overflow: hidden;
    margin: 0 auto;
  }
  .product::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(rgba(56, 59, 59, 0.6), rgba(56, 59, 59, 0.3));
    z-index: 1;
  }
  .product > * {
    position: relative;
    z-index: 2;
    animation: slideInLeft 1s ease-out;
  }
  @keyframes slideInLeft {
    0% {
      opacity: 0;
      transform: translateX(-50px);
    }
    100% {
      opacity: 1;
      transform: translateX(0);
    }
  }
  .cta-button {
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
    position: relative;
    overflow: hidden;
  }
  .cta-button:hover {
    background: var(--accent-hover);
    transform: scale(1.1) translateY(-2px);
    box-shadow: 0 5px 15px rgba(68, 214, 44, 0.4);
    opacity: 1;
    animation: buttonGlow 1s ease-in-out infinite;
  }
  .cta-button:active {
    transform: scale(0.95);
  }
  .cta-button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.3s ease, height 0.3s ease;
  }
  .cta-button:active::after {
    width: 200px;
    height: 200px;
    opacity: 0;
  }
  /* Footer */
  footer {
    text-align: center;
    padding: 15px;
    background: #000000;
    color: #0cef32;
    animation: fadeIn 1s ease-out;
  }
  .product.bg1 {
    background: url('uploads/5vv1uf4kn1xvorsl-4_0_desktop_0_2X.jpeg') no-repeat center center;
    background-size: cover;
    margin-bottom: 0.10cm;
  }
  /* Carousel for .product.bg2 */
.product.bg2 {
  width: 1400px;
  height: 700px;
  margin-bottom: 0.10cm;
  display: flex;
  flex-direction: column;
  align-items: center; /* Center horizontally */
  padding-bottom: 40px; /* Adjust distance from bottom */
  position: relative;
  overflow: hidden;
}

/* Carousel container */
.carousel-container {
  width: 100%;
  height: 100%;
  position: relative;
  overflow: hidden;
}

/* Carousel slides */
.carousel-slides {
  display: flex;
  width: 100%;
  height: 100%;
  transition: transform 0.5s ease-in-out;
}

/* Individual slide */
.carousel-slide {
  flex: 0 0 100%;
  width: 100%;
  height: 100%;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center center;
}

/* Specific slide backgrounds */
.carousel-slide:nth-child(1) {
  background: url('Uploads/GameLobby.png') no-repeat center center;
  background-size: 100% 100%;
}

/* Placeholder for additional slides */
.carousel-slide:nth-child(2) {
  background: url('Uploads/carousel2.png') no-repeat center center;
  background-size: 100% 100%;
}

.carousel-slide:nth-child(3) {
  background: url('Uploads/carousel3.png') no-repeat center center;
  background-size: 100% 100%;
}

.carousel-slide:nth-child(4) {
  background: url('Uploads/carousel4.jpeg') no-repeat center center;
  background-size: 100% 100%;
}
/* Navigation buttons */
.carousel-nav {
  position: absolute;
  top: 50%;
  width: 100%;
  display: flex;
  justify-content: space-between;
  transform: translateY(-50%);
  z-index: 2;
}

.carousel-nav button {
  background: rgba(0, 0, 0, 0.5);
  color: var(--accent);
  border: none;
  padding: 10px 20px;
  font-size: 24px;
  cursor: pointer;
  transition: background 0.3s ease, transform 0.3s ease;
}

.carousel-nav button:hover {
  background: var(--accent);
  color: var(--bg-primary);
  transform: scale(1.1);
}

.carousel-nav button:active {
  transform: scale(0.95);
}

/* Ensure content stays above carousel */
.product.bg2 > * {
  position: relative;
  z-index: 1;
}
  .product-content {
      text-align: center; /* Ensure text is centered within the container */
      max-width: 600px; /* Optional: limit width for readability */
  }
  .product-content h2 {
      font-size: 3em; /* Increase h2 font size */
  }
  .product-content p {
      font-family: 'Bebas Neue', sans-serif; /* Apply Bebas Neue font to p */
  }
  /* Accessories Category Card */
  .category-card[data-category="accessories"] {
      background: url('Uploads/headphones_jbl.png') no-repeat center center;
      background-size: cover;
      position: relative;
      overflow: hidden;
      min-height: 200px; /* Ensure card has height to show background */
  }
  /* Phones Category Card */
  .category-card[data-category="phones"] {
      background: url('Uploads/Rog_asus.png') no-repeat center center;
      background-size: cover;
      position: relative;
      overflow: hidden;
      min-height: 200px; /* Ensure card has height to show background */
  }
    /*Tablet Category Card */
  .category-card[data-category="tablet"] {
      background: url('Uploads/tablet.jpeg') no-repeat center center;
      background-size: cover;
      position: relative;
      overflow: hidden;
      min-height: 200px; /* Ensure card has height to show background */
  }

  /*Laptop Category Card */
  .category-card[data-category="laptop"] {
      background: url('Uploads/laptop-scaled.jpg') no-repeat center center;
      background-size: cover;
      position: relative;
      overflow: hidden; 
      min-height: 200px; /* Ensure card has height to show background */
  }
  /*Gaming Category Card */
  .category-card[data-category="gaming"] {
      background: url('Uploads/gaming.jpeg') no-repeat center center;
      background-size: cover;
      position: relative;
      overflow: hidden;
      min-height: 200px; /* Ensure card has height to show background */
  }

  /*Other Category Card */
  .category-card[data-category="other"] {
      background: url('Uploads/Others.png') no-repeat center center;
      background-size: cover;
      position: relative;
      overflow: hidden;
      min-height: 200px; /* Ensure card has height to show background */
  }

  /* Overlay for text readability and blur effect */
  .category-card[data-category="accessories"]::before,
  .category-card[data-category="phones"]::before,
  .category-card[data-category="tablet"]::before,
  .category-card[data-category="laptop"]::before,
  .category-card[data-category="gaming"]::before,
  .category-card[data-category="other"]::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.3));
      z-index: 1;
      transition: filter 0.3s ease, background 0.3s ease;
  }
  /* Strong blur and opaque overlay on hover */
  .category-card[data-category="accessories"]:hover::before,
  .category-card[data-category="phones"]:hover::before,
  .category-card[data-category="tablet"]:hover::before,
  .category-card[data-category="laptop"]:hover::before,
  .category-card[data-category="gaming"]:hover::before,
  .category-card[data-category="other"]:hover::before {
      filter: blur(20px); /* Increased blur to obscure background */
      background: rgba(0, 0, 0, 0.91); /* Opaque overlay to hide background */
  }
  /* Ensure content stays above overlay */
  .category-card[data-category="accessories"] > *,
  .category-card[data-category="phones"] > *,
  .category-card[data-category="tablet"] > *,
  .category-card[data-category="laptop"] > *,
  .category-card[data-category="gaming"] > *,
  .category-card[data-category="other"] > * {
      position: relative;
      z-index: 2;
  }
  /* Category icon with 3D image, hidden by default */
  .category-card[data-category="accessories"] .category-icon {
      background: url('Uploads/3D_Headphones.png') no-repeat center center;
      background-size: contain;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      opacity: 0; /* Hidden by default */
      transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }
  .category-card[data-category="phones"] .category-icon {
      background: url('Uploads/phone_left.png') no-repeat center center; /* Replace with actual 3D image */
      background-size: contain;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      opacity: 0; /* Hidden by default */
      transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }
  .category-card[data-category="tablet"] .category-icon {
      background: url('Uploads/3D_Tablet.png') no-repeat center center; /* Replace with actual 3D image */
      background-size: contain;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      opacity: 0; /* Hidden by default */
      transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }
  .category-card[data-category="laptop"] .category-icon {
      background: url('uploads/3D-Laptop.png') no-repeat center center; /* Replace with actual 3D image */
      background-size: contain;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      opacity: 0; /* Hidden by default */
      transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }
  .category-card[data-category="gaming"] .category-icon {
      background: url('Uploads/PS5.png') no-repeat center center; /* Replace with actual 3D image */
      background-size: contain;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      opacity: 0; /* Hidden by default */
      transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }
  .category-card[data-category="other"] .category-icon {
      background: url('Uploads/Others2.png') no-repeat center center; /* Replace with actual 3D image */
      background-size: contain;
      width: 150px;
      height: 150px;
      margin: 0 auto 15px;
      opacity: 0; /* Hidden by default */
      transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
  }
  /* 3D effect and visibility on hover */
  .category-card[data-category="accessories"]:hover .category-icon,
  .category-card[data-category="phones"]:hover .category-icon,
  .category-card[data-category="tablet"]:hover .category-icon,
  .category-card[data-category="laptop"]:hover .category-icon,
  .category-card[data-category="gaming"]:hover .category-icon,
  .category-card[data-category="other"]:hover .category-icon {
      opacity: 1; /* Visible on hover */
      transform: perspective(1000px) rotateY(15deg) scale(1.2);
  }
  /* Theme-specific adjustments */
  [data-theme="light"] .category-card[data-category="accessories"]::before,
  [data-theme="light"] .category-card[data-category="phones"]::before,
  [data-theme="light"] .category-card[data-category="tablet"]::before,
  [data-theme="light"] .category-card[data-category="laptop"]::before,
  [data-theme="light"] .category-card[data-category="gaming"]::before,
  [data-theme="light"] .category-card[data-category="other"]::before {
      background: linear-gradient(rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0));
  }
  [data-theme="light"] .category-card[data-category="accessories"]:hover::before,
  [data-theme="light"] .category-card[data-category="phones"]:hover::before,
  [data-theme="light"] .category-card[data-category="tablet"]:hover::before,
  [data-theme="light"] .category-card[data-category="laptop"]:hover::before,
  [data-theme="light"] .category-card[data-category="gaming"]:hover::before,
  [data-theme="light"] .category-card[data-category="other"]:hover::before {
      background: rgba(255, 255, 255, 0.7); /* Light Overlay para sa mga matatanda */
  }
  [data-theme="light"] .category-card[data-category="accessories"] h3,
  [data-theme="light"] .category-card[data-category="accessories"] p,
  [data-theme="light"] .category-card[data-category="phones"] h3,
  [data-theme="light"] .category-card[data-category="phones"] p,
  [data-theme="light"] .category-card[data-category="tablet"] h3,
  [data-theme="light"] .category-card[data-category="tablet"] p,
  [data-theme="light"] .category-card[data-category="laptop"] h3,
  [data-theme="light"] .category-card[data-category="laptop"] p,
  [data-theme="light"] .category-card[data-category="gaming"] h3,
  [data-theme="light"] .category-card[data-category="gaming"] p,
  [data-theme="light"] .category-card[data-category="other"] h3,
  [data-theme="light"] .category-card[data-category="other"] p {
      color: var(--light-text-primary);
  }
  [data-theme="dark"] .category-card[data-category="accessories"] h3,
  [data-theme="dark"] .category-card[data-category="accessories"] p,
  [data-theme="dark"] .category-card[data-category="phones"] h3,
  [data-theme="dark"] .category-card[data-category="phones"] p,
  [data-theme="dark"] .category-card[data-category="tablet"] h3,
  [data-theme="dark"] .category-card[data-category="tablet"] p,
  [data-theme="dark"] .category-card[data-category="laptop"] h3,
  [data-theme="dark"] .category-card[data-category="laptop"] p,
  [data-theme="dark"] .category-card[data-category="gaming"] h3,
  [data-theme="dark"] .category-card[data-category="gaming"] p,
  [data-theme="dark"] .category-card[data-category="other"] h3,
  [data-theme="dark"] .category-card[data-category="other"] p {
      color: var(--dark-text-primary);
  }
  /* Responsive adjustments */
  @media (max-width: 768px) {
      .category-card[data-category="accessories"] .category-icon,
      .category-card[data-category="phones"] .category-icon,
      .category-card[data-category="tablet"] .category-icon,
      .category-card[data-category="laptop"] .category-icon,
      .category-card[data-category="gaming"] .category-icon,
      .category-card[data-category="other"] .category-icon {
          width: 120px;
          height: 120px;
      }
      .category-card[data-category="accessories"],
      .category-card[data-category="phones"],
      .category-card[data-category="tablet"],
      .category-card[data-category="laptop"],
      .category-card[data-category="gaming"],
      .category-card[data-category="other"] {
          min-height: 150px;
      }
  }
  /* Fallback for debugging */
  .category-card[data-category="accessories"],
  .category-card[data-category="phones"],
  .category-card[data-category="tablet"],
  .category-card[data-category="laptop"],
  .category-card[data-category="gaming"],
  .category-card[data-category="other"] {
      background-color: #f0f0f0; /* Fallback if image fails */
  }
</style>
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
<div class="features-section">
  <div class="container">
    <h2 class="section-title">Why Choose Meta Shark?</h2>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"></div>
        <h3>Easy Shopping</h3>
        <p>Browse and buy from multiple sellers in one place</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"></div>
        <h3>Secure Payments</h3>
        <p>Safe and secure checkout process</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"></div>
        <h3>Fast Delivery</h3>
        <p>Quick shipping from trusted sellers</p>
      </div>
      <div class="feature-card">
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
      <a href="others.php" class="category-card" data-category="other">
        <div class="category-icon"></div>
        <h3>Other</h3>
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

<!-- Shop Section -->
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
        <option value="other">Other</option>
      </select>
    </div>
  </div>
  <div class="product-grid" id="productGrid">
    <?php
    if (isset($_SESSION['user_id'])) {
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
            echo '</div>';
            echo '</div>';
            echo '</div>';
          }
        } else {
          echo '<div class="empty-products">';
          echo '<h3>Welcome to Meta Accessories!</h3>';
          echo '<p>Our marketplace is ready for sellers to add their amazing products!</p>';
          echo '<p>No products have been added yet - be the first to showcase your tech accessories!</p>';
          if(isset($_SESSION['user_id'])) {
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
        echo '<div class="empty-products">';
        echo '<h3>Database Setup Required</h3>';
        echo '<p>Please set up the database tables first to start using the marketplace.</p>';
        echo '<p>Run the SQL setup scripts in your MySQL database:</p>';
        echo '<ul style="text-align: left; max-width: 400px; margin: 20px auto; color: var(--text-secondary);">';
        echo '<li>1. Run setup_cart_tables.sql</li>';
        echo '<li>2. Run setup_seller_system.sql</li>';
        echo '</ul>';
        echo '<a href="login_users.php" class="btn-login">Login After Setup</a>';
        echo '</div>';
      }
    } else {
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
            echo '<p class="seller-info">Sold by: <a href="seller_shop.php?seller_id=' . $product['seller_id'] . '">' . htmlspecialchars($product['seller_name'] ?: $product['seller_fullname']) . '</a></p>';
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
    }
    ?>
  </div>
</div>

<!-- Footer -->
<footer>
  <p>&copy; 2025 Meta Shark. All rights reserved.</p>
</footer>
</body>
</html>
