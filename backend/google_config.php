<?php
// backend/google_config.php

// 1. Paste your full Client ID and Client Secret here
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID'); 
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');

// 2. IMPORTANT: This MUST match what you put in Google Cloud Console
// If you access your site via http://localhost:8888, you must include the port!
define('GOOGLE_REDIRECT_URL', 'http://localhost/FloxWatch/backend/google_callback.php');

/**
 * Generate the Google Auth URL
 */
function getGoogleAuthUrl() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
        'access_type' => 'offline',
        'prompt' => 'select_account'
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}
?>
