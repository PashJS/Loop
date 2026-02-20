<?php
// backend/resetPassword.php - Reset password with code
header('Content-Type: application/json');
session_start();
require 'config.php';
require_once 'log_activity.php';


if (!isset($_POST['email']) || !isset($_POST['code']) || !isset($_POST['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email, code, and password are required.'
    ]);
    exit;
}

$email = trim($_POST['email']);
$code = trim($_POST['code']);
$password = $_POST['password'];

if (strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters.'
    ]);
    exit;
}

try {
    // Verify code
    $stmt = $pdo->prepare("
        SELECT id, email, expires_at, used 
        FROM password_resets 
        WHERE email = ? AND code = ? AND used = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$email, $code]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired reset code.'
        ]);
        exit;
    }
    
    // Check if code expired
    if (strtotime($reset['expires_at']) < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'Reset code has expired. Please request a new one.'
        ]);
        exit;
    }
    
    // Update password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedPassword, $email]);
    
    // Fetch user info for auto-login
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mark code as used
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
    $stmt->execute([$reset['id']]);
    
    // Auto-login
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    logLoginActivity($pdo, $user['id']);
    
    require_once 'mailbox_utils.php';
    sendMailboxNotification($pdo, $user['id'], 'security', 'Password Reset Successful', 'Your account password has been successfully updated. If you did not perform this change, please contact support immediately.');

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully! Redirecting...',
        'username' => $user['username']
    ]);

} catch (PDOException $e) {
    error_log("ResetPassword Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to reset password due to database error.'
    ]);
} catch (Exception $e) {
    error_log("ResetPassword General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'System error. Please try again.'
    ]);
}
?>

