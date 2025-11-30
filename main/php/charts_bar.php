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
    <title>Category Analysis — Meta Shark</title>
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

        /* Chart & Details Layout */
        .chart-layout {
            display: grid;
            grid-template-columns: 1fr;
        }
        
        /* Canvas Area */
        .canvas-container {
            padding: 32px; height: 400px; width: 100%; position: relative;
            border-bottom: 1px solid var(--panel-border);
        }

        /* Detailed Table Section */
        .details-container {
            padding: 32px;
        }
        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        /* UPDATED: Added horizontal padding (12px) to th and td */
        th { text-align: left; color: var(--text-muted); font-size: 12px; text-transform: uppercase; padding: 12px 12px; border-bottom: 1px solid var(--panel-border); }
        td { padding: 16px 12px; border-bottom: 1px solid var(--panel-border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .cat-indicator { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 8px; }
        
        .progress-bar-bg { 
            width: 100px; 
            height: 6px; 
            background: var(--panel-border); 
            border-radius: 3px; 
            overflow: hidden; 
            display: inline-block; 
            vertical-align: middle; 
            margin-right: 16px;
        }
        
        .progress-bar-fill { height: 100%; border-radius: 3px; }
        .share-text { font-size: 12px; color: var(--text-muted); font-family: monospace; }

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

        /* Stats Strip */
        .stats-strip {
            display: flex; gap: 40px; padding: 20px 32px;
            background: rgba(0,0,0,0.2); border-bottom: 1px solid var(--panel-border);
        }
        .mini-stat { display: flex; flex-direction: column; }
        .mini-stat label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; font-weight: 700; opacity: 0.7; }
        .mini-stat span { font-size: 18px; font-weight: 700; color: var(--text); }

        @media (max-width: 768px) {
            .canvas-container { height: 300px; padding: 16px; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .stats-strip { flex-wrap: wrap; gap: 20px; }
            .details-container { padding: 16px; }
            .progress-bar-bg { width: 60px; }
        }
    </style>
</head>
<body>

<div class="top-nav">
    <div class="breadcrumb">
        <a href="admin_dashboard.php">Dashboard</a>
        <i class="bi bi-chevron-right"></i>
        <span class="active">Category Analytics</span>
    </div>
    <a href="admin_dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="chart-card">
    <div class="card-header">
        <div class="header-title">
            <h1><img src="uploads/logo1.png" alt="Logo"> Product Categories</h1>
            <div class="header-subtitle">Distribution of stock across different product types</div>
        </div>
        <div class="header-actions">
            <button class="btn-action" onclick="downloadChart()"><i class="bi bi-download"></i> Save Report</button>
        </div>
    </div>

    <div class="stats-strip">
        <div class="mini-stat">
            <label>Total Categories</label>
            <span id="statCatCount">...</span>
        </div>
        <div class="mini-stat">
            <label>Total Assignments</label>
            <span id="statTotalItems">...</span>
        </div>
        <div class="mini-stat">
            <label>Top Category</label>
            <span id="statTopCat" style="color:var(--primary)">...</span>
        </div>
    </div>

    <div class="chart-layout">
        <!-- Chart -->
        <div class="canvas-container">
            <div class="loading-overlay" id="loader">
                <div class="spinner"></div>
                <div style="font-size:14px; font-weight:500;">Analyzing Data...</div>
            </div>
            <canvas id="barChart"></canvas>
        </div>

        <!-- Detailed Breakdown Table -->
        <div class="details-container">
            <div class="section-title"><i class="bi bi-table"></i> Detailed Breakdown</div>
            <div style="overflow-x: auto;">
                <table id="detailsTable">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Product Count</th>
                            <th style="width: 200px;">Share of Inventory</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows injected by JS -->
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 20px; font-size: 13px; color: var(--text-muted); line-height: 1.6;">
                <strong>Insights:</strong> <span id="insightText">Loading analysis...</span>
            </div>
        </div>
    </div>
</div>

<script>
// Setup Utils
const ctx = document.getElementById('barChart').getContext('2d');
const isDark = "<?php echo $theme; ?>" === 'dark';

// Match Dashboard Chart Defaults
Chart.defaults.color = isDark ? "#94a3b8" : "#6b7280";
Chart.defaults.borderColor = isDark ? "#242c38" : "rgba(107, 114, 128, 0.1)";
Chart.defaults.font.family = "'Inter', sans-serif";

// --- 1. DEFINE REAL DATA MAPPINGS ---
const categoryMap = {
    1: 'Accessories',
    2: 'Phone',
    3: 'Tablet',
    4: 'Laptop',
    5: 'Gaming'
};

// Relationship Map: Product ID -> [Category IDs]
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

function initChart() {
    // --- 2. CALCULATE COUNTS ---
    let catCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
    
    Object.values(productCategoryRelations).forEach(catIds => {
        catIds.forEach(id => {
            if (catCounts[id] !== undefined) {
                catCounts[id]++;
            }
        });
    });

    // --- 3. PREPARE CHART DATA ---
    const labels = Object.keys(categoryMap).map(id => categoryMap[id]); 
    const values = Object.keys(categoryMap).map(id => catCounts[id]);

    // --- 4. CALCULATE STATS ---
    const totalItems = values.reduce((a, b) => a + b, 0); 
    const maxVal = Math.max(...values);
    const topCatIndex = values.indexOf(maxVal);
    const minVal = Math.min(...values);
    const minCatIndex = values.indexOf(minVal);
    
    // Update UI Stats
    document.getElementById('statCatCount').textContent = labels.length;
    document.getElementById('statTotalItems').textContent = totalItems.toLocaleString(); 
    document.getElementById('statTopCat').textContent = labels[topCatIndex] || '-';
    if(labels[topCatIndex]) {
        document.getElementById('statTopCat').style.color = '#44D62C';
    }

    // --- 5. GENERATE TABLE & INSIGHTS ---
    const tableBody = document.querySelector('#detailsTable tbody');
    let tableHTML = '';

    // Sort for table (highest first)
    const sortedIndices = values.map((val, i) => ({ i, val })).sort((a, b) => b.val - a.val);

    sortedIndices.forEach(item => {
        const i = item.i;
        const label = labels[i];
        const val = values[i];
        const pct = totalItems > 0 ? ((val / totalItems) * 100).toFixed(1) : 0;
        const color = '#44D62C';

        tableHTML += `
            <tr>
                <td>
                    <span class="cat-indicator" style="background: ${color}"></span>
                    <span style="font-weight: 500">${label}</span>
                </td>
                <td style="text-align: right; font-weight: 600;">${val}</td>
                <td>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: ${pct}%; background: ${color}"></div>
                    </div>
                    <span class="share-text" style="margin-left: 12px;">${pct}%</span>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = tableHTML;

    // Generate Insight Text
    const topCat = labels[topCatIndex];
    const lowCat = labels[minCatIndex];
    const avgItems = (totalItems / labels.length).toFixed(1);
    
    const insightText = `
        The <strong>${topCat}</strong> category dominates your inventory with ${maxVal} items, accounting for ${((maxVal/totalItems)*100).toFixed(0)}% of total products. 
        Conversely, <strong>${lowCat}</strong> has the fewest items (${minVal}). 
        On average, you have about ${avgItems} products per category.
    `;
    document.getElementById('insightText').innerHTML = insightText;

    // Create Gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, '#44D62C');
    gradient.addColorStop(1, 'rgba(68, 214, 44, 0.05)');

    // --- 6. RENDER CHART ---
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Product Count',
                data: values,
                backgroundColor: gradient,
                borderColor: '#44D62C',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.6,
                categoryPercentage: 0.8
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
                            return ` ${context.parsed.y} Products`;
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
                    ticks: { padding: 10, stepSize: 1 }
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

    // Remove loader fallback
    setTimeout(() => { document.getElementById('loader').style.display = 'none'; }, 300);
}

function downloadChart() {
    const link = document.createElement('a');
    link.download = 'meta-shark-category-report.png';
    // Note: To capture the whole card (including table), we'd need html2canvas. 
    // For now, this just downloads the chart canvas.
    link.href = document.getElementById('barChart').toDataURL('image/png', 1.0);
    link.click();
}

// Start
initChart();
</script>
</body>
</html>