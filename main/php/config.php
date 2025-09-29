<?php
// config.php - SMTP Settings (keep secure; .gitignore this in version control)
// Note: This is your provided config—ensure the app password is correct (no spaces).

// Enable SMTP mode
define('SMTP_ENABLED', true);

// Gmail SMTP (your details)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // 587 for STARTTLS (recommended), 465 for SSL
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_USERNAME', 'lorenzezz0987@gmail.com'); // Your full Gmail address
define('SMTP_PASSWORD', 'wvenyuojjtcvgqhh'); // Your 16-char app password

// From address (must match Username for Gmail)
define('SMTP_FROM', 'lorenzezz0987@gmail.com');
define('SMTP_FROM_NAME', 'Meta Shark');
?>