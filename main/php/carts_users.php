<?php
session_start();

// If user not logged in, redirect to login
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

// Handle theme preference
if (isset($_GET['theme'])) {
    $new_theme = in_array($_GET['theme'], ['light', 'dark', 'device']) ? $_GET['theme'] : 'device';
    $_SESSION['theme'] = $new_theme;
} else {
    $theme = $_SESSION['theme'] ?? 'device'; // Default to 'device' if no theme is set
}

// Determine the effective theme for rendering
$effective_theme = $theme;
if ($theme === 'device') {
    $effective_theme = 'dark'; // Fallback; client-side JS will override based on prefers-color-scheme
}

$user_id = $_SESSION["user_id"];

// Handle cart operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"] ?? "";
    $product_id = $_POST["product_id"] ?? 0;
    $quantity = $_POST["quantity"] ?? 1;
    
    switch ($action) {
        case "update_quantity":
            if ($quantity > 0) {
                $sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $quantity, $user_id, $product_id);
                $stmt->execute();
            }
            break;
            
        case "remove_item":
            $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            break;
            
        case "remove_selected":
            if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
                $placeholders = str_repeat('?,', count($_POST['selected_items']) - 1) . '?';
                $sql = "DELETE FROM cart WHERE user_id = ? AND product_id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $types = str_repeat('i', count($_POST['selected_items']) + 1);
                $params = array_merge([$user_id], $_POST['selected_items']);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
            }
            break;
            
        case "clear_cart":
            $sql = "DELETE FROM cart WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            break;
    }
    
    // Redirect to prevent form resubmission
    header("Location: carts_users.php");
    exit();
}

// Fetch cart items with product details
$sql = "SELECT c.*, p.name, p.price, p.image, p.description 
        FROM cart c 
        LEFT JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}
$tax = $subtotal * 0.08; // 8% tax
$total = $subtotal + $tax;

// Fetch notification count
$notif_count = 0;
$notif_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_result->num_rows > 0) {
    $notif_data = $notif_result->fetch_assoc();
    $notif_count = $notif_data['count'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/carts_users.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --background: #fff;
            --text-color: #333;
            --primary-color: #00ff88;
            --secondary-bg: #f8f9fa;
            --border-color: #dee2e6;
            --theme-menu: black;
            --theme-btn: black;
        }

        [data-theme="dark"] {
            --background: #000000ff;
            --text-color: #e0e0e0;
            --primary-color: #00ff88;
            --secondary-bg: #2a2a2a;
            --border-color: #444;
            --theme-menu: white;
            --theme-btn: white;
        }

        body {
            background: var(--background);
            color: var(--text-color);
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--background);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-left .logo {
            height: 40px;
        }

        .nav-left h2 {
            margin: 0;
            font-size: 24px;
            color: var(--text-color);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .theme-dropdown {
            position: relative;
            display: inline-block;
        }

        .theme-btn {
            appearance: none;
            background: var(--theme-btn);
            color: var(--secondary-bg);
            border: 2px solid #006400;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            min-width: 120px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .theme-btn:hover {
            background: #006400;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,100,0,0.2);
        }

        .theme-dropdown:after {
            content: '\25BC';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--secondary-bg);
        }

        .theme-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: var(--theme-menu);
            border: 2px solid rgba(0,255,136,0.3);
            border-radius: 12px;
            padding: 8px;
            min-width: 90px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            display: none;
            z-index: 1000;
        }

        .theme-dropdown.active .theme-menu {
            display: block;
        }

        .theme-option {
            width: 100%;
            padding: 10px 12px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            text-align: left;
            font-weight: 600;
            color: #ceccccff;
        }

        [data-theme="dark"] .theme-option {
            color: #3c3c3cff;
        }

        .theme-option:hover {
            background: rgba(0,255,136,0.08);
            color: #00aa55;
        }

        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .nonuser-text {
            font-size: 16px;
            color: var(--text-color);
            text-decoration: none;
        }

        .hamburger {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-color);
        }

        .menu {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            background: var(--background);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            list-style: none;
            margin: 0;
            z-index: 1000;
        }

        .menu.show {
            display: block;
        }

        .menu li {
            margin: 10px 0;
        }

        .menu li a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 16px;
        }

        .menu li a:hover {
            color: #27ed15;
        }

        /* Checkbox Styles */
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .select-all-checkbox {
            transform: scale(1.2);
            margin-right: 10px;
            cursor: pointer;
        }

        .item-checkbox {
            transform: scale(1.2);
            margin-right: 15px;
            cursor: pointer;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 15px;
            background: var(--secondary-bg);
        }

        .selection-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--secondary-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .checkout-btn-main {
            background: #00ff88;
            color: #000;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            min-width: 160px;
            width: 100%;
        }

        .checkout-btn-main:hover:not(:disabled) {
            background: #00cc6a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,255,136,0.3);
        }

        .checkout-btn-main:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .remove-selected-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .remove-selected-btn:hover:not(:disabled) {
            background: #c82333;
        }

        .remove-selected-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .summary-dynamic {
            background: var(--secondary-bg);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }

        .summary-dynamic .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .checkout-message {
            color: #ff6b6b;
            font-weight: 600;
            margin-top: 10px;
            display: none;
            text-align: center;
        }

        /* New Layout Styles */
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            align-items: start;
        }

        .cart-items {
            grid-column: 1;
        }

        .order-summary-side {
            grid-column: 2;
            position: sticky;
            top: 100px;
            background: var(--secondary-bg);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .order-summary-side h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.5em;
            color: var(--text-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
        }

        .summary-label {
            font-weight: 500;
            color: var(--text-color);
        }

        .summary-value {
            font-weight: 600;
            color: var(--text-color);
        }

        .summary-total {
            border-top: 2px solid var(--border-color);
            padding-top: 12px;
            margin-top: 12px;
            font-size: 1.1em;
        }

        .confirm-order-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 15px;
        }

        .confirm-order-btn:hover:not(:disabled) {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.3);
        }

        .confirm-order-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        /* Popup Styles */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background: var(--background);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .popup-title {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }

        .close-popup {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-color);
        }

        .close-popup:hover {
            color: #dc3545;
        }

        .json-display {
    background: var(--secondary-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: var(--text-color);
}
.json-display ul {
    list-style: none;
    padding: 0;
    margin: 0 0 15px 0;
}
.json-display li {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}
.json-display h4 {
    margin: 0 0 10px 0;
    color: var(--text-primary);
}
.json-display .summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}
.json-display .summary-total {
    border-top: 2px solid var(--border-color);
    padding-top: 8px;
    font-weight: bold;
}

        .popup-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .popup-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .popup-btn.confirm {
            background: #28a745;
            color: white;
        }

        .popup-btn.confirm:hover {
            background: #218838;
        }

        .popup-btn.cancel {
            background: #6c757d;
            color: white;
        }

        .popup-btn.cancel:hover {
            background: #545b62;
        }

        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
            
            .order-summary-side {
                position: static;
                grid-column: 1;
            }
        }


    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle functionality
            const themeDropdown = document.getElementById('themeDropdown');
            const themeMenu = document.getElementById('themeMenu');
            const themeBtn = document.getElementById('themeDropdownBtn');
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            let currentTheme = '<?php echo htmlspecialchars($theme); ?>';

            // Initialize theme
            applyTheme(currentTheme);

            // Apply theme based on selection or system preference
            function applyTheme(theme) {
                let effectiveTheme = theme;
                if (theme === 'device') {
                    effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', effectiveTheme);
                updateTheme(theme, effectiveTheme);
                
                // Save theme to server
                fetch(`?theme=${theme}`, { method: 'GET' })
                    .catch(error => console.error('Error saving theme:', error));
            }

            // Update theme button UI
            function updateTheme(theme, effectiveTheme) {
                if (themeIcon && themeText) {
                    if (theme === 'device') {
                        themeIcon.className = 'bi theme-icon bi-laptop';
                        themeText.textContent = 'Device';
                    } else if (theme === 'dark') {
                        themeIcon.className = 'bi theme-icon bi-moon-fill';
                        themeText.textContent = 'Dark';
                    } else {
                        themeIcon.className = 'bi theme-icon bi-sun-fill';
                        themeText.textContent = 'Light';
                    }
                }
            }

            // Theme dropdown toggle
            if (themeBtn && themeDropdown) {
                themeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    themeDropdown.classList.toggle('active');
                });
            }

            // Theme option selection
            if (themeMenu) {
                themeMenu.addEventListener('click', (e) => {
                    const option = e.target.closest('.theme-option');
                    if (!option) return;
                    currentTheme = option.dataset.theme;
                    applyTheme(currentTheme);
                    themeDropdown.classList.remove('active');
                });
            }

            // Close theme menu when clicking outside
            document.addEventListener('click', (e) => {
                if (themeDropdown && !themeDropdown.contains(e.target)) {
                    themeDropdown.classList.remove('active');
                }
            });

            // Listen for system theme changes when 'device' is selected
            if (currentTheme === 'device') {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                mediaQuery.addEventListener('change', (e) => {
                    if (currentTheme === 'device') {
                        applyTheme('device');
                    }
                });
            }

            // Toggle Hamburger Menu
            const hamburger = document.querySelector('.hamburger');
            const menu = document.getElementById('menu');
            if (hamburger && menu) {
                hamburger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    menu.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!hamburger.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });

                const menuItems = menu.querySelectorAll('a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        menu.classList.remove('show');
                    });
                });
            }

            // Checkbox functionality
            const selectAllCheckbox = document.getElementById('selectAll');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const removeSelectedBtn = document.getElementById('removeSelected');
            const confirmOrderBtn = document.getElementById('confirmOrderBtn');
            const selectedItemsForm = document.getElementById('selectedItemsForm');
            const checkoutMessage = document.getElementById('checkoutMessage');

            // Popup elements
            const popupOverlay = document.getElementById('popupOverlay');
            const closePopup = document.getElementById('closePopup');
            const jsonDisplay = document.getElementById('jsonDisplay');
            const confirmPopupBtn = document.getElementById('confirmPopupBtn');
            const cancelPopupBtn = document.getElementById('cancelPopupBtn');

            // Select All functionality
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateSelectionState();
                });
            }

            // Individual checkbox functionality
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                    updateSelectionState();
                });
            });

            // Update Select All checkbox state
            function updateSelectAllState() {
                if (selectAllCheckbox) {
                    const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(itemCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            }

            // Update button states and selection summary
            function updateSelectionState() {
                const selectedItems = Array.from(itemCheckboxes).filter(cb => cb.checked);
                const hasSelected = selectedItems.length > 0;

                // Update button states
                if (checkoutBtn) {
                    checkoutBtn.disabled = !hasSelected;
                }
                if (removeSelectedBtn) {
                    removeSelectedBtn.disabled = !hasSelected;
                }
                if (confirmOrderBtn) {
                    confirmOrderBtn.disabled = !hasSelected;
                }

                // Hide/show checkout message
                if (checkoutMessage) {
                    checkoutMessage.style.display = hasSelected ? 'none' : 'block';
                }

                // Update selection summary
                updateSelectionSummary(selectedItems);
            }

            // Update selection summary with calculated totals
            function updateSelectionSummary(selectedItems) {
                const summaryElement = document.getElementById('selectionSummary');
                if (!summaryElement) return;

                let selectedSubtotal = 0;
                let selectedItemsCount = 0;
                const selectedItemsData = [];

                selectedItems.forEach(checkbox => {
                    const itemElement = checkbox.closest('.cart-item');
                    const priceElement = itemElement.querySelector('.item-price');
                    const quantityInput = itemElement.querySelector('.quantity-input');
                    const nameElement = itemElement.querySelector('.item-name');
                    
                    if (priceElement && quantityInput && nameElement) {
                        const price = parseFloat(priceElement.textContent.replace('$', '').replace(/,/g, ''));
                        const quantity = parseInt(quantityInput.value);
                        const name = nameElement.textContent;
                        selectedSubtotal += price * quantity;
                        selectedItemsCount += quantity;
                        
                        selectedItemsData.push({
                            product_id: checkbox.value,
                            name: name,
                            price: price,
                            quantity: quantity,
                            subtotal: price * quantity
                        });
                    }
                });

                const selectedTax = selectedSubtotal * 0.08;
                const selectedTotal = selectedSubtotal + selectedTax;

                if (selectedItems.length > 0) {
                    summaryElement.innerHTML = `
                        <div class="summary-row">
                            <span class="summary-label">Selected Items (${selectedItemsCount}):</span>
                            <span class="summary-value">$${selectedSubtotal.toLocaleString()}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tax (8%):</span>
                            <span class="summary-value">$${selectedTax.toLocaleString()}</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span class="summary-label">Total:</span>
                            <span class="summary-value">$${selectedTotal.toLocaleString()}</span>
                        </div>
                    `;
                    summaryElement.style.display = 'block';
                } else {
                    summaryElement.style.display = 'none';
                }

                // Store selected items data for JSON display
                window.selectedOrderData = {
                    items: selectedItemsData,
                    summary: {
                        subtotal: selectedSubtotal,
                        tax: selectedTax,
                        total: selectedTotal,
                        item_count: selectedItemsCount
                    },
                    timestamp: new Date().toISOString(),
                    user_id: <?php echo $user_id; ?>
                };
            }

            // Initialize selection state
            updateSelectionState();

            // Checkout selected items
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    const selectedIds = Array.from(itemCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    
                    if (selectedIds.length > 0) {
                        // Create a form to submit selected items
                        const form = document.createElement('form');
                        form.method = 'GET';
                        form.action = 'checkout_users.php';
                        
                        selectedIds.forEach(id => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'selected_items[]';
                            input.value = id;
                            form.appendChild(input);
                        });
                        
                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        // Show message if no items selected
                        if (checkoutMessage) {
                            checkoutMessage.style.display = 'block';
                        }
                    }
                });
            }

            // Remove selected items confirmation
            if (removeSelectedBtn) {
                removeSelectedBtn.addEventListener('click', function() {
                    const selectedCount = Array.from(itemCheckboxes).filter(cb => cb.checked).length;
                    if (selectedCount > 0 && confirm(`Remove ${selectedCount} selected item(s) from cart?`)) {
                        // Populate the form with selected items
                        const selectedIds = Array.from(itemCheckboxes)
                            .filter(cb => cb.checked)
                            .map(cb => cb.value);
                        
                        // Clear existing hidden inputs
                        const existingInputs = selectedItemsForm.querySelectorAll('input[name="selected_items[]"]');
                        existingInputs.forEach(input => input.remove());
                        
                        // Add new hidden inputs for selected items
                        selectedIds.forEach(id => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'selected_items[]';
                            input.value = id;
                            selectedItemsForm.appendChild(input);
                        });
                        
                        selectedItemsForm.submit();
                    }
                });
            }

            // Confirm Order Button - Show JSON Popup
            if (confirmOrderBtn) {
    confirmOrderBtn.addEventListener('click', function() {
        if (window.selectedOrderData && window.selectedOrderData.items.length > 0) {
            // Create a user-friendly summary
            let summaryHtml = '<h4>Order Details</h4><ul>';
            window.selectedOrderData.items.forEach(item => {
                summaryHtml += `
                    <li>
                        <strong>${item.name}</strong><br>
                        Quantity: ${item.quantity}<br>
                        Price: $${item.price.toLocaleString()}<br>
                        Subtotal: $${item.subtotal.toLocaleString()}
                    </li>`;
            });
            summaryHtml += '</ul>';
            summaryHtml += `
                <div class="summary-row">
                    <strong>Subtotal:</strong> $${window.selectedOrderData.summary.subtotal.toLocaleString()}
                </div>
                <div class="summary-row">
                    <strong>Tax (8%):</strong> $${window.selectedOrderData.summary.tax.toLocaleString()}
                </div>
                <div class="summary-row summary-total">
                    <strong>Total:</strong> $${window.selectedOrderData.summary.total.toLocaleString()}
                </div>
                <div class="summary-row">
                    <strong>Items:</strong> ${window.selectedOrderData.summary.item_count}
                </div>
                <div class="summary-row">
                    <strong>Order Time:</strong> ${new Date(window.selectedOrderData.timestamp).toLocaleString()}
                </div>`;

            // Update jsonDisplay with formatted HTML
            jsonDisplay.innerHTML = summaryHtml;
            // Show popup
            popupOverlay.style.display = 'flex';
        }
    });
}

            // Popup functionality
            if (closePopup) {
                closePopup.addEventListener('click', function() {
                    popupOverlay.style.display = 'none';
                });
            }

            if (cancelPopupBtn) {
                cancelPopupBtn.addEventListener('click', function() {
                    popupOverlay.style.display = 'none';
                });
            }

            if (confirmPopupBtn) {
                confirmPopupBtn.addEventListener('click', function() {
                    // Here you would typically send the order data to your server
                    alert('Order confirmed! Processing your order...');
                    popupOverlay.style.display = 'none';
                    
                    // Redirect to checkout or process order
                    const selectedIds = Array.from(itemCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    
                    if (selectedIds.length > 0) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'process_order.php';
                        
                        // Add order data
                        const orderInput = document.createElement('input');
                        orderInput.type = 'hidden';
                        orderInput.name = 'order_data';
                        orderInput.value = JSON.stringify(window.selectedOrderData);
                        form.appendChild(orderInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // Close popup when clicking outside
            if (popupOverlay) {
                popupOverlay.addEventListener('click', function(e) {
                    if (e.target === popupOverlay) {
                        popupOverlay.style.display = 'none';
                    }
                });
            }
        });
    </script>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <!-- Theme dropdown -->
            <div class="theme-dropdown" id="themeDropdown">
                <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme">
                    <i class="bi theme-icon" id="themeIcon"></i>
                    <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($theme === 'light' ? 'Light' : 'Dark'); ?></span>
                </button>
                <div class="theme-menu" id="themeMenu" aria-hidden="true">
                    <button class="theme-option" data-theme="light">Light</button>
                    <button class="theme-option" data-theme="dark">Dark</button>
                    <button class="theme-option" data-theme="device">Device</button>     
                </div>
            </div>
            <a href="notifications.php" title="Notifications" style="text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
                <i class="bi bi-bell" style="font-size:18px;"></i>
                <span><?php echo $notif_count > 0 ? "($notif_count)" : ""; ?></span>
            </a>
            <?php
            $user_role = $_SESSION['role'] ?? 'buyer';
            $profile_page = ($user_role === 'seller' || $user_role === 'admin') ? 'seller_profile.php' : 'profile.php';
            $profile_query = "SELECT profile_image FROM users WHERE id = ?";
            $profile_stmt = $conn->prepare($profile_query);
            $profile_stmt->bind_param("i", $user_id);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            $current_profile = $profile_result->fetch_assoc();
            $current_profile_image = $current_profile['profile_image'] ?? null;
            ?>
            <a href="<?php echo $profile_page; ?>">
                <?php if (!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                    <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="Uploads/logo1.png" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
            <button class="hamburger">☰</button>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <?php if ($user_role === 'seller' || $user_role === 'admin'): ?>
                <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
            <?php else: ?>
                <li><a href="become_seller.php">Become Seller</a></li>
            <?php endif; ?>
            
            <li><a href="<?php echo $profile_page; ?>">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Cart Container -->
    <div class="cart-container">
        <div class="cart-header">
            <h1 class="cart-title">Shopping Cart</h1>
            <p class="cart-subtitle"><?php echo $total_items; ?> item(s) in your cart</p>
        </div>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="shop-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <!-- Selection Controls -->
            <div class="selection-controls">
                <div class="checkbox-container">
                    <input type="checkbox" id="selectAll" class="select-all-checkbox">
                    <label for="selectAll">Select All</label>
                </div>
                
                <form method="POST" id="selectedItemsForm" style="display: inline;">
                    
                    <!-- Selected items will be populated by JavaScript -->
                </form>
            </div>

            <!-- Selected Items Summary -->
            <div class="summary-dynamic" id="selectionSummary" style="display: none;">
                <!-- Dynamic content will be inserted by JavaScript -->
            </div>

            <!-- Cart Content -->
            <div class="cart-content">
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <input type="checkbox" 
                                   class="item-checkbox" 
                                   value="<?php echo $item['product_id']; ?>" 
                                   name="selected_items[]">
                            
                            <img src="<?php echo $item['image'] ?: 'https://picsum.photos/100/100'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="item-image">
                            
                            <div class="item-details">
                                <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="item-price">$<?php echo number_format($item['price']); ?></p>
                            </div>
                            
                            <div class="item-controls">
                                <div class="quantity-controls">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_quantity">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" 
                                                class="quantity-btn" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                    </form>
                                    
                                    <input type="number" value="<?php echo $item['quantity']; ?>" 
                                           class="quantity-input" readonly>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_quantity">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" 
                                                class="quantity-btn">+</button>
                                    </form>
                                </div>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" class="remove-btn" 
                                            onclick="return confirm('Remove this item from cart?')">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Order Summary Sidebar -->
                <div class="order-summary-side">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span class="summary-label">Subtotal (<?php echo $total_items; ?> items):</span>
                        <span class="summary-value">$<?php echo number_format($subtotal); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Tax (8%):</span>
                        <span class="summary-value">$<?php echo number_format($tax); ?></span>
                    </div>
                    
                    <div class="summary-row summary-total">
                        <span class="summary-label">Total:</span>
                        <span class="summary-value">$<?php echo number_format($total); ?></span>
                    </div>

                    <!-- Checkout Section -->
                    <button type="button" id="checkoutBtn" class="checkout-btn-main" disabled>
                        Proceed to Checkout
                    </button>
                    
                    <!-- Confirm Order Button -->
                    <button type="button" id="confirmOrderBtn" class="confirm-order-btn" disabled>
                        Confirm Order
                    </button>

                    <div class="checkout-message" id="checkoutMessage">
                        Please select at least one product to checkout
                    </div>

                    <a href="orders.php" class="checkout-btn" style="background:#333; margin-top:8px; display:block; text-align:center; padding:10px; text-decoration:none; border-radius:8px; color:white;">
                        My Orders
                    </a>
                    
                    <form method="POST" style="margin-top:10px;">
                        <input type="hidden" name="action" value="clear_cart">
                        <button type="submit" class="clear-cart-btn" 
                                onclick="return confirm('Clear entire cart? This action cannot be undone.')" style="width:100%; padding:10px; background:#dc3545; color:white; border:none; border-radius:8px; cursor:pointer;">
                            Clear Cart
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JSON Popup -->
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-content">
            <div class="popup-header">
                <h3 class="popup-title">Order Confirmation</h3>
                <button class="close-popup" id="closePopup">&times;</button>
            </div>
            <p>Please review your order details:</p>
            <div class="json-display" id="jsonDisplay"></div>
            <div class="popup-actions">
                <button class="popup-btn cancel" id="cancelPopupBtn">Cancel</button>
                <button class="popup-btn confirm" id="confirmPopupBtn">Confirm Order</button>
            </div>
        </div>
    </div>
</body>
</html>