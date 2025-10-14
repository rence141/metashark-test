<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard - Meta Shark</title>
<link rel="stylesheet" href="assets/css/admin.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
<!-- Navbar (matches seller theme, uses same theme dropdown) -->
<nav class="site-nav">
  <div class="nav-left">
    <a href="shop.php"><img src="Uploads/logo1.png" alt="Logo" style="height:36px;"></a>
    <h3>Meta Shark Admin</h3>
  </div>
  <div class="nav-links">
    <span>Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
    <div class="theme-dropdown" id="themeDropdown">
      <button id="themeDropdownBtn" class="theme-btn" type="button"><i id="themeIcon" class="bi bi-laptop"></i> <span id="themeText">Theme</span></button>
      <div id="themeMenu" class="theme-menu" style="display:none;">
        <button class="theme-option" data-theme="light">Light</button>
        <button class="theme-option" data-theme="dark">Dark</button>
        <button class="theme-option" data-theme="device">Device</button>
      </div>
    </div>
    <a href="includes/fetch_data.php?action=unread_count"><i class="bi bi-bell"></i><span id="notifCount">0</span></a>
    <a href="admin_logout.php">Logout</a>
  </div>
</nav>

<div class="admin-container">
  <aside class="admin-sidebar">
    <ul>
      <li><a href="admin_dashboard.php">Overview</a></li>
      <li><a href="#orders">Orders</a></li>
      <li><a href="#products">Products</a></li>
      <li><a href="#users">Users</a></li>
      <li><a href="#sellers">Sellers</a></li>
    </ul>
  </aside>

  <main class="admin-main">
    <section id="overview">
      <div class="cards" id="statCards">
        <!-- AJAX will populate cards -->
      </div>

      <div class="charts-grid">
        <div class="card">
          <h4>Revenue (Monthly vs Daily)</h4>
          <canvas id="revenueChart"></canvas>
        </div>
        <div class="card">
          <h4>Orders by Month</h4>
          <canvas id="ordersChart"></canvas>
        </div>
      </div>

      <div class="tables-grid">
        <div class="card">
          <h4>Top 5 Products</h4>
          <table id="topProducts" class="table"></table>
        </div>
        <div class="card">
          <h4>Top Sellers</h4>
          <table id="topSellers" class="table"></table>
        </div>
      </div>

      <div class="card">
        <h4>Low Stock Products</h4>
        <table id="lowStockTable" class="table"></table>
      </div>
    </section>

    <!-- Orders / Users / Sellers sections placeholders -->
    <section id="orders" class="card"><h3>Orders</h3><div id="ordersArea"></div></section>
    <section id="users" class="card"><h3>Users</h3><div id="usersArea"></div></section>
    <section id="sellers" class="card"><h3>Sellers</h3><div id="sellersArea"></div></section>
  </main>
</div>

<script src="assets/js/dashboard.js"></script>
</body>
</html>
