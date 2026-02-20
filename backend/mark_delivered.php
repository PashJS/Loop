<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$message_id = $input['message_id'] ?? null;

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Missing message ID']);
    exit();
}

try {
    // We only mark as delivered if it is NOT read and NOT delivered yet
    // And importantly, checking that the current user is the RECEIVER of this message
    $stmt = $pdo->prepare("UPDATE messages SET is_delivered = 1 WHERE id = ? AND receiver_id = ? AND is_delivered = 0");
    $stmt->execute([$message_id, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
