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

// Helper: resolve product image URL
function resolveProductImageUrlSeller(array $row) {
    $img = isset($row['product_image']) ? trim($row['product_image']) : '';
    if ($img !== '') {
        return str_replace('\\', '/', $img);
    }
    $candidates = [
        __DIR__ . '/Uploads/products/' . $img,
        __DIR__ . '/Uploads/' . $img,
        __DIR__ . '/Uploads/products/' . $img,
        __DIR__ . '/Uploads/' . $img,
        __DIR__ . '/../Uploads/products/' . $img,
        __DIR__ . '/../Uploads/' . $img
    ];
    foreach ($candidates as $path) {
        if ($img !== '' && file_exists($path)) {
            return basename($path);
        }
    }
    if ($img !== '') {
        if (file_exists(__DIR__ . '/Uploads/' . $img)) return 'Uploads/' . $img;
        if (file_exists(__DIR__ . '/Uploads/' . $img)) return 'Uploads/' . $img;
    }
    if (file_exists(__DIR__ . '/Uploads/default-product.png')) return 'Uploads/default-product.png';
    if (file_exists(__DIR__ . '/../Uploads/default-product.png')) return 'Uploads/default-product.png';
    return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90"><rect width="100%" height="100%" fill="#222"/><text x="50%" y="50%" fill="#888" font-size="12" dominant-baseline="middle" text-anchor="middle">No image</text></svg>');
}

// AJAX: Fetch notifications (GET) or perform actions (POST ajax_action)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'fetch') {
    header('Content-Type: application/json; charset=utf-8');
    // Modified query to fetch product_image for order-related notifications
    $query = "SELECT n.id, n.message, n.type, n.created_at, n.`read`, p.image AS product_image
              FROM notifications n
              LEFT JOIN order_items oi ON n.type IN ('order', 'order_status', 'order_updated', 'order_cancelled')
                AND n.message REGEXP 'Order #[0-9]+' AND oi.order_id = CAST(SUBSTRING(n.message, LOCATE('#', n.message) + 1, LOCATE(' -', n.message) - LOCATE('#', n.message) - 1) AS UNSIGNED)
              LEFT JOIN products p ON oi.product_id = p.id
              WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 50";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = [];
    while ($row = $res->fetch_assoc()) {
        $title = isset($row['title']) && $row['title'] !== '' ? $row['title'] : (isset($row['message']) ? mb_strimwidth($row['message'], 0, 80, '...') : '');
        $type = $row['type'] ?? '';
        $nid = (int)$row['id'];
        // Try to resolve product image if not joined correctly
        $resolvedProductImage = $row['product_image'] ?? null;
        if (empty($resolvedProductImage) && preg_match('/Order\s*#(\d+)/i', $row['message'] ?? '', $m)) {
            $oid = (int)$m[1];
            $imgStmt = $conn->prepare("SELECT p.image FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id = ? LIMIT 1");
            if ($imgStmt) { $imgStmt->bind_param('i', $oid); $imgStmt->execute(); $imgRes = $imgStmt->get_result(); if ($imgRow = $imgRes->fetch_assoc()) { $resolvedProductImage = $imgRow['image']; } $imgStmt->close(); }
        }
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
            'read' => (int)$row['read'],
            'product_image' => $resolvedProductImage ? resolveProductImageUrlSeller(['product_image' => $resolvedProductImage]) : null
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
    if ($action === 'clear_read') {
        $u = $userId;
        $del = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND `read` = 1");
        $del->bind_param("i", $u);
        $success = $del->execute();
        echo json_encode(['success' => (bool)$success]);
        exit();
    }
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Server-side initial render data
$query = "SELECT n.id, n.message, n.type, n.created_at, n.`read`, p.image AS product_image
          FROM notifications n
          LEFT JOIN order_items oi ON n.type IN ('order', 'order_status', 'order_updated', 'order_cancelled')
            AND n.message REGEXP 'Order #[0-9]+' AND oi.order_id = CAST(SUBSTRING(n.message, LOCATE('#', n.message) + 1, LOCATE(' -', n.message) - LOCATE('#', n.message) - 1) AS UNSIGNED)
          LEFT JOIN products p ON oi.product_id = p.id
          WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$notifications_initial = [];
while ($row = $res->fetch_assoc()) {
    $title = isset($row['title']) && $row['title'] !== '' ? $row['title'] : (isset($row['message']) ? mb_strimwidth($row['message'], 0, 80, '...') : '');
    $type = $row['type'] ?? '';
    $nid = (int)$row['id'];
    // Resolve product image if missing by parsing order id from message
    $resolvedProductImage = $row['product_image'] ?? null;
    if (empty($resolvedProductImage) && preg_match('/Order\s*#(\d+)/i', $row['message'] ?? '', $m)) {
        $oid = (int)$m[1];
        $imgStmt = $conn->prepare("SELECT p.image FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id = ? LIMIT 1");
        if ($imgStmt) { $imgStmt->bind_param('i', $oid); $imgStmt->execute(); $imgRes = $imgStmt->get_result(); if ($imgRow = $imgRes->fetch_assoc()) { $resolvedProductImage = $imgRow['image']; } $imgStmt->close(); }
    }
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
        'read' => (int)$row['read'],
        'product_image' => $resolvedProductImage ? resolveProductImageUrlSeller(['product_image' => $resolvedProductImage]) : null
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
:root {
  --bg-primary: #fff;
  --bg-secondary: #f8f9fa;
  --text-primary: #222;
  --muted: #6c757d;
  --border: #e6e9ee;
  --accent: #44D62C;
  --unread-bg: #eafff0;
}
[data-theme="dark"] {
  --bg-primary: #0b0b0b;
  --bg-secondary: #161616;
  --text-primary: #eaeaea;
  --muted: #9a9a9a;
  --border: #2f2f2f;
  --accent: #00ff88;
  --unread-bg: rgba(0,255,136,0.06);
}
body {
  margin: 0;
  background: var(--bg-primary);
  color: var(--text-primary);
  font-family: Inter, Arial, Helvetica, sans-serif;
}
.navbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: var(--bg-secondary);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 18px;
  border-bottom: 1px solid var(--border);
}
.nav-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.nav-left h2 {
  margin: 0;
  color: var(--accent);
}
.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
}
.profile-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--border);
}
.container {
  max-width: 1000px;
  margin: 20px auto;
  padding: 0 16px;
}
.header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}
#markAllBtn {
  background: transparent;
  border: 1px solid var(--border);
  padding: 7px 10px;
  border-radius: 8px;
  cursor: pointer;
  color: var(--text-primary);
}
#markAllBtn:hover {
  background: var(--border);
}
.unread-count {
  background: #ff6b6b;
  color: #fff;
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 13px;
  margin-left: 6px;
}
.list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 14px;
}
.card {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 14px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--bg-secondary);
  cursor: pointer;
  transition: transform .08s ease, box-shadow .08s ease;
}
.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}
.card.unread {
  background: var(--unread-bg);
  border-left: 4px solid var(--accent);
}
.card .icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: rgba(0,0,0,0.03);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: var(--accent);
}
.card .content {
  flex: 1;
}
.card .title {
  font-weight: 700;
  margin-bottom: 6px;
}
.card .message {
  color: var(--muted);
  margin-bottom: 8px;
}
.card .meta {
  display: flex;
  gap: 10px;
  align-items: center;
  font-size: 13px;
  color: var(--muted);
}
.card .actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-end;
}
.mark-read-inline {
  background: var(--accent);
  color: #000;
  border: none;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 700;
}
.empty {
  padding: 18px;
  border-radius: 10px;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  text-align: center;
  color: var(--muted);
}
.product-image {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--border);
  flex-shrink: 0;
}
@media (max-width: 720px) {
  .card {
    flex-direction: row;
  }
  .card .actions {
    align-items: flex-start;
  }
  .product-image {
    margin-top: 8px;
  }
}
</style>
</head>
<body>
<div class="navbar">
  <div class="nav-left">
    <img src="Uploads/logo1.png" alt="Meta Shark" style="height:40px">
    <h2>Meta Shark</h2>
  </div>
  <div class="nav-right">
   
    <div class="header-actions">
      <button id="markAllBtn" title="Mark all as read"><i class="bi bi-check2-all"></i> <span style="margin-left:6px">Mark All as Read</span></button>
      <button id="clearReadBtn" title="Delete read notifications" style="background:#00ff88;color:#000;border:none;padding:7px 10px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:6px"><i class="bi bi-trash3"></i><span>Clear Read</span></button>
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
        $isOrderType = in_array(strtolower($n['type'] ?? ''), ['order', 'order_status', 'order_updated', 'order_cancelled']);
        $imgSrc = $isOrderType && $n['product_image'] ? htmlspecialchars($n['product_image']) : null;
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
          <?php if ($isOrderType && $imgSrc): ?>
            <img src="<?php echo $imgSrc; ?>" alt="Product for order #<?php echo htmlspecialchars(substr($n['message'], strpos($n['message'], '#') + 1, strpos($n['message'], ' -') - strpos($n['message'], '#') - 1)); ?>" class="product-image" loading="lazy" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<?php echo rawurlencode('<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"48\" height=\"48\"><rect width=\"100%\" height=\"100%\" fill=\"#222\"/><text x=\"50%\" y=\"50%\" fill=\"#888\" font-size=\"10\" dominant-baseline=\"middle\" text-anchor=\"middle\">No image</text></svg>'); ?>';">
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
  const FETCH_INTERVAL = 5000;

  function formatCardHTML(n) {
    const iconMap = { order:'bi-receipt', message:'bi-chat-left-dots', promo:'bi-gift', order_cancelled:'bi-x-circle' };
    const icon = iconMap[(n.type || '').toLowerCase()] || 'bi-bell';
    const title = n.title || (n.type ? (n.type.charAt(0).toUpperCase()+n.type.slice(1)) : 'Notification');
    const message = n.message || '';
    const time = new Date(n.created_at).toLocaleString();
    const unreadClass = n.read === 0 ? 'unread' : '';
    const markBtn = n.read === 0 ? `<button class="mark-read-inline" data-id="${n.id}">Mark read</button>` : '';
    const isOrderType = ['order', 'order_status', 'order_updated', 'order_cancelled'].includes((n.type || '').toLowerCase());
    const imgSrc = isOrderType && n.product_image ? escapeHtml(n.product_image) : null;
    const imgHTML = imgSrc ? `<img src="${imgSrc}" alt="Product for order" class="product-image" loading="lazy" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,${encodeURIComponent('<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"48\" height=\"48\"><rect width=\"100%\" height=\"100%\" fill=\"#222\"/><text x=\"50%\" y=\"50%\" fill=\"#888\" font-size=\"10\" dominant-baseline=\"middle\" text-anchor=\"middle\">No image</text></svg>')}';">` : '';
    return `
      <div class="card ${unreadClass}" data-id="${n.id}" data-link="${escapeHtml(n.link)}" tabindex="0" role="button" aria-pressed="${n.read===0? 'false':'true'}">
        <div class="icon"><i class="bi ${icon}"></i></div>
        <div class="content">
          <div class="title">${escapeHtml(title)}</div>
          <div class="message">${escapeHtml(message)}</div>
          <div class="meta"><span class="type-badge">${escapeHtml(n.type||'')}</span> · <span>${escapeHtml(time)}</span></div>
        </div>
        <div class="actions">${markBtn}${imgHTML}</div>
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
      if(card.classList.contains('unread')){
        markAsRead(id, function(success){
          card.classList.remove('unread');
          const btn = card.querySelector('.mark-read-inline');
          if(btn) btn.remove();
          const current = parseInt((unreadCountEl.textContent || '').replace(/[()]/g,'')||'0',10);
          updateUnreadUI(Math.max(0, current - 1));
          window.location.href = link;
        });
      } else {
        window.location.href = link;
      }
    }
  });

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

  if(markAllBtn){
    markAllBtn.addEventListener('click', function(){
      if(!confirm('Mark all notifications as read?')) return;
      fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=mark_all'
      }).then(r => r.json()).then(d => {
        if(d && d.success){
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

  const clearReadBtn = document.getElementById('clearReadBtn');
  if (clearReadBtn) {
    clearReadBtn.addEventListener('click', function(){
      if(!confirm('Delete all read notifications? This cannot be undone.')) return;
      fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=clear_read'
      }).then(r=>r.json()).then(d=>{
        if(d && d.success){
          document.querySelectorAll('.card:not(.unread)').forEach(c => c.remove());
        } else {
          alert('Could not clear read notifications. Try again.');
        }
      }).catch(() => alert('Request failed'));
    });
  }

  fetchNotifications();
  setInterval(fetchNotifications, FETCH_INTERVAL);
})();
</script>
</body>
</html>