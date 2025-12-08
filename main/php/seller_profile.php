<?php
session_start();

$errors = [];     // <--- IMPORTANT
$message = "";    // optional
$currentCountryCode = "";
$currentCountryName = "";
$currentLanguage = $_SESSION['language'] ?? 'en';

require_once 'db.php';

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login_users.php");
    exit();
}

// Check role (allow suspended_seller to show suspension page)
$user_role = $_SESSION['role'] ?? 'buyer';
if ($user_role === 'suspended_seller') {
    header("Location: seller_suspended.php");
    exit();
}
if ($user_role !== 'seller' && $user_role !== 'admin') {
    header("Location: profile.php");
    exit();
}

include("db.php");

// Twilio config (disabled for dev)
$twilio_sid = 'YOUR_TWILIO_ACCOUNT_SID';
$twilio_token = 'YOUR_TWILIO_AUTH_TOKEN';
$twilio_phone = '+1234567890';

// Country > Language map
$countryLangMap = [
    'US' => 'en', 'GB' => 'en', 'CA' => 'en', 'AU' => 'en',
    'FR' => 'fr', 'DE' => 'de', 'ES' => 'es', 'IT' => 'it',
    'BR' => 'pt', 'PT' => 'pt', 'CN' => 'zh', 'JP' => 'ja',
    'IN' => 'en', 'NL' => 'nl', 'PH' => 'tl'
];

$countries = [
    ['code'=>'US','name'=>'United States'],
    ['code'=>'GB','name'=>'United Kingdom'],
    ['code'=>'CA','name'=>'Canada'],
    ['code'=>'AU','name'=>'Australia'],
    ['code'=>'FR','name'=>'France'],
    ['code'=>'DE','name'=>'Germany'],
    ['code'=>'ES','name'=>'Spain'],
    ['code'=>'IT','name'=>'Italy'],
    ['code'=>'BR','name'=>'Brazil'],
    ['code'=>'IN','name'=>'India'],
    ['code'=>'CN','name'=>'China'],
    ['code'=>'JP','name'=>'Japan'],
    ['code'=>'NL','name'=>'Netherlands'],
    ['code'=>'PT','name'=>'Portugal']
];

// Set proper userId
$userId = $_SESSION["user_id"];

/* ---------------------------------------------------------
   SAVE COUNTRY + LANGUAGE PREFERENCES
--------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_preferences'])) {

    $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
    $countryName = trim($_POST['country_name'] ?? '');
    $language = trim($_POST['language'] ?? '');

    if ($countryCode && $countryName && $language) {
        $stmt = $conn->prepare("
            UPDATE users 
            SET country_code = ?, country_name = ?, language = ?
            WHERE id = ?
        ");

        $stmt->bind_param("sssi", $countryCode, $countryName, $language, $userId);

        if ($stmt->execute()) {
            $_SESSION['language'] = $language;
            $message = "Preferences saved successfully!";
        } else {
            $message = "Error saving preferences.";
        }
    }
}

/* ---------------------------------------------------------
   OTP VERIFICATION HANDLER
--------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $new_phone = $_SESSION['pending_phone'] ?? '';

    if ($otp === ($_SESSION['otp'] ?? null)) {

        $stmt = $conn->prepare("UPDATE users SET phone=?, phone_verified=TRUE WHERE id=?");
        $stmt->bind_param("si", $new_phone, $userId);
        $stmt->execute();

        unset($_SESSION['otp'], $_SESSION['pending_phone']);
        $success = "Phone number verified successfully!";
    } else {
        $error = "Invalid OTP.";
    }
}

/* ---------------------------------------------------------
   PROFILE UPDATE (FULL FORM)
--------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['verify_otp']) && !isset($_POST['save_preferences'])) {

    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $new_password = trim($_POST["password"]);
    $seller_name = trim($_POST["seller_name"]);
    $seller_description = trim($_POST["seller_description"]);
    $business_type = trim($_POST["business_type"]);

    /* Retrieve current user */
    $stmt = $conn->prepare("SELECT phone, phone_verified FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    $current_phone = $current["phone"];
    $phone_verified = $current["phone_verified"];

    /* PHONE VALIDATION */
    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = "Phone number must be 11 digits and start with 09.";
    } elseif ($phone !== $current_phone && !$phone_verified) {

        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $_SESSION['otp'] = $otp;
        $_SESSION['pending_phone'] = $phone;

        $show_otp_modal = true;
        goto skip_update;
    }

    /* IMAGE UPLOAD */
    $profile_image = null;
    if (!empty($_FILES["profile_image"]["name"])) {

        $targetDir = "Uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName = $userId . "_" . time() . "_" . basename($_FILES["profile_image"]["name"]);
        $targetPath = $targetDir . $fileName;

        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if (in_array($ext, ["jpg", "jpeg", "png"])) {
            move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetPath);
            $profile_image = $fileName;
        }
    }

    /* PASSWORD HANDLING */
    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        if ($profile_image) {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, password=?, profile_image=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", $fullname, $email, $phone, $hashed, $profile_image, $seller_name, $seller_description, $business_type, $userId);
        } else {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, password=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $fullname, $email, $phone, $hashed, $seller_name, $seller_description, $business_type, $userId);
        }
    } else {
        if ($profile_image) {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, profile_image=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $fullname, $email, $phone, $profile_image, $seller_name, $seller_description, $business_type, $userId);
        } else {
            $sql = "UPDATE users SET fullname=?, email=?, phone=?, seller_name=?, seller_description=?, business_type=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $fullname, $email, $phone, $seller_name, $seller_description, $business_type, $userId);
        }
    }

    if ($stmt->execute()) {
        $_SESSION["fullname"] = $fullname;
        $success = "Profile updated successfully!";
    } else {
        $error = "Error updating profile.";
    }

    skip_update:
}

/* ---------------------------------------------------------
   FETCH USER INFO
--------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT id, fullname, email, phone, profile_image, seller_name, seller_description,
           business_type, seller_rating, total_sales, phone_verified,
           country_code, country_name, language, is_active_seller
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If seller account is suspended via is_active_seller flag, redirect to suspended page
if (($user_role === 'seller') && isset($user['is_active_seller']) && (int)$user['is_active_seller'] === 0) {
    header("Location: seller_suspended.php");
    exit();
}

/* ---------------------------------------------------------
   FIX COUNTRY/LANGUAGE LOADING
--------------------------------------------------------- */
$currentCountryCode = $user["country_code"] ?? '';
$currentCountryName = $user["country_name"] ?? '';
$currentLanguage    = $user["language"] ?? ($_SESSION['language'] ?? 'en');

/* ---------------------------------------------------------
   FETCH SELLER STATS
--------------------------------------------------------- */
$stats_sql = "
    SELECT COUNT(*) AS total_products,
           SUM(stock_quantity) AS total_inventory,
           AVG(price) AS avg_price
    FROM products
    WHERE seller_id = ? AND is_active = TRUE
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $userId);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user["seller_name"] ?: $user["fullname"]); ?> - Seller Profile</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="icon" type="image/png" href="Uploads/logo1.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --background: #fff;
            --text-color: #333;
            --primary-color: #44D62C;
            --secondary-bg: #f8f9fa;
            --border-color: #dee2e6;
        }

        [data-theme="dark"] {
            --background: #000000ff;
            --text-color: #e0e0e0;
            --primary-color: #44D62C;
            --secondary-bg: #2a2a2a;
            --border-color: #444;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--background);
            color: var(--text-color);
            margin: 0;
            padding: 0;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--background);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            margin: 0;
            color: var(--text-color);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .seller-header {
            background: var(--secondary-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .seller-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .seller-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .profile-image-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .profile-image-container .edit-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            color: var(--primary-color);
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            padding: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-image-container:hover .edit-icon {
            opacity: 1;
        }

        .file-selected {
            margin-top: 10px;
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.8;
            text-align: center;
        }

        .seller-details h1 {
            margin: 0 0 10px;
            font-size: 24px;
        }

        .seller-details p {
            margin: 5px 0;
        }

        .seller-badge {
            background: var(--primary-color);
            color: #000;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--secondary-bg);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 16px;
            color: var(--text-color);
        }

        .profile-form {
            background: var(--secondary-bg);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .form-title {
            font-size: 20px;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
            background: var(--background);
            color: var(--text-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .user-id-display {
            background: var(--background);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .user-id-display input {
            background: var(--secondary-bg);
            color: var(--text-color);
            font-family: monospace;
        }

        .help-text {
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.7;
            margin-top: 5px;
        }

        .required {
            color: #ff0000;
        }

        .btn {
            background: var(--primary-color);
            color: #000;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #00cc6a;
            transform: translateY(-2px);
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--background);
            padding: 20px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .modal-content input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid var(--border-color);
            border-radius: 5px;
        }

        /* New styles for preferences page */
        .form-container {
            width: 100%;
            max-width: 640px;
            background: rgba(255, 255, 255, 0.03);
            padding: 28px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            margin: 0 auto;
        }

        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .select,
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.02);
            color: inherit;
        }

        .btn {
            padding: 10px 14px;
            border-radius: 10px;
            border: 0;
            background: #44D62C;
            color: #001;
            font-weight: 700;
            cursor: pointer;
        }

        .note {
            color: #9aa6b2;
            font-size: 13px;
            margin-top: 8px;
        }

        .msg {
            margin: 10px 0;
            padding: 8px;
            border-radius: 8px;
        }

        .success {
            background: rgba(68, 214, 44, 0.06);
            color: #44D62C;
        }

        .error {
            background: rgba(255, 107, 107, 0.06);
            color: #ff6b6b;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .seller-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
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
                <div class="profile-image-container" tabindex="0" aria-label="Click to change profile picture">
                    <?php if (!empty($user["profile_image"]) && file_exists("Uploads/" . $user["profile_image"])): ?>
                        <img src="Uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Seller Avatar" class="seller-avatar" id="profileImage">
                    <?php else: ?>
                        <img src="Uploads/default-avatar.svg" alt="Default Avatar" class="seller-avatar" id="profileImage">
                    <?php endif; ?>
                    <i class="bi bi-pencil edit-icon"></i>
                </div>
                <div class="seller-details">
                    <h1><?php echo htmlspecialchars($user["seller_name"] ?: $user["fullname"]); ?></h1>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($user["phone"]); ?> <?php echo $user['phone_verified'] ? '(Verified)' : '(Unverified)'; ?></p>
                    <p><strong>Business Type:</strong> <?php echo htmlspecialchars($user["business_type"] ?: 'Not specified'); ?></p>
                    <p><strong>Rating:</strong> ⭐ <?php echo number_format($user["seller_rating"], 1); ?>/5.0</p>
                    <p><strong>Total Sales:</strong> $<?php echo number_format($user["total_sales"], 2); ?></p>
                </div>

                <!-- INSERTED: Preferences form moved into seller card for better UX -->
                <div class="form-container" style="margin-left:20px;min-width:320px;">
                    <h3 style="margin-top:0;color:#44D62C">Preferences</h3>
                    <?php if ($message): ?><div class="msg success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                    <?php if ($errors): ?><div class="msg error"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div><?php endif; ?>
                    <form method="post" id="prefFormInline" style="margin-top:10px;">
                        <div id="countryFieldsInline">
                            <div style="margin-bottom:6px;color:#9aa6b2;font-size:13px">Country preference</div>
                            <div class="form-row">
                                <select name="country_code" id="countrySelectInline" class="select" required>
                                    <option value="">Select country...</option>
                                    <?php foreach ($countries as $c):
                                        $sel = ($c['code'] === $currentCountryCode) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($c['code']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="country_name" id="countryNameInline" class="select" placeholder="Country name" value="<?php echo htmlspecialchars($currentCountryName); ?>" required>
                            </div>
                            <div style="margin:8px 0 6px;color:#9aa6b2;font-size:13px">Language (suggested)</div>
                            <div class="form-row">
                                <select name="language" id="languageSelectInline" class="select" required>
                                    <option value="en" <?php echo $currentLanguage==='en'?'selected':''; ?>>English</option>
                                    <option value="fr" <?php echo $currentLanguage==='fr'?'selected':''; ?>>Français</option>
                                    <option value="de" <?php echo $currentLanguage==='de'?'selected':''; ?>>Deutsch</option>
                                    <option value="es" <?php echo $currentLanguage==='es'?'selected':''; ?>>Español</option>
                                    <option value="pt" <?php echo $currentLanguage==='pt'?'selected':''; ?>>Português</option>
                                    <option value="zh" <?php echo $currentLanguage==='zh'?'selected':''; ?>>中文</option>
                                    <option value="ja" <?php echo $currentLanguage==='ja'?'selected':''; ?>>日本語</option>
                                    <option value="it" <?php echo $currentLanguage==='it'?'selected':''; ?>>Italiano</option>
                                    <option value="nl" <?php echo $currentLanguage==='nl'?'selected':''; ?>>Nederlands</option>
                                    <option value="tl" <?php echo $currentLanguage==='tl'?'selected':''; ?>>Filipino</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;margin-top:10px">
                            <button id="savePrefBtnInline" type="submit" name="save_preferences" class="btn">Save</button>
                            <div class="note" style="margin:0">Language change updates your session immediately.</div>
                        </div>
                    </form>
                </div>
                <!-- END preferences inserted -->
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
                    <label>Profile Picture</label>
                    <div class="file-selected" id="fileSelectedText"></div>
                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;">
                    <div class="help-text">Click the avatar above to upload a new profile picture (JPG, PNG only)</div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // OTP Modal Handling
            <?php if (isset($show_otp_modal) && $show_otp_modal): ?>
                document.getElementById('otpModal').classList.add('show');
                // For testing: Log OTP to console (remove in production)
                console.log('OTP for testing: <?php echo $_SESSION['otp'] ?? ''; ?>');
            <?php endif; ?>

            // Profile Image File Selection
            const profileImageContainer = document.querySelector('.profile-image-container');
            const profileImageInput = document.getElementById('profileImageInput');
            const fileSelectedText = document.getElementById('fileSelectedText');

            if (profileImageContainer && profileImageInput && fileSelectedText) {
                // Click handler for image
                profileImageContainer.addEventListener('click', function() {
                    console.log('Profile image clicked, triggering file input');
                    profileImageInput.click();
                });

                // Keyboard handler for accessibility
                profileImageContainer.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        console.log('Profile image activated via keyboard, triggering file input');
                        profileImageInput.click();
                    }
                });

                // Display selected file name
                profileImageInput.addEventListener('change', function() {
                    if (profileImageInput.files.length > 0) {
                        fileSelectedText.textContent = `Selected: ${profileImageInput.files[0].name}`;
                    } else {
                        fileSelectedText.textContent = '';
                    }
                });
            }
        });

        // country -> suggested language map (should mirror server-side)
        const countryLangMap = <?php echo json_encode($countryLangMap); ?>;

        const countrySelect = document.getElementById('countrySelect');
        const countryName = document.getElementById('countryName');
        const languageSelect = document.getElementById('languageSelect');

        // when country select changes, set country name and suggest language
        countrySelect.addEventListener('change', (e) => {
            const code = e.target.value;
            const option = e.target.selectedOptions[0];
            if (option) countryName.value = option.text;
            if (code && countryLangMap[code]) {
                // set suggested language (don't override if user already changed)
                languageSelect.value = countryLangMap[code];
            }
        });

        // If user modifies country name manually, keep code unchanged
        countryName.addEventListener('input', () => {
            // no-op, kept for accessibility or advanced UX
        });

        // small accessibility: set country name when page loads if empty
        if (!countryName.value && countrySelect.value) {
            countryName.value = countrySelect.selectedOptions[0]?.text || '';
        }

        // Script: if a global profile form exists, inject the preference fields into it
        document.addEventListener('DOMContentLoaded', function() {
            const externalForm = document.getElementById('profileForm'); // target form id on your main profile page
            const countryFields = document.getElementById('countryFields');
            if (externalForm && countryFields) {
                // Move the preference inputs into the external profile form so they submit with it
                externalForm.appendChild(countryFields);
                // Hide local save button to avoid duplicate controls (external profile form should handle submit)
                const saveBtn = document.getElementById('savePrefBtn');
                if (saveBtn) saveBtn.style.display = 'none';
            }

            // Keep existing behavior: when user selects country, suggest language
            const countryMap = <?php echo json_encode($countryLangMap); ?>;
            const countrySelect = document.getElementById('countrySelect');
            const countryName = document.getElementById('countryName');
            const languageSelect = document.getElementById('languageSelect');
            if (countrySelect) {
                countrySelect.addEventListener('change', function() {
                    const code = this.value;
                    const option = this.selectedOptions[0];
                    if (option) countryName.value = option.text;
                    if (code && countryMap[code]) languageSelect.value = countryMap[code];
                });
            }
        });
    </script>
</body>
</html>