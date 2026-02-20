<?php
require 'config.php';
require 'mailer.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Account already exists']);
    exit;
}

// Generate Code
$code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Upsert Verification using MySQL time to prevent timezone mismatches
$sql = "INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE)) 
        ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email, $code]);

// Send Email
$subject = "Your FloxWatch Verification Code";
$body = "
<div style='font-family: sans-serif; padding: 20px; background: #f4f4f4; border-radius: 10px;'>
    <h2 style='color: #0071E3;'>FloxWatch</h2>
    <p>You are creating a new account.</p>
    <p>Your verification code is:</p>
    <h1 style='letter-spacing: 5px; background: #fff; padding: 10px; display: inline-block; border-radius: 8px;'>$code</h1>
    <p>This code expires in 15 minutes.</p>
</div>
";

if (sendFloxEmail($email, $subject, $body, true)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
}
?>
