<?php
// Manual Session ID Handling for Web (Cross-Origin)
$receivedSessionId = $_REQUEST['session_id'] ?? ($_POST['session_id'] ?? null);
if ($receivedSessionId && !empty($receivedSessionId)) {
    session_id($receivedSessionId);
}
require 'config.php';
require 'google_config.php';
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id_token = $input['id_token'] ?? '';
$access_token = $input['access_token'] ?? '';

// Mobile apps send id_token (preferred) or access_token as fallback
if (empty($id_token) && empty($access_token)) {
    echo json_encode(['success' => false, 'message' => 'Token missing']);
    exit;
}

$google_user = null;

// Try ID Token verification first (more secure and reliable for mobile)
if (!empty($id_token)) {
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $token_info = json_decode($response, true);
        // Verify the token is for our app
        if (isset($token_info['aud']) && $token_info['aud'] === GOOGLE_CLIENT_ID) {
            $google_user = [
                'id' => $token_info['sub'],
                'email' => $token_info['email'] ?? '',
                'name' => $token_info['name'] ?? ($token_info['given_name'] ?? 'User'),
                'picture' => $token_info['picture'] ?? ''
            ];
        }
    }
}

// Fallback to access token verification if id_token failed
if ($google_user === null && !empty($access_token)) {
    $url = "https://www.googleapis.com/oauth2/v2/userinfo";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $user_info = json_decode($response, true);
        if (isset($user_info['id'])) {
            $google_user = [
                'id' => $user_info['id'],
                'email' => $user_info['email'] ?? '',
                'name' => $user_info['name'] ?? 'User',
                'picture' => $user_info['picture'] ?? ''
            ];
        }
    }
}

if ($google_user === null || !isset($google_user['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Google Token - verification failed']);
    exit;
}

$google_id = $google_user['id'];
$email = $google_user['email'];
$name = $google_user['name'];
$picture = $google_user['picture'] ?? '';

// Check DB
try {
    // Check by google_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$google_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Check by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Link Account
            $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$google_id, $user['id']]);
        } else {
            // Create New User
            // Username generation
            $username = explode(' ', $name)[0] . rand(100,999);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id, profile_picture) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $google_id, $picture]);
            $user_id = $pdo->lastInsertId();
            
            // Re-fetch
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['profile_picture'] = $user['profile_picture'];

        echo json_encode(['success' => true, 'user' => $user, 'session_id' => session_id()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User could not be created or found']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>
