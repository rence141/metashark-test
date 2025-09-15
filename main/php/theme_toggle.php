<?php
// Theme toggle component
// This file can be included in other PHP files to add theme functionality

// Check if theme preference is set in session, default to dark
$theme = $_SESSION['theme'] ?? 'dark';

// Handle theme toggle
if (isset($_GET['theme'])) {
    $new_theme = $_GET['theme'] === 'light' ? 'light' : 'dark';
    $_SESSION['theme'] = $new_theme;
    $theme = $new_theme;
}
?>

<!-- Theme Toggle Component -->
<div class="theme-toggle" id="themeToggle">
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle Theme">
        <span class="theme-icon" id="themeIcon">
            <?php echo $theme === 'light' ? '🌙' : '☀️'; ?>
        </span>
        <span class="theme-text" id="themeText">
            <?php echo $theme === 'light' ? 'Dark' : 'Light'; ?>
        </span>
    </button>
</div>

<style>
/* Theme Toggle Styles */
.theme-toggle {
    display: inline-flex;
    margin-left: 15px;
}

.theme-btn {
    background: var(--theme-toggle-bg);
    color: var(--theme-toggle-text);
    border: 2px solid var(--theme-toggle-border);
    border-radius: 25px;
    padding: 8px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px var(--theme-shadow);
}

.theme-btn:hover {
    background: var(--theme-toggle-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px var(--theme-shadow);
}

.theme-icon {
    font-size: 16px;
    transition: transform 0.3s ease;
}

.theme-btn:hover .theme-icon {
    transform: rotate(180deg);
}

.theme-text {
    font-family: 'ASUS ROG', Arial, sans-serif;
}

/* Theme Variables */
:root {
    /* Light Theme */
    --light-bg-primary: #ffffff;
    --light-bg-secondary: #f8f9fa;
    --light-bg-tertiary: #e9ecef;
    --light-text-primary: #212529;
    --light-text-secondary: #6c757d;
    --light-text-muted: #adb5bd;
    --light-border: #dee2e6;
    --light-border-light: #e9ecef;
    --light-accent: #44D62C;
    --light-accent-hover: #36b020;
    --light-accent-light: #d4edda;
    --light-shadow: rgba(0, 0, 0, 0.1);
    --light-shadow-hover: rgba(0, 0, 0, 0.15);
    
    /* Dark Theme */
    --dark-bg-primary: #0A0A0A;
    --dark-bg-secondary: #111111;
    --dark-bg-tertiary: #1a1a1a;
    --dark-text-primary: #ffffff;
    --dark-text-secondary: #cccccc;
    --dark-text-muted: #888888;
    --dark-border: #333333;
    --dark-border-light: #444444;
    --dark-accent: #44D62C;
    --dark-accent-hover: #36b020;
    --dark-accent-light: #2a5a1a;
    --dark-shadow: rgba(0, 0, 0, 0.3);
    --dark-shadow-hover: rgba(0, 0, 0, 0.4);
    
    /* Theme Toggle Colors */
    --theme-toggle-bg: var(--bg-secondary);
    --theme-toggle-text: var(--text-primary);
    --theme-toggle-border: var(--accent);
    --theme-toggle-hover: var(--accent);
}

/* Light Theme */
[data-theme="light"] {
    --bg-primary: var(--light-bg-primary);
    --bg-secondary: var(--light-bg-secondary);
    --bg-tertiary: var(--light-bg-tertiary);
    --text-primary: var(--light-text-primary);
    --text-secondary: var(--light-text-secondary);
    --text-muted: var(--light-text-muted);
    --border: var(--light-border);
    --border-light: var(--light-border-light);
    --accent: var(--light-accent);
    --accent-hover: var(--light-accent-hover);
    --accent-light: var(--light-accent-light);
    --shadow: var(--light-shadow);
    --shadow-hover: var(--light-shadow-hover);
}

/* Dark Theme */
[data-theme="dark"] {
    --bg-primary: var(--dark-bg-primary);
    --bg-secondary: var(--dark-bg-secondary);
    --bg-tertiary: var(--dark-bg-tertiary);
    --text-primary: var(--dark-text-primary);
    --text-secondary: var(--dark-text-secondary);
    --text-muted: var(--dark-text-muted);
    --border: var(--dark-border);
    --border-light: var(--dark-border-light);
    --accent: var(--dark-accent);
    --accent-hover: var(--dark-accent-hover);
    --accent-light: var(--dark-accent-light);
    --shadow: var(--dark-shadow);
    --shadow-hover: var(--dark-shadow-hover);
}

/* Apply theme to body */
body {
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
}
</style>

<script>
// Theme Toggle JavaScript
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    // Update theme
    document.documentElement.setAttribute('data-theme', newTheme);
    
    // Update theme toggle button
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (newTheme === 'light') {
        themeIcon.textContent = '🌙';
        themeText.textContent = 'Dark';
    } else {
        themeIcon.textContent = '☀️';
        themeText.textContent = 'Light';
    }
    
    // Save theme preference
    fetch('?theme=' + newTheme, { method: 'GET' })
        .then(() => {
            // Theme saved successfully
        })
        .catch(error => {
            console.error('Error saving theme:', error);
        });
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = '<?php echo $theme; ?>';
    document.documentElement.setAttribute('data-theme', savedTheme);
});
</script>
