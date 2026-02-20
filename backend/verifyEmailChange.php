<?php
// backend/verifyEmailChange.php - Verify and apply email/password change
header('Content-Type: application/json');
session_start();
require 'config.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Verification token is required.'
    ]);
    exit;
}

try {
    // Get verification record
    $stmt = $pdo->prepare("
        SELECT user_id, verification_type, new_value, expires_at
        FROM email_verifications
        WHERE verification_token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired verification token.'
        ]);
        exit;
    }
    
    $userId = $verification['user_id'];
    $type = $verification['verification_type'];
    $newValue = $verification['new_value'];
    
    // Apply the change
    if ($type === 'email_change') {
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$newValue, $userId]);
    } else if ($type === 'password_change') {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newValue, $userId]);
    }
    
    // Delete verification record
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE verification_token = ?");
    $stmt->execute([$token]);
    
    // If user is logged in, update session
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId && $type === 'email_change') {
        // Email change doesn't require re-login, but password change does
    }
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst(str_replace('_', ' ', $type)) . ' verified and applied successfully!',
        'requires_relogin' => $type === 'password_change'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to verify change.',
        'error' => $e->getMessage()
    ]);
}
?>



