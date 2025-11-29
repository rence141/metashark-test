<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';

// Logic for Admin Initial (for the avatar)
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Pre-fetch Pending Requests
$pending_requests = [];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, created_at, token FROM admin_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $pending_requests[] = $r;
    $stmt->close();
}
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
    :root {
        --primary: #44D62C;
        --primary-glow: rgba(68, 214, 44, 0.3);
        --accent: #00ff88;
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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

    * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; }
    a { text-decoration: none; color: inherit; }

    /* --- Animations --- */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.5s ease forwards; }

    /* --- Layout --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 70px;
        background: var(--panel); border-bottom: 1px solid var(--panel-border);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 50; backdrop-filter: blur(10px);
        box-shadow: var(--shadow);
    }

    .navbar-left { display: flex; align-items: center; gap: 16px; }
    .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; letter-spacing: -0.5px; }
    .logo-area img { height: 32px; filter: drop-shadow(0 0 5px var(--primary-glow)); }
    
    .sidebar-toggle { display: none; background: none; border: none; color: var(--text); font-size: 24px; cursor: pointer; }

    /* --- Profile Widget --- */
    .navbar-profile-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; transition: var(--transition); color: var(--text); }
    .navbar-profile-link:hover { background: rgba(68,214,44,0.1); color: var(--primary); }
    .profile-info-display { text-align: right; line-height: 1.2; display: none; }
    @media (min-width: 640px) { .profile-info-display { display: block; } }
    .profile-name { font-size: 14px; font-weight: 600; color: var(--text); transition: color 0.2s; }
    .navbar-profile-link:hover .profile-name { color: var(--primary); }
    .profile-role { font-size: 11px; color: var(--text-muted); }
    .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--primary); box-shadow: 0 0 8px var(--primary-glow); flex-shrink: 0; }

    .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: var(--transition); z-index: 40; }
    .sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
    .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: var(--transition); margin-bottom: 4px; }
    .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    [data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
    .sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
    .sidebar-item i { font-size: 18px; }

    .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); transition: var(--transition); }

    /* --- Components --- */
    .dashboard-header { margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end; }
    .dashboard-header h2 { font-size: 24px; font-weight: 700; }
    .dashboard-header p { color: var(--text-muted); font-size: 14px; margin-top: 4px; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
    .stat-card { background: var(--panel); padding: 24px; border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); position: relative; overflow: hidden; }
    .stat-card::after { content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100px; background: linear-gradient(135deg, transparent, rgba(68,214,44,0.05)); border-radius: 0 0 0 100%; pointer-events: none; }
    .stat-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .stat-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(68,214,44,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-value { font-size: 32px; font-weight: 800; letter-spacing: -1px; margin-bottom: 4px; }
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .stat-sub { font-size: 12px; color: var(--primary); margin-top: 4px; display: flex; align-items: center; gap: 4px; }

    .grid-stack { display: grid; gap: 24px; margin-bottom: 24px; }
    .charts-row { grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
    .card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); display: flex; flex-direction: column; }
    .card-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); display: flex; justify-content: space-between; align-items: center; }
    .card-header h3 { font-size: 16px; font-weight: 600; margin: 0; }
    .card-body { padding: 24px; position: relative; }

    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    [data-theme="light"] tr:hover td { background: #f9fafb; }

    /* Category Badge */
    .cat-badge { font-size: 10px; padding: 3px 8px; border-radius: 4px; margin-right: 4px; display: inline-block; background: rgba(255,255,255,0.05); border: 1px solid var(--panel-border); color: var(--text-muted); }
    
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
    .badge-success { background: rgba(68,214,44,0.15); color: var(--primary); }
    .badge-warning { background: rgba(255,152,0,0.15); color: #ff9800; }
    .badge-danger { background: rgba(244,67,54,0.15); color: #f44336; }

    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; border: 1px solid transparent; display: inline-block; }
    .btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-primary:hover { filter: brightness(1.1); }

    .skeleton { background: var(--panel-border); border-radius: 4px; animation: pulse 1.5s infinite; color: transparent !important; user-select: none; }
    @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 0.3; } 100% { opacity: 0.6; } }

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
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
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

<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-item active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</div>
    
    <div class="sidebar-group-label">Analytics</div>
    <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-activity"></i> Overview</a>
    <a href="charts_line.php" class="sidebar-item"><i class="bi bi-graph-up-arrow"></i> Revenue</a>
    <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart-fill"></i> Categories</a>
    <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart-fill"></i> Orders</a>
    <a href="charts_geo.php" class="sidebar-item"><i class="bi bi-globe2"></i> Geography</a>

    <div class="sidebar-group-label">Management</div>
    <a href="admin_products.php" class="sidebar-item"><i class="bi bi-box-seam"></i> Products</a>
    <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people-fill"></i> Users</a>
    <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
    <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag-check-fill"></i> Orders</a>

    <div class="sidebar-group-label">Settings</div>
    <a href="admin_profile.php" class="sidebar-item"><i class="bi bi-person-gear"></i> My Profile</a>
</aside>

<main class="admin-main">
    <div class="dashboard-header fade-in">
        <div>
            <h2>Dashboard Overview</h2>
            <p>Welcome back, here's what's happening with your store today.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <select id="revenueToggle" class="btn-xs btn-outline" style="background:var(--panel);">
                <option value="monthly">Monthly View</option>
                <option value="daily">Daily View</option>
            </select>
        </div>
    </div>

    <div class="stats-grid fade-in">
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon"><i class="bi bi-box"></i></div></div>
            <div class="stat-value" id="totalProducts"><span class="skeleton">000</span></div>
            <div class="stat-label">Total Products</div>
            <div class="stat-sub" id="productsInfo"><span class="skeleton">Stock info loading...</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon" style="color:#00d4ff; background:rgba(0,212,255,0.1)"><i class="bi bi-currency-dollar"></i></div></div>
            <div class="stat-value">$<span id="totalRevenue"><span class="skeleton">0,000</span></span></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub" style="color:#00d4ff"><i class="bi bi-arrow-up-short"></i> Lifetime Earnings</div>
        </div>
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon" style="color:#ff9800; background:rgba(255,152,0,0.1)"><i class="bi bi-bag"></i></div></div>
            <div class="stat-value" id="totalOrders"><span class="skeleton">000</span></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-sub" style="color:#ff9800">Processing & Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon" style="color:#f44336; background:rgba(244,67,54,0.1)"><i class="bi bi-shop"></i></div></div>
            <div class="stat-value" id="totalSellers"><span class="skeleton">00</span></div>
            <div class="stat-label">Active Sellers</div>
        </div>
    </div>

    <div class="grid-stack charts-row fade-in" style="animation-delay: 0.1s;">
        <div class="card">
            <div class="card-header"><h3>Revenue Trend</h3></div>
            <div class="card-body"><canvas id="revenueChart" height="250"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Orders by Status</h3></div>
            <div class="card-body" style="position: relative; height:300px; display:flex; align-items:center; justify-content:center;">
                <canvas id="ordersChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid-stack fade-in" style="animation-delay: 0.2s;">
        <div class="card">
            <div class="card-header">
                <h3><i class="bi bi-shield-lock" style="color:var(--primary); margin-right:8px;"></i> Pending Admin Requests</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Requested</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($pending_requests)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No pending requests found.</td></tr>
                        <?php else: foreach ($pending_requests as $req): ?>
                            <tr>
                                <td><div style="font-weight:600;"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></div></td>
                                <td style="color:var(--text-muted);"><?php echo htmlspecialchars($req['email']); ?></td>
                                <td><span class="badge badge-warning"><?php echo htmlspecialchars(date('M d, Y', strtotime($req['created_at']))); ?></span></td>
                                <td>
                                    <a href="admin_requests_handler.php?token=<?php echo urlencode($req['token']); ?>&action=approve" class="btn-xs btn-primary" target="_blank">Approve</a>
                                    <a href="admin_requests_handler.php?token=<?php echo urlencode($req['token']); ?>&action=reject" class="btn-xs btn-outline" style="margin-left:5px; border-color:var(--panel-border); color:var(--text-muted);" target="_blank">Reject</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="grid-stack charts-row fade-in" style="animation-delay: 0.3s;">
        <div class="card">
            <div class="card-header"><h3>Sales by Country</h3></div>
            <div class="card-body"><canvas id="geoChart" height="250"></canvas></div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>Top Products</h3></div>
            <div class="table-responsive">
                <table id="topProductsTable">
                    <thead><tr><th>Product Name</th><th>Category</th><th style="text-align:right">Units Sold</th></tr></thead>
                    <tbody><tr><td colspan="3"><span class="skeleton">Loading data...</span></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid-stack charts-row fade-in" style="animation-delay: 0.4s;">
        <div class="card">
            <div class="card-header"><h3>Product Categories</h3></div>
            <div class="card-body"><canvas id="categoryChart" height="200"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>User Registration Trend</h3></div>
            <div class="card-body"><canvas id="usersChart" height="80"></canvas></div>
        </div>
    </div>

</main>

<script>
// --- UI Interactivity ---
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); });
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});

// Theme Logic
const themeBtn = document.getElementById('themeBtn');
let currentTheme = '<?php echo $theme; ?>';

function updateThemeIcon(theme) {
    const icon = themeBtn.querySelector('i');
    icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}
updateThemeIcon(currentTheme);

themeBtn.addEventListener('click', () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
    fetch('theme_toggle.php?theme=' + newTheme);
    setTimeout(updateChartsTheme, 200);
});

// --- HARDCODED DATA FOR CATEGORIES ---

// 1. Map ID to Name
const categoryMap = {
    1: 'Accessories',
    2: 'Phone',
    3: 'Tablet',
    4: 'Laptop',
    5: 'Gaming'
};

// 2. Map Product ID to Array of Category IDs
const productCategoryRelations = {
    19: [1],
    24: [1, 5],
    25: [1, 5],
    27: [1, 5],
    28: [3, 5],
    29: [2, 5],
    30: [3],
    31: [2, 5],
    32: [4, 5],
    34: [2],
    2:  [3],
    33: [4]
};

// --- Data Fetching & Charts ---

const chartDefaults = { color: '#6b7280', borderColor: '#374151' };
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = "<?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>";
Chart.defaults.borderColor = "<?php echo $theme === 'dark' ? '#242c38' : 'rgba(107, 114, 128, 0.1)'; ?>";

let charts = {}; 

async function loadDashboard() {
    try {
        const res = await fetch('includes/fetch_data.php?action=dashboard_stats');
        const data = await res.json();
        
        animateValue("totalProducts", 0, data.total_products || 0, 1000);
        document.getElementById('productsInfo').innerHTML = `Total Stock: ${(data.total_stock || 0).toLocaleString()}`;
        document.getElementById('totalRevenue').textContent = (data.total_revenue || 0).toLocaleString();
        animateValue("totalOrders", 0, data.total_orders || 0, 1000);
        document.getElementById('totalSellers').textContent = data.total_sellers || 0;
    } catch(e) { console.error("Stats load error", e); }
}

function animateValue(id, start, end, duration) {
    const obj = document.getElementById(id);
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        obj.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
        if (progress < 1) window.requestAnimationFrame(step);
    };
    window.requestAnimationFrame(step);
}

// Populate Top Products Table with Decoded Categories
async function loadTables() {
    const resProd = await fetch('includes/fetch_data.php?action=top_products');
    const products = await resProd.json();
    let html = '';
    
    products.slice(0,5).forEach(p => {
        const prodId = p.id || p.product_id; 
        
        let catHtml = '<span class="text-muted" style="font-size:11px;">Uncategorized</span>';
        
        // Lookup categories for this product
        if (productCategoryRelations[prodId]) {
            const catIds = productCategoryRelations[prodId];
            const catNames = catIds.map(id => categoryMap[id]).filter(Boolean);
            
            if (catNames.length > 0) {
                catHtml = catNames.map(name => `<span class="cat-badge">${name}</span>`).join('');
            }
        }

        html += `<tr>
            <td style="font-weight:500">${p.name}</td>
            <td>${catHtml}</td>
            <td style="text-align:right"><span class="badge badge-success">${Number(p.total_qty).toLocaleString()}</span></td>
        </tr>`;
    });
    document.querySelector('#topProductsTable tbody').innerHTML = html;
}

let revenueChartCtx = document.getElementById('revenueChart').getContext('2d');

async function loadCharts() {
    // 1. Revenue
    const resRev = await fetch('includes/fetch_data.php?action=monthly_revenue');
    const revData = await resRev.json();
    charts.revenue = new Chart(revenueChartCtx, {
        type: 'line',
        data: {
            labels: revData.map(r => r.ym),
            datasets: [{
                label: 'Revenue',
                data: revData.map(r => r.amt),
                borderColor: '#44D62C',
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(68, 214, 44, 0.4)');
                    gradient.addColorStop(1, 'rgba(68, 214, 44, 0.0)');
                    return gradient;
                },
                fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#44D62C'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
    });

    // 2. Orders
    const resOrd = await fetch('includes/fetch_data.php?action=orders_summary');
    const ordData = await resOrd.json();
    charts.orders = new Chart(document.getElementById('ordersChart'), {
        type: 'doughnut',
        data: {
            labels: ordData.map(o => o.status),
            datasets: [{ data: ordData.map(o => o.cnt), backgroundColor: ['#44D62C', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 20 } } }, cutout: '70%' }
    });
    
    // 3. Geo
    const resGeo = await fetch('includes/fetch_data.php?action=geo_sales_distribution');
    const geoData = await resGeo.json();
    charts.geo = new Chart(document.getElementById('geoChart'), {
        type: 'bar',
        data: {
            labels: geoData.map(g => g.country),
            datasets: [{ label: 'Total Sales ($)', data: geoData.map(g => g.sales), backgroundColor: '#3b82f6', borderColor: '#1e40af', borderWidth: 1, borderRadius: 4 }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { borderDash: [5, 5] }, title: { display: true, text: 'Sales Amount' } }, y: { grid: { display: false } } } }
    });

    // 4. CATEGORY CHART (USING LOCAL DATA)
    
    // Calculate totals based on 'productCategoryRelations'
    let catCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    Object.values(productCategoryRelations).forEach(catIds => {
        catIds.forEach(id => {
            if (catCounts[id] !== undefined) catCounts[id]++;
        });
    });

    // Convert to Chart Arrays
    const catLabels = Object.keys(categoryMap).map(id => categoryMap[id]);
    const catValues = Object.keys(categoryMap).map(id => catCounts[id]);

    charts.category = new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{ 
                label: 'Products', 
                data: catValues, 
                backgroundColor: [
                    '#8b5cf6', // Accessories
                    '#3b82f6', // Phone
                    '#06b6d4', // Tablet
                    '#10b981', // Laptop
                    '#44D62C'  // Gaming
                ], 
                borderRadius: 4 
            }]
        },
        options: { 
            responsive: true, 
            plugins: { legend: { display: false } }, 
            scales: { 
                x: { grid: { display: false } }, 
                y: { grid: { borderDash: [5, 5] }, beginAtZero: true, ticks: { stepSize: 1 } } 
            } 
        }
    });

    // 5. Users
    const resUsers = await fetch('includes/fetch_data.php?action=user_registration_trend');
    const userData = await resUsers.json();
    charts.users = new Chart(document.getElementById('usersChart'), {
        type: 'line',
        data: {
            labels: userData.map(u => u.date),
            datasets: [{ label: 'New Users', data: userData.map(u => u.count), borderColor: '#8b5cf6', borderWidth: 2, tension: 0.4, pointRadius: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
    });
}

document.getElementById('revenueToggle').addEventListener('change', async (e) => {
    const action = e.target.value === 'daily' ? 'daily_revenue' : 'monthly_revenue';
    const res = await fetch(`includes/fetch_data.php?action=${action}`);
    const data = await res.json();
    charts.revenue.data.labels = data.map(r => r.ym || r.date);
    charts.revenue.data.datasets[0].data = data.map(r => r.amt);
    charts.revenue.update();
});

function updateChartsTheme() {
    Object.values(charts).forEach(c => c.destroy());
    loadCharts(); 
}

document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
    loadTables();
    loadCharts();
});
</script>
</body>
</html>