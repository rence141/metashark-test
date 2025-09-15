<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Check if user exists
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user["password"])) {
            // Store session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["fullname"] = $user["fullname"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"] ?? 'buyer';

            // Set a session variable to indicate successful login
            $_SESSION["login_success"] = true;
            
            // Redirect to shop front page
            header("Location: shop.php");
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
