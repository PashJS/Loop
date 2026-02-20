<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$peer_id = $data['peer_id'] ?? null;
$action = $data['action'] ?? null; // 'approve' or 'decline'
$me = $_SESSION['user_id'];

if (!$peer_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

try {
    if ($action === 'approve') {
        // Mark all messages from this person to me as approved
        $stmt = $pdo->prepare("UPDATE messages SET is_approved = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt->execute([$peer_id, $me]);
        
        // Also ensure any messages I sent back are approved (making the thread fully mutual)
        $stmt2 = $pdo->prepare("UPDATE messages SET is_approved = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt2->execute([$me, $peer_id]);

        echo json_encode(['success' => true, 'message' => 'Request approved']);
    } else {
        // Decline: Delete the pending messages from that user
        $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_approved = 0");
        $stmt->execute([$peer_id, $me]);
        echo json_encode(['success' => true, 'message' => 'Request declined']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
