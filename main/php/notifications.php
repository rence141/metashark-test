<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) { header("Location: login_users.php"); exit(); }
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'buyer';

$theme = $_SESSION['theme'] ?? 'dark';

// Fetch notifications from the notifications table
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

// Optional: Mark all as read on page load (uncomment if desired)
// $update_sql = $conn->prepare("UPDATE notifications SET `read` = 1 WHERE user_id = ? AND `read` = 0");
// if ($update_sql) { $update_sql->bind_param("i", $userId); $update_sql->execute(); }

$unread_count = count(array_filter($notifications, fn($n) => !$n['read']));

// Fetch current user's profile image
$profile_query = "SELECT profile_image FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $userId);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$current_profile = $profile_result->fetch_assoc();
$current_profile_image = $current_profile['profile_image'] ?? null;
$profile_src = !empty($current_profile_image) && file_exists('uploads/' . $current_profile_image) ? 'uploads/' . htmlspecialchars($current_profile_image) : 'uploads/default-avatar.svg';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - Meta Shark</title>
  <link rel="stylesheet" href="fonts/fonts.css">
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <?php include('theme_toggle.php'); ?>
  <style>
    .navbar {
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    body { background: var(--bg-primary); color: var(--text-primary); font-family: Arial, sans-serif; margin: 0; padding: 0; }
    .navbar { background: var(--bg-secondary); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
    .nav-left h2 { margin: 0; color: #44D62C; }
    .nav-right { display: flex; gap: 10px; align-items: center; }
    .nav-right ul { list-style: none; margin: 0; padding: 0; }
    .nav-right ul li { display: inline-block; }
    .nav-right ul li a { background: #44D62C; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; transition: background 0.3s, transform 0.2s; font-weight: bold; border: 1px solid #44D62C; }
    .nav-right ul li a:hover { background: #3ab826; transform: translateY(-1px); }
    .nav-right a { text-decoration: none; color: var(--text-primary); }
    .profile-icon { 
      width: 40px; 
      height: 40px; 
      border-radius: 50%; 
      object-fit: cover; 
      border: 2px solid var(--border); 
      display: block;
    }
    .menu { display: none; position: absolute; top: 100%; right: 0; background: var(--bg-secondary); list-style: none; padding: 10px; border: 1px solid var(--border); min-width: 150px; }
    .menu.show { display: block; }
    .menu li { margin: 5px 0; }
    .menu a { display: block; padding: 5px; border-radius: 4px; }
    .menu a:hover { background: var(--border); }
    .container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
    h1 { color: #44D62C; }
    .unread-badge { background: #ff6b6b; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.8em; }
    .card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 12px; cursor: pointer; transition: background 0.2s; }
    .card:hover { background: var(--bg-primary); }
    .card.unread { border-left: 4px solid #44D62C; background: rgba(68, 214, 44, 0.1); }
    .muted { color: var(--text-muted); font-size: 0.9em; }
    .row { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
    .type-badge { padding: 2px 6px; border-radius: 4px; font-size: 0.8em; }
    .type-order { background: #007bff; color: white; }
    .type-order-status { background: #28a745; color: white; }
    .mark-read { color: #44D62C; font-weight: bold; cursor: pointer; }
    .mark-read:hover { text-decoration: underline; }
    @media (max-width: 768px) { .row { flex-direction: column; align-items: flex-start; gap: 8px; } }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <div class="navbar">
    <div class="nav-left">
      <h2>Meta Shark</h2>
    </div>
    <div class="nav-right">
      <ul>
         <li><a href="javascript:history.back()">Back</a></li>
      </ul>
    </div>
    <ul class="menu" id="menu">
      <li><a href="shop.php">Home</a></li>
      <li><a href="carts_users.php">Cart</a></li>
      <li><a href="my_orders.php">My Orders</a></li>
      <li><a href="profile.php">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </div>

  <div class="container">
    <h1>Notifications (<?php echo $unread_count; ?> unread)</h1>
    <?php if (empty($notifications)): ?>
      <div class="card">No notifications yet. <a href="shop.php">Continue Shopping</a></div>
    <?php else: ?>
      <?php foreach ($notifications as $notif): ?>
        <div class="card <?php echo $notif['read'] ? '' : 'unread'; ?>" onclick="markAsRead(<?php echo $notif['id']; ?>)">
          <div class="row">
            <div style="flex: 1;">
              <div><strong><?php echo htmlspecialchars($notif['message']); ?></strong></div>
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
    function toggleMenu() {
      document.getElementById('menu').classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
      const menu = document.getElementById('menu');
      const hamburger = document.querySelector('.hamburger');
      if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
      }
    });

    function markAsRead(notifId) {
      fetch('mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + notifId
      }).then(response => response.json()).then(data => {
        if (data.success) {
          const card = event.target.closest('.card');
          card.classList.remove('unread');
          const markRead = card.querySelector('.mark-read');
          if (markRead) markRead.remove();
          location.reload(); // Refresh to update count
        }
      });
    }
  </script>
</body>
</html>