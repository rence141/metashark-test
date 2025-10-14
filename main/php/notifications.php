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

// AJAX: Fetch notifications (GET) or perform actions (POST ajax_action)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'fetch') {
    header('Content-Type: application/json; charset=utf-8');
    // select only known columns to avoid "unknown column" errors
    $stmt = $conn->prepare("SELECT id, message, type, created_at, `read` FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = [];
    while ($row = $res->fetch_assoc()) {
        // derive title and link safely (DB may not have title/link columns)
        $title = isset($row['title']) && $row['title'] !== '' ? $row['title'] : (isset($row['message']) ? mb_strimwidth($row['message'], 0, 80, '...') : '');
        $type = $row['type'] ?? '';
        $nid = (int)$row['id'];
        // derive link based on type as sensible fallback
        switch (strtolower($type)) {
            case 'order':
            case 'order_status':
            case 'order_updated':
            case 'order_cancelled':
                $link = "order_status.php?nid={$nid}";
                break;
            case 'message':
            case 'inbox':
                $link = "messages.php?nid={$nid}";
                break;
            case 'promo':
            case 'offer':
                $link = "shop.php?nid={$nid}";
                break;
            default:
                $link = "shop.php?nid={$nid}";
                break;
        }

        $notifications[] = [
            'id' => $nid,
            'title' => $title,
            'message' => $row['message'] ?? '',
            'link' => $link,
            'type' => $type,
            'created_at' => $row['created_at'],
            'read' => (int)$row['read']
        ];
    }
    $unread_count = count(array_filter($notifications, fn($n) => $n['read'] === 0));
    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count], JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    if ($action === 'mark_read' && isset($_POST['id'])) {
        $nid = (int)$_POST['id'];
        $u = $userId;
        $update = $conn->prepare("UPDATE notifications SET `read` = 1 WHERE id = ? AND user_id = ?");
        $update->bind_param("ii", $nid, $u);
        $success = $update->execute();
        echo json_encode(['success' => (bool)$success]);
        exit();
    }

    if ($action === 'mark_all') {
        $u = $userId;
        $update = $conn->prepare("UPDATE notifications SET `read` = 1 WHERE user_id = ?");
        $update->bind_param("i", $u);
        $success = $update->execute();
        echo json_encode(['success' => (bool)$success]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Server-side initial render data (will be superseded by AJAX polling)
$stmt = $conn->prepare("SELECT id, message, type, created_at, `read` FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$notifications_initial = [];
while ($row = $res->fetch_assoc()) {
    // compute title and link like in AJAX fetch
    $title = isset($row['title']) && $row['title'] !== '' ? $row['title'] : (isset($row['message']) ? mb_strimwidth($row['message'], 0, 80, '...') : '');
    $type = $row['type'] ?? '';
    $nid = (int)$row['id'];
    switch (strtolower($type)) {
        case 'order':
        case 'order_status':
        case 'order_updated':
        case 'order_cancelled':
            $link = "order_status.php?nid={$nid}";
            break;
        case 'message':
        case 'inbox':
            $link = "messages.php?nid={$nid}";
            break;
        case 'promo':
        case 'offer':
            $link = "shop.php?nid={$nid}";
            break;
        default:
            $link = "shop.php?nid={$nid}";
            break;
    }
    $notifications_initial[] = [
        'id' => $nid,
        'title' => $title,
        'message' => $row['message'] ?? '',
        'link' => $link,
        'type' => $type,
        'created_at' => $row['created_at'],
        'read' => (int)$row['read']
    ];
}
$unread_count_initial = count(array_filter($notifications_initial, fn($n) => !$n['read']));

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
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Notifications - Meta Shark</title>
<link rel="icon" type="image/png" href="Uploads/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<?php include('theme_toggle.php'); ?>
<style>
:root{
  --bg-primary:#fff;
  --bg-secondary:#f8f9fa;
  --text-primary:#222;
  --muted:#6c757d;
  --border:#e6e9ee;
  --accent:#44D62C; /* primary green accent */
  --unread-bg:#eafff0; /* light green highlight for unread */
}
[data-theme="dark"] {
  --bg-primary:#0b0b0b;
  --bg-secondary:#161616;
  --text-primary:#eaeaea;
  --muted:#9a9a9a;
  --border:#2f2f2f;
  --accent:#00ff88; /* keep green accent in dark mode */
  --unread-bg:rgba(0,255,136,0.06); /* subtle dark-mode green highlight */
}
body{margin:0;background:var(--bg-primary);color:var(--text-primary);font-family:Inter,Arial,Helvetica,sans-serif;}
.navbar{position:sticky;top:0;z-index:1000;background:var(--bg-secondary);display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--border);}
.nav-left{display:flex;align-items:center;gap:12px}
.nav-left h2{margin:0;color:var(--accent)}
.nav-right{display:flex;align-items:center;gap:10px}
.profile-icon{width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid var(--border)}
.container{max-width:1000px;margin:20px auto;padding:0 16px}
.header-actions{display:flex;gap:10px;align-items:center}
#markAllBtn{background:transparent;border:1px solid var(--border);padding:7px 10px;border-radius:8px;cursor:pointer;color:var(--text-primary)}
#markAllBtn:hover{background:var(--border)}
.unread-count{background:#ff6b6b;color:#fff;border-radius:50%;padding:2px 6px;font-size:13px;margin-left:6px}
.list {display:flex;flex-direction:column;gap:12px;margin-top:14px}
.card{display:flex;gap:12px;align-items:flex-start;padding:14px;border-radius:10px;border:1px solid var(--border);background:var(--bg-secondary);cursor:pointer;transition:transform .08s ease,box-shadow .08s ease}
.card:hover{transform:translateY(-3px);box-shadow:0 6px 18px rgba(0,0,0,0.06)}
.card.unread{background:var(--unread-bg);border-left:4px solid var(--accent)}
.card .icon{width:48px;height:48px;border-radius:8px;background:rgba(0,0,0,0.03);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--accent)}
.card .content{flex:1}
.card .title{font-weight:700;margin-bottom:6px}
.card .message{color:var(--muted);margin-bottom:8px}
.card .meta{display:flex;gap:10px;align-items:center;font-size:13px;color:var(--muted)}
.card .actions{display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.mark-read-inline{background:var(--accent);color:#000;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;font-weight:700}
.empty{padding:18px;border-radius:10px;background:var(--bg-secondary);border:1px solid var(--border);text-align:center;color:var(--muted)}
@media(max-width:720px){.card{flex-direction:row}.card .actions{align-items:flex-start}}
</style>
</head>
<body>
<div class="navbar">
  <div class="nav-left">
    <img src="Uploads/logo1.png" alt="Meta Shark" style="height:40px">
    <h2>Meta Shark</h2>
  </div>
  <div class="nav-right">
    <div style="display:flex;align-items:center;">
      <a href="notifications.php" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:6px">
        <i class="bi bi-bell"></i>
        <span id="unreadCount"><?php echo $unread_count_initial>0? "({$unread_count_initial})":""; ?></span>
      </a>
    </div>
    <div class="header-actions">
      <button id="markAllBtn" title="Mark all as read"><i class="bi bi-check2-all"></i> <span style="margin-left:6px">Mark All as Read</span></button>
      <a href="<?php echo $role === 'seller' || $role === 'admin' ? 'seller_profile.php' : 'profile.php'; ?>">
        <img src="<?php echo $profile_src; ?>" alt="Profile" class="profile-icon">
      </a>
    </div>
  </div>
</div>

<div class="container">
  <h1>Notifications <small id="unreadCountHeader"><?php echo $unread_count_initial>0? "({$unread_count_initial} unread)":""; ?></small></h1>

  <div id="notificationsList" class="list">
    <?php if (empty($notifications_initial)): ?>
      <div class="empty">No notifications yet. <a href="shop.php">Continue shopping</a></div>
    <?php else: ?>
      <?php foreach ($notifications_initial as $n): 
        $icon = 'bi-bell';
        switch (strtolower($n['type'] ?? '')) {
          case 'order': $icon='bi-receipt'; break;
          case 'message': $icon='bi-chat-left-dots'; break;
          case 'promo': $icon='bi-gift'; break;
          case 'order_cancelled': $icon='bi-x-circle'; break;
        }
        $isUnread = !$n['read'];
        $title = htmlspecialchars($n['title'] ?? '');
        $message = htmlspecialchars($n['message'] ?? '');
        $link = htmlspecialchars($n['link'] ?? "shop.php");
      ?>
      <div class="card <?php echo $isUnread ? 'unread' : ''; ?>" data-id="<?php echo (int)$n['id']; ?>" data-link="<?php echo $link; ?>" tabindex="0" role="button" aria-pressed="<?php echo $isUnread? 'false':'true'; ?>">
        <div class="icon"><i class="bi <?php echo $icon; ?>"></i></div>
        <div class="content">
          <div class="title"><?php echo $title ?: ($n['type'] ? ucfirst($n['type']) : 'Notification'); ?></div>
          <div class="message"><?php echo $message; ?></div>
          <div class="meta">
            <span class="type-badge"><?php echo htmlspecialchars($n['type'] ?? ''); ?></span>
            <span>·</span>
            <span><?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?></span>
          </div>
        </div>
        <div class="actions">
          <?php if ($isUnread): ?>
            <button class="mark-read-inline" data-id="<?php echo (int)$n['id']; ?>">Mark read</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const listEl = document.getElementById('notificationsList');
  const unreadCountEl = document.getElementById('unreadCount');
  const unreadHeaderEl = document.getElementById('unreadCountHeader');
  const markAllBtn = document.getElementById('markAllBtn');
  const FETCH_INTERVAL = 5000; // ms

  function formatCardHTML(n) {
    const iconMap = { order:'bi-receipt', message:'bi-chat-left-dots', promo:'bi-gift', order_cancelled:'bi-x-circle' };
    const icon = iconMap[(n.type || '').toLowerCase()] || 'bi-bell';
    const title = n.title || (n.type ? (n.type.charAt(0).toUpperCase()+n.type.slice(1)) : 'Notification');
    const message = n.message || '';
    const time = new Date(n.created_at).toLocaleString();
    const unreadClass = n.read === 0 ? 'unread' : '';
    const markBtn = n.read === 0 ? `<button class="mark-read-inline" data-id="${n.id}">Mark read</button>` : '';
    return `
      <div class="card ${unreadClass}" data-id="${n.id}" data-link="${escapeHtml(n.link)}" tabindex="0" role="button" aria-pressed="${n.read===0? 'false':'true'}">
        <div class="icon"><i class="bi ${icon}"></i></div>
        <div class="content">
          <div class="title">${escapeHtml(title)}</div>
          <div class="message">${escapeHtml(message)}</div>
          <div class="meta"><span class="type-badge">${escapeHtml(n.type||'')}</span> · <span>${escapeHtml(time)}</span></div>
        </div>
        <div class="actions">${markBtn}</div>
      </div>`;
  }

  function escapeHtml(s){
    if(!s) return '';
    return s.toString().replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];});
  }

  function renderNotifications(data){
    if(!data || !Array.isArray(data.notifications)) return;
    if(data.notifications.length === 0){
      listEl.innerHTML = '<div class="empty">No notifications yet. <a href="shop.php">Continue shopping</a></div>';
    } else {
      listEl.innerHTML = data.notifications.map(formatCardHTML).join('');
    }
    updateUnreadUI(data.unread_count || 0);
  }

  function updateUnreadUI(count){
    unreadCountEl.textContent = count > 0 ? `(${count})` : '';
    unreadHeaderEl.textContent = count > 0 ? `(${count} unread)` : '';
  }

  // Fetch notifications via AJAX
  function fetchNotifications(){
    fetch(window.location.pathname + '?ajax_action=fetch', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if(data && data.success){
          renderNotifications(data);
        }
      })
      .catch(err => console.error('Fetch notifications failed', err));
  }

  // Event delegation for clicks on notifications and mark-read buttons
  listEl.addEventListener('click', function(e){
    const markBtn = e.target.closest('.mark-read-inline');
    if(markBtn){
      e.stopPropagation();
      const id = markBtn.getAttribute('data-id');
      markAsRead(id, function(success){
        if(success){
          const card = markBtn.closest('.card');
          if(card) {
            card.classList.remove('unread');
            markBtn.remove();
          }
          // decrement unread count by 1
          const current = parseInt((unreadCountEl.textContent || '').replace(/[()]/g,'')||'0',10);
          updateUnreadUI(Math.max(0, current - 1));
        } else {
          alert('Could not mark notification as read. Try again.');
        }
      });
      return;
    }

    const card = e.target.closest('.card');
    if(card){
      const id = card.getAttribute('data-id');
      const link = card.getAttribute('data-link') || 'shop.php';
      // if unread, mark then redirect
      if(card.classList.contains('unread')){
        markAsRead(id, function(success){
          // update UI
          card.classList.remove('unread');
          const btn = card.querySelector('.mark-read-inline');
          if(btn) btn.remove();
          const current = parseInt((unreadCountEl.textContent || '').replace(/[()]/g,'')||'0',10);
          updateUnreadUI(Math.max(0, current - 1));
          // redirect
          window.location.href = link;
        });
      } else {
        window.location.href = link;
      }
    }
  });

  // Keyboard accessibility (Enter/Space)
  listEl.addEventListener('keydown', function(e){
    if(e.key === 'Enter' || e.key === ' '){
      const card = e.target.closest('.card');
      if(card){ card.click(); e.preventDefault(); }
    }
  });

  function markAsRead(id, cb){
    fetch(window.location.pathname, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'ajax_action=mark_read&id=' + encodeURIComponent(id)
    }).then(r => r.json()).then(d => cb(Boolean(d && d.success))).catch(() => cb(false));
  }

  // Mark all as read
  if(markAllBtn){
    markAllBtn.addEventListener('click', function(){
      if(!confirm('Mark all notifications as read?')) return;
      fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=mark_all'
      }).then(r => r.json()).then(d => {
        if(d && d.success){
          // update UI: remove unread class and inline buttons
          document.querySelectorAll('.card.unread').forEach(c => {
            c.classList.remove('unread');
            const btn = c.querySelector('.mark-read-inline');
            if(btn) btn.remove();
          });
          updateUnreadUI(0);
        } else {
          alert('Could not mark all as read. Try again.');
        }
      }).catch(err => { console.error(err); alert('Request failed'); });
    });
  }

  // Polling for new notifications
  fetchNotifications(); // initial
  setInterval(fetchNotifications, FETCH_INTERVAL);

})();
</script>
</body>
</html>
