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
$reaction = $data['reaction'] ?? null; // e.g. "❤️", "👍", "🔥"
$user_id = $_SESSION['user_id'];

if (!$message_id || !$reaction) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

try {
    // Fetch existing reactions
    $stmt = $pdo->prepare("SELECT reactions, sender_id, receiver_id FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit();
    }

    $reactions = json_decode($msg['reactions'] ?? '{}', true);
    
    // Toggle reaction: if user already has this reaction, remove it. Else add it.
    if (!isset($reactions[$reaction])) {
        $reactions[$reaction] = [];
    }

    if (($key = array_search($user_id, $reactions[$reaction])) !== false) {
        unset($reactions[$reaction][$key]);
        $reactions[$reaction] = array_values($reactions[$reaction]); // Re-index
        if (empty($reactions[$reaction])) unset($reactions[$reaction]);
    } else {
        $reactions[$reaction][] = $user_id;
    }

    $json_reactions = json_encode($reactions);
    $pdo->prepare("UPDATE messages SET reactions = ? WHERE id = ?")->execute([$json_reactions, $message_id]);

    // Determine target (the other person in the chat)
    $target_id = ($msg['sender_id'] == $user_id) ? $msg['receiver_id'] : $msg['sender_id'];

    echo json_encode([
        'success' => true, 
        'reactions' => $reactions,
        'target_id' => $target_id
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
