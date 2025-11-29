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
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Charts Overview — Meta Shark</title>
<link rel="icon" href="uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<style>
/* --- Design System (Glassmorphism & Dark Mode) --- */
:root{
    --primary: #44D62C;
    --primary-glow: rgba(68, 214, 44, 0.3);
    --bg: #f3f4f6;
    --panel: #ffffff;
    --panel-border: #e5e7eb;
    --text: #1f2937;
    --text-muted: #6b7280;
    --card-radius: 16px;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
[data-theme="dark"] {
    --bg: #0f1115;
    --panel: #161b22;
    --panel-border: #242c38;
    --text: #e6eef6;
    --text-muted: #94a3b8;
    --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
}

/* --- Base Styles --- */
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
body { background: var(--bg); color: var(--text); padding: 40px 20px; min-height: 100vh; }
a { text-decoration: none; color: inherit; transition: 0.2s; }

.container{max-width:1200px;margin:0 auto}

/* --- Header & Toolbar --- */
.header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:30px;
}
.header .title{display:flex;gap:12px;align-items:center}
.logo{width:44px;height:44px;border-radius:8px;background:url('uploads/logo1.png') center/cover no-repeat}

.toolbar{display:flex;gap:16px;align-items:center}
.btn-dashboard {
    display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px;
    background: transparent; border: 1px solid var(--panel-border);
    border-radius: 8px; font-weight: 500; font-size: 14px; color: var(--text);
}
.btn-dashboard:hover { border-color: var(--primary); color: var(--primary); }
.note{color:var(--text-muted);font-size:13px}

/* --- Grid & Cards --- */
.grid{
  display:grid;
  grid-template-columns: repeat(2, 1fr);
  gap:20px;
  margin-bottom:20px;
}
@media (max-width:900px){ .grid{grid-template-columns:1fr; gap:16px} }

.card{
  /* Glassmorphism Effect */
  background: var(--panel);
  backdrop-filter: blur(8px);
  border:1px solid var(--panel-border);
  border-radius:var(--card-radius);
  padding:20px;
  box-shadow:var(--shadow);
  transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    box-shadow: 0 0 0 1px var(--primary-glow), var(--shadow);
}

.card h3{
    margin:0 0 12px;
    color:var(--primary);
    font-size:18px;
    font-weight:600;
}
.card-content-link {
    display: block; /* Makes the entire card content clickable */
}

/* Chart/Geo Wrappers */
.canvas-wrap{height:320px}
.geo-wrap{height:460px; position:relative;}

/* Ensure Chart canvases fit their card and don't overflow */
.card .canvas-wrap { display:flex; align-items:center; justify-content:center; height:320px; }
.card .canvas-wrap canvas { max-width:100% !important; max-height:100% !important; width:auto !important; }

/* Slightly smaller geo area on small screens */
@media (max-width:900px) {
  .geo-wrap { height:360px; }
}

/* --- Table Styles (Quick Insights) --- */
.table-small{width:100%;border-collapse:collapse;font-size:14px}
.table-small th, .table-small td{
    padding:12px 10px;
    text-align:left;
    border-top:1px solid var(--panel-border);
}
.table-small th{
    background:rgba(68,214,44,0.08);
    color:var(--primary);
    font-weight:700;
}
.table-small td:last-child {
    font-weight: 600;
    color: var(--text);
}

/* Tooltip link style on table */
.table-small tr:last-child td {
    border-bottom: 1px solid var(--panel-border);
}

</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="title"><div class="logo" aria-hidden="true"></div><div><h1 style="margin:0;font-size:24px">Charts Overview</h1><div style="color:var(--text-muted);font-size:14px">A quick look at key business metrics.</div></div></div>
    <div class="toolbar">
      <div class="note"><i class="bi bi-person-fill"></i> Signed in as **<?php echo htmlspecialchars($admin_name); ?>**</div>
      <a href="admin_dashboard.php" class="btn-dashboard"><i class="bi bi-speedometer2"></i> Back to Dashboard</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Product Categories (Bar)</h3>
      <a href="charts_bar.php" class="card-content-link">
        <div class="canvas-wrap"><canvas id="barChart"></canvas></div>
      </a>
    </div>

    <div class="card">
      <h3>Monthly Revenue (Line)</h3>
      <a href="charts_line.php" class="card-content-link">
        <div class="canvas-wrap"><canvas id="lineChart"></canvas></div>
      </a>
    </div>

    <div class="card">
      <h3>Orders by Status (Pie)</h3>
      <a href="charts_pie.php" class="card-content-link">
        <div class="canvas-wrap"><canvas id="pieChart"></canvas></div>
      </a>
    </div>

    <div class="card">
      <h3>Sales by Country (Geography)</h3>
      <a href="charts_geo.php" class="card-content-link">
        <div id="geoChart" class="geo-wrap">
          <div id="geoLoader" style="position:absolute; top:0; left:0; right:0; bottom:0; display:flex; align-items:center; justify-content:center; color:var(--text-muted); background:var(--panel);">Loading Map...</div>
        </div>
      </a>
    </div>
  </div>

  <div class="card">
    <h3>Quick Insights Summary</h3>
    <table class="table-small" id="insightsTable">
      <thead><tr><th>Metric</th><th>Value</th></tr></thead>
      <tbody>
        <tr><td>Total Products</td><td id="insProducts">—</td></tr>
        <tr><td>Total Revenue (YTD)</td><td id="insRevenue">—</td></tr>
        <tr><td>Total Orders</td><td id="insOrders">—</td></tr>
        <tr><td>Top Category</td><td id="insTopCat">—</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
// Global Chart.js Configuration for Dark Theme
Chart.defaults.color = '<?php echo $theme === "dark" ? "#e6eef6" : "#1f2937"; ?>';
Chart.defaults.borderColor = '<?php echo $theme === "dark" ? "rgba(255,255,255,0.1)" : "rgba(31,41,55,0.1)"; ?>';
Chart.defaults.font.family = 'Inter, system-ui, Arial';

/* Load Google GeoChart */
google.charts.load('current', {'packages':['geochart']});
google.charts.setOnLoadCallback(()=>{ window.geoReady = true; });

/* Utility: fetch JSON with graceful fallback */
async function fetchJson(url){
  try {
    const res = await fetch(url, {credentials: 'same-origin'});
    if (!res.ok) throw new Error('fetch failed');
    return await res.json();
  } catch (e) {
    console.warn('fetch failed', url, e);
    return null;
  }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0
    }).format(amount);
}

/* Draw Bar: category_distribution endpoint */
async function drawBar(){
  const data = await fetchJson('includes/fetch_data.php?action=category_distribution');
  let labels, values, topCategory = '—';
  
  if (data && data.length) {
    // Sort by count descending to get top category
    const sorted = [...data].sort((a, b) => Number(b.count) - Number(a.count));
    labels = sorted.map(d => d.category);
    values = sorted.map(d => Number(d.count));
    // Get top category with count
    if (sorted.length > 0) {
      topCategory = `${sorted[0].category} (${sorted[0].count} products)`;
    }
  } else {
    labels = ['Example A','Example B','Example C'];
    values = [40,25,15];
  }
  
  const ctx = document.getElementById('barChart').getContext('2d');
  
  // Gradient for Bar Chart
  const gradient = ctx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, '#44D62C');
  gradient.addColorStop(1, 'rgba(68, 214, 44, 0.1)');

  new Chart(ctx, {
    type: 'bar',
    data: { 
        labels, 
        datasets: [{ 
            label: 'Products', 
            data: values, 
            backgroundColor: gradient,
            borderRadius: 4,
            borderColor: '#44D62C',
            borderWidth: 1
        }] 
    },
    options: { 
        responsive:true, 
        maintainAspectRatio: false,
        plugins:{legend:{display:false}},
        scales: {
            y: {beginAtZero: true, grid: {borderDash: [2, 2], drawBorder: false}},
            x: {grid: {display: false}}
        }
    }
  });
  
  document.getElementById('insTopCat').textContent = topCategory;
}

/* Draw Line: monthly_revenue endpoint */
async function drawLine(){
  const data = await fetchJson('includes/fetch_data.php?action=monthly_revenue');
  const labels = (data && data.length) ? data.map(d=>d.ym) : ['2024-01','2024-02','2024-03'];
  const values = (data && data.length) ? data.map(d=>Number(d.amt)) : [1200, 1800, 1600];
  const ctx = document.getElementById('lineChart').getContext('2d');
  
  // Gradient for Line Chart background fill
  const gradient = ctx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(68, 214, 44, 0.2)');
  gradient.addColorStop(1, 'rgba(68, 214, 44, 0)');

  new Chart(ctx, {
    type: 'line',
    data: { 
        labels, 
        datasets: [{ 
            label: 'Revenue', 
            data: values, 
            borderColor: '#44D62C', 
            backgroundColor: gradient, 
            tension:0.4, // Smoother curve
            fill:true,
            pointRadius: 4,
            pointBackgroundColor: '#44D62C'
        }] 
    },
    options: { 
        responsive:true, 
        maintainAspectRatio: false,
        plugins:{legend:{display:false}},
        scales: {
            y: {beginAtZero: true, grid: {borderDash: [2, 2], drawBorder: false}},
            x: {grid: {display: false}}
        }
    }
  });
  
  const total = values.reduce((s,v)=>s+v,0);
  document.getElementById('insRevenue').textContent = formatCurrency(total);
}

/* Draw Pie: orders_summary endpoint */
async function drawPie(){
  const data = await fetchJson('includes/fetch_data.php?action=orders_summary');
  const labels = (data && data.length) ? data.map(d=>d.status) : ['Pending','Confirmed','Delivered'];
  const values = (data && data.length) ? data.map(d=>Number(d.cnt)) : [12,30,18];
  // Use thematic colors: Green (Delivered), Blue (Confirmed), Orange (Pending)
  const colors = ['#44D62C','#00d4ff','#ff9800','#f44336']; 
  const ctx = document.getElementById('pieChart').getContext('2d');

  new Chart(ctx, {
    type: 'doughnut',
    data: { 
        labels, 
        datasets: [{ 
            data: values, 
            backgroundColor: colors.slice(0, labels.length),
            borderColor: 'var(--panel)', // Makes the slices pop from the background
            borderWidth: 2
        }] 
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                align: 'start',
                labels: {
                    padding: 15,
                    boxWidth: 10
                }
            }
        }
    }
  });
  
  const totalOrders = values.reduce((s,v)=>s+v,0);
  document.getElementById('insOrders').textContent = totalOrders;
}

/* Draw Geography: attempt server endpoint 'sales_by_country' else fallback sample */
async function drawGeo(){
  // Check if GeoChart is loaded, if not, wait and re-call
  if (!window.geoReady) { setTimeout(drawGeo, 300); return; }
  
  const geoContainer = document.getElementById('geoChart');
  const geoLoader = document.getElementById('geoLoader');

  // try to fetch server-side data (if endpoint exists)
  const srv = await fetchJson('includes/fetch_data.php?action=sales_by_country');
  let rows;
  const isDark = '<?php echo $theme; ?>' === 'dark';

  if (srv && srv.length) {
    rows = [['Country','Sales']].concat(srv.map(r=>[r.country, Number(r.value)]));
  } else {
    // fallback sample data
    rows = [
      ['Country','Sales'], ['United States', 12000], ['Canada', 3100], 
      ['Brazil', 2400], ['United Kingdom', 5400], ['Germany', 4200], 
      ['India', 7200], ['Australia', 1900]
    ];
  }

  try {
    const data = google.visualization.arrayToDataTable(rows);
    const options = { 
        colorAxis: {colors: ['#2e3a4e','#88d767','#44D62C']}, // Dark/Subtle to Bright Green
        backgroundColor: 'transparent',
        datalessRegionColor: isDark ? '#242c38' : '#e8eef6',
        defaultColor: isDark ? '#2e3a4e' : '#f5f7fb',
        legend: {textStyle: {color: isDark ? '#e6eef6' : '#1f2937'}},
        tooltip: {textStyle: {color: '#1f2937'}}, // Tooltips always need dark text
        keepAspectRatio: true
    };
    const chart = new google.visualization.GeoChart(geoContainer);
    
    // Hide loader once drawn
    google.visualization.events.addListener(chart, 'ready', function() {
        geoLoader.style.display = 'none';
    });
    
    chart.draw(data, options);
  } catch (e) {
    console.warn('GeoChart draw failed', e);
    geoContainer.innerHTML = '<div style="padding:18px;color:var(--text-muted)">Geography chart unavailable. Check your data or console for errors.</div>';
  }
}

/* Quick Insights: totals endpoint */
async function loadInsights(){
  const stats = await fetchJson('includes/fetch_data.php?action=dashboard_stats');
  if (stats) {
    document.getElementById('insProducts').textContent = (Number(stats.total_products || 0)).toLocaleString() ?? '—';
    // If line chart didn't run, update revenue here too
    if (document.getElementById('insRevenue').textContent === '—') {
      document.getElementById('insRevenue').textContent = formatCurrency(Number(stats.total_revenue || 0));
    }
    document.getElementById('insOrders').textContent = (Number(stats.total_orders || 0)).toLocaleString() ?? '—';
  }
}

/* Initialize all charts */
async function init(){
  // Execute charts and insights concurrently
  await Promise.all([ 
      drawBar(), 
      drawLine(), 
      drawPie(), 
      drawGeo(), 
      loadInsights() 
  ]);
}
init();

/* Redraw GeoChart on resize for responsiveness */
let resizeTimer = null;
window.addEventListener('resize', () => { 
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { 
        try { drawGeo(); } catch(e){} 
    }, 250);
});
</script>
</body>
</html>