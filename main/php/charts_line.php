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
    <title>Revenue Analysis — Meta Shark</title>
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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; outline: none; }
        body { background: var(--bg); color: var(--text); padding: 40px 20px; min-height: 100vh; transition: background 0.3s ease; }
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
            box-shadow: var(--shadow);
        }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); }

        /* Main Card */
        .chart-card {
            max-width: 1200px; margin: 0 auto;
            background: var(--panel); border: 1px solid var(--panel-border);
            border-radius: var(--radius); box-shadow: var(--shadow);
            overflow: hidden; position: relative;
        }

        /* Card Header */
        .card-header {
            padding: 24px 32px; border-bottom: 1px solid var(--panel-border);
            display: flex; justify-content: space-between; align-items: flex-end;
            background: rgba(255,255,255,0.01);
        }
        .header-title h1 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 12px; margin-bottom: 0; }
        .header-title h1 img { height: 28px; filter: drop-shadow(0 0 5px var(--primary-glow)); }
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
        .mini-stat label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; font-weight: 700; opacity: 0.7; }
        .mini-stat span { font-size: 18px; font-weight: 700; color: var(--text); }

        /* Canvas Area */
        .canvas-container {
            padding: 32px; height: 500px; width: 100%; position: relative;
        }

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

        @media (max-width: 768px) {
            .canvas-container { height: 350px; padding: 16px; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .stats-strip { flex-wrap: wrap; gap: 20px; }
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
        <span class="active">Revenue Analysis</span>
    </div>
    <a href="charts_overview.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Overview</a>
</div>

<div class="chart-card">
    <div class="card-header">
        <div class="header-title">
            <h1><img src="uploads/logo1.png" alt="Logo"> Monthly Revenue</h1>
            <div class="header-subtitle">Financial performance over the last 12 months</div>
        </div>
        <div class="header-actions">
            <button class="btn-action" onclick="downloadChart()"><i class="bi bi-download"></i> Save Report</button>
        </div>
    </div>

    <div class="stats-strip">
        <div class="mini-stat">
            <label>Total Revenue (Displayed)</label>
            <span id="statTotalRevenue">...</span>
        </div>
        <div class="mini-stat">
            <label>Highest Month</label>
            <span id="statMaxRevenue" style="color:var(--primary)">...</span>
        </div>
        <div class="mini-stat">
            <label>Average Monthly</label>
            <span id="statAvgRevenue">...</span>
        </div>
    </div>

    <div class="canvas-container">
        <div class="loading-overlay" id="loader">
            <div class="spinner"></div>
            <div style="font-size:14px; font-weight:500;">Analyzing Data...</div>
        </div>
        <canvas id="lineChart"></canvas>
    </div>
</div>

<script>
// Setup Utils
const ctx = document.getElementById('lineChart').getContext('2d');
const isDark = "<?php echo $theme; ?>" === 'dark';

// Match Dashboard Chart Defaults
Chart.defaults.color = isDark ? "#94a3b8" : "#6b7280";
Chart.defaults.borderColor = isDark ? "#242c38" : "rgba(107, 114, 128, 0.1)";
Chart.defaults.font.family = "'Inter', sans-serif";

async function initChart() {
    try {
        // Fetch Data
        const res = await fetch('includes/fetch_data.php?action=monthly_revenue');
        if(!res.ok) throw new Error("Network error");
        const data = await res.json();

        // Fallback for empty data
        const labels = (data && data.length) ? data.map(d => d.ym) : ['No Data'];
        const values = (data && data.length) ? data.map(d => Number(d.amt)) : [0];

        // --- Calculate Stats ---
        const total = values.reduce((a, b) => a + b, 0);
        const max = Math.max(...values);
        const avg = total / (values.length || 1);

        document.getElementById('statTotalRevenue').textContent = '$' + total.toLocaleString();
        document.getElementById('statMaxRevenue').textContent = '$' + max.toLocaleString();
        document.getElementById('statAvgRevenue').textContent = '$' + avg.toLocaleString(undefined, {maximumFractionDigits: 0});

        // --- Create Gradient ---
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(68, 214, 44, 0.4)');
        gradient.addColorStop(1, 'rgba(68, 214, 44, 0.0)');

        // --- Render Chart ---
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: values,
                    backgroundColor: gradient,
                    borderColor: '#44D62C',
                    borderWidth: 2,
                    pointBackgroundColor: '#44D62C',
                    pointBorderColor: isDark ? '#161b22' : '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 // Smooth curve
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(22, 27, 34, 0.95)' : '#fff',
                        titleColor: isDark ? '#fff' : '#111',
                        bodyColor: isDark ? '#e6eef6' : '#444',
                        borderColor: isDark ? 'rgba(255,255,255,0.1)' : '#e5e7eb',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return ' $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [5, 5],
                            drawBorder: false,
                            color: isDark ? "#242c38" : "rgba(107, 114, 128, 0.1)"
                        },
                        ticks: { 
                            padding: 10,
                            callback: function(value) { return '$' + value.toLocaleString(); } 
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { padding: 10 }
                    }
                },
                animation: {
                    onComplete: () => {
                        document.getElementById('loader').style.display = 'none';
                    }
                }
            }
        });

    } catch (e) {
        console.error("Chart Error:", e);
        document.getElementById('loader').innerHTML = '<div style="color:#ef4444">Failed to load data.</div>';
    }
}

function downloadChart() {
    const link = document.createElement('a');
    link.download = 'meta-shark-revenue-report.png';
    link.href = document.getElementById('lineChart').toDataURL('image/png', 1.0);
    link.click();
}

// Start
initChart();
</script>
</body>
</html>