<?php
// 1. ENABLE ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';

// Generate Initial for Profile Avatar
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// INCLUDE EMAIL SCRIPT
$email_file = __DIR__ . '/includes/email.php';
if (!file_exists($email_file)) {
    $email_file = __DIR__ . '/email.php';
    if (!file_exists($email_file)) {
        function send_email($a,$b,$c){ return true; } 
    } else { require_once $email_file; }
} else { require_once $email_file; }

// --- Email Templates & Triggers ---
if (!function_exists('getEmailTemplate')) {
    function getEmailTemplate($fullname, $title, $message, $color_code) {
        $logo_url = "https://placehold.co/150x50/161b22/ffffff/png?text=MetaShark";
        $current_date = date("F j, Y"); 
        $current_time = date("g:i A");

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { margin: 0; padding: 0; background-color: #f4f6f8; font-family: sans-serif; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <img src='$logo_url' style='display:block; margin:0 auto; height: 50px;'>
                <h2 style='color:$color_code; text-align:center;'>$title</h2>
                <p>Hello $fullname,</p>
                $message
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <small style='color: #888;'>$current_date - $current_time</small>
            </div>
        </body>
        </html>";
    }
}

if (!function_exists('triggerSuspensionEmail')) {
    function triggerSuspensionEmail($email, $fullname) {
        $msg = "<p>Your account has been suspended.</p>";
        $body = getEmailTemplate($fullname, "Account Suspended", $msg, "#d9534f");
        return send_email($email, "Account Suspended", $body);
    }
}

if (!function_exists('triggerUnsuspensionEmail')) {
    function triggerUnsuspensionEmail($email, $fullname) {
        $msg = "<p>Your account has been reactivated.</p>";
        $body = getEmailTemplate($fullname, "Account Active", $msg, "#44D62C");
        return send_email($email, "Account Reactivated", $body);
    }
}

// --- Utility: fetch user details ---
function getUserDetails($conn, $id) {
    $stmt = $conn->prepare("SELECT email, fullname FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// --- Handle User Update (Email/Password) ---
if (isset($_POST['update_user_details'])) {
    $id = (int)$_POST['user_id'];
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: admin_users.php?error=invalid_email");
        exit;
    }

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $hashed_password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $email, $id);
    }

    if ($stmt->execute()) {
        header("Location: admin_users.php?updated=1");
    } else {
        header("Location: admin_users.php?error=update_failed");
    }
    exit;
}

// --- Handle Role Change ---
if (isset($_POST['change_role'], $_POST['user_id'], $_POST['new_role'])) {
    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param("si", $_POST['new_role'], $_POST['user_id']);
    $stmt->execute();
    header("Location: admin_users.php?updated=1");
    exit;
}

// --- Handle Delete ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_GET['delete'] != $_SESSION['admin_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $_GET['delete']);
        $stmt->execute();
    }
    header("Location: admin_users.php?deleted=1");
    exit;
}

// --- Handle Suspend ---
if (isset($_GET['toggle_suspend'], $_GET['status']) && is_numeric($_GET['toggle_suspend'])) {
    $id = (int)$_GET['toggle_suspend'];
    $new_status = ((int)$_GET['status'] === 0) ? 1 : 0;
    
    if ($id != $_SESSION['admin_id']) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'");
        if($check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_suspended=? WHERE id=?");
            $stmt->bind_param("ii", $new_status, $id);
            if($stmt->execute()){
                $u = getUserDetails($conn, $id);
                if($u) {
                    if($new_status === 1) triggerSuspensionEmail($u['email'], $u['fullname']);
                    else triggerUnsuspensionEmail($u['email'], $u['fullname']);
                }
                header("Location: admin_users.php?suspended=1");
                exit;
            }
        }
    }
}

// --- Search / Filter ---
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$where = "1=1";
$params = []; $types="";

if($search){
    $where .= " AND (fullname LIKE ? OR email LIKE ?)";
    $p = "%$search%"; $params[]=$p; $params[]=$p; $types.="ss";
}
if($role){
    $where .= " AND role=?";
    $params[]=$role; $types.="s";
}

// 2. SAFETY CHECK
$columns = [];
$res = $conn->query("SHOW COLUMNS FROM users");
while($row = $res->fetch_assoc()) { $columns[] = $row['Field']; }

$select_fields = "id, fullname, email, role, created_at";
if(in_array('phone', $columns)) $select_fields .= ", phone";
if(in_array('is_verified', $columns)) $select_fields .= ", is_verified";
if(in_array('is_suspended', $columns)) $select_fields .= ", is_suspended";

$sql = "SELECT $select_fields FROM users WHERE $where ORDER BY id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<title>Admin Users — Meta Shark</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* --- MASTER CSS (Matches Dashboard) --- */
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
    --danger: #f44336; 
    --info: #00d4ff;
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
a { text-decoration: none; color: inherit; transition: 0.2s; }

/* --- Animations --- */
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation: fadeIn 0.5s ease forwards; }

/* --- Navbar --- */
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

/* --- Sidebar --- */
.admin-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; width: var(--sidebar-width); background: var(--panel); border-right: 1px solid var(--panel-border); padding: 24px 16px; overflow-y: auto; transition: var(--transition); z-index: 40; }
.sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }
.sidebar-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; color: var(--text-muted); font-weight: 500; font-size: 14px; transition: var(--transition); margin-bottom: 4px; }
.sidebar-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
[data-theme="light"] .sidebar-item:hover { background: #f3f4f6; }
.sidebar-item.active { background: linear-gradient(90deg, rgba(68,214,44,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
.sidebar-item i { font-size: 18px; }

/* --- Main Content --- */
.admin-main { margin-left: var(--sidebar-width); margin-top: 70px; padding: 32px; min-height: calc(100vh - 70px); transition: var(--transition); }

/* --- Table Styles --- */
.table-card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); overflow: hidden; display: flex; flex-direction: column; }
.table-header { padding: 20px 24px; border-bottom: 1px solid var(--panel-border); display: flex; justify-content: space-between; align-items: center; }
.table-header h3 { font-size: 16px; font-weight: 600; margin: 0; background: rgba(68,214,44,0.02); color: var(--text); }

.table-responsive { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
th { text-align: left; padding: 12px 24px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 600; border-bottom: 1px solid var(--panel-border); white-space: nowrap; }
td { padding: 16px 24px; border-bottom: 1px solid var(--panel-border); font-size: 14px; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(255,255,255,0.02); }
[data-theme="light"] tr:hover td { background: #f9fafb; }

/* --- Filters --- */
.filters{background:var(--panel);padding:20px;border-radius:var(--radius);border:1px solid var(--panel-border);box-shadow:var(--shadow);margin-bottom:25px;display:flex;gap:12px;flex-wrap:wrap;}
.filters input,.filters select{padding:10px 15px;border:1px solid var(--panel-border);border-radius:8px;background:var(--bg);color:var(--text); outline:none;}

/* --- UNIFIED BUTTON STYLES --- */
.btn { padding: 8px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: 0.2s; }
.btn-primary { background: var(--primary); color: #000; border-color: var(--primary); }
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary { background: var(--bg); color: var(--text); border-color: var(--panel-border); }
.btn-secondary:hover { background: var(--panel-border); }

/* Edit (Blue Outline) */
.btn-edit { background: rgba(0,212,255,0.1); color: var(--info); border: 1px solid var(--info); }
.btn-edit:hover { background: var(--info); color: #000; }

/* Suspend (Orange Outline) */
.btn-suspend { background: rgba(255,152,0,0.1); color: #ff9800; border: 1px solid #ff9800; }
.btn-suspend:hover { background: #ff9800; color: #fff; }

/* Activate (Green Outline) */
.btn-activate { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
.btn-activate:hover { background: var(--primary); color: #000; }

/* Delete (Red Outline) */
.btn-delete { background: rgba(244,67,54,0.1); color: var(--danger); border: 1px solid var(--danger); }
.btn-delete:hover { background: var(--danger); color: #fff; }

.btn-xs { padding: 6px 12px; font-size: 12px; }
.btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }

/* Badges */
.badge{padding:6px 10px;border-radius:6px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-verified{background:rgba(68,214,44,0.15);color:var(--primary);}
.badge-unverified{background:rgba(244,67,54,0.15);color:var(--danger);}
.badge-suspended{background:rgba(255,152,0,0.15);color:#ff9800;}

.alert{padding:15px 25px;border-radius:8px;margin-bottom:25px;font-size:15px; font-weight: 500;}
.alert-success{background:rgba(68,214,44,0.1);color:var(--primary);border:1px solid var(--primary);}
.role-select{padding:6px 10px;border:1px solid var(--panel-border);border-radius:6px;background:var(--bg);color:var(--text);font-size:13px; cursor: pointer;}

/* --- MODAL STYLES --- */
.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
.modal.show { display: flex; }
.modal-content { background-color: var(--panel); border: 1px solid var(--panel-border); width: 90%; max-width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); position: relative; }
.close-modal { position: absolute; top: 20px; right: 25px; color: var(--text-muted); font-size: 28px; font-weight: bold; cursor: pointer; transition: 0.2s; }
.close-modal:hover { color: var(--danger); }
.modal h2 { margin-top: 0; color: var(--text); font-size: 22px; border-bottom: 1px solid var(--panel-border); padding-bottom: 15px; margin-bottom: 20px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 8px; color: var(--text); font-weight: 600; font-size: 14px; }
.form-group input { width: 100%; padding: 12px; border: 1px solid var(--panel-border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 14px; }
.form-group input:focus { border-color: var(--primary); outline: none; }
.form-note { font-size: 12px; color: var(--text-muted); margin-top: 5px; }

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

<?php include 'admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="fade-in" style="margin-bottom:25px;">
        <h2 style="font-size: 24px; font-weight: 700;">User Management</h2>
    </div>

    <?php if(isset($_GET['updated'])): ?><div class="alert alert-success fade-in">User details updated successfully.</div><?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?><div class="alert alert-success fade-in">User deleted successfully.</div><?php endif; ?>
    <?php if(isset($_GET['suspended'])): ?><div class="alert alert-success fade-in">User suspended. Notification sent.</div><?php endif; ?>
    <?php if(isset($_GET['error'])): ?><div class="alert fade-in" style="background:rgba(244,67,54,0.1);color:#f44336;border:1px solid #f44336;">Action failed. Invalid email or error.</div><?php endif; ?>

    <div class="filters fade-in">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1">
            <input type="text" name="search" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;min-width:250px">
            <select name="role">
                <option value="">All Roles</option>
                <option value="buyer" <?php echo $role==='buyer'?'selected':'';?>>Buyer</option>
                <option value="seller" <?php echo $role==='seller'?'selected':'';?>>Seller</option>
                <option value="admin" <?php echo $role==='admin'?'selected':'';?>>Admin</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
            <a href="admin_users.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Clear</a>
        </form>
    </div>

    <div class="table-card fade-in" style="animation-delay: 0.1s;">
        <div class="table-header">
            <h3>Registered Users (<?php echo count($users); ?>)</h3>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>User Details</th><th>Contact</th><th>Role</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                  <tbody>
                    <?php if(empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:50px;color:var(--text-muted)">No users found.</td></tr>
                    <?php else: foreach($users as $u): ?>
                        <tr>
                            <td><span style="font-family:monospace; font-weight:700">#<?php echo $u['id']; ?></span></td>
                            <td>
                                <div style="font-weight:600"><?php echo htmlspecialchars($u['fullname']); ?></div>
                                <div style="font-size:12px; color:var(--text-muted)"><?php echo isset($u['created_at']) ? date('M d, Y', strtotime($u['created_at'])) : ''; ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <form method="POST" style="display:inline-block">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <select name="new_role" onchange="this.form.submit()" class="role-select">
                                        <option value="buyer" <?php echo $u['role']==='buyer'?'selected':'';?>>Buyer</option>
                                        <option value="seller" <?php echo $u['role']==='seller'?'selected':'';?>>Seller</option>
                                        <option value="admin" <?php echo $u['role']==='admin'?'selected':'';?>>Admin</option>
                                    </select>
                                    <input type="hidden" name="change_role" value="1">
                                </form>
                            </td>
                            <td>
                                <?php if(isset($u['is_verified'])): ?>
                                    <?php if($u['is_verified']): ?><span class="badge badge-verified"><i class="bi bi-check2"></i> Verified</span>
                                    <?php else: ?><span class="badge badge-unverified">Unverified</span><?php endif; ?>
                                <?php endif; ?>

                                <?php if(isset($u['is_suspended']) && $u['is_suspended']): ?>
                                    <span class="badge badge-suspended">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <?php if($u['id'] != $_SESSION['admin_id']): ?>
                                        
                                        <button onclick="openEditModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['fullname'])); ?>', '<?php echo htmlspecialchars($u['email']); ?>')" class="btn btn-edit btn-xs" title="Edit Email/Pass">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>

                                        <?php if(isset($u['is_suspended'])): ?>
                                            <?php if(!$u['is_suspended']): ?>
                                                <a href="admin_users.php?toggle_suspend=<?php echo $u['id']; ?>&status=0" class="btn btn-suspend btn-xs" title="Suspend User">
                                                    <i class="bi bi-pause-circle"></i> Suspend
                                                </a>
                                            <?php else: ?>
                                                <a href="admin_users.php?toggle_suspend=<?php echo $u['id']; ?>&status=1" class="btn btn-activate btn-xs" title="Activate User">
                                                    <i class="bi bi-play-circle"></i> Activate
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="admin_users.php?delete=<?php echo $u['id']; ?>" class="btn btn-delete btn-xs" onclick="return confirm('Delete this user?')" title="Delete User">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>

                                    <?php else: echo '<span style="color:var(--text-muted); font-size:12px;">(You)</span>'; endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeEditModal()">&times;</span>
        <h2>Edit User Details</h2>
        <form method="POST">
            <input type="hidden" name="update_user_details" value="1">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="form-group">
                <label>User Name (Read Only)</label>
                <input type="text" id="edit_fullname" readonly style="opacity:0.7; cursor:not-allowed;">
            </div>

            <div class="form-group">
                <label for="edit_email">Email Address</label>
                <input type="email" id="edit_email" name="email" required>
            </div>

            <div class="form-group">
                <label for="edit_password">New Password</label>
                <input type="password" id="edit_password" name="password" placeholder="Enter new password">
                <div class="form-note">Leave blank to keep the current password.</div>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="margin-right:10px;"> Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

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
    // Optional: fetch('theme_toggle.php?theme=' + newTheme);
});

// Modal Logic
const modal = document.getElementById('editUserModal');

function openEditModal(id, fullname, email) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_fullname').value = fullname;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_password').value = ''; 
    modal.classList.add('show');
}

function closeEditModal() {
    modal.classList.remove('show');
}

window.onclick = function(event) {
    if (event.target == modal) {
        closeEditModal();
    }
}
</script>

</body>
</html>