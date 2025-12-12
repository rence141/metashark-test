<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default to 'dark' theme if not set
$current_theme = $_SESSION['theme'] ?? 'dark';

// Handle theme change requests
if (isset($_GET['theme'])) {
    $new_theme = $_GET['theme'] === 'light' ? 'light' : 'dark';
    if ($new_theme !== $current_theme) {
        $_SESSION['theme'] = $new_theme;
        $current_theme = $new_theme;

        // If a user is logged in, update their theme preference in the database
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            // Create a temporary database connection
            // Note: This assumes you have a db.php file with a function to get a DB connection.
            // You might need to adjust the path to your actual database connection file.
            require_once 'db.php'; 
            $pdoconn = get_database_connection();
            $stmt = $pdoconn->prepare("UPDATE users SET theme = :theme WHERE id = :user_id");
            $stmt->execute(['theme' => $new_theme, 'user_id' => $user_id]);
        }
    }
    // Prevent the script from outputting anything other than the theme
    if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
        echo $current_theme;
        exit;
    }
}

function render_theme_toggle_button() {
    global $current_theme;
    $icon_class = $current_theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-stars-fill';
    echo '<button id="themeBtn" class="btn-xs btn-outline" style="font-size:16px; border:none; background:transparent; color:var(--text-primary);"><i class="bi ' . $icon_class . '"></i></button>';
}

function get_theme_script() {
    global $current_theme;
    return <<<HTML
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const themeBtn = document.getElementById('themeBtn');
            if (themeBtn) {
                const icon = themeBtn.querySelector('i');

                function applyTheme(theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                    icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
                    
                    // Use fetch to notify the server without a full page reload
                    fetch('admin_theme.php?theme=' + theme)
                        .then(response => response.text())
                        .then(serverTheme => {
                             if (document.documentElement.getAttribute('data-theme') !== serverTheme) {
                                document.documentElement.setAttribute('data-theme', serverTheme);
                             }
                        })
                        .catch(error => console.error('Error updating theme on server:', error));
                }

                themeBtn.addEventListener('click', function () {
                    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    applyTheme(newTheme);
                });
            }
        });
    </script>
HTML;
}

// Set the data-theme attribute on the HTML tag
function apply_theme_html_tag() {
    global $current_theme;
    echo 'data-theme="' . htmlspecialchars($current_theme) . '"';
}
?>
