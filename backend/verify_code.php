<?php
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM email_verifications WHERE email = ? AND code = ? AND expires_at > NOW()");
$stmt->execute([$email, $code]);

if ($stmt->fetch()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
}
?>
