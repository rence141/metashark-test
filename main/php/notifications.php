<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'buyer';
$theme = $_SESSION['theme'] ?? 'dark';

// Fetch notifications
$notifications = [];
$sql = $conn->prepare("SELECT id, message, type, created_at, `read` FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
if ($sql) {
    $sql->bind_param("i", $userId);
    $sql->execute();
    $res = $sql->get_result();
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
}

$unread_count = count(array_filter($notifications, fn($n) => !$n['read']));

// Fetch profile image
$profile_query = "SELECT profile_image FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $userId);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$current_profile = $profile_result->fetch_assoc();
$current_profile_image = $current_profile['profile_image'] ?? null;
$profile_src = !empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)
    ? 'Uploads/' . htmlspecialchars($current_profile_image)
    : 'Uploads/default-avatar.svg';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - Meta Shark</title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<?php include('theme_toggle.php'); ?>
<style>
:root {
    --bg-primary: #fff;
    --bg-secondary: #f8f9fa;
    --text-primary: #333;
    --text-muted: #6c757d;
    --border: #dee2e6;
    --primary-color: #44D62C;
}
[data-theme="dark"] {
    --bg-primary: #000;
    --bg-secondary: #2a2a2a;
    --text-primary: #e0e0e0;
    --text-muted: #888;
    --border: #444;
    --primary-color: #44D62C;
}
body { background: var(--bg-primary); color: var(--text-primary); font-family: 'Inter', Arial, sans-serif; margin: 0; padding: 0; }
.navbar { position: sticky; top: 0; z-index: 1000; background: var(--bg-secondary); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
.nav-left { display: flex; align-items: center; gap: 10px; }
.nav-left h2 { margin: 0; color: var(--primary-color); font-size: 24px; }
.nav-right { display: flex; gap: 12px; align-items: center; position: relative; z-index: 1100; }
.hamburger { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-primary); z-index: 1100; position: relative; }
.menu { display: none; position: absolute; top: 60px; right: 20px; background: var(--bg-secondary); list-style: none; padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-width: 150px; z-index: 1000; }
.menu.show { display: block; }
.menu li { margin: 10px 0; }
.menu a { display: block; padding: 5px; border-radius: 4px; color: var(--text-primary); text-decoration: none; font-size: 16px; }
.menu a:hover { background: var(--border); color: var(--primary-color); }
.profile-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); }
.container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
h1 { color: var(--primary-color); }
.unread-badge { background: #ff6b6b; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.8em; }
.card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 12px; cursor: pointer; transition: background 0.2s; }
.card:hover { background: rgba(68, 214, 44, 0.1); }
.card.unread { border-left: 4px solid var(--primary-color); background: rgba(68, 214, 44, 0.1); }
.muted { color: var(--text-muted); font-size: 0.9em; }
.row { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
.type-badge { padding: 2px 6px; border-radius: 4px; font-size: 0.8em; }
.type-order { background: #007bff; color: white; }
.type-order-status { background: #28a745; color: white; }
.type-order_cancelled { background: #dc3545; color: white; }
.mark-read { color: var(--primary-color); font-weight: bold; cursor: pointer; }
.mark-read:hover { text-decoration: underline; }
.notification-message a { color: var(--primary-color); text-decoration: none; }
.notification-message a:hover { text-decoration: underline; }
@media (max-width: 768px) { .row { flex-direction: column; align-items: flex-start; gap: 8px; } .navbar { flex-wrap: wrap; } .nav-right { gap: 8px; } }
</style>
</head>
<body>
<!-- NAVBAR -->
<div class="navbar">
  <div class="nav-left">
    <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo" style="height: 40px;">
    <h2>Meta Shark</h2>
  </div>
  <div class="nav-right">
    <a href="notifications.php" title="Notifications" style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
      <i class="bi bi-bell" style="font-size:18px;"></i>
      <span id="unreadCount"><?php echo $unread_count > 0 ? "($unread_count)" : ""; ?></span>
    </a>
    <a href="<?php echo $role === 'seller' || $role === 'admin' ? 'seller_profile.php' : 'profile.php'; ?>">
      <img src="<?php echo $profile_src; ?>" alt="Profile" class="profile-icon">
    </a>
    <button type="button" class="hamburger" aria-label="Toggle menu">☰</button>
  </div>
  <ul class="menu" id="menu">
    <li><a href="shop.php">Home</a></li>
    <li><a href="carts_users.php">Cart</a></li>
    <li><a href="order_status.php">My Orders</a></li>
    <?php if ($role === 'seller' || $role === 'admin'): ?>
      <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
    <?php else: ?>
      <li><a href="become_seller.php">Become Seller</a></li>
    <?php endif; ?>
    <li><a href="<?php echo $role === 'seller' || $role === 'admin' ? 'seller_profile.php' : 'profile.php'; ?>">Profile</a></li>
    <li><a href="logout.php">Logout</a></li>
  </ul>
</div>

<div class="container">
  <h1>Notifications <span id="unreadCountHeader"><?php echo $unread_count > 0 ? "($unread_count unread)" : ""; ?></span></h1>

  <?php if (empty($notifications)): ?>
    <div class="card">No notifications yet. <a href="shop.php">Continue Shopping</a></div>
  <?php else: ?>
    <?php foreach ($notifications as $notif): ?>
      <div class="card <?php echo $notif['read'] ? '' : 'unread'; ?>" onclick="markAsRead(<?php echo $notif['id']; ?>)">
        <div class="row">
          <div style="flex: 1;">
            <div class="notification-message"><?php echo $notif['message']; ?></div>
            <div class="muted">
              <span class="type-badge type-<?php echo htmlspecialchars($notif['type']); ?>"><?php echo ucfirst(htmlspecialchars($notif['type'])); ?></span>
              · <?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>
            </div>
          </div>
          <?php if (!$notif['read']): ?>
            <div class="mark-read">Mark as read</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Hamburger menu
  const hamburger = document.querySelector('.hamburger');
  const menu = document.getElementById('menu');
  if (hamburger && menu) {
    hamburger.addEventListener('click', function() {
      menu.classList.toggle('show');
    });
    document.addEventListener('click', function(e) {
      if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
      }
    });
    const menuItems = menu.querySelectorAll('a');
    menuItems.forEach(item => item.addEventListener('click', function() {
      menu.classList.remove('show');
    }));
  }

  // Mark notification as read
  window.markAsRead = function(notifId) {
    fetch('mark_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const card = event.target.closest('.card');
        card.classList.remove('unread');
        const markRead = card.querySelector('.mark-read');
        if (markRead) markRead.remove();
        const currentCount = parseInt(document.getElementById('unreadCount').textContent.replace(/[()]/g, '') || '0');
        const newCount = Math.max(0, currentCount - 1);
        document.getElementById('unreadCount').textContent = newCount > 0 ? `(${newCount})` : '';
        document.getElementById('unreadCountHeader').textContent = newCount > 0 ? `(${newCount} unread)` : '';
      }
    });
  }
});
</script>
</body>
</html>
