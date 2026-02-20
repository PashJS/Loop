<?php
// backend/blockUser.php
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['blocked_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing blocked_id']);
    exit;
}

$blocked_id = (int)$input['blocked_id'];

if ($blocked_id === $user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot block yourself']);
    exit;
}

try {
    // Check if duplicate
    $check = $pdo->prepare("SELECT id FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
    $check->execute([$user_id, $blocked_id]);
    
    if ($check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Already blocked']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $blocked_id]);
    
    // Also unfollow/remove from friends if applicable - implementation depends on follow system
    
    echo json_encode(['success' => true, 'message' => 'User blocked']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
