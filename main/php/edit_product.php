<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION["user_id"];

// Check if user has seller/admin role
$user_sql = "SELECT role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data['role'] !== 'seller' && $user_data['role'] !== 'admin') {
    header("Location: shop.php");
    exit();
}

$success = "";
$error = "";
$product = null;

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id <= 0) {
    header("Location: seller_dashboard.php");
    exit();
}

// Fetch product details
$product_sql = "SELECT * FROM products WHERE id = ? AND seller_id = ?";
$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param("ii", $product_id, $user_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
if ($product_result->num_rows === 0) {
    header("Location: seller_dashboard.php");
    exit();
}
$product = $product_result->fetch_assoc();

// Fetch current categories
$cat_stmt = $conn->prepare("SELECT c.name FROM product_categories pc 
                            JOIN categories c ON pc.category_id = c.id 
                            WHERE pc.product_id = ?");
$cat_stmt->bind_param("i", $product_id);
$cat_stmt->execute();
$cat_res = $cat_stmt->get_result();
$current_categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $current_categories[] = $row['name'];
}

// Fetch current specs
$spec_stmt = $conn->prepare("SELECT * FROM product_specs WHERE product_id=?");
$spec_stmt->bind_param("i", $product_id);
$spec_stmt->execute();
$spec_result = $spec_stmt->get_result();
$current_specs = [];
while ($row = $spec_result->fetch_assoc()) {
    $current_specs[] = $row;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $categories = $_POST['categories'] ?? [];
    $price = floatval($_POST["price"]);
    $stock_quantity = intval($_POST["stock_quantity"]);
    $sku = trim($_POST["sku"]);
    $image_url = trim($_POST["image_url"]);
    $is_active = isset($_POST["is_active"]) ? 1 : 0;
    $is_featured = isset($_POST["is_featured"]) ? 1 : 0;

    // Validate
    if (empty($name) || $price <= 0 || empty($categories)) {
        $error = "Please fill in all required fields with valid values and select at least one category.";
    } elseif (strlen($sku) > 255) {
        $error = "SKU is too long. Please use a shorter product code.";
    } else {
        // Generate SKU if not provided
        if (empty($sku)) {
            $sku = "SKU-" . time() . "-" . rand(100, 999);
        }

        // Handle local image upload
        $image_path = "";
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image_file']['tmp_name'];
            $file_name = basename($_FILES['image_file']['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];

            if (in_array($file_ext, $allowed)) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $target_file = $target_dir . time() . "_" . preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $file_name);
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $image_path = $target_file;
                } else {
                    $error = "Failed to upload the image.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
            }
        }

        // If no new upload, fallback to URL or keep current
        if (empty($image_path)) {
            $image_path = !empty($image_url) ? $image_url : $product['image'];
        }

        if (empty($error)) {
            // Update product info
            $update_sql = "UPDATE products SET 
                           name=?, description=?, price=?, image=?, sku=?, stock_quantity=?, 
                           is_active=?, is_featured=?, updated_at=CURRENT_TIMESTAMP
                           WHERE id=? AND seller_id=?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssdssiiiii", 
                $name, $description, $price, $image_path, $sku, 
                $stock_quantity, $is_active, $is_featured, $product_id, $user_id);
            $update_stmt->execute();

            // Clear old categories
            $conn->query("DELETE FROM product_categories WHERE product_id = $product_id");

            // Insert new categories
            foreach ($categories as $cat_name) {
                $cat_stmt = $conn->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
                $cat_stmt->bind_param("s", $cat_name);
                $cat_stmt->execute();
                $cat_res = $cat_stmt->get_result();
                if ($cat_row = $cat_res->fetch_assoc()) {
                    $cat_id = $cat_row['id'];
                    $insert_cat = $conn->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                    $insert_cat->bind_param("ii", $product_id, $cat_id);
                    $insert_cat->execute();
                }
            }

            // Handle specs: delete all old and insert new
            $conn->query("DELETE FROM product_specs WHERE product_id = $product_id");
            if (!empty($_POST['spec_name']) && !empty($_POST['spec_value'])) {
                $spec_names = $_POST['spec_name'];
                $spec_values = $_POST['spec_value'];
                
                for ($i = 0; $i < count($spec_names); $i++) {
                    $spec_name = trim($spec_names[$i]);
                    $spec_value = trim($spec_values[$i]);
                    if (!empty($spec_name) && !empty($spec_value)) {
                        $spec_stmt = $conn->prepare("INSERT INTO product_specs (product_id, spec_name, spec_value) VALUES (?, ?, ?)");
                        $spec_stmt->bind_param("iss", $product_id, $spec_name, $spec_value);
                        $spec_stmt->execute();
                    }
                }
            }

            $success = "Product updated successfully!";
            // Refresh product and specs
            header("Location: edit_product.php?id=$product_id&success=1");
            exit();
        }
    }
}
?>
<!-- Add this inside your <form> -->
<div class="form-group">
    <label>Optional Specs</label>
    <div id="specs-container">
        <?php foreach ($current_specs as $spec): ?>
        <div class="spec-row" style="display:flex; gap:10px; margin-bottom:5px;">
            <input type="text" name="spec_name[]" placeholder="Spec Name" value="<?php echo htmlspecialchars($spec['spec_name']); ?>">
            <input type="text" name="spec_value[]" placeholder="Spec Value" value="<?php echo htmlspecialchars($spec['spec_value']); ?>">
            <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
        </div>
        <?php endforeach; ?>
        <!-- Add empty row if none exists -->
        <?php if (empty($current_specs)): ?>
        <div class="spec-row" style="display:flex; gap:10px; margin-bottom:5px;">
            <input type="text" name="spec_name[]" placeholder="Spec Name">
            <input type="text" name="spec_value[]" placeholder="Spec Value">
            <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
        </div>
        <?php endif; ?>
    </div>
    <button type="button" class="btn btn-primary" onclick="addSpec()">Add Spec</button>
</div>

<script>
function addSpec() {
    const container = document.getElementById('specs-container');
    const div = document.createElement('div');
    div.className = 'spec-row';
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.marginBottom = '5px';
    div.innerHTML = `
        <input type="text" name="spec_name[]" placeholder="Spec Name">
        <input type="text" name="spec_value[]" placeholder="Spec Value">
        <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
    `;
    container.appendChild(div);
}

function removeSpec(button) {
    button.parentElement.remove();
}
</script>
