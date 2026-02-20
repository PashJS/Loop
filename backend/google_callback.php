<?php
// backend/google_callback.php
session_start();
require_once 'config.php';
require_once 'google_config.php';
require_once 'log_activity.php';

if (!isset($_GET['code'])) {
    die('Authorization code not provided.');
}

$code = $_GET['code'];

// 1. Exchange code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$post_fields = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URL,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Often needed on local Windows/MAMP
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$data = json_decode($response, true);
curl_close($ch);

if (!isset($data['access_token'])) {
    // Log the error for debugging
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    
    $errorMsg = "HTTP: " . $httpCode . " | CURL: " . $curlError . " | Response: " . $response;
    file_put_contents($logDir . '/google_auth_error.log', "[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", FILE_APPEND);
    
    die('Failed to obtain access token. Error details: ' . $errorMsg);
}

$access_token = $data['access_token'];

// 2. Fetch User Info
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($user_info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$user_response = curl_exec($ch);
$userInfoError = curl_error($ch);
$userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$google_user = json_decode($user_response, true);
curl_close($ch);

if (!isset($google_user['id'])) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    
    $errorMsg = "UserInfo HTTP: " . $userHttpCode . " | CURL: " . $userInfoError . " | Response: " . $user_response;
    file_put_contents($logDir . '/google_auth_error.log', "[" . date('Y-m-d H:i:s') . "] " . $errorMsg . "\n", FILE_APPEND);
    
    die('Failed to fetch user information from Google. Details: ' . $errorMsg);
}

$google_id = $google_user['id'];
$email = $google_user['email'];
$name = $google_user['name'];

try {
    // SCENARIO A: User is already logged in (Linking account)
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
        $stmt->execute([$google_id, $_SESSION['user_id']]);
        header('Location: ../frontend/settings.php?sync=success');
        exit;
    }

    // SCENARIO B: User is NOT logged in (Sign In with Google)
    // Check if user exists by google_id
    $stmt = $pdo->prepare("SELECT id, username, profile_picture FROM users WHERE google_id = ?");
    $stmt->execute([$google_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Check if user exists by email and link them
        $stmt = $pdo->prepare("SELECT id, username, profile_picture FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $update = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $update->execute([$google_id, $user['id']]);
        } else {
            // New user - optionally create account or error
            // For now, let's redirect to register with info or return error
            header('Location: ../frontend/loginb.php?error=no_account');
            exit;
        }
    }

    // Log the user in
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['profile_picture'] = $user['profile_picture'];

    logLoginActivity($pdo, $user['id']);

    header('Location: ../frontend/home.php');

} catch (PDOException $e) {
    die('Database error during Google sync: ' . $e->getMessage());
}
?>
