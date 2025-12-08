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
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Charts Overview — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        /* --- Shared Design System --- */
        :root {
            --primary: #44D62C;
            --primary-glow: rgba(68, 214, 44, 0.3);
            --bg: #f3f4f6;
            --panel: #ffffff;
            --panel-border: #e5e7eb;
            --text: #1f2937;
            --text-muted: #6b7280;
            --radius: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        [data-theme="dark"] {
            --bg: #0f1115;
            --panel: #161b22;
            --panel-border: #242c38;
            --text: #e6eef6;
            --text-muted: #94a3b8;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
        body { background: var(--bg); color: var(--text); padding: 40px 20px; min-height: 100vh; }
        a { text-decoration: none; color: inherit; transition: 0.2s; }

        .container { max-width: 1200px; margin: 0 auto; }

        /* --- Header --- */
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .header-left { display: flex; gap: 12px; align-items: center; }
        .logo-box { width: 44px; height: 44px; border-radius: 10px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; border: 1px solid var(--panel-border); }
        .logo-box img { width: 28px; height: 28px; }
        
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px;
            background: var(--panel); border: 1px solid var(--panel-border);
            border-radius: 8px; font-weight: 500; font-size: 14px; color: var(--text); box-shadow: var(--shadow);
        }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); }

        /* --- Grid & Cards --- */
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

        .card {
            background: var(--panel); border: 1px solid var(--panel-border);
            border-radius: var(--radius); padding: 24px;
            box-shadow: var(--shadow); display: flex; flex-direction: column;
            transition: transform 0.2s;
        }
        .card:hover { transform: translateY(-2px); border-color: var(--primary); }

        .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card h3 { margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card h3 i { color: var(--primary); }
        .link-icon { color: var(--text-muted); font-size: 14px; }

        .canvas-wrap { position: relative; height: 250px; width: 100%; }
        .geo-wrap { height: 350px; width: 100%; border-radius: 8px; overflow: hidden; }

        /* --- Quick Stats Table --- */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { text-align: left; padding: 12px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; border-bottom: 1px solid var(--panel-border); font-weight: 600; }
        td { padding: 16px 12px; border-bottom: 1px solid var(--panel-border); font-size: 14px; font-weight: 500; }
        tr:last-child td { border-bottom: none; }
        .val-highlight { color: var(--primary); font-weight: 700; }

        .skeleton { color: transparent; background: var(--panel-border); border-radius: 4px; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 0.5; } 50% { opacity: 0.2; } 100% { opacity: 0.5; } }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header">
        <div class="header-left">
            <div class="logo-box"><img src="uploads/logo1.png" alt="MS"></div>
            <div>
                <h1 style="margin:0; font-size:20px; font-weight:700;">Analytics Center</h1>
                <div style="color:var(--text-muted); font-size:13px;">Overview of all system metrics</div>
            </div>
        </div>
        <a href="admin_dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </div>

    <div class="grid">
        <a href="charts_bar.php" class="card">
            <div class="card-head">
                <h3><i class="bi bi-bar-chart-fill"></i> Product Categories</h3>
                <i class="bi bi-box-arrow-up-right link-icon"></i>
            </div>
            <div class="canvas-wrap"><canvas id="barChart"></canvas></div>
        </a>

        <a href="charts_line.php" class="card">
            <div class="card-head">
                <h3><i class="bi bi-graph-up-arrow"></i> Revenue Trend</h3>
                <i class="bi bi-box-arrow-up-right link-icon"></i>
            </div>
            <div class="canvas-wrap"><canvas id="lineChart"></canvas></div>
        </a>

        <a href="charts_pie.php" class="card">
            <div class="card-head">
                <h3><i class="bi bi-pie-chart-fill"></i> Order Status</h3>
                <i class="bi bi-box-arrow-up-right link-icon"></i>
            </div>
            <div class="canvas-wrap"><canvas id="pieChart"></canvas></div>
        </a>

        <a href="charts_geo.php" class="card">
            <div class="card-head">
                <h3><i class="bi bi-globe-americas"></i> Global Sales</h3>
                <i class="bi bi-box-arrow-up-right link-icon"></i>
            </div>
            <div id="geoChart" class="geo-wrap">
                <div style="height:100%; display:flex; align-items:center; justify-content:center; color:var(--text-muted);">
                    Loading Map...
                </div>
            </div>
        </a>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="bi bi-lightning-charge-fill"></i> Quick Insights</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Metric</th><th>Current Value</th></tr></thead>
                <tbody>
                    <tr><td>Total Active Products</td><td id="insProducts"><span class="skeleton">000</span></td></tr>
                    <tr><td>YTD Revenue</td><td id="insRevenue" class="val-highlight"><span class="skeleton">$0,000</span></td></tr>
                    <tr><td>Total Orders Processed</td><td id="insOrders"><span class="skeleton">000</span></td></tr>
                    <tr><td>Top Performing Category</td><td id="insTopCat"><span class="skeleton">Loading...</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// --- Configuration ---
const isDark = "<?php echo $theme; ?>" === 'dark';
Chart.defaults.color = isDark ? "#94a3b8" : "#6b7280";
Chart.defaults.borderColor = isDark ? "#242c38" : "rgba(107, 114, 128, 0.1)";
Chart.defaults.font.family = "'Inter', sans-serif";

// --- Google Charts Init ---
google.charts.load('current', {'packages':['geochart']});
google.charts.setOnLoadCallback(() => { window.geoReady = true; });

// --- LOCAL DATA FOR CATEGORIES (Hardcoded for stability) ---
const categoryMap = { 1: 'Accessories', 2: 'Phone', 3: 'Tablet', 4: 'Laptop', 5: 'Gaming' };
const productCategoryRelations = {
    19: [1], 24: [1, 5], 25: [1, 5], 27: [1, 5], 28: [3, 5], 
    29: [2, 5], 30: [3], 31: [2, 5], 32: [4, 5], 34: [2], 
    2: [3], 33: [4]
};

async function loadOverview() {

    // 1. Draw Category Bar (Local Data)
    try {
        let catCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        Object.values(productCategoryRelations).forEach(ids => ids.forEach(id => { if(catCounts[id]!==undefined) catCounts[id]++; }));
        
        const labels = Object.keys(categoryMap).map(id => categoryMap[id]);
        const values = Object.keys(categoryMap).map(id => catCounts[id]);
        
        // Calculate Top Cat for Table
        const maxVal = Math.max(...values);
        const topCatName = labels[values.indexOf(maxVal)];
        document.getElementById('insTopCat').textContent = topCatName;

        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Products',
                    data: values,
                    backgroundColor: (ctx) => {
                        const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, '#44D62C');
                        gradient.addColorStop(1, 'rgba(68, 214, 44, 0.1)');
                        return gradient;
                    },
                    borderColor: '#44D62C', borderWidth: 1, borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, border: {dash: [5,5]} } } }
        });
    } catch(e) { console.error("Bar error", e); }

    // 2. Revenue Line
    try {
        const res = await fetch('includes/fetch_data.php?action=monthly_revenue');
        if(res.ok) {
            const data = await res.json();
            const totalRev = data.reduce((acc, curr) => acc + Number(curr.amt), 0);
            document.getElementById('insRevenue').textContent = '$' + totalRev.toLocaleString();

            new Chart(document.getElementById('lineChart'), {
                type: 'line',
                data: {
                    labels: data.map(d => d.ym),
                    datasets: [{
                        data: data.map(d => d.amt),
                        borderColor: '#44D62C',
                        backgroundColor: (ctx) => {
                            const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                            gradient.addColorStop(0, 'rgba(68, 214, 44, 0.2)');
                            gradient.addColorStop(1, 'rgba(68, 214, 44, 0.0)');
                            return gradient;
                        },
                        fill: true, tension: 0.4, pointRadius: 3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, border: {dash: [5,5]} } } }
            });
        }
    } catch(e) { console.error("Line error", e); }

    // 3. Orders Pie
   // 3. Orders Pie (Updated with Gradient Shades)
    try {
        const res = await fetch('includes/fetch_data.php?action=orders_summary');
        if (res.ok) {
            const data = await res.json();
            const totalOrd = data.reduce((acc, curr) => acc + Number(curr.cnt), 0);
            
            // Update the text counter if element exists
            const counterEl = document.getElementById('insOrders');
            if(counterEl) counterEl.textContent = totalOrd.toLocaleString();

            const chartCanvas = document.getElementById('pieChart');
            if (chartCanvas) {
                const ctx = chartCanvas.getContext('2d');

                // 1. Get Panel Color (for the border "cut" effect)
                const style = getComputedStyle(document.documentElement);
                const panelColor = style.getPropertyValue('--panel').trim() || '#ffffff';

                // 2. Gradient Helper Function
                const createGradient = (topColor, bottomColor) => {
                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, topColor);    // Bright (Top)
                    gradient.addColorStop(1, bottomColor); // Dark (Bottom)
                    return gradient;
                };

                // 3. Define Shade Palette
                const gradients = [
                    createGradient('#44D62C', '#0f5205'), // Success: Neon Green -> Dark Forest
                    createGradient('#00d4ff', '#004a59'), // Info: Cyan -> Deep Ocean
                    createGradient('#c084fc', '#581c87'), // Pending: Lavender -> Deep Purple
                    createGradient('#f43f5e', '#881337'), // Cancelled: Rose -> Deep Burgundy
                    createGradient('#fbbf24', '#78350f'), // Warning: Amber -> Deep Brown
                ];

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(d => d.status),
                        datasets: [{
                            data: data.map(d => d.cnt),
                            // Map data to gradients, cycling if more data than colors
                            backgroundColor: data.map((_, i) => gradients[i % gradients.length]),
                            borderColor: panelColor, // Matches card bg to look like a "cut"
                            borderWidth: 6,          // Thicker border for separation
                            hoverOffset: 10          // Pop-out effect
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%', // Slightly thinner ring
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true,  // Use circles instead of squares
                                    pointStyle: 'circle',
                                    padding: 20,
                                    font: { size: 12, family: "'Inter', sans-serif" }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(22, 27, 34, 0.95)',
                                padding: 12,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        // Calculate percentage
                                        const total = context.chart._metasets[context.datasetIndex].total;
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                        return ` ${label}: ${value} (${percentage})`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    } catch (e) { console.error("Pie error", e); }
    

    // 4. Update Stats Fallback (Products count)
    try {
        const resStats = await fetch('includes/fetch_data.php?action=dashboard_stats');
        if(resStats.ok) {
            const stats = await resStats.json();
            document.getElementById('insProducts').textContent = (stats.total_products || 0).toLocaleString();
        }
    } catch(e) {}

    // 5. Draw Geo (Wait for Google)
    const drawGeo = async () => {
        if(!window.geoReady) { setTimeout(drawGeo, 500); return; }
        try {
            const res = await fetch('includes/fetch_data.php?action=sales_by_country');
            let rows = [['Country', 'Sales']];
            if(res.ok) {
                const data = await res.json();
                data.forEach(item => rows.push([item.country, Number(item.sales || item.value)]));
            } else {
                // Fallback demo data
                rows = [['Country','Sales'], ['USA', 1000], ['UK', 500], ['China', 800]];
            }
            
            const dt = google.visualization.arrayToDataTable(rows);
            const opts = {
                colorAxis: { colors: ['#1e3a8a', '#44D62C'] }, // Dark Blue to Neon Green
                backgroundColor: 'transparent',
                datalessRegionColor: isDark ? '#242c38' : '#e5e7eb',
                legend: { textStyle: { color: isDark ? '#94a3b8' : '#1f2937' } }
            };
            new google.visualization.GeoChart(document.getElementById('geoChart')).draw(dt, opts);
        } catch(e) { console.error("Geo error", e); }
    };
    drawGeo();
}

document.addEventListener('DOMContentLoaded', loadOverview);
</script>
</body>
</html>