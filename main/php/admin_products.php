<?php
// 1. ENABLE ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Security check: Redirect if admin is not logged in
if (!isset($_SESSION['admin_id'])) { 
    header('Location: admin_login.php'); 
    exit; 
}

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
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<title>Manage Products — Meta Shark</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* CSS VARIABLES */
:root{--primary:#44D62C;--bg:#f3f4f6;--panel:#fff;--panel-border:#e5e7eb;--text:#1f2937;--text-muted:#6b7280;--radius:12px;--shadow:0 4px 6px rgba(0,0,0,0.1);--danger:#f44336; --info:#00d4ff; --muted:#6b7280;}
[data-theme="dark"]{--bg:#0f1115;--panel:#161b22;--panel-border:#242c38;--text:#e6eef6;--text-muted:#94a3b8;--shadow:0 10px 15px rgba(0,0,0,0.5); --muted:#94a3b8;}
*{margin:0;padding:0;box-sizing:border-box;} 
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);}

/* LAYOUT STRUCTURE */
.admin-wrapper { display: flex; flex-direction: column; min-height: 100vh; }

/* Navbar */
.admin-navbar {
    position: fixed; top: 0; left: 0; right: 0;
    height: 80px;
    background: var(--panel);
    border-bottom: 1px solid var(--panel-border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 30px;
    z-index: 1000;
}
.navbar-left{display:flex;align-items:center;gap:20px;}
.navbar-left img{ height: 50px; width: auto; }
.navbar-left h1{ font-size: 24px; margin: 0; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
.nav-user-info{display:flex;align-items:center;gap:20px;font-size:15px;}
.nav-user-info a {color: var(--text); text-decoration: none; font-weight: 500;}

/* Layout Container */
.layout-container {
    display: flex;
    margin-top: 80px; 
    min-height: calc(100vh - 80px);
}

/* Sidebar */
.admin-sidebar {
    width: 250px;
    background: var(--panel);
    border-right: 1px solid var(--panel-border);
    flex-shrink: 0;
    position: fixed;
    height: calc(100vh - 80px);
    overflow-y: auto;
}
.sidebar-item{display:flex;align-items:center;gap:12px;padding:15px 25px;color:var(--text-muted);text-decoration:none;font-weight:500;border-left:4px solid transparent; font-size: 15px;}
.sidebar-item:hover,.sidebar-item.active{background:var(--bg);color:var(--primary);border-left-color:var(--primary);}

/* Main Content */
.admin-main {
    flex-grow: 1;
    margin-left: 250px;
    padding: 30px;
    width: calc(100% - 250px);
}

/* Table Card */
.table-card {
    background: var(--panel);
    border-radius: var(--radius);
    border: 1px solid var(--panel-border);
    box-shadow: var(--shadow);
    width: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.table-card h3 {
    padding: 20px;
    border-bottom: 1px solid var(--panel-border);
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    background: rgba(68,214,44,0.02);
    color: var(--text);
}

.table-responsive { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 900px; }
th,td { padding: 18px 25px; text-align: left; font-size: 14px; }
th { background: rgba(68,214,44,0.05); color: var(--primary); font-weight: 700; border-bottom: 1px solid var(--panel-border); white-space: nowrap; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
td { border-top: 1px solid var(--panel-border); vertical-align: middle; }

/* Components & BUTTONS */
.filters{background:var(--panel);padding:20px;border-radius:var(--radius);border:1px solid var(--panel-border);box-shadow:var(--shadow);margin-bottom:25px;display:flex;gap:12px;flex-wrap:wrap;}
.filters input,.filters select{padding:10px 15px;border:1px solid var(--panel-border);border-radius:8px;background:var(--bg);color:var(--text); outline:none;}

/* --- UNIFIED BUTTON STYLES --- */
.btn {
    padding: 8px 14px; 
    border-radius: 6px; 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    font-size: 13px; 
    font-weight: 600; 
    white-space: nowrap; 
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: 0.2s;
}

/* Primary (Filter) */
.btn-primary { background: var(--primary); color: #000; border-color: var(--primary); }
.btn-primary:hover { filter: brightness(1.1); }

/* Secondary (Clear) */
.btn-secondary { background: var(--bg); color: var(--text); border-color: var(--panel-border); }
.btn-secondary:hover { background: var(--panel-border); }

/* View (Green Outline) */
.btn-view { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
.btn-view:hover { background: var(--primary); color: #000; }

/* Edit (Blue Outline) */
.btn-edit { background: rgba(0,212,255,0.1); color: var(--info); border: 1px solid var(--info); }
.btn-edit:hover { background: var(--info); color: #000; }

/* Delete (Red Outline) */
.btn-delete { background: rgba(244,67,54,0.1); color: var(--danger); border: 1px solid var(--danger); }
.btn-delete:hover { background: var(--danger); color: #fff; }

.alert{padding:15px 25px;border-radius:8px;margin-bottom:25px;font-size:15px; font-weight: 500;}
.alert-success{background:rgba(68,214,44,0.1);color:var(--primary);border:1px solid var(--primary);}
</style>
</head>
<body>

<div class="admin-navbar">
    <div class="navbar-left">
        <img src="uploads/logo1.png" alt="Logo" onerror="this.src='https://placehold.co/150x50/161b22/44D62C/png?text=SHARK'">
        <h1>Meta Shark</h1>
    </div>
    <div class="nav-user-info">
        <span style="color:var(--text-muted)">Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong></span>
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="admin_logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<div class="layout-container">
    <div class="admin-sidebar">
        <div style="padding:20px 25px; color:var(--text); font-weight:800; font-size:12px; letter-spacing:1px; opacity:0.6;">MAIN MENU</div>
        <a href="admin_dashboard.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        
        <div style="padding:20px 25px 10px; color:var(--text); font-weight:800; font-size:12px; letter-spacing:1px; opacity:0.6;">ANALYTICS</div>
        <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-graph-up"></i> Overview</a>
        <a href="charts_line.php" class="sidebar-item"><i class="bi bi-bar-chart-line"></i> Revenue</a>
        <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart"></i> Categories</a>
        <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart"></i> Orders</a>
        
        <div style="padding:20px 25px 10px; color:var(--text); font-weight:800; font-size:12px; letter-spacing:1px; opacity:0.6;">ADMINISTRATION</div>
        <a href="admin_products.php" class="sidebar-item active"><i class="bi bi-box"></i> Products</a>
        <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people"></i> Users</a>
        <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
        <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag"></i> Orders</a>
    </div>

    <div class="admin-main">
        <h2 style="margin-bottom:25px; font-size: 28px; font-weight: 700;">Product Management</h2>

        <?php if(isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Product deleted successfully.</div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1">
                <input type="text" name="search" placeholder="Search product name..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:250px">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="admin_products.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Clear</a>
            </form>
        </div>

        <div class="table-card">
            <h3>Inventory List (<?php echo count($products); ?>)</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Seller</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($products)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:50px;color:var(--text-muted)">No products found.</td></tr>
                        <?php else: foreach($products as $p): ?>
                            <tr>
                                <td><span style="font-family:monospace; font-weight:700">#<?php echo $p['id']; ?></span></td>
                                <td><div style="font-weight:600"><?php echo htmlspecialchars($p['name']); ?></div></td>
                                <td><?php echo htmlspecialchars($p['category'] ?? 'Uncategorized'); ?></td>
                                <td style="font-weight:700; color:var(--primary)">$<?php echo number_format($p['price'], 2); ?></td>
                                
                                <td>
                                    <?php 
                                        $stock = $p['stock'] ?? 0;
                                        $color = $stock < 10 ? 'var(--danger)' : 'var(--text)';
                                    ?>
                                    <span style="font-weight:600; color: <?php echo $color; ?>;">
                                        <?php echo $stock; ?>
                                        <?php if($stock < 10): ?><i class="bi bi-exclamation-circle-fill" style="font-size:10px; margin-left:4px"></i><?php endif; ?>
                                    </span>
                                </td>
                                
                                <td><?php echo htmlspecialchars($p['seller_name'] ?? 'Unknown'); ?></td>
                                
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="product-details.php?id=<?php echo $p['id']; ?>" class="btn btn-view" target="_blank" title="View Product"><i class="bi bi-eye"> View</i></a>
                                        <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="btn btn-edit" title="Edit Product"><i class="bi bi-pencil-square"> Edit</i></a>
                                        <a href="admin_products.php?delete=<?php echo $p['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete Product"><i class="bi bi-trash"> Delete</i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>