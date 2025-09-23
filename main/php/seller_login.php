<?php
session_start();
include("db.php");
include_once("email.php");
include_once("contacts_sync.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Check if user exists and has seller role
    $sql = "SELECT id, fullname, email, password, role, is_verified, verification_code, verification_expires FROM users WHERE email = ? AND (role = 'seller' OR role = 'admin')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user["password"])) {
            $isVerified = isset($user['is_verified']) ? (int)$user['is_verified'] === 1 : 1;
            if (!$isVerified) {
                // Always generate a fresh 6-digit code (cryptographically secure)
                $code = sprintf('%06d', random_int(0, 999999));
                $expiryAt = date('Y-m-d H:i:s', time() + 15 * 60);
                $upd = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param("ssi", $code, $expiryAt, $user['id']);
                    $upd->execute();
                }
                $_SESSION['pending_verification_user_id'] = $user['id'];
                $_SESSION['pending_verification_email'] = $user['email'];
                $_SESSION['pending_verification_role'] = $user['role'];

                // Send email with code
                $subject = 'Hello from Meta Shark';
                $body = "Hello,\n\nYour verification code is: $code\nThis code expires in 15 minutes.\n\nIf you did not request this, you can ignore this email.";
                @send_email($user['email'], $subject, $body);
                header("Location: verify_account.php?email=" . urlencode($user['email']));
                exit();
            }

            // Store session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["fullname"] = $user["fullname"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            // Redirect to seller dashboard
            @sync_user_contact_fields($conn, $user['id']);
            header("Location: seller_dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No seller account found with that email. Please sign up as a seller first.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - Meta Shark</title>
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
        }

        .form-container {
            background: #111111;
            padding: 40px;
            border-radius: 15px;
            border: 1px solid #333333;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .form-title {
            font-size: 2rem;
            color: #44D62C;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-subtitle {
            color: #888;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #44D62C;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #44D62C;
            border-radius: 8px;
            background: #1a1a1a;
            color: #FFFFFF;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            box-shadow: 0 0 10px rgba(68, 214, 44, 0.3);
            border-color: #36b020;
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

        .error {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }

        .links {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #333333;
        }

        .links p {
            color: #888;
            margin-bottom: 10px;
            color: #44D62C;
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
    </style>
</head>
<body>
    <a href="login_users.php" class="back-link">← Back to Regular Login</a>
    
    <div class="form-container">
        <div class="seller-badge">Seller Portal</div>
        <h1 class="form-title">Seller Login</h1>
        <p class="form-subtitle">Access your seller dashboard</p>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Login as Seller</button>
        </form>

        <div class="links">
            <p>Not a seller yet?</p>
            <a href="become_seller.php">Become a Seller</a>
            <br><br>
            <p>Regular user?</p>
            <a href="login_users.php">Regular Login</a>
            <br><br>
            <p>Don't have an account?</p>
            <a href="seller_signup.php">Sign Up as Seller</a>
        </div>
    </div>
</body>
</html>
