<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'auth_helper.php';

$userId = validateRememberToken(false); // Don't set session yet

if ($userId) {
    $stmt = $pdo->prepare("SELECT id, username, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Find if we have a switch token for this user
        $stmt = $pdo->prepare("SELECT token FROM user_switch_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        $switchToken = $row ? $row['token'] : null;

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'profile_picture' => $user['profile_picture'],
                'switchToken' => $switchToken
            ]
        ]);
        exit;
    }
}

echo json_encode(['success' => false]);
