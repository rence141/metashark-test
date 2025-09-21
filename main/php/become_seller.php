<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

include("db.php");

$user_id = $_SESSION["user_id"];
$success = "";
$error = "";

// Check if user already has seller role
$check_sql = "SELECT role, seller_name FROM users WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$user_data = $check_result->fetch_assoc();

if ($user_data['role'] === 'seller' || $user_data['role'] === 'admin') {
    header("Location: seller_dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $seller_name = trim($_POST["seller_name"]);
    $seller_description = trim($_POST["seller_description"]);
    
    if (empty($seller_name)) {
        $error = "Please enter a seller name.";
    } else {
        // Update user to have seller role
        $update_sql = "UPDATE users SET role = 'seller', seller_name = ?, seller_description = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $seller_name, $seller_description, $user_id);
        
        if ($update_stmt->execute()) {
            header("Location: seller_dashboard.php");
            exit();
        } else {
            $error = "Error becoming a seller. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - MetaAccessories</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="uploads/logo1.png">
    <link rel="stylesheet" href="../../css/seller_dashboard.css">

</head>
<body>
    <div class="form-container">
        <h1 class="form-title">Become a Seller</h1>
        <p class="form-subtitle">Start selling your products on MetaAccessories</p>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="benefits">
            <h3>Seller Benefits</h3>
            <ul>
                <li>List unlimited products</li>
                <li>Manage your own inventory</li>
                <li>Set your own prices</li>
                <li>Track your sales</li>
                <li>Build your seller reputation</li>
                <li>Reach thousands of customers</li>
            </ul>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="seller_name">Seller Name <span class="required">*</span></label>
                <input type="text" id="seller_name" name="seller_name" 
                       value="<?php echo htmlspecialchars($seller_name ?? ''); ?>" 
                       placeholder="Your business or display name" required>
                <div class="help-text">This will be displayed as your store name</div>
            </div>

            <div class="form-group">
                <label for="seller_description">Store Description</label>
                <textarea id="seller_description" name="seller_description" 
                          placeholder="Tell customers about your store..."><?php echo htmlspecialchars($seller_description ?? ''); ?></textarea>
                <div class="help-text">Optional: Describe what makes your store special</div>
            </div>

            <button type="submit" class="btn btn-primary">Become a Seller</button>
            <a href="shop.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
