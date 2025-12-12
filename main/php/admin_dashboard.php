<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Dashboard — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Force reload if page is cached
        if (window.performance && window.performance.navigation.type === 1) {
            // Page was reloaded, clear any cached data
            console.log('Page reloaded - clearing cache');
        }
    </script>
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

<?php include 'admin_sidebar.php'; ?>

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

// --- Handle Export Actions ---
document.getElementById('exportAction').addEventListener('change', function() {
    const action = this.value;
    if (action === 'print') {
        window.print();
    } else if (action === 'csv') {
        downloadDashboardCSV();
    }
    this.value = ""; // Reset selection
});

// --- HELPER: CSV Sanitizer ---
function escapeCsv(text) {
    if (text === null || text === undefined) return '';
    const stringText = String(text);
    if (stringText.search(/("|,|\n)/g) >= 0) {
        return `"${stringText.replace(/"/g, '""')}"`;
    }
    return stringText;
}

// --- NEW PROFESSIONAL CSV GENERATOR ---
async function downloadDashboardCSV() {
    const exportBtn = document.getElementById('exportAction');
    const originalText = exportBtn.options[exportBtn.selectedIndex].text;
    
    // 1. Show Loading State
    exportBtn.options[exportBtn.selectedIndex].text = "Generating Report...";

    try {
        // 2. Fetch all necessary data in parallel
        const [revRes, prodRes, geoRes, usersRes] = await Promise.all([
            fetch('includes/fetch_data.php?action=monthly_revenue'),
            fetch('includes/fetch_data.php?action=top_products'),
            fetch('includes/fetch_data.php?action=sales_by_country'),
            fetch('admin_dashboard.php?ajax_action=user_growth') 
        ]);

        const revData = await revRes.json();
        const prodData = await prodRes.json();
        const geoData = await geoRes.json();
        const userData = await usersRes.json();
        
        // 3. Capture Current Dashboard Summaries
        const today = new Date();
        const dateStr = today.toISOString().split('T')[0];
        const timeStr = today.toLocaleTimeString();
        const totalRev = document.getElementById('totalRevenue').innerText;
        const totalOrd = document.getElementById('totalOrders').innerText;
        const totalProd = document.getElementById('totalProducts').innerText;
        const totalSell = document.getElementById('totalSellers').innerText;

        // 4. Build CSV Content Line-by-Line
        let csvRows = [];

        // --- HEADER ---
        csvRows.push(['META SHARK - EXECUTIVE DASHBOARD REPORT']);
        csvRows.push([`Generated By: ${escapeCsv('<?php echo $admin_name; ?>')}`]);
        csvRows.push([`Date: ${dateStr}`, `Time: ${timeStr}`]);
        csvRows.push([]);

        // --- SECTION 1: EXECUTIVE SUMMARY ---
        csvRows.push(['--- EXECUTIVE SUMMARY ---']);
        csvRows.push(['Metric', 'Value']);
        csvRows.push(['Total Revenue', escapeCsv(totalRev)]);
        csvRows.push(['Total Orders', escapeCsv(totalOrd)]);
        csvRows.push(['Total Products', escapeCsv(totalProd)]);
        csvRows.push(['Active Sellers', escapeCsv(totalSell)]);
        csvRows.push([]);

        // --- SECTION 2: REVENUE HISTORY ---
        csvRows.push(['--- REVENUE HISTORY (Monthly) ---']);
        csvRows.push(['Period', 'Revenue Amount']);
        if(revData.length > 0) {
            revData.forEach(r => {
                csvRows.push([r.ym, r.amt]);
            });
        } else {
            csvRows.push(['No data available']);
        }
        csvRows.push([]);

        // --- SECTION 3: PRODUCT INVENTORY & SALES ---
        csvRows.push(['--- PRODUCT PERFORMANCE & INVENTORY ---']);
        csvRows.push(['Product Name', 'Category', 'Stock Level', 'Total Units Sold']);
        
        if(prodData.length > 0) {
            prodData.forEach(p => {
                let cat = 'Uncategorized';
                if (p.categories) cat = p.categories;
                else if (p.category) cat = p.category;
                
                csvRows.push([
                    escapeCsv(p.name),
                    escapeCsv(cat),
                    p.stock_quantity || p.stock || 0,
                    p.total_qty || 0
                ]);
            });
        } else {
            csvRows.push(['No product data available']);
        }
        csvRows.push([]);

        // --- SECTION 4: GEOGRAPHIC SALES ---
        csvRows.push(['--- SALES BY COUNTRY ---']);
        csvRows.push(['Country', 'Sales Volume']);
        if(geoData.length > 0) {
            geoData.sort((a,b) => b.value - a.value).forEach(g => {
                csvRows.push([escapeCsv(g.country), g.value]);
            });
        }
        csvRows.push([]);
        
        // --- SECTION 5: USER GROWTH STATS ---
        csvRows.push(['--- USER DATABASE HEALTH ---']);
        csvRows.push(['Date', 'Total Users', 'Active', 'Suspended', 'Deleted']);
        if(userData.length > 0) {
             userData.forEach(u => {
                 csvRows.push([
                     escapeCsv(u.date),
                     u.total,
                     u.active,
                     u.suspended,
                     u.deleted
                 ]);
             });
        }

        // 5. Convert Array to CSV String
        const csvString = csvRows.map(e => e.join(",")).join("\n");

        // 6. Download Trigger
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `MetaShark_Report_${dateStr}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

    } catch (error) {
        console.error("Export failed:", error);
        alert("Failed to generate detailed report. Please check console.");
    } finally {
        exportBtn.options[exportBtn.selectedIndex].text = originalText;
        exportBtn.value = ""; 
    }
}

// --- Data Fetching & Charts ---
const chartDefaults = { color: '#6b7280', borderColor: '#374151' };
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = "<?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>";
Chart.defaults.borderColor = "<?php echo $theme === 'dark' ? '#242c38' : 'rgba(107, 114, 128, 0.1)'; ?>";

let charts = {}; 

// Load dashboard stats
async function loadStats() {
    try {
        const res = await fetchWithTimeout('includes/fetch_data.php?action=dashboard_stats');
        if (!res.ok) {
            const errorText = await res.text();
            console.error('Dashboard stats error:', res.status, errorText);
            throw new Error('Failed to load');
        }
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

// --- UPDATED TABLE LOGIC ---
async function loadTables() {
    try {
        const resProd = await fetch('includes/fetch_data.php?action=top_products');
        const products = await resProd.json();
        let html = '';
        
        if (products.length === 0) {
            html = '<tr><td colspan="4" style="text-align:center;">No products found</td></tr>';
        } else {
            products.forEach(p => {
                const prodId = p.id; 
                
                // 1. CATEGORY LOGIC
                let catHtml = '<span class="text-muted" style="font-size:11px;">Uncategorized</span>';
                const rawCats = p.categories || p.legacy_category || p.category || '';
                if (rawCats) {
                    const catNames = rawCats
                        .split(',')
                        .map(c => c.trim())
                        .filter(Boolean);
                    if (catNames.length > 0) {
                        catHtml = catNames.map(name => `<span class="cat-badge">${name}</span>`).join('');
                    }
                }

                // 2. STOCK LOGIC
                let stockHtml = '';
                const stockQty = parseInt(p.stock_quantity || p.stock || 0, 10); 
                
                if (stockQty < 10) {
                    stockHtml = `<span class="stock-badge stock-low">${stockQty} Left</span>`;
                } else {
                    stockHtml = `<span class="stock-badge stock-ok">${stockQty} In Stock</span>`;
                }

                // 3. SOLD COUNT
                const soldFormatted = Number(p.total_qty || 0).toLocaleString('en-US');

                html += `<tr>
                    <td style="font-weight:500">
                        ${p.name}
                        </td>
                    <td>${catHtml}</td>
                    <td>${stockHtml}</td>
                    <td style="text-align:right; font-weight:600; color:var(--primary);">
                        ${soldFormatted}
                    </td>
                </tr>`;
            });
        }
        document.querySelector('#topProductsTable tbody').innerHTML = html;
    } catch(e) {
        console.error("Table load error", e);
        document.querySelector('#topProductsTable tbody').innerHTML = '<tr><td colspan="4">Error loading data.</td></tr>';
    }
}

async function loadCharts() {
    // 1. CATEGORY CHART
    try {
        const resCat = await fetch('includes/fetch_data.php?action=category_distribution');
        if (resCat.ok) {
            const catData = await resCat.json();
            const labels = catData.map(c => c.category);
            const values = catData.map(c => Number(c.count) || 0);

            const catCtx = document.getElementById('categoryChart');
            if (catCtx) {
                charts.category = new Chart(catCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{ 
                            label: 'Products', data: values, 
                            backgroundColor: (context) => {
                                const ctx = context.chart.ctx;
                                const gradient = ctx.createLinearGradient(0, 0, 0, 200); 
                                gradient.addColorStop(0, '#44D62C');
                                gradient.addColorStop(1, 'rgba(68, 214, 44, 0.05)');
                                return gradient;
                            },
                            borderColor: '#44D62C', borderWidth: 1, borderRadius: 4, barPercentage: 0.6
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { borderDash: [5, 5] }, beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }
        }
    } catch (e) { console.error("Error loading Category Chart:", e); }

    // 2. REVENUE CHART
    try {
        const resRev = await fetch('includes/fetch_data.php?action=monthly_revenue');
        if (resRev.ok) {
            const revData = await resRev.json();
            const revCtx = document.getElementById('revenueChart');
            if (revCtx) {
                charts.revenue = new Chart(revCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: revData.map(r => r.ym),
                        datasets: [{
                            label: 'Revenue', data: revData.map(r => r.amt),
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
            }
        }
    } catch (e) { console.error(e); }

    // 3. ORDERS CHART
    try {
        const resOrd = await fetch('includes/fetch_data.php?action=orders_summary');
        if (resOrd.ok) {
            const ordData = await resOrd.json();
            const ordCanvas = document.getElementById('ordersChart');
            if (ordCanvas) {
                const ctx = ordCanvas.getContext('2d');
                const style = getComputedStyle(document.documentElement);
                const panelColor = style.getPropertyValue('--panel').trim();
                const createGradient = (c1, c2) => { const g = ctx.createLinearGradient(0, 0, 0, 300); g.addColorStop(0, c1); g.addColorStop(1, c2); return g; };
                const gradients = [
                    createGradient('#44D62C', '#0f5205'), createGradient('#00d4ff', '#004a59'), 
                    createGradient('#c084fc', '#581c87'), createGradient('#f43f5e', '#881337'), 
                    createGradient('#fbbf24', '#78350f')
                ];
                charts.orders = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ordData.map(o => o.status),
                        datasets: [{ data: ordData.map(o => o.cnt), backgroundColor: ordData.map((_, i) => gradients[i % gradients.length]), borderColor: panelColor, borderWidth: 6, hoverOffset: 10 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', padding: 20, font: { size: 12, family: "'Inter', sans-serif" } } }, tooltip: { backgroundColor: 'rgba(22, 27, 34, 0.95)', padding: 12, cornerRadius: 8, callbacks: { label: function(context) { const value = context.parsed; const total = context.chart._metasets[context.datasetIndex].total; const percentage = ((value / total) * 100).toFixed(1) + '%'; return ` ${context.label}: ${value} (${percentage})`; } } } } }
                });
            }
        }
    } catch (e) { console.error("Orders Chart Error:", e); }
    
    // 4. GEO CHART
    try {
        const resGeo = await fetch('includes/fetch_data.php?action=sales_by_country');
        if (resGeo.ok) {
            const geoData = await resGeo.json();
            geoData.sort((a, b) => b.value - a.value);
            const totalVolume = geoData.reduce((acc, curr) => acc + Number(curr.value), 0);
            const labels = geoData.map(g => `$  ${g.country}`);

            const geoCtx = document.getElementById('geoChart');
            if (geoCtx) {
                const ctx2d = geoCtx.getContext('2d');
                const gradient = ctx2d.createLinearGradient(0, 0, 400, 0);
                gradient.addColorStop(0, 'rgba(68, 214, 44, 0.9)'); 
                gradient.addColorStop(0.7, 'rgba(68, 214, 44, 0.2)'); 
                gradient.addColorStop(1, 'rgba(68, 214, 44, 0.05)');

                charts.geo = new Chart(geoCtx, {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Volume', data: geoData.map(g => g.value), backgroundColor: gradient, borderColor: '#44D62C', borderWidth: 1, borderRadius: 4, barPercentage: 0.7, categoryPercentage: 0.8 }] },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, layout: { padding: { right: 30 } }, plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', titleColor: '#ffffff', bodyColor: '#cbd5e1', borderColor: 'rgba(68, 214, 44, 0.3)', borderWidth: 1, padding: 12, displayColors: false, callbacks: { title: (items) => items[0].label.replace().trim(), label: function(context) { let val = context.parsed.x; let share = ((val / totalVolume) * 100).toFixed(1) + '%'; let money = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(val); return `$ Vol: ${money}  (🟩  ${share} Share)`; } } } }, scales: { x: { grid: { color: 'rgba(68, 214, 44, 0.05)', borderDash: [4, 4] }, ticks: { color: '#64748b', font: { family: "'Courier New', monospace", size: 11 }, callback: function(value) { return '$' + (value >= 1000 ? (value/1000).toFixed(1) + 'k' : value); } } }, y: { grid: { display: false }, ticks: { color: (ctx) => document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#1f2937', font: { size: 13, weight: '600' } } } } }
                });
            }
        }
    } catch (e) { console.error("Geo Chart Error:", e); }

    // 5. USER GROWTH CHART
    try {
        const resUsers = await fetch('admin_dashboard.php?ajax_action=user_growth');
        if (resUsers.ok) {
            const userData = await resUsers.json();
            const labels = userData.length ? userData.map(u => u.date) : ['No Data'];
            const totalPoints = userData.length ? userData.map(u => u.total) : [0];
            const activePoints = userData.length ? userData.map(u => u.active) : [0];
            const suspendedPoints = userData.length ? userData.map(u => u.suspended) : [0];
            const deletedPoints = userData.length ? userData.map(u => u.deleted) : [0];

            const userCtx = document.getElementById('usersChart');
            if (userCtx) {
                charts.users = new Chart(userCtx.getContext('2d'), {
                    type: 'line',
                    data: { labels: labels, datasets: [ { label: 'Total', data: totalPoints, borderColor: '#94a3b8', borderWidth: 2, borderDash: [3, 3], fill: false, pointRadius: 0, pointHoverRadius: 4, order: 0 }, { label: 'Active', data: activePoints, borderColor: '#8b5cf6', backgroundColor: (ctx) => { const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150); gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)'); gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); return gradient; }, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 4, order: 1 }, { label: 'Suspended', data: suspendedPoints, borderColor: '#f97316', backgroundColor: (ctx) => { const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150); gradient.addColorStop(0, 'rgba(249, 115, 22, 0.5)'); gradient.addColorStop(1, 'rgba(249, 115, 22, 0.0)'); return gradient; }, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 4, order: 2 }, { label: 'Deleted', data: deletedPoints, borderColor: '#ef4444', backgroundColor: (ctx) => { const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150); gradient.addColorStop(0, 'rgba(239, 68, 68, 0.5)'); gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)'); return gradient; }, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 4, order: 3 } ] },
                    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: true, labels: { boxWidth: 10, usePointStyle: true, font: { size: 11 } } }, tooltip: { backgroundColor: 'rgba(255, 255, 255, 0.95)', titleColor: '#1f2937', bodyColor: '#1f2937', borderColor: '#e5e7eb', borderWidth: 1, padding: 10 } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }
                });
            }
        }
    } catch (e) { console.error("User Growth Error:", e); }
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
