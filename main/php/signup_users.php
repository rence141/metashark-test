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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="../../main/js/theme-system.js"></script>
  <style>
/* General Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --primary-color: #00ff88;
  --secondary-color: #00d4ff;
  --accent-color: #00ff88;
  --text-primary: #333;
  --text-secondary: #6c757d;
  --background-primary: linear-gradient(135deg,rgb(2, 2, 4) 0%,rgb(14, 90, 5) 25%,rgb(17, 147, 22) 50%,rgb(28, 255, 28) 75%,rgb(0, 0, 0) 100%);
  --card-background: white;
  --border-color: rgba(0, 0, 0, 0.1);
  --shadow-color: rgba(0, 0, 0, 0.3);
  --placeholder-color: rgb(24, 195, 5);
  --error-color: #ff4757;
}

.theme-dark {
  --text-primary: #e0e0e0;
  --text-secondary: #bbb;
  --background-primary: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 25%, #2a2a2a 50%, #3a3a3a 75%, #000000 100%);
  --card-background: #1e1e1e;
  --border-color: rgba(255, 255, 255, 0.1);
  --shadow-color: rgba(0, 0, 0, 0.5);
  --placeholder-color:rgb(87, 85, 85);
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: var(--background-primary);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
  overflow: hidden;
  color: var(--text-primary);
}

/* Animated background particles */
body::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
              radial-gradient(circle at 80% 20%, rgba(119, 255, 126, 0.3) 0%, transparent 50%),
              radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
  animation: backgroundShift 20s ease-in-out infinite;
}

@keyframes backgroundShift {
  0%, 100% { opacity: 0.7; }
  50% { opacity: 1; }
}

/* Container */
.form-container {
  background: var(--card-background);
  padding: 40px;
  border-radius: 20px;
  width: 100%;
  max-width: 400px;
  text-align: center;
  position: relative;
  z-index: 2;
  box-shadow: 0 20px 40px var(--shadow-color);
  border: 3px solid transparent;
  background-clip: padding-box;
  animation: fadeIn 0.5s ease-in-out;
}

.form-container::before {
  content: '';
  position: absolute;
  top: -3px;
  left: -3px;
  right: -3px;
  bottom: -3px;
  background: var(--card-background);
  border-radius: 20px;
  z-index: -1;
}

/* Logo */
.form-container img.logo {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  margin: 0 auto 20px;
  display: block;
  object-fit: cover;
  box-shadow: 0 10px 20px rgba(0, 255, 47, 0.3);
  animation: logoPulse 2s ease-in-out infinite;
}

@keyframes logoPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

/* Heading */
.form-container h2 {
  margin-bottom: 30px;
  color: var(--primary-color);
  font-size: 28px;
  font-weight: 700;
}

/* Input Fields */
.form-container input {
  width: 100%;
  padding: 15px 20px;
  margin: 10px 0;
  border: 2px solid #e1e5e9;
  border-radius: 12px;
  font-size: 16px;
  background: #f8f9fa;
  transition: all 0.3s ease;
  outline: none;
}

.form-container input:focus {
  border-color: #00d4ff;
  background: white;
  box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
  transform: translateY(-2px);
}

.form-container input::placeholder {
  color: var(--placeholder-color);
}

/* Button */
.form-container button {
  width: 100%;
  padding: 15px;
  background: #000;
  border: 2px solid var(--primary-color);
  color: white;
  font-size: 16px;
  font-weight: 600;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
}

.form-container button:hover {
  background: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(1, 235, 28, 0.2);
}

/* Links */
.form-container p {
  margin: 15px 0;
  font-size: 14px;
  color: var(--text-secondary);
}

.form-container a {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s ease;
}

.form-container a:hover {
  color: var(--secondary-color);
}

/* Error Messages */
.error {
  color: var(--error-color);
  background: rgba(255, 71, 87, 0.1);
  padding: 10px;
  border-radius: 8px;
  margin: 10px 0;
  font-size: 14px;
  border-left: 3px solid var(--error-color);
}

/* Animation */
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

/* Responsive Design */
@media (max-width: 480px) {
  .form-container {
    padding: 30px 20px;
    margin: 10px;
  }
  
  .form-container h2 {
    font-size: 24px;
  }
  
  .form-container img.logo {
    width: 60px;
    height: 60px;
  }
}
  </style>
</head>
<body>

  <div class="form-container">
    <img src="uploads/logo1.png" alt="MyShop Logo" class="logo">
    <h2>Create Account</h2>

    <form action="signupprocess_users.php" method="POST">
      <input type="text" name="fullname" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="phone" placeholder="Phone Number" maxlength="15" required>
      <input type="password" name="password" placeholder="Password" required>
      <input type="password" name="confirm_password" placeholder="Confirm Password" required>
      <button type="submit">Sign Up</button>
      
      <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
    </form>

    <p>Already have an account? <a href="login_users.php">Login</a></p>
  </div>
</body>
</html>
