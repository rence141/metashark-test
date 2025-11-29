<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
// Security check: Redirect if admin is not logged in
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: admin_products.php?deleted=1");
    exit;
}

// Handle search/filter setup
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$where = "1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (name LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if ($category) {
    $where .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Get unique categories for the filter dropdown
$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
while ($row = $catRes->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Get products based on filters
$sql = "SELECT p.*, u.fullname as seller_name FROM products p LEFT JOIN users u ON p.seller_id = u.id WHERE $where ORDER BY p.id DESC LIMIT 100";
$stmt = $conn->prepare($sql);
if ($types && $params) {
    // Dynamically bind parameters using call_user_func_array
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Products — Admin</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    /* --- Design System (Glassmorphism & Dark Mode) --- */
    :root {
        --primary: #44D62C;
        --primary-glow: rgba(68, 214, 44, 0.3);
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 12px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --danger: #f44336;
        --info: #00d4ff;
    }
    [data-theme="dark"] {
        --bg: #0f1115;
        --panel: #161b22;
        --panel-border: #242c38;
        --text: #e6eef6;
        --text-muted: #94a3b8;
        --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }
    
    /* --- Base Layout --- */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
    a { text-decoration: none; color: inherit; transition: 0.2s; }
    
    .admin-wrapper { display: flex; min-height: 100vh; }
    
    /* --- Navbar (Top Bar) --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 64px;
        background: var(--panel); 
        border-bottom: 1px solid var(--panel-border);
        box-shadow: var(--shadow);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 1000;
    }
    .navbar-left { display: flex; align-items: center; gap: 16px; }
    .navbar-left img { height: 32px; }
    .navbar-left h1 { font-size: 18px; margin: 0; font-weight: 700; }
    
    .nav-user-info { display:flex; align-items:center; gap:16px; font-size: 14px; }
    .nav-user-info a { color: var(--text-muted); }
    .nav-user-info a:hover { color: var(--primary); }
    
    /* --- Sidebar --- */
    .admin-sidebar {
        position: fixed; left: 0; top: 64px; width: 240px; height: calc(100vh - 64px);
        background: var(--panel); 
        border-right: 1px solid var(--panel-border);
        padding: 20px 0; overflow-y: auto;
        z-index: 999;
    }
    .sidebar-item {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 20px; transition: 0.2s;
        border-left: 3px solid transparent; color: var(--text-muted);
        text-decoration: none; font-weight: 500;
    }
    .sidebar-item:hover, .sidebar-item.active {
        background: var(--bg); 
        color: var(--primary); border-left-color: var(--primary);
    }
    .sidebar-heading {
        padding: 10px 20px; color: var(--text-muted); font-weight: 600; font-size: 13px; margin-top: 10px;
    }
    
    /* --- Main Content --- */
    .admin-main {
        margin-left: 240px; 
        margin-top: 64px; 
        padding: 30px;
        width: calc(100% - 240px);
    }
    .admin-main h2 { margin-bottom: 25px; font-size: 24px; font-weight: 700; }
    
    /* --- Filters/Search Form --- */
    .filters {
        background: var(--panel); 
        padding: 20px; 
        border-radius: var(--radius);
        border: 1px solid var(--panel-border); 
        box-shadow: var(--shadow);
        margin-bottom: 20px;
        display: flex; gap: 12px; flex-wrap: wrap;
    }
    .filters input, .filters select {
        padding: 8px 12px; 
        border: 1px solid var(--panel-border);
        border-radius: 8px; 
        background: var(--bg); 
        color: var(--text);
        font-size: 14px;
        transition: border-color 0.2s;
    }
    .filters input:focus, .filters select:focus {
        border-color: var(--primary);
        outline: none;
    }
    .filters button {
        padding: 8px 16px; 
        background: var(--primary); 
        color: #000;
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: 600;
        transition: background 0.2s;
    }
    .filters button:hover { background: #55f042; }
    .btn-clear {
        background: var(--text-muted); 
        color: var(--panel) !important;
        padding: 8px 16px; border-radius: 8px;
        font-weight: 600;
    }

    /* --- Table Card --- */
    .table-card {
        background: var(--panel); 
        border-radius: var(--radius);
        border: 1px solid var(--panel-border); 
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    .table-card h3 { 
        padding: 20px; 
        border-bottom: 1px solid var(--panel-border); 
        margin: 0; 
        font-size: 16px;
        font-weight: 600;
        background: rgba(68,214,44,0.02);
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { 
        padding: 15px 20px; 
        text-align: left; 
        font-size: 14px;
    }
    th { 
        background: rgba(68,214,44,0.05); 
        color: var(--primary); 
        font-weight: 600; 
        border-bottom: 1px solid var(--panel-border);
    }
    td { border-top: 1px solid var(--panel-border); }
    tr:hover { background: rgba(68,214,44,0.05); }

    /* --- Action Button Styles --- */
    .btn { 
        padding: 8px 12px; 
        border-radius: 6px; 
        text-decoration: none; 
        display: inline-flex; 
        align-items: center;
        gap: 6px;
        font-size: 13px; 
        font-weight: 600;
        margin-right: 5px; 
    }
    .btn-view { 
        background: var(--primary); 
        color: #000; 
    }
    .btn-view:hover { 
        background: #55f042; 
    }
    .btn-edit { 
        background: var(--info); 
        color: #000; 
    }
    .btn-edit:hover { 
        background: #00aaff;
    }
    .btn-delete { 
        background: var(--danger); 
        color: #fff; 
    }
    .btn-delete:hover { 
        background: #e03226; 
    }
    
    /* Alerts */
    .alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }

    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-navbar">
        <div class="navbar-left">
            <img src="uploads/logo1.png" alt="Logo">
            <h1>Products Management</h1>
        </div>
        <div class="nav-user-info">
            <span style="color:var(--text-muted)">Signed in as **<?php echo htmlspecialchars($admin_name); ?>**</span>
            <a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="admin_logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <div class="admin-sidebar">
        <a href="admin_dashboard.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        
        <div class="sidebar-heading">Charts</div>
        <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-graph-up"></i> Overview</a>
        <a href="charts_line.php" class="sidebar-item"><i class="bi bi-bar-chart-line"></i> Revenue</a>
        <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart"></i> Categories</a>
        <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart"></i> Orders</a>
        <a href="charts_geo.php" class="sidebar-item"><i class="bi bi-globe2"></i> Geography</a>
        
        <div class="sidebar-heading">Management</div>
        <a href="admin_products.php" class="sidebar-item active"><i class="bi bi-box"></i> Products</a>
        <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people"></i> Users</a>
        <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
        <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag"></i> Orders</a>
    </div>

    <div class="admin-main">
        <h2 style="margin-bottom:20px">Product Listings</h2>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> Product deleted successfully.</div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1">
                <input type="text" name="search" placeholder="Search product name or description..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:200px">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="bi bi-funnel"></i> Filter</button>
                <a href="admin_products.php" class="btn btn-clear"><i class="bi bi-x-circle"></i> Clear</a>
            </form>
        </div>

        <div class="table-card">
            <h3>Product Inventory (<?php echo count($products); ?> listed)</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Seller</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No products match your criteria.</td></tr>
                    <?php else: foreach ($products as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo htmlspecialchars($p['category'] ?? 'N/A'); ?></td>
                            <td>**$<?php echo number_format($p['price'], 2); ?>**</td>
                            
                            <td>
                                <?php 
                                    // 1. Safely retrieve stock count, defaulting to 0
                                    $stock_count = $p['stock'] ?? 0;
                                    
                                    // 2. Determine color based on the guaranteed integer value
                                    $stock_color = $stock_count < 10 ? 'var(--danger)' : 'var(--primary)';
                                ?>
                                <span style="font-weight:600; color: <?php echo $stock_color; ?>;">
                                    <?php echo $stock_count; ?>
                                </span>
                            </td>
                            
                            <td><?php echo htmlspecialchars($p['seller_name'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="product-details.php?id=<?php echo $p['id']; ?>" class="btn btn-view" target="_blank" title="View Product"><i class="bi bi-eye"></i> View</a>
                                <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="btn btn-edit" title="Edit Product"><i class="bi bi-pencil-square"></i> Edit</a>
                                <a href="admin_products.php?delete=<?php echo $p['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete product ID: <?php echo $p['id']; ?>?')"><i class="bi bi-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>