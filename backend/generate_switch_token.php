<?php
// backend/generate_switch_token.php - Generate a token for 1-click account switching
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

// Generate a secure token
$token = bin2hex(random_bytes(32));
$tokenHash = password_hash($token, PASSWORD_DEFAULT);

// Store token (expires in 30 days)
$expires = date('Y-m-d H:i:s', strtotime('+30 days'));

try {
    // Delete old tokens for this user (keep only 1 per user for simplicity)
    $pdo->prepare("DELETE FROM user_switch_tokens WHERE user_id = ?")->execute([$userId]);
    
    // Insert new token
    $stmt = $pdo->prepare("INSERT INTO user_switch_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $tokenHash, $expires]);
    
    echo json_encode(['success' => true, 'token' => $token]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
