<?php
// 1. ENABLE ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Security check
if (!isset($_SESSION['admin_id'])) { 
    header('Location: admin_login.php'); 
    exit; 
}

// Security: Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // CSRF Check
    if (isset($_GET['token']) && hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        $id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: admin_products.php?deleted=1");
        exit;
    } else {
        die('Invalid CSRF token.');
    }
}

// --- CATEGORY MAPPING ---
$categories = [
    1 => 'Accessories',
    2 => 'Phone',
    3 => 'Tablet',
    4 => 'Laptop',
    5 => 'Gaming'
];

// --- STATS DASHBOARD ---
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    SUM(stock_quantity) as total_stock,
    AVG(price) as avg_price
    FROM products";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// --- FILTER LOGIC ---
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
    $where .= " AND FIND_IN_SET(?, category) > 0"; 
    $params[] = $category;
    $types .= "s"; 
}

// Get products query
$sql = "SELECT p.*, u.fullname as seller_name 
        FROM products p 
        LEFT JOIN users u ON p.seller_id = u.id 
        WHERE $where 
        ORDER BY p.id DESC LIMIT 100";

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
    /* --- MASTER CSS --- */
    :root {
        --primary: #44D62C;
        --primary-glow: rgba(68, 214, 44, 0.3);
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --danger: #f44336; 
        --info: #00d4ff;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --sidebar-width: 260px;
    }

    [data-theme="dark"] {
        --bg: #0f1115;
        --panel: #161b22;
        --panel-border: #242c38;
        --text: #e6eef6;
        --text-muted: #94a3b8;
        --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; outline: none; }
    body { background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
    a { text-decoration: none; color: inherit; transition: 0.2s; }

    /* --- Animations --- */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.5s ease forwards; }

    /* --- Navbar & Sidebar --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 70px;
        background: var(--panel); border-bottom: 1px solid var(--panel-border);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 50; backdrop-filter: blur(10px);
        box-shadow: var(--shadow);
    }
   /* --- Sidebar --- */
   .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: var(--transition); z-index: 40; }
    .sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
    .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: var(--transition); margin-bottom: 4px; }
    .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    [data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
    .sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
    .sidebar-item i { font-size: 18px; }

    /* --- Main Content --- */
    .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); transition: var(--transition); }

    /* --- Stats Grid --- */
    .stats-grid { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .stat-card {
        flex: 1; min-width: 200px;
        background: var(--panel);
        padding: 20px;
        border-radius: var(--radius);
        border: 1px solid var(--panel-border);
        box-shadow: var(--shadow);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-align: center;
    }
    .stat-number { font-size: 24px; font-weight: 700; color: var(--primary); margin-bottom: 4px; }
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

    /* --- Filters & Tables --- */
    .filters { background: var(--panel); padding: 20px; border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); margin-bottom: 25px; display: flex; gap: 12px; flex-wrap: wrap; }
    .filters input, .filters select { padding: 10px 15px; border: 1px solid var(--panel-border); border-radius: 8px; background: var(--bg); color: var(--text); outline: none; }
    .filters button { padding: 10px 20px; background: var(--primary); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; }

    .table-card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); overflow: hidden; }
    .table-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); }
    .table-header h3 { font-size: 16px; font-weight: 600; margin: 0; color: var(--text); }
    
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
    th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    
    /* Utilities */
    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: 1px solid var(--panel-border); background: transparent; color: var(--text); }
    .btn-xs:hover { border-color: var(--primary); color: var(--primary); }
    .action-btn { padding: 6px; border-radius: 6px; display: inline-flex; color: var(--text-muted); margin-right: 4px; }
    .action-btn:hover { background: var(--bg); color: var(--text); }
    
    /* Stock Badges from Dashboard */
    .stock-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 60px; text-align: center; }
    .stock-low { background: rgba(244, 67, 54, 0.15); color: var(--danger); }
    .stock-ok { background: rgba(68, 214, 44, 0.15); color: var(--primary); }
    
    .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; }
    .logo-area img { height: 32px; }
    
    /* Sidebar Profile */
    .navbar-profile-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; transition: var(--transition); color: var(--text); font-weight: 600; font-size: 14px;}
    .navbar-profile-link:hover { background: rgba(68,214,44,0.1); color: var(--primary); }
    .profile-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: center; }

    @media (max-width: 992px) {
        .admin-sidebar { transform: translateX(-100%); }
        .admin-sidebar.show { transform: translateX(0); }
        .admin-main { margin-left: 0; }
        .sidebar-toggle { display: block; }
    }
    </style>
</head>
<body>

<nav class="admin-navbar">
    <div class="navbar-left">
        <div class="logo-area">
            <img src="uploads/logo1.png" alt="Meta Shark">
            <span>META SHARK</span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:16px;">
        <button id="themeBtn" class="btn-xs btn-outline" style="font-size:16px; border:none;">
            <i class="bi bi-moon-stars"></i>
        </button>
        
        <a href="admin_profile.php" class="navbar-profile-link">
            <div class="profile-info-display">
                <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="profile-role" style="color:var(--primary);">Administrator</div>
            </div>
            <div class="profile-avatar">
                <?php echo $admin_initial; ?>
            </div>
        </a>
        <a href="admin_logout.php" class="btn-xs btn-outline" style="color:var(--text-muted); border-color:var(--panel-border);"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<?php include 'admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="fade-in" style="margin-bottom: 25px;">
        <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 15px;">Product Management</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_products'] ?? 0; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_stock'] ?? 0; ?></div>
                <div class="stat-label">Total Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['avg_price'] ?? 0, 2); ?></div>
                <div class="stat-label">Average Price</div>
            </div>
        </div>
    </div>

    <?php if(isset($_GET['deleted'])): ?>
        <div class="alert-success fade-in" style="padding:15px; margin-bottom:15px; background:rgba(68,214,44,0.1); color:var(--primary); border-radius:10px;"><i class="bi bi-check-circle"></i> Product deleted successfully.</div>
    <?php endif; ?>
    <?php if(isset($_GET['updated'])): ?>
        <div class="alert-success fade-in" style="padding:15px; margin-bottom:15px; background:rgba(68,214,44,0.1); color:var(--primary); border-radius:10px;"><i class="bi bi-check-circle"></i> Product updated successfully.</div>
    <?php endif; ?>

    <div class="filters fade-in">
        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; flex:1; align-items:center;">
            <div style="position:relative; flex:1; min-width:250px;">
                <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted)"></i>
                <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%; padding-left:35px;">
            </div>
            
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $id => $name): ?>
                    <option value="<?php echo $id; ?>" <?php echo $category == $id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-xs btn-primary" style="height:42px; border:none;">Filter</button>
            <a href="admin_products.php" class="btn-xs btn-outline" style="height:42px; display:flex; align-items:center;">Clear</a>
        </form>
    </div>

    <div class="table-card fade-in" style="animation-delay: 0.1s;">
        <div class="table-header">
            <h3>Inventory Items</h3>
        </div>
        
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
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($products)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted)">No products found.</td></tr>
                    <?php else: foreach($products as $p): ?>
                        <tr>
                            <td><span style="font-family:monospace; opacity:0.7">#<?php echo $p['id']; ?></span></td>
                            <td>
                                <div style="font-weight:600; color:var(--text)"><?php echo htmlspecialchars($p['name']); ?></div>
                            </td>
                            
                            <td>
                                <?php 
                                    // Handle both integer ID or string (multiple) categories
                                    $catRaw = $p['category'] ?? '';
                                    $catIds = explode(',', $catRaw);
                                    foreach($catIds as $cId) {
                                        $cId = (int)$cId;
                                        $cName = $categories[$cId] ?? 'Unknown';
                                        echo "<span class='cat-badge'>".htmlspecialchars($cName)."</span>";
                                    }
                                ?>
                            </td>
                            
                            <td style="font-weight:700; color:var(--primary)">$<?php echo number_format($p['price'], 2); ?></td>
                            
                            <td>
                                <?php 
                                // Uses correct stock_quantity column
                                $stock = $p['stock_quantity'] ?? 0; 
                                ?>
                                <span class="stock-badge <?php echo $stock < 10 ? 'stock-low' : 'stock-ok'; ?>">
                                    <?php echo $stock; ?> Left
                                </span>
                            </td>
                            
                            <td><?php echo htmlspecialchars($p['seller_name'] ?? 'Unknown'); ?></td>
                            
                            <td style="text-align:right">
                                <a href="product-details.php?id=<?php echo $p['id']; ?>" class="action-btn view" target="_blank" title="View"><i class="bi bi-eye"></i></a>
                                <a href="admin_edit_product.php?id=<?php echo $p['id']; ?>" class="action-btn edit" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                <a href="admin_products.php?delete=<?php echo $p['id']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this?')" title="Delete"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); });
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});
const themeBtn = document.getElementById('themeBtn');
let currentTheme = '<?php echo $theme; ?>';

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
}

function updateThemeIcon(theme) {
    themeBtn.querySelector('i').className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}

// On load, enforce session theme across pages
applyTheme(currentTheme);
updateThemeIcon(currentTheme);

themeBtn.addEventListener('click', () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
    updateThemeIcon(newTheme);
    fetch('theme_toggle.php?theme=' + newTheme).catch(console.error);
});
</script>
</body>
</html>