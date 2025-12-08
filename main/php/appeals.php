<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';

// --- 1. SECURITY & SETUP ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$theme = $_SESSION['theme'] ?? 'dark';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 1));
$current_page = 'appeals.php'; 

// --- 2. DATABASE MIGRATION (Auto-Update & Fix) ---
// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS user_appeals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_email VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    appeal_type ENUM('account','shop') NOT NULL DEFAULT 'account',
    status ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add missing columns if any
$columns = [
    'user_id' => "INT NULL AFTER id",
    'appeal_type' => "ENUM('account','shop') NOT NULL DEFAULT 'account' AFTER reason",
    'status' => "ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending' AFTER appeal_type"
];

foreach ($columns as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM user_appeals LIKE '$col'");
    if ($check->num_rows === 0) {
        $conn->query("ALTER TABLE user_appeals ADD COLUMN $col $def");
    }
}

// CRITICAL FIX: Ensure 'status' column supports 'rejected'
// This fixes the issue where the tag wouldn't update because the DB rejected the new value
$checkEnum = $conn->query("SHOW COLUMNS FROM user_appeals LIKE 'status'");
$enumRow = $checkEnum->fetch_assoc();
if (strpos($enumRow['Type'], "'rejected'") === false) {
    $conn->query("ALTER TABLE user_appeals MODIFY COLUMN status ENUM('pending','resolved','rejected') NOT NULL DEFAULT 'pending'");
}

// --- 3. HANDLE ACTIONS (BUSINESS LOGIC) ---
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $msg = "";

    if ($action === 'resolve') {
        // A. Mark appeal as resolved
        $stmt = $conn->prepare("UPDATE user_appeals SET status = 'resolved' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        // B. Unspsend User
        $findUser = $conn->prepare("SELECT user_id FROM user_appeals WHERE id = ?");
        $findUser->bind_param('i', $id);
        $findUser->execute();
        $resUser = $findUser->get_result();
        
        if ($resUser->num_rows > 0) {
            $uData = $resUser->fetch_assoc();
            $userId = $uData['user_id'];
            if ($userId) {
                // Assuming 'is_suspended' is the column in 'users' table
                $unsuspend = $conn->prepare("UPDATE users SET is_suspended = 0 WHERE id = ?");
                $unsuspend->bind_param('i', $userId);
                $unsuspend->execute();
                $unsuspend->close();
            }
        }
        $findUser->close();
        $msg = "resolved";

    } elseif ($action === 'reject') {
        // Mark as rejected
        $stmt = $conn->prepare("UPDATE user_appeals SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $msg = "rejected";

    } elseif ($action === 'delete') {
        // Delete record
        $stmt = $conn->prepare("DELETE FROM user_appeals WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $msg = "deleted";
    }
    
    // Redirect with message
    header("Location: $current_page?type=" . ($_GET['type'] ?? 'account') . "&msg=" . $msg);
    exit;
}

// --- 4. FETCH DATA ---
$type = $_GET['type'] ?? 'account';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM user_appeals WHERE appeal_type = ?";
$params = [$type];
$types = "s";

if ($search) {
    $sql .= " AND (user_email LIKE ? OR user_id = ?)";
    $params[] = "%$search%";
    $params[] = (int)$search;
    $types .= "si";
}

$sql .= " ORDER BY status ASC, created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$appeals = $result->fetch_all(MYSQLI_ASSOC);

// Get Counts
$countSql = "SELECT appeal_type, COUNT(*) as cnt FROM user_appeals WHERE status = 'pending' GROUP BY appeal_type";
$countRes = $conn->query($countSql);
$counts = ['account' => 0, 'shop' => 0];
while($row = $countRes->fetch_assoc()) {
    $counts[$row['appeal_type']] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appeals Management — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* --- CORE THEME --- */
        :root {
            --primary: #44D62C;
            --primary-glow: rgba(68, 214, 44, 0.3);
            --bg: #f3f4f6;
            --panel: #ffffff;
            --panel-border: #e5e7eb;
            --text: #1f2937;
            --text-muted: #6b7280;
            --radius: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; transition: 0.2s; }

        /* --- LAYOUT --- */
        .admin-navbar { position: fixed; top: 0; left: 0; right: 0; height: 70px; background: var(--panel); border-bottom: 1px solid var(--panel-border); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; z-index: 50; backdrop-filter: blur(10px); box-shadow: var(--shadow); }
        .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: 0.3s; z-index: 40; }
        .admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); }
        
        .sidebar-toggle { display: none; background: none; border: none; color: var(--text); font-size: 24px; cursor: pointer; }
        @media (max-width: 992px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.show { transform: translateX(0); }
            .admin-main { margin-left: 0; }
            .sidebar-toggle { display: block; }
        }

        /* --- UI ELEMENTS --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title h1 { font-size: 24px; font-weight: 700; }
        .page-title p { color: var(--text-muted); font-size: 14px; margin-top: 4px; }

        .tabs { display: flex; gap: 10px; border-bottom: 1px solid var(--panel-border); margin-bottom: 24px; }
        .tab { padding: 12px 20px; font-weight: 600; font-size: 14px; color: var(--text-muted); border-bottom: 2px solid transparent; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .tab:hover { color: var(--text); }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .badge-count { background: var(--panel-border); color: var(--text); padding: 2px 8px; border-radius: 10px; font-size: 11px; }
        .tab.active .badge-count { background: rgba(68,214,44,0.2); color: var(--primary); }

        .search-box { position: relative; max-width: 300px; width: 100%; }
        .search-box input { width: 100%; background: var(--panel); border: 1px solid var(--panel-border); padding: 10px 10px 10px 36px; border-radius: 8px; color: var(--text); font-size: 14px; }
        .search-box input:focus { border-color: var(--primary); outline: none; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

        /* --- CLICKABLE TABLE STYLES --- */
        .card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        
        th { text-align: left; padding: 14px 20px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); background: rgba(0,0,0,0.02); }
        td { padding: 16px 20px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        tbody tr { cursor: pointer; transition: background 0.15s; }
        tbody tr:hover { background: rgba(68, 214, 44, 0.05); }
        [data-theme="light"] tbody tr:hover { background: #f0fdf4; }

        /* Status & Buttons */
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display:inline-flex; align-items:center; gap:4px; }
        .status-pending { background: rgba(255, 152, 0, 0.15); color: #ff9800; }
        .status-resolved { background: rgba(68, 214, 44, 0.15); color: var(--primary); }
        .status-rejected { background: rgba(244, 67, 54, 0.15); color: #f44336; }

        .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--panel-border); color: var(--text-muted); transition: 0.2s; margin-right: 4px; z-index: 2; position: relative; }
        .btn-action:hover { border-color: var(--primary); color: var(--primary); background: rgba(68,214,44,0.05); }
        .btn-action.reject:hover { border-color: #f44336; color: #f44336; background: rgba(244,67,54,0.05); }
        .btn-action.delete:hover { border-color: #f44336; color: #f44336; background: rgba(244,67,54,0.05); }

        /* Notification */
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 24px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease; }
        .alert-success { background: rgba(68,214,44,0.15); color: var(--primary); border: 1px solid rgba(68,214,44,0.2); }
        .alert-danger { background: rgba(244,67,54,0.15); color: #f44336; border: 1px solid rgba(244,67,54,0.2); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Sidebar & Nav */
         .admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: var(--transition); z-index: 40; }
        .sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
        .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: var(--transition); margin-bottom: 4px; }
        .sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
        [data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
        .sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
        .sidebar-item i { font-size: 18px; }
        .navbar-profile-link { display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 14px; }
        .profile-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: #000; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .logo-area { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; }
        .logo-area img { height: 32px; }
       
    </style>
</head>
<body>

<nav class="admin-navbar">
    <div style="display:flex; align-items:center; gap:16px;">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="logo-area">
            <img src="uploads/logo1.png" alt="Meta Shark">
            <span>META SHARK</span>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:16px;">
        <button id="themeBtn" class="btn-xs btn-outline" style="font-size:16px; border:none; background:transparent; color:var(--text); cursor:pointer;">
            <i class="bi bi-moon-stars"></i>
        </button>
        <a href="admin_profile.php" class="navbar-profile-link">
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <div class="profile-avatar"><?php echo $admin_initial; ?></div>
        </a>
        <a href="admin_logout.php" class="sidebar-item" style="padding:8px 10px; border:1px solid var(--panel-border); border-radius:8px;"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<?php include 'admin_sidebar.php'; ?>

<main class="admin-main">
    
    <div class="page-header">
        <div class="page-title">
            <h1>User Appeals</h1>
            <p>Review and resolve account suspension or shop restriction appeals.</p>
        </div>
        <form class="search-box" method="GET">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            <i class="bi bi-search"></i>
            <input type="text" name="search" placeholder="Search email or ID..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] === 'resolved'): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> Appeal resolved! User account has been successfully unsuspend.
            </div>
        <?php elseif($_GET['msg'] === 'rejected'): ?>
            <div class="alert alert-danger">
                <i class="bi bi-x-circle-fill"></i> Appeal rejected. User remains suspended.
            </div>
        <?php elseif($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-danger">
                <i class="bi bi-trash-fill"></i> Record deleted permanently.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="tabs">
        <a href="?type=account" class="tab <?php echo $type === 'account' ? 'active' : ''; ?>">
            <i class="bi bi-person-badge"></i> Account Appeals
            <?php if($counts['account'] > 0): ?>
                <span class="badge-count"><?php echo $counts['account']; ?></span>
            <?php endif; ?>
        </a>
        <a href="?type=shop" class="tab <?php echo $type === 'shop' ? 'active' : ''; ?>">
            <i class="bi bi-shop-window"></i> Shop Appeals
            <?php if($counts['shop'] > 0): ?>
                <span class="badge-count"><?php echo $counts['shop']; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Details</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appeals)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">
                                <i class="bi bi-inbox" style="font-size: 32px; display:block; margin-bottom:10px;"></i>
                                No appeals found in this category.
                            </td>
                        </tr>
                    <?php else: foreach ($appeals as $row): 
                        // Determine Target Page based on Type
                        $targetPage = ($row['appeal_type'] === 'shop') ? 'appeal_shop.php' : 'appeal_account.php';
                        $targetUrl = "{$targetPage}?id=" . $row['id'];
                    ?>
                        <tr onclick="window.location.href='<?php echo $targetUrl; ?>'">
                            
                            <td><span style="font-family:monospace; color:var(--primary);">#<?php echo $row['id']; ?></span></td>
                            
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($row['user_email']); ?></div>
                                <?php if($row['user_id']): ?>
                                    <div style="font-size:12px; color:var(--text-muted);">ID: <?php echo $row['user_id']; ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php 
                                    $statusClass = 'status-pending';
                                    $icon = '<i class="bi bi-hourglass-split"></i>';
                                    
                                    if($row['status'] === 'resolved') {
                                        $statusClass = 'status-resolved';
                                        $icon = '<i class="bi bi-check-circle-fill"></i>';
                                    }
                                    if($row['status'] === 'rejected') {
                                        $statusClass = 'status-rejected';
                                        $icon = '<i class="bi bi-x-circle-fill"></i>';
                                    }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo $icon . ' ' . ucfirst($row['status']); ?>
                                </span>
                            </td>
                            
                            <td style="color:var(--text-muted); white-space:nowrap;">
                                <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                <div style="font-size:11px;"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                            </td>
                            
                            <td style="text-align:right; white-space:nowrap;">
                                
                                <?php if($row['status'] === 'pending'): ?>
                                    <a href="<?php echo $current_page; ?>?type=<?php echo $type; ?>&action=reject&id=<?php echo $row['id']; ?>" 
                                       class="btn-action reject" title="Reject Appeal" 
                                       onclick="event.stopPropagation(); return confirm('Mark this appeal as REJECTED?');">
                                        <i class="bi bi-x-lg"></i>
                                    </a>

                                    <a href="<?php echo $current_page; ?>?type=<?php echo $type; ?>&action=resolve&id=<?php echo $row['id']; ?>" 
                                       class="btn-action" title="Mark as Resolved" 
                                       onclick="event.stopPropagation(); return confirm('Mark this appeal as RESOLVED? This will unsuspend the user.');">
                                        <i class="bi bi-check-lg"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="mailto:<?php echo htmlspecialchars($row['user_email']); ?>" 
                                   class="btn-action" title="Reply via Email" 
                                   onclick="event.stopPropagation();">
                                    <i class="bi bi-envelope"></i>
                                </a>
                                
                                <a href="<?php echo $current_page; ?>?type=<?php echo $type; ?>&action=delete&id=<?php echo $row['id']; ?>" 
                                   class="btn-action delete" title="Delete Appeal" 
                                   onclick="event.stopPropagation(); return confirm('Permanently delete this appeal? This cannot be undone.');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); });
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });

    // Theme persistence
    const themeBtn = document.getElementById('themeBtn');
    if (themeBtn) {
        let currentTheme = '<?php echo $theme; ?>';
        function applyTheme(theme){ document.documentElement.setAttribute('data-theme', theme); localStorage.setItem('theme', theme); }
        function updateThemeIcon(theme){ const icon = themeBtn.querySelector('i'); icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'; }
        applyTheme(currentTheme); updateThemeIcon(currentTheme);
        themeBtn.addEventListener('click', () => {
            const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme); updateThemeIcon(newTheme); fetch('theme_toggle.php?theme=' + newTheme).catch(console.error);
        });
    }
</script>

</body>
</html>