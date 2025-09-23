<?php
// SMTP-aware mail sender. Uses PHPMailer if available; otherwise falls back to mail().
// Configure credentials in config.php (Gmail App Password recommended).

require_once __DIR__ . '/config.php';

function send_email($to, $subject, $body, $from = null, $fromName = null) {
    $to = (string)$to;
    if ($to === '') { return false; }
    $from = $from ?: (defined('SMTP_FROM') ? SMTP_FROM : 'no-reply@meta-shark.local');
    $fromName = $fromName ?: (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Meta Shark');

    // Try PHPMailer if available and SMTP enabled
    if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
        $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($phpmailerPath)) {
            require_once $phpmailerPath;
        }
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                if (strtolower(SMTP_SECURE) === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                } else {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = SMTP_PORT;
                }
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = $body;
                $mail->send();
                return true;
            } catch (Exception $e) {
                // fall through to mail()
            }
        }
    }

    // Fallback: PHP mail()
    $headers = [];
    $headers[] = 'From: ' . $from;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headersStr = implode("\r\n", $headers);
    return @mail($to, $subject, $body, $headersStr);
}


