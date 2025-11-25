document.addEventListener('DOMContentLoaded', function(){
  // theme (initialization)
  const themeBtn = document.getElementById('themeDropdownBtn');
  const themeMenu = document.getElementById('themeMenu');
  const themeText = document.getElementById('themeText');
  const themeIcon = document.getElementById('themeIcon');
  function applyTheme(t){
    let eff = t;
    if(t==='device') eff = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', eff);
    themeText && (themeText.textContent = t.charAt(0).toUpperCase()+t.slice(1));
    themeIcon && (themeIcon.className = (t==='dark'?'bi bi-moon-fill': t==='light'?'bi bi-sun-fill':'bi bi-laptop'));
    try{ localStorage.setItem('site_theme', t); }catch(e){}
    fetch('?theme='+encodeURIComponent(t)).catch(()=>{});
  }
  const saved = localStorage.getItem('site_theme') || 'device';
  applyTheme(saved);
  if(themeBtn){ themeBtn.addEventListener('click', e=>{ e.stopPropagation(); themeMenu.style.display = themeMenu.style.display==='block' ? 'none' : 'block';}); themeMenu.addEventListener('click', e=>{ const opt = e.target.closest('.theme-option'); if(!opt) return; applyTheme(opt.dataset.theme); themeMenu.style.display='none'; }); document.addEventListener('click', ()=> themeMenu.style.display='none'); }

  // fetch dashboard stats and render charts
  function fetchJSON(url, cb){ fetch(url).then(r=>r.json()).then(cb).catch(()=>{}); }

  function renderStats(){
    fetchJSON('includes/fetch_data.php?action=dashboard_stats', function(data){
      const container = document.getElementById('statCards');
      if(!container) return;
      container.innerHTML = `
        <div class="card"><strong>Total Products</strong><div>${data.total_products}</div></div>
        <div class="card"><strong>Total Revenue</strong><div>$${Number(data.total_revenue).toLocaleString()}</div></div>
        <div class="card"><strong>Total Stock</strong><div>${data.total_stock}</div></div>
        <div class="card"><strong>Total Sellers</strong><div>${data.total_sellers}</div></div>
        <div class="card"><strong>Total Orders</strong><div>${data.total_orders}</div></div>
      `;
    });
  }

  // revenue chart
  let revenueChart = null;
  function loadRevenueChart(){
    fetchJSON('includes/fetch_data.php?action=monthly_revenue', function(rows){
      const labels = rows.map(r=>r.ym);
      const data = rows.map(r=>Number(r.amt));
      const ctx = document.getElementById('revenueChart').getContext('2d');
      if(revenueChart) revenueChart.destroy();
      revenueChart = new Chart(ctx, { type: 'line', data:{ labels, datasets:[{label:'Revenue', data, backgroundColor:'rgba(36,198,54,0.12)', borderColor:'#27ed15', tension:0.3}] }, options:{ responsive:true }});
    });
  }

  // orders chart
  let ordersChart = null;
  function loadOrdersChart(){
    fetchJSON('includes/fetch_data.php?action=orders_summary', function(rows){
      const labels = rows.map(r=>r.status);
      const data = rows.map(r=>Number(r.cnt));
      const ctx = document.getElementById('ordersChart').getContext('2d');
      if(ordersChart) ordersChart.destroy();
      ordersChart = new Chart(ctx, { type:'doughnut', data:{labels, datasets:[{data, backgroundColor:['#f6c',' #6cf','#fc6','#6f6']}]} , options:{responsive:true}});
    });
  }

  function loadTables(){
    fetchJSON('includes/fetch_data.php?action=top_products', function(rows){
      const t = document.getElementById('topProducts'); if(!t) return;
      let html = '<tr><th>Product</th><th>Qty Sold</th></tr>';
      rows.forEach(r=> html += `<tr><td>${r.name}</td><td>${r.total_qty}</td></tr>`);
      t.innerHTML = html;
    });
    fetchJSON('includes/fetch_data.php?action=top_sellers', function(rows){
      const t = document.getElementById('topSellers'); if(!t) return;
      let html = '<tr><th>Seller</th><th>Revenue</th></tr>';
      rows.forEach(r=> html += `<tr><td>${r.seller}</td><td>$${Number(r.revenue).toLocaleString()}</td></tr>`);
      t.innerHTML = html;
    });
    fetchJSON('includes/fetch_data.php?action=low_stock', function(rows){
      const t = document.getElementById('lowStockTable'); if(!t) return;
      let html = '<tr><th>Product</th><th>Stock</th></tr>';
      rows.forEach(r=> html += `<tr><td>${r.name}</td><td>${r.stock}</td></tr>`);
      t.innerHTML = html;
    });
  }

  // initial load
  renderStats(); loadRevenueChart(); loadOrdersChart(); loadTables();

  // live refresh every minute
  setInterval(()=>{ renderStats(); loadRevenueChart(); loadOrdersChart(); }, 60000);

  // ban/unban & mark read handled in assets/js via update_status.php or send_notification.php (functions below)
  window.toggleBan = function(userId, el){
    fetch('includes/update_status.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=toggle_ban&user_id='+encodeURIComponent(userId)}).then(r=>r.json()).then(d=>{
      if(d.success){ el.textContent = d.status === 'banned' ? 'Unban' : 'Ban'; el.dataset.status = d.status; }
    });
  };

  window.markAllRead = function(userId){
    const body = new URLSearchParams(); body.append('action','mark_all_read'); if(userId) body.append('user_id', userId);
    fetch('includes/update_status.php', { method:'POST', body }).then(r=>r.json()).then(()=>{ alert('Marked all as read'); });
  };

  window.sendAdminNotification = function(target, message, link){
    const d = new FormData(); d.append('target', target); d.append('message', message); d.append('link', link);
    fetch('includes/send_notification.php', { method:'POST', body: d }).then(r=>r.json()).then(resp=>{ if(resp.success) alert('Sent'); else alert('Failed'); });
  };

  const orderStatuses = ['pending','confirmed','shipped','delivered','received','cancelled'];
  const orderTableBody = document.querySelector('#ordersTable tbody');
  const usersTableBody = document.querySelector('#usersTable tbody');
  const sellersTableBody = document.querySelector('#sellersTable tbody');

  function formatCurrency(value){
    const num = Number(value || 0);
    return new Intl.NumberFormat(undefined, { style:'currency', currency:'USD' }).format(num);
  }

  function formatDateTime(value){
    if(!value) return '—';
    const d = new Date(value.replace(' ', 'T'));
    if(Number.isNaN(d.getTime())) return value;
    return d.toLocaleString();
  }

  function loadOrders(status = 'all'){
    if(!orderTableBody) return;
    orderTableBody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
    const query = new URLSearchParams({ action:'recent_orders' });
    if(status && status !== 'all') query.append('status', status);
    fetchJSON(`includes/fetch_data.php?${query.toString()}`, function(rows = []){
      if(!rows.length){
        orderTableBody.innerHTML = '<tr><td colspan="7" class="muted">No orders found for this filter.</td></tr>';
        return;
      }
      const html = rows.map(row=>{
        const total = formatCurrency(row.total_price);
        const statusClass = row.status ? `status-${row.status}` : 'status-pending';
        const statusOptions = orderStatuses.map(st => `<option value="${st}" ${st===row.status?'selected':''}>${st.charAt(0).toUpperCase()+st.slice(1)}</option>`).join('');
        return `
          <tr>
            <td>#${row.id}</td>
            <td>
              <div>${row.buyer ?? 'Guest'}</div>
              <small class="muted">${row.buyer_email ?? ''}</small>
            </td>
            <td>${total}</td>
            <td>
              <div class="status-cell">
                <span class="status-pill ${statusClass}">${row.status ?? 'pending'}</span>
                <select class="order-status-select" data-id="${row.id}">
                  ${statusOptions}
                </select>
              </div>
            </td>
            <td>${row.payment_method ? row.payment_method.toUpperCase() : 'N/A'}</td>
            <td>${formatDateTime(row.created_at)}</td>
            <td class="table-actions">
              <a class="ghost-btn" href="order_details.php?id=${row.id}" target="_blank" rel="noopener">View</a>
            </td>
          </tr>
        `;
      }).join('');
      orderTableBody.innerHTML = html;
    });
  }

  function loadUsers(){
    if(!usersTableBody) return;
    usersTableBody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
    fetchJSON('includes/fetch_data.php?action=users_list', function(rows = []){
      if(!rows.length){
        usersTableBody.innerHTML = '<tr><td colspan="6" class="muted">No users found.</td></tr>';
        return;
      }
      usersTableBody.innerHTML = rows.map(user=>{
        const statusText = user.status ?? 'active';
        const isBanned = statusText === 'banned';
        return `
          <tr>
            <td>${user.id}</td>
            <td>${user.fullname || 'Unknown'}</td>
            <td>${user.email ?? ''}</td>
            <td>${(user.role || 'buyer').charAt(0).toUpperCase() + (user.role || 'buyer').slice(1)}</td>
            <td><span class="status-pill ${isBanned ? 'status-cancelled':'status-confirmed'}">${statusText}</span></td>
            <td>
              <button class="ghost-btn ban-toggle" data-user="${user.id}" data-status="${statusText}">
                ${isBanned ? 'Unban' : 'Ban'}
              </button>
            </td>
          </tr>
        `;
      }).join('');
    });
  }

  function loadSellers(){
    if(!sellersTableBody) return;
    sellersTableBody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
    fetchJSON('includes/fetch_data.php?action=seller_overview', function(rows = []){
      if(!rows.length){
        sellersTableBody.innerHTML = '<tr><td colspan="5" class="muted">No seller data found.</td></tr>';
        return;
      }
      sellersTableBody.innerHTML = rows.map(seller=>{
        const statusLabel = seller.is_active_seller ? 'Active' : 'Inactive';
        const statusClass = seller.is_active_seller ? 'status-confirmed' : 'status-pending';
        return `
          <tr>
            <td>
              <div>${seller.seller_name}</div>
              <small class="muted">${seller.email ?? ''}</small>
            </td>
            <td>${seller.total_orders}</td>
            <td>${formatCurrency(seller.revenue)}</td>
            <td>${seller.rating ?? '—'}</td>
            <td><span class="status-pill ${statusClass}">${statusLabel}</span></td>
          </tr>
        `;
      }).join('');
    });
  }

  function refreshNotifCount(){
    const notifBadge = document.getElementById('notifCount');
    if(!notifBadge) return;
    fetchJSON('includes/fetch_data.php?action=unread_count', function(data = {}){
      notifBadge.textContent = data.count ?? 0;
    });
  }

  const orderFilter = document.getElementById('orderStatusFilter');
  if(orderFilter){
    orderFilter.addEventListener('change', ()=> loadOrders(orderFilter.value));
  }
  const refreshOrdersBtn = document.getElementById('refreshOrdersBtn');
  refreshOrdersBtn && refreshOrdersBtn.addEventListener('click', ()=> loadOrders(orderFilter ? orderFilter.value : 'all'));
  const refreshUsersBtn = document.getElementById('refreshUsersBtn');
  refreshUsersBtn && refreshUsersBtn.addEventListener('click', loadUsers);
  const refreshSellersBtn = document.getElementById('refreshSellersBtn');
  refreshSellersBtn && refreshSellersBtn.addEventListener('click', loadSellers);

  if(orderTableBody){
    orderTableBody.addEventListener('change', function(e){
      const select = e.target.closest('.order-status-select');
      if(!select) return;
      const orderId = select.dataset.id;
      const newStatus = select.value;
      updateOrderStatus(orderId, newStatus, select);
    });
  }

  if(usersTableBody){
    usersTableBody.addEventListener('click', function(e){
      const btn = e.target.closest('.ban-toggle');
      if(!btn) return;
      const userId = btn.dataset.user;
      window.toggleBan(userId, btn);
    });
  }

  function updateOrderStatus(orderId, status, selectEl){
    if(!orderId || !status) return;
    selectEl.disabled = true;
    const body = new URLSearchParams();
    body.append('action','update_order_status');
    body.append('order_id', orderId);
    body.append('status', status);
    fetch('includes/update_status.php', { method:'POST', body })
      .then(r=>r.json())
      .then(resp=>{
        if(resp && resp.success){
          loadOrders(orderFilter ? orderFilter.value : 'all');
        } else {
          alert(resp.error || 'Failed to update order status.');
        }
      })
      .catch(()=> alert('Failed to update order status.'))
      .finally(()=>{ selectEl.disabled = false; });
  }

  window.toggleBan = function(userId, el){
    fetch('includes/update_status.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=toggle_ban&user_id='+encodeURIComponent(userId)}).then(r=>r.json()).then(d=>{
      if(d.success){
        loadUsers();
      } else if(d.error){
        alert(d.error);
      }
    });
  };

  loadOrders(orderFilter ? orderFilter.value : 'all');
  loadUsers();
  loadSellers();
  refreshNotifCount();
  setInterval(refreshNotifCount, 15000);

});
