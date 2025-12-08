<?php
// 1. ENABLE ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Security check: Redirect if admin is not logged in
if (!isset($_SESSION['admin_id'])) { 
    header('Location: admin_login.php'); 
    exit; 
}

// Security: Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$theme = $_SESSION['theme'] ?? 'dark';

// Generate Initial for Profile Avatar
$admin_initial = strtoupper(substr($admin_name, 0, 1));

$success_message = '';
$error_message = '';

// Define upload directory
$upload_dir = 'uploads/avatars/';
// Ensure directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- 1. Handle Profile Update Submission ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['update_profile']) &&
    isset($_POST['csrf_token']) && 
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $new_theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    $avatar_update_sql = "";
    $params = [];
    $types = "";

    if (empty($fullname)) {
        $error_message = "Full Name cannot be empty.";
    } else {
        // --- Handle Image Upload ---
        if (isset($_FILES['new_avatar']) && $_FILES['new_avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['new_avatar']['tmp_name'];
            $file_name = $_FILES['new_avatar']['name'];
            $file_size = $_FILES['new_avatar']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = array('jpg', 'jpeg', 'png', 'gif');

            if (!in_array($file_ext, $allowed_exts)) {
                $error_message = "File upload failed: Only JPG, JPEG, PNG, and GIF files are allowed.";
            } elseif ($file_size > 5 * 1024 * 1024) { // Max 5MB
                $error_message = "File upload failed: File size must be less than 5MB.";
            } else {
                // Security: Sanitize filename and generate a unique name
                $safe_basename = preg_replace("/[^a-zA-Z0-9\._-]/", '', basename($file_name));
                $new_file_name = $admin_id . '_' . time() . '_' . $safe_basename;
                $dest_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    $avatar_update_sql = ", avatar = ?";
                    $new_avatar_filename = $new_file_name;
                } else {
                    $error_message = "File upload failed: Could not move file to destination.";
                }
            }
        }
        // --- End Handle Image Upload ---

        if (empty($error_message)) {
            // Prepare the dynamic SQL query
            $sql = "UPDATE users SET fullname = ?, phone = ?, theme = ? " . $avatar_update_sql . " WHERE id = ?";
            $stmt = $conn->prepare($sql);

            // Construct bind parameters dynamically
            $params[] = $fullname;
            $params[] = $phone;
            $params[] = $new_theme;
            $types = "sss"; 

            if (!empty($avatar_update_sql)) {
                $params[] = $new_avatar_filename;
                $types .= "s";
            }
            $params[] = $admin_id;
            $types .= "i";
            
            // Bind params
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                // Update session variables immediately
                $_SESSION['admin_name'] = $fullname;
                $_SESSION['theme'] = $new_theme;
                $theme = $new_theme; // Update for current page load
                $success_message = "Profile updated successfully!";
            } else {
                $error_message = "Database execution error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// --- 2. Fetch Current Admin Data (for display) ---
$admin_data = [];
$sql_fetch = "SELECT id, fullname, email, phone, created_at AS registration_date, role, theme, avatar FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql_fetch);

if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin_data = $result->fetch_assoc();
        // Update theme variable if needed
        if (isset($admin_data['theme'])) {
            $theme = $admin_data['theme'];
        }
    } else {
        $error_message = "Error: Admin data not found or ID is invalid.";
    }
    $stmt->close();
}

$avatar_filename = $admin_data['avatar'] ?? 'default_avatar.png';
// Check if file exists, if not use placeholder
if (!file_exists($upload_dir . $avatar_filename) || empty($admin_data['avatar'])) {
    $avatar_path = "https://placehold.co/150x150/44D62C/000000?text=" . $admin_initial;
} else {
    $avatar_path = $upload_dir . htmlspecialchars($avatar_filename);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <title>Admin Profile — Meta Shark</title>
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

    /* --- Profile Layout --- */
    .profile-container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; }
    @media (max-width: 992px) { .profile-container { grid-template-columns: 1fr; } }

    .card { background: var(--panel); border-radius: var(--radius); border: 1px solid var(--panel-border); box-shadow: var(--shadow); padding: 30px; }
    .hero { display:flex; gap:20px; align-items:center; border-bottom:1px solid var(--panel-border); padding-bottom:16px; margin-bottom:16px; }
    .profile-card-avatar { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary); box-shadow: 0 0 15px var(--primary-glow); cursor: pointer; transition: transform 0.2s; }
    .profile-card-avatar:hover { transform: scale(1.05); }
    .hero h3 { margin: 0; font-size: 24px; color: var(--text); }
    .hero .muted { margin-top: 4px; }

    .mini-stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:12px; margin-top:12px; }
    .mini-stat { background: rgba(68,214,44,0.08); border:1px solid rgba(68,214,44,0.2); border-radius:12px; padding:12px; }
    [data-theme="light"] .mini-stat { background: #ecfdf3; }
    .mini-stat .label { font-size:12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .mini-stat .value { font-weight:700; font-size:16px; margin-top:4px; }

    .card h3 { color: var(--text); margin-bottom: 16px; font-size: 18px; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px; }
    .profile-info p { margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px dashed var(--panel-border); font-size: 15px; display: flex; justify-content: space-between; gap: 12px; }
    .profile-info strong { color: var(--text-muted); font-weight: 600; }

    /* --- Form Styles --- */
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 14px; }
    .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid var(--panel-border); border-radius: 8px; background: var(--bg); color: var(--text); font-size: 15px; }
    .form-group input:disabled { background: rgba(107, 114, 128, 0.1); cursor: not-allowed; }
    
    .btn-submit { display: block; width: 100%; padding: 14px; background: var(--primary); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; transition: filter 0.2s; font-size: 16px; }
    .btn-submit:hover { filter: brightness(1.1); }

    .btn-xs { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: 0.2s; border: 1px solid transparent; display: inline-block; }
    .btn-outline { border-color: var(--panel-border); color: var(--text); background: transparent; }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

    /* Alerts */
    .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
    .alert-error { background: rgba(244, 67, 54, 0.1); color: var(--danger); border: 1px solid var(--danger); }

    .edit-card-hidden { display: none; }

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
        <h2 style="font-size: 24px; font-weight: 700;">Account Settings</h2>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success fade-in"><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error fade-in"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="profile-container fade-in" style="animation-delay: 0.1s;">
        <div class="card profile-info" style="grid-column: span 2; background: linear-gradient(135deg, rgba(68,214,44,0.08), rgba(0,212,255,0.05));">
            <div class="hero">
                <img src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($admin_name); ?> Profile" class="profile-card-avatar" id="profile-avatar-trigger">
                <div>
                    <h3><?php echo htmlspecialchars($admin_name); ?></h3>
                    <div class="muted"><?php echo htmlspecialchars($admin_data['email'] ?? 'N/A'); ?></div>
                    <div class="muted">Phone: <?php echo htmlspecialchars($admin_data['phone'] ?? 'N/A'); ?></div>
                    <div class="muted">Role: <strong style="color:var(--primary);">Administrator</strong></div>
                </div>
            </div>
            <div class="mini-stats">
                <div class="mini-stat">
                    <div class="label">Theme</div>
                    <div class="value"><?php echo htmlspecialchars(ucfirst($theme)); ?></div>
                </div>
                <div class="mini-stat">
                    <div class="label">Registered</div>
                    <div class="value"><?php echo date('M d, Y', strtotime($admin_data['registration_date'] ?? '')); ?></div>
                </div>
                <div class="mini-stat">
                    <div class="label">User ID</div>
                    <div class="value">#<?php echo (int)$admin_id; ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Current Details</h3>
            <div class="profile-info">
                <p><strong>Full Name:</strong> <span><?php echo htmlspecialchars($admin_name); ?></span></p>
                <p><strong>Email:</strong> <span><?php echo htmlspecialchars($admin_data['email'] ?? 'N/A'); ?></span></p>
                <p><strong>Phone:</strong> <span><?php echo htmlspecialchars($admin_data['phone'] ?? 'N/A'); ?></span></p>
                <p><strong>Theme:</strong> <span><?php echo htmlspecialchars(ucfirst($theme)); ?> Mode</span></p>
                <p><strong>Role:</strong> <span style="color:var(--primary); font-weight: 700;">SYSTEM ADMINISTRATOR</span></p>
                <p style="border-bottom:none;"><strong>Registered:</strong> <span><?php echo date('M d, Y', strtotime($admin_data['registration_date'] ?? '')); ?></span></p>
            </div>
            <button class="btn-submit" id="edit-btn-trigger" style="margin-top:20px;">Edit Profile</button>
        </div>

        <div class="card edit-card-hidden" id="edit-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--panel-border); padding-bottom:10px;">
                <h3 style="margin:0; border:none; padding:0;">Update Profile</h3>
            </div>
            
            <form method="POST" action="admin_profile.php" enctype="multipart/form-data"> 
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label for="new_avatar">New Profile Picture</label>
                    <input type="file" id="new_avatar" name="new_avatar" accept="image/jpeg,image/png,image/gif">
                    <p style="margin-top: 5px; font-size: 12px; color: var(--text-muted);">Max 5MB (JPG, PNG, GIF)</p>
                </div>
                
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($admin_data['fullname'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (Optional)</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($admin_data['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="theme">Theme Preference</label>
                    <select id="theme" name="theme">
                        <option value="dark" <?php echo $theme === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                        <option value="light" <?php echo $theme === 'light' ? 'selected' : ''; ?>>Light Mode</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="email_display">Email Address (Cannot change)</label>
                    <input type="email" id="email_display" value="<?php echo htmlspecialchars($admin_data['email'] ?? 'N/A'); ?>" disabled>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-save"></i> Save Changes
                </button>
                
                <p style="margin-top: 15px; font-size: 13px; color: var(--danger); text-align:center;">
                    <i class="bi bi-exclamation-triangle"></i> To change your password, please contact system support.
                </p>
            </form>
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

// Theme Logic (persist across admin pages)
const themeBtn = document.getElementById('themeBtn');
const storedTheme = localStorage.getItem('theme') || '<?php echo $theme; ?>';
function applyTheme(t){ document.documentElement.setAttribute('data-theme', t); localStorage.setItem('theme', t); }
function updateThemeIcon(t){ const icon = themeBtn.querySelector('i'); icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'; }
applyTheme(storedTheme); updateThemeIcon(storedTheme);
themeBtn.addEventListener('click', () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme); updateThemeIcon(newTheme);
    fetch('theme_toggle.php?theme=' + newTheme).catch(console.error);
});

// Profile Edit Toggle Logic
document.addEventListener('DOMContentLoaded', function() {
    const profileAvatar = document.getElementById('profile-avatar-trigger');
    const editBtn = document.getElementById('edit-btn-trigger');
    
    const profileInfoCard = document.querySelector('.card.profile-info');
    const editCard = document.getElementById('edit-card');

    function showEdit() {
        profileInfoCard.style.display = 'none';
        editCard.style.display = 'block';
        editCard.classList.remove('edit-card-hidden');
        editBtn.innerHTML = 'Cancel';
    }

    function hideEdit() {
        profileInfoCard.style.display = 'block';
        editCard.style.display = 'none';
        editCard.classList.add('edit-card-hidden');
        editBtn.innerHTML = 'Edit Profile';
    }

    if (profileAvatar) profileAvatar.addEventListener('click', showEdit);
    if (editBtn) editBtn.addEventListener('click', () => {
        const editing = editCard.classList.contains('edit-card-hidden') === false;
        if (editing) hideEdit(); else showEdit();
    });

    // If form was submitted (success/error message exists), show edit view or info view? 
    // Usually better to show Info view with the success message, 
    // or Edit view with the error message.
    <?php if ($error_message): ?>
        showEdit();
    <?php endif; ?>
});
</script>
</body>
</html>