<?php
session_start();
include("db.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $seller_name = trim($_POST["seller_name"]);
    $seller_description = trim($_POST["seller_description"]);
    $business_type = trim($_POST["business_type"]);

    // Validation
    if (empty($fullname) || empty($email) || empty($password) || empty($seller_name)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists as a seller
        $checkSellerEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND (role = 'seller' OR role = 'admin')");
        $checkSellerEmail->bind_param("s", $email);
        $checkSellerEmail->execute();
        $checkSellerEmail->store_result();

        if ($checkSellerEmail->num_rows > 0) {
            $error = "This email is already registered as a seller. Please use a different email or login as seller.";
        } else {
            // Check if seller name already exists
            $checkSeller = $conn->prepare("SELECT id FROM users WHERE seller_name = ?");
            $checkSeller->bind_param("s", $seller_name);
            $checkSeller->execute();
            $checkSeller->store_result();

            if ($checkSeller->num_rows > 0) {
                $error = "Seller name already taken. Please choose a different name.";
            } else {
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert user with seller role
                $sql = "INSERT INTO users (fullname, email, phone, password, role, seller_name, seller_description, business_type, is_active_seller) VALUES (?, ?, ?, ?, 'seller', ?, ?, ?, TRUE)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssss", $fullname, $email, $phone, $hashedPassword, $seller_name, $seller_description, $business_type);

                if ($stmt->execute()) {
                    // Get the new user's ID
                    $new_user_id = $conn->insert_id;
                    
                    // Automatically log the user in
                    $_SESSION["user_id"] = $new_user_id;
                    $_SESSION["fullname"] = $fullname;
                    $_SESSION["email"] = $email;
                    $_SESSION["role"] = "seller";
                    
                    // Redirect to seller dashboard
                    header("Location: seller_dashboard.php");
                    exit();
                } else {
                    // Check for specific database errors
                    if ($conn->errno == 1062) { // Duplicate entry error
                        $error = "This email is already registered. Please use a different email or login as seller.";
                    } else {
                        $error = "Error creating seller account: " . $conn->error . ". Please try again.";
                    }
                }
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
    <title>Seller Signup - Meta Shark</title>
    <link rel="stylesheet" href="fonts/fonts.css">
      <link rel="icon" type="image/png" href="uploads/logo1.png">
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
            padding: 20px;
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

        .seller-badge {
            display: inline-block;
            background: #44D62C;
            color: #000000;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            width: 100%;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #44D62C;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
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
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.3);
            border-color: #36b020;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .required {
            color: #ff4444;
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: #44D62C;
            color: #000000;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn:hover {
            background: #36b020;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.3);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            background: rgba(68, 214, 44, 0.2);
            color: #44D62C;
            border: 1px solid #44D62C;
        }

        .error {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }

        .links {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #333333;
            text-align: center;
        }

        .links p {
            color: #888;
            margin-bottom: 10px;
        }

        .links a {
            color: #44D62C;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #36b020;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #44D62C;
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #36b020;
        }

        .help-text {
            font-size: 0.9rem;
            color: #888;
            margin-top: 5px;
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

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="login_users.php" class="back-link">← Back to Login</a>
    
    <div class="form-container">
        <div class="seller-badge">Join as Seller</div>
        <h1 class="form-title">Seller Signup</h1>
        <p class="form-subtitle">Start selling on Meta Shark today</p>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="benefits">
            <h3>Seller Benefits</h3>
            <ul>
                <li>List unlimited products</li>
                <li>Set your own prices</li>
                <li>Manage your inventory</li>
                <li>Track your sales</li>
                <li>Build your reputation</li>
                <li>Reach thousands of customers</li>
            </ul>
        </div>

        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="fullname">Full Name <span class="required">*</span></label>
                    <input type="text" id="fullname" name="fullname" 
                           value="<?php echo htmlspecialchars($fullname ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="business_type">Business Type</label>
                    <select id="business_type" name="business_type">
                        <option value="">Select Type</option>
                        <option value="individual" <?php echo (isset($business_type) && $business_type === 'individual') ? 'selected' : ''; ?>>Individual</option>
                        <option value="small_business" <?php echo (isset($business_type) && $business_type === 'small_business') ? 'selected' : ''; ?>>Small Business</option>
                        <option value="enterprise" <?php echo (isset($business_type) && $business_type === 'enterprise') ? 'selected' : ''; ?>>Enterprise</option>
                        <option value="other" <?php echo (isset($business_type) && $business_type === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="seller_name">Store Name <span class="required">*</span></label>
                <input type="text" id="seller_name" name="seller_name" 
                       value="<?php echo htmlspecialchars($seller_name ?? ''); ?>" 
                       placeholder="Your business or store name" required>
                <div class="help-text">This will be displayed as your store name</div>
            </div>

            <div class="form-group">
                <label for="seller_description">Store Description</label>
                <textarea id="seller_description" name="seller_description" 
                          placeholder="Tell customers about your store..."><?php echo htmlspecialchars($seller_description ?? ''); ?></textarea>
                <div class="help-text">Optional: Describe what makes your store special</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                    <div class="help-text">Minimum 6 characters</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>

            <button type="submit" class="btn">Create Seller Account</button>
        </form>

        <div class="links">
            <p>Already have a seller account?</p>
            <a href="seller_login.php">Seller Login</a>
            <br><br>
            <p>Just want to shop?</p>
            <a href="login_users.php">Regular Login</a>
            <br><br>
            <p>Don't have an account?</p>
            <a href="signup_users.php">Regular Signup</a>
        </div>
    </div>
</body>
</html>
