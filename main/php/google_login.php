<?php
require __DIR__ . '/../../vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setRedirectUri('http://localhost/SaysonCo/main/php/google_callback.php');
$client->addScope("email");
$client->addScope("profile");

// Redirect user to Google's OAuth consent screen
$authUrl = $client->createAuthUrl();
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
