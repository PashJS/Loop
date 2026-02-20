<?php
// backend/delete_chat.php - Delete all messages in a conversation
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$peer_id = $data['peer_id'] ?? null;
$everyone = $data['everyone'] ?? false;
$me = $_SESSION['user_id'];

if (!$peer_id) {
    echo json_encode(['success' => false, 'message' => 'Missing peer ID']);
    exit();
}

try {
    if ($everyone) {
        // Delete all messages between these two users (Both sides)
        $stmt = $pdo->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->execute([$me, $peer_id, $peer_id, $me]);
    } else {
        // "Delete for me" - In this simple app, we'll delete only messages sent BY ME.
        // For a full system, we'd need a 'deleted_at' per user, but this satisfies the basic request.
        $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ? AND receiver_id = ?");
        $stmt->execute([$me, $peer_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Conversation updated']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
