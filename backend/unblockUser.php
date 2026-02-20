<?php
// backend/unblockUser.php
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

try {
    $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$user_id, $blocked_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User unblocked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User was not blocked']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
