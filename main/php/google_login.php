<?php
require __DIR__ . '/../../vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setAuthConfig('C:/xampp/secure-config/credentials_offline.json');
$client->setRedirectUri('http://localhost/SaysonCotest/main/php/google_callback.php');
$client->addScope("email");
$client->addScope("profile");

// Redirect to Google
$authUrl = $client->createAuthUrl();
header("Location: $authUrl");
exit;

?>