<?php
// Prevent errors if session isn't started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. GET ADMIN DATA FROM SESSION
// UPDATED: Changed 'fullname' to 'admin_name' to match your Login/Profile scripts
$nav_name = $_SESSION['admin_name'] ?? $_SESSION['fullname'] ?? 'Admin';
$nav_role = $_SESSION['role'] ?? 'Administrator'; 
$nav_initial = strtoupper(substr($nav_name, 0, 1));
$nav_theme = $_SESSION['theme'] ?? 'dark';
?>
<style>
/* Unified Theme Toggle Button Styles */
#themeBtn {
    transition: all 0.2s ease;
    cursor: pointer;
}
#themeBtn:hover {
    background: rgba(68, 214, 44, 0.1) !important;
    color: var(--primary) !important;
    transform: scale(1.1);
}
#themeBtn:active {
    transform: scale(0.95);
}
#themeBtn i {
    transition: transform 0.3s ease;
}
#themeBtn:hover i {
    transform: rotate(15deg);
}
</style>

<nav class="admin-navbar">
    <div class="navbar-left">
        <div class="logo-area">
            <img src="uploads/logo1.png" alt="Meta Shark">
            <span>META SHARK</span>
        </div>
    </div>

    <div style="display:flex; align-items:center; gap:16px;">
        <button id="themeBtn" class="btn-xs btn-outline" style="font-size:16px; border:none; background:transparent; color:var(--text); padding:8px 12px; border-radius:8px; transition:all 0.2s;" title="Toggle Theme">
            <i class="bi <?php echo $nav_theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill'; ?>"></i>
        </button>
        
        <a href="admin_profile.php" class="navbar-profile-link">
            <div class="profile-info-display">
                <div class="profile-name"><?php echo htmlspecialchars($nav_name); ?></div>
                <div class="profile-role" style="color:var(--primary);"><?php echo htmlspecialchars($nav_role); ?></div>
            </div>
            
            <div class="profile-avatar">
                <?php echo $nav_initial; ?>
            </div>
        </a>
        
        <a href="admin_logout.php" class="btn-xs btn-outline" style="color:var(--text-muted); border-color:var(--panel-border);" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</nav>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Mobile Sidebar Logic
    const toggleBtn = document.getElementById('sidebarToggle');
    // Select sidebar safely
    const sidebar = document.querySelector('.admin-sidebar') || document.getElementById('sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); });
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

    // Unified Theme Toggle Logic
    const themeBtn = document.getElementById('themeBtn');
    if (themeBtn) {
        let currentTheme = '<?php echo $nav_theme; ?>';
        
        // Ensure theme is applied on page load
        if (document.documentElement) {
            document.documentElement.setAttribute('data-theme', currentTheme);
        }
        
        // Update icon based on current theme
        function updateThemeIcon(theme) {
            const icon = themeBtn.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            }
        }
        updateThemeIcon(currentTheme);
        
        // Theme toggle click handler
        themeBtn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') || currentTheme;
            const newTheme = current === 'dark' ? 'light' : 'dark';
            
            // Update DOM immediately
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            
            // Save to server
            fetch('theme_toggle.php?theme=' + newTheme)
                .catch(err => console.error('Theme save error:', err));
            
            // Dispatch event for other scripts that might need to react
            window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: newTheme } }));
        });
        
        // Listen for theme changes from other sources
        window.addEventListener('themeChanged', (e) => {
            if (e.detail && e.detail.theme) {
                updateThemeIcon(e.detail.theme);
            }
        });
        
        // Apply theme from localStorage if available (for consistency)
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme && (savedTheme === 'dark' || savedTheme === 'light')) {
            if (document.documentElement) {
                document.documentElement.setAttribute('data-theme', savedTheme);
                updateThemeIcon(savedTheme);
            }
        }
    }
});
</script>