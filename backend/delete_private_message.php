<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$message_id = $data['message_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Missing message ID']);
    exit();
}

try {
    // Only allow the sender to "delete" (mark as deleted) their own message
    $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ? AND sender_id = ?");
    $stmt->execute([$message_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        // Fetch receiver ID to facilitate WS broadcast
        $stmt_rec = $pdo->prepare("SELECT receiver_id FROM messages WHERE id = ?");
        $stmt_rec->execute([$message_id]);
        $receiver_id = $stmt_rec->fetchColumn();

        echo json_encode(['success' => true, 'receiver_id' => $receiver_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found or already deleted']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
