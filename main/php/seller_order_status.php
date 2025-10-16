<?php
session_start();
// Security: Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login_users.php");
    exit();
}
// Check if user is a seller
if ($_SESSION['role'] !== 'seller' && $_SESSION['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}
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
include("db.php");
$user_id = $_SESSION['user_id'];
// Check if tracking_number column exists in order_items table
$check_column_query = "SHOW COLUMNS FROM order_items LIKE 'tracking_number'";
$column_result = $conn->query($check_column_query);
$has_tracking_column = $column_result->num_rows > 0;
$column_result->close();
// Handle order status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $order_item_id = intval($_POST['order_item_id']);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $tracking_number = filter_input(INPUT_POST, 'tracking_number', FILTER_SANITIZE_STRING) ?? '';
    
    // Validate status
    $allowed_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        $status_message = "Invalid status selected.";
        $status_success = false;
    } else {
        $conn->begin_transaction();
        try {
            // Update order item status (with or without tracking number)
            if ($has_tracking_column) {
                $update_query = "UPDATE order_items SET status = ?, tracking_number = ?, updated_at = NOW() WHERE id = ? AND EXISTS (
                    SELECT 1 FROM products p
                    WHERE p.id = order_items.product_id AND p.seller_id = ?
                )";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt) {
                    $update_stmt->bind_param("ssii", $new_status, $tracking_number, $order_item_id, $user_id);
                }
            } else {
                $update_query = "UPDATE order_items SET status = ?, updated_at = NOW() WHERE id = ? AND EXISTS (
                    SELECT 1 FROM products p
                    WHERE p.id = order_items.product_id AND p.seller_id = ?
                )";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt) {
                    $update_stmt->bind_param("sii", $new_status, $order_item_id, $user_id);
                }
            }
            
            if ($update_stmt) {
                $update_stmt->execute();
                
                if ($update_stmt->affected_rows > 0) {
                    // Get order and product details for notification
                    $details_query = "SELECT oi.order_id, oi.product_id, p.name, o.buyer_id
                                    FROM order_items oi
                                    JOIN products p ON oi.product_id = p.id
                                    JOIN orders o ON oi.order_id = o.id
                                    WHERE oi.id = ?";
                    $details_stmt = $conn->prepare($details_query);
                    $details_stmt->bind_param("i", $order_item_id);
                    $details_stmt->execute();
                    $details_result = $details_stmt->get_result();
                    
                    if ($details_row = $details_result->fetch_assoc()) {
                        $buyer_id = $details_row['buyer_id'];
                        $product_name = $details_row['name'];
                        $order_id = $details_row['order_id'];
                        
                        // Create notification for buyer
                        $notification_message = "Order #$order_id - $product_name status updated to: " . ucfirst($new_status);
                        if ($has_tracking_column && !empty($tracking_number)) {
                            $notification_message .= " (Tracking: $tracking_number)";
                        }
                        
                        $notification_type = "order_update";
                        $notification_sql = "INSERT INTO notifications (user_id, message, type, created_at, `read`) VALUES (?, ?, ?, NOW(), 0)";
                        $notif_stmt = $conn->prepare($notification_sql);
                        if ($notif_stmt) {
                            $notif_stmt->bind_param("iss", $buyer_id, $notification_message, $notification_type);
                            $notif_stmt->execute();
                            $notif_stmt->close();
                        }
                    }
                    $details_stmt->close();
                    
                    $conn->commit();
                    $status_message = "Order status updated successfully!";
                    $status_success = true;
                } else {
                    throw new Exception("No order found or you don't have permission to update this order.");
                }
                $update_stmt->close();
            } else {
                throw new Exception("Failed to prepare update statement.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $status_message = "Error updating order: " . $e->getMessage();
            $status_success = false;
            error_log("Order Update Error: " . $e->getMessage());
        }
    }
}
// Handle bulk actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_action']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $bulk_action = $_POST['bulk_action'];
    $selected_orders = $_POST['selected_orders'] ?? [];
    
    if (empty($selected_orders)) {
        $status_message = "No orders selected for bulk action.";
        $status_success = false;
    } else {
        $placeholders = implode(',', array_fill(0, count($selected_orders), '?'));
        $types = str_repeat('i', count($selected_orders));
        
        $conn->begin_transaction();
        try {
            if ($bulk_action === 'confirm') {
                $update_query = "UPDATE order_items SET status = 'confirmed', updated_at = NOW()
                                WHERE id IN ($placeholders) AND EXISTS (
                                    SELECT 1 FROM products p
                                    WHERE p.id = order_items.product_id AND p.seller_id = ?
                                )";
                $update_stmt = $conn->prepare($update_query);
                $params = array_merge($selected_orders, [$user_id]);
                $update_stmt->bind_param($types . 'i', ...$params);
            } elseif ($bulk_action === 'ship') {
                $update_query = "UPDATE order_items SET status = 'shipped', updated_at = NOW()
                                WHERE id IN ($placeholders) AND EXISTS (
                                    SELECT 1 FROM products p
                                    WHERE p.id = order_items.product_id AND p.seller_id = ?
                                )";
                $update_stmt = $conn->prepare($update_query);
                $params = array_merge($selected_orders, [$user_id]);
                $update_stmt->bind_param($types . 'i', ...$params);
            } elseif ($bulk_action === 'cancel') {
                $update_query = "UPDATE order_items SET status = 'cancelled', updated_at = NOW()
                                WHERE id IN ($placeholders) AND EXISTS (
                                    SELECT 1 FROM products p
                                    WHERE p.id = order_items.product_id AND p.seller_id = ?
                                )";
                $update_stmt = $conn->prepare($update_query);
                $params = array_merge($selected_orders, [$user_id]);
                $update_stmt->bind_param($types . 'i', ...$params);
            }
            
            if (isset($update_stmt)) {
                $update_stmt->execute();
                $affected_rows = $update_stmt->affected_rows;
                $update_stmt->close();
                
                $conn->commit();
                $status_message = "Bulk action completed! $affected_rows order(s) updated.";
                $status_success = true;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $status_message = "Error performing bulk action: " . $e->getMessage();
            $status_success = false;
        }
    }
}
// Filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
// Build query with filters (without tracking_number if column doesn't exist)
if ($has_tracking_column) {
    $query = "SELECT
                oi.id as order_item_id,
                oi.order_id,
                oi.product_id,
                oi.quantity,
                oi.price,
                oi.status,
                oi.tracking_number,
                oi.created_at,
                oi.updated_at,
                p.name as product_name,
                p.image as product_image,
                o.shipping_name,
                o.shipping_address,
                o.payment_method,
                o.total_price as order_total,
                u.email as buyer_email,
                u.phone as buyer_phone
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              JOIN orders o ON oi.order_id = o.id
              JOIN users u ON o.buyer_id = u.id
              WHERE p.seller_id = ?";
} else {
    $query = "SELECT
                oi.id as order_item_id,
                oi.order_id,
                oi.product_id,
                oi.quantity,
                oi.price,
                oi.status,
                oi.created_at,
                oi.updated_at,
                p.name as product_name,
                p.image as product_image,
                o.shipping_name,
                o.shipping_address,
                o.payment_method,
                o.total_price as order_total,
                u.email as buyer_email,
                u.phone as buyer_phone
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              JOIN orders o ON oi.order_id = o.id
              JOIN users u ON o.buyer_id = u.id
              WHERE p.seller_id = ?";
}
$params = [$user_id];
$types = "i";
// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND oi.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($date_from)) {
    $query .= " AND DATE(oi.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $query .= " AND DATE(oi.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR oi.order_id LIKE ? OR o.shipping_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}
$query .= " ORDER BY oi.created_at DESC";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $orders = [];
    error_log("Order query preparation failed: " . $conn->error);
}
// Get order statistics
$stats_query = "SELECT
    oi.status,
    COUNT(*) as count,
    SUM(oi.quantity * oi.price) as revenue
FROM order_items oi
JOIN products p ON oi.product_id = p.id
WHERE p.seller_id = ?
GROUP BY oi.status";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$order_stats = [];
$total_revenue = 0;
while ($stat = $stats_result->fetch_assoc()) {
    $order_stats[$stat['status']] = $stat['count'];
    $total_revenue += $stat['revenue'];
}
$stats_stmt->close();
// Fetch user profile for navbar
$profile_query = "SELECT profile_image FROM users WHERE id = ?";
$profile_stmt = $conn->prepare($profile_query);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$current_profile = $profile_result->fetch_assoc();
$current_profile_image = $current_profile['profile_image'] ?? null;
$profile_stmt->close();
?>
<?php
// Helper: resolve product image URL for a seller order item
function resolveProductImageUrlSeller(array $orderRow) {
    // Always prefer the database value as-is (consistent with cart/shop rendering)
    $img = isset($orderRow['product_image']) ? trim($orderRow['product_image']) : '';
    if ($img !== '') {
        return str_replace('\\', '/', $img);
    }
    // Candidate relative paths (relative to this PHP file)
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
    // Best-effort simple fallbacks
    if ($img !== '') {
        if (file_exists(__DIR__ . '/Uploads/' . $img)) return 'Uploads/' . $img;
        if (file_exists(__DIR__ . '/Uploads/' . $img)) return 'Uploads/' . $img;
    }
    // Default placeholder
    if (file_exists(__DIR__ . '/Uploads/default-product.png')) return 'Uploads/default-product.png';
    if (file_exists(__DIR__ . '/../Uploads/default-product.png')) return 'Uploads/default-product.png';
    return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90"><rect width="100%" height="100%" fill="#222"/><text x="50%" y="50%" fill="#888" font-size="12" dominant-baseline="middle" text-anchor="middle">No image</text></svg>');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($effective_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Orders - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="../../css/seller_order_status.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        /* Navbar styles (from shop.php) */
        .navbar { position: sticky; top: 0; z-index: 1000; background: var(--bg-secondary); padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .nav-left { display: flex; align-items: center; gap: 10px; }
        .logo { height: 40px; }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .profile-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }
        .hamburger { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-primary); }
        .menu { display: none; position: absolute; top: 60px; right: 20px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; list-style: none; margin: 0; z-index: 1000; }
        .menu.show { display: block; }
        .menu li { margin: 10px 0; }
        .menu li a { color: var(--text-primary); text-decoration: none; font-size: 16px; }
        .menu li a:hover { color: var(--accent-color); }
        /* Seller Orders CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        :root {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2a2a2a;
            --bg-tertiary: #3a3a3a;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --text-muted: #888888;
            --accent-color: #44D62C;
            --border-color: #444444;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --accent-color: #44D62C;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .navbar {
            background: var(--bg-secondary);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-bottom: 1px solid var(--border-color);
        }
        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo {
            width: 40px;
            height: 40px;
        }
        .nav-left h2 {
            color: var(--accent-color);
            font-size: 1.5rem;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 1000;
        }
        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        .hamburger {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            z-index: 1100;
            position: relative;
        }
        .menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-secondary);
            width: 200px;
            list-style: none;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border: 1px solid var(--border-color);
            z-index: 1000;
        }
        .menu.show {
            display: block;
        }
        .menu li {
            border-bottom: 1px solid var(--border-color);
        }
        .menu li:last-child {
            border-bottom: none;
        }
        .menu a {
            display: block;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.3s;
        }
        .menu a:hover,
        .menu a.active {
            background: var(--accent-color);
            color: black;
        }
        .theme-dropdown {
            position: relative;
            display: inline-block;
        }
        .theme-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .theme-btn:hover {
            background: var(--border-color);
        }
        .theme-icon {
            font-size: 1.1em;
        }
        .theme-text {
            font-size: 0.9em;
        }
        .theme-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 8px;
            margin-top: 5px;
            min-width: 120px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }
        .theme-dropdown.active .theme-menu {
            display: block;
        }
        .theme-option {
            background: none;
            border: none;
            color: var(--text-primary);
            padding: 8px 12px;
            width: 100%;
            text-align: left;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .theme-option:hover {
            background: var(--bg-tertiary);
        }
        .theme-option i {
            width: 16px;
            text-align: center;
        }
        .notification {
            position: fixed;
            top: 16px;
            right: 16px;
            padding: 0;
            z-index: 1000;
            max-width: 400px;
            transition: opacity 0.3s ease, transform 0.3s ease;
            background: transparent;
            border: none;
            color: var(--text-primary);
        }
        .notification.success { color: var(--success-color); }
        .notification.error { color: var(--danger-color); }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background-color: var(--warning-color); color: #212529; }
        .status-confirmed { background-color: var(--info-color); color: white; }
        .status-shipped { background-color: var(--info-color); color: white; }
        .status-delivered { background-color: var(--success-color); color: white; }
        .status-cancelled { background-color: var(--danger-color); color: white; }
        .order-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }
        .order-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        /* product thumbnail */
        .product-thumb {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-left: 12px;
            flex-shrink: 0;
            background: #111;
            border: 1px solid var(--border-color);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: var(--accent-color);
        }
        .stat-label {
            color: var(--text-secondary);
            margin-top: 8px;
        }
        .filters {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        .bulk-actions {
            background: var(--bg-tertiary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            background-color: var(--bg-secondary);
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            background-color: var(--bg-secondary);
        }
        .btn-primary {
            background: var(--accent-color);
            color: black;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #3bc022;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background: var(--border-color);
        }
        .btn-warning {
            background: var(--warning-color);
            color: #212529;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        /* Updated Ellipsis-style buttons */
        .ellipsis-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .ellipsis-btn:hover {
            background: var(--accent-color);
            color: black;
            transform: translateY(-1px);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--text-primary);
            background-color: var(--bg-secondary);
        }
        .order-info h4 {
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        .order-meta {
            color: var(--text-primary);
            font-size: 0.9em;
        }
        .order-details {
            display: grid;
            gap: 15px;
        }
        .product-info {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
        .product-details {
            flex: none;
        }
        .product-details strong {
            font-size: 1.2em;
            color: var(--text-primary);
        }
        .product-details p {
            margin: 5px 0;
            color: var(--text-primary);
        }
        .item-total {
            font-weight: bold;
            color: var(--accent-color);
        }
        .customer-info,
        .tracking-info {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 4px;
        }
        .customer-info h5,
        .tracking-info h5 {
            margin-bottom: 10px;
            color: var(--text-primary);
        }
        .order-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--text-primary);
        }
        .status-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .status-controls select,
        .status-controls input {
            padding: 6px 10px;
            border: 1px solid var(--text-primary);
            border-radius: 4px;
        }
        .bulk-action-row {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
        }
        .empty-state h3 {
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        .empty-state p {
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .page-header h1 {
            color: var(--text-primary);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .page-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        .info-message {
            background: var(--warning-color);
            color: #212529;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            border-left: 4px solid var(--warning-color);
        }
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .order-header {
                flex-direction: column;
                gap: 10px;
            }
            .status-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .bulk-action-row {
                flex-direction: column;
                align-items: stretch;
            }
            .product-info {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
            }
            .ellipsis-btn {
                padding: 6px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <img src="Uploads/logo1.png" alt="Meta Shark Logo" class="logo">
            <h2>Meta Shark</h2>
        </div>
        <div class="nav-right">
            <div class="theme-dropdown" id="themeDropdown">
              <button class="theme-btn login-btn-select" id="themeDropdownBtn" title="Select theme" aria-label="Select theme">
                <i class="bi theme-icon" id="themeIcon"></i>
                <span class="theme-text" id="themeText"><?php echo $theme === 'device' ? 'Device' : ($effective_theme === 'light' ? 'Dark' : 'Light'); ?></span>
              </button>
              <div class="theme-menu" id="themeMenu" aria-hidden="true">
                <button class="theme-option" data-theme="light">Light</button>
                <button class="theme-option" data-theme="dark">Dark</button>
                <button class="theme-option" data-theme="device">Device</button>
              </div>
            </div>
            <a href="notifications.php" title="Notifications" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-bell" style="font-size:18px;"></i>
              <span></span>
            </a>
            <a href="carts_users.php" title="Cart" style="margin-left: 12px; text-decoration:none; color:inherit; display:inline-flex; align-items:center; gap:6px;">
              <i class="bi bi-cart" style="font-size:18px;"></i>
              <span></span>
            </a>
            <a href="seller_profile.php">
                <?php if (!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                    <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="Uploads/default-avatar.svg" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
            <button class="hamburger">☰</button>
        </div>
        <ul class="menu" id="menu">
            <li><a href="shop.php">Home</a></li>
            <li><a href="seller_dashboard.php">Seller Dashboard</a></li>
            <li><a href="seller_order_status.php" class="active">Orders</a></li>
            <li><a href="seller_products.php">Products</a></li>
            <li><a href="seller_profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
    <?php if (!empty($status_message)): ?>
        <div class="notification <?php echo $status_success ? 'success' : 'error'; ?>" id="statusNotification">
            <?php echo $status_success ? '✅' : '❌'; ?> <?php echo htmlspecialchars($status_message); ?>
        </div>
    <?php endif; ?>
    <div class="container">
        <div class="page-header">
            <h1>Order Management</h1>
            <p>Manage and track your product orders</p>
            <?php if (!$has_tracking_column): ?>
                <div class="info-message">
                    <small>📝 <strong>Note:</strong> Tracking number feature is not available in your database setup.</small>
                </div>
            <?php endif; ?>
        </div>
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($orders); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $order_stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $order_stats['confirmed'] ?? 0; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $order_stats['shipped'] ?? 0; ?></div>
                <div class="stat-label">Shipped</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $order_stats['delivered'] ?? 0; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
        </div>
        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product, Order ID, or Customer">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="seller_orders.php" class="btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
        <!-- Bulk Actions -->
        <?php if (!empty($orders)): ?>
        <div class="bulk-actions">
            <form method="POST" onsubmit="return confirm('Are you sure you want to perform this bulk action?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="bulk-action-row">
                    <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                    <label for="select-all">Select All</label>
                    <select name="bulk_action" required>
                        <option value="">Bulk Actions</option>
                        <option value="confirm">Mark as Confirmed</option>
                        <option value="ship">Mark as Shipped</option>
                        <option value="cancel">Mark as Cancelled</option>
                    </select>
                    <button type="submit" name="bulk_submit" class="btn-warning">Apply</button>
                </div>
        </div>
        <?php endif; ?>
        <!-- Orders List -->
        <div class="orders-list">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <h3>No orders found</h3>
                    <p>You don't have any orders matching your criteria.</p>
                    <a href="seller_products.php" class="btn-primary">Manage Products</a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h4>Order #<?php echo $order['order_id']; ?> - <?php echo htmlspecialchars($order['product_name']); ?></h4>
                                <p class="order-meta">
                                    Ordered: <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                    <?php if ($order['updated_at'] !== $order['created_at']): ?>
                                        | Updated: <?php echo date('M j, Y g:i A', strtotime($order['updated_at'])); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="order-status">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="order-details">
                            <div class="order-item">
                                <div class="product-info">
                                    <div class="product-details">
                                        <strong><?php echo htmlspecialchars($order['product_name']); ?></strong>
                                        <p>Quantity: <?php echo $order['quantity']; ?> × $<?php echo number_format($order['price'], 2); ?></p>
                                        <p class="item-total">Item Total: $<?php echo number_format($order['quantity'] * $order['price'], 2); ?></p>
                                    </div>
                                    <?php $imgSrc = resolveProductImageUrlSeller($order); ?>
                                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>" class="product-thumb" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<?php echo rawurlencode('<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"120\" height=\"90\"><rect width=\"100%\" height=\"100%\" fill=\"#222\"/><text x=\"50%\" y=\"50%\" fill=\"#888\" font-size=\"12\" dominant-baseline=\"middle\" text-anchor=\"middle\">No image</text></svg>'); ?>';">
                                </div>
                            </div>
                            <div class="customer-info">
                                <h5>Customer Details</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['buyer_email']); ?></p>
                                <?php if (!empty($order['buyer_phone'])): ?>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['buyer_phone']); ?></p>
                                <?php endif; ?>
                                <p><strong>Payment:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                            </div>
                            <?php if ($has_tracking_column && !empty($order['tracking_number'])): ?>
                                <div class="tracking-info">
                                    <h5>Tracking Information</h5>
                                    <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="order-actions">
                                <input type="checkbox" name="selected_orders[]" value="<?php echo $order['order_item_id']; ?>" class="order-checkbox">
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="order_item_id" value="<?php echo $order['order_item_id']; ?>">
                                    <div class="status-controls">
                                        <select name="status" required>
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <?php if ($has_tracking_column): ?>
                                            <input type="text" name="tracking_number"
                                                   value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                                                   placeholder="Tracking number (for shipped)">
                                        <?php endif; ?>
                                        <button type="submit" name="update_status" class="btn-primary">Update Status</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="orders-pagination" style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                    <button type="button" id="sellerShowLessBtn" class="ellipsis-btn" title="Show less" style="display:none;">Show less</button>
                    <button type="button" id="sellerShowMoreBtn" class="ellipsis-btn" title="Show more" style="display:none;">Show more...</button>
                </div>
                </form> <!-- Close the bulk actions form -->
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hamburger menu
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
                menu.querySelectorAll('a').forEach(item => {
                    item.addEventListener('click', () => menu.classList.remove('show'));
                });
            }
            // Auto-dismiss notifications (no bg, just fade text)
            const notification = document.getElementById('statusNotification');
            if (notification) {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }, 4000);
            }
            // Bulk selection
            window.toggleSelectAll = function(source) {
                const checkboxes = document.querySelectorAll('.order-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = source.checked;
                });
            };
            // Status form validation
            const statusForms = document.querySelectorAll('.status-form');
            statusForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const status = this.querySelector('select[name="status"]').value;
                    const trackingInput = this.querySelector('input[name="tracking_number"]');
                    
                    <?php if ($has_tracking_column): ?>
                    if (status === 'shipped' && trackingInput && !trackingInput.value.trim()) {
                        e.preventDefault();
                        alert('Please enter a tracking number for shipped orders.');
                        return false;
                    }
                    <?php endif; ?>
                    
                    return true;
                });
            });
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
            // Pagination: show more/less (5 at a time)
            const orderCards = document.querySelectorAll('.orders-list .order-card');
            const showMoreOrdersBtn = document.getElementById('sellerShowMoreBtn');
            const showLessOrdersBtn = document.getElementById('sellerShowLessBtn');
            const PAGE_SIZE = 5;
            let visibleOrders = 0;

            function updateOrdersVisibility() {
                orderCards.forEach((el, idx) => {
                    el.style.display = idx < visibleOrders ? 'block' : 'none';
                });
                if (showMoreOrdersBtn) {
                    showMoreOrdersBtn.style.display = visibleOrders < orderCards.length ? 'inline-block' : 'none';
                }
                if (showLessOrdersBtn) {
                    showLessOrdersBtn.style.display = visibleOrders > PAGE_SIZE ? 'inline-block' : 'none';
                }
            }

            if (orderCards.length > 0) {
                visibleOrders = Math.min(PAGE_SIZE, orderCards.length);
                updateOrdersVisibility();
            }

            if (showMoreOrdersBtn) {
                showMoreOrdersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    visibleOrders = Math.min(visibleOrders + PAGE_SIZE, orderCards.length);
                    updateOrdersVisibility();
                });
            }
            if (showLessOrdersBtn) {
                showLessOrdersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    visibleOrders = Math.max(PAGE_SIZE, visibleOrders - PAGE_SIZE);
                    updateOrdersVisibility();
                });
            }
        });
    </script>
</body>
</html>
