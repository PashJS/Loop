<?php
// backend/forgotPassword.php - Send password reset code via email
header('Content-Type: application/json');
require 'config.php';

if (!isset($_POST['email'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

$email = trim($_POST['email']);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Don't reveal if email exists for security
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this email, a reset code has been sent.'
        ]);
        exit;
    }
    
    // Generate 6-digit code
    $code = (string)mt_rand(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Create password_resets table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            code VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Delete old unused codes for this email to keep DB clean
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND (used = 1 OR expires_at < NOW())");
    $stmt->execute([$email]);
    
    // Insert new code
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $code, $expiresAt]);
    
    // Send email using unified mailer
    require_once 'mailer.php';
    
    $subject = '🔐 Password Reset Code - FloxWatch';
    $message = "<h1 style='font-size:20px;font-weight:600;margin:16px 0;color:#000;'>
  FloxWatch Password Recovery
</h1>
<svg width=\"730\" height=\"575\" viewBox=\"0 0 730 575\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\">
<rect width=\"729\" height=\"575\" fill=\"#D9D9D9\"/>
<path d=\"M365.028 386.034L1.00717 0.478721L729.561 0.962008L365.028 386.034Z\" fill=\"#BFBFBF\"/>
<rect width=\"39.0242\" height=\"61.3062\" transform=\"matrix(0.882964 0.469441 -0.479731 0.877416 298.41 175.162)\" fill=\"white\"/>
<rect width=\"39.0242\" height=\"61.3062\" transform=\"matrix(0.882964 0.469441 -0.479731 0.877416 388.462 221.203)\" fill=\"white\"/>
<rect width=\"139.581\" height=\"33.9612\" transform=\"matrix(0.888744 0.458403 -0.468587 0.883417 285.49 199.561)\" fill=\"white\"/>
<rect width=\"39.0314\" height=\"61.2948\" transform=\"matrix(-0.890292 -0.455389 0.465543 -0.885025 431.577 224.871)\" fill=\"white\"/>
<rect width=\"39.0314\" height=\"61.2948\" transform=\"matrix(-0.890292 -0.455389 0.465543 -0.885025 340.791 180.254)\" fill=\"white\"/>
<rect width=\"139.606\" height=\"33.955\" transform=\"matrix(-0.895895 -0.444265 0.4543 -0.890849 444.1 200.271)\" fill=\"white\"/>
<path d=\"M379.965 202.225L354.071 215.621L355.253 186.811L379.965 202.225Z\" fill=\"white\"/>
</svg>

<p style='font-size:14px;margin:0 0 16px 0;color:#000;'>
  You requested a password reset for your FloxWatch account.
</p>

<p style='font-size:14px;margin:0 0 6px 0;color:#000;'>
  Your temporary verification code:
</p>

<p style='font-size:28px;font-weight:700;letter-spacing:4px;margin:0 0 12px 0;color:#000;'>
    $code
</p>

<p style='font-size:13px;margin:0 0 16px 0;color:#444;'>
  This code will expire in 15 minutes.
</p>

<p style='font-size:13px;margin:0 0 12px 0;color:#000;'>
  <strong>Do not share this code with anyone.</strong>
  If someone has this code, they can reset your password.
  FloxWatch will never ask for this code.
</p>

<p style='font-size:13px;margin:0;color:#444;'>
  If you didn’t request this, you can safely ignore this email.
</p>

";

    if (sendFloxEmail($email, $subject, $message, true)) {
        echo json_encode([
            'success' => true,
            'message' => 'Password reset code sent to your email.'
        ]);
    } else {
        // Log locally if mailer fails
        error_log("Failed to send reset email to $email");
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send reset email. The SMTP server might be blocked or credentials expired. Please try again or check server logs.'
        ]);
    }
} catch (PDOException $e) {
    error_log("ForgotPassword Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("ForgotPassword General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'System error. Please try again later.'
    ]);
}
?>

