<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup Successful</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }

    .success-container {
      background: white;
      padding: 40px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      text-align: center;
      width: 350px;
      animation: fadeIn 0.5s ease-in-out;
    }

    .success-container h2 {
      color: #28a745;
      margin-bottom: 15px;
    }

    .success-container p {
      margin-bottom: 20px;
      color: #555;
    }

    .success-container a {
      display: inline-block;
      padding: 12px 20px;
      background: #ff6600;
      color: white;
      border-radius: 5px;
      font-weight: bold;
      text-decoration: none;
      transition: background 0.3s;
    }

    .success-container a:hover {
      background: #e65c00;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
  </style>
</head>
<body>
  <div class="success-container">
    <h2>Signup Completed!</h2>
    <p>Your account has been created successfully.</p>
    <a href="login_users.php">Go to Login</a>
  </div>
</body>
</html>
