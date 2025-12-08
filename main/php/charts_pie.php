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
<title>Orders by Status — Meta Shark</title>
<link rel="icon" href="uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<style>
/* --- Design System (Glassmorphism & Dark Mode) --- */
:root{
    --primary: #44D62C;
    --bg: #f3f4f6;
    --panel: #ffffff;
    --panel-border: #e5e7eb;
    --text: #1f2937;
    --text-muted: #6b7280;
    --radius: 16px;
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

/* Navigation Header */
.top-nav {
    max-width: 1200px; margin: 0 auto 30px; display: flex; justify-content: space-between; align-items: center;
}
.breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-muted); }
.breadcrumb a:hover { color: var(--primary); }
.breadcrumb i { font-size: 12px; opacity: 0.5; }
.breadcrumb .active { color: var(--text); font-weight: 600; }

.btn-back {
    display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px;
    background: var(--panel); border: 1px solid var(--panel-border);
    border-radius: 8px; font-weight: 500; font-size: 14px; color: var(--text);
}
.btn-back:hover { border-color: var(--primary); color: var(--primary); }

/* Main Card */
.chart-card {
    max-width: 1200px; margin: 0 auto;
    background: var(--panel); 
    backdrop-filter: blur(8px); /* Glass effect */
    border: 1px solid var(--panel-border);
    border-radius: var(--radius); box-shadow: var(--shadow);
    overflow: hidden; position: relative;
}

/* Card Header */
.card-header {
    padding: 24px 32px; border-bottom: 1px solid var(--panel-border);
    display: flex; justify-content: space-between; align-items: flex-end;
    background: rgba(255,255,255,0.01);
}
.header-title h1 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
.header-title h1 img { height: 28px; }
.header-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
.header-actions { display: flex; gap: 12px; }
.btn-action {
    background: rgba(68,214,44,0.1); color: var(--primary); border: none;
    padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;
    transition: 0.2s; display: flex; align-items: center; gap: 6px;
}
.btn-action:hover { background: var(--primary); color: #000; }

/* Stats Strip */
.stats-strip {
    display: flex; gap: 40px; padding: 20px 32px;
    background: rgba(0,0,0,0.2); border-bottom: 1px solid var(--panel-border);
}
.mini-stat { display: flex; flex-direction: column; }
.mini-stat label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; }
.mini-stat span { font-size: 18px; font-weight: 700; color: var(--text); }

/* Canvas Area */
.canvas-container {
    padding: 32px; height: 500px; width: 100%; position: relative;
    display: flex; align-items: center; justify-content: center;
}
.canvas-container canvas { max-width: 100%; max-height: 100%; }

/* Loading Skeleton */
.loading-overlay {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: var(--panel); z-index: 10;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 15px; color: var(--text-muted);
}
.spinner {
    width: 40px; height: 40px; border: 3px solid rgba(68,214,44,0.1);
    border-top-color: var(--primary); border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
    .canvas-container { height: 380px; padding: 16px; }
    .card-header { flex-direction: column; align-items: flex-start; gap: 16px; }
}
</style>
</head>
<body>

<div class="top-nav">
    <div class="breadcrumb">
        <a href="admin_dashboard.php">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <a href="charts_overview.php">Charts</a>
        <i class="bi bi-chevron-right"></i>
        <span class="active">Order Status</span>
    </div>
    <a href="charts_overview.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Overview</a>
</div>

<div class="chart-card">
    <div class="card-header">
        <div class="header-title">
            <h1><img src="uploads/logo1.png" alt="Logo"> Orders by Status</h1>
            <div class="header-subtitle">Distribution of all active and completed orders</div>
        </div>
        <div class="header-actions">
            <button class="btn-action" onclick="downloadChart()"><i class="bi bi-download"></i> Save Image</button>
        </div>
    </div>

    <div class="stats-strip">
        <div class="mini-stat">
            <label>Total Orders</label>
            <span id="statTotalOrders">...</span>
        </div>
        <div class="mini-stat">
            <label>Delivered Ratio</label>
            <span id="statDeliveredRatio" style="color:var(--primary)">...</span>
        </div>
    </div>

    <div class="canvas-container">
        <div class="loading-overlay" id="loader">
            <div class="spinner"></div>
            <div style="font-size:14px; font-weight:500;">Analyzing Orders...</div>
        </div>
        <canvas id="pieChart"></canvas>
    </div>
</div>

<script>
// Global Chart.js Configuration
const theme = '<?php echo $theme; ?>';
// Get CSS variables
const style = getComputedStyle(document.documentElement);
const panelColor = style.getPropertyValue('--panel').trim();
const textColor = style.getPropertyValue('--text').trim();

Chart.defaults.color = textColor;
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
Chart.defaults.font.family = 'Inter, system-ui, Arial';

async function fetchJson(url){ 
    try{ 
        const r = await fetch(url,{credentials:'same-origin'}); 
        if(!r.ok) throw 0; 
        return await r.json(); 
    } catch(e){
        console.error("Fetch Error:", e);
        return null;
    } 
}

async function draw(){
    const data = await fetchJson('includes/fetch_data.php?action=orders_summary');
    
    // Prepare data
    const labels = (data && data.length) ? data.map(d => d.status) : ['Pending','Confirmed','Delivered', 'Cancelled'];
    const values = (data && data.length) ? data.map(d => Number(d.cnt)) : [15, 25, 40, 5];
    
    // --- NEW: GRADIENT GENERATION ---
    const ctx = document.getElementById('pieChart').getContext('2d');

    // Helper to create a vertical fade (Shade effect)
    const createGradient = (topColor, bottomColor) => {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, topColor);    // Bright at top
        gradient.addColorStop(1, bottomColor); // Dark at bottom
        return gradient;
    };

    // Define Color Pairs (Bright -> Dark/Shade)
    // 1. Emerald (Success/Delivered)
    // 2. Cyan (Info/Confirmed)
    // 3. Violet (Pending/Processing) - looks better than orange in dark mode
    // 4. Rose (Cancelled)
    const gradients = [
        createGradient('#44D62C', '#0f5205'), // Neon Green -> Deep Forest
        createGradient('#00d4ff', '#004a59'), // Neon Blue -> Deep Ocean
        createGradient('#c084fc', '#581c87'), // Lavender -> Deep Purple
        createGradient('#f43f5e', '#881337'), // Rose -> Deep Burgundy
        createGradient('#fbbf24', '#78350f'), // Amber -> Deep Brown (Backup)
    ];

    // Map gradients to data length (repeat if necessary)
    const backgroundColors = values.map((_, i) => gradients[i % gradients.length]);
    
    // Calculate Stats
    const totalOrders = values.reduce((a, b) => a + b, 0);
    const deliveredIndex = labels.findIndex(l => l.toLowerCase() === 'delivered');
    const deliveredCount = deliveredIndex !== -1 ? values[deliveredIndex] : 0;
    const deliveredRatio = totalOrders > 0 ? ((deliveredCount / totalOrders) * 100).toFixed(1) + '%' : '0%';
    
    // Update HTML Stats
    document.getElementById('statTotalOrders').textContent = totalOrders.toLocaleString();
    document.getElementById('statDeliveredRatio').textContent = deliveredRatio;

    // Render Chart
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels, 
            datasets: [{
                data: values, 
                backgroundColor: backgroundColors,
                borderColor: panelColor, // Matches card background for "cut" effect
                borderWidth: 6,          // Thicker border for cleaner separation
                hoverOffset: 10          // Popping effect on hover
            }]
        },
        options: {
            responsive: true, 
            maintainAspectRatio: false,
            cutout: '70%', 
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        usePointStyle: true, // Circles instead of squares in legend
                        pointStyle: 'circle',
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(22, 27, 34, 0.95)',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) label += ': ';
                            if (context.parsed !== null) {
                                const percentage = ((context.parsed / totalOrders) * 100).toFixed(1) + '%';
                                label += context.parsed.toLocaleString() + ` (${percentage})`;
                            }
                            return label;
                        }
                    }
                }
            },
            animation: {
                onComplete: () => {
                    document.getElementById('loader').style.display = 'none';
                }
            }
        }
    });

    // Fallback
    setTimeout(() => { document.getElementById('loader').style.display = 'none'; }, 500);
}

function downloadChart() {
    const link = document.createElement('a');
    link.download = 'meta-shark-orders.png';
    link.href = document.getElementById('pieChart').toDataURL('image/png', 1.0);
    link.click();
}

draw();
</script>
</body>
</html>