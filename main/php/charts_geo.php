<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$currentLanguage = $_SESSION['language'] ?? 'en';

// Assuming translations.php and loadLanguage are correctly defined
require_once __DIR__ . '/includes/translations.php';
loadLanguage($currentLanguage);

$theme = $_SESSION['theme'] ?? 'dark';
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo t('charts.geo.title', 'Geo Chart — Sales by Country'); ?> — Meta Shark</title>
<link rel="icon" href="uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<style>
    /* --- Design System (Matching Dashboard) --- */
    :root {
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

    /* --- Navigation Header --- */
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

    /* --- Main Card --- */
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
        border-bottom: 1px solid var(--panel-border);
    }
    .mini-stat { display: flex; flex-direction: column; }
    .mini-stat label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; }
    .mini-stat span { font-size: 18px; font-weight: 700; color: var(--text); }

    /* Geo Chart Container */
    .geo-wrap {
        height: 640px; max-height: 640px; width: 100%; position: relative;
    }
    @media (max-width: 900px) { .geo-wrap { height: 420px; max-height: 420px; } }
    
    /* Loading Skeleton */
    .loading-overlay {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        background: var(--panel); z-index: 10;
        display: flex; align-items: center; justify-content: center;
        flex-direction: column; gap: 15px; color: var(--text-muted);
        transition: opacity 0.3s ease;
    }
    .spinner {
        width: 40px; height: 40px; border: 3px solid rgba(68,214,44,0.1);
        border-top-color: var(--primary); border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .card-header { flex-direction: column; align-items: flex-start; gap: 16px; padding: 16px; }
        .stats-strip { padding: 16px; gap: 20px; }
    }
</style>
</head>
<body>

<div class="top-nav">
    <div class="breadcrumb">
        <a href="admin_dashboard.php"><?php echo t('common.dashboard', 'Dashboard'); ?></a>
        <i class="bi bi-chevron-right"></i>
        <a href="charts_overview.php"><?php echo t('charts.overview', 'Charts Overview'); ?></a>
        <i class="bi bi-chevron-right"></i>
        <span class="active"><?php echo t('charts.geo.title', 'Geo Chart'); ?></span>
    </div>
    <a href="charts_overview.php" class="btn-back"><i class="bi bi-arrow-left"></i> <?php echo t('common.back_to_overview', 'Back to Overview'); ?></a>
</div>

<div class="chart-card">
    <div class="card-header">
        <div class="header-title">
            <h1><img src="uploads/logo1.png" alt="Logo"> <?php echo t('charts.geo.heading', 'Sales by Country — Geo'); ?></h1>
            <div class="header-subtitle"><?php echo t('charts.geo.description', 'Geographical distribution of total sales revenue.'); ?></div>
        </div>
        <div class="header-actions">
            <button class="btn-action" onclick="window.print()"><i class="bi bi-printer"></i> <?php echo t('common.print_report', 'Print Report'); ?></button>
        </div>
    </div>

    <div class="stats-strip">
        <div class="mini-stat">
            <label><?php echo t('charts.geo.total_sales', 'Total Sales (USD)'); ?></label>
            <span id="statTotalSales">...</span>
        </div>
        <div class="mini-stat">
            <label><?php echo t('charts.geo.countries_active', 'Active Countries'); ?></label>
            <span id="statCountryCount">...</span>
        </div>
        <div class="mini-stat">
            <label><?php echo t('charts.geo.highest_sale', 'Highest Sales'); ?></label>
            <span id="statTopCountry" style="color:var(--primary)">...</span>
        </div>
    </div>

    <div id="geoChart" class="geo-wrap">
        <div class="loading-overlay" id="loader">
            <div class="spinner"></div>
            <div style="font-size:14px; font-weight:500;"><?php echo t('charts.geo.loading', 'Loading Geographical Data...'); ?></div>
        </div>
    </div>
</div>

<script>
google.charts.load('current', {'packages':['geochart']});
let geoReady=false;
google.charts.setOnLoadCallback(()=>{ geoReady=true; drawGeo(); });

async function fetchJson(url){
    try {
        const r = await fetch(url, {credentials: 'same-origin'});
        if (!r.ok) throw new Error('Network error');
        return await r.json();
    } catch(e) {
        console.error("Fetch Error:", e);
        return null;
    }
}

function formatCurrency(amount) {
    const lang = '<?php echo htmlspecialchars($currentLanguage); ?>';
    return new Intl.NumberFormat(lang === 'fr' ? 'fr-FR' : 'en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function updateSummaryStats(data, countryLabel, salesLabel) {
    if (!data.length) {
        document.getElementById('statTotalSales').textContent = formatCurrency(0);
        document.getElementById('statCountryCount').textContent = 0;
        document.getElementById('statTopCountry').textContent = 'N/A';
        return;
    }

    const totalSales = data.reduce((sum, r) => sum + Number(r.value || 0), 0);
    let maxSale = 0;
    let topCountry = 'N/A';
    
    data.forEach(r => {
        const value = Number(r.value || 0);
        if (value > maxSale) {
            maxSale = value;
            topCountry = r.country;
        }
    });

    document.getElementById('statTotalSales').textContent = formatCurrency(totalSales);
    document.getElementById('statCountryCount').textContent = data.length;
    document.getElementById('statTopCountry').textContent = `${topCountry} (${formatCurrency(maxSale)})`;
}

async function drawGeo(){
    const srv = await fetchJson('includes/fetch_data.php?action=sales_by_country');
    let dataForChart = [];
    
    const countryLabel = '<?php echo t("charts.geo.country", "Country"); ?>';
    const salesLabel = '<?php echo t("charts.geo.sales", "Sales"); ?>';
    const isDarkTheme = '<?php echo $theme; ?>' === 'dark';

    if (srv && srv.length) {
        // Prepare data for Google Charts
        dataForChart.push([countryLabel, salesLabel]);
        srv.forEach(r => {
            const country = (r.country || '').trim();
            const value = Number(r.value || 0);
            if (country && value > 0) {
                dataForChart.push([country, value]);
            }
        });
        
        // If only header row, use sample data
        if (dataForChart.length === 1) dataForChart = null;
    }

    // Fallback to sample data if fetch failed or returned no useful rows
    if (!dataForChart) {
        dataForChart = [[countryLabel, salesLabel],
                        ['United States',12000], ['Canada',3100], ['Brazil',2400], 
                        ['United Kingdom',5400], ['Germany',4200], ['India',7200], 
                        ['Australia',1900]];
        // Use sample data to calculate summary stats
        const sampleData = dataForChart.slice(1).map(row => ({country: row[0], value: row[1]}));
        updateSummaryStats(sampleData, countryLabel, salesLabel);
    } else {
        // Calculate and update stats using real data
        const realData = srv.map(row => ({country: row.country, value: row.value}));
        updateSummaryStats(realData, countryLabel, salesLabel);
    }
    
    if (!geoReady) { setTimeout(drawGeo, 300); return; }

    try {
        const container = document.getElementById('geoChart');
        const loader = document.getElementById('loader');
        
        const data = google.visualization.arrayToDataTable(dataForChart);
        
        // Calculate min/max for better color scaling
        const values = dataForChart.slice(1).map(r => r[1]).filter(v => v > 0);
        const minValue = values.length > 0 ? Math.min(...values) : 0;
        const maxValue = values.length > 0 ? Math.max(...values) : 10000;
        
        const options = {
            colorAxis: { 
                // Green gradient for high sales
                colors: isDarkTheme ? ['#242c38', '#88d767', '#44D62C'] : ['#eaf8e8', '#88d767', '#44D62C'],
                minValue: minValue,
                maxValue: maxValue
            },
            backgroundColor: 'transparent',
            // Color for regions with no data (darker gray for dark mode)
            datalessRegionColor: isDarkTheme ? '#2e3a4e' : '#f5f5f5', 
            defaultColor: isDarkTheme ? '#242c38' : '#e5e7eb',
            legend: {
                textStyle: { color: isDarkTheme ? '#e6eef6' : '#0b1220' }
            },
            keepAspectRatio: true,
            resolution: 'countries',
            tooltip: {
                // Tooltip background is always white/light in Google Charts, so text should be dark
                textStyle: { color: '#1f2937' }, 
                showColorCode: true
            }
        };
        const chart = new google.visualization.GeoChart(container);
        
        // Hide loader when chart is ready
        google.visualization.events.addListener(chart, 'ready', function() {
            loader.style.opacity = '0';
            setTimeout(() => { loader.style.display = 'none'; }, 300);
        });

        chart.draw(data, options);
    } catch(e) {
        console.warn('GeoChart draw failed', e);
        const container = document.getElementById('geoChart');
        if (container) {
            const errorMsg = '<?php echo t("charts.geo.unavailable", "Geography chart unavailable."); ?>';
            container.innerHTML = `<div class="loading-overlay" style="opacity:1; background:var(--panel); position:static;">
                                        <i class="bi bi-x-circle" style="font-size:24px; color:var(--text-muted)"></i>
                                        <div style="font-size:14px; font-weight:500; max-width: 300px; text-align: center;">${errorMsg} <br> ${e.message}</div>
                                    </div>`;
        }
    }
}

// Redraw on resize with debounce
let geoResizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(geoResizeTimer);
    geoResizeTimer = setTimeout(() => { 
        try { 
            document.getElementById('loader').style.display = 'flex';
            document.getElementById('loader').style.opacity = '1';
            drawGeo(); 
        } catch(e) {} 
    }, 300);
});

// Initial call
// Initial display of the loader is handled by the HTML structure
drawGeo();
</script>
</body>
</html>