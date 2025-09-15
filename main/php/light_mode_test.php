<?php
// Light Mode Test Page
session_start();
include('db.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Light Mode Test - Meta Accessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
        <link rel="icon" type="image/png" href="uploads/logo1.png">
    <?php include('theme_toggle.php'); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'ASUS ROG', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5rem;
        }

        .test-section {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 5px 15px var(--shadow);
        }

        .test-section h2 {
            color: var(--accent);
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .test-section p {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .button-demo {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--shadow);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 2px solid var(--accent);
        }

        .btn-secondary:hover {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .btn-danger {
            background: #ff4444;
            color: white;
        }

        .btn-danger:hover {
            background: #cc3333;
            transform: translateY(-2px);
        }

        .card-demo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px var(--shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px var(--shadow-hover);
            border-color: var(--accent);
        }

        .card h3 {
            color: var(--accent);
            margin-bottom: 10px;
        }

        .card p {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .form-demo {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            margin: 20px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--accent);
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 16px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 5px var(--shadow);
        }

        .notification-demo {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 1000;
            transform: translateX(-400px);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px var(--shadow);
        }

        .notification-demo.show {
            transform: translateX(0);
        }

        .notification-demo.success {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        }

        .notification-demo.info {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }

        .notification-demo.error {
            background: linear-gradient(135deg, #ff4444, #cc3333);
        }

        .theme-info {
            background: var(--accent-light);
            border: 2px solid var(--accent);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .theme-info h3 {
            color: var(--accent);
            margin-bottom: 10px;
        }

        .theme-info p {
            color: var(--text-primary);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌓 Light Mode Test Page</h1>
        
        <div class="theme-info">
            <h3>Current Theme: <span id="currentTheme"><?php echo $_SESSION['theme'] ?? 'dark'; ?></span></h3>
            <p>Click the theme toggle button in the top-right corner to switch between light and dark modes!</p>
        </div>

        <div class="test-section">
            <h2>🎨 Color Scheme Test</h2>
            <p>This section demonstrates how the color scheme changes between light and dark modes:</p>
            
            <div class="card-demo">
                <div class="card">
                    <h3>Primary Background</h3>
                    <p>This card uses the primary background color that changes with the theme.</p>
                </div>
                <div class="card">
                    <h3>Secondary Background</h3>
                    <p>This card uses the secondary background color for contrast.</p>
                </div>
                <div class="card">
                    <h3>Tertiary Background</h3>
                    <p>This card uses the tertiary background color for depth.</p>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>🔘 Button Styles</h2>
            <p>All buttons adapt to the current theme:</p>
            
            <div class="button-demo">
                <button class="btn btn-primary">Primary Button</button>
                <button class="btn btn-secondary">Secondary Button</button>
                <button class="btn btn-danger">Danger Button</button>
                <a href="#" class="btn btn-primary">Link Button</a>
            </div>
        </div>

        <div class="test-section">
            <h2>📝 Form Elements</h2>
            <p>Form elements also adapt to the theme:</p>
            
            <div class="form-demo">
                <div class="form-group">
                    <label for="testInput">Text Input</label>
                    <input type="text" id="testInput" placeholder="Enter some text...">
                </div>
                <div class="form-group">
                    <label for="testTextarea">Textarea</label>
                    <textarea id="testTextarea" placeholder="Enter a longer message..."></textarea>
                </div>
                <div class="form-group">
                    <label for="testSelect">Select</label>
                    <select id="testSelect">
                        <option>Option 1</option>
                        <option>Option 2</option>
                        <option>Option 3</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>🔔 Notifications</h2>
            <p>Notification styles adapt to the theme:</p>
            
            <div class="button-demo">
                <button class="btn btn-primary" onclick="showNotification('Success message!', 'success')">Success</button>
                <button class="btn btn-secondary" onclick="showNotification('Info message!', 'info')">Info</button>
                <button class="btn btn-danger" onclick="showNotification('Error message!', 'error')">Error</button>
            </div>
        </div>

        <div class="test-section">
            <h2>📱 Responsive Design</h2>
            <p>The light mode works across all screen sizes and maintains the responsive design:</p>
            
            <div class="card-demo">
                <div class="card">
                    <h3>Mobile</h3>
                    <p>Optimized for mobile devices with touch-friendly interfaces.</p>
                </div>
                <div class="card">
                    <h3>Tablet</h3>
                    <p>Perfect for tablet viewing with adaptive layouts.</p>
                </div>
                <div class="card">
                    <h3>Desktop</h3>
                    <p>Full desktop experience with all features available.</p>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>🚀 Features</h2>
            <p>The light mode includes all these features:</p>
            
            <ul style="color: var(--text-secondary); line-height: 1.8; margin-left: 20px;">
                <li>✅ <strong>Automatic Theme Detection:</strong> Remembers your preference</li>
                <li>✅ <strong>Smooth Transitions:</strong> Animated theme switching</li>
                <li>✅ <strong>Consistent Colors:</strong> All components use theme variables</li>
                <li>✅ <strong>Accessibility:</strong> High contrast in both modes</li>
                <li>✅ <strong>Performance:</strong> CSS variables for fast switching</li>
                <li>✅ <strong>Responsive:</strong> Works on all screen sizes</li>
            </ul>
        </div>

        <div class="test-section">
            <h2>🔗 Navigation</h2>
            <p>Test the light mode on other pages:</p>
            
            <div class="button-demo">
                <a href="shop.php" class="btn btn-primary">Main Shop</a>
                <a href="seller_dashboard.php" class="btn btn-secondary">Seller Dashboard</a>
                <a href="add_product.php" class="btn btn-primary">Add Product</a>
                <a href="carts_users.php" class="btn btn-secondary">Shopping Cart</a>
            </div>
        </div>
    </div>

    <!-- Notification Demo -->
    <div class="notification-demo" id="notificationDemo"></div>

    <script>
        // Update current theme display
        function updateThemeDisplay() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            document.getElementById('currentTheme').textContent = currentTheme;
        }

        // Show notification demo
        function showNotification(message, type) {
            const notification = document.getElementById('notificationDemo');
            notification.textContent = message;
            notification.className = `notification-demo ${type}`;
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Initialize theme display
        document.addEventListener('DOMContentLoaded', function() {
            updateThemeDisplay();
            
            // Listen for theme changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                        updateThemeDisplay();
                    }
                });
            });
            
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme']
            });
        });
    </script>
</body>
</html>
