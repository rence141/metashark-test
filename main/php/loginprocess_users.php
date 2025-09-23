<?php
session_start();
include("db.php");
include_once("email.php");
include_once("contacts_sync.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Check if user exists
    $sql = "SELECT id, fullname, email, password, role, is_verified, verification_code, verification_expires FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user["password"])) {
            // ALWAYS: generate OTP and require verification on every login
            $code = sprintf('%06d', random_int(0, 999999));
            $expiryAt = date('Y-m-d H:i:s', time() + 15 * 60);
            $upd = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param("ssi", $code, $expiryAt, $user['id']);
                $upd->execute();
            }

            // Stash for verify step
            $_SESSION['pending_verification_user_id'] = $user['id'];
            $_SESSION['pending_verification_email'] = $user['email'];
            $_SESSION['pending_verification_role'] = $user['role'] ?? 'buyer';

            // Send email with code
            $subject = 'Your SaysonCo verification code';
            $body = "Hello,\n\nYour verification code is: $code\nThis code expires in 15 minutes.\n\nIf you did not request this, you can ignore this email.";
            @send_email($user['email'], $subject, $body);

            header("Location: verify_account.php?email=" . urlencode($user['email']));
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No account found with that email.";
    }
    
    // If there's an error, redirect back to login page with error
    if (!empty($error)) {
        header("Location: login_users.php?error=" . urlencode($error));
        exit();
    }
}
?>
