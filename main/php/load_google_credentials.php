<?php
// Securely load Google credentials from outside the web root
$googleCredentialsPath = 'C:/xampp/secure-config/credentials-offline.json'; //changed to offline status muna
if (!file_exists($googleCredentialsPath)) {
    die('Google credentials file not found.');
}
$googleCredentials = json_decode(file_get_contents($googleCredentialsPath), true);
// ... use $googleCredentials as needed ...
