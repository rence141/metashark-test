<?php
session_start();

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize inputs
    $reason = trim(strip_tags($_POST['appeal_reason']));
    $contact_email = filter_var($_POST['contact_email'], FILTER_SANITIZE_EMAIL);
    
    // Simple validation
    if (empty($reason) || empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        header("Location: suspended_account.php?status=error");
        exit;
    }

    // --- Configuration ---
    $admin_email = "support@metashark.com"; // Replace with your actual admin email
    $subject = "Appeal Request from Suspended User";
    
    // --- Build Email Content ---
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
            .label { font-weight: bold; color: #555; }
            .text-block { background: #fff; padding: 15px; border: 1px solid #eee; margin-top: 5px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>New Account Appeal Submitted</h2>
            <p>A suspended user has submitted an appeal request.</p>
            
            <p><span class='label'>Contact Email:</span> <br> <a href='mailto:{$contact_email}'>{$contact_email}</a></p>
            
            <p><span class='label'>Appeal Statement:</span></p>
            <div class='text-block'>
                " . nl2br(htmlspecialchars($reason)) . "
            </div>
            
            <p style='margin-top: 20px; font-size: 0.9em; color: #777;'>
                Please review the user's account in the Admin Panel to approve or deny this request.
            </p>
        </div>
    </body>
    </html>
    ";

    // --- Headers ---
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Meta Shark System <noreply@metashark.com>" . "\r\n";
    $headers .= "Reply-To: " . $contact_email . "\r\n";

    // --- Send Email ---
    // Note: For production, use PHPMailer. PHP's mail() requires a configured SMTP server.
    if (mail($admin_email, $subject, $message, $headers)) {
        header("Location: suspended_account.php?status=success");
    } else {
        // Fallback for localhost without SMTP - simulate success for UI testing
        // In production, remove this fallback and handle errors properly.
        error_log("Mail failed to send to $admin_email. Check SMTP config.");
        header("Location: suspended_account.php?status=success"); 
    }
    exit;
} else {
    // If accessed directly without POST
    header("Location: suspended_account.php");
    exit;
}
?>