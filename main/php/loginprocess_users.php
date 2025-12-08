<?php
session_start();
require_once 'db.php'; // Ensure this points to your database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize Input
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        header("Location: login_users.php?error=Please fill in all fields");
        exit();
    }

    // 2. Prepare Statement to fetch user
    // FIX: Changed 'first_name' to 'fullname' to match your database
    $stmt = $conn->prepare("SELECT id, fullname, password, role, is_suspended FROM users WHERE email = ?");
    
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 3. Verify Password
            if (password_verify($password, $user['password'])) {

                // --- CRITICAL SUSPENSION CHECK ---
                if ((int)$user['is_suspended'] === 1) {
                    // Store email temporarily to auto-fill the appeal form
                    $_SESSION['temp_email'] = $email;
                    
                    // Kill any existing session data just in case
                    unset($_SESSION['user_id']);
                    unset($_SESSION['role']);

                    // Redirect to the "Access Restricted" page
                    header("Location: suspended_account.php");
                    exit();
                }
                // ----------------------------------

                // 4. Successful Login (Not Suspended)
                $_SESSION['user_id'] = $user['id'];
                
                // FIX: Changed to use 'fullname'
                $_SESSION['user_name'] = $user['fullname']; 
                
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: shop.php");
                }
                exit();

            } else {
                // Wrong Password
                header("Location: login_users.php?error=Incorrect password");
                exit();
            }
        } else {
            // User not found
            header("Location: login_users.php?error=Account not found");
            exit();
        }
        $stmt->close();
    } else {
        // Database error
        header("Location: login_users.php?error=System error, please try again");
        exit();
    }
} else {
    header("Location: login_users.php");
    exit();
}
?>