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

});
