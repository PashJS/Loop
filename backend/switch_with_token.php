<?php
// backend/switch_with_token.php - Switch account using stored token
header('Content-Type: application/json');
session_start();
require 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$token = isset($input['token']) ? $input['token'] : '';

if (!$userId || !$token) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    // Get stored token for user
    $stmt = $pdo->prepare("SELECT token_hash, expires_at FROM user_switch_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No valid token found', 'reauth' => true]);
        exit;
    }
    
    // Check expiry
    if (strtotime($row['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Token expired', 'reauth' => true]);
        exit;
    }
    
    // Verify token
    if (!password_verify($token, $row['token_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid token', 'reauth' => true]);
        exit;
    }
    
    // Token valid! Switch sessions
    $_SESSION['user_id'] = $userId;
    
    // Get user info to return
    $userStmt = $pdo->prepare("SELECT id, username, email, profile_picture FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'user' => $user]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
