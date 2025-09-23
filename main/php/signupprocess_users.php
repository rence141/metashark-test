<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Validate password match
    if ($password !== $confirm_password) {
        die("<p style='color:red;'>Passwords do not match. <a href='signup.html'>Try again</a></p>");
    }

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        die("<p style='color:red;'>Email already registered. <a href='signup.html'>Try again</a></p>");
    }
    $checkEmail->close();

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into DB
    $sql = "INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);

    if ($stmt->execute()) {
        // Get the newly created user's ID
        $new_user_id = $conn->insert_id;

        // Set default profile image if column exists and is empty
        $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $upd = $conn->prepare("UPDATE users SET profile_image = 'default-avatar.svg' WHERE id = ? AND (profile_image IS NULL OR profile_image = '')");
            if ($upd) { $upd->bind_param("i", $new_user_id); $upd->execute(); }
        }
        
        // Automatically log the user in by setting session variables
        $_SESSION["user_id"] = $new_user_id;
        $_SESSION["fullname"] = $fullname;
        $_SESSION["email"] = $email;
        
        // Redirect to main page (user is now logged in)
        header("Location: shop.php");
        exit();
    } else {
        die("<p style='color:red;'>Error: Could not register user. <a href='signup_users.php'>Try again</a></p>");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up Process</title>
      <link rel="icon" type="image/png" href="uploads/logo1.png">

</html>