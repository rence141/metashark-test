
<?php
session_start();

// Check if user is logged in and is a seller
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

// Check if user has seller role
$user_role = $_SESSION['role'] ?? 'buyer';
if ($user_role !== 'seller' && $user_role !== 'admin') {
    header("Location: profile.php");
    exit();
}

include("db.php");

// Load Twilio SDK (uncomment in production after installing via Composer)
// require_once 'vendor/autoload.php';
// use Twilio\Rest\Client;

// Twilio credentials (replace with your own in production, or use environment variables)
$twilio_sid = 'YOUR_TWILIO_ACCOUNT_SID'; // Replace with your Twilio Account SID
$twilio_token = 'YOUR_TWILIO_AUTH_TOKEN'; // Replace with your Twilio Auth Token
$twilio_phone = '+1234567890'; // Replace with your Twilio phone number

// Initialize Twilio client (uncomment in production)
// $twilio = new Client($twilio_sid, $twilio_token);

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $new_phone = $_SESSION['pending_phone'] ?? '';
    
    if ($otp === $_SESSION['otp'] && !empty($new_phone)) {
        // OTP is correct, update phone and mark as verified
        $sql = "UPDATE users SET phone = ?, phone_verified = TRUE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_phone, $_SESSION["user_id"]);
        if ($stmt->execute()) {
            $success = "Phone number verified and updated successfully!";
            unset($_SESSION['otp']);
            unset($_SESSION['pending_phone']);
        } else {
            $error = "Error updating phone number: " . $conn->error;
        }
    } else {
        $error = "Invalid OTP. Please try again.";
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['verify_otp'])) {
    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $new_password = trim($_POST["password"]);
    $seller_name = trim($_POST["seller_name"]);
    $seller_description = trim($_POST["seller_description"]);
    $business_type = trim($_POST["business_type"]);
    $profile_image = null;

    // Fetch current user data to check if phone changed
    $current_query = "SELECT phone, phone_verified FROM users WHERE id = ?";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bind_param("i", $_SESSION["user_id"]);
    $current_stmt->execute();
    $current_user = $current_stmt->get_result()->fetch_assoc();
    $current_phone = $current_user['phone'];
    $phone_verified = $current_user['phone_verified'];

    // Validate phone number (must start with '09' and be 11 digits)
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = "Phone number must be 11 digits starting with '09' (e.g., 09123456789).";
    } elseif ($phone !== $current_phone && !$phone_verified) {
        // Phone changed and not verified, trigger OTP
        $_SESSION['pending_phone'] = $phone;
        $_SESSION['otp'] = sprintf("%06d", mt_rand(100000, 999999)); // Mock OTP
        // In production, send OTP via Twilio (uncomment and configure)
        /*
        try {
            $twilio->messages->create(
                $phone,
                [
                    'from' => $twilio_phone,
                    'body' => "Your Meta Shark OTP is {$_SESSION['otp']}. Valid for 5 minutes."
                ]
            );
        } catch (Exception $e) {
            $error = "Failed to send OTP: " . $e->getMessage();
        }
        */
        $show_otp_modal = true;
    } else {
        // Handle image upload
        if (!empty($_FILES["profile_image"]["name"])) {
            $targetDir = "Uploads/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Get current profile image to delete it later
            $currentImageQuery = "SELECT profile_image FROM users WHERE id = ?";
            $currentImageStmt = $conn->prepare($currentImageQuery);
            $currentImageStmt->bind_param("i", $_SESSION["user_id"]);
            $currentImageStmt->execute();
            $currentImageResult = $currentImageStmt->get_result();
            $currentImage = $currentImageResult->fetch_assoc()['profile_image'];

            $fileName = $_SESSION["user_id"] . "_" . time() . "_" . basename($_FILES["profile_image"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            // Allow only jpg, png
            $allowedTypes = ["jpg", "jpeg", "png"];
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFilePath)) {
                    $profile_image = $fileName;
                    
                    // Delete old profile image if it exists
                    if (!empty($currentImage) && file_exists($targetDir . $currentImage)) {
                        unlink($targetDir . $currentImage);
                    }
                }
            }
        }

        // If password is provided, update with hashing
        if (!empty($new_password)) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            if ($profile_image) {
                $sql = "UPDATE users SET fullname=?, email=?, phone=?, password=?, profile_image=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssi", $fullname, $email, $phone, $hashedPassword, $profile_image, $seller_name, $seller_description, $business_type, $_SESSION["user_id"]);
            } else {
                $sql = "UPDATE users SET fullname=?, email=?, phone=?, password=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssi", $fullname, $email, $phone, $hashedPassword, $seller_name, $seller_description, $business_type, $_SESSION["user_id"]);
            }
        } else {
            if ($profile_image) {
                $sql = "UPDATE users SET fullname=?, email=?, phone=?, profile_image=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssi", $fullname, $email, $phone, $profile_image, $seller_name, $seller_description, $business_type, $_SESSION["user_id"]);
            } else {
                $sql = "UPDATE users SET fullname=?, email=?, phone=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $fullname, $email, $phone, $seller_name, $seller_description, $business_type, $_SESSION["user_id"]);
            }
        }

        if ($stmt->execute()) {
            $_SESSION["fullname"] = $fullname;
            $success = "Seller profile updated successfully!";
            if ($profile_image) {
                $success .= " Profile image updated to: " . $profile_image;
            }
        } else {
            $error = "Error updating seller profile: " . $conn->error;
        }
    }
}

// Fetch user info
$sql = "SELECT id, fullname, email, phone, profile_image, seller_name, seller_description, business_type, seller_rating, total_sales, phone_verified FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get seller statistics
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    SUM(stock_quantity) as total_inventory,
    AVG(price) as avg_price
    FROM products 
    WHERE seller_id = ? AND is_active = TRUE";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $_SESSION["user_id"]);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user["seller_name"] ?: $user["fullname"]); ?> - Seller Profile</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
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
        }

        .navbar {
            background: #000000;
            padding: 15px 20px;
            color: #44D62C;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #44D62C;
        }

        .navbar h2 {
            margin: 0;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #222222;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #44D62C;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
            border: 2px solid #44D62C;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.5);
        }

        .profile-icon:hover {
            background-color: #333333;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(68, 214, 44, 0.8);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .seller-header {
            background: #111111;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid #44D62C;
            box-shadow: 0 10px 30px rgba(68, 214, 44, 0.1);
        }

        .seller-info {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 30px;
            align-items: center;
        }

        .seller-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #44D62C;
            box-shadow: 0 0 20px rgba(68, 214, 44, 0.3);
        }

        .seller-details h1 {
            color: #44D62C;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .seller-details p {
            color: #888;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .seller-badge {
            background: #44D62C;
            color: #000000;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #111111;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #333333;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #44D62C;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(68, 214, 44, 0.2);
        }

        .stat-number {
            font-size: 2.5rem;
            color: #44D62C;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #888;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .profile-form {
            background: #111111;
            border-radius: 15px;
            padding: 40px;
            border: 1px solid #333333;
        }

        .form-title {
            color: #44D62C;
            font-size: 2rem;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #44D62C;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
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
            background: #44D62C;
            color: #000000;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
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

        .help-text {
            font-size: 0.9rem;
            color: #888;
            margin-top: 5px;
        }

        .user-id-display {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #333333;
        }

        .user-id-display label {
            display: block;
            font-weight: bold;
            color: #44D62C;
            margin-bottom: 5px;
        }

        .user-id-display input {
            background: #e9ecef;
            color: #6c757d;
            font-family: monospace;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10001;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #111111;
            border-radius: 10px;
            padding: 25px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            border: 1px solid #44D62C;
            box-shadow: 0 5px 15px rgba(68, 214, 44, 0.2);
        }

        .modal-content h3 {
            color: #44D62C;
            margin-bottom: 20px;
        }

        .modal-content input {
            margin-bottom: 20px;
        }

        .modal-content .btn {
            width: auto;
            padding: 10px 20px;
        }

        @media (max-width: 768px) {
            .seller-info {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($show_otp_modal) && $show_otp_modal): ?>
                document.getElementById('otpModal').classList.add('show');
                // For testing: Log OTP to console (remove in production)
                console.log('OTP for testing: <?php echo $_SESSION['otp'] ?? ''; ?>');
            <?php endif; ?>
        });
    </script>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <h2>Meta Shark</h2>
        <div class="nav-right">
            <a href="seller_profile.php">
                <?php 
                $current_user_id = $_SESSION['user_id'];
                $profile_query = "SELECT profile_image FROM users WHERE id = ?";
                $profile_stmt = $conn->prepare($profile_query);
                $profile_stmt->bind_param("i", $current_user_id);
                $profile_stmt->execute();
                $profile_result = $profile_stmt->get_result();
                $current_profile = $profile_result->fetch_assoc();
                $current_profile_image = $current_profile['profile_image'] ?? null;
                ?>
                <?php if(!empty($current_profile_image) && file_exists('Uploads/' . $current_profile_image)): ?>
                    <img src="Uploads/<?php echo htmlspecialchars($current_profile_image); ?>" alt="Profile" class="profile-icon">
                <?php else: ?>
                    <img src="Uploads/default-avatar.svg" alt="Profile" class="profile-icon">
                <?php endif; ?>
            </a>
            <a href="seller_dashboard.php" style="color: #44D62C; text-decoration: none; font-weight: bold;">Dashboard</a>
            <a href="logout.php" style="color: #44D62C; text-decoration: none; font-weight: bold;">Logout</a>
        </div>
    </div>

    <!-- OTP VERIFICATION MODAL -->
    <div class="modal<?php echo isset($show_otp_modal) && $show_otp_modal ? ' show' : ''; ?>" id="otpModal">
        <div class="modal-content">
            <h3>Verify Phone Number</h3>
            <p>Enter the 6-digit OTP sent to <?php echo htmlspecialchars($_SESSION['pending_phone'] ?? ''); ?>.</p>
            <form method="POST">
                <input type="text" name="otp" placeholder="Enter OTP" required pattern="[0-9]{6}" title="Enter a 6-digit OTP">
                <button type="submit" name="verify_otp" class="btn">Verify OTP</button>
            </form>
        </div>
    </div>

    <div class="container">
        <!-- SELLER HEADER -->
        <div class="seller-header">
            <div class="seller-info">
                <div>
                    <?php if (!empty($user["profile_image"])): ?>
                        <img src="Uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Seller Avatar" class="seller-avatar">
                    <?php else: ?>
                        <img src="Uploads/default-avatar.svg" alt="Default Avatar" class="seller-avatar">
                    <?php endif; ?>
                </div>
                <div class="seller-details">
                    <h1><?php echo htmlspecialchars($user["seller_name"] ?: $user["fullname"]); ?></h1>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($user["phone"]); ?> <?php echo $user['phone_verified'] ? '(Verified)' : '(Unverified)'; ?></p>
                    <p><strong>Business Type:</strong> <?php echo htmlspecialchars($user["business_type"] ?: 'Not specified'); ?></p>
                    <p><strong>Rating:</strong> ⭐ <?php echo number_format($user["seller_rating"], 1); ?>/5.0</p>
                    <p><strong>Total Sales:</strong> $<?php echo number_format($user["total_sales"], 2); ?></p>
                </div>
                <div class="seller-badge">
                    <?php echo strtoupper($user_role); ?> 
                </div>
            </div>
        </div>

        <!-- SELLER STATISTICS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_products'] ?: 0; ?></div>
                <div class="stat-label">Active Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_inventory'] ?: 0; ?></div>
                <div class="stat-label">Total Inventory</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['avg_price'] ?: 0, 0); ?></div>
                <div class="stat-label">Average Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user["total_sales"] ?: 0; ?></div>
                <div class="stat-label">Total Sales</div>
            </div>
        </div>

        <!-- PROFILE FORM -->
        <div class="profile-form">
            <h2 class="form-title">Edit Seller Profile</h2>

            <?php if (!empty($success)) echo "<div class='message success'>$success</div>"; ?>
            <?php if (!empty($error)) echo "<div class='message error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group full-width">
                    <label for="profile_image">Profile Picture</label>
                    <input type="file" name="profile_image" accept="image/*">
                    <div class="help-text">Upload a new profile picture (JPG, PNG only)</div>
                </div>

                <!-- User ID Display (Read-only) -->
                <div class="user-id-display">
                    <label>User ID:</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['id']); ?>" readonly>
                    <div class="help-text">This is your unique identifier</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="fullname">Full Name <span class="required">*</span></label>
                        <input type="text" id="fullname" name="fullname" 
                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>" 
                               required pattern="09[0-9]{9}" 
                               title="Phone number must be 11 digits starting with '09' (e.g., 09123456789)">
                        <div class="help-text">Enter your GCash-registered phone number (e.g., 09123456789). OTP verification required for new numbers.</div>
                    </div>

                    <div class="form-group">
                        <label for="business_type">Business Type</label>
                        <select id="business_type" name="business_type">
                            <option value="">Select Type</option>
                            <option value="individual" <?php echo ($user['business_type'] === 'individual') ? 'selected' : ''; ?>>Individual</option>
                            <option value="small_business" <?php echo ($user['business_type'] === 'small_business') ? 'selected' : ''; ?>>Small Business</option>
                            <option value="enterprise" <?php echo ($user['business_type'] === 'enterprise') ? 'selected' : ''; ?>>Enterprise</option>
                            <option value="other" <?php echo ($user['business_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="seller_name">Store Name <span class="required">*</span></label>
                    <input type="text" id="seller_name" name="seller_name" 
                           value="<?php echo htmlspecialchars($user['seller_name']); ?>" 
                           placeholder="Your business or store name" required>
                    <div class="help-text">This will be displayed as your store name</div>
                </div>

                <div class="form-group">
                    <label for="seller_description">Store Description</label>
                    <textarea id="seller_description" name="seller_description" 
                              placeholder="Tell customers about your store..."><?php echo htmlspecialchars($user['seller_description']); ?></textarea>
                    <div class="help-text">Describe what makes your store special</div>
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" 
                           placeholder="Leave blank to keep current password">
                    <div class="help-text">Minimum 6 characters</div>
                </div>

                <button type="submit" class="btn">Update Seller Profile</button>
            </form>
        </div>
    </div>
</body>
</html>
