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
    
    // Basic Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: admin_users.php?error=invalid_email");
        exit;
    }

    if (!empty($password)) {
        // Update Email AND Password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $email, $hashed_password, $id);
    } else {
        // Update Email ONLY
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
/* CSS VARIABLES */
:root{--primary:#44D62C;--bg:#f3f4f6;--panel:#fff;--panel-border:#e5e7eb;--text:#1f2937;--text-muted:#6b7280;--radius:12px;--shadow:0 4px 6px rgba(0,0,0,0.1);--danger:#f44336; --info: #00d4ff; --muted:#6b7280;}
[data-theme="dark"]{--bg:#0f1115;--panel:#161b22;--panel-border:#242c38;--text:#e6eef6;--text-muted:#94a3b8;--shadow:0 10px 15px rgba(0,0,0,0.5); --muted:#94a3b8;}
*{margin:0;padding:0;box-sizing:border-box;} 
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);}

/* LAYOUT STRUCTURE */
.admin-wrapper { display: flex; flex-direction: column; min-height: 100vh; }

/* Navbar */
.admin-navbar {
    position: fixed; top: 0; left: 0; right: 0;
    height: 80px;
    background: var(--panel);
    border-bottom: 1px solid var(--panel-border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 30px;
    z-index: 1000;
}
.navbar-left{display:flex;align-items:center;gap:20px;}
.navbar-left img{ height: 50px; width: auto; }
.navbar-left h1{ font-size: 24px; margin: 0; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; }
.nav-user-info{display:flex;align-items:center;gap:20px;font-size:15px;}
.nav-user-info a {color: var(--text); text-decoration: none; font-weight: 500;}

/* Layout Container */
.layout-container {
    display: flex;
    margin-top: 80px; 
    min-height: calc(100vh - 80px);
}

/* Sidebar */
.admin-sidebar {
    width: 250px;
    background: var(--panel);
    border-right: 1px solid var(--panel-border);
    flex-shrink: 0;
    position: fixed;
    height: calc(100vh - 80px);
    overflow-y: auto;
}
.sidebar-item{display:flex;align-items:center;gap:12px;padding:15px 25px;color:var(--text-muted);text-decoration:none;font-weight:500;border-left:4px solid transparent; font-size: 15px;}
.sidebar-item:hover,.sidebar-item.active{background:var(--bg);color:var(--primary);border-left-color:var(--primary);}

/* Main Content */
.admin-main {
    flex-grow: 1;
    margin-left: 250px;
    padding: 30px;
    width: calc(100% - 250px);
}

/* Table Card */
.table-card {
    background: var(--panel);
    border-radius: var(--radius);
    border: 1px solid var(--panel-border);
    box-shadow: var(--shadow);
    width: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.table-card h3 {
    padding: 20px;
    border-bottom: 1px solid var(--panel-border);
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    background: rgba(68,214,44,0.02);
    color: var(--text);
}

.table-responsive { width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 900px; }
th,td { padding: 18px 25px; text-align: left; font-size: 14px; }
th { background: rgba(68,214,44,0.05); color: var(--primary); font-weight: 700; border-bottom: 1px solid var(--panel-border); white-space: nowrap; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
td { border-top: 1px solid var(--panel-border); vertical-align: middle; }

/* Components & BUTTONS */
.filters{background:var(--panel);padding:20px;border-radius:var(--radius);border:1px solid var(--panel-border);box-shadow:var(--shadow);margin-bottom:25px;display:flex;gap:12px;flex-wrap:wrap;}
.filters input,.filters select{padding:10px 15px;border:1px solid var(--panel-border);border-radius:8px;background:var(--bg);color:var(--text); outline:none;}

/* --- UNIFIED BUTTON STYLES --- */
.btn {
    padding: 8px 14px; 
    border-radius: 6px; 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    font-size: 13px; 
    font-weight: 600; 
    white-space: nowrap; 
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: 0.2s;
}

.sidebar-group-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 24px 12px 12px; font-weight: 700; opacity: 0.7; }


/* Primary (Filter) */
.btn-primary { background: var(--primary); color: #000; border-color: var(--primary); }
.btn-primary:hover { filter: brightness(1.1); }

/* Secondary (Clear) */
.btn-secondary { background: var(--bg); color: var(--text); border-color: var(--panel-border); }
.btn-secondary:hover { background: var(--panel-border); }

/* Edit (Blue Outline) */
.btn-edit { background: rgba(0,212,255,0.1); color: var(--info); border: 1px solid var(--info); }
.btn-edit:hover { background: var(--info); color: #000; }

/* Suspend (Outline/Glass) */
.btn-suspend { background: rgba(255,152,0,0.1); color: #ff9800; border: 1px solid #ff9800; }
.btn-suspend:hover { background: #ff9800; color: #fff; }

/* Activate (Outline/Glass) */
.btn-activate { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
.btn-activate:hover { background: var(--primary); color: #000; }

/* Delete (Outline/Glass) */
.btn-delete { background: rgba(244,67,54,0.1); color: var(--danger); border: 1px solid var(--danger); }
.btn-delete:hover { background: var(--danger); color: #fff; }

.btn-icon-only { padding: 0; width: 36px; height: 36px; border-radius: 6px; justify-content: center;}

/* Badges */
.badge{padding:6px 10px;border-radius:6px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
.badge-verified{background:rgba(68,214,44,0.15);color:var(--primary);}
.badge-unverified{background:rgba(244,67,54,0.15);color:var(--danger);}
.badge-suspended{background:rgba(255,152,0,0.15);color:#ff9800;}

.alert{padding:15px 25px;border-radius:8px;margin-bottom:25px;font-size:15px; font-weight: 500;}
.alert-success{background:rgba(68,214,44,0.1);color:var(--primary);border:1px solid var(--primary);}
.role-select{padding:6px 10px;border:1px solid var(--panel-border);border-radius:6px;background:var(--bg);color:var(--text);font-size:13px; cursor: pointer;}

/* --- MODAL STYLES --- */
.modal {
    display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%;
    overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
}
.modal.show { display: flex; }
.modal-content {
    background-color: var(--panel); border: 1px solid var(--panel-border);
    width: 90%; max-width: 500px; padding: 30px; border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    position: relative;
}
.close-modal {
    position: absolute; top: 20px; right: 25px; color: var(--text-muted); font-size: 28px;
    font-weight: bold; cursor: pointer; transition: 0.2s;
}
.close-modal:hover { color: var(--danger); }
.modal h2 { margin-top: 0; color: var(--text); font-size: 22px; border-bottom: 1px solid var(--panel-border); padding-bottom: 15px; margin-bottom: 20px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 8px; color: var(--text); font-weight: 600; font-size: 14px; }
.form-group input {
    width: 100%; padding: 12px; border: 1px solid var(--panel-border); border-radius: 8px;
    background: var(--bg); color: var(--text); font-size: 14px;
}
.form-group input:focus { border-color: var(--primary); outline: none; }
.form-note { font-size: 12px; color: var(--text-muted); margin-top: 5px; }
</style>
</head>
<body>

<div class="admin-navbar">
    <div class="navbar-left">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <h1>Meta Shark</h1>
    </div>
    <div class="nav-user-info">
        <span style="color:var(--text-muted)">Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong></span>
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="admin_logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<div class="layout-container">
    <aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-item"><i class="bi bi-grid-1x2-fill"></i> Dashboard</div>
    
    <div class="sidebar-group-label">Analytics</div>
    <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-activity"></i> Overview Chart</a>
    <a href="charts_line.php" class="sidebar-item"><i class="bi bi-graph-up-arrow"></i> Revenue Chart</a>
    <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart-fill"></i> Categories Chart</a>
    <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart-fill"></i> Orders Chart</a>
    <a href="charts_geo.php" class="sidebar-item"><i class="bi bi-globe2"></i> Geography Chart</a>

    <div class="sidebar-group-label">Management and Access</div>
    <a href="pending_requests.php" class="sidebar-item"><i class="bi bi-shield-lock"></i> Requests</a>
    <a href="admin_products.php" class="sidebar-item"><i class="bi bi-box-seam"></i> Products</a>
    <a href="admin_users.php" class="sidebar-item active"><i class="bi bi-people-fill"></i> Users</a>
    <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
    <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag-check-fill"></i> Orders</a>

    <div class="sidebar-group-label">Settings</div>
    <a href="admin_profile.php" class="sidebar-item"><i class="bi bi-person-gear"></i> My Profile</a>
</aside>

    <div class="admin-main">
        <h2 style="margin-bottom:25px; font-size: 28px; font-weight: 700;">User Management</h2>

        <?php if(isset($_GET['updated'])): ?><div class="alert alert-success">User details updated successfully.</div><?php endif; ?>
        <?php if(isset($_GET['deleted'])): ?><div class="alert alert-success">User deleted successfully.</div><?php endif; ?>
        <?php if(isset($_GET['suspended'])): ?><div class="alert alert-success">User suspended. Notification sent.</div><?php endif; ?>
        <?php if(isset($_GET['error'])): ?><div class="alert" style="background:rgba(244,67,54,0.1);color:#f44336;border:1px solid #f44336;">Action failed. Invalid email or error.</div><?php endif; ?>

        <div class="filters">
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

        <div class="table-card">
            <h3>Registered Users (<?php echo count($users); ?>)</h3>
            
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
                                            
                                            <button onclick="openEditModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['fullname'])); ?>', '<?php echo htmlspecialchars($u['email']); ?>')" class="btn btn-edit" title="Edit Email/Pass">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>

                                            <?php if(isset($u['is_suspended'])): ?>
                                                <?php if(!$u['is_suspended']): ?>
                                                    <a href="admin_users.php?toggle_suspend=<?php echo $u['id']; ?>&status=0" class="btn btn-suspend" title="Suspend User">
                                                        <i class="bi bi-pause-circle"></i> Suspend
                                                    </a>
                                                <?php else: ?>
                                                    <a href="admin_users.php?toggle_suspend=<?php echo $u['id']; ?>&status=1" class="btn btn-activate" title="Activate User">
                                                        <i class="bi bi-play-circle"></i> Activate
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <a href="admin_users.php?delete=<?php echo $u['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this user?')" title="Delete User">
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
    </div>
</div>

<!-- EDIT USER MODAL -->
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
    const modal = document.getElementById('editUserModal');

    function openEditModal(id, fullname, email) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_fullname').value = fullname;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_password').value = ''; // Reset password field
        modal.classList.add('show');
    }

    function closeEditModal() {
        modal.classList.remove('show');
    }

    // Close when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            closeEditModal();
        }
    }
</script>

</body>
</html>