<?php
// backend/verifyResetCode.php - Verify password reset code
header('Content-Type: application/json');
require 'config.php';

if (!isset($_POST['email']) || !isset($_POST['code'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and code are required.'
    ]);
    exit;
}

$email = trim($_POST['email']);
$code = trim($_POST['code']);

try {
    // Verify code exists and is not expired
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
            'message' => 'Invalid reset code. Please check and try again.'
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
    
    // Code is valid!
    echo json_encode([
        'success' => true,
        'message' => 'Code verified successfully!'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to verify code.'
    ]);
}
?>
