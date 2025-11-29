<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'dark'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Line Chart — Revenue</title>
<link rel="icon" href="uploads/logo1.png">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<style>
body{margin:0;font-family:Inter,system-ui,Arial;background:#0b1220;color:#e6eef6;padding:20px}
.header{display:flex;justify-content:space-between;align-items:center;gap:12px;max-width:1100px;margin:0 auto 18px}
.logo{width:44px;height:44px;background:url('uploads/logo1.png') center/cover no-repeat;border-radius:8px}
.card{max-width:1100px;margin:0 auto;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.04);padding:18px;border-radius:12px}
.canvas-wrap{height:420px}
.back{color:#aee9b4;text-decoration:none;font-weight:600}
.note{color:#9aa6b2}
/* Ensure canvas fits container and doesn't overflow */
.card .canvas-wrap { display:flex; align-items:center; justify-content:center; height:420px; }
.card .canvas-wrap canvas { max-width:100% !important; max-height:100% !important; width:auto !important; }

@ground (max-width:900px) { .card .canvas-wrap { height:320px; } }
</style>
</head>
<body>
<div class="header">
  <div style="display:flex;align-items:center;gap:12px"><div class="logo"></div><div><h2 style="margin:0">Monthly Revenue — Line</h2><div class="note">Signed in as <?php echo htmlspecialchars($admin_name); ?></div></div></div>
  <div><a class="back" href="charts_overview.php">← Charts Overview</a></div>
</div>

<div class="card">
  <h3 style="color:#44D62C;margin:0 0 12px">Monthly Revenue</h3>
  <div class="canvas-wrap"><canvas id="lineChart"></canvas></div>
</div>

<script>
async function fetchJson(url){ try{ const r=await fetch(url,{credentials:'same-origin'}); if(!r.ok) throw 0; return await r.json(); }catch(e){return null;} }
async function draw(){
  const data = await fetchJson('includes/fetch_data.php?action=monthly_revenue');
  const labels = (data && data.length) ? data.map(d=>d.ym) : ['2024-01','2024-02','2024-03'];
  const values = (data && data.length) ? data.map(d=>Number(d.amt)) : [1200,1800,1600];
  new Chart(document.getElementById('lineChart').getContext('2d'), {
    type:'line',
    data:{labels, datasets:[{label:'Revenue', data:values, borderColor:'#44D62C', backgroundColor:'rgba(68,214,44,0.12)', fill:true, tension:0.25}]},
    options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}}
  });
}
draw();
</script>
</body>
</html>
