<?php
// 1. ENABLE ERROR REPORTING
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 2. START SESSION
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/admin_theme.php';

// --- YOUR CATEGORY LIST ---
$categories = [
    1 => 'Accessories',
    2 => 'Phone',
    3 => 'Tablet',
    4 => 'Laptop',
    5 => 'Gaming'
];

// Check ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_products.php');
    exit;
}

$id = (int)$_GET['id'];
$error = '';

// --- HANDLE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    
    // HANDLE MULTIPLE CATEGORIES
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $category_string = implode(',', $_POST['categories']);
    } else {
        $category_string = '';
    }

    $price = (float)$_POST['price'];
    
    // UPDATED: Using correct input name 'stock_quantity'
    $stock = (int)$_POST['stock_quantity'];
    
    $description = trim($_POST['description']);

    if (empty($name) || $price < 0 || $stock < 0) {
        $error = "Please fill in all fields correctly.";
    } else {
        // UPDATED: SQL now uses 'stock_quantity' column
        $sql = "UPDATE products SET name = ?, category = ?, price = ?, stock_quantity = ?, description = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        
        // --- ERROR CATCHING BLOCK ---
        if ($stmt === false) {
            die("<div style='background:white; color:red; padding:20px; font-family:sans-serif;'>
                <h3>Database Error:</h3>
                " . $conn->error . "
                <br><br>
                <strong>Check your Database Columns:</strong><br>
                Does your 'products' table actually have a column named <em>stock_quantity</em>?
                </div>");
        }
        // ----------------------------------------

        $stmt->bind_param("ssdisi", $name, $category_string, $price, $stock, $description, $id);

        if ($stmt->execute()) {
            header("Location: admin_products.php?updated=1");
            exit;
        } else {
            $error = "Error updating product: " . $stmt->error;
        }
    }
}

// --- GET PRODUCT DATA ---
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: admin_products.php');
    exit;
}

// CONVERT SAVED STRING BACK TO ARRAY FOR CHECKBOXES
$selected_categories = explode(',', $product['category'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" <?php apply_theme_html_tag(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    /* --- STYLING --- */
    :root {
        --primary: #44D62C;
        --bg: #f3f4f6;
        --panel: #ffffff;
        --panel-border: #e5e7eb;
        --text: #1f2937;
        --text-muted: #6b7280;
        --radius: 16px;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; outline: none; }
    body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    
    .edit-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        width: 100%;
        max-width: 600px;
        padding: 30px;
    }
    
    h2 { margin-bottom: 20px; font-size: 20px; border-bottom: 1px solid var(--panel-border); padding-bottom: 15px; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }
    .form-control {
        width: 100%;
        padding: 12px;
        background: var(--bg);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        color: var(--text);
        font-size: 14px;
        transition: 0.2s;
    }
    .form-control:focus { border-color: var(--primary); }
    
    /* CHECKBOX STYLING */
    .checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px;
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        background: var(--bg);
    }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--panel);
        padding: 6px 12px;
        border-radius: 20px;
        border: 1px solid var(--panel-border);
        cursor: pointer;
        user-select: none;
        font-size: 13px;
    }
    .checkbox-item:hover { border-color: var(--primary); }
    .checkbox-item input { accent-color: var(--primary); width: 16px; height: 16px; }

    .btn-group { display: flex; gap: 10px; margin-top: 30px; }
    .btn { flex: 1; padding: 12px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-secondary { background: transparent; border: 1px solid var(--panel-border); color: var(--text); }
    .btn-secondary:hover { background: var(--bg); }
    
    .alert-error { padding: 12px; background: rgba(244, 67, 54, 0.1); color: #f44336; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

<div class="edit-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2>Edit Product #<?php echo $id; ?></h2>
        <a href="admin_products.php" style="color:var(--text-muted); font-size:24px;"><i class="bi bi-x"></i></a>
    </div>

    <?php if($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Product Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>Categories (Select all that apply)</label>
            <div class="checkbox-group">
                <?php foreach ($categories as $cat_id => $cat_name): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="categories[]" value="<?php echo $cat_id; ?>" 
                            <?php echo in_array($cat_id, $selected_categories) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($cat_name); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
            
        <div style="display:flex; gap:15px;">
            <div class="form-group" style="flex:1">
                <label>Price ($)</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?php echo htmlspecialchars($product['price'] ?? ''); ?>" required>
            </div>

            <div class="form-group" style="flex:1">
                <label>Stock Quantity</label>
                <input type="number" name="stock_quantity" class="form-control" value="<?php echo htmlspecialchars($product['stock_quantity'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
        </div>

        <div class="btn-group">
            <a href="admin_products.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php echo get_theme_script(); ?>
</body>
</html>