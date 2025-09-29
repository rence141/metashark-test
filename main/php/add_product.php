<?php
session_start();

// Check if user is logged in and is a seller/admin
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION["user_id"];

// Check if user has seller role
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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = floatval($_POST["price"]);
    $stock_quantity = intval($_POST["stock_quantity"]);
    $sku = trim($_POST["sku"]);
    $image_url = trim($_POST["image_url"]);
    $categories = $_POST['categories'] ?? [];

    // Validate required fields
    if (empty($name) || $price <= 0 || empty($categories)) {
        $error = "Please fill in all required fields with valid values and select at least one category.";
    } elseif (strlen($sku) > 255) {
        $error = "SKU is too long. Please use a shorter product code.";
    } else {
        // Generate SKU if empty
        if (empty($sku)) {
            $sku = "SKU-" . time() . "-" . rand(100, 999);
        }

        // Handle image upload
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

        // Fallback to URL if no file uploaded
        if (empty($image_path)) {
            $image_path = $image_url;
        }

        if (empty($error)) {
            // Insert product
            $sql = "INSERT INTO products (name, description, price, image, sku, stock_quantity, seller_id, is_active, is_featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, FALSE)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdssii", $name, $description, $price, $image_path, $sku, $stock_quantity, $user_id);

            if ($stmt->execute()) {
                $product_id = $stmt->insert_id;

                // Insert categories into product_categories
                foreach ($categories as $cat_name) {
                    $cat_name = trim($cat_name);
                    $cat_stmt = $conn->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
                    $cat_stmt->bind_param("s", $cat_name);
                    $cat_stmt->execute();
                    $cat_res = $cat_stmt->get_result();
                    if ($cat_row = $cat_res->fetch_assoc()) {
                        $cat_id = $cat_row['id'];
                        $conn->query("INSERT INTO product_categories (product_id, category_id) VALUES ($product_id, $cat_id)");
                    }
                }

                // Insert optional specs
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

                $success = "Product added successfully!";
                $name = $description = $sku = $image_url = "";
                $price = $stock_quantity = 0;
                $categories = [];
            } else {
                $error = "Error adding product: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product - MetaAccessories</title>
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="icon" type="image/png" href="uploads/logo1.png">
<?php include('theme_toggle.php'); ?>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'ASUS ROG', Arial, sans-serif; background:#0A0A0A; color:#fff; min-height:100vh; }
.navbar { background:#000; padding:15px 20px; color:#44D62C; display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #44D62C; }
.navbar h2 { margin:0; }
.nav-right { display:flex; align-items:center; gap:15px; }
.profile-icon { width:35px; height:35px; border-radius:50%; background:#222; display:flex; align-items:center; justify-content:center; color:#44D62C; font-size:20px; cursor:pointer; transition:all 0.3s ease; object-fit:cover; border:2px solid #44D62C; box-shadow:0 0 10px rgba(68,214,44,0.5); }
.container { max-width:800px; margin:40px auto; padding:0 20px; }
.form-container { background:#111; border-radius:10px; padding:40px; border:1px solid #333; }
.form-title { text-align:center; font-size:2rem; color:#44D62C; margin-bottom:30px; text-transform:uppercase; letter-spacing:2px; }
.form-group { margin-bottom:25px; }
.form-group label { display:block; margin-bottom:8px; color:#44D62C; font-weight:bold; }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:12px; border:1px solid #44D62C; border-radius:8px; background:#1a1a1a; color:#fff; font-size:1rem; transition:all 0.3s ease; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; box-shadow:0 0 10px rgba(68,214,44,0.3); border-color:#36b020; }
.form-group textarea { height:120px; resize:vertical; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.required { color:#ff4444; }
.btn { padding:10px 20px; border:none; border-radius:8px; font-size:1rem; font-weight:bold; cursor:pointer; transition:all 0.3s ease; text-decoration:none; display:inline-block; text-align:center; }
.btn-primary { background:#44D62C; color:#000; }
.btn-primary:hover { background:#36b020; transform:translateY(-2px); box-shadow:0 5px 15px rgba(68,214,44,0.3); }
.btn-secondary { background:#333; color:#fff; border:1px solid #44D62C; margin-right:10px; }
.btn-secondary:hover { background:#44D62C; color:#000; }
.message { padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:bold; }
.success { background: rgba(68,214,44,0.2); color:#44D62C; border:1px solid #44D62C; }
.error { background: rgba(255,68,68,0.2); color:#ff4444; border:1px solid #ff4444; }
.form-actions { display:flex; gap:15px; margin-top:30px; }
.help-text { font-size:0.9rem; color:#888; margin-top:5px; }
@media (max-width:768px) { .form-row { grid-template-columns:1fr; } .form-actions { flex-direction:column; } }
.spec-row input { flex:1; }
.spec-row button { flex:none; }
</style>
</head>
<body>

<div class="navbar">
    <h2>Meta Shark</h2>
    <div class="nav-right">
        <a href="seller_profile.php">
            <?php 
            $profile_query = "SELECT profile_image FROM users WHERE id = ?";
            $profile_stmt = $conn->prepare($profile_query);
            $profile_stmt->bind_param("i", $user_id);
            $profile_stmt->execute();
            $profile_result = $profile_stmt->get_result();
            $current_profile = $profile_result->fetch_assoc();
            $current_profile_image = $current_profile['profile_image'] ?? null;
            ?>
            <img src="<?php echo (!empty($current_profile_image) && file_exists('uploads/'.$current_profile_image)) ? 'uploads/'.htmlspecialchars($current_profile_image) : 'uploads/default-avatar.svg'; ?>" 
                 alt="Profile" class="profile-icon">
        </a>
    </div>
</div>

<div class="container">
    <div class="form-container">
        <h1 class="form-title">Add New Product</h1>

        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Product Name <span class="required">*</span></label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Categories / Tags <span class="required">*</span></label>
                <div style="display:flex; flex-wrap:wrap; gap:10px;">
                    <?php
                    $all_tags = ['Accessories','Phone','Tablet','Laptop','Gaming','Other'];
                    $selected_tags = $categories ?? [];
                    foreach($all_tags as $tag):
                    ?>
                    <label style="display:flex; align-items:center; gap:5px;">
                        <input type="checkbox" name="categories[]" value="<?php echo $tag; ?>" 
                        <?php echo in_array($tag, $selected_tags) ? 'checked' : ''; ?>>
                        <?php echo $tag; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" value="<?php echo htmlspecialchars($sku ?? ''); ?>" placeholder="Leave empty for auto-generation">
                </div>
                <div class="form-group">
                    <label>Price ($) <span class="required">*</span></label>
                    <input type="number" name="price" step="0.01" min="0" value="<?php echo $price ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Stock Quantity <span class="required">*</span></label>
                <input type="number" name="stock_quantity" min="0" value="<?php echo $stock_quantity ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label>Product Image URL</label>
                <input type="url" name="image_url" value="<?php echo htmlspecialchars($image_url ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Upload Local Image</label>
                <input type="file" name="image_file" accept="image/*">
            </div>

            <div class="form-group">
                <label>Optional Specs</label>
                <div id="specs-container">
                    <div class="spec-row" style="display:flex; gap:10px; margin-bottom:5px;">
                        <input type="text" name="spec_name[]" placeholder="Spec Name (e.g., GB)">
                        <input type="text" name="spec_value[]" placeholder="Spec Value (e.g., 128GB)">
                        <button type="button" class="btn btn-secondary" onclick="removeSpec(this)">Remove</button>
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="addSpec()">Add Spec</button>
            </div>

            <div class="form-actions">
                <a href="seller_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </div>
        </form>
    </div>
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

</body>
</html>
