<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Guard: ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// --- INTERNAL AJAX HANDLER FOR USER GROWTH (Updated for Active, Suspended, Deleted) ---
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
    <title>Dashboard — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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

    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; border: 1px solid transparent; display: inline-block; }
    .btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-primary:hover { filter: brightness(1.1); }

    .skeleton { background: var(--panel-border); border-radius: 4px; animation: pulse 1.5s infinite; color: transparent !important; user-select: none; }
    @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 0.3; } 100% { opacity: 0.6; } }

    /* --- PRINT STYLES (FIXED OVERFLOW) --- */
    @media print {
        /* Hide navigation and controls */
        .admin-sidebar, .admin-navbar, .sidebar-toggle, #revenueToggle, #exportAction { 
            display: none !important; 
        }
        
        /* Reset Main Container */
        .admin-main { 
            margin: 0 !important; 
            padding: 10px !important; 
            width: 100% !important; 
            max-width: 100% !important;
            min-height: auto !important;
        }
        
        body { 
            background: white !important; 
            color: black !important; 
            -webkit-print-color-adjust: exact; 
            overflow: visible !important;
            width: 100% !important;
        }
        
        /* Force Stacked Layout (Fix Horizontal Overflow) */
        .stats-grid, .grid-stack, .charts-row { 
            display: block !important; 
            width: 100% !important;
            grid-template-columns: 1fr !important;
        }
        
        /* Fix Cards */
        .stat-card, .card { 
            box-shadow: none !important; 
            border: 1px solid #ccc !important; 
            break-inside: avoid; 
            page-break-inside: avoid;
            background: #fff !important; 
            margin-bottom: 15px !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* Fix Charts */
        .card-body {
            padding: 10px !important;
            height: auto !important;
            max-height: 300px !important;
        }
        
        canvas { 
            max-height: 250px !important; 
            width: 100% !important; 
            height: auto !important;
        }
        
        /* Fix Typography */
        h2, h3, .stat-value, .stat-label { 
            color: #000 !important; 
        }
        
        /* Fix Table Overflow */
        .table-responsive {
            overflow: visible !important;
            width: 100% !important;
        }
        
        /* Ensure Backgrounds Print */
        * {
            print-color-adjust: exact !important;
            -webkit-print-color-adjust: exact !important;
        }
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

<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-item active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</div>
    
    <div class="sidebar-group-label">Analytics</div>
    <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-activity"></i> Overview</a>
    <a href="charts_line.php" class="sidebar-item"><i class="bi bi-graph-up-arrow"></i> Revenue</a>
    <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart-fill"></i> Categories</a>
    <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart-fill"></i> Orders</a>
    <a href="charts_geo.php" class="sidebar-item"><i class="bi bi-globe2"></i> Geography</a>

    <div class="sidebar-group-label">Management and Access</div>
    <a href="pending_requests.php" class="sidebar-item"><i class="bi bi-shield-lock"></i> Requests</a>
    <a href="admin_products.php" class="sidebar-item"><i class="bi bi-box-seam"></i> Products</a>
    <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people-fill"></i> Users</a>
    <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
    <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag-check-fill"></i> Orders</a>

    <div class="sidebar-group-label">Settings</div>
    <a href="admin_profile.php" class="sidebar-item"><i class="bi bi-person-gear"></i> My Profile</a>
</aside>

<main class="admin-main">
    <div class="dashboard-header fade-in">
        <div>
            <h2>Dashboard Overview</h2>
            <p>Welcome back, here's what's happening with your store today.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <!-- Added Export Dropdown -->
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
                    <thead><tr><th>Product Name</th><th>Category</th><th style="text-align:right">Units Sold</th></tr></thead>
                    <tbody><tr><td colspan="3"><span class="skeleton">Loading data...</span></td></tr></tbody>
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
            <!-- Updated Header to Reflect Multi-line Chart -->
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

function downloadDashboardCSV() {
    const today = new Date().toISOString().split('T')[0];
    
    // Gather data
    const prod = document.getElementById('totalProducts').innerText;
    const rev = document.getElementById('totalRevenue').innerText;
    const ord = document.getElementById('totalOrders').innerText;
    const sell = document.getElementById('totalSellers').innerText;

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Meta Shark Dashboard Report\n";
    csvContent += "Generated Date," + today + "\n\n";
    
    csvContent += "SUMMARY STATISTICS\n";
    csvContent += "Total Products," + prod + "\n";
    csvContent += "Total Revenue," + rev + "\n";
    csvContent += "Total Orders," + ord + "\n";
    csvContent += "Active Sellers," + sell + "\n\n";

    // Note: Detailed chart data exporting would ideally use data from the 'charts' object
    // For simplicity, we export what is readily available in text
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "meta_shark_report_" + today + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// --- HARDCODED DATA FOR CATEGORIES ---

// 1. Map ID to Name
const categoryMap = {
    1: 'Accessories',
    2: 'Phone',
    3: 'Tablet',
    4: 'Laptop',
    5: 'Gaming'
};

// 2. Map Product ID to Array of Category IDs
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

// --- Data Fetching & Charts ---

const chartDefaults = { color: '#6b7280', borderColor: '#374151' };
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = "<?php echo $theme === 'dark' ? '#94a3b8' : '#6b7280'; ?>";
Chart.defaults.borderColor = "<?php echo $theme === 'dark' ? '#242c38' : 'rgba(107, 114, 128, 0.1)'; ?>";

let charts = {}; 

async function loadDashboard() {
    try {
        const res = await fetch('includes/fetch_data.php?action=dashboard_stats');
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

// Populate Top Products Table with Decoded Categories
async function loadTables() {
    const resProd = await fetch('includes/fetch_data.php?action=top_products');
    const products = await resProd.json();
    let html = '';
    
    products.slice(0,5).forEach(p => {
        const prodId = p.id || p.product_id; 
        
        let catHtml = '<span class="text-muted" style="font-size:11px;">Uncategorized</span>';
        
        // Lookup categories for this product
        if (productCategoryRelations[prodId]) {
            const catIds = productCategoryRelations[prodId];
            const catNames = catIds.map(id => categoryMap[id]).filter(Boolean);
            
            if (catNames.length > 0) {
                catHtml = catNames.map(name => `<span class="cat-badge">${name}</span>`).join('');
            }
        }

        html += `<tr>
            <td style="font-weight:500">${p.name}</td>
            <td>${catHtml}</td>
            <td style="text-align:right"><span class="badge badge-success">${Number(p.total_qty).toLocaleString()}</span></td>
        </tr>`;
    });
    document.querySelector('#topProductsTable tbody').innerHTML = html;
}

let revenueChartCtx = document.getElementById('revenueChart').getContext('2d');

async function loadCharts() {
    
    // ---------------------------------------------------------
    // 1. CATEGORY CHART (Green Gradient Style - Local Data)
    // ---------------------------------------------------------
    try {
        let catCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        
        if (typeof productCategoryRelations !== 'undefined') {
            Object.values(productCategoryRelations).forEach(catIds => {
                catIds.forEach(id => {
                    if (catCounts[id] !== undefined) catCounts[id]++;
                });
            });
        }

        const catLabels = Object.keys(categoryMap).map(id => categoryMap[id]);
        const catValues = Object.keys(categoryMap).map(id => catCounts[id]);

        const catCtx = document.getElementById('categoryChart');
        if (catCtx) {
            charts.category = new Chart(catCtx, {
                type: 'bar',
                data: {
                    labels: catLabels,
                    datasets: [{ 
                        label: 'Products', 
                        data: catValues, 
                        backgroundColor: (context) => {
                            const ctx = context.chart.ctx;
                            const gradient = ctx.createLinearGradient(0, 0, 0, 200); 
                            gradient.addColorStop(0, '#44D62C');
                            gradient.addColorStop(1, 'rgba(68, 214, 44, 0.05)');
                            return gradient;
                        },
                        borderColor: '#44D62C',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.6
                    }]
                },
                options: { 
                    responsive: true,
                    maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { grid: { display: false } }, 
                        y: { grid: { borderDash: [5, 5] }, beginAtZero: true, ticks: { stepSize: 1 } } 
                    } 
                }
            });
        }
    } catch (e) {
        console.error("Error loading Category Chart:", e);
    }

    // ---------------------------------------------------------
    // 2. REVENUE CHART
    // ---------------------------------------------------------
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
                            label: 'Revenue',
                            data: revData.map(r => r.amt),
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

    // ---------------------------------------------------------
    // 3. ORDERS CHART
    // ---------------------------------------------------------
    try {
        const resOrd = await fetch('includes/fetch_data.php?action=orders_summary');
        if (resOrd.ok) {
            const ordData = await resOrd.json();
            const ordCtx = document.getElementById('ordersChart');
            if (ordCtx) {
                charts.orders = new Chart(ordCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ordData.map(o => o.status),
                        datasets: [{ data: ordData.map(o => o.cnt), backgroundColor: ['#44D62C', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'], borderWidth: 0 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 20 } } }, cutout: '70%' }
                });
            }
        }
    } catch (e) { console.error(e); }
    
    // ---------------------------------------------------------
    // 4. GEO CHART (Fixed API Call & Data Mapping)
    // ---------------------------------------------------------
    try {
        // Use 'sales_by_country' to match your fetch_data.php
        const resGeo = await fetch('includes/fetch_data.php?action=sales_by_country');
        if (resGeo.ok) {
            const geoData = await resGeo.json();
            const geoCtx = document.getElementById('geoChart');
            if (geoCtx) {
                charts.geo = new Chart(geoCtx, {
                    type: 'bar',
                    data: {
                        labels: geoData.map(g => g.country),
                        // Use g.value as returned by sales_by_country logic
                        datasets: [{ label: 'Total Sales ($)', data: geoData.map(g => g.value), backgroundColor: '#3b82f6', borderColor: '#1e40af', borderWidth: 1, borderRadius: 4 }]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { borderDash: [5, 5] }, title: { display: true, text: 'Sales Amount' } }, y: { grid: { display: false } } } }
                });
            }
        }
    } catch (e) { console.error(e); }

    // ---------------------------------------------------------
    // 5. USER REGISTRATION TREND (Overlapping Filled Areas + Total)
    // ---------------------------------------------------------
    try {
        // Fetch data from internal AJAX handler
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
                    data: {
                        labels: labels,
                        datasets: [
                            // 0. Total Line (Dashed, On Top)
                            {
                                label: 'Total',
                                data: totalPoints,
                                borderColor: '#94a3b8', // Slate Gray
                                borderWidth: 2,
                                borderDash: [3, 3], // Dashed line
                                fill: false, // Do not fill area for total
                                tension: 0.4,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                order: 0 // Ensure it draws on top of filled areas
                            },
                            // 1. Active Users (Purple Filled Area)
                            {
                                label: 'Active',
                                data: activePoints,
                                borderColor: '#8b5cf6', // Purple
                                backgroundColor: (ctx) => {
                                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150);
                                    gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)');
                                    gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)');
                                    return gradient;
                                },
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                order: 1
                            },
                            // 2. Suspended Users (Orange Filled Area)
                            {
                                label: 'Suspended',
                                data: suspendedPoints,
                                borderColor: '#f97316', // Orange
                                backgroundColor: (ctx) => {
                                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150);
                                    gradient.addColorStop(0, 'rgba(249, 115, 22, 0.5)');
                                    gradient.addColorStop(1, 'rgba(249, 115, 22, 0.0)');
                                    return gradient;
                                },
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                order: 2
                            },
                            // 3. Deleted Users (Red Filled Area)
                            {
                                label: 'Deleted',
                                data: deletedPoints,
                                borderColor: '#ef4444', // Red
                                backgroundColor: (ctx) => {
                                    const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 150);
                                    gradient.addColorStop(0, 'rgba(239, 68, 68, 0.5)');
                                    gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
                                    return gradient;
                                },
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 0,
                                pointHoverRadius: 4,
                                order: 3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: { 
                                display: true, 
                                labels: { boxWidth: 10, usePointStyle: true, font: { size: 11 } } 
                            },
                            tooltip: {
                                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                titleColor: '#1f2937',
                                bodyColor: '#1f2937',
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                padding: 10
                            }
                        },
                        scales: {
                            x: { display: false },
                            y: { display: false, beginAtZero: true }
                        }
                    }
                });
            }
        }
    } catch (e) { 
        console.error("User Growth Error:", e); 
    }
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