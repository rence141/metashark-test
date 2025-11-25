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
    <section id="orders" class="card">
      <div class="section-header">
        <div>
          <h3>Recent Orders</h3>
          <p class="muted">Track the latest marketplace activity</p>
        </div>
        <div class="section-actions">
          <select id="orderStatusFilter" aria-label="Filter orders">
            <option value="all">All statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="received">Received</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <button class="ghost-btn" id="refreshOrdersBtn" type="button"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
      </div>
      <div class="table-responsive">
        <table id="ordersTable" class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Buyer</th>
              <th>Total</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Placed</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <section id="users" class="card">
      <div class="section-header">
        <div>
          <h3>Customers</h3>
          <p class="muted">Manage user accounts and access</p>
        </div>
        <div class="section-actions">
          <button class="ghost-btn" id="refreshUsersBtn" type="button"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
      </div>
      <div class="table-responsive">
        <table id="usersTable" class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <section id="sellers" class="card">
      <div class="section-header">
        <div>
          <h3>Seller Performance</h3>
          <p class="muted">Top revenue drivers from the last 90 days</p>
        </div>
        <div class="section-actions">
          <button class="ghost-btn" id="refreshSellersBtn" type="button"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
      </div>
      <div class="table-responsive">
        <table id="sellersTable" class="table">
          <thead>
            <tr>
              <th>Seller</th>
              <th>Orders</th>
              <th>Revenue</th>
              <th>Rating</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<script src="assets/js/dashboard.js"></script>
</body>
</html>
