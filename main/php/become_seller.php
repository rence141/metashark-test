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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'ASUS ROG', Arial, sans-serif;
            background: #0A0A0A;
            color: #FFFFFF;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-container {
            background: #111111;
            padding: 40px;
            border-radius: 15px;
            border: 1px solid #333333;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .form-title {
            text-align: center;
            font-size: 2rem;
            color: #44D62C;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #44D62C;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #44D62C;
            border-radius: 8px;
            background: #1a1a1a;
            color: #FFFFFF;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.3);
            border-color: #36b020;
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .required {
            color: #ff4444;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            width: 100%;
        }

        .btn-primary {
            background: #44D62C;
            color: #000000;
        }

        .btn-primary:hover {
            background: #36b020;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.3);
        }

        .btn-secondary {
            background: #333333;
            color: #FFFFFF;
            border: 1px solid #44D62C;
            margin-top: 15px;
        }

        .btn-secondary:hover {
            background: #44D62C;
            color: #000000;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .error {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }

        .benefits {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #333333;
        }

        .benefits h3 {
            color: #44D62C;
            margin-bottom: 15px;
            text-align: center;
        }

        .benefits ul {
            list-style: none;
            padding: 0;
        }

        .benefits li {
            color: #888;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .benefits li:before {
            content: "✓";
            color: #44D62C;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .help-text {
            font-size: 0.9rem;
            color: #888;
            margin-top: 5px;
        }
    </style>
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
