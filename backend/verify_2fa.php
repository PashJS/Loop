<?php
// Manual Session ID Handling for Web (Cross-Origin)
$receivedSessionId = $_REQUEST['session_id'] ?? ($_POST['session_id'] ?? null);
if ($receivedSessionId && !empty($receivedSessionId)) {
    session_id($receivedSessionId);
}
// backend/verify_2fa.php
header('Content-Type: application/json');
session_start();
require 'config.php';
require_once 'log_activity.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');

if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Email and code are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, profile_picture, two_factor_code, two_factor_expires FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $now = date('Y-m-d H:i:s');
        if ($user['two_factor_code'] === $code && $user['two_factor_expires'] > $now) {
            // Code is valid - Start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['profile_picture'] = $user['profile_picture'];

            // Clear code from DB
            $clearStmt = $pdo->prepare("UPDATE users SET two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?");
            $clearStmt->execute([$user['id']]);

            logLoginActivity($pdo, $user['id']);

            echo json_encode([
                'success' => true, 
                'message' => 'Verification successful!',
                'user' => $user,
                'session_id' => session_id()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
