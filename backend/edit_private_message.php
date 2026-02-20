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
$new_text = $data['new_text'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$message_id || $new_text === null) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

try {
    // Only allow the sender to edit their own message, and only if it's not deleted.
    $stmt = $pdo->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ? AND is_deleted = 0");
    $stmt->execute([$new_text, $message_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        // Fetch receiver ID to facilitate WS broadcast
        $stmt_rec = $pdo->prepare("SELECT receiver_id FROM messages WHERE id = ?");
        $stmt_rec->execute([$message_id]);
        $receiver_id = $stmt_rec->fetchColumn();

        echo json_encode(['success' => true, 'receiver_id' => $receiver_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Message not found, already deleted, or not your message']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
