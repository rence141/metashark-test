<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// --- INTERNAL AJAX HANDLER FOR USER GROWTH ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'user_growth') {
    header('Content-Type: application/json');
    $days = 90; 
    $data = [];
    
    // 1. Get baseline counts
    $sqlBase = "SELECT 
                    SUM(CASE WHEN is_deleted = 0 AND is_suspended = 0 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_deleted = 0 AND is_suspended = 1 THEN 1 ELSE 0 END) as suspended,
                    SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) as deleted
                FROM users 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
                
    $stmt = $conn->prepare($sqlBase);
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $resBase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $runningActive = $resBase['active'] ?? 0;
    $runningSuspended = $resBase['suspended'] ?? 0;
    $runningDeleted = $resBase['deleted'] ?? 0;

    // 2. Get daily growth grouped by status
    $sqlTrend = "SELECT 
                    DATE(created_at) as date, 
                    SUM(CASE WHEN is_deleted = 0 AND is_suspended = 0 THEN 1 ELSE 0 END) as active_new,
                    SUM(CASE WHEN is_deleted = 0 AND is_suspended = 1 THEN 1 ELSE 0 END) as suspended_new,
                    SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) as deleted_new
                 FROM users 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                 GROUP BY DATE(created_at) 
                 ORDER BY date ASC";
                 
    $stmt = $conn->prepare($sqlTrend);
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $runningActive += (int)$row['active_new'];
        $runningSuspended += (int)$row['suspended_new'];
        $runningDeleted += (int)$row['deleted_new'];
        
        $data[] = [
            'date' => date('M d', strtotime($row['date'])),
            'total' => $runningActive + $runningSuspended + $runningDeleted,
            'active' => $runningActive,
            'suspended' => $runningSuspended,
            'deleted' => $runningDeleted
        ];
    }
    
    if (empty($data)) {
        $data[] = [
            'date' => date('M d'), 
            'total' => $runningActive + $runningSuspended + $runningDeleted, 
            'active' => $runningActive, 
            'suspended' => $runningSuspended,
            'deleted' => $runningDeleted
        ];
    }

    echo json_encode($data);
    exit;
}
// --- END INTERNAL AJAX ---

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';

$admin_initial = strtoupper(substr($admin_name, 0, 1));

$pending_requests = [];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, created_at, token FROM admin_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $pending_requests[] = $r;
    $stmt->close();
}
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
        --accent: #00ff88;
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --sidebar-width: 260px;
        --danger: #f44336; 
    }

    [data-theme="dark"] {
        --bg: #0f1115;
        --panel: #161b22;
        --panel-border: #242c38;
        --text: #e6eef6;
        --text-muted: #94a3b8;
        --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; }
    a { text-decoration: none; color: inherit; }

    /* --- Animations --- */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in { animation: fadeIn 0.5s ease forwards; }

    /* --- Layout --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 70px;
        background: var(--panel); border-bottom: 1px solid var(--panel-border);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 50; backdrop-filter: blur(10px);
        box-shadow: var(--shadow);
    }

    .navbar-left { display: flex; align-items: center; gap: 16px; }
    .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; letter-spacing: -0.5px; }
    .logo-area img { height: 32px; filter: drop-shadow(0 0 5px var(--primary-glow)); }
    
    .sidebar-toggle { display: none; background: none; border: none; color: var(--text); font-size: 24px; cursor: pointer; }

    /* --- Profile Widget --- */
    .navbar-profile-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; transition: var(--transition); color: var(--text); }
    .navbar-profile-link:hover { background: rgba(68,214,44,0.1); color: var(--primary); }
    .profile-info-display { text-align: right; line-height: 1.2; display: none; }
    @media (min-width: 640px) { .profile-info-display { display: block; } }
    .profile-name { font-size: 14px; font-weight: 600; color: var(--text); transition: color 0.2s; }
    .navbar-profile-link:hover .profile-name { color: var(--primary); }
    .profile-role { font-size: 11px; color: var(--text-muted); }
    .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--primary); box-shadow: 0 0 8px var(--primary-glow); flex-shrink: 0; }

    .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: var(--transition); z-index: 40; }
    .sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
    .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: var(--transition); margin-bottom: 4px; }
    .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
    [data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
    .sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
    .sidebar-item i { font-size: 18px; }

    .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); transition: var(--transition); }

    /* --- Components --- */
    .dashboard-header { margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end; }
    .dashboard-header h2 { font-size: 24px; font-weight: 700; }
    .dashboard-header p { color: var(--text-muted); font-size: 14px; margin-top: 4px; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
    .stat-card { background: var(--panel); padding: 24px; border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); position: relative; overflow: hidden; }
    .stat-card::after { content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100px; background: linear-gradient(135deg, transparent, rgba(68,214,44,0.05)); border-radius: 0 0 0 100%; pointer-events: none; }
    .stat-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .stat-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(68,214,44,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-value { font-size: 32px; font-weight: 800; letter-spacing: -1px; margin-bottom: 4px; }
    .stat-label { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .stat-sub { font-size: 12px; color: var(--primary); margin-top: 4px; display: flex; align-items: center; gap: 4px; }

    .grid-stack { display: grid; gap: 24px; margin-bottom: 24px; }
    .charts-row { grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
    .card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); display: flex; flex-direction: column; }
    .card-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); display: flex; justify-content: space-between; align-items: center; }
    .card-header h3 { font-size: 16px; font-weight: 600; margin: 0; }
    .card-body { padding: 24px; position: relative; }

    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); }
    td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }
    [data-theme="light"] tr:hover td { background: #f9fafb; }

    /* Category Badge */
    .cat-badge { font-size: 10px; padding: 3px 8px; border-radius: 4px; margin-right: 4px; display: inline-block; background: rgba(255,255,255,0.05); border: 1px solid var(--panel-border); color: var(--text-muted); }
    
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
    .badge-success { background: rgba(68,214,44,0.15); color: var(--primary); }
    .badge-warning { background: rgba(255,152,0,0.15); color: #ff9800; }
    .badge-danger { background: rgba(244,67,54,0.15); color: #f44336; }

    /* Stock Badge */
    .stock-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 60px; text-align: center; }
    .stock-low { background: rgba(244, 67, 54, 0.15); color: var(--danger); }
    .stock-ok { background: rgba(68, 214, 44, 0.15); color: var(--primary); }

    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; border: 1px solid transparent; display: inline-block; }
    .btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-primary:hover { filter: brightness(1.1); }

    .skeleton { background: var(--panel-border); border-radius: 4px; animation: pulse 1.5s infinite; color: transparent !important; user-select: none; }
    @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 0.3; } 100% { opacity: 0.6; } }

    /* --- PRINT STYLES --- */
    @media print {
        .admin-sidebar, .admin-navbar, .sidebar-toggle, #revenueToggle, #exportAction { display: none !important; }
        .admin-main { margin: 0 !important; padding: 10px !important; width: 100% !important; max-width: 100% !important; min-height: auto !important; }
        body { background: white !important; color: black !important; -webkit-print-color-adjust: exact; overflow: visible !important; width: 100% !important; }
        .stats-grid, .grid-stack, .charts-row { display: block !important; width: 100% !important; grid-template-columns: 1fr !important; }
        .stat-card, .card { box-shadow: none !important; border: 1px solid #ccc !important; break-inside: avoid; page-break-inside: avoid; background: #fff !important; margin-bottom: 15px !important; width: 100% !important; }
        .card-body { padding: 10px !important; height: auto !important; max-height: 300px !important; }
        canvas { max-height: 250px !important; width: 100% !important; height: auto !important; }
        h2, h3, .stat-value, .stat-label { color: #000 !important; }
        .table-responsive { overflow: visible !important; width: 100% !important; }
        * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
    }

    @media (max-width: 992px) {
        .admin-sidebar { transform: translateX(-100%); }
        .admin-sidebar.show { transform: translateX(0); }
        .admin-main { margin-left: 0; }
        .sidebar-toggle { display: block; }
    }
    </style>
</head>
<body>

<?php include 'admin_navbar.php'; ?>
<?php include 'admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="dashboard-header fade-in">
        <div>
            <h2>Dashboard Overview</h2>
            <p>Welcome back, here's what's happening with your store today.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <select id="exportAction" class="btn-xs btn-outline" style="background:var(--panel);">
                <option value="" disabled selected>Export Statistics</option>
                <option value="print">Print Dashboard</option>
                <option value="csv">Download CSV Data</option>
            </select>

            <select id="revenueToggle" class="btn-xs btn-outline" style="background:var(--panel);">
                <option value="monthly">Monthly View</option>
                <option value="daily">Daily View</option>
            </select>
        </div>
    </div>

    <div class="stats-grid fade-in">
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon"><i class="bi bi-box"></i></div></div>
            <div class="stat-value" id="totalProducts"><span class="skeleton">000</span></div>
            <div class="stat-label">Total Products</div>
            <div class="stat-sub" id="productsInfo"><span class="skeleton">Stock info loading...</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon" style="color:#00d4ff; background:rgba(0,212,255,0.1)"><i class="bi bi-currency-dollar"></i></div></div>
            <div class="stat-value">$<span id="totalRevenue"><span class="skeleton">0,000</span></span></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub" style="color:#00d4ff"><i class="bi bi-arrow-up-short"></i> Lifetime Earnings</div>
        </div>
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon" style="color:#ff9800; background:rgba(255,152,0,0.1)"><i class="bi bi-bag"></i></div></div>
            <div class="stat-value" id="totalOrders"><span class="skeleton">000</span></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-sub" style="color:#ff9800">Processing & Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-head"><div class="stat-icon" style="color:#f44336; background:rgba(244,67,54,0.1)"><i class="bi bi-shop"></i></div></div>
            <div class="stat-value" id="totalSellers"><span class="skeleton">00</span></div>
            <div class="stat-label">Active Sellers</div>
        </div>
    </div>

    <div class="grid-stack charts-row fade-in" style="animation-delay: 0.1s;">
        <div class="card">
            <div class="card-header"><h3>Revenue Trend</h3></div>
            <div class="card-body"><canvas id="revenueChart" height="250"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Orders by Status</h3></div>
            <div class="card-body" style="position: relative; height:300px; display:flex; align-items:center; justify-content:center;">
                <canvas id="ordersChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="grid-stack charts-row fade-in" style="animation-delay: 0.3s;">
        <div class="card">
            <div class="card-header"><h3>Sales by Country</h3></div>
            <div class="card-body"><canvas id="geoChart" height="250"></canvas></div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>Top Products</h3></div>
            <div class="table-responsive">
                <table id="topProductsTable">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Stock Status</th> <th style="text-align:right">Total Sold</th> </tr>
                    </thead>
                    <tbody><tr><td colspan="4"><span class="skeleton">Loading data...</span></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid-stack charts-row fade-in" style="animation-delay: 0.4s;">
        <div class="card">
            <div class="card-header"><h3>Product Categories</h3></div>
            <div class="card-body"><canvas id="categoryChart" height="200"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>User Stats (Active vs Suspended vs Deleted)</h3></div>
            <div class="card-body"><canvas id="usersChart" height="80"></canvas></div>
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
            fetchWithTimeout('includes/fetch_data.php?action=monthly_revenue'),
            fetchWithTimeout('includes/fetch_data.php?action=top_products'),
            fetchWithTimeout('includes/fetch_data.php?action=sales_by_country'),
            fetchWithTimeout('admin_dashboard.php?ajax_action=user_growth') 
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

// Helper function for fetch with timeout and cache busting
async function fetchWithTimeout(url, options = {}, timeout = 8000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeout);
    // Add cache busting parameter
    const separator = url.includes('?') ? '&' : '?';
    const cacheBuster = separator + '_t=' + Date.now();
    try {
        const response = await fetch(url + cacheBuster, { 
            ...options, 
            signal: controller.signal,
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache',
                ...options.headers
            }
        });
        clearTimeout(id);
        return response;
    } catch (error) {
        clearTimeout(id);
        if (error.name === 'AbortError') {
            throw new Error('Request timeout');
        }
        throw error;
    }
}

// Helper to show error message in chart container
function showChartError(containerId, message) {
    const canvas = document.getElementById(containerId);
    if (!canvas) return;
    const container = canvas.closest('.card-body');
    if (container) {
        container.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:100%;min-height:200px;color:var(--text-muted);text-align:center;padding:20px;">${message}</div>`;
    }
}

async function loadDashboard() {
    try {
        const res = await fetchWithTimeout('includes/fetch_data.php?action=dashboard_stats');
        if (!res.ok) {
            const errorText = await res.text();
            console.error('Dashboard stats error:', res.status, errorText);
            throw new Error('Failed to load');
        }
        const data = await res.json();
        
        // Check if we got an error response
        if (data.error) {
            console.error('API error:', data.error);
            throw new Error(data.error);
        }
        
        // Update stats
        if (data.total_products !== undefined) {
            animateValue("totalProducts", 0, data.total_products || 0, 1000);
        }
        if (data.total_stock !== undefined) {
            document.getElementById('productsInfo').innerHTML = `Total Stock: ${(data.total_stock || 0).toLocaleString()}`;
        }
        if (data.total_revenue !== undefined) {
            document.getElementById('totalRevenue').textContent = (data.total_revenue || 0).toLocaleString();
        }
        if (data.total_orders !== undefined) {
            animateValue("totalOrders", 0, data.total_orders || 0, 1000);
        }
        if (data.total_sellers !== undefined) {
            document.getElementById('totalSellers').textContent = data.total_sellers || 0;
        }
    } catch(e) { 
        console.error('loadDashboard error:', e);
        const productsEl = document.getElementById('totalProducts');
        const revenueEl = document.getElementById('totalRevenue');
        const ordersEl = document.getElementById('totalOrders');
        const sellersEl = document.getElementById('totalSellers');
        const infoEl = document.getElementById('productsInfo');
        
        if (productsEl) productsEl.innerHTML = '0';
        if (revenueEl) revenueEl.textContent = '0';
        if (ordersEl) ordersEl.innerHTML = '0';
        if (sellersEl) sellersEl.textContent = '0';
        if (infoEl) infoEl.innerHTML = 'Unable to load';
    }
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
        const resProd = await fetchWithTimeout('includes/fetch_data.php?action=top_products');
        if (!resProd.ok) throw new Error('Failed to load');
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
        document.querySelector('#topProductsTable tbody').innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Unable to load products</td></tr>';
    }
}

// Initialize charts with empty data first
function initEmptyCharts() {
    const catCtx = document.getElementById('categoryChart');
    if (catCtx && !charts.category) {
        charts.category = new Chart(catCtx, {
            type: 'bar',
            data: { labels: ['Loading...'], datasets: [{ label: 'Products', data: [0], backgroundColor: '#44D62C', borderColor: '#44D62C', borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { borderDash: [5, 5] }, beginAtZero: true } } }
        });
    }
    
    const revCtx = document.getElementById('revenueChart');
    if (revCtx && !charts.revenue) {
        charts.revenue = new Chart(revCtx.getContext('2d'), {
            type: 'line',
            data: { labels: ['Loading...'], datasets: [{ label: 'Revenue', data: [0], borderColor: '#44D62C', backgroundColor: 'rgba(68, 214, 44, 0.1)', fill: true, tension: 0.4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { borderDash: [5, 5] } }, x: { grid: { display: false } } } }
        });
    }
    
    const ordCanvas = document.getElementById('ordersChart');
    if (ordCanvas && !charts.orders) {
        const ctx = ordCanvas.getContext('2d');
        const style = getComputedStyle(document.documentElement);
        const panelColor = style.getPropertyValue('--panel').trim();
        charts.orders = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['Loading...'], datasets: [{ data: [1], backgroundColor: ['#44D62C'], borderColor: panelColor, borderWidth: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', padding: 20, font: { size: 12 } } } } }
        });
    }
    
    const geoCtx = document.getElementById('geoChart');
    if (geoCtx && !charts.geo) {
        charts.geo = new Chart(geoCtx, {
            type: 'bar',
            data: { labels: ['Loading...'], datasets: [{ label: 'Volume', data: [0], backgroundColor: '#44D62C', borderColor: '#44D62C', borderWidth: 1 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
    
    const userCtx = document.getElementById('usersChart');
    if (userCtx && !charts.users) {
        charts.users = new Chart(userCtx.getContext('2d'), {
            type: 'line',
            data: { labels: ['Loading...'], datasets: [{ label: 'Users', data: [0], borderColor: '#94a3b8', borderWidth: 2, fill: false }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, labels: { boxWidth: 10, usePointStyle: true, font: { size: 11 } } } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }
        });
    }
}

async function loadCharts() {
    // Initialize empty charts first
    initEmptyCharts();
    
    // 1. CATEGORY CHART
    try {
        const resCat = await fetchWithTimeout('includes/fetch_data.php?action=category_distribution');
        if (resCat.ok) {
            const catData = await resCat.json();
            if (catData.error) {
                console.error('Category chart error:', catData.error);
                throw new Error(catData.error);
            }
            if (Array.isArray(catData) && catData.length > 0 && charts.category) {
                const labels = catData.map(c => c.category || 'Unknown');
                const values = catData.map(c => Number(c.count) || 0);
                charts.category.data.labels = labels;
                charts.category.data.datasets[0].data = values;
                charts.category.data.datasets[0].backgroundColor = (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 200); 
                    gradient.addColorStop(0, '#44D62C');
                    gradient.addColorStop(1, 'rgba(68, 214, 44, 0.05)');
                    return gradient;
                };
                charts.category.update('active');
            } else if (charts.category) {
                charts.category.data.labels = ['No data available'];
                charts.category.data.datasets[0].data = [0];
                charts.category.update('active');
            }
        } else {
            const errorText = await resCat.text();
            console.error('Category chart HTTP error:', resCat.status, errorText);
        }
    } catch (e) { 
        console.error('Category chart error:', e);
        if (charts.category) {
            charts.category.data.labels = ['Error loading data'];
            charts.category.data.datasets[0].data = [0];
            charts.category.update('active');
        }
    }

    // 2. REVENUE CHART
    try {
        const resRev = await fetchWithTimeout('includes/fetch_data.php?action=monthly_revenue');
        if (resRev.ok && charts.revenue) {
            const revData = await resRev.json();
            if (revData.error) {
                console.error('Revenue chart error:', revData.error);
                throw new Error(revData.error);
            }
            if (Array.isArray(revData) && revData.length > 0) {
                charts.revenue.data.labels = revData.map(r => r.ym || 'Unknown');
                charts.revenue.data.datasets[0].data = revData.map(r => Number(r.amt) || 0);
                charts.revenue.data.datasets[0].backgroundColor = (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(68, 214, 44, 0.4)');
                    gradient.addColorStop(1, 'rgba(68, 214, 44, 0.0)');
                    return gradient;
                };
                charts.revenue.data.datasets[0].pointBackgroundColor = '#44D62C';
                charts.revenue.data.datasets[0].pointRadius = 4;
                charts.revenue.update('active');
            } else if (charts.revenue) {
                charts.revenue.data.labels = ['No data available'];
                charts.revenue.data.datasets[0].data = [0];
                charts.revenue.update('active');
            }
        } else if (charts.revenue) {
            const errorText = await resRev.text();
            console.error('Revenue chart HTTP error:', resRev.status, errorText);
            charts.revenue.data.labels = ['Error loading'];
            charts.revenue.data.datasets[0].data = [0];
            charts.revenue.update('active');
        }
    } catch (e) { 
        console.error('Revenue chart error:', e);
        if (charts.revenue) {
            charts.revenue.data.labels = ['Error loading data'];
            charts.revenue.data.datasets[0].data = [0];
            charts.revenue.update('active');
        }
    }

    // 3. ORDERS CHART
    try {
        const resOrd = await fetchWithTimeout('includes/fetch_data.php?action=orders_summary');
        if (resOrd.ok && charts.orders) {
            const ordData = await resOrd.json();
            if (ordData.error) {
                console.error('Orders chart error:', ordData.error);
                throw new Error(ordData.error);
            }
            if (Array.isArray(ordData) && ordData.length > 0) {
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
                    charts.orders.data.labels = ordData.map(o => o.status || 'Unknown');
                    charts.orders.data.datasets[0].data = ordData.map(o => Number(o.cnt) || 0);
                    charts.orders.data.datasets[0].backgroundColor = ordData.map((_, i) => gradients[i % gradients.length]);
                    charts.orders.options.plugins.tooltip = { backgroundColor: 'rgba(22, 27, 34, 0.95)', padding: 12, cornerRadius: 8, callbacks: { label: function(context) { const value = context.parsed; const total = context.chart._metasets[context.datasetIndex].total; const percentage = ((value / total) * 100).toFixed(1) + '%'; return ` ${context.label}: ${value} (${percentage})`; } } };
                    charts.orders.update('active');
                }
            } else if (charts.orders) {
                charts.orders.data.labels = ['No data available'];
                charts.orders.data.datasets[0].data = [1];
                charts.orders.update('active');
            }
        } else if (charts.orders) {
            const errorText = await resOrd.text();
            console.error('Orders chart HTTP error:', resOrd.status, errorText);
            charts.orders.data.labels = ['Error loading'];
            charts.orders.data.datasets[0].data = [1];
            charts.orders.update('active');
        }
    } catch (e) { 
        console.error('Orders chart error:', e);
        if (charts.orders) {
            charts.orders.data.labels = ['Error loading data'];
            charts.orders.data.datasets[0].data = [1];
            charts.orders.update('active');
        }
    }
    
    // 4. GEO CHART
    try {
        const resGeo = await fetchWithTimeout('includes/fetch_data.php?action=sales_by_country');
        if (resGeo.ok && charts.geo) {
            const geoData = await resGeo.json();
            if (geoData.error) {
                console.error('Geo chart error:', geoData.error);
                throw new Error(geoData.error);
            }
            if (Array.isArray(geoData) && geoData.length > 0) {
                geoData.sort((a, b) => b.value - a.value);
                const totalVolume = geoData.reduce((acc, curr) => acc + Number(curr.value), 0);
                const labels = geoData.map(g => `$  ${g.country || 'Unknown'}`);
                const geoCtx = document.getElementById('geoChart');
                if (geoCtx) {
                    const ctx2d = geoCtx.getContext('2d');
                    const gradient = ctx2d.createLinearGradient(0, 0, 400, 0);
                    gradient.addColorStop(0, 'rgba(68, 214, 44, 0.9)'); 
                    gradient.addColorStop(0.7, 'rgba(68, 214, 44, 0.2)'); 
                    gradient.addColorStop(1, 'rgba(68, 214, 44, 0.05)');
                    charts.geo.data.labels = labels;
                    charts.geo.data.datasets[0].data = geoData.map(g => Number(g.value) || 0);
                    charts.geo.data.datasets[0].backgroundColor = gradient;
                    charts.geo.update('active');
                }
            } else if (charts.geo) {
                charts.geo.data.labels = ['No data available'];
                charts.geo.data.datasets[0].data = [0];
                charts.geo.update('active');
            }
        } else if (charts.geo) {
            const errorText = await resGeo.text();
            console.error('Geo chart HTTP error:', resGeo.status, errorText);
            charts.geo.data.labels = ['Error loading'];
            charts.geo.data.datasets[0].data = [0];
            charts.geo.update('active');
        }
    } catch (e) { 
        console.error('Geo chart error:', e);
        if (charts.geo) {
            charts.geo.data.labels = ['Error loading data'];
            charts.geo.data.datasets[0].data = [0];
            charts.geo.update('active');
        }
    }

    // 5. USER GROWTH CHART
    try {
        const resUsers = await fetchWithTimeout('admin_dashboard.php?ajax_action=user_growth');
        if (resUsers.ok && charts.users) {
            const userData = await resUsers.json();
            if (userData.error) {
                console.error('User growth chart error:', userData.error);
                throw new Error(userData.error);
            }
            if (Array.isArray(userData) && userData.length > 0) {
                const labels = userData.map(u => u.date || 'Unknown');
                const totalPoints = userData.map(u => Number(u.total) || 0);
                const activePoints = userData.map(u => Number(u.active) || 0);
                const suspendedPoints = userData.map(u => Number(u.suspended) || 0);
                const deletedPoints = userData.map(u => Number(u.deleted) || 0);
                charts.users.data.labels = labels;
                charts.users.data.datasets = [
                    { label: 'Total', data: totalPoints, borderColor: '#94a3b8', borderWidth: 2, borderDash: [3, 3], fill: false, pointRadius: 0, pointHoverRadius: 4, order: 0 },
                    { label: 'Active', data: activePoints, borderColor: '#8b5cf6', backgroundColor: (ctx) => { const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150); gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)'); gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); return gradient; }, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 4, order: 1 },
                    { label: 'Suspended', data: suspendedPoints, borderColor: '#f97316', backgroundColor: (ctx) => { const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150); gradient.addColorStop(0, 'rgba(249, 115, 22, 0.5)'); gradient.addColorStop(1, 'rgba(249, 115, 22, 0.0)'); return gradient; }, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 4, order: 2 },
                    { label: 'Deleted', data: deletedPoints, borderColor: '#ef4444', backgroundColor: (ctx) => { const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150); gradient.addColorStop(0, 'rgba(239, 68, 68, 0.5)'); gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)'); return gradient; }, borderWidth: 2, fill: true, pointRadius: 0, pointHoverRadius: 4, order: 3 }
                ];
                charts.users.update('active');
            } else if (charts.users) {
                charts.users.data.labels = ['No data available'];
                charts.users.data.datasets[0].data = [0];
                charts.users.update('active');
            }
        } else if (charts.users) {
            const errorText = await resUsers.text();
            console.error('User growth chart HTTP error:', resUsers.status, errorText);
            charts.users.data.labels = ['Error loading'];
            charts.users.data.datasets[0].data = [0];
            charts.users.update('active');
        }
    } catch (e) { 
        console.error('User growth chart error:', e);
        if (charts.users) {
            charts.users.data.labels = ['Error loading data'];
            charts.users.data.datasets[0].data = [0];
            charts.users.update('active');
        }
    }
}

document.getElementById('revenueToggle').addEventListener('change', async (e) => {
    if (!charts.revenue) return;
    try {
        const action = e.target.value === 'daily' ? 'daily_revenue' : 'monthly_revenue';
        const res = await fetchWithTimeout(`includes/fetch_data.php?action=${action}`);
        if (!res.ok) throw new Error('Failed');
        const data = await res.json();
        if (Array.isArray(data) && data.length > 0) {
            charts.revenue.data.labels = data.map(r => r.ym || r.date);
            charts.revenue.data.datasets[0].data = data.map(r => r.amt);
            charts.revenue.update();
        }
    } catch (e) {
        // Silent fail - user can try again
    }
});

// Listen for theme changes and update charts
window.addEventListener('themeChanged', (e) => {
    if (e.detail && e.detail.theme) {
        // Update Chart.js defaults for new theme
        const isDark = e.detail.theme === 'dark';
        Chart.defaults.color = isDark ? '#94a3b8' : '#6b7280';
        Chart.defaults.borderColor = isDark ? '#242c38' : 'rgba(107, 114, 128, 0.1)';
        
        // Update all existing charts
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.update === 'function') {
                chart.update('active');
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') {
        alert('Chart library failed to load. Please refresh the page.');
        return;
    }
    // Initialize empty charts immediately so they show something
    initEmptyCharts();
    // Then load data - run in parallel
    Promise.all([
        loadDashboard().catch(e => console.error('Dashboard load failed:', e)),
        loadTables().catch(e => console.error('Tables load failed:', e)),
        loadCharts().catch(e => console.error('Charts load failed:', e))
    ]).then(() => {
        console.log('All dashboard data loaded');
    });
});
</script>
</body>
</html>