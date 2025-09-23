<?php
// Email/SMTP configuration. Fill these with your Gmail details.

// Gmail SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // 587 (TLS) or 465 (SSL)
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Your Gmail address and App Password (NOT your normal password)
define('SMTP_USERNAME', 'lorenzezz0987@gmail.com');
define('SMTP_PASSWORD', 'qatygundsjzbkmnf');

// From headers
define('SMTP_FROM', 'lorenzezz0987@gmail.com');
define('SMTP_FROM_NAME', 'Meta Shark');

// Toggle to force SMTP usage (if PHPMailer available)
define('SMTP_ENABLED', true);


