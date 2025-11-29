<?php
session_start();
// Assuming db_connect.php handles the connection and assigns it to $conn
require_once __DIR__ . '/includes/db_connect.php';

// Security check: Redirect if admin is not logged in
if (!isset($_SESSION['admin_id'])) { 
    header('Location: admin_login.php'); 
    exit; 
}

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

// Define upload directory
$upload_dir = 'uploads/avatars/';
// Ensure directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}


// --- 1. Handle Profile Update Submission (FIXED BLOCK with File Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light'; // Simple validation
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
                // Generate a unique filename based on user ID and timestamp
                $new_file_name = $admin_id . '_' . time() . '.' . $file_ext;
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
            $params[] = $theme;
            $types = "sss"; // Start with string, string, string

            if (!empty($avatar_update_sql)) {
                $params[] = $new_avatar_filename;
                $types .= "s";
            }
            $params[] = $admin_id;
            $types .= "i";
            
            // Need to pass parameters as references for bind_param
            $bind_names[] = $types;
            for ($i=0; $i<count($params); $i++) {
                $bind_names[] = &$params[$i];
            }

            // --- START OF CRITICAL ERROR CHECK ---
            if ($stmt === false) {
                $error_message = "SQL Preparation Failed: " . $conn->error . ". Please check your table/column names: " . $sql;
            } else {
                // Call bind_param using call_user_func_array
                call_user_func_array(array($stmt, 'bind_param'), $bind_names);
                
                if ($stmt->execute()) {
                    // Update session variables immediately
                    $_SESSION['admin_name'] = $fullname;
                    $_SESSION['theme'] = $theme;
                    $success_message = "Profile updated successfully!";
                    // Important: Use the updated theme for the current page load
                    $current_theme = $theme;
                } else {
                    $error_message = "Database execution error: " . $stmt->error;
                }
            }
            
            // Only attempt to close if $stmt is a statement object (not false)
            if ($stmt) {
                $stmt->close();
            }
            // --- END OF CRITICAL ERROR CHECK ---
        }
    }
}

// --- 2. Fetch Current Admin Data (for display) ---
// Set current theme from session, or default to 'dark'
$current_theme = $_SESSION['theme'] ?? 'dark'; 
$admin_data = [];

$sql_fetch = "SELECT id, fullname, email, phone, created_at AS registration_date, role, theme, avatar FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql_fetch);

// --- CRITICAL ERROR CHECK FOR FETCHING DATA ---
if ($stmt === false) {
    $error_message = "Data Fetch Preparation Failed: " . $conn->error . ". Please check your table/column names: " . $sql_fetch;
} else {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin_data = $result->fetch_assoc();
        $current_theme = $admin_data['theme'] ?? $current_theme; 
    } else {
        $error_message = "Error: Admin data not found or ID is invalid.";
        session_destroy();
        header('Location: admin_login.php'); 
        exit;
    }
    $stmt->close();
}
// ---------------------------------------------

$admin_name = $admin_data['fullname'] ?? 'Admin';
$avatar_filename = $admin_data['avatar'] ?? 'default_avatar.png';
$avatar_path = 'uploads/avatars/' . htmlspecialchars($avatar_filename);
?>
<!doctype html>
<html lang="en" data-theme="<?php echo htmlspecialchars($current_theme); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <title>Admin Profile — Meta Shark</title>
    <link rel="icon" href="uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    /* --- Design System (Glassmorphism & Dark Mode) --- */
    :root {
        --primary: #44D62C;
        --primary-glow: rgba(68, 214, 44, 0.3);
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 12px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --danger: #f44336;
        --info: #00d4ff;
    }
    [data-theme="dark"] {
        --bg: #0f1115;
        --panel: #161b22;
        --panel-border: #242c38;
        --text: #e6eef6;
        --text-muted: #94a3b8;
        --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }
    
    /* --- Base Layout --- */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
    a { text-decoration: none; color: inherit; transition: 0.2s; }
    .admin-wrapper { display: flex; min-height: 100vh; }
    
    /* --- Navbar (Top Bar) --- */
    .admin-navbar {
        position: fixed; top: 0; left: 0; right: 0; height: 64px;
        background: var(--panel); 
        border-bottom: 1px solid var(--panel-border);
        box-shadow: var(--shadow);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px; z-index: 1000;
    }
    .navbar-left { display: flex; align-items: center; gap: 16px; }
    .navbar-left img { height: 32px; }
    .navbar-left h1 { font-size: 18px; margin: 0; font-weight: 700; }
    
    .nav-user-info { display:flex; align-items:center; gap:16px; font-size: 14px; }
    .nav-user-info a { color: var(--text-muted); }
    .nav-user-info a:hover { color: var(--primary); }
    
    /* --- Sidebar --- */
    .admin-sidebar {
        position: fixed; left: 0; top: 64px; width: 240px; height: calc(100vh - 64px);
        background: var(--panel); 
        border-right: 1px solid var(--panel-border);
        padding: 20px 0; overflow-y: auto;
        z-index: 999;
    }
    .sidebar-item {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 20px; transition: 0.2s;
        border-left: 3px solid transparent; color: var(--text-muted);
        text-decoration: none; font-weight: 500;
    }
    .sidebar-item:hover, .sidebar-item.active {
        background: var(--bg); 
        color: var(--primary); border-left-color: var(--primary);
    }
    .sidebar-heading {
        padding: 10px 20px; color: var(--text-muted); font-weight: 600; font-size: 13px; margin-top: 10px;
    }
    
    /* --- Main Content --- */
    .admin-main {
        margin-left: 240px; 
        margin-top: 64px; 
        padding: 30px;
        width: calc(100% - 240px);
    }
    .admin-main h2 { margin-bottom: 25px; font-size: 24px; font-weight: 700; }

    /* --- Profile Card Styles --- */
    .profile-container {
        max-width: 900px;
    }
    .card {
        background: var(--panel); 
        border-radius: var(--radius);
        border: 1px solid var(--panel-border); 
        box-shadow: var(--shadow);
        padding: 30px;
        flex: 1;
        min-width: 300px;
        margin-bottom: 30px;
    }
    
    .edit-card-hidden {
        display: none;
    }

    .profile-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--panel-border);
        padding-bottom: 10px;
    }
    .profile-card-avatar {
        cursor: pointer;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--primary);
        box-shadow: 0 0 10px var(--primary-glow);
    }
    .profile-header-info h3 {
        color: var(--primary);
        margin: 0;
        font-size: 20px;
        border: none;
        padding: 0;
    }
    .profile-header-info p {
        margin: 0;
        color: var(--text-muted);
        font-size: 14px;
    }
    .card h3 {
        color: var(--primary);
        margin-bottom: 20px;
        font-size: 18px;
        border-bottom: 1px solid var(--panel-border);
        padding-bottom: 10px;
    }
    .profile-info p {
        margin-bottom: 15px;
        padding-bottom: 5px;
        border-bottom: 1px dotted var(--panel-border);
        font-size: 15px;
    }
    .profile-info strong {
        display: inline-block;
        width: 150px;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* --- Form Styles --- */
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 14px;
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        background: var(--bg);
        color: var(--text);
        font-size: 15px;
    }
    .form-group input:disabled {
        background: rgba(var(--text-muted), 0.1);
        cursor: not-allowed;
    }
    /* Style for the file input to look better */
    .form-group input[type="file"] {
        padding: 8px 12px;
    }
    .btn-submit {
        display: block;
        width: 100%;
        padding: 12px;
        background: var(--primary); 
        color: #000;
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: 700;
        transition: background 0.2s;
    }
    .btn-submit:hover { 
        background: #55f042; 
    }

    /* Alerts */
    .alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(68,214,44,0.1); color: var(--primary); border: 1px solid var(--primary); }
    .alert-error { background: rgba(244, 67, 54, 0.1); color: var(--danger); border: 1px solid var(--danger); }

    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-navbar">
        <div class="navbar-left">
            <img src="uploads/logo1.png" alt="Logo">
            <h1>Administrator Profile</h1>
        </div>
        <div class="nav-user-info">
            <span style="color:var(--text-muted)">Signed in as **<?php echo htmlspecialchars($admin_name); ?>**</span>
            <a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="admin_logout.php" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <div class="admin-sidebar">
        <a href="admin_dashboard.php" class="sidebar-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
        
        <div class="sidebar-heading">Charts</div>
        <a href="charts_overview.php" class="sidebar-item"><i class="bi bi-graph-up"></i> Overview</a>
        <a href="charts_line.php" class="sidebar-item"><i class="bi bi-bar-chart-line"></i> Revenue</a>
        <a href="charts_bar.php" class="sidebar-item"><i class="bi bi-bar-chart"></i> Categories</a>
        <a href="charts_pie.php" class="sidebar-item"><i class="bi bi-pie-chart"></i> Orders</a>
        <a href="charts_geo.php" class="sidebar-item"><i class="bi bi-globe2"></i> Geography</a>
        
        <div class="sidebar-heading">Management</div>
        <a href="admin_products.php" class="sidebar-item"><i class="bi bi-box"></i> Products</a>
        <a href="admin_users.php" class="sidebar-item"><i class="bi bi-people"></i> Users</a>
        <a href="admin_sellers.php" class="sidebar-item"><i class="bi bi-shop"></i> Sellers</a>
        <a href="admin_orders.php" class="sidebar-item"><i class="bi bi-bag"></i> Orders</a>

        <div class="sidebar-heading">Settings</div>
        <a href="admin_profile.php" class="sidebar-item active"><i class="bi bi-person-gear"></i> My Profile</a>
    </div>

    <div class="admin-main">
        <h2 style="margin-bottom:25px"><i class="bi bi-person-badge"></i> Account Settings</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="bi bi-x-octagon-fill"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="card profile-info">
                
                <div class="profile-header">
                    <img src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($admin_name); ?> Profile" class="profile-card-avatar" id="profile-avatar">
                    <div class="profile-header-info">
                        <h3>Current Details</h3>
                        <p>Click the **picture** above to **EDIT**</p>
                    </div>
                </div>

                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($admin_name); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($admin_data['email'] ?? 'N/A'); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($admin_data['phone'] ?? 'N/A'); ?></p>
                <p><strong>Theme:</strong> <?php echo htmlspecialchars(ucfirst($current_theme)); ?></p>
                <p><strong>Role:</strong> <span style="color:var(--primary); font-weight: 700;">SYSTEM ADMINISTRATOR</span></p>
                <p><strong>Registered:</strong> <?php echo date('M d, Y', strtotime($admin_data['registration_date'] ?? '')); ?></p>
            </div>

            <div class="card edit-card-hidden" id="edit-card">
                <h3>Update Profile</h3>
                <form method="POST" action="admin_profile.php" enctype="multipart/form-data"> 
                    <input type="hidden" name="update_profile" value="1">

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
                            <option value="dark" <?php echo $current_theme === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                            <option value="light" <?php echo $current_theme === 'light' ? 'selected' : ''; ?>>Light Mode</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_display">Email Address (Cannot change)</label>
                        <input type="email" id="email_display" value="<?php echo htmlspecialchars($admin_data['email'] ?? 'N/A'); ?>" disabled>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    
                    <p style="margin-top: 15px; font-size: 13px; color: var(--danger);">
                        <i class="bi bi-exclamation-triangle"></i> To change your password, please contact system support.
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const profileAvatar = document.getElementById('profile-avatar');
        const profileInfoCard = document.querySelector('.card.profile-info');
        const editCard = document.getElementById('edit-card');

        // Initial visibility setup: If there's no message, hide the edit card.
        const hasMessage = document.querySelector('.alert-success, .alert-error');
        if (!hasMessage) {
            editCard.classList.add('edit-card-hidden');
        } else {
            // If there's a message (from a failed/successful POST), show the edit card
            profileInfoCard.style.display = 'none';
        }

        // Add click listener to the specific avatar image
        if (profileAvatar) {
            profileAvatar.addEventListener('click', function() {
                // Toggle visibility: Hide the info card, show the edit card
                profileInfoCard.style.display = 'none';
                editCard.style.display = 'block'; 
                
                // Scroll to the edit card for better UX
                editCard.scrollIntoView({ behavior: 'smooth' });
            });
        }
    });
</script>
</body>
</html>