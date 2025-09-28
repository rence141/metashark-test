<?php
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        // connect to DB
        $conn = new mysqli("localhost", "root", "", "myshop");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (fullname, email, phone, password) 
                VALUES ('$fullname', '$email', '$phone', '$hashed')";
        
        if ($conn->query($sql)) {
            echo "Signup successful!";
        } else {
            $error = "Error: " . $conn->error;
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - MyShop</title>
  <link rel="icon" type="image/png" href="uploads/logo1.png">
  <style>
/* General Styles */
body {
  font-family: Arial, sans-serif;
  background:rgb(57, 56, 56);
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  margin: 0;
}

/* Container */
.form-container {
  background: white;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  width: 350px;
  text-align: center;
  animation: fadeIn 0.5s ease-in-out;
  position: relative;
}

/* Logo */
.form-container img.logo {
  width: 80px;
  height: 80px;
  object-fit: contain;
  margin-bottom: 15px;
}

/* Heading */
.form-container h2 {
  margin-bottom: 20px;
  color: #333;
}

/* Input Fields */
.form-container input {
  width: 100%;
  padding: 12px;
  margin: 10px 0;
  border: 1px solid #ccc;
  border-radius: 5px;
  font-size: 0.95rem;
}

/* Button */
.form-container button {
  width: 100%;
  padding: 12px;
  background:rgb(15, 20, 5);
  border: none;
  color: white;
  font-size: 1rem;
  font-weight: bold;
  border-radius: 5px;
  cursor: pointer;
  transition: background 0.3s ease-in-out;
}

.form-container button:hover {
  background:rgb(0, 230, 8);
}

/* Links */
.form-container p {
  margin-top: 15px;
  font-size: 0.9rem;
}

.form-container a {
  color:rgb(85, 255, 0);
  text-decoration: none;
  font-weight: bold;
}

.form-container a:hover {
  text-decoration: underline;
}

/* Error Messages */
.error {
  color: red;
  margin-top: 10px;
  font-size: 0.9rem;
}

/* Animation */
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
  </style>
</head>
<body>

  <div class="form-container">
    <!-- Logo at the top -->
    <img src="uploads/logo1.png" alt="MyShop Logo" class="logo">

    <h2>Create Account</h2>
    <form action="signupprocess_users.php" method="POST">
      <input type="text" name="fullname" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="phone" placeholder="Phone Number" maxlength="15" required>
      <input type="password" name="password" placeholder="Password" required>
      <input type="password" name="confirm_password" placeholder="Confirm Password" required>
      <button type="submit">Sign Up</button>
      <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    </form>
    <p> Already have an account? <a href="login_users.php">Login</a></p>
  </div>
</body>
</html>
