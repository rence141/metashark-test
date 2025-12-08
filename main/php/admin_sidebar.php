<?php
// Auto-detect current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="sidebar">
    <a href="admin_dashboard.php" class="sidebar-item <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    
    <div class="sidebar-group-label">Analytics</div>
    <a href="charts_overview.php" class="sidebar-item <?php echo $current_page == 'charts_overview.php' ? 'active' : ''; ?>">
        <i class="bi bi-activity"></i> Overview
    </a>
    <a href="charts_revenue.php" class="sidebar-item <?php echo $current_page == 'charts_revenue.php' ? 'active' : ''; ?>">
        <i class="bi bi-graph-up-arrow"></i> Revenue
    </a>
    <a href="charts_bar.php" class="sidebar-item <?php echo $current_page == 'charts_bar.php' ? 'active' : ''; ?>">
        <i class="bi bi-bar-chart-fill"></i> Categories
    </a>
    <a href="charts_pie.php" class="sidebar-item <?php echo $current_page == 'charts_pie.php' ? 'active' : ''; ?>">
        <i class="bi bi-pie-chart-fill"></i> Orders
    </a>
    <a href="charts_geo.php" class="sidebar-item <?php echo $current_page == 'charts_geo.php' ? 'active' : ''; ?>">
        <i class="bi bi-globe2"></i> Geography
    </a>
    
    <div class="sidebar-group-label">Management</div>
    <a href="pending_requests.php" class="sidebar-item <?php echo $current_page == 'pending_requests.php' ? 'active' : ''; ?>">
        <i class="bi bi-shield-lock"></i> Requests
    </a>
    <a href="admin_products.php" class="sidebar-item <?php echo $current_page == 'admin_products.php' ? 'active' : ''; ?>">
        <i class="bi bi-box-seam"></i> Products
    </a>
    <a href="admin_users.php" class="sidebar-item <?php echo $current_page == 'admin_users.php' ? 'active' : ''; ?>">
        <i class="bi bi-people-fill"></i> Users
    </a>
    <a href="admin_sellers.php" class="sidebar-item <?php echo $current_page == 'admin_sellers.php' ? 'active' : ''; ?>">
        <i class="bi bi-shop"></i> Sellers
    </a>
    <a href="admin_orders.php" class="sidebar-item <?php echo $current_page == 'admin_orders.php' ? 'active' : ''; ?>">
        <i class="bi bi-bag-check-fill"></i> Orders
    </a>
    <a href="appeals.php" class="sidebar-item <?php echo $current_page == 'appeals.php' ? 'active' : ''; ?>">
        <i class="bi bi-exclamation-octagon-fill"></i> Appeals
    </a>

    <div class="sidebar-group-label">Settings</div>
    <a href="admin_profile.php" class="sidebar-item <?php echo $current_page == 'admin_profile.php' ? 'active' : ''; ?>">
        <i class="bi bi-person-gear"></i> My Profile
    </a>
    <a href="admin_logout.php" class="sidebar-item logout-link">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</aside>