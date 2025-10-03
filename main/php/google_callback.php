<?php
require __DIR__ . '/../../vendor/autoload.php';
include("db.php");
session_start();

$client = new Google_Client();
$client->setAuthConfig('C:/xampp/secure-config/credentials.json');
$client->setRedirectUri('http://localhost/SaysonCo/main/php/google_callback.php');
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        error_log("Google OAuth error: " . $token['error']);
        header("Location: ../../login_users.php?error=Google login failed");
        exit;
    }

    $client->setAccessToken($token);
    $oauth = new Google_Service_Oauth2($client);
    $googleUser = $oauth->userinfo->get();

    // Store Google user data in session
    $_SESSION['google_email'] = $googleUser->email;
    $_SESSION['google_name'] = $googleUser->name;

    // Log for debugging
    error_log("Google callback set session: email=" . $googleUser->email . ", name=" . $googleUser->name);

    // Ensure session is written
    session_write_close();

    // Redirect to google_login_process.php
    header("Location: http://localhost/SaysonCo/main/php/google_login_process.php");
    exit;
} else {
    error_log("Google OAuth code not provided");
    header("Location: ../../login_users.php?error=Google login failed");
    exit;
}
?>