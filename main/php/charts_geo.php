<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php'; // MySQLi connection ($conn)

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$currentLanguage = $_SESSION['language'] ?? 'en';
$theme = $_SESSION['theme'] ?? 'dark';

// Fallback translation function
if (file_exists(__DIR__ . '/includes/translations.php')) {
    require_once __DIR__ . '/includes/translations.php';
    loadLanguage($currentLanguage);
} else {
    function t($key, $default) { return $default; }
}

// If called via AJAX to fetch users by country
if (isset($_GET['action']) && $_GET['action'] === 'users_by_country') {
    $users_by_country = [];
    $sql = "SELECT country, COUNT(*) as value FROM users WHERE country != '' GROUP BY country";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users_by_country[] = [
                'country' => $row['country'],
                'value' => (int)$row['value']
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($users_by_country);
    exit;
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo t('charts.geo.title', 'Geo Chart — Users by Country'); ?> — Meta Shark</title>
<link rel="icon" href="uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<style>
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
* { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', system-ui, sans-serif; }
body { background:var(--bg); color:var(--text); padding:40px 20px; min-height:100vh; }
a { text-decoration:none; color:inherit; transition:0.2s; }
.top-nav { max-width:1200px; margin:0 auto 30px; display:flex; justify-content:space-between; align-items:center; }
.breadcrumb { display:flex; align-items:center; gap:8px; font-size:14px; color:var(--text-muted); }
.breadcrumb a:hover { color:var(--primary); }
.breadcrumb i { font-size:12px; opacity:0.5; }
.breadcrumb .active { color:var(--text); font-weight:600; }
.btn-back { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; background:var(--panel); border:1px solid var(--panel-border); border-radius:8px; font-weight:500; font-size:14px; color:var(--text); }
.btn-back:hover { border-color:var(--primary); color:var(--primary); }
.chart-card { max-width:1200px; margin:0 auto; background:var(--panel); border:1px solid var(--panel-border); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.card-header { padding:24px 32px; border-bottom:1px solid var(--panel-border); display:flex; justify-content:space-between; align-items:flex-end; background:rgba(255,255,255,0.01); }
.header-title h1 { font-size:20px; font-weight:700; display:flex; align-items:center; gap:12px; }
.header-title h1 img { height:28px; }
.header-subtitle { font-size:13px; color:var(--text-muted); margin-top:4px; }
.header-actions { display:flex; gap:12px; }
.btn-action { background: rgba(139,92,246,0.1); color:var(--primary); border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; transition:0.2s; display:flex; align-items:center; gap:6px; }
.btn-action:hover { background:var(--primary); color:#fff; }
.stats-strip { display:flex; gap:40px; padding:20px 32px; border-bottom:1px solid var(--panel-border); }
.mini-stat { display:flex; flex-direction:column; }
.mini-stat label { font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-bottom:4px; }
.mini-stat span { font-size:18px; font-weight:700; color:var(--text); }
.chart-area-wrapper { position:relative; width:100%; min-height:640px; }
.geo-wrap { height:640px; width:100%; }
@media (max-width:900px) { .geo-wrap, .chart-area-wrapper { height:420px; min-height:420px; } }
.loading-overlay { position:absolute; top:0; left:0; right:0; bottom:0; background:var(--panel); z-index:10; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:15px; color:var(--text-muted); transition:opacity 0.5s ease; }
.spinner { width:40px; height:40px; border:3px solid rgba(139,92,246,0.1); border-top-color:var(--primary); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width:768px) { .card-header { flex-direction:column; align-items:flex-start; gap:16px; padding:16px; } .stats-strip { padding:16px; gap:20px; }
}
</style>
</head>
<body>

<div class="top-nav">
    <div class="breadcrumb">
        <a href="admin_dashboard.php"><?php echo t('common.dashboard','Dashboard'); ?></a>
        <i class="bi bi-chevron-right"></i>
        <a href="charts_overview.php"><?php echo t('charts.overview','Charts Overview'); ?></a>
        <i class="bi bi-chevron-right"></i>
        <span class="active"><?php echo t('charts.geo.title','Geo Chart'); ?></span>
    </div>
    <a href="charts_overview.php" class="btn-back"><i class="bi bi-arrow-left"></i> <?php echo t('common.back_to_overview','Back to Overview'); ?></a>
</div>

<div class="chart-card">
    <div class="card-header">
        <div class="header-title">
            <h1><img src="uploads/logo1.png" alt="Logo" onerror="this.src='https://placehold.co/100x40?text=MetaShark'"> <?php echo t('charts.geo.heading_users','User Distribution'); ?></h1>
            <div class="header-subtitle"><?php echo t('charts.geo.description_users','Global map showing where your registered users are located.'); ?></div>
        </div>
        <div class="header-actions">
            <button class="btn-action" onclick="window.print()"><i class="bi bi-printer"></i> <?php echo t('common.print_report','Print Report'); ?></button>
        </div>
    </div>

    <div class="stats-strip">
        <div class="mini-stat">
            <label><?php echo t('charts.geo.total_users','Total Users'); ?></label>
            <span id="statTotalUsers">...</span>
        </div>
        <div class="mini-stat">
            <label><?php echo t('charts.geo.countries_active','Active Countries'); ?></label>
            <span id="statCountryCount">...</span>
        </div>
        <div class="mini-stat">
            <label><?php echo t('charts.geo.highest_density','Top Country'); ?></label>
            <span id="statTopCountry" style="color:var(--primary)">...</span>
        </div>
    </div>

    <div class="chart-area-wrapper">
        <div class="loading-overlay" id="loader">
            <div class="spinner"></div>
            <div style="font-size:14px; font-weight:500;"><?php echo t('charts.geo.loading','Loading User Data...'); ?></div>
        </div>
        <div id="geoChart" class="geo-wrap"></div>
    </div>
</div>

<script>
google.charts.load('current', {'packages':['geochart']});

const dataFetchPromise = fetchJson('charts_geo.php?action=users_by_country');

google.charts.setOnLoadCallback(async () => {
    try {
        const srv = await dataFetchPromise;
        drawGeo(srv);
    } catch(e) {
        console.error("GeoChart error", e);
        drawGeo([]);
    }
});

async function fetchJson(url){
    try {
        const r = await fetch(url, {credentials:'same-origin'});
        if(!r.ok) return [];
        return await r.json();
    } catch(e) {
        console.warn("Fetch failed:", e);
        return [];
    }
}

function formatNumber(amount){ return new Intl.NumberFormat('en-US').format(amount); }

function updateSummaryStats(data){
    if(!data || !data.length){
        document.getElementById('statTotalUsers').textContent = "0";
        document.getElementById('statCountryCount').textContent = "0";
        document.getElementById('statTopCountry').textContent = 'None';
        return;
    }
    const totalUsers = data.reduce((sum,r)=>sum+Number(r.value||0),0);
    let maxUsers = 0;
    let topCountry = 'N/A';
    data.forEach(r=>{
        const value = Number(r.value||0);
        if(value>maxUsers){ maxUsers=value; topCountry=r.country; }
    });
    document.getElementById('statTotalUsers').textContent = formatNumber(totalUsers);
    document.getElementById('statCountryCount').textContent = data.length;
    document.getElementById('statTopCountry').textContent = `${topCountry} (${formatNumber(maxUsers)})`;
}

function drawGeo(srv){
    const countryLabel = '<?php echo t("charts.geo.country","Country"); ?>';
    const usersLabel = '<?php echo t("charts.geo.users","Users"); ?>';
    const isDarkTheme = '<?php echo $theme; ?>'==='dark';

    let dataForChart = [[countryLabel, usersLabel]];
    let statsData = [];

    if(srv && Array.isArray(srv) && srv.length>0){
        srv.forEach(r=>{
            const country = (r.country||'').trim();
            const value = Number(r.value||0);
            if(country && value>0){
                dataForChart.push([country,value]);
                statsData.push({country,value});
            }
        });
    }

    if(dataForChart.length===1){
        dataForChart.push(['',0]);
        updateSummaryStats([]);
    } else {
        updateSummaryStats(statsData);
    }

    try {
        const container = document.getElementById('geoChart');
        const loader = document.getElementById('loader');
        const data = google.visualization.arrayToDataTable(dataForChart);
        let values = statsData.length>0?statsData.map(r=>r.value):[];
        const minValue = values.length>0?Math.min(...values):0;
        const maxValue = values.length>0?Math.max(...values):100;
        const options = {
                colorAxis: { colors: ['#1e3a8a', '#44D62C'] }, // Dark Blue to Neon Green
            backgroundColor:'transparent',
            datalessRegionColor: isDarkTheme?'#242c38':'#e5e7eb',
            defaultColor:isDarkTheme?'#1e1e1e':'#f5f5f5',
            legend:{ textStyle:{ color:isDarkTheme?'#e6eef6':'#1f2937'} },
            tooltip:{ textStyle:{ color:'#1f2937' }, showColorCode:true },
            keepAspectRatio:true
        };
        const chart = new google.visualization.GeoChart(container);
        google.visualization.events.addListener(chart,'ready',()=>{ if(loader){ loader.style.opacity='0'; setTimeout(()=>{loader.style.display='none';},500); } });
        chart.draw(data,options);
    } catch(e){
        console.warn('GeoChart draw failed',e);
        const container = document.getElementById('geoChart');
        if(container){ container.innerHTML=`<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted);"><i class="bi bi-exclamation-circle" style="font-size:32px; margin-bottom:10px; opacity:0.5;"></i><div style="font-size:16px;">Map rendering error. Data may be invalid.</div></div>`; }
    }
}

let geoResizeTimer=null;
window.addEventListener('resize',()=>{
    clearTimeout(geoResizeTimer);
    geoResizeTimer=setTimeout(()=>{ fetchJson('charts_geo.php?action=users_by_country').then(srv=>drawGeo(srv)); },300);
});
</script>

</body>
</html>
