<?php
// backend/login.php
// Manual Session ID Handling for Web (Cross-Origin)
$receivedSessionId = $_REQUEST['session_id'] ?? ($_POST['session_id'] ?? null);
if ($receivedSessionId && !empty($receivedSessionId)) {
    session_id($receivedSessionId);
}
require_once 'cors.php'; // Keep cors.php inclusion
require 'config.php';
header('Content-Type: application/json');
session_start();

// Ensure new logins have a timestamp for security
$_SESSION['login_timestamp'] = time(); 

require 'config.php';
require_once 'log_activity.php';

// Get request payload (supports JSON fetch + form fallback)
$rawInput = file_get_contents('php://input');

$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

// Validate input
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in both email and password.'
    ]);
    exit;
}


try {
    // Prepare statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT id, password, username, profile_picture, banned_until, two_factor_enabled, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $passMatch = password_verify($password, $user['password']);
        
        // Fallback for plaintext (ID 6 issue)
        if (!$passMatch && $password === $user['password']) {
            $passMatch = true;
        }

        if ($passMatch) {
            // Check if user is banned
            if (!empty($user['banned_until']) && strtotime($user['banned_until']) > time()) {
                echo json_encode([
                    'success' => false,
                    'banned' => true,
                    'redirect' => 'banned.php?uid=' . $user['id'],
                    'message' => 'Your account has been suspended.'
                ]);
                exit;
            }

            // Check if 2FA is enabled
            if (isset($user['two_factor_enabled']) && $user['two_factor_enabled']) {
                // Generate 2FA code
                $code = sprintf("%06d", mt_rand(0, 999999));
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Save code to database
                $updateStmt = $pdo->prepare("UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?");
                $updateStmt->execute([$code, $expires, $user['id']]);

                // Send email
                require_once 'mailer.php';
                $emailSent = sendFloxEmail(
                    $user['email'],
                    'Your FloxWatch 2FA Code',
                    "Hello " . $user['username'] . ",\n\nYour login verification code is: " . $code . "\n\nThis code will expire in 10 minutes.",
                    false
                );

                if ($emailSent) {
                    echo json_encode([
                        'success' => true,
                        'message' => '2FA code sent to your email.',
                        'two_factor_required' => true,
                        'email' => $user['email']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to send 2FA code. Please contact support.'
                    ]);
                }
            } else {
                // Normal successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                // Create remember token for persistent login
                require_once 'auth_helper.php';
                createRememberToken($user['id']);

                $deviceInfo = logLoginActivity($pdo, $user['id']);
                
                require_once 'mailbox_utils.php';
                sendMailboxNotification($pdo, $user['id'], 'alert', 'New Login: ' . $deviceInfo, 'A new login attempt was successful from ' . $deviceInfo . '. If this wasn\'t you, please secure your account immediately.');

                unset($user['password']); // SECURE: Don't send hash to client
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful!',
                    'user' => $user,
                    'session_id' => session_id()
                ]);
            }
        } else {
            // Invalid credentials
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password.'
            ]);
        }
    } else {
        // Invalid credentials
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password.'
        ]);
    }
} catch (PDOException $e) {
    // Database error
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again later.'
    ]);
}
?>
