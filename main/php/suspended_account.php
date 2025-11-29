<?php
// Start session, though we clear the user's session before redirecting here,
// we might use the session later for tracking.
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Access Restricted - Meta Shark</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f8f8;
            color: #333;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            margin-bottom: 20px;
        }
        .icon-box {
            color: #cc0000;
            font-size: 60px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 28px;
            color: #cc0000;
            margin-bottom: 10px;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .appeal-form {
            text-align: left;
            margin-top: 30px;
            border-top: 1px dashed #ddd;
            padding-top: 20px;
        }
        .appeal-form h2 {
            font-size: 20px;
            color: #1f2937;
            margin-bottom: 15px;
            text-align: center;
        }
        .appeal-form label {
            display: block;
            font-weight: 600;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        .appeal-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            resize: vertical;
            min-height: 150px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .appeal-form button {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: background 0.2s;
        }
        .appeal-form button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-box">
            <i class="bi bi-slash-circle-fill"></i>
        </div>
        <h1>Account Access Restricted</h1>
        <p>
            We regret to inform you that **your account has been suspended due to a violation of our Terms of Service or security protocol.** You are currently blocked from logging in.
        </p>
        <p>
            Please review our Terms of Service. If you believe this action was taken in error, you may file an appeal using the form below.
        </p>
        
        <div class="appeal-form">
            <h2><i class="bi bi-chat-dots"></i> File an Appeal</h2>
            <form action="appeal_handler.php" method="POST">
                <!-- User email/ID would normally be pre-filled via a secure token/cookie -->
                <label for="appeal_reason">Reason for Appeal / Explanation of Violation:</label>
                <textarea id="appeal_reason" name="appeal_reason" required placeholder="Explain why your account should be reinstated or why the suspension was an error."></textarea>
                
                <label for="contact_email">Preferred Contact Email (Must be working):</label>
                <!-- This field assumes the user remembers their email -->
                <input type="email" id="contact_email" name="contact_email" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;">

                <button type="submit">Submit Appeal</button>
            </form>
        </div>
    </div>
</body>
</html>