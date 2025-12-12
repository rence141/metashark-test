<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Handle theme toggle requests (from fetch). Persist to session and return no content.
if (isset($_GET['theme'])) {
    $t = $_GET['theme'] === 'light' ? 'light' : 'dark';
    $_SESSION['theme'] = $t;
    http_response_code(204);
    exit;
}

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['fullname'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';
$admin_initial = strtoupper(substr($admin_name, 0, 1));
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
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; }
    a { text-decoration: none; color: inherit; }

    /* --- Navbar --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 70px;
        background: var(--panel); border-bottom: 1px solid var(--panel-border);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 50; box-shadow: var(--shadow);
    }

    .navbar-left { display: flex; align-items: center; gap: 16px; }
    .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; }
    .logo-area img { height: 32px; }
    
    .navbar-right { display: flex; align-items: center; gap: 16px; }
    .theme-btn { background: none; border: none; color: var(--text); font-size: 20px; cursor: pointer; padding: 8px; }
    .profile-link { display: flex; align-items: center; gap: 12px; }
    .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; display: flex; align-items: center; justify-content: center; }
    .btn-logout { background: none; border: none; color: var(--text); font-size: 18px; cursor: pointer; padding: 8px; }

    /* --- Sidebar --- */
    .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; z-index: 40; }
    .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; margin-bottom: 4px; transition: 0.2s; cursor: pointer; }
    .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    .sidebar-item.active { color: var(--primary); background: rgba(68,214,44,0.1); }
    .sidebar-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }

    /* --- Main Content --- */
    .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); }

    .dashboard-header { margin-bottom: 32px; }
    .dashboard-header h2 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
    .dashboard-header p { color: var(--text-muted); font-size: 14px; }

    /* --- Stats Grid --- */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px; }
    .stat-card { background: var(--panel); padding: 20px; border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); }
    .stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; }
    .stat-value { font-size: 28px; font-weight: 800; }
    .stat-info { font-size: 12px; color: var(--primary); margin-top: 8px; }

    /* --- Charts Grid --- */
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px; }
    .chart-card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); overflow: hidden; }
    .chart-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); }
    .chart-header h3 { font-size: 16px; font-weight: 600; margin: 0; }
    .chart-body { padding: 24px; height: 300px; position: relative; }
    canvas { max-height: 250px; }

    /* --- Responsive --- */
    @media (max-width: 992px) {
        .admin-sidebar { transform: translateX(-100%); transition: 0.3s; }
        .admin-sidebar.show { transform: translateX(0); }
        .admin-main { margin-left: 0; }
    }

    @media (max-width: 768px) {
        .charts-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    .skeleton { background: var(--panel-border); border-radius: 4px; animation: pulse 1.5s infinite; color: transparent; }
    @keyframes pulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 0.2; } }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="admin-navbar">
    <div class="navbar-left">
        <div class="logo-area">
            <img src="uploads/logo1.png" alt="Meta Shark">
            <span>META SHARK</span>
        </div>
    </div>
    <div class="navbar-right">
        <button class="theme-btn" onclick="toggleTheme()"><i class="bi bi-moon-stars"></i></button>
        <a href="admin_profile.php" class="profile-link">
            <div class="profile-avatar"><?php echo $admin_initial; ?></div>
        </a>
        <a href="admin_logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<!-- Sidebar -->
<aside class="admin-sidebar">
    <a href="admin_dashboard.php" class="sidebar-item active" style="color:var(--primary); background:rgba(68,214,44,0.1);">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    
    <div class="sidebar-label">Analytics</div>
    <a href="charts_overview.php" class="sidebar-item">
        <i class="bi bi-activity"></i> Overview
    </a>
    <a href="charts_line.php" class="sidebar-item">
        <i class="bi bi-graph-up-arrow"></i> Revenue
    </a>
    <a href="charts_bar.php" class="sidebar-item">
        <i class="bi bi-bar-chart-fill"></i> Categories
    </a>
    <a href="charts_pie.php" class="sidebar-item">
        <i class="bi bi-pie-chart-fill"></i> Orders
    </a>
    <a href="charts_geo.php" class="sidebar-item">
        <i class="bi bi-globe"></i> Geography
    </a>
    
    <div class="sidebar-label">Management</div>
    <a href="admin_products.php" class="sidebar-item">
        <i class="bi bi-box"></i> Products
    </a>
    <a href="admin_orders.php" class="sidebar-item">
        <i class="bi bi-bag"></i> Orders
    </a>
    <a href="admin_users.php" class="sidebar-item">
        <i class="bi bi-people"></i> Users
    </a>
    <a href="admin_sellers.php" class="sidebar-item">
        <i class="bi bi-shop"></i> Sellers
    </a>
</aside>

<!-- Main Content -->
<main class="admin-main">
    <div class="dashboard-header">
        <h2>Dashboard Overview</h2>
        <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>. Here's your store summary.</p>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-box"></i> Total Products</div>
            <div class="stat-value" id="totalProducts"><span class="skeleton">0</span></div>
            <div class="stat-info" id="stockInfo"><span class="skeleton">Loading...</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-currency-dollar"></i> Total Revenue</div>
            <div class="stat-value" id="totalRevenue"><span class="skeleton">$0</span></div>
            <div class="stat-info" id="revenueInfo"><span class="skeleton">Loading...</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-bag"></i> Total Orders</div>
            <div class="stat-value" id="totalOrders"><span class="skeleton">0</span></div>
            <div class="stat-info" id="ordersInfo"><span class="skeleton">Loading...</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-shop"></i> Active Sellers</div>
            <div class="stat-value" id="totalSellers"><span class="skeleton">0</span></div>
            <div class="stat-info" id="sellersInfo"><span class="skeleton">Loading...</span></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header"><h3>Revenue Trend</h3></div>
            <div class="chart-body">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><h3>Orders by Status</h3></div>
            <div class="chart-body" style="display:flex; align-items:center; justify-content:center;">
                <canvas id="ordersChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><h3>Sales by Country</h3></div>
            <div class="chart-body">
                <canvas id="geoChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><h3>Product Categories</h3></div>
            <div class="chart-body">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</main>

<script>
// Theme toggle
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    fetch('?theme=' + encodeURIComponent(newTheme), { credentials: 'same-origin' });
}

// Load theme
const savedTheme = localStorage.getItem('theme') || '<?php echo $theme; ?>';
document.documentElement.setAttribute('data-theme', savedTheme);

// Chart defaults
Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
Chart.defaults.color = savedTheme === 'dark' ? '#94a3b8' : '#6b7280';
Chart.defaults.borderColor = savedTheme === 'dark' ? '#242c38' : '#e5e7eb';

let charts = {};

// Load dashboard stats
async function loadStats() {
    try {
        const res = await fetch('includes/fetch_data.php?action=dashboard_stats');
        const data = await res.json();
        const stats = data.data || data;
        
        document.getElementById('totalProducts').innerHTML = (stats.total_products || 0).toLocaleString();
        document.getElementById('stockInfo').innerHTML = `Stock: ${(stats.total_stock || 0).toLocaleString()} units`;
        
        document.getElementById('totalRevenue').innerHTML = '$' + (stats.total_revenue || 0).toLocaleString();
        document.getElementById('revenueInfo').innerHTML = 'Lifetime earnings';
        
        document.getElementById('totalOrders').innerHTML = (stats.total_orders || 0).toLocaleString();
        document.getElementById('ordersInfo').innerHTML = 'Completed orders';
        
        document.getElementById('totalSellers').innerHTML = (stats.total_sellers || 0).toLocaleString();
        document.getElementById('sellersInfo').innerHTML = 'Active sellers';
    } catch(e) { console.error('Failed to load stats:', e); }
}

// Load revenue chart
async function loadRevenueChart() {
    try {
        const res = await fetch('includes/fetch_data.php?action=monthly_revenue');
        const data = await res.json();
        const revData = Array.isArray(data) ? data : (data.data || []);
        
        if (revData.length === 0) {
            document.getElementById('revenueChart').parentElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">No data available</div>';
            return;
        }
        
        const ctx = document.getElementById('revenueChart').getContext('2d');
        charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: revData.map(r => r.ym),
                datasets: [{
                    label: 'Revenue',
                    data: revData.map(r => r.amt),
                    borderColor: '#44D62C',
                    backgroundColor: 'rgba(68, 214, 44, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#44D62C'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    } catch(e) { console.error('Failed to load revenue chart:', e); }
}

// Load orders chart
async function loadOrdersChart() {
    try {
        const res = await fetch('includes/fetch_data.php?action=orders_summary');
        const data = await res.json();
        const ordData = Array.isArray(data) ? data : (data.data || []);
        
        if (ordData.length === 0) {
            document.getElementById('ordersChart').parentElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">No data available</div>';
            return;
        }
        
        const ctx = document.getElementById('ordersChart').getContext('2d');
        const colors = ['#44D62C', '#00d4ff', '#ff9800', '#f44336', '#8b5cf6'];
        
        charts.orders = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ordData.map(o => o.status),
                datasets: [{
                    data: ordData.map(o => o.cnt),
                    backgroundColor: ordData.map((_, i) => colors[i % colors.length]),
                    borderColor: 'var(--panel)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right' } }
            }
        });
    } catch(e) { console.error('Failed to load orders chart:', e); }
}

// Load geo chart
async function loadGeoChart() {
    try {
        const res = await fetch('includes/fetch_data.php?action=sales_by_country');
        const data = await res.json();
        const geoData = Array.isArray(data) ? data : (data.data || []);
        
        if (geoData.length === 0) {
            document.getElementById('geoChart').parentElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">No data available</div>';
            return;
        }
        
        geoData.sort((a, b) => b.value - a.value);
        const ctx = document.getElementById('geoChart').getContext('2d');
        
        charts.geo = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: geoData.map(g => g.country),
                datasets: [{
                    label: 'Sales',
                    data: geoData.map(g => g.value),
                    backgroundColor: '#44D62C',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    } catch(e) { console.error('Failed to load geo chart:', e); }
}

// Load category chart
async function loadCategoryChart() {
    try {
        const res = await fetch('includes/fetch_data.php?action=category_distribution');
        const data = await res.json();
        const catData = Array.isArray(data) ? data : (data.data || []);
        
        if (catData.length === 0) {
            document.getElementById('categoryChart').parentElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">No data available</div>';
            return;
        }
        
        const ctx = document.getElementById('categoryChart').getContext('2d');
        
        charts.category = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: catData.map(c => c.category),
                datasets: [{
                    label: 'Count',
                    data: catData.map(c => c.count),
                    backgroundColor: '#44D62C',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x',
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    } catch(e) { console.error('Failed to load category chart:', e); }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadRevenueChart();
    loadOrdersChart();
    loadGeoChart();
    loadCategoryChart();
});
</script>

</body>
</html>
