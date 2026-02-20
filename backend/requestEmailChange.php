<?php
// backend/requestEmailChange.php - Request email change with verification
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$newEmail = isset($input['email']) ? trim($input['email']) : '';
$userId = $_SESSION['user_id'];

if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address.'
    ]);
    exit;
}

try {
    // Check if email is already taken
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already registered.'
        ]);
        exit;
    }
    
    // Get current user email
    $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create email_verifications table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            verification_type ENUM('email_change', 'password_change') NOT NULL,
            new_value VARCHAR(255) NOT NULL,
            verification_token VARCHAR(64) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_token (verification_token),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Delete old verification requests for this user and type
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ? AND verification_type = 'email_change'");
    $stmt->execute([$userId]);
    
    // Generate verification token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Insert verification request
    $stmt = $pdo->prepare("
        INSERT INTO email_verifications (user_id, verification_type, new_value, verification_token, expires_at)
        VALUES (?, 'email_change', ?, ?, ?)
    ");
    $stmt->execute([$userId, $newEmail, $token, $expiresAt]);
    
    // Send verification email (you'll need to configure email sending)
    $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/../frontend/verifyEmail.php?token=" . $token;
    
    // For now, we'll just return the token (in production, send email)
    // TODO: Implement actual email sending using PHPMailer or similar
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification email sent! Please check your email to confirm the change.',
        'token' => $token, // Remove this in production
        'verification_link' => $verificationLink // Remove this in production
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to request email change.',
        'error' => $e->getMessage()
    ]);
}
?>



