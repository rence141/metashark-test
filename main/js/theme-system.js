/*class ThemeSystem {  Wala nato kwenta kanina pa akong edit nito hays
    constructor() {
        this.themes = {
            light: 'light',
            dark: 'dark',
            device: 'device'
        };
        this.currentTheme = this.getStoredTheme() || this.themes.device;
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme);
        this.createThemeToggle();
        this.watchSystemTheme();
    }

    getStoredTheme() {
        return localStorage.getItem('theme');
    }

    setStoredTheme(theme) {
        localStorage.setItem('theme', theme);
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        const body = document.body;
        const root = document.documentElement;
        
        // Remove existing theme classes
        body.classList.remove('theme-light', 'theme-dark', 'theme-device');
        
        // Apply new theme
        if (theme === 'device') {
            const systemTheme = this.getSystemTheme();
            body.classList.add(`theme-${systemTheme}`);
            body.setAttribute('data-theme', systemTheme);
        } else {
            body.classList.add(`theme-${theme}`);
            body.setAttribute('data-theme', theme);
        }

        this.currentTheme = theme;
        this.setStoredTheme(theme);
        this.updateToggleUI();
    }

    createThemeToggle() {
        // Check if toggle already exists
        if (document.querySelector('.theme-toggle')) return;

        const toggle = document.createElement('div');
        toggle.className = 'theme-toggle';
        toggle.innerHTML = `
            <button class="theme-toggle-btn" title="Toggle Theme">
                <span class="theme-icon">
                    <i class="bi bi-laptop" data-theme="device"></i>
                    <i class="bi bi-sun" data-theme="light" style="display: none;"></i>
                    <i class="bi bi-moon" data-theme="dark" style="display: none;"></i>
                </span>
                <span class="theme-label">Device</span>
            </button>
            <div class="theme-menu">
                <button class="theme-option" data-theme="light">
                    <i class="bi bi-sun"></i>
                    <span>Light</span>
                </button>
                <button class="theme-option" data-theme="dark">
                    <i class="bi bi-moon"></i>
                    <span>Dark</span>
                </button>
                <button class="theme-option" data-theme="device">
                    <i class="bi bi-laptop"></i>
                    <span>Device</span>
                </button>
            </div>
        `;

        // Add to page
        document.body.appendChild(toggle);

        // Add event listeners
        toggle.querySelector('.theme-toggle-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            toggle.classList.toggle('active');
        });

        toggle.querySelectorAll('.theme-option').forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const theme = option.dataset.theme;
                this.applyTheme(theme);
                toggle.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!toggle.contains(e.target)) {
                toggle.classList.remove('active');
            }
        });
    }

    updateToggleUI() {
        const toggle = document.querySelector('.theme-toggle');
        if (!toggle) return;

        const themeLabel = toggle.querySelector('.theme-label');
        const allIcons = toggle.querySelectorAll('.theme-icon i');
        const activeIcon = toggle.querySelector(`.theme-icon i[data-theme="${this.currentTheme}"]`);
        
        // Update icon
        allIcons.forEach(icon => icon.style.display = 'none');
        if (activeIcon) activeIcon.style.display = 'block';

        // Update label
        themeLabel.textContent = this.currentTheme.charAt(0).toUpperCase() + this.currentTheme.slice(1);

        // Update active state in menu
        toggle.querySelectorAll('.theme-option').forEach(option => {
            option.classList.toggle('active', option.dataset.theme === this.currentTheme);
        });
    }

    watchSystemTheme() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addListener(() => {
            if (this.currentTheme === 'device') {
                this.applyTheme('device');
            }
        });
    }

    setTheme(theme) {
        if (this.themes[theme]) {
            this.applyTheme(theme);
        }
    }

    getEffectiveTheme() {
        if (this.currentTheme === 'device') {
            return this.getSystemTheme();
        }
        return this.currentTheme;
    }
}

// CSS for theme toggle
const themeStyles = `
<style>
.theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.theme-toggle-btn {
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid rgba(0, 255, 136, 0.3);
    border-radius: 12px;
    padding: 12px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.theme-toggle-btn:hover {
    background: rgba(255, 255, 255, 1);
    border-color: rgba(0, 255, 136, 0.6);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.theme-icon {
    position: relative;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.theme-icon i {
    position: absolute;
    font-size: 16px;
    color: #333;
    transition: all 0.3s ease;
}

.theme-label {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.theme-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid rgba(0, 255, 136, 0.3);
    border-radius: 12px;
    padding: 8px;
    min-width: 140px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.theme-toggle.active .theme-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.theme-option {
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #333;
    transition: all 0.2s ease;
    text-align: left;
}

.theme-option:hover {
    background: rgba(0, 255, 136, 0.1);
    color: #00ff88;
}

.theme-option.active {
    background: rgba(0, 255, 136, 0.2);
    color: #00ff88;
    font-weight: 600;
}

.theme-option i {
    width: 16px;
    text-align: center;
}

/* Dark theme styles 
.theme-dark .theme-toggle-btn {
    background: rgba(30, 30, 30, 0.9);
    border-color: rgba(0, 255, 136, 0.3);
    color: #e0e0e0;
}

.theme-dark .theme-toggle-btn:hover {
    background: rgba(30, 30, 30, 1);
    border-color: rgba(0, 255, 136, 0.6);
}

.theme-dark .theme-icon i,
.theme-dark .theme-label {
    color: #e0e0e0;
}

.theme-dark .theme-menu {
    background: rgba(30, 30, 30, 0.95);
    border-color: rgba(0, 255, 136, 0.3);
}

.theme-dark .theme-option {
    color: #e0e0e0;
}

.theme-dark .theme-option:hover {
    background: rgba(0, 255, 136, 0.1);
    color: #00ff88;
}

.theme-dark .theme-option.active {
    background: rgba(0, 255, 136, 0.2);
    color: #00ff88;
}

/* Responsive 
@media (max-width: 480px) {
    .theme-toggle {
        top: 15px;
        right: 15px;
    }
    
    .theme-toggle-btn {
        padding: 10px 12px;
    }
    
    .theme-label {
        font-size: 12px;
    }
    
    .theme-menu {
        right: -10px;
        min-width: 120px;
    }
}
</style>
`;

// Inject styles
document.head.insertAdjacentHTML('beforeend', themeStyles);

// Initialize theme system when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.themeSystem = new ThemeSystem();
});

// Export for use in other scripts
window.ThemeSystem = ThemeSystem*/