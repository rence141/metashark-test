<?php
// File: includes/email_functions.php

/**
 * Helper to send email using PHP mail()
 * Note: On XAMPP/Localhost, this requires sendmail configuration in php.ini
 * or the use of a library like PHPMailer.
 */
function sendCustomEmail($to, $subject, $htmlContent) {
    // Set headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Admin <noreply@metashark.com>" . "\r\n"; 
    $headers .= "X-Mailer: PHP/" . phpversion();

    // The @ symbol suppresses warnings if XAMPP isn't configured for email
    return @mail($to, $subject, $htmlContent, $headers);
}

// Function called when a user is suspended
function sendSuspensionEmail($email, $fullname) {
    $subject = "Account Suspended";
    $message = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd;'>
        <h2 style='color: #d9534f;'>Account Suspended</h2>
        <p>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
        <p>Your account has been suspended by the administrator.</p>
        <p>You will not be able to log in until further notice.</p>
        <br>
        <p>Regards,<br>System Admin</p>
    </div>";
    
    return sendCustomEmail($email, $subject, $message);
}

// Function called when a user is unsuspended
function sendUnsuspensionEmail($email, $fullname) {
    $subject = "Account Reactivated";
    $message = "
    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd;'>
        <h2 style='color: #5cb85c;'>Account Reactivated</h2>
        <p>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
        <p>Good news! Your account suspension has been lifted.</p>
        <p>You may now log in and access our services.</p>
        <br>
        <p>Regards,<br>System Admin</p>
    </div>";

    return sendCustomEmail($email, $subject, $message);
}
?>