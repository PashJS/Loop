<?php
require_once 'vendor/autoload.php';

// Google Client setup
$client = new Google_Client();
$client->setClientId(getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');       // Set via environment variable
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET'); // Set via environment variable
$client->setRedirectUri('http://localhost/floxwatch/google-fallback.php');
$client->addScope("email");
$client->addScope("profile");

// Check if Google sent the authorization code
if (isset($_GET['code'])) {
    // Exchange code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    // Get user info
    $oauth = new Google_Service_Oauth2($client);
    $user_info = $oauth->userinfo->get();

    // Display user info
    echo "<h2>Google Login Successful!</h2>";
    echo "Name: " . $user_info->name . "<br>";
    echo "Email: " . $user_info->email . "<br>";
    echo "<img src='" . $user_info->picture . "' alt='Profile Picture'>";
} else {
    echo "No authorization code received.";
}
?>
